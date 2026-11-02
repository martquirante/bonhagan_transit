<?php
/**
 * BONHAGAN TRANSIT — User Registration API
 * Public registration strictly assigns 'passenger' role.
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/helpers/response.php';
require_once dirname(dirname(__DIR__)) . '/helpers/validation.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';
require_once dirname(dirname(__DIR__)) . '/helpers/audit-logger.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';

$input = getJsonInput(true);

$validator = Validator::make($input)
    ->required('full_name')
    ->minLength('full_name', 2)
    ->maxLength('full_name', 150)
    ->required('email')
    ->email('email')
    ->required('password')
    ->minLength('password', 8)
    ->maxLength('password', 72);

if ($validator->fails()) {
    sendError('Validation failed.', $validator->getErrors(), 422);
}

$fullName = trim($input['full_name']);
$email = strtolower(trim($input['email']));
$password = $input['password'];
$mobile = isset($input['mobile_number']) ? trim($input['mobile_number']) : null;

try {
    $pdo = Database::getConnection();

    // Check if email already registered
    $stmtCheck = $pdo->prepare("SELECT id FROM public.profiles WHERE LOWER(email) = LOWER(:email) LIMIT 1");
    $stmtCheck->execute([':email' => $email]);
    if ($stmtCheck->fetch()) {
        sendError('An account with this email address already exists.', ['email' => 'Email is already registered.'], 409);
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $pdo->prepare("
        INSERT INTO public.profiles (full_name, email, password_hash, mobile_number, role, account_status, created_at, updated_at)
        VALUES (:full_name, :email, :password_hash, :mobile, 'passenger', 'active', NOW(), NOW())
        RETURNING id, full_name, email, mobile_number, role, account_status, created_at
    ");
    $stmt->execute([
        ':full_name' => $fullName,
        ':email' => $email,
        ':password_hash' => $passwordHash,
        ':mobile' => $mobile
    ]);
    $user = $stmt->fetch();

    // Automatically authenticate the newly registered user
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['email'] = $user['email'];

    AuditLogger::log('register', 'profiles', (string)$user['id'], ['email' => $email], (string)$user['id']);

    sendSuccess('Account created successfully. Welcome to Bonhagan Transit!', [
        'user' => $user,
        'csrf_token' => getCsrfToken()
    ], 201);

} catch (Exception $e) {
    sendServerError('Registration failed. Please try again.');
}

