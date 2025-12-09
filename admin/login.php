<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'trinity');

// Connect to database
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed: " . $e->getMessage()]);
    exit();
}

// Check if user is already logged in
function checkSession() {
    if (!empty($_SESSION['user_id'])) {
        return [
            'loggedIn' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'email' => $_SESSION['user_email'],
                'name' => $_SESSION['user_name']
            ]
        ];
    }
    
    // Check for remember me cookie
    if (empty($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
        global $pdo;
        try {
            $token_parts = explode(':', base64_decode($_COOKIE['remember_token']));
            if (count($token_parts) === 2) {
                $user_id = $token_parts[0];
                $token_hash = $token_parts[1];
                
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && hash('sha256', $user['password']) === $token_hash) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    
                    // Refresh cookie
                    $cookie_value = base64_encode($user['id'] . ':' . $token_hash);
                    setcookie('remember_token', $cookie_value, time() + (86400 * 30), "/"); // 30 days
                    
                    return [
                        'loggedIn' => true,
                        'user' => [
                            'id' => $user['id'],
                            'email' => $user['email'],
                            'name' => $user['first_name'] . ' ' . $user['last_name']
                        ]
                    ];
                }
            }
        } catch (PDOException $e) {
            // Cookie is invalid - just continue with normal login
        }
    }
    
    return ['loggedIn' => false];
}

// Handle GET request to check authentication status
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sessionStatus = checkSession();
    echo json_encode($sessionStatus);
    exit();
}

// Handle POST request for login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents("php://input"));

    if (is_null($input)) {
        http_response_code(400);
        echo json_encode(["message" => "Invalid JSON input"]);
        exit();
    }

    // Initialize variables
    $errors = [];
    $email = isset($input->email) ? trim($input->email) : '';
    $password = isset($input->password) ? $input->password : '';
    $remember = isset($input->remember) ? (bool)$input->remember : false;

    // Validation
    if (empty($email) && empty($password)){
        $errors['emailandpass'] = "Email and password is required";
    }
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email';
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required';
    }

    // If validation errors, return them
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            "message" => "Validation failed", 
            "errors" => $errors
        ]);
        exit();
    }

    // Attempt login
    try {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Login successful - set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            
            // Remember me functionality (set cookie)
            if ($remember) {
                $cookie_value = base64_encode($user['id'] . ':' . hash('sha256', $user['password']));
                setcookie('remember_token', $cookie_value, time() + (86400 * 30), "/"); // 30 days
            }
            
            // Return success response
            http_response_code(200);
            echo json_encode([
                "message" => "Login successful",
                "user" => [
                    "id" => $user['id'],
                    "email" => $user['email'],
                    "name" => $user['first_name'] . ' ' . $user['last_name']
                ]
            ]);
            exit();
        } else {
            http_response_code(401);
            echo json_encode(["message" => "Invalid email or password"]);
            exit();
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Login failed: " . $e->getMessage()]);
        exit();
    }
}

// If method not supported
http_response_code(405);
echo json_encode(["message" => "Method not allowed"]);
exit();
?>