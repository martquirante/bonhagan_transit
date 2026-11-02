<?php
/**
 * BONHAGAN TRANSIT — Admin Manage Discounts API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/admin-middleware.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';
require_once dirname(dirname(__DIR__)) . '/helpers/validation.php';
require_once dirname(dirname(__DIR__)) . '/helpers/audit-logger.php';

$admin = requireAdmin();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = Database::getConnection();

if ($method === 'GET') {
    try {
        $stmtTypes = $pdo->query("SELECT id, discount_name, discount_percentage, description, is_active FROM public.discount_types ORDER BY discount_name ASC");
        $types = $stmtTypes->fetchAll();

        $stmtVerifs = $pdo->query("
            SELECT dv.id, dv.verification_status, dv.document_path, dv.review_notes, dv.reviewed_at, dv.created_at,
                   dt.discount_name, dt.discount_percentage,
                   bp.passenger_name, bp.passenger_type,
                   b.booking_reference,
                   u.full_name AS submitter_name, u.email AS submitter_email,
                   reviewer.full_name AS reviewer_name
            FROM public.discount_verifications dv
            JOIN public.discount_types dt ON dt.id = dv.discount_type_id
            JOIN public.booking_passengers bp ON bp.id = dv.passenger_id
            JOIN public.bookings b ON b.id = bp.booking_id
            JOIN public.profiles u ON u.id = b.user_id
            LEFT JOIN public.profiles reviewer ON reviewer.id = dv.reviewed_by
            ORDER BY (dv.verification_status = 'pending') DESC, dv.created_at DESC
        ");
        $verifications = $stmtVerifs->fetchAll();

        sendSuccess('Discounts data retrieved.', [
            'discount_types' => $types,
            'verifications' => $verifications
        ]);
    } catch (Exception $e) {
        sendServerError('Failed to fetch discounts.');
    }
}

validateCsrfToken();
$input = getJsonInput(true);

if ($method === 'PUT' || $method === 'PATCH') {
    $typeId = (int)($input['discount_type_id'] ?? 0);
    $percentage = isset($input['discount_percentage']) ? (float)$input['discount_percentage'] : null;

    if ($typeId <= 0 || $percentage === null || $percentage < 0 || $percentage > 100) {
        sendError('Valid discount_type_id and percentage (0-100) required.', [], 422);
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE public.discount_types
            SET discount_percentage = :pct
            WHERE id = :id
            RETURNING id, discount_name, discount_percentage
        ");
        $stmt->execute([':pct' => $percentage, ':id' => $typeId]);
        $updated = $stmt->fetch();

        AuditLogger::log('update_discount_pct', 'discount_types', (string)$typeId, ['percentage' => $percentage], (string)$admin['id']);
        sendSuccess('Discount percentage updated.', ['discount_type' => $updated]);
    } catch (Exception $e) {
        sendServerError('Failed to update discount.');
    }
}

