<?php
/**
 * BONHAGAN TRANSIT — Verify Discount Request API
 * Admin-only: approves or rejects discount documents.
 */

require_once dirname(dirname(__DIR__)) . '/middleware/admin-middleware.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';
require_once dirname(dirname(__DIR__)) . '/helpers/validation.php';
require_once dirname(dirname(__DIR__)) . '/helpers/audit-logger.php';
require_once dirname(dirname(__DIR__)) . '/services/NotificationService.php';

$admin = requireAdmin();
validateCsrfToken();

$input = getJsonInput(true);

$validator = Validator::make($input)
    ->required('verification_id')
    ->integer('verification_id')
    ->required('action')
    ->in('action', ['approve', 'reject']);

if ($validator->fails()) {
    sendError('Validation failed.', $validator->getErrors(), 422);
}

$verificationId = (int)$input['verification_id'];
$action = $input['action'];
$notes = isset($input['review_notes']) ? trim($input['review_notes']) : null;
$newStatus = $action === 'approve' ? 'approved' : 'rejected';

try {
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare("
        SELECT dv.id, dv.passenger_id, dv.verification_status,
               dt.discount_name,
               bp.passenger_name, b.user_id
        FROM public.discount_verifications dv
        JOIN public.discount_types dt ON dt.id = dv.discount_type_id
        JOIN public.booking_passengers bp ON bp.id = dv.passenger_id
        JOIN public.bookings b ON b.id = bp.booking_id
        WHERE dv.id = :id
    ");
    $stmt->execute([':id' => $verificationId]);
    $record = $stmt->fetch();

    if (!$record) {
        sendNotFound('Discount verification record not found.');
    }

    $stmtUp = $pdo->prepare("
        UPDATE public.discount_verifications
        SET verification_status = :status,
            reviewed_by = :admin_id,
            reviewed_at = NOW(),
            review_notes = :notes
        WHERE id = :id
        RETURNING id, verification_status, reviewed_at, review_notes
    ");
    $stmtUp->execute([
        ':status' => $newStatus,
        ':admin_id' => $admin['id'],
        ':notes' => $notes,
        ':id' => $verificationId
    ]);
    $updated = $stmtUp->fetch();

    // Send Notification to passenger
    NotificationService::notifyDiscountResult(
        $record['user_id'],
        $record['discount_name'],
        $newStatus,
        $notes
    );

    AuditLogger::log(
        'review_discount',
        'discount_verifications',
        (string)$verificationId,
        ['status' => $newStatus, 'passenger' => $record['passenger_name']],
        (string)$admin['id']
    );

    sendSuccess("Discount request {$newStatus} successfully.", [
        'verification' => $updated
    ]);

} catch (Exception $e) {
    sendServerError('Failed to update discount verification.');
}

