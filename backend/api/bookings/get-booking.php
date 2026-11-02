<?php
/**
 * BONHAGAN TRANSIT — Get Booking Details API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/auth-middleware.php';

$user = requireAuth();

$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$bookingRef = isset($_GET['booking_reference']) ? trim($_GET['booking_reference']) : '';

if ($bookingId <= 0 && empty($bookingRef)) {
    sendError('Booking identifier required (booking_id or booking_reference).', [], 400);
}

try {
    $pdo = Database::getConnection();

    $sql = "
        SELECT b.id, b.booking_reference, b.booking_status, b.total_amount,
               b.payment_deadline, b.booking_date, b.user_id,
               u.full_name AS booked_by_name, u.email AS booked_by_email,
               t.id AS trip_id, t.trip_code, t.departure_datetime, t.estimated_arrival, t.status AS trip_status,
               r.route_name, r.direction, r.origin_name, r.destination_name,
               bus.bus_number, bt.type_name AS bus_type,
               pay.id AS payment_id, pay.payment_reference, pay.payment_method, pay.payment_status, pay.expires_at AS payment_expires_at,
               (
                   SELECT json_agg(json_build_object(
                       'passenger_id', bp.id,
                       'passenger_name', bp.passenger_name,
                       'passenger_type', bp.passenger_type,
                       'mobile_number', bp.mobile_number,
                       'base_fare', bp.base_fare,
                       'discount_amount', bp.discount_amount,
                       'final_fare', bp.final_fare,
                       'boarding_stop', s_b.stop_name,
                       'dropoff_stop', s_d.stop_name,
                       'allocation_type', pa.allocation_type,
                       'seat_number', bs.seat_number,
                       'ticket_id', tkt.id,
                       'ticket_reference', tkt.ticket_reference,
                       'ticket_status', tkt.ticket_status,
                       'qr_token', tkt.qr_token
                   ))
                   FROM public.booking_passengers bp
                   JOIN public.route_stops rs_b ON rs_b.id = bp.boarding_route_stop_id
                   JOIN public.stops s_b ON s_b.id = rs_b.stop_id
                   JOIN public.route_stops rs_d ON rs_d.id = bp.dropoff_route_stop_id
                   JOIN public.stops s_d ON s_d.id = rs_d.stop_id
                   LEFT JOIN public.passenger_allocations pa ON pa.passenger_id = bp.id
                   LEFT JOIN public.bus_seats bs ON bs.id = pa.seat_id
                   LEFT JOIN public.tickets tkt ON tkt.passenger_id = bp.id
                   WHERE bp.booking_id = b.id
               ) AS passengers
        FROM public.bookings b
        JOIN public.profiles u ON u.id = b.user_id
        JOIN public.trips t ON t.id = b.trip_id
        JOIN public.routes r ON r.id = t.route_id
        JOIN public.buses bus ON bus.id = t.bus_id
        JOIN public.bus_types bt ON bt.id = bus.bus_type_id
        LEFT JOIN public.payments pay ON pay.booking_id = b.id
        WHERE " . ($bookingId > 0 ? "b.id = :b_id" : "b.booking_reference = :b_ref");

    $stmt = $pdo->prepare($sql);
    if ($bookingId > 0) {
        $stmt->execute([':b_id' => $bookingId]);
    } else {
        $stmt->execute([':b_ref' => $bookingRef]);
    }
    $booking = $stmt->fetch();

    if (!$booking) {
        sendNotFound('Booking not found.');
    }

    // Role check: passenger can only view their own booking
    if ($user['role'] === 'passenger' && $booking['user_id'] !== $user['id']) {
        sendForbidden('You are not authorized to view this booking.');
    }

    if (is_string($booking['passengers'])) {
        $booking['passengers'] = json_decode($booking['passengers'], true);
    }

    sendSuccess('Booking details retrieved.', [
        'booking' => $booking
    ]);

} catch (Exception $e) {
    sendServerError('Failed to fetch booking details.');
}

