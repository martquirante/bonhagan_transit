<?php
/**
 * BONHAGAN TRANSIT — Cancel Booking API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/auth-middleware.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';
require_once dirname(dirname(__DIR__)) . '/helpers/audit-logger.php';

$user = requireAuth();
validateCsrfToken();

$input = getJsonInput(true);
$bookingId = (int)($input['booking_id'] ?? 0);

if ($bookingId <= 0) {
    sendError('Valid booking_id is required.', ['booking_id' => 'Required.'], 400);
}

try {
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare("SELECT id, user_id, booking_status, booking_reference FROM public.bookings WHERE id = :id");
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        sendNotFound('Booking not found.');
    }

    if ($user['role'] === 'passenger' && $booking['user_id'] !== $user['id']) {
        sendForbidden('You are not authorized to cancel this booking.');
    }

    if ($booking['booking_status'] === 'cancelled') {
        sendError('Booking is already cancelled.', [], 400);
    }

    $pdo->beginTransaction();

    // Update booking status
    $stmtUp = $pdo->prepare("UPDATE public.bookings SET booking_status = 'cancelled', updated_at = NOW() WHERE id = :id");
    $stmtUp->execute([':id' => $bookingId]);

    // Release allocations
    $stmtAlloc = $pdo->prepare("
        UPDATE public.passenger_allocations pa
        SET allocation_status = 'released', hold_expires_at = NULL
        FROM public.booking_passengers bp
        WHERE bp.id = pa.passenger_id AND bp.booking_id = :b_id
    ");
    $stmtAlloc->execute([':b_id' => $bookingId]);

    // Cancel tickets
    $stmtTkt = $pdo->prepare("
        UPDATE public.tickets t
        SET ticket_status = 'cancelled'
        FROM public.booking_passengers bp
        WHERE bp.id = t.passenger_id AND bp.booking_id = :b_id
    ");
    $stmtTkt->execute([':b_id' => $bookingId]);

    // Cancel payment if pending
    $stmtPay = $pdo->prepare("UPDATE public.payments SET payment_status = 'cancelled', updated_at = NOW() WHERE booking_id = :b_id AND payment_status = 'pending'");
    $stmtPay->execute([':b_id' => $bookingId]);

    AuditLogger::log('cancel_booking', 'bookings', (string)$bookingId, ['ref' => $booking['booking_reference']], (string)$user['id']);

    $pdo->commit();

    sendSuccess('Booking cancelled successfully.');

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendServerError('Failed to cancel booking.');
}

