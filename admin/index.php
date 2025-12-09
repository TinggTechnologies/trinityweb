<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Trinity Distribution</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .auth-bg {
            width: 100%;
            background-image: url(./images/auth-bg.png);
            background-repeat: no-repeat;
            background-size: cover;
            height: 100vh !important;
        }

        .card-logo {
            height: 32px;
            width: 24px;
        } 

        .auth-card {
            min-height: 310px;
            width: 410px;
        }

        .divider {
            width: 100%;
            background-color: grey;
            height: 1px;
        }

        .divider-text {
            width: 400px !important;
            color: rgb(159, 158, 158);
        }

        .card-wrapper {
            min-height: 625px;
        }

        .otp-input {
            font-size: 30px;
        }

        .primary-text {
            color: #007bff;
        }

        .primary-bg {
            background-color: #007bff;
            border-color: #007bff;
        }

        .text-grey {
            color: #6c757d;
        }

        .no-gap {
            margin-bottom: 0.5rem;
        }

        .clickable {
            cursor: pointer;
            text-decoration: underline;
        }

        .fw-bold {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php
    header("Access-Control-Allow-Origin: http://localhost:3000");
    header("Content-Type: text/html; charset=UTF-8");
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

    $errors = [];
    $success_message = '';
    $isLoading = false;

    // Check if user is already logged in and redirect
    $sessionStatus = checkSession();
   // if ($sessionStatus['loggedIn']) {
      //  header("Location: ./");
      //  exit();
   // }

    // Handle POST request for login
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $isLoading = true;
        
        // Get form input
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $remember = isset($_POST['remember']) ? true : false;

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

        // If no validation errors, attempt login
        if (empty($errors)) {
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
                    
                    // Redirect to dashboard
                    header("Location: ./dashboard.php");
                    exit();
                } else {
                    $errors['general'] = "Invalid email or password";
                }
            } catch (PDOException $e) {
                $errors['general'] = "Login failed. Please try again.";
            }
        }
        $isLoading = false;
    }
    ?>

    <div class="auth-bg">
        <div class="d-flex justify-content-center align-items-center card-wrapper">
            <div class="card auth-card mt-5 mb-5">
                <div class="card-body">
                    <h6 class="text-danger text-center mt-3">Welcome Back!</h6>
                    
                    <form method="POST" action="">
                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($errors['general']); ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($errors['emailandpass'])): ?>
                            <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($errors['emailandpass']); ?></div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <input 
                                type="email" 
                                class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                placeholder="Enter your email address" 
                                name="email"
                                value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                                required
                            />
                            <?php if (isset($errors['email'])): ?>
                                <div class="text-danger small mt-1"><?php echo htmlspecialchars($errors['email']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-3">
                            <input 
                                type="password" 
                                class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                placeholder="Enter your password" 
                                name="password"
                                required
                            />
                            <?php if (isset($errors['password'])): ?>
                                <div class="text-danger small mt-1"><?php echo htmlspecialchars($errors['password']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-3 form-check">
                            <input 
                                type="checkbox" 
                                class="form-check-input" 
                                id="rememberMe"
                                name="remember"
                                <?php echo (isset($_POST['remember']) ? 'checked' : ''); ?>
                            />
                            <label class="form-check-label" for="rememberMe">
                                Remember me
                            </label>
                        </div>

                        <button 
                            type="submit" 
                            class="btn btn-danger danger-bg form-control mt-3"
                            <?php echo $isLoading ? 'disabled' : ''; ?>
                        >
                            <?php echo $isLoading ? 'Logging in...' : 'Log in'; ?>
                        </button>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>