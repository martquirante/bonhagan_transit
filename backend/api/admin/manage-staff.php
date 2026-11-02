<?php
/**
 * BONHAGAN TRANSIT — Admin Manage Staff Accounts API
 * Exclusive administrative control over staff role assignments.
 */

require_once dirname(dirname(__DIR__)) . '/middleware/admin-middleware.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';
require_once dirname(dirname(__DIR__)) . '/helpers/validation.php';
require_once dirname(dirname(__DIR__)) . '/helpers/audit-logger.php';

$admin = requireAdmin();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = Database::getConnection();

// GET: List all staff and operational personnel
if ($method === 'GET') {
    try {
        $stmt = $pdo->query("
            SELECT id, full_name, email, mobile_number, role, account_status, created_at,
                   (SELECT COUNT(*) FROM public.trip_staff ts WHERE ts.staff_id = p.id) AS total_assignments
            FROM public.profiles p
            WHERE role IN ('staff', 'admin')
            ORDER BY role ASC, full_name ASC
        ");
        sendSuccess('Staff members retrieved.', ['staff' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        sendServerError('Failed to fetch staff accounts.');
    }
}

validateCsrfToken();
$input = getJsonInput(true);

// POST: Create new Staff account
if ($method === 'POST') {
    $validator = Validator::make($input)
        ->required('full_name')->minLength('full_name', 2)
        ->required('email')->email('email')
        ->required('password')->minLength('password', 8)
        ->required('role')->in('role', ['staff', 'admin']);

    if ($validator->fails()) {
        sendError('Validation failed.', $validator->getErrors(), 422);
    }

    $email = strtolower(trim($input['email']));

    try {
        $stmtCheck = $pdo->prepare("SELECT id FROM public.profiles WHERE LOWER(email) = LOWER(:email) LIMIT 1");
        $stmtCheck->execute([':email' => $email]);
        if ($stmtCheck->fetch()) {
            sendError('An account with this email address already exists.', ['email' => 'Email taken.'], 409);
        }

        $passwordHash = password_hash($input['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $pdo->prepare("
            INSERT INTO public.profiles (full_name, email, password_hash, mobile_number, role, account_status, created_at, updated_at)
            VALUES (:name, :email, :hash, :mobile, :role, 'active', NOW(), NOW())
            RETURNING id, full_name, email, mobile_number, role, account_status, created_at
        ");
        $stmt->execute([
            ':name' => trim($input['full_name']),
            ':email' => $email,
            ':hash' => $passwordHash,
            ':mobile' => $input['mobile_number'] ?? null,
            ':role' => $input['role']
        ]);
        $newStaff = $stmt->fetch();

        AuditLogger::log('create_staff', 'profiles', (string)$newStaff['id'], ['role' => $newStaff['role'], 'email' => $email], (string)$admin['id']);

        sendSuccess('Staff account created successfully.', ['staff' => $newStaff], 201);

    } catch (Exception $e) {
        sendServerError('Failed to create staff account.');
    }
}

// PUT / PATCH: Promote/Demote user or update account status
if ($method === 'PUT' || $method === 'PATCH') {
    $userId = $input['user_id'] ?? '';
    if (empty($userId)) sendError('user_id is required.', [], 400);

    $newRole = isset($input['role']) ? trim($input['role']) : null;
    $status = isset($input['account_status']) ? trim($input['account_status']) : null;

    if ($newRole && !in_array($newRole, ['passenger', 'staff', 'admin'], true)) {
        sendError('Invalid role value.', [], 422);
    }
    if ($status && !in_array($status, ['active', 'suspended', 'deactivated'], true)) {
        sendError('Invalid account_status value.', [], 422);
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE public.profiles
            SET role = COALESCE(:role, role),
                account_status = COALESCE(:status, account_status),
                updated_at = NOW()
            WHERE id = :id
            RETURNING id, full_name, email, role, account_status
        ");
        $stmt->execute([
            ':role' => $newRole,
            ':status' => $status,
            ':id' => $userId
        ]);
        $updated = $stmt->fetch();

        if (!$updated) sendNotFound('User not found.');

        AuditLogger::log('modify_user_role_status', 'profiles', (string)$userId, ['role' => $newRole, 'status' => $status], (string)$admin['id']);
        sendSuccess('User updated successfully.', ['user' => $updated]);

    } catch (Exception $e) {
        sendServerError('Failed to modify user account.');
    }
}

