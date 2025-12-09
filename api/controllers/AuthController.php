<?php
/**
 * Authentication Controller
 * Handles user authentication operations
 */

class AuthController {
    /**
     * User login
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
        
        // Find user
        $userModel = new User();
        $user = $userModel->findByEmail($data['email']);
        
        if (!$user || !password_verify($data['password'], $user['password'])) {
            Response::error('Invalid email or password', 401);
        }
        
        // Check if email is verified
        if (!$user['is_verified']) {
            Response::error('Please verify your email before logging in', 403);
        }
        
        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        
        // Handle remember me
        if (isset($data['remember']) && $data['remember']) {
            $cookieValue = base64_encode($user['id'] . ':' . hash('sha256', $user['password']));
            setcookie('remember_token', $cookieValue, time() + REMEMBER_ME_EXPIRY, "/");
        }
        
        Response::success([
            'user' => [
                'id' => $user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'stage_name' => $user['stage_name'],
                'profile_image' => $user['profile_image']
            ]
        ], 'Login successful');
    }
    
    /**
     * User registration
     */
    public static function register() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        $validator = new Validator();
        $validator->required('first_name', $data['first_name'] ?? '')
                  ->required('last_name', $data['last_name'] ?? '')
                  ->required('email', $data['email'] ?? '')
                  ->email('email', $data['email'] ?? '')
                  ->required('password', $data['password'] ?? '')
                  ->minLength('password', $data['password'] ?? '', 8);
        
        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }
        
        // Check if email already exists
        $userModel = new User();
        if ($userModel->findByEmail($data['email'])) {
            Response::error('Email already registered', 409);
        }
        
        // Create user
        $verificationToken = bin2hex(random_bytes(32));
        $userId = $userModel->create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'verification_token' => $verificationToken,
            'is_verified' => 0
        ]);
        
        // Send verification email
        self::sendVerificationEmail($data['email'], $verificationToken);
        
        Response::success([
            'user_id' => $userId,
            'email' => $data['email']
        ], 'Registration successful. Please check your email to verify your account', 201);
    }
    
    /**
     * Verify email
     */
    public static function verify() {
        $token = $_GET['token'] ?? '';
        
        if (empty($token)) {
            Response::error('Invalid verification link');
        }
        
        $userModel = new User();
        $user = $userModel->findByVerificationToken($token);
        
        if (!$user) {
            Response::error('Invalid or expired verification link');
        }
        
        $userModel->verifyEmail($user['id']);
        
        Response::success(null, 'Email verified successfully. You can now log in');
    }
    
    /**
     * Logout
     */
    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION = [];
        session_destroy();
        setcookie('remember_token', '', time() - 3600, '/');
        
        Response::success(null, 'Logged out successfully');
    }
    
    /**
     * Get current user
     */
    public static function getCurrentUser() {
        $userId = AuthMiddleware::authenticate();
        $userModel = new User();
        $user = $userModel->getProfile($userId);

        unset($user['password']);
        unset($user['verification_token']);

        Response::success($user);
    }

    /**
     * Check if user is authenticated
     */
    public static function checkAuth() {
        try {
            $userId = AuthMiddleware::authenticate();
            Response::success(['authenticated' => true, 'user_id' => $userId]);
        } catch (Exception $e) {
            Response::success(['authenticated' => false]);
        }
    }

    /**
     * Check if email exists in users table
     */
    public static function checkEmail() {
        $email = $_GET['email'] ?? '';

        if (empty($email)) {
            Response::error('Email is required');
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        Response::success(['exists' => $user ? true : false]);
    }

    /**
     * Send verification email
     */
    private static function sendVerificationEmail($email, $token) {
        $verificationLink = APP_URL . "/public/verify.html?token=" . $token;
        $subject = "Verify Your Email - " . APP_NAME;

        // HTML email message
        $message = "
        <html>
        <head>
            <title>Email Verification</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <h1 style='color: #ED3237;'>Trinity Distribution</h1>
                </div>
                <div style='background-color: #f8f9fa; padding: 30px; border-radius: 8px;'>
                    <h2 style='color: #333; margin-top: 0;'>Welcome to Trinity Distribution!</h2>
                    <p>Thank you for registering. Please verify your email address to activate your account.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$verificationLink' style='background-color: #ED3237; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: 500;'>Verify Email Address</a>
                    </div>
                    <p style='color: #6c757d; font-size: 14px;'>Or copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; color: #0d6efd;'>$verificationLink</p>
                    <hr style='border: none; border-top: 1px solid #dee2e6; margin: 20px 0;'>
                    <p style='color: #6c757d; font-size: 12px; margin-bottom: 0;'>
                        If you didn't create an account, you can safely ignore this email.<br>
                        This verification link will expire in 24 hours.
                    </p>
                </div>
                <div style='text-align: center; margin-top: 20px; color: #6c757d; font-size: 12px;'>
                    <p>&copy; 2024 Trinity Distribution. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        // Email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">" . "\r\n";

        // Send email
        @mail($email, $subject, $message, $headers);
    }
}

