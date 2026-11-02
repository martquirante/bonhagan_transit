<?php
/**
 * BONHAGAN TRANSIT — Passenger Booking History API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/auth-middleware.php';

$user = requireAuth();

try {
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare("
        SELECT b.id, b.booking_reference, b.booking_status, b.total_amount,
               b.payment_deadline, b.booking_date, b.created_at,
               t.trip_code, t.departure_datetime, t.estimated_arrival, t.status AS trip_status,
               r.route_name, r.direction, r.origin_name, r.destination_name,
               bus.bus_number, bt.type_name AS bus_type,
               pay.payment_reference, pay.payment_method, pay.payment_status,
               (
                   SELECT json_agg(json_build_object(
                       'passenger_id', bp.id,
                       'passenger_name', bp.passenger_name,
                       'passenger_type', bp.passenger_type,
                       'final_fare', bp.final_fare,
                       'seat_number', bs.seat_number,
                       'allocation_type', pa.allocation_type,
                       'ticket_reference', tkt.ticket_reference,
                       'ticket_status', tkt.ticket_status
                   ))
                   FROM public.booking_passengers bp
                   LEFT JOIN public.passenger_allocations pa ON pa.passenger_id = bp.id
                   LEFT JOIN public.bus_seats bs ON bs.id = pa.seat_id
                   LEFT JOIN public.tickets tkt ON tkt.passenger_id = bp.id
                   WHERE bp.booking_id = b.id
               ) AS passengers
        FROM public.bookings b
        JOIN public.trips t ON t.id = b.trip_id
        JOIN public.routes r ON r.id = t.route_id
        JOIN public.buses bus ON bus.id = t.bus_id
        JOIN public.bus_types bt ON bt.id = bus.bus_type_id
        LEFT JOIN public.payments pay ON pay.booking_id = b.id
        WHERE b.user_id = :user_id
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([':user_id' => $user['id']]);
    $bookings = $stmt->fetchAll();

    foreach ($bookings as &$b) {
        if (is_string($b['passengers'])) {
            $b['passengers'] = json_decode($b['passengers'], true);
        }
    }

    sendSuccess('Bookings retrieved.', [
        'bookings' => $bookings
    ]);

} catch (Exception $e) {
    sendServerError('Failed to load bookings.');
}

