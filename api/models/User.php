<?php
/**
 * User Model
 * Handles all user-related database operations
 */

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Create new user
     */
    public function create($data) {
        $sql = "INSERT INTO users (first_name, last_name, email, password, verification_token, is_verified, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['password'],
            $data['verification_token'],
            $data['is_verified'] ?? 0
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Find user by ID
     */
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Find user by email
     */
    public function findByEmail($email) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    /**
     * Find user by verification token
     */
    public function findByVerificationToken($token) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE verification_token = ? AND is_verified = 0");
        $stmt->execute([$token]);
        return $stmt->fetch();
    }
    
    /**
     * Update user
     */
    public function update($id, $data) {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        
        $values[] = $id;
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
    
    /**
     * Verify user email
     */
    public function verifyEmail($id) {
        $stmt = $this->db->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Get all users with pagination
     */
    public function getAll($page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT 
                    u.id, u.first_name, u.last_name, u.email, u.stage_name, 
                    u.mobile_number, u.origin_country, u.residence_country, 
                    u.profile_image, u.created_at, u.is_verified,
                    COUNT(DISTINCT r.id) as total_releases,
                    COUNT(DISTINCT pm.id) as payment_methods
                FROM users u
                LEFT JOIN releases r ON u.id = r.user_id
                LEFT JOIN payment_methods pm ON u.id = pm.user_id
                GROUP BY u.id
                ORDER BY u.created_at DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get total user count
     */
    public function count() {
        $stmt = $this->db->query("SELECT COUNT(*) FROM users");
        return $stmt->fetchColumn();
    }
    
    /**
     * Delete user
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Get user profile with images and social media
     */
    public function getProfile($id) {
        $user = $this->findById($id);

        if (!$user) {
            return null;
        }

        // Remove password from response
        unset($user['password']);

        // Get user images
        $stmt = $this->db->prepare("SELECT * FROM user_images WHERE user_id = ? ORDER BY is_primary DESC, uploaded_at DESC");
        $stmt->execute([$id]);
        $user['images'] = $stmt->fetchAll();

        // If profile_image is null but there's a primary image, use it
        if (empty($user['profile_image']) && !empty($user['images'])) {
            foreach ($user['images'] as $image) {
                if ($image['is_primary']) {
                    $user['profile_image'] = $image['image_path'];

                    // Sync to database for future requests
                    $stmt = $this->db->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                    $stmt->execute([$image['image_path'], $id]);
                    break;
                }
            }
        }

        // Get social media links
        $stmt = $this->db->prepare("SELECT platform, url FROM user_social_media WHERE user_id = ?");
        $stmt->execute([$id]);
        $socialMediaResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convert to platform => url format
        $socialMedia = [];
        foreach ($socialMediaResults as $sm) {
            $socialMedia[$sm['platform']] = $sm['url'];
        }
        $user['social_media'] = $socialMedia;

        return $user;
    }

    /**
     * Update user social media
     */
    public function updateSocialMedia($userId, $socialMediaData) {
        // Delete existing social media
        $stmt = $this->db->prepare("DELETE FROM user_social_media WHERE user_id = ?");
        $stmt->execute([$userId]);

        // Insert new social media
        if (!empty($socialMediaData)) {
            $stmt = $this->db->prepare("INSERT INTO user_social_media (user_id, platform, url) VALUES (?, ?, ?)");
            foreach ($socialMediaData as $platform => $url) {
                if (!empty($url)) {
                    $stmt->execute([$userId, $platform, $url]);
                }
            }
        }

        return true;
    }

    /**
     * Add user image
     */
    public function addImage($userId, $imagePath, $isPrimary = 0) {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("INSERT INTO user_images (user_id, image_path, is_primary, uploaded_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$userId, $imagePath, $isPrimary]);
            $imageId = $this->db->lastInsertId();

            // If this is set as primary, update users.profile_image
            if ($isPrimary) {
                $stmt = $this->db->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                $stmt->execute([$imagePath, $userId]);
            }

            $this->db->commit();
            return $imageId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Delete user image
     */
    public function deleteImage($imageId, $userId) {
        $this->db->beginTransaction();

        try {
            // Get image data before deletion
            $stmt = $this->db->prepare("SELECT image_path, is_primary FROM user_images WHERE id = ? AND user_id = ?");
            $stmt->execute([$imageId, $userId]);
            $imageData = $stmt->fetch();

            if (!$imageData) {
                $this->db->rollBack();
                return false;
            }

            $wasPrimary = $imageData['is_primary'];

            // Delete from database
            $stmt = $this->db->prepare("DELETE FROM user_images WHERE id = ? AND user_id = ?");
            $stmt->execute([$imageId, $userId]);

            // If deleted image was primary, update users.profile_image
            if ($wasPrimary) {
                // Get the next available image to set as primary
                $stmt = $this->db->prepare("SELECT id, image_path FROM user_images WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 1");
                $stmt->execute([$userId]);
                $nextImage = $stmt->fetch();

                if ($nextImage) {
                    // Set next image as primary
                    $stmt = $this->db->prepare("UPDATE user_images SET is_primary = 1 WHERE id = ?");
                    $stmt->execute([$nextImage['id']]);

                    // Update users.profile_image
                    $stmt = $this->db->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                    $stmt->execute([$nextImage['image_path'], $userId]);
                } else {
                    // No more images, clear profile_image
                    $stmt = $this->db->prepare("UPDATE users SET profile_image = NULL WHERE id = ?");
                    $stmt->execute([$userId]);
                }
            }

            $this->db->commit();

            // Delete physical file
            if (file_exists($imageData['image_path'])) {
                unlink($imageData['image_path']);
            }

            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Set primary image
     */
    public function setPrimaryImage($imageId, $userId) {
        $this->db->beginTransaction();

        try {
            // Get the image path
            $stmt = $this->db->prepare("SELECT image_path FROM user_images WHERE id = ? AND user_id = ?");
            $stmt->execute([$imageId, $userId]);
            $image = $stmt->fetch();

            if (!$image) {
                throw new Exception('Image not found');
            }

            // Reset all images to non-primary
            $stmt = $this->db->prepare("UPDATE user_images SET is_primary = 0 WHERE user_id = ?");
            $stmt->execute([$userId]);

            // Set selected image as primary
            $stmt = $this->db->prepare("UPDATE user_images SET is_primary = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$imageId, $userId]);

            // Update users.profile_image column
            $stmt = $this->db->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
            $stmt->execute([$image['image_path'], $userId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Verify old password
     */
    public function verifyPassword($userId, $password) {
        $user = $this->findById($userId);
        if (!$user) {
            return false;
        }
        return password_verify($password, $user['password']);
    }

    /**
     * Update password
     */
    public function updatePassword($userId, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$hashedPassword, $userId]);
    }
}

