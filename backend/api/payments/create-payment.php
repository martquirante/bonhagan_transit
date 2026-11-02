<?php
/**
 * BONHAGAN TRANSIT — Create Payment API
 * Initializes either online payment or terminal payment for a booking.
 */

require_once dirname(dirname(__DIR__)) . '/middleware/auth-middleware.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';
require_once dirname(dirname(__DIR__)) . '/helpers/validation.php';
require_once dirname(dirname(__DIR__)) . '/services/PaymentService.php';

$user = requireAuth();
validateCsrfToken();

$input = getJsonInput(true);

$validator = Validator::make($input)
    ->required('booking_id')
    ->integer('booking_id')
    ->required('payment_method')
    ->in('payment_method', ['online', 'terminal']);

if ($validator->fails()) {
    sendError('Validation failed.', $validator->getErrors(), 422);
}

$bookingId = (int)$input['booking_id'];
$method = $input['payment_method'];
$provider = isset($input['payment_provider']) ? trim($input['payment_provider']) : ($method === 'online' ? 'GCash' : null);

try {
    $pdo = Database::getConnection();

    // Verify ownership
    $stmtB = $pdo->prepare("SELECT id, user_id FROM public.bookings WHERE id = :id");
    $stmtB->execute([':id' => $bookingId]);
    $booking = $stmtB->fetch();

    if (!$booking) {
        sendNotFound('Booking not found.');
    }

    if ($user['role'] === 'passenger' && $booking['user_id'] !== $user['id']) {
        sendForbidden('You can only create payments for your own bookings.');
    }

    $payment = PaymentService::createPayment($bookingId, $method, $provider);

    sendSuccess(
        $method === 'terminal'
            ? 'Terminal payment registered. Please present your booking reference at the terminal before deadline.'
            : 'Online payment initiated.',
        ['payment' => $payment],
        201
    );

} catch (InvalidArgumentException $e) {
    sendError($e->getMessage(), ['payment' => $e->getMessage()], 422);
} catch (RuntimeException $e) {
    sendError($e->getMessage(), ['payment' => $e->getMessage()], 400);
} catch (Exception $e) {
    sendServerError('Payment processing failed.');
}

