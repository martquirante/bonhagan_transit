<?php
/**
 * BONHAGAN TRANSIT — Standard JSON API Response Helper
 */

require_once dirname(__DIR__) . '/config/cors.php';

if (!function_exists('jsonResponse')) {
    function jsonResponse(array $payload, int $statusCode = 200): void {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit(0);
    }
}

if (!function_exists('sendSuccess')) {
    function sendSuccess(string $message = 'Operation completed successfully.', mixed $data = [], int $statusCode = 200): void {
        jsonResponse([
            'success' => true,
            'message' => $message,
            'data' => (object)$data === new stdClass() && empty($data) ? (object)[] : $data
        ], $statusCode);
    }
}

if (!function_exists('sendError')) {
    function sendError(string $message = 'An error occurred.', mixed $errors = [], int $statusCode = 400): void {
        jsonResponse([
            'success' => false,
            'message' => $message,
            'errors' => (object)$errors === new stdClass() && empty($errors) ? (object)[] : $errors
        ], $statusCode);
    }
}

if (!function_exists('sendUnauthorized')) {
    function sendUnauthorized(string $message = 'Authentication required. Please sign in.'): void {
        sendError($message, ['auth' => 'User is not authenticated.'], 401);
    }
}

if (!function_exists('sendForbidden')) {
    function sendForbidden(string $message = 'Access denied. You do not have permission.'): void {
        sendError($message, ['permission' => 'Insufficient privileges for this action.'], 403);
    }
}

if (!function_exists('sendNotFound')) {
    function sendNotFound(string $message = 'Requested resource not found.'): void {
        sendError($message, ['resource' => 'Resource does not exist.'], 404);
    }
}

if (!function_exists('sendServerError')) {
    function sendServerError(string $message = 'An unexpected server error occurred.'): void {
        sendError($message, ['server' => 'Internal processing failure.'], 500);
    }
}

