<?php
/**
 * BONHAGAN TRANSIT — Staff Authorization Middleware
 * Admins are also authorized to perform staff operational functions.
 */

require_once __DIR__ . '/auth-middleware.php';

if (!function_exists('requireStaff')) {
    function requireStaff(): array {
        $user = requireAuth();

        if (!in_array($user['role'], ['staff', 'admin'], true)) {
            sendForbidden('Staff or Administrator privileges are required to access this endpoint.');
        }

        return $user;
    }
}

