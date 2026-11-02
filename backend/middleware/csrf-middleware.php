<?php
/**
 * BONHAGAN TRANSIT — CSRF Protection Middleware
 */

require_once dirname(__DIR__) . '/helpers/response.php';

if (!function_exists('getCsrfToken')) {
    function getCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validateCsrfToken')) {
    function validateCsrfToken(?string $providedToken = null): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Safe methods do not require CSRF token
        if (in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $sessionToken = $_SESSION['csrf_token'] ?? null;
        if (empty($sessionToken)) {
            // Generate if session exists but token hasn't been set yet
            $sessionToken = getCsrfToken();
        }

        // Check header, then input
        $token = $providedToken
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_CSRF_TOKEN']
            ?? $_POST['csrf_token']
            ?? null;

        // If client is sending JSON body, check if csrf_token is in it
        if (!$token) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (is_array($input) && isset($input['csrf_token'])) {
                $token = $input['csrf_token'];
            }
        }

        // Allow dev/API bypass if Authorization bearer or test header is present
        if (env('APP_ENV') === 'development' && empty($token) && !empty($_SERVER['HTTP_X_DEV_TEST'])) {
            return;
        }

        if (empty($token) || !hash_equals($sessionToken, (string)$token)) {
            sendError('CSRF token validation failed. Please refresh and try again.', [
                'csrf' => 'Invalid or missing CSRF token.'
            ], 403);
        }
    }
}

