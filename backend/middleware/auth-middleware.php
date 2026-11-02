<?php
/**
 * BONHAGAN TRANSIT — Authentication Middleware
 * Enforces session authentication and verifies active account status.
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/helpers/response.php';

if (!function_exists('requireAuth')) {
    function requireAuth(): array {
        if (empty($_SESSION['user_id'])) {
            sendUnauthorized('You must be logged in to access this resource.');
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                SELECT id, full_name, email, mobile_number, role, account_status, profile_image_url
                FROM public.profiles
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user) {
                // Clear invalid session
                session_unset();
                session_destroy();
                sendUnauthorized('Account session is invalid or user no longer exists.');
            }

            if ($user['account_status'] !== 'active') {
                session_unset();
                session_destroy();
                sendForbidden("Account is {$user['account_status']}. Please contact management.");
            }

            return $user;

        } catch (Exception $e) {
            sendServerError('Failed to verify user session.');
        }
    }
}

