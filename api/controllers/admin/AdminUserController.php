<?php
/**
 * Admin User Controller
 * Handles user management for admins
 */

class AdminUserController {
    /**
     * Get all users
     * GET /api/admin/users
     */
    public static function getUsers() {
        AdminAuthMiddleware::authenticate();

        $db = Database::getInstance()->getConnection();

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = ($page - 1) * $limit;

        // Get analytics data
        $analytics = [];

        // Total users
        $stmt = $db->query("SELECT COUNT(*) FROM users");
        $analytics['total_users'] = $stmt->fetchColumn();

        // Verified users
        $stmt = $db->query("SELECT COUNT(*) FROM users WHERE is_verified = 1");
        $analytics['verified_users'] = $stmt->fetchColumn();

        // Total artists (users with stage_name)
        $stmt = $db->query("SELECT COUNT(*) FROM users WHERE stage_name IS NOT NULL AND stage_name != ''");
        $analytics['total_artists'] = $stmt->fetchColumn();

        // Users this month
        $stmt = $db->query("SELECT COUNT(*) FROM users WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
        $analytics['users_this_month'] = $stmt->fetchColumn();

        // Users with releases
        $stmt = $db->query("SELECT COUNT(DISTINCT user_id) FROM releases");
        $analytics['users_with_releases'] = $stmt->fetchColumn();

        // Total countries
        $stmt = $db->query("SELECT COUNT(DISTINCT origin_country) FROM users WHERE origin_country IS NOT NULL AND origin_country != ''");
        $analytics['total_countries'] = $stmt->fetchColumn();

        // Get users with additional data
        $stmt = $db->prepare("
            SELECT
                u.id,
                u.first_name,
                u.last_name,
                u.email,
                u.stage_name,
                u.mobile_number,
                u.origin_country,
                u.residence_country,
                u.artist_bio,
                u.profile_image,
                u.created_at,
                u.updated_at,
                u.is_verified,
                COUNT(DISTINCT r.id) as total_releases,
                COUNT(DISTINCT pm.id) as payment_methods
            FROM users u
            LEFT JOIN releases r ON u.id = r.user_id
            LEFT JOIN payment_methods pm ON u.id = pm.user_id
            GROUP BY u.id
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll();

        Response::success([
            'users' => $users,
            'analytics' => $analytics,
            'pagination' => [
                'total' => $analytics['total_users'],
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($analytics['total_users'] / $limit)
            ]
        ]);
    }
    
    /**
     * Get single user
     * GET /api/admin/users/{id}
     */
    public static function getUser($id) {
        AdminAuthMiddleware::authenticate();
        
        $userModel = new User();
        $user = $userModel->findById($id);
        
        if (!$user) {
            Response::notFound('User not found');
        }
        
        // Get user's releases
        $releaseModel = new Release();
        $releases = $releaseModel->getByUserId($id, 1, 100);
        
        // Get user's payment methods
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM payment_methods WHERE user_id = ?");
        $stmt->execute([$id]);
        $payment_methods = $stmt->fetchAll();
        
        Response::success([
            'user' => $user,
            'releases' => $releases,
            'payment_methods' => $payment_methods
        ]);
    }
    
    /**
     * Update user
     * PUT /api/admin/users/{id}
     */
    public static function updateUser($id) {
        AdminAuthMiddleware::authenticate();

        $userModel = new User();
        $user = $userModel->findById($id);

        if (!$user) {
            Response::notFound('User not found');
        }

        $data = json_decode(file_get_contents('php://input'), true);

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE users SET
                first_name = ?,
                last_name = ?,
                email = ?,
                stage_name = ?,
                mobile_number = ?,
                origin_country = ?,
                residence_country = ?,
                artist_bio = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $data['first_name'] ?? $user['first_name'],
            $data['last_name'] ?? $user['last_name'],
            $data['email'] ?? $user['email'],
            $data['stage_name'] ?? $user['stage_name'],
            $data['mobile_number'] ?? $user['mobile_number'],
            $data['origin_country'] ?? $user['origin_country'],
            $data['residence_country'] ?? $user['residence_country'],
            $data['artist_bio'] ?? $user['artist_bio'],
            $id
        ]);

        Response::success(null, 'User updated successfully');
    }

    /**
     * Delete user
     * DELETE /api/admin/users/{id}
     */
    public static function deleteUser($id) {
        AdminAuthMiddleware::authenticate();

        $userModel = new User();
        $user = $userModel->findById($id);

        if (!$user) {
            Response::notFound('User not found');
        }

        // Delete user (cascading deletes will handle related records)
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        Response::success(null, 'User deleted successfully');
    }

    /**
     * Bulk delete users
     * POST /api/admin/users/bulk-delete
     */
    public static function bulkDelete() {
        AdminAuthMiddleware::authenticate();

        $data = json_decode(file_get_contents('php://input'), true);
        $userIds = $data['user_ids'] ?? [];

        if (empty($userIds)) {
            Response::error('No users selected for deletion');
        }

        $db = Database::getInstance()->getConnection();
        $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
        $stmt = $db->prepare("DELETE FROM users WHERE id IN ($placeholders)");
        $stmt->execute($userIds);

        Response::success(null, count($userIds) . ' user(s) deleted successfully');
    }

    /**
     * Verify user
     * POST /api/admin/users/{id}/verify
     */
    public static function verifyUser($id) {
        AdminAuthMiddleware::authenticate();

        $userModel = new User();
        $user = $userModel->findById($id);

        if (!$user) {
            Response::notFound('User not found');
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
        $stmt->execute([$id]);

        Response::success(null, 'User verified successfully');
    }
}

