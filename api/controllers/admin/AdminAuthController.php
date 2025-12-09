<?php
/**
 * Admin Authentication Controller
 * Handles admin login and authentication
 */

class AdminAuthController {
    /**
     * Admin login
     * POST /api/admin/login
     */
    public static function login() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        $validator = new Validator();
        $validator->required('email', $data['email'] ?? '')
                  ->email('email', $data['email'] ?? '')
                  ->required('password', $data['password'] ?? '');
        
        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }
        
        $userModel = new User();
        $user = $userModel->findByEmail($data['email']);
        
        if (!$user || !password_verify($data['password'], $user['password'])) {
            Response::error('Invalid email or password', 401);
        }
        
        // Check if user is an admin
        $adminModel = new Admin();
        if (!$adminModel->isAdmin($user['id'])) {
            Response::error('Admin access required', 403);
        }
        
        // Get admin details
        $admin = $adminModel->getByUserId($user['id']);
        
        // Create session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_role'] = $admin['role_title'];
        
        // Handle remember me
        if (!empty($data['remember_me'])) {
            $token = bin2hex(random_bytes(32));
            setcookie('remember_token', $token, time() + (86400 * 30), '/'); // 30 days
            
            // Store token in database (you may want to create a remember_tokens table)
            $userModel->updateRememberToken($user['id'], $token);
        }
        
        Response::success([
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'is_admin' => true,
                'admin_role' => $admin['role_title']
            ]
        ], 'Login successful');
    }
    
    /**
     * Admin logout
     * POST /api/admin/logout
     */
    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Clear session
        session_unset();
        session_destroy();
        
        // Clear remember me cookie
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        Response::success(null, 'Logged out successfully');
    }
    
    /**
     * Check admin authentication status
     * GET /api/admin/check-auth
     */
    public static function checkAuth() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            Response::error('Not authenticated', 401);
        }

        // Check if user is an admin
        $adminModel = new Admin();
        if (!$adminModel->isAdmin($_SESSION['user_id'])) {
            Response::error('Admin access required', 403);
        }

        Response::success(['authenticated' => true]);
    }

    /**
     * Get current admin user
     * GET /api/admin/me
     */
    public static function getCurrentAdmin() {
        $admin = AdminAuthMiddleware::getAdminDetails();

        Response::success([
            'admin' => $admin
        ]);
    }
}

