<?php
/**
 * BONHAGAN TRANSIT — JSON Request Parsing Helper
 */

require_once __DIR__ . '/response.php';

if (!function_exists('getJsonInput')) {
    function getJsonInput(bool $required = false): array {
        $raw = file_get_contents('php://input');

        if (empty($raw)) {
            // Check if standard form-encoded POST was sent
            if (!empty($_POST)) {
                return $_POST;
            }
            if ($required) {
                sendError('Missing request body. Expected JSON data.', ['body' => 'JSON payload is required.'], 400);
            }
            return [];
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            sendError('Invalid JSON format received.', [
                'json_error' => json_last_error_msg()
            ], 400);
        }

        return is_array($decoded) ? $decoded : [];
    }
}

