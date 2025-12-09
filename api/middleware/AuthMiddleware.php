<?php
/**
 * Authentication Middleware
 * Handles session-based authentication for API requests
 */

class AuthMiddleware {
    /**
     * Check if user is authenticated
     */
    public static function authenticate() {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            // Check for remember me cookie
            if (isset($_COOKIE['remember_token'])) {
                self::authenticateFromCookie();
            } else {
                Response::unauthorized('Please log in to continue');
            }
        }
        
        return $_SESSION['user_id'];
    }
    
    /**
     * Authenticate from remember me cookie
     */
    private static function authenticateFromCookie() {
        try {
            $db = Database::getInstance()->getConnection();
            $tokenParts = explode(':', base64_decode($_COOKIE['remember_token']));
            
            if (count($tokenParts) !== 2) {
                Response::unauthorized('Invalid authentication token');
            }
            
            $userId = $tokenParts[0];
            $tokenHash = $tokenParts[1];
            
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user && hash('sha256', $user['password']) === $tokenHash) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                
                // Refresh cookie
                $cookieValue = base64_encode($user['id'] . ':' . $tokenHash);
                setcookie('remember_token', $cookieValue, time() + REMEMBER_ME_EXPIRY, "/");
            } else {
                Response::unauthorized('Invalid authentication token');
            }
        } catch (Exception $e) {
            Response::unauthorized('Authentication failed');
        }
    }
    
    /**
     * Check if user is admin
     */
    public static function requireAdmin() {
        $userId = self::authenticate();
        
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT COUNT(*) FROM administrators WHERE user_id = ?");
            $stmt->execute([$userId]);
            $isAdmin = $stmt->fetchColumn() > 0;
            
            if (!$isAdmin) {
                Response::forbidden('Admin access required');
            }
            
            return $userId;
        } catch (Exception $e) {
            Response::serverError('Failed to verify admin status');
        }
    }
    
    /**
     * Get current user ID
     */
    public static function getUserId() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current user data
     */
    public static function getUser() {
        $userId = self::getUserId();
        
        if (!$userId) {
            return null;
        }
        
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, first_name, last_name, email, stage_name, profile_image FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }
}

