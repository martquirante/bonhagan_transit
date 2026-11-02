<?php
/**
 * BONHAGAN TRANSIT — Get Trip Details API
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/helpers/response.php';

$tripId = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;

if ($tripId <= 0) {
    sendError('Missing or invalid trip_id.', ['trip_id' => 'Valid trip_id is required.'], 400);
}

try {
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare("
        SELECT t.id, t.trip_code, t.departure_datetime, t.estimated_arrival, t.actual_departure, t.actual_arrival,
               t.status, t.standing_enabled, t.standing_capacity_override, t.notes,
               r.id AS route_id, r.route_code, r.route_name, r.direction, r.origin_name, r.destination_name,
               b.id AS bus_id, b.bus_number, b.plate_number, b.bus_name, b.seating_capacity, b.standing_capacity,
               bt.id AS bus_type_id, bt.type_name AS bus_type
        FROM public.trips t
        JOIN public.routes r ON r.id = t.route_id
        JOIN public.buses b ON b.id = t.bus_id
        JOIN public.bus_types bt ON bt.id = b.bus_type_id
        WHERE t.id = :trip_id
    ");
    $stmt->execute([':trip_id' => $tripId]);
    $trip = $stmt->fetch();

    if (!$trip) {
        sendNotFound('Trip not found.');
    }

    // Fetch ordered route stops
    $stmtStops = $pdo->prepare("
        SELECT rs.id AS route_stop_id, rs.sequence_number, rs.distance_from_origin, rs.estimated_minutes_from_origin,
               s.id AS stop_id, s.stop_name, s.address, s.latitude, s.longitude
        FROM public.route_stops rs
        JOIN public.stops s ON s.id = rs.stop_id
        WHERE rs.route_id = :route_id
        ORDER BY rs.sequence_number ASC
    ");
    $stmtStops->execute([':route_id' => $trip['route_id']]);
    $stops = $stmtStops->fetchAll();

    $trip['route_stops'] = $stops;

    sendSuccess('Trip details retrieved.', [
        'trip' => $trip
    ]);

} catch (Exception $e) {
    sendServerError('Failed to fetch trip details.');
}

