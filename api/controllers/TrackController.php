<?php
/**
 * Track Controller
 * Handles track-related operations
 */

class TrackController {
    /**
     * Get all tracks for a release
     * GET /api/releases/{release_id}/tracks
     */
    public static function getReleaseTracks($releaseId) {
        $userId = AuthMiddleware::authenticate();
        
        // Verify release ownership
        $releaseModel = new Release();
        $release = $releaseModel->findById($releaseId);
        
        if (!$release) {
            Response::notFound('Release not found');
        }
        
        if ($release['user_id'] != $userId) {
            Response::forbidden('You do not have access to this release');
        }
        
        $trackModel = new Track();
        $tracks = $trackModel->getByReleaseId($releaseId);
        
        Response::success($tracks);
    }
    
    /**
     * Get single track
     * GET /api/tracks/{id}
     */
    public static function getTrack($id) {
        $userId = AuthMiddleware::authenticate();
        
        $trackModel = new Track();
        $track = $trackModel->findById($id);
        
        if (!$track) {
            Response::notFound('Track not found');
        }
        
        // Verify ownership through release
        $releaseModel = new Release();
        $release = $releaseModel->findById($track['release_id']);
        
        if ($release['user_id'] != $userId) {
            Response::forbidden('You do not have access to this track');
        }
        
        // Get track artists
        $track['artists'] = $trackModel->getTrackArtists($id);
        
        Response::success($track);
    }
    
    /**
     * Create new track
     * POST /api/releases/{release_id}/tracks
     */
    public static function createTrack($releaseId) {
        $userId = AuthMiddleware::authenticate();
        
        // Verify release ownership
        $releaseModel = new Release();
        $release = $releaseModel->findById($releaseId);
        
        if (!$release) {
            Response::notFound('Release not found');
        }
        
        if ($release['user_id'] != $userId) {
            Response::forbidden('You do not have access to this release');
        }
        
        // Handle multipart form data
        $data = $_POST;
        $data['release_id'] = $releaseId;
        
        // Validate input
        $validator = new Validator();
        $validator->required('track_title', $data['track_title'] ?? '')
                  ->required('track_number', $data['track_number'] ?? '')
                  ->numeric('track_number', $data['track_number'] ?? '')
                  ->required('language', $data['language'] ?? '')
                  ->required('explicit_content', $data['explicit_content'] ?? '');
        
        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }

        // Handle pre-uploaded audio file (from progress upload)
        if (!empty($data['uploaded_audio_path'])) {
            $tempPath = __DIR__ . '/../../' . $data['uploaded_audio_path'];
            if (file_exists($tempPath)) {
                // Move from temp to permanent location
                $filename = basename($data['uploaded_audio_path']);
                // AUDIO_DIR is already a full path, use it directly
                $permanentDir = AUDIO_DIR;
                if (!file_exists($permanentDir)) {
                    mkdir($permanentDir, 0755, true);
                }
                $permanentPath = $permanentDir . $filename;

                if (rename($tempPath, $permanentPath)) {
                    // Store relative path in database
                    $data['audio_file_path'] = 'uploads/audio/' . $filename;
                } else {
                    Response::error('Failed to move uploaded audio file');
                }
            } else {
                Response::error('Uploaded audio file not found');
            }
        }
        // Handle audio file upload if provided (fallback for direct upload)
        elseif (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['audio_file']['error'] !== UPLOAD_ERR_OK) {
                Response::error('Audio file upload failed');
            }

            $uploadResult = FileUpload::uploadAudio($_FILES['audio_file'], AUDIO_DIR);
            if (!$uploadResult['success']) {
                Response::error($uploadResult['error']);
            }

