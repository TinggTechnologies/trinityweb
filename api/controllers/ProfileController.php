<?php
/**
 * Profile Controller
 * Handles user profile operations
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/FileUpload.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class ProfileController {
    
    /**
     * Get user profile
     * GET /api/profile
     */
    public static function getProfile() {
        $userId = AuthMiddleware::authenticate();
        
        $userModel = new User();
        $profile = $userModel->getProfile($userId);
        
        if (!$profile) {
            Response::error('Profile not found', 404);
        }
        
        Response::success($profile);
    }
    
    /**
     * Update user profile
     * PUT /api/profile
     */
    public static function updateProfile() {
        $userId = AuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $requiredFields = ['first_name', 'last_name', 'stage_name', 'email', 'mobile_number', 'origin_country', 'residence_country'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                Response::error("Field '$field' is required", 400);
            }
        }
        
        // Validate email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email format', 400);
        }
        
        // Check if email is already taken by another user
        $userModel = new User();
        $existingUser = $userModel->findByEmail($data['email']);
        if ($existingUser && $existingUser['id'] != $userId) {
            Response::error('Email already in use', 400);
        }
        
        // Prepare update data
        $updateData = [
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'stage_name' => trim($data['stage_name']),
            'email' => trim($data['email']),
            'mobile_number' => trim($data['mobile_number']),
            'origin_country' => trim($data['origin_country']),
            'residence_country' => trim($data['residence_country']),
            'artist_bio' => trim($data['artist_bio'] ?? '')
        ];
        
        try {
            $userModel->update($userId, $updateData);
            
            // Update social media if provided
            if (isset($data['social_media']) && is_array($data['social_media'])) {
                $userModel->updateSocialMedia($userId, $data['social_media']);
            }
            
            Response::success(null, 'Profile updated successfully');
        } catch (Exception $e) {
            Response::error('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Upload profile images
     * POST /api/profile/images
     */
    public static function uploadImages() {
        $userId = AuthMiddleware::authenticate();

        if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
            Response::error('No images provided', 400);
        }

        // Use absolute path from document root
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/profile_images/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Relative path for database storage
        $dbPath = 'uploads/profile_images/';
        
        $userModel = new User();
        $uploadedImages = [];
        $totalFiles = count($_FILES['images']['name']);
        
        // Check if user has any existing images
        $profile = $userModel->getProfile($userId);
        $hasExistingImages = !empty($profile['images']);
        
        for ($i = 0; $i < $totalFiles; $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                // Validate image
                $imageInfo = getimagesize($_FILES['images']['tmp_name'][$i]);
                if ($imageInfo === false) {
                    Response::error("File is not a valid image", 400);
                }
                
                // Check dimensions
                list($width, $height) = $imageInfo;
                if ($width < 50 || $height < 50) {
                    Response::error("Image must be at least 50x50 pixels", 400);
                }
                
                // Generate filename
                $fileExt = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                $fileName = 'user_' . $userId . '_' . time() . '_' . $i . '.' . $fileExt;
                $targetPath = $uploadDir . $fileName;
                $dbStoragePath = $dbPath . $fileName;

                if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $targetPath)) {
                    // Make first uploaded image primary if no existing images
                    $isPrimary = (!$hasExistingImages && $i == 0) ? 1 : 0;
                    $imageId = $userModel->addImage($userId, $dbStoragePath, $isPrimary);
                    $uploadedImages[] = [
                        'id' => $imageId,
                        'path' => $dbStoragePath,
                        'is_primary' => $isPrimary
                    ];
                } else {
                    Response::error("Failed to upload image", 500);
                }
            }
        }
        
        Response::success([
            'images' => $uploadedImages,
            'count' => count($uploadedImages)
        ], count($uploadedImages) . ' image(s) uploaded successfully', 201);
    }

    /**
     * Delete profile image
     * DELETE /api/profile/images/{id}
     */
    public static function deleteImage($imageId) {
        $userId = AuthMiddleware::authenticate();

        if (!$imageId || !is_numeric($imageId)) {
            Response::error('Invalid image ID', 400);
        }

        $userModel = new User();

        try {
            $result = $userModel->deleteImage($imageId, $userId);

            if (!$result) {
                Response::error('Image not found or unauthorized', 404);
            }

            Response::success(null, 'Image deleted successfully');
        } catch (Exception $e) {
            Response::error('Failed to delete image: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Set primary image
     * PUT /api/profile/images/{id}/primary
     */
    public static function setPrimaryImage($imageId) {
        $userId = AuthMiddleware::authenticate();

        if (!$imageId || !is_numeric($imageId)) {
            Response::error('Invalid image ID', 400);
        }

        $userModel = new User();

        try {
            $userModel->setPrimaryImage($imageId, $userId);
            Response::success(null, 'Primary image updated successfully');
        } catch (Exception $e) {
            Response::error('Failed to set primary image: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update password
     * PUT /api/profile/password
     */
    public static function updatePassword() {
        $userId = AuthMiddleware::authenticate();

        $data = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        if (!isset($data['old_password']) || !isset($data['new_password']) || !isset($data['confirm_password'])) {
            Response::error('All password fields are required', 400);
        }

        if ($data['new_password'] !== $data['confirm_password']) {
            Response::error('New passwords do not match', 400);
        }

        if (strlen($data['new_password']) < 8) {
            Response::error('Password must be at least 8 characters', 400);
        }

        $userModel = new User();

        // Verify old password
        if (!$userModel->verifyPassword($userId, $data['old_password'])) {
            Response::error('Old password is incorrect', 400);
        }

        try {
            $userModel->updatePassword($userId, $data['new_password']);
            Response::success(null, 'Password updated successfully');
        } catch (Exception $e) {
            Response::error('Failed to update password: ' . $e->getMessage(), 500);
        }
    }
}
