<?php
/**
 * Admin Authentication Middleware
 * Verifies that the user is both authenticated and an administrator
 */

class AdminAuthMiddleware {
    /**
     * Authenticate and verify admin status
     * Returns the user ID if authenticated and is admin
     * Sends error response and exits if not
     */
    public static function authenticate() {
        // First check if user is authenticated
        $userId = AuthMiddleware::authenticate();
        
        // Then check if user is an admin
        $adminModel = new Admin();
        if (!$adminModel->isAdmin($userId)) {
            Response::forbidden('Admin access required');
        }
        
        return $userId;
    }
    
    /**
     * Get admin details for authenticated admin
     */
    public static function getAdminDetails() {
        $userId = self::authenticate();
        
        $adminModel = new Admin();
        return $adminModel->getByUserId($userId);
    }
}