            $data['audio_file_path'] = $uploadResult['relative_path'];
        }

        // Parse all artist types from JSON
        $allArtists = [];

        // Display artists
        if (!empty($data['display_artists'])) {
            $displayArtists = json_decode($data['display_artists'], true);
            error_log("Display artists: " . print_r($displayArtists, true));
            if (is_array($displayArtists)) {
                $allArtists = array_merge($allArtists, $displayArtists);
            }
        }

        // Writers
        if (!empty($data['writers'])) {
            $writers = json_decode($data['writers'], true);
            error_log("Writers: " . print_r($writers, true));
            if (is_array($writers)) {
                $allArtists = array_merge($allArtists, $writers);
            }
        }

        // Production
        if (!empty($data['production'])) {
            $production = json_decode($data['production'], true);
            error_log("Production: " . print_r($production, true));
            if (is_array($production)) {
                $allArtists = array_merge($allArtists, $production);
            }
        }

        // Performers
        if (!empty($data['performers'])) {
            $performers = json_decode($data['performers'], true);
            error_log("Performers: " . print_r($performers, true));
            if (is_array($performers)) {
                $allArtists = array_merge($allArtists, $performers);
            }
        }

        error_log("All artists combined: " . print_r($allArtists, true));

        // Add all artists to data
        if (!empty($allArtists)) {
            $data['artists'] = $allArtists;
        }

        // Add recording metadata
        if (!empty($data['recording_year'])) {
            $data['recording_year'] = $data['recording_year'];
        }
        if (!empty($data['recording_country'])) {
            $data['recording_country'] = $data['recording_country'];
        }

        $trackModel = new Track();

        try {
            $trackId = $trackModel->create($data);

            // Save selected stores to release_stores table
            if (!empty($data['selected_stores'])) {
                $selectedStores = json_decode($data['selected_stores'], true);
                if (is_array($selectedStores) && !empty($selectedStores)) {
                    $releaseModel = new Release();

                    // Delete existing stores for this release
                    $releaseModel->deleteReleaseStores($releaseId);

                    // Insert new stores
                    foreach ($selectedStores as $storeName) {
                        $releaseModel->addStore($releaseId, $storeName);
                    }
                }
            }

            Response::success(['track_id' => $trackId], 'Track created successfully');
        } catch (Exception $e) {
            error_log("Error creating track: " . $e->getMessage());
            Response::serverError('Failed to create track');
        }
    }
    
    /**
     * Update track
     * PUT /api/tracks/{id}
     */
    public static function updateTrack($id) {
        $userId = AuthMiddleware::authenticate();

        $trackModel = new Track();
        $track = $trackModel->findById($id);

        if (!$track) {
            Response::notFound('Track not found');
        }

        // Verify ownership through release
        $releaseModel = new Release();
        $release = $releaseModel->findById($track['release_id']);

        if ($release['user_id'] != $userId) {
            Response::forbidden('You do not have access to this track');
        }

        // Handle multipart form data for PUT request
        $_PUT = [];
        $_FILES_PUT = [];

        if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            // Parse multipart form data for PUT request
            $raw_data = file_get_contents('php://input');
            $boundary = substr($raw_data, 0, strpos($raw_data, "\r\n"));

            if ($boundary) {
                $parts = array_slice(explode($boundary, $raw_data), 1);

                foreach ($parts as $part) {
                    if ($part == "--\r\n") break;

                    $part = ltrim($part, "\r\n");
                    if (empty($part)) continue;

                    list($raw_headers, $body) = explode("\r\n\r\n", $part, 2);
                    $body = substr($body, 0, strlen($body) - 2);

                    $raw_headers = explode("\r\n", $raw_headers);
                    $headers = [];
                    foreach ($raw_headers as $header) {
                        if (strpos($header, ':') !== false) {
                            list($name, $value) = explode(':', $header, 2);
                            $headers[strtolower($name)] = ltrim($value, ' ');
                        }
                    }

                    if (isset($headers['content-disposition'])) {
                        preg_match('/name="([^"]+)"/', $headers['content-disposition'], $name_match);
                        $field_name = $name_match[1] ?? '';

                        // Check if it's a file
                        if (preg_match('/filename="([^"]+)"/', $headers['content-disposition'], $file_match)) {
                            $filename = $file_match[1];
                            $tmp_name = tempnam(sys_get_temp_dir(), 'php');
                            file_put_contents($tmp_name, $body);

                            $_FILES_PUT[$field_name] = [
                                'name' => $filename,
                                'type' => $headers['content-type'] ?? 'application/octet-stream',
                                'tmp_name' => $tmp_name,
                                'error' => UPLOAD_ERR_OK,
                                'size' => strlen($body)
                            ];
                        } else {
                            $_PUT[$field_name] = $body;
                        }
                    }
                }
            }

            $data = $_PUT;
            $_FILES = array_merge($_FILES, $_FILES_PUT);
        } else {
            $data = $_POST;
        }

        // Handle audio file upload if new audio is provided
        $audioPath = null;

        // Handle pre-uploaded audio file (from progress upload)
        if (!empty($data['uploaded_audio_path'])) {
            $tempPath = __DIR__ . '/../../' . $data['uploaded_audio_path'];
            if (file_exists($tempPath)) {
                // Move from temp to permanent location
                $filename = basename($data['uploaded_audio_path']);
                // AUDIO_DIR is already a full path, use it directly
                $permanentDir = AUDIO_DIR;
                if (!file_exists($permanentDir)) {
                    mkdir($permanentDir, 0755, true);
                }
                $permanentPath = $permanentDir . $filename;

                if (rename($tempPath, $permanentPath)) {
                    // Store relative path in database
                    $audioPath = 'uploads/audio/' . $filename;

                    // Delete old audio if exists
                    if (!empty($track['audio_file_path']) && file_exists(__DIR__ . '/../../' . $track['audio_file_path'])) {
                        @unlink(__DIR__ . '/../../' . $track['audio_file_path']);
                    }
                } else {
                    Response::error('Failed to move uploaded audio file');
                }
            }
        }
        // Handle direct audio file upload (fallback)
        elseif (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            error_log("Processing audio file upload for track update");
            error_log("File info: " . print_r($_FILES['audio_file'], true));

            if ($_FILES['audio_file']['error'] !== UPLOAD_ERR_OK) {
                error_log("Audio upload error code: " . $_FILES['audio_file']['error']);
                Response::error('Audio upload failed with error code: ' . $_FILES['audio_file']['error']);
            }

            $uploadResult = FileUpload::uploadAudio($_FILES['audio_file'], AUDIO_DIR);
            error_log("Upload result: " . print_r($uploadResult, true));

            if (!$uploadResult['success']) {
                Response::error($uploadResult['error']);
            }

            $audioPath = $uploadResult['relative_path'];

            // Delete old audio if exists
            if (!empty($track['audio_file_path']) && file_exists('../' . $track['audio_file_path'])) {
                @unlink('../' . $track['audio_file_path']);
            }
        }

        // Prepare update data
        $updateData = [
            'track_title' => $data['track_title'] ?? $track['track_title'],
            'track_version' => $data['track_version'] ?? $track['track_version'],
            'explicit_content' => $data['explicit_content'] ?? $track['explicit_content'],
            'audio_style' => $data['audio_style'] ?? $track['audio_style'],
            'language' => $data['language'] ?? $track['language'],
            'preview_start' => $data['preview_start'] ?? $track['preview_start'],
            'release_date' => $data['release_date'] ?? $track['release_date'],
            'release_time' => $data['release_time'] ?? $track['release_time'],
            'worldwide_release' => $data['worldwide_release'] ?? $track['worldwide_release']
        ];

        // Add audio path if new audio was uploaded
        if ($audioPath) {
            $updateData['audio_file_path'] = $audioPath;
        }

        // Parse all artist types from JSON
        $allArtists = [];

        // Display artists
        if (!empty($data['display_artists'])) {
            $displayArtists = json_decode($data['display_artists'], true);
            if (is_array($displayArtists)) {
                $allArtists = array_merge($allArtists, $displayArtists);
            }
        }

        // Writers
        if (!empty($data['writers'])) {
            $writers = json_decode($data['writers'], true);
            if (is_array($writers)) {
                $allArtists = array_merge($allArtists, $writers);
            }
        }

        // Production
        if (!empty($data['production'])) {
            $production = json_decode($data['production'], true);
            if (is_array($production)) {
                $allArtists = array_merge($allArtists, $production);
            }
        }

        // Performers
        if (!empty($data['performers'])) {
            $performers = json_decode($data['performers'], true);
            if (is_array($performers)) {
                $allArtists = array_merge($allArtists, $performers);
            }
        }

        try {
            $trackModel->update($id, $updateData);

            // Update track artists if provided
            if (!empty($allArtists)) {
                // Delete existing artists
                $trackModel->deleteTrackArtists($id);

                // Insert new artists
                foreach ($allArtists as $artist) {
                    $trackModel->addArtist($id, $artist['name'], $artist['role'], $artist['type']);
                }
            }

            // Update track metadata (recording year and country)
            // Always update metadata if the fields are present (even if empty)
            if (isset($data['recording_year']) || isset($data['recording_country'])) {
                error_log("Updating track metadata - Year: " . ($data['recording_year'] ?? 'null') . ", Country: " . ($data['recording_country'] ?? 'null'));

                // Check if metadata exists
                $existingMetadata = $trackModel->getMetadata($id);

                $metadataData = [
                    'recording_year' => !empty($data['recording_year']) ? $data['recording_year'] : null,
                    'recording_country' => !empty($data['recording_country']) ? $data['recording_country'] : null
                ];

                if ($existingMetadata) {
                    error_log("Updating existing metadata");
                    $trackModel->updateMetadata($id, $metadataData);
                } else {
                    error_log("Creating new metadata");
                    $trackModel->createMetadata($id, $metadataData);
                }
            }

            // Update selected stores in release_stores table
            if (!empty($data['selected_stores'])) {
                $selectedStores = json_decode($data['selected_stores'], true);
                if (is_array($selectedStores)) {
                    // Get the release_id for this track
                    $track = $trackModel->findById($id);
                    if ($track && !empty($track['release_id'])) {
                        $releaseModel = new Release();

                        // Delete existing stores for this release
                        $releaseModel->deleteReleaseStores($track['release_id']);

                        // Insert new stores
                        foreach ($selectedStores as $storeName) {
                            $releaseModel->addStore($track['release_id'], $storeName);
                        }
                    }
                }
            }

            Response::success(['track_id' => $id], 'Track updated successfully');
        } catch (Exception $e) {
            error_log("Error updating track: " . $e->getMessage());
            Response::serverError('Failed to update track');
        }
    }

    /**
     * Delete track
     * DELETE /api/tracks/{id}
     */
    public static function deleteTrack($id) {
        $userId = AuthMiddleware::authenticate();

        $trackModel = new Track();
        $track = $trackModel->findById($id);

        if (!$track) {
            Response::notFound('Track not found');
        }

        // Verify ownership through release
        $releaseModel = new Release();
        $release = $releaseModel->findById($track['release_id']);

        if ($release['user_id'] != $userId) {
            Response::forbidden('You do not have access to this track');
        }

        try {
            // Delete track artists first
            $trackModel->deleteTrackArtists($id);

            // Delete track
            $trackModel->delete($id);

            Response::success(null, 'Track deleted successfully');
        } catch (Exception $e) {
            error_log("Error deleting track: " . $e->getMessage());
            Response::serverError('Failed to delete track');
        }
    }

    /**
     * Upload audio file
     * POST /api/audio-upload
     */
    public static function uploadAudio() {
        $userId = AuthMiddleware::authenticate();

        try {
            // Check if file was uploaded
            if (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] !== UPLOAD_ERR_OK) {
                $errorMessage = 'No audio file uploaded';
                if (isset($_FILES['audio_file']['error'])) {
                    switch ($_FILES['audio_file']['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE:
                            $errorMessage = 'File is too large';
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $errorMessage = 'File was only partially uploaded';
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            $errorMessage = 'No file was uploaded';
                            break;
                    }
                }
                Response::error($errorMessage);
                return;
            }

            $file = $_FILES['audio_file'];

            // Validate file size (300MB limit)
            $maxSize = 300 * 1024 * 1024; // 300MB in bytes
            if ($file['size'] > $maxSize) {
                Response::error('Audio file must be less than 300MB');
                return;
            }

            // Validate file type
            $allowedTypes = ['audio/wav', 'audio/x-wav', 'audio/flac', 'audio/x-flac', 'audio/aiff', 'audio/x-aiff'];
            $allowedExtensions = ['wav', 'flac', 'aiff', 'aif'];

            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes) && !in_array($fileExtension, $allowedExtensions)) {
                Response::error('Invalid audio file type. Only WAV, FLAC, and AIFF files are allowed.');
                return;
            }

            // Create upload directory if it doesn't exist
            $uploadDir = __DIR__ . '/../../uploads/audio/temp/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename
            $uniqueName = uniqid('audio_') . '_' . time() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $uniqueName;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                Response::serverError('Failed to save audio file');
                return;
            }

            // Return the temporary file path
            Response::success([
                'temp_file' => 'uploads/audio/temp/' . $uniqueName,
                'original_name' => $file['name'],
                'size' => $file['size']
            ], 'Audio file uploaded successfully');

        } catch (Exception $e) {
            error_log("Error uploading audio: " . $e->getMessage());
            Response::serverError('Failed to upload audio file');
        }
    }
}


