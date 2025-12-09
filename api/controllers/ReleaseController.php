<?php
/**
 * Release Controller
 * Handles release-related operations
 */

class ReleaseController {
    /**
     * Get all releases for current user
     */
    public static function getAllReleases() {
        $userId = AuthMiddleware::authenticate();
        
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? DEFAULT_PAGE_SIZE;
        
        $releaseModel = new Release();
        $releases = $releaseModel->getByUserId($userId, $page, $limit);
        $total = $releaseModel->countByUserId($userId);
        
        Response::success([
            'releases' => $releases,
            'pagination' => [
                'page' => (int)$page,
                'limit' => (int)$limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    /**
     * Get single release
     */
    public static function getRelease($id) {
        $userId = AuthMiddleware::authenticate();

        $releaseModel = new Release();
        $release = $releaseModel->findById($id);

        if (!$release) {
            Response::notFound('Release not found');
        }

        // Check ownership OR collaborator access
        if ($release['user_id'] != $userId) {
            // Check if user is a collaborator on this release
            if (!self::isCollaborator($userId, $id)) {
                Response::forbidden('You do not have access to this release');
            }
        }

        // Get tracks
        $trackModel = new Track();
        $release['tracks'] = $trackModel->getByReleaseId($id);

        Response::success($release);
    }

    /**
     * Check if user is a collaborator on a release
     */
    private static function isCollaborator($userId, $releaseId) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT id FROM split_shares
            WHERE release_id = ? AND user_id = ? AND status = 'accepted'
        ");
        $stmt->execute([$releaseId, $userId]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Get next catalog number
     */
    public static function getNextCatalog() {
        $userId = AuthMiddleware::authenticate();

        $releaseModel = new Release();
        $catalogNumber = $releaseModel->getNextCatalogNumber($userId);

        Response::success(['catalog_number' => $catalogNumber]);
    }

    /**
     * Create new release
     */
    public static function createRelease() {
        $userId = AuthMiddleware::authenticate();

        // Handle multipart form data
        $data = $_POST;

        // Validate input
        $validator = new Validator();
        $validator->required('release_title', $data['release_title'] ?? '')
                  ->required('genre', $data['genre'] ?? '')
                  ->required('label_name', $data['label_name'] ?? '')
                  ->required('num_tracks', $data['num_tracks'] ?? '')
                  ->numeric('num_tracks', $data['num_tracks'] ?? '')
                  ->min('num_tracks', $data['num_tracks'] ?? 0, 1);

        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }

        // Validate at least one artist
        if (empty($data['stage_names']) || !is_array($data['stage_names']) || empty(trim($data['stage_names'][0] ?? ''))) {
            Response::validationError(['stage_names' => 'At least one artist is required']);
        }

        // Handle artwork upload
        $artworkPath = null;
        if (isset($_FILES['artwork']) && $_FILES['artwork']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['artwork']['error'] !== UPLOAD_ERR_OK) {
                Response::error('Artwork upload failed');
            }

            // Validate dimensions
            $dimensionCheck = FileUpload::validateImageDimensions($_FILES['artwork'], 3000, 3000);
            if (!$dimensionCheck['valid']) {
                Response::error($dimensionCheck['error']);
            }

            $uploadResult = FileUpload::uploadImage($_FILES['artwork'], ARTWORK_DIR);
            if (!$uploadResult['success']) {
                Response::error($uploadResult['error']);
            }

            $artworkPath = $uploadResult['relative_path'];
        }

        $releaseModel = new Release();

        // Get next catalog number
        $catalogNumber = $releaseModel->getNextCatalogNumber($userId);

        // Create release (now handles artists internally)
        // When user creates release, flags are 0 (not set by admin)
        $releaseId = $releaseModel->create([
            'user_id' => $userId,
            'release_title' => $data['release_title'],
            'release_version' => $data['release_version'] ?? null,
            'catalog_number' => $catalogNumber,
            'upc' => ($data['upc_option'] ?? 'manual') === 'manual' ? ($data['upc'] ?? null) : null,
            'isrc' => ($data['isrc_option'] ?? 'manual') === 'manual' ? ($data['isrc'] ?? null) : null,
            'upc_set_by_admin' => 0,
            'isrc_set_by_admin' => 0,
            'genre' => $data['genre'],
            'subgenre' => $data['subgenre'] ?? null,
            'label_name' => $data['label_name'],
            'c_line_year' => $data['c_line_year'] ?? null,
            'c_line_text' => $data['c_line_text'] ?? null,
            'p_line_year' => $data['p_line_year'] ?? null,
            'p_line_text' => $data['p_line_text'] ?? null,
            'num_tracks' => $data['num_tracks'],
            'pricing_tier' => $data['pricing_tier'] ?? null,
            'artwork_path' => $artworkPath,
            'release_time' => $data['release_time'] ?? '',
            'stage_names' => $data['stage_names'] ?? []
        ]);

        Response::success(['release_id' => $releaseId], 'Release created successfully', 201);
    }
    
    /**
     * Update release
     */
    public static function updateRelease($id) {
        $userId = AuthMiddleware::authenticate();

        $releaseModel = new Release();
        $release = $releaseModel->findById($id);

        if (!$release) {
            Response::notFound('Release not found');
        }

        if ($release['user_id'] != $userId) {
            Response::forbidden('You do not have access to this release');
        }

        // Handle multipart form data for PUT request
        // PHP doesn't populate $_POST for PUT requests, so we need to parse it manually
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
                            // Handle array fields (e.g., stage_names[])
                            if (strpos($field_name, '[]') !== false) {
                                $field_name = str_replace('[]', '', $field_name);
                                if (!isset($_PUT[$field_name])) {
                                    $_PUT[$field_name] = [];
                                }
                                $_PUT[$field_name][] = $body;
                            } else {
                                $_PUT[$field_name] = $body;
                            }
                        }
                    }
                }
            }

