<?php
/**
 * BONHAGAN TRANSIT — CORS Configuration
 */

require_once __DIR__ . '/environment.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');

// Allow matching frontend origin or localhost dev origins
$allowedOrigins = array_filter([
    $frontendUrl,
    'http://localhost',
    'http://localhost:3000',
    'http://localhost:5173',
    'http://127.0.0.1:5500',
    'http://localhost:8000',
    'http://localhost:8080'
]);

if (!empty($origin)) {
    if (in_array($origin, $allowedOrigins, true) || env('APP_ENV') === 'development') {
        header("Access-Control-Allow-Origin: {$origin}");
    }
} else {
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
header("Access-Control-Max-Age: 86400");

// Handle preflight OPTIONS request
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}
