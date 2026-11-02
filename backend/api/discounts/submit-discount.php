<?php
/**
 * BONHAGAN TRANSIT — Submit Discount Verification Document API
 * Securely stores ID document for review; private storage.
 */

require_once dirname(dirname(__DIR__)) . '/middleware/auth-middleware.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';
require_once dirname(dirname(__DIR__)) . '/helpers/validation.php';
require_once dirname(dirname(__DIR__)) . '/helpers/audit-logger.php';

$user = requireAuth();
validateCsrfToken();

$passengerId = (int)($_POST['passenger_id'] ?? 0);
$discountTypeId = (int)($_POST['discount_type_id'] ?? 0);

if ($passengerId <= 0 || $discountTypeId <= 0) {
    sendError('passenger_id and discount_type_id are required.', [], 422);
}

// Verify file upload
if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    sendError('Valid document file (image or PDF) is required.', ['document' => 'File upload error.'], 400);
}

$file = $_FILES['document'];
$maxSize = 5 * 1024 * 1024; // 5MB

if ($file['size'] > $maxSize) {
    sendError('Document file exceeds maximum permitted size of 5MB.', ['document' => 'File too large.'], 422);
}

$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedMimes, true)) {
    sendError('Invalid file type. Only JPG, PNG, and PDF files are allowed.', ['document' => 'Invalid file format.'], 422);
}

// Extension determination
$ext = match ($mimeType) {
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
    default => 'bin'
};

$uploadDir = dirname(dirname(__DIR__)) . '/storage/uploads/discounts';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0770, true);
}

$filename = 'disc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$targetPath = $uploadDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    sendServerError('Failed to save uploaded document.');
}

try {
    $pdo = Database::getConnection();

    // Verify passenger belongs to user's booking
    $stmtCheck = $pdo->prepare("
        SELECT bp.id, b.user_id, bp.passenger_name
        FROM public.booking_passengers bp
        JOIN public.bookings b ON b.id = bp.booking_id
        WHERE bp.id = :id
    ");
    $stmtCheck->execute([':id' => $passengerId]);
    $passenger = $stmtCheck->fetch();

    if (!$passenger) {
        @unlink($targetPath);
        sendNotFound('Passenger record not found.');
    }

    if ($user['role'] === 'passenger' && $passenger['user_id'] !== $user['id']) {
        @unlink($targetPath);
        sendForbidden('You can only submit documents for passengers in your bookings.');
    }

    // Insert verification record
    $stmt = $pdo->prepare("
        INSERT INTO public.discount_verifications (
            passenger_id, discount_type_id, document_path, verification_status, created_at
        )
        VALUES (
            :p_id, :d_type, :doc_path, 'pending', NOW()
        )
        RETURNING id, passenger_id, discount_type_id, verification_status, created_at
    ");
    $stmt->execute([
        ':p_id' => $passengerId,
        ':d_type' => $discountTypeId,
        ':doc_path' => $filename // Only store relative safe reference, never absolute path
    ]);
    $verification = $stmt->fetch();

    AuditLogger::log('submit_discount_doc', 'discount_verifications', (string)$verification['id'], ['passenger_id' => $passengerId], (string)$user['id']);

    sendSuccess('Discount verification document submitted successfully. Awaiting administrator approval.', [
        'verification' => $verification
    ], 201);

} catch (Exception $e) {
    @unlink($targetPath);
    sendServerError('Failed to register discount document.');
}

