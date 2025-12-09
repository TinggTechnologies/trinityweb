<?php
/**
 * Admin Administrator Controller
 * Handles administrator management operations
 */

class AdminAdministratorController {

    /**
     * Initialize tables and default data
     */
    private static function initializeTables() {
        try {
            $db = Database::getInstance()->getConnection();

            // Create admin_roles table
            $db->exec("
                CREATE TABLE IF NOT EXISTS admin_roles (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    title VARCHAR(100) NOT NULL,
                    privileges TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Create administrators table
            $db->exec("
                CREATE TABLE IF NOT EXISTS administrators (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    role_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id),
                    FOREIGN KEY (role_id) REFERENCES admin_roles(id),
                    UNIQUE KEY unique_user_admin (user_id)
                )
            ");

            // Insert default admin roles if they don't exist
            $stmt = $db->query("SELECT COUNT(*) as count FROM admin_roles");
            $role_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($role_count == 0) {
                $default_roles = [
                    ['Super Administrator', 'Full system access'],
                    ['Media Personnel', 'Access to media management'],
                    ['Support Staff', 'Access to user support and tickets'],
                    ['Content Moderator', 'Access to content moderation']
                ];

                $stmt = $db->prepare("INSERT INTO admin_roles (title, privileges) VALUES (?, ?)");
                foreach ($default_roles as $role) {
                    $stmt->execute([$role[0], $role[1]]);
                }
            }
        } catch (PDOException $e) {
            error_log("Error initializing tables: " . $e->getMessage());
        }
    }

    /**
     * Get all administrators with analytics
     * GET /api/admin/administrators
     */
    public static function getAdministrators() {
        AdminAuthMiddleware::authenticate();

        try {
            // Initialize tables first
            self::initializeTables();

            $db = Database::getInstance()->getConnection();

            // Get administrators
            $stmt = $db->prepare("
                SELECT
                    a.id as admin_id,
                    a.created_at,
                    u.id as user_id,
                    u.first_name,
                    u.last_name,
                    u.mobile_number as phone_number,
                    u.email,
                    u.stage_name,
                    ar.id as role_id,
                    ar.title as role_title
                FROM administrators a
                INNER JOIN users u ON a.user_id = u.id
                INNER JOIN admin_roles ar ON a.role_id = ar.id
                ORDER BY a.created_at DESC
            ");
            $stmt->execute();
            $administrators = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get all users
            $stmt = $db->prepare("
                SELECT u.id, u.first_name, u.last_name, u.stage_name, u.mobile_number as phone_number, u.email,
                       CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END as is_admin
                FROM users u
                LEFT JOIN administrators a ON u.id = a.user_id
                ORDER BY u.first_name, u.last_name
            ");
            $stmt->execute();
            $available_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get admin roles
            $stmt = $db->prepare("SELECT id, title FROM admin_roles ORDER BY title");
            $stmt->execute();
            $admin_roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get analytics
            $analytics = [];

            // Total administrators
            $stmt = $db->query("SELECT COUNT(*) as count FROM administrators");
            $analytics['total_admins'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Total admin roles
            $stmt = $db->query("SELECT COUNT(*) as count FROM admin_roles");
            $analytics['total_roles'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Total users
            $stmt = $db->query("SELECT COUNT(*) as count FROM users");
            $analytics['total_users'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Active administrators (verified users who are admins)
            $stmt = $db->query("
                SELECT COUNT(DISTINCT a.id) as count
                FROM administrators a
                INNER JOIN users u ON a.user_id = u.id
                WHERE u.is_verified = 1
            ");
            $analytics['active_admins'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

            Response::success([
                'administrators' => $administrators,
                'available_users' => $available_users,
                'admin_roles' => $admin_roles,
                'analytics' => $analytics
            ], 'Administrators retrieved successfully');

        } catch (PDOException $e) {
            error_log("Database error in getAdministrators: " . $e->getMessage());
            Response::error('Failed to retrieve administrators', 500);
        }
    }

    /**
     * Create a new administrator
     * POST /api/admin/administrators
     */
    public static function createAdministrator() {
        AdminAuthMiddleware::authenticate();

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $user_id = (int)($data['user_id'] ?? 0);
            $role_id = (int)($data['role_id'] ?? 0);

            if (empty($user_id) || empty($role_id)) {
                Response::error('User ID and Role ID are required', 400);
                return;
            }

            $db = Database::getInstance()->getConnection();

            // Check if user is already an admin
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM administrators WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $admin_exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($admin_exists > 0) {
                Response::error('This user is already an administrator', 400);
                return;
            }

            // Create new administrator
            $stmt = $db->prepare("INSERT INTO administrators (user_id, role_id, created_at) VALUES (?, ?, NOW())");
            $result = $stmt->execute([$user_id, $role_id]);

            if ($result) {
                Response::success([
                    'admin_id' => $db->lastInsertId()
                ], 'Administrator created successfully');
            } else {
                Response::error('Failed to create administrator', 500);
            }

        } catch (PDOException $e) {
            error_log("Database error in createAdministrator: " . $e->getMessage());
            Response::error('Failed to create administrator', 500);
        }
    }

    /**
     * Update administrator role
     * PUT /api/admin/administrators/{id}
     */
    public static function updateAdministrator($id) {
        AdminAuthMiddleware::authenticate();

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $role_id = (int)($data['role_id'] ?? 0);

            if (empty($role_id)) {
                Response::error('Role ID is required', 400);
                return;
            }

            $db = Database::getInstance()->getConnection();

            $stmt = $db->prepare("UPDATE administrators SET role_id = ?, updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$role_id, $id]);

            if ($result) {
                Response::success(null, 'Administrator role updated successfully');
            } else {
                Response::error('Failed to update administrator', 500);
            }

        } catch (PDOException $e) {
            error_log("Database error in updateAdministrator: " . $e->getMessage());
            Response::error('Failed to update administrator', 500);
        }
    }

    /**
     * Delete administrator
     * DELETE /api/admin/administrators/{id}
     */
    public static function deleteAdministrator($id) {
        AdminAuthMiddleware::authenticate();

        try {
            $db = Database::getInstance()->getConnection();

            $stmt = $db->prepare("DELETE FROM administrators WHERE id = ?");
            $result = $stmt->execute([$id]);

            if ($result) {
                Response::success(null, 'Administrator removed successfully');
            } else {
                Response::error('Failed to remove administrator', 500);
            }

        } catch (PDOException $e) {
            error_log("Database error in deleteAdministrator: " . $e->getMessage());
            Response::error('Failed to remove administrator', 500);
        }
    }
}

