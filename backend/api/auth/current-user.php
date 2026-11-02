<?php
/**
 * BONHAGAN TRANSIT — Current Authenticated User API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/auth-middleware.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';

$user = requireAuth();

sendSuccess('User profile retrieved.', [
    'user' => $user,
    'csrf_token' => getCsrfToken()
]);

