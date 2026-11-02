<?php
/**
 * BONHAGAN TRANSIT — Check Payment Status API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/auth-middleware.php';

$user = requireAuth();

$paymentRef = isset($_GET['payment_reference']) ? trim($_GET['payment_reference']) : '';
$bookingRef = isset($_GET['booking_reference']) ? trim($_GET['booking_reference']) : '';
$paymentId = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;

if (empty($paymentRef) && empty($bookingRef) && $paymentId <= 0) {
    sendError('Missing payment identifier.', [], 400);
}

try {
    $pdo = Database::getConnection();

    $sql = "
        SELECT p.id, p.payment_reference, p.payment_method, p.payment_provider,
               p.amount, p.payment_status, p.paid_at, p.expires_at, p.created_at,
               b.id AS booking_id, b.booking_reference, b.booking_status, b.user_id
        FROM public.payments p
        JOIN public.bookings b ON b.id = p.booking_id
        WHERE " . ($paymentId > 0 ? "p.id = :val" : (!empty($paymentRef) ? "p.payment_reference = :val" : "b.booking_reference = :val"));

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':val' => $paymentId > 0 ? $paymentId : (!empty($paymentRef) ? $paymentRef : $bookingRef)]);
    $payment = $stmt->fetch();

    if (!$payment) {
        sendNotFound('Payment record not found.');
    }

    if ($user['role'] === 'passenger' && $payment['user_id'] !== $user['id']) {
        sendForbidden('You are not authorized to check this payment.');
    }

    sendSuccess('Payment status retrieved.', [
        'payment' => $payment
    ]);

} catch (Exception $e) {
    sendServerError('Failed to fetch payment status.');
}

