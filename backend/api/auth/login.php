<?php
/**
 * BONHAGAN TRANSIT — User Login API
 * Validates credentials via password_verify, checks status, regenerates session ID.
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/helpers/response.php';
require_once dirname(dirname(__DIR__)) . '/helpers/validation.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';
require_once dirname(dirname(__DIR__)) . '/helpers/audit-logger.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';

$input = getJsonInput(true);

$validator = Validator::make($input)
    ->required('email')
    ->email('email')
    ->required('password');

if ($validator->fails()) {
    sendError('Validation failed.', $validator->getErrors(), 422);
}

$email = strtolower(trim($input['email']));
$password = $input['password'];

try {
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare("
        SELECT id, full_name, email, password_hash, mobile_number, role, account_status, profile_image_url
        FROM public.profiles
        WHERE LOWER(email) = LOWER(:email)
        LIMIT 1
    ");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        AuditLogger::log('login_failed', 'profiles', null, ['email' => $email]);
        sendError('Invalid email or password.', ['credentials' => 'Credentials do not match our records.'], 401);
    }

    if ($user['account_status'] !== 'active') {
        AuditLogger::log('login_blocked_status', 'profiles', (string)$user['id'], ['status' => $user['account_status']]);
        sendForbidden("Your account is {$user['account_status']}. Please contact Bonhagan Transit support.");
    }

    // Secure Session Initialization
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['email'] = $user['email'];

    unset($user['password_hash']);

    AuditLogger::log('login_success', 'profiles', (string)$user['id'], ['role' => $user['role']], (string)$user['id']);

    sendSuccess('Signed in successfully.', [
        'user' => $user,
        'csrf_token' => getCsrfToken()
    ]);

} catch (Exception $e) {
    sendServerError('Login processing error.');
}