            $data = $_PUT;
            $_FILES = array_merge($_FILES, $_FILES_PUT);
        } else {
            $data = $_POST;
        }

        // Validate input
        $validator = new Validator();
        $validator->required('release_title', $data['release_title'] ?? '')
                  ->required('genre', $data['genre'] ?? '')
                  ->required('label_name', $data['label_name'] ?? '');

        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }

        // Validate at least one artist
        if (empty($data['stage_names']) || !is_array($data['stage_names']) || empty(trim($data['stage_names'][0] ?? ''))) {
            Response::validationError(['stage_names' => 'At least one artist is required']);
        }

        // Handle artwork upload if new artwork is provided
        $artworkPath = null;
        if (isset($_FILES['artwork']) && $_FILES['artwork']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['artwork']['error'] !== UPLOAD_ERR_OK) {
                Response::error('Artwork upload failed');
            }

            // Validate dimensions
            $dimensionCheck = FileUpload::validateImageDimensions($_FILES['artwork'], 3000, 3000);
            if (!$dimensionCheck['valid']) {
                Response::error($dimensionCheck['error']);
            }

            $uploadResult = FileUpload::uploadImage($_FILES['artwork'], ARTWORK_DIR);
            if (!$uploadResult['success']) {
                Response::error($uploadResult['error']);
            }

            $artworkPath = $uploadResult['relative_path'];

            // Delete old artwork if exists
            if (!empty($release['artwork_path']) && file_exists('../' . $release['artwork_path'])) {
                @unlink('../' . $release['artwork_path']);
            }
        }

        // Prepare update data
        $updateData = [
            'release_title' => $data['release_title'],
            'release_version' => $data['release_version'] ?? null,
            'genre' => $data['genre'],
            'subgenre' => $data['subgenre'] ?? null,
            'label_name' => $data['label_name'],
            'c_line_year' => $data['c_line_year'] ?? null,
            'c_line_text' => $data['c_line_text'] ?? null,
            'p_line_year' => $data['p_line_year'] ?? null,
            'p_line_text' => $data['p_line_text'] ?? null,
            'pricing_tier' => $data['pricing_tier'] ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Only allow user to update UPC if not set by admin
        if (!$release['upc_set_by_admin']) {
            $updateData['upc'] = ($data['upc_option'] ?? 'manual') === 'manual' ? ($data['upc'] ?? null) : null;
            $updateData['upc_set_by_admin'] = 0; // Ensure flag stays 0 when user sets it
        }

        // Only allow user to update ISRC if not set by admin
        if (!$release['isrc_set_by_admin']) {
            $updateData['isrc'] = ($data['isrc_option'] ?? 'manual') === 'manual' ? ($data['isrc'] ?? null) : null;
            $updateData['isrc_set_by_admin'] = 0; // Ensure flag stays 0 when user sets it
        }

        // Add artwork path if new artwork was uploaded
        if ($artworkPath) {
            $updateData['artwork_path'] = $artworkPath;
        }

        // Update release
        $releaseModel->update($id, $updateData);

        // Update artists - delete old ones and insert new ones
        $releaseModel->deleteReleaseArtists($id);
        foreach ($data['stage_names'] as $stageName) {
            if (!empty(trim($stageName))) {
                $releaseModel->addArtist($id, trim($stageName));
            }
        }

        Response::success(['release_id' => $id], 'Release updated successfully');
    }
    
    /**
     * Delete release
     */
    public static function deleteRelease($id) {
        $userId = AuthMiddleware::authenticate();
        
        $releaseModel = new Release();
        $release = $releaseModel->findById($id);
        
        if (!$release) {
            Response::notFound('Release not found');
        }
        
        if ($release['user_id'] != $userId) {
            Response::forbidden('You do not have access to this release');
        }
        
        $releaseModel->delete($id);
        
        Response::success(null, 'Release deleted successfully');
    }
}

