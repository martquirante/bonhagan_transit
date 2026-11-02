<?php
/**
 * BONHAGAN TRANSIT — Get Seat Map & Segment Availability API
 * Supports trip_id with origin/boarding and destination/dropoff variations.
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/services/SeatReservationService.php';
require_once dirname(dirname(__DIR__)) . '/services/FareService.php';
require_once dirname(dirname(__DIR__)) . '/helpers/response.php';

function parseStopParam($val): int {
    if ($val === null || $val === '') return 0;
    $digits = preg_replace('/\D/', '', (string)$val);
    if ($digits === '') return 0;
    return (int)$digits;
}

$tripId = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;

$rawBoarding = isset($_GET['boarding_stop_id']) ? parseStopParam($_GET['boarding_stop_id']) :
               (isset($_GET['origin_stop_id']) ? parseStopParam($_GET['origin_stop_id']) :
               (isset($_GET['origin_id']) ? parseStopParam($_GET['origin_id']) :
               (isset($_GET['origin']) ? parseStopParam($_GET['origin']) : 0)));

$rawDropoff = isset($_GET['dropoff_stop_id']) ? parseStopParam($_GET['dropoff_stop_id']) :
              (isset($_GET['destination_stop_id']) ? parseStopParam($_GET['destination_stop_id']) :
              (isset($_GET['destination_id']) ? parseStopParam($_GET['destination_id']) :
              (isset($_GET['destination']) ? parseStopParam($_GET['destination']) : 0)));

if ($tripId <= 0) {
    sendError('Missing required trip_id parameter.', ['params' => 'trip_id is required.'], 400);
}

try {
    $pdo = Database::getConnection();

    // 1. Fetch trip and route metadata
    $stmtTrip = $pdo->prepare("
        SELECT t.id AS trip_id, t.trip_code, t.departure_datetime, t.estimated_arrival,
               t.bus_id, t.route_id,
               r.route_code, r.route_name, r.direction, r.origin_name, r.destination_name,
               b.bus_number, b.plate_number, b.bus_name, b.seating_capacity, b.standing_capacity,
               bt.id AS bus_type_id, bt.type_name AS bus_type
        FROM public.trips t
        JOIN public.routes r ON r.id = t.route_id
        JOIN public.buses b ON b.id = t.bus_id
        JOIN public.bus_types bt ON bt.id = b.bus_type_id
        WHERE t.id = :trip_id
        LIMIT 1
    ");
    $stmtTrip->execute([':trip_id' => $tripId]);
    $tripInfo = $stmtTrip->fetch(PDO::FETCH_ASSOC);

    if (!$tripInfo) {
        sendError('Trip not found.', [], 404);
    }

    // 2. Fetch all route stops for this trip's route in sequence
    $stmtRs = $pdo->prepare("
        SELECT rs.id AS route_stop_id, rs.stop_id, rs.sequence_number, s.stop_name
        FROM public.route_stops rs
        JOIN public.stops s ON s.id = rs.stop_id
        WHERE rs.route_id = :route_id
        ORDER BY rs.sequence_number ASC
    ");
    $stmtRs->execute([':route_id' => $tripInfo['route_id']]);
    $routeStops = $stmtRs->fetchAll(PDO::FETCH_ASSOC);

    if (empty($routeStops)) {
        sendServerError('No route stops configured for this corridor.');
    }

    // 3. Resolve boarding and dropoff route_stops
    // Resolves: route_stop_id directly, physical stop_id (7..14), or 1..N sequence index
    $resolveRouteStop = function($val, $fallbackIdx) use ($routeStops) {
        if ($val > 0) {
            // Check direct route_stop_id match
            foreach ($routeStops as $rs) {
                if ((int)$rs['route_stop_id'] === $val) return $rs;
            }
            // Check physical stop_id match
            foreach ($routeStops as $rs) {
                if ((int)$rs['stop_id'] === $val) return $rs;
            }
            // Check 1-based index (e.g. 1 for first stop, 8 for 8th stop)
            if ($val >= 1 && $val <= count($routeStops)) {
                return $routeStops[$val - 1];
            }
        }
        $idx = max(0, min(count($routeStops) - 1, $fallbackIdx));
        return $routeStops[$idx];
    };

    $boardStop = $resolveRouteStop($rawBoarding, 0);
    $dropStop = $resolveRouteStop($rawDropoff, count($routeStops) - 1);

    // Ensure boarding precedes dropoff
    if ((int)$boardStop['sequence_number'] >= (int)$dropStop['sequence_number']) {
        // If same or inverted, default to full route segment
        $boardStop = $routeStops[0];
        $dropStop = $routeStops[count($routeStops) - 1];
    }

    $bRouteStopId = (int)$boardStop['route_stop_id'];
    $dRouteStopId = (int)$dropStop['route_stop_id'];

    // 4. Calculate seat availability from service
    $seatData = SeatReservationService::getSeatMap($tripId, $bRouteStopId, $dRouteStopId);

    // 5. Calculate base fare for the selected segment
    try {
        $fareData = FareService::calculate(
            (int)$tripInfo['route_id'],
            (int)$tripInfo['bus_type_id'],
            $bRouteStopId,
            $dRouteStopId,
            'regular'
        );
        $baseFare = (float)$fareData['final_fare'];
    } catch (Exception $e) {
        $baseFare = ($tripInfo['bus_type_id'] == 1) ? 65.00 : 50.00;
    }

    // 6. Enrich response with full trip data so frontend renders immediately
    $seatData['trip'] = [
        'trip_id' => (int)$tripInfo['trip_id'],
        'trip_code' => $tripInfo['trip_code'],
        'bus_number' => $tripInfo['bus_number'],
        'plate_number' => $tripInfo['plate_number'] ?? '',
        'bus_name' => $tripInfo['bus_name'],
        'bus_type' => $tripInfo['bus_type'],
        'bus_type_code' => (stripos($tripInfo['bus_type'], 'air') !== false) ? 'aircon' : 'ordinary',
        'departure_time' => $tripInfo['departure_datetime'],
        'estimated_arrival' => $tripInfo['estimated_arrival'],
        'origin_name' => $boardStop['stop_name'],
        'destination_name' => $dropStop['stop_name'],
        'boarding_stop_id' => $bRouteStopId,
        'dropoff_stop_id' => $dRouteStopId,
        'base_fare' => $baseFare
    ];

    sendSuccess('Seat availability calculated.', $seatData);

} catch (InvalidArgumentException $e) {
    sendError($e->getMessage(), ['segment' => $e->getMessage()], 422);
} catch (Exception $e) {
    sendServerError('Failed to calculate seat availability: ' . $e->getMessage());
}
