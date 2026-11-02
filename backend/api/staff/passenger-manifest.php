<?php
/**
 * BONHAGAN TRANSIT — Staff Passenger Manifest API
 * Detailed manifest of passengers, seats, stops, and boarding status for a trip.
 */

require_once dirname(dirname(__DIR__)) . '/middleware/staff-middleware.php';

$staff = requireStaff();

$tripId = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;

if ($tripId <= 0) {
    sendError('trip_id is required.', [], 400);
}

try {
    $pdo = Database::getConnection();

    // Verify staff assignment (or admin)
    if ($staff['role'] !== 'admin') {
        $stmtCheck = $pdo->prepare("SELECT 1 FROM public.trip_staff WHERE trip_id = :t_id AND staff_id = :s_id LIMIT 1");
        $stmtCheck->execute([':t_id' => $tripId, ':s_id' => $staff['id']]);
        if (!$stmtCheck->fetch()) {
            sendForbidden('You are not assigned to this trip.');
        }
    }

    // Trip overview
    $stmtTrip = $pdo->prepare("
        SELECT t.id, t.trip_code, t.departure_datetime, t.status,
               r.route_name, r.direction,
               b.bus_number, b.seating_capacity, b.standing_capacity
        FROM public.trips t
        JOIN public.routes r ON r.id = t.route_id
        JOIN public.buses b ON b.id = t.bus_id
        WHERE t.id = :id
    ");
    $stmtTrip->execute([':id' => $tripId]);
    $trip = $stmtTrip->fetch();

    if (!$trip) {
        sendNotFound('Trip not found.');
    }

    // Passengers query
    $stmtPass = $pdo->prepare("
        SELECT bp.id AS passenger_id, bp.passenger_name, bp.passenger_type, bp.mobile_number,
               b.booking_reference, b.booking_status,
               rs_b.sequence_number AS board_seq, s_b.stop_name AS boarding_stop,
               rs_d.sequence_number AS drop_seq, s_d.stop_name AS dropoff_stop,
               pa.allocation_type, pa.allocation_status, bs.seat_number,
               tkt.ticket_reference, tkt.ticket_status, tkt.qr_token,
               br.boarded_at, p_staff.full_name AS boarded_by_staff
        FROM public.booking_passengers bp
        JOIN public.bookings b ON b.id = bp.booking_id
        JOIN public.route_stops rs_b ON rs_b.id = bp.boarding_route_stop_id
        JOIN public.stops s_b ON s_b.id = rs_b.stop_id
        JOIN public.route_stops rs_d ON rs_d.id = bp.dropoff_route_stop_id
        JOIN public.stops s_d ON s_d.id = rs_d.stop_id
        LEFT JOIN public.passenger_allocations pa ON pa.passenger_id = bp.id
        LEFT JOIN public.bus_seats bs ON bs.id = pa.seat_id
        LEFT JOIN public.tickets tkt ON tkt.passenger_id = bp.id
        LEFT JOIN public.boarding_records br ON br.ticket_id = tkt.id
        LEFT JOIN public.profiles p_staff ON p_staff.id = br.verified_by
        WHERE b.trip_id = :trip_id AND b.booking_status IN ('confirmed', 'pending')
        ORDER BY rs_b.sequence_number ASC, bs.seat_number ASC, bp.passenger_name ASC
    ");
    $stmtPass->execute([':trip_id' => $tripId]);
    $passengers = $stmtPass->fetchAll();

    $boardedCount = 0;
    foreach ($passengers as $p) {
        if ($p['ticket_status'] === 'boarded') {
            $boardedCount++;
        }
    }

    sendSuccess('Passenger manifest retrieved.', [
        'trip' => $trip,
        'summary' => [
            'total_passengers' => count($passengers),
            'boarded_count' => $boardedCount,
            'pending_boarding' => count($passengers) - $boardedCount
        ],
        'passengers' => $passengers
    ]);

} catch (Exception $e) {
    sendServerError('Failed to generate passenger manifest.');
}

