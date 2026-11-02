<?php
/**
 * BONHAGAN TRANSIT — Logout API
 */

require_once dirname(dirname(__DIR__)) . '/config/environment.php';
require_once dirname(dirname(__DIR__)) . '/helpers/response.php';
require_once dirname(dirname(__DIR__)) . '/helpers/audit-logger.php';

if (!empty($_SESSION['user_id'])) {
    AuditLogger::log('logout', 'profiles', (string)$_SESSION['user_id'], null, (string)$_SESSION['user_id']);
}

// Clear session variables
$_SESSION = [];

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

sendSuccess('Signed out successfully.');

