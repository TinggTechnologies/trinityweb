<?php
/**
 * Application Configuration
 * Supports both local and production environments
 *
 * Local: localhost/trinity/ng
 * Production: trinity.futurewebhost.com.ng/ng
 */

// Detect environment
$isLocal = (
    isset($_SERVER['HTTP_HOST']) &&
    (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
     strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)
);

// Database Configuration - Different for local vs production
if ($isLocal) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');  // Local XAMPP default
    define('DB_PASS', '');      // Local XAMPP default (empty)
    define('DB_NAME', 'trinitydistribution_trinity');
} else {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'trinitydistribution_trinity');
    define('DB_PASS', 'DW4Dnd2xfs6qr9zBGP4Y');
    define('DB_NAME', 'trinitydistribution_trinity');
}

// Application Settings - Environment-aware URLs
define('APP_NAME', 'Trinity Distribution');

if ($isLocal) {
    define('APP_URL', 'http://localhost/trinity');
    define('BASE_URL', 'http://localhost/trinity');
    define('FRONTEND_URL', 'http://localhost/trinity/ng');
    define('API_URL', 'http://localhost/trinity/api');
    define('ASSETS_URL', 'http://localhost/trinity/assets');
    define('UPLOADS_URL', 'http://localhost/trinity/uploads');
} else {
    define('APP_URL', 'https://trinity.futurewebhost.com.ng');
    define('BASE_URL', 'https://trinity.futurewebhost.com.ng');
    define('FRONTEND_URL', 'https://trinity.futurewebhost.com.ng/ng');
    define('API_URL', 'https://trinity.futurewebhost.com.ng/api');
    define('ASSETS_URL', 'https://trinity.futurewebhost.com.ng/assets');
    define('UPLOADS_URL', 'https://trinity.futurewebhost.com.ng/uploads');
}

// Store environment flag
define('IS_LOCAL', $isLocal);

// Security Settings
define('JWT_SECRET', 'your-secret-key-change-this-in-production'); // Change this!
define('JWT_EXPIRY', 86400); // 24 hours
define('SESSION_LIFETIME', 86400); // 24 hours
define('REMEMBER_ME_EXPIRY', 2592000); // 30 days

// File Upload Settings
define('UPLOAD_DIR', __DIR__ . '/../../uploads/');
define('ARTWORK_DIR', UPLOAD_DIR . 'artworks/');
define('PROFILE_IMAGE_DIR', UPLOAD_DIR . 'profile_images/');
define('AUDIO_DIR', UPLOAD_DIR . 'audio/');
define('MAX_FILE_SIZE', 10485760); // 10MB
define('MAX_AUDIO_SIZE', 52428800); // 50MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg']);
define('ALLOWED_AUDIO_TYPES', ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/flac']);

// Email Configuration
define('MAIL_FROM', 'trinitydistribution@futurewebhost.com.ng');
define('MAIL_FROM_NAME', 'Trinity Distribution');
define('ADMIN_EMAIL', 'admin@yourdomain.com');

// SMTP Configuration (for sending emails)
define('SMTP_ENABLED', true);
define('SMTP_HOST', 'smtp.gmail.com');  // Change to your SMTP server
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');  // Your SMTP username/email
define('SMTP_PASSWORD', '');  // Your SMTP password or app password
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'

// Pagination
define('DEFAULT_PAGE_SIZE', 10);
define('MAX_PAGE_SIZE', 100);

// Error Reporting (set to 0 in production)
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('UTC');

