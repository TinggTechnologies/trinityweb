<?php
/**
 * CORS Configuration
 * Handles Cross-Origin Resource Sharing
 */

// Get the origin of the request
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

// List of allowed origins (add your production domain here)
$allowedOrigins = [
    'http://localhost',
    'http://localhost:3000',
    'http://127.0.0.1',
    'https://trinity.futurewebhost.com.ng',
    'http://trinity.futurewebhost.com.ng',
    'https://www.trinity.futurewebhost.com.ng',
    'http://www.trinity.futurewebhost.com.ng',
    'https://trinitydistribution.co',
    'https://www.trinitydistribution.co',
    'http://trinitydistribution.co',
    'http://www.trinitydistribution.co'
];

// Check if origin is allowed or allow all for development
if (in_array($origin, $allowedOrigins) || empty($origin)) {
    header("Access-Control-Allow-Origin: " . ($origin ?: '*'));
} else {
    // For other origins, still allow but without credentials
    header("Access-Control-Allow-Origin: *");
}

// Allow specific methods
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");

// Allow specific headers
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, Cache-Control");

// Allow credentials only for known origins
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Credentials: true");
}

// Set max age for preflight requests
header("Access-Control-Max-Age: 86400");

// Set content type to JSON
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

