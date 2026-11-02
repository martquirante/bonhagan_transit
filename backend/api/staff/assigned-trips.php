<?php
/**
 * BONHAGAN TRANSIT — Staff Assigned Trips API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/staff-middleware.php';

$staff = requireStaff();

try {
    $pdo = Database::getConnection();

    // If admin, show all active trips; if staff, show assigned trips
    $sql = "
        SELECT t.id AS trip_id, t.trip_code, t.departure_datetime, t.estimated_arrival, t.actual_departure, t.actual_arrival,
               t.status AS trip_status, t.standing_enabled, t.standing_capacity_override,
               r.route_code, r.route_name, r.direction, r.origin_name, r.destination_name,
               b.bus_number, b.plate_number, b.bus_name, b.seating_capacity, b.standing_capacity,
               bt.type_name AS bus_type,
               ts.assignment_role, ts.assigned_at
        FROM public.trips t
        JOIN public.routes r ON r.id = t.route_id
        JOIN public.buses b ON b.id = t.bus_id
        JOIN public.bus_types bt ON bt.id = b.bus_type_id
        " . ($staff['role'] === 'admin'
            ? "LEFT JOIN public.trip_staff ts ON ts.trip_id = t.id AND ts.staff_id = :staff_id"
            : "JOIN public.trip_staff ts ON ts.trip_id = t.id AND ts.staff_id = :staff_id") . "
        ORDER BY t.departure_datetime ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':staff_id' => $staff['id']]);
    $trips = $stmt->fetchAll();

    sendSuccess('Assigned trips retrieved.', [
        'count' => count($trips),
        'trips' => $trips
    ]);

} catch (Exception $e) {
    sendServerError('Failed to fetch assigned trips.');
}

