<?php
/**
 * BONHAGAN TRANSIT — Update Passenger Profile API
 * Users can update personal info; role tampering is blocked.
 */

require_once dirname(dirname(__DIR__)) . '/middleware/auth-middleware.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';
require_once dirname(dirname(__DIR__)) . '/helpers/validation.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';
require_once dirname(dirname(__DIR__)) . '/helpers/audit-logger.php';

$user = requireAuth();
validateCsrfToken();

$input = getJsonInput(true);

$validator = Validator::make($input)
    ->required('full_name')
    ->minLength('full_name', 2)
    ->maxLength('full_name', 150);

if ($validator->fails()) {
    sendError('Validation failed.', $validator->getErrors(), 422);
}

$fullName = trim($input['full_name']);
$mobileNumber = isset($input['mobile_number']) ? trim($input['mobile_number']) : $user['mobile_number'];
$profileImage = isset($input['profile_image_url']) ? trim($input['profile_image_url']) : $user['profile_image_url'];

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("
        UPDATE public.profiles
        SET full_name = :full_name,
            mobile_number = :mobile,
            profile_image_url = :image,
            updated_at = NOW()
        WHERE id = :id
        RETURNING id, full_name, email, mobile_number, role, account_status, profile_image_url, updated_at
    ");
    $stmt->execute([
        ':full_name' => $fullName,
        ':mobile' => $mobileNumber,
        ':image' => $profileImage,
        ':id' => $user['id']
    ]);
    $updated = $stmt->fetch();

    AuditLogger::log('update_profile', 'profiles', (string)$user['id'], ['name' => $fullName], (string)$user['id']);

    sendSuccess('Profile updated successfully.', [
        'profile' => $updated
    ]);

} catch (Exception $e) {
    sendServerError('Failed to update profile.');
}

