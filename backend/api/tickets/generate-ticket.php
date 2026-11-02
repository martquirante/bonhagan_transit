<?php
/**
 * BONHAGAN TRANSIT — Generate / Issue Tickets API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/auth-middleware.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';
require_once dirname(dirname(__DIR__)) . '/services/TicketService.php';

$user = requireAuth();
validateCsrfToken();

$input = getJsonInput(true);
$bookingId = (int)($input['booking_id'] ?? 0);

if ($bookingId <= 0) {
    sendError('booking_id is required.', [], 400);
}

try {
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare("SELECT id, user_id, booking_status FROM public.bookings WHERE id = :id");
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        sendNotFound('Booking not found.');
    }

    if ($user['role'] === 'passenger' && $booking['user_id'] !== $user['id']) {
        sendForbidden('You can only generate tickets for your own bookings.');
    }

    $tickets = TicketService::issueTicketsForBooking($bookingId);

    sendSuccess('Tickets issued successfully.', [
        'tickets' => $tickets
    ]);

} catch (RuntimeException $e) {
    sendError($e->getMessage(), [], 400);
} catch (Exception $e) {
    sendServerError('Failed to generate tickets.');
}

