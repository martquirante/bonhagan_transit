<?php
/**
 * BONHAGAN TRANSIT — Record Passenger Boarding API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/staff-middleware.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';
require_once dirname(dirname(__DIR__)) . '/helpers/validation.php';
require_once dirname(dirname(__DIR__)) . '/services/TicketService.php';

$staff = requireStaff();

// If GET, return boarding records for a trip
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tripId = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;
    try {
        $pdo = Database::getConnection();
        $sql = "
            SELECT br.id, br.boarded_at, br.verification_method, br.notes,
                   tkt.ticket_reference, bp.passenger_name, bs.seat_number, pa.allocation_type,
                   p.full_name AS staff_name
            FROM public.boarding_records br
            JOIN public.tickets tkt ON tkt.id = br.ticket_id
            JOIN public.booking_passengers bp ON bp.id = tkt.passenger_id
            JOIN public.bookings b ON b.id = bp.booking_id
            LEFT JOIN public.passenger_allocations pa ON pa.passenger_id = bp.id
            LEFT JOIN public.bus_seats bs ON bs.id = pa.seat_id
            LEFT JOIN public.profiles p ON p.id = br.verified_by
            WHERE (:trip_id = 0 OR b.trip_id = :trip_id)
            ORDER BY br.boarded_at DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':trip_id' => $tripId]);
        $records = $stmt->fetchAll();

        sendSuccess('Boarding records retrieved.', [
            'count' => count($records),
            'records' => $records
        ]);
    } catch (Exception $e) {
        sendServerError('Failed to fetch boarding records.');
    }
}

// POST: Record new boarding
validateCsrfToken();
$input = getJsonInput(true);

$validator = Validator::make($input)->required('token');
if ($validator->fails()) {
    sendError('Validation failed.', $validator->getErrors(), 422);
}

$token = trim($input['token']);
$method = $input['verification_method'] ?? 'qr';
$notes = $input['notes'] ?? null;

try {
    $result = TicketService::validateBoarding($token, $staff['id'], $method, $notes);
    sendSuccess('Boarding recorded.', ['ticket' => $result]);
} catch (RuntimeException $e) {
    sendError($e->getMessage(), ['boarding' => $e->getMessage()], 409);
} catch (Exception $e) {
    sendServerError('Failed to record boarding.');
}

