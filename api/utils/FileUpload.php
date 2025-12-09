<?php
/**
 * File Upload Utility Class
 */

class FileUpload {
    /**
     * Upload image file
     */
    public static function uploadImage($file, $directory, $allowedTypes = null, $maxSize = null) {
        $allowedTypes = $allowedTypes ?? ALLOWED_IMAGE_TYPES;
        $maxSize = $maxSize ?? MAX_FILE_SIZE;
        
        // Check if file was uploaded
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'error' => 'No file uploaded'];
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'File upload error: ' . $file['error']];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'File size exceeds maximum allowed size'];
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            return ['success' => false, 'error' => 'Invalid file type. Only JPG and PNG allowed'];
        }
        
        // Create directory if it doesn't exist
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = $directory . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Get relative path from project root
            $projectRoot = realpath(__DIR__ . '/../../');
            $relativePath = str_replace($projectRoot . DIRECTORY_SEPARATOR, '', realpath($filepath));
            // Convert backslashes to forward slashes for web paths
            $relativePath = str_replace('\\', '/', $relativePath);

            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'relative_path' => $relativePath
            ];
        }

        return ['success' => false, 'error' => 'Failed to move uploaded file'];
    }
    
    /**
     * Validate image dimensions (minimum)
     */
    public static function validateImageDimensions($file, $minWidth, $minHeight) {
        $imageInfo = getimagesize($file['tmp_name']);

        if (!$imageInfo) {
            return ['valid' => false, 'error' => 'Invalid image file'];
        }

        if ($imageInfo[0] < $minWidth || $imageInfo[1] < $minHeight) {
            return [
                'valid' => false,
                'error' => "Image must be at least {$minWidth}x{$minHeight} pixels. Uploaded: {$imageInfo[0]}x{$imageInfo[1]}"
            ];
        }

        return ['valid' => true];
    }
    
    /**
     * Upload audio file
     */
    public static function uploadAudio($file, $directory, $allowedTypes = null, $maxSize = null) {
        $allowedTypes = $allowedTypes ?? ALLOWED_AUDIO_TYPES;
        $maxSize = $maxSize ?? MAX_AUDIO_SIZE;

        // Check if file was uploaded
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'error' => 'No file uploaded'];
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'File upload error: ' . $file['error']];
        }

        // Check file size
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'File size exceeds maximum allowed size (50MB)'];
        }

        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            return ['success' => false, 'error' => 'Invalid file type. Only MP3, WAV, and FLAC allowed'];
        }

        // Create directory if it doesn't exist
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0777, true)) {
                error_log("Failed to create directory: " . $directory);
                return ['success' => false, 'error' => 'Failed to create upload directory'];
            }
            chmod($directory, 0777);
        }

        // Check if directory is writable
        if (!is_writable($directory)) {
            error_log("Directory is not writable: " . $directory);
            return ['success' => false, 'error' => 'Upload directory is not writable'];
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = $directory . $filename;

        // Move uploaded file
        // Use move_uploaded_file for regular uploads, rename for PUT requests with temp files
        $moved = false;
        if (is_uploaded_file($file['tmp_name'])) {
            $moved = move_uploaded_file($file['tmp_name'], $filepath);
        } else {
            // For PUT requests where we created a temp file manually
            if (file_exists($file['tmp_name'])) {
                $moved = rename($file['tmp_name'], $filepath);
            } else {
                error_log("Temp file does not exist: " . $file['tmp_name']);
                return ['success' => false, 'error' => 'Temporary file not found'];
            }
        }

        if ($moved) {
            // Get relative path from project root
            $projectRoot = realpath(__DIR__ . '/../../');
            $relativePath = str_replace($projectRoot . DIRECTORY_SEPARATOR, '', realpath($filepath));
            // Convert backslashes to forward slashes for web paths
            $relativePath = str_replace('\\', '/', $relativePath);

            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'relative_path' => $relativePath
            ];
        }

        error_log("Failed to move file from {$file['tmp_name']} to {$filepath}");
        return ['success' => false, 'error' => 'Failed to move uploaded file'];
    }

    /**
     * Delete file
     */
    public static function deleteFile($filepath) {
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        return false;
    }
}

