<?php
/**
 * Application Configuration
 * Loads environment variables from .env file
 */

// Load environment variables
function loadEnv($path) {
    if (!file_exists($path)) {
        die('.env file not found. Copy .env.example to .env and configure it.');
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
        }
    }
}

loadEnv(__DIR__ . '/../../.env');

// Detect environment
$isLocal = (
    isset($_SERVER['HTTP_HOST']) &&
    (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
     strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)
);

// Database Configuration
if ($isLocal) {
    define('DB_HOST', $_ENV['DB_HOST_LOCAL']);
    define('DB_USER', $_ENV['DB_USER_LOCAL']);
    define('DB_PASS', $_ENV['DB_PASS_LOCAL']);
    define('DB_NAME', $_ENV['DB_NAME_LOCAL']);
} else {
    define('DB_HOST', $_ENV['DB_HOST_PROD']);
    define('DB_USER', $_ENV['DB_USER_PROD']);
    define('DB_PASS', $_ENV['DB_PASS_PROD']);
    define('DB_NAME', $_ENV['DB_NAME_PROD']);
}

// Application Settings
define('APP_NAME', 'Trinity Distribution');

if ($isLocal) {
    define('APP_URL', $_ENV['APP_URL_LOCAL']);
    define('BASE_URL', $_ENV['APP_URL_LOCAL']);
    define('FRONTEND_URL', $_ENV['FRONTEND_URL_LOCAL']);
    define('API_URL', $_ENV['API_URL_LOCAL']);
    define('ASSETS_URL', $_ENV['APP_URL_LOCAL'] . '/assets');
    define('UPLOADS_URL', $_ENV['APP_URL_LOCAL'] . '/uploads');
} else {
    define('APP_URL', $_ENV['APP_URL_PROD']);
    define('BASE_URL', $_ENV['APP_URL_PROD']);
    define('FRONTEND_URL', $_ENV['FRONTEND_URL_PROD']);
    define('API_URL', $_ENV['API_URL_PROD']);
    define('ASSETS_URL', $_ENV['APP_URL_PROD'] . '/assets');
    define('UPLOADS_URL', $_ENV['APP_URL_PROD'] . '/uploads');
}

define('IS_LOCAL', $isLocal);

// Security Settings
define('JWT_SECRET', $_ENV['JWT_SECRET']);
define('JWT_EXPIRY', (int)$_ENV['JWT_EXPIRY']);
define('SESSION_LIFETIME', 86400);
define('REMEMBER_ME_EXPIRY', 2592000);

// File Upload Settings
define('UPLOAD_DIR', __DIR__ . '/../../uploads/');
define('ARTWORK_DIR', UPLOAD_DIR . 'artworks/');
define('PROFILE_IMAGE_DIR', UPLOAD_DIR . 'profile_images/');
define('AUDIO_DIR', UPLOAD_DIR . 'audio/');
define('MAX_FILE_SIZE', 10485760);
define('MAX_AUDIO_SIZE', 52428800);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg']);
define('ALLOWED_AUDIO_TYPES', ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/flac']);

// Email Configuration
define('MAIL_FROM', $_ENV['MAIL_FROM']);
define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME']);
define('ADMIN_EMAIL', 'admin@yourdomain.com');

// SMTP Configuration
define('SMTP_ENABLED', true);
define('SMTP_HOST', $_ENV['SMTP_HOST']);
define('SMTP_PORT', (int)$_ENV['SMTP_PORT']);
define('SMTP_USERNAME', $_ENV['SMTP_USERNAME']);
define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD']);
define('SMTP_SECURE', $_ENV['SMTP_SECURE']);

// Pagination
define('DEFAULT_PAGE_SIZE', 10);
define('MAX_PAGE_SIZE', 100);

// Error Reporting
define('DEBUG_MODE', filter_var($_ENV['DEBUG_MODE'], FILTER_VALIDATE_BOOLEAN));

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

date_default_timezone_set('UTC');
