<?php
/**
 * BONHAGAN TRANSIT — Get Electronic Tickets API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/auth-middleware.php';
require_once dirname(dirname(__DIR__)) . '/services/QRCodeService.php';

$user = requireAuth();

$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$ticketRef = isset($_GET['ticket_reference']) ? trim($_GET['ticket_reference']) : '';

try {
    $pdo = Database::getConnection();

    $sql = "
        SELECT t.id AS ticket_id, t.ticket_reference, t.qr_token, t.ticket_status, t.issued_at,
               b.id AS booking_id, b.booking_reference, b.booking_status, b.user_id,
               bp.id AS passenger_id, bp.passenger_name, bp.passenger_type, bp.mobile_number,
               bp.base_fare, bp.discount_amount, bp.final_fare,
               s_board.stop_name AS boarding_stop_name, s_drop.stop_name AS dropoff_stop_name,
               tr.trip_code, tr.departure_datetime, tr.estimated_arrival,
               r.route_code, r.route_name, r.direction,
               bus.bus_number, bus.bus_name, bt.type_name AS bus_type,
               pa.allocation_type, pa.allocation_status, bs.seat_number
        FROM public.tickets t
        JOIN public.booking_passengers bp ON bp.id = t.passenger_id
        JOIN public.bookings b ON b.id = bp.booking_id
        JOIN public.trips tr ON tr.id = b.trip_id
        JOIN public.routes r ON r.id = tr.route_id
        JOIN public.buses bus ON bus.id = tr.bus_id
        JOIN public.bus_types bt ON bt.id = bus.bus_type_id
        JOIN public.route_stops rs_board ON rs_board.id = bp.boarding_route_stop_id
        JOIN public.stops s_board ON s_board.id = rs_board.stop_id
        JOIN public.route_stops rs_drop ON rs_drop.id = bp.dropoff_route_stop_id
        JOIN public.stops s_drop ON s_drop.id = rs_drop.stop_id
        LEFT JOIN public.passenger_allocations pa ON pa.passenger_id = bp.id
        LEFT JOIN public.bus_seats bs ON bs.id = pa.seat_id
        WHERE 1=1
    ";

    $params = [];
    if ($bookingId > 0) {
        $sql .= " AND b.id = :booking_id ";
        $params[':booking_id'] = $bookingId;
    }
    if (!empty($ticketRef)) {
        $sql .= " AND t.ticket_reference = :ticket_ref ";
        $params[':ticket_ref'] = $ticketRef;
    }
    if ($user['role'] === 'passenger') {
        $sql .= " AND b.user_id = :user_id ";
        $params[':user_id'] = $user['id'];
    }

    $sql .= " ORDER BY t.created_at DESC ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();

    foreach ($tickets as &$tkt) {
        $tkt['qr_payload'] = QRCodeService::buildPayload($tkt['qr_token'], $tkt['ticket_reference']);
        $tkt['qr_svg'] = QRCodeService::renderSvg($tkt['qr_token']);
    }

    sendSuccess('Tickets retrieved.', [
        'count' => count($tickets),
        'tickets' => $tickets
    ]);

} catch (Exception $e) {
    sendServerError('Failed to fetch tickets.');
}

