<?php
/**
 * BONHAGAN TRANSIT — Administrator Authorization Middleware
 */

require_once __DIR__ . '/auth-middleware.php';

if (!function_exists('requireAdmin')) {
    function requireAdmin(): array {
        $user = requireAuth();

        if ($user['role'] !== 'admin') {
            sendForbidden('Administrative privileges are required to access this endpoint.');
        }

        return $user;
    }
}

