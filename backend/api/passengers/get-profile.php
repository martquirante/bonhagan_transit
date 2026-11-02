<?php
/**
 * BONHAGAN TRANSIT — Get Passenger Profile API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/auth-middleware.php';

$user = requireAuth();

sendSuccess('Profile retrieved.', [
    'profile' => $user
]);

