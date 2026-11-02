<?php
/**
 * BONHAGAN TRANSIT — Trip Search API
 * Searches scheduled trips by origin, destination, travel date, time, and bus type.
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/helpers/response.php';
require_once dirname(dirname(__DIR__)) . '/services/SeatReservationService.php';
require_once dirname(dirname(__DIR__)) . '/services/FareService.php';

// Support parameter variations
$rawOrigin = isset($_GET['origin_id']) ? (int)$_GET['origin_id'] : 
             (isset($_GET['origin_stop_id']) ? (int)$_GET['origin_stop_id'] : 
             (isset($_GET['origin']) ? (int)$_GET['origin'] : 0));

$rawDest = isset($_GET['destination_id']) ? (int)$_GET['destination_id'] : 
           (isset($_GET['destination_stop_id']) ? (int)$_GET['destination_stop_id'] : 
           (isset($_GET['destination']) ? (int)$_GET['destination'] : 0));

$travelDate = isset($_GET['date']) ? trim($_GET['date']) : 
              (isset($_GET['travel_date']) ? trim($_GET['travel_date']) : '');

$preferredTime = isset($_GET['time']) ? trim($_GET['time']) : '';
$busTypeId = isset($_GET['bus_type_id']) ? (int)$_GET['bus_type_id'] : 0;
$busType = isset($_GET['bus_type']) ? strtolower(trim($_GET['bus_type'])) : '';

// Normalization: Map 1..8 indices from UI dropdowns to actual stop IDs (7..14)
function normalizeStopId(int $id): int {
    if ($id >= 1 && $id <= 8) {
        return $id + 6; // 1 -> 7 (Sta. Cruz), 8 -> 14 (Sapang Palay)
    }
    return $id;
}

$originStopId = normalizeStopId($rawOrigin);
$destinationStopId = normalizeStopId($rawDest);

try {
    $pdo = Database::getConnection();
    SeatReservationService::cleanupExpiredHolds();

    // Base query
    $sql = "
        SELECT t.id AS trip_id, t.trip_code, t.departure_datetime, t.estimated_arrival, t.status AS trip_status,
               t.standing_enabled, t.standing_capacity_override,
               r.id AS route_id, r.route_code, r.route_name, r.direction, r.origin_name, r.destination_name,
               b.id AS bus_id, b.bus_number, b.plate_number, b.bus_name, b.seating_capacity, b.standing_capacity,
               bt.id AS bus_type_id, bt.type_name AS bus_type,
               rs_board.id AS boarding_route_stop_id, rs_board.sequence_number AS board_seq, s_board.stop_name AS board_stop_name,
               rs_drop.id AS dropoff_route_stop_id, rs_drop.sequence_number AS drop_seq, s_drop.stop_name AS drop_stop_name
        FROM public.trips t
        JOIN public.routes r ON r.id = t.route_id
        JOIN public.buses b ON b.id = t.bus_id
        JOIN public.bus_types bt ON bt.id = b.bus_type_id
        LEFT JOIN public.route_stops rs_board ON rs_board.route_id = r.id AND (:origin_id = 0 OR rs_board.stop_id = :origin_id)
        LEFT JOIN public.stops s_board ON s_board.id = rs_board.stop_id
        LEFT JOIN public.route_stops rs_drop ON rs_drop.route_id = r.id AND (:dest_id = 0 OR rs_drop.stop_id = :dest_id)
        LEFT JOIN public.stops s_drop ON s_drop.id = rs_drop.stop_id
        WHERE t.status IN ('scheduled', 'boarding')
    ";

    $params = [
        ':origin_id' => $originStopId,
        ':dest_id' => $destinationStopId
    ];

    if ($originStopId > 0 && $destinationStopId > 0) {
        $sql .= " AND rs_board.sequence_number < rs_drop.sequence_number ";
    }

    if (!empty($travelDate)) {
        $sql .= " AND DATE(t.departure_datetime) = :travel_date ";
        $params[':travel_date'] = $travelDate;
        if ($travelDate === date('Y-m-d')) {
            $sql .= " AND t.departure_datetime >= (NOW() - INTERVAL '30 minutes') ";
        }
    } else {
        $sql .= " AND t.departure_datetime >= (NOW() - INTERVAL '30 minutes') ";
    }

    if (!empty($preferredTime)) {
        $sql .= " AND t.departure_datetime::time >= :pref_time ";
        $params[':pref_time'] = $preferredTime;
    }

    if ($busTypeId > 0) {
        $sql .= " AND bt.id = :bus_type_id ";
        $params[':bus_type_id'] = $busTypeId;
    } elseif (!empty($busType) && $busType !== 'all') {
        if ($busType === 'aircon' || strpos($busType, 'air') !== false) {
            $sql .= " AND LOWER(bt.type_name) LIKE '%air%' ";
        } elseif ($busType === 'ordinary' || strpos($busType, 'ord') !== false) {
            $sql .= " AND LOWER(bt.type_name) LIKE '%ord%' ";
        }
    }

    $sql .= " ORDER BY t.departure_datetime ASC LIMIT 250 ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];

    foreach ($trips as $trip) {
        $bStopId = (int)$trip['boarding_route_stop_id'];
        $dStopId = (int)$trip['dropoff_route_stop_id'];

        $availableSeats = (int)$trip['seating_capacity'];
        $fareEstimate = 0.00;

        if ($bStopId > 0 && $dStopId > 0) {
            try {
                $seatData = SeatReservationService::getSeatMap((int)$trip['trip_id'], $bStopId, $dStopId);
                $availableSeats = $seatData['available_seats_count'];
                $availableStanding = $seatData['available_standing_count'];

                $fare = FareService::calculate((int)$trip['route_id'], (int)$trip['bus_type_id'], $bStopId, $dStopId, 'regular');
                $fareEstimate = $fare['final_fare'];
            } catch (Exception $e) {
                $availableStanding = 0;
            }
        } else {
            $availableStanding = (bool)$trip['standing_enabled'] ? (int)($trip['standing_capacity_override'] ?? $trip['standing_capacity']) : 0;
        }

        $results[] = [
            'trip_id' => (int)$trip['trip_id'],
            'trip_code' => $trip['trip_code'],
            'departure_datetime' => $trip['departure_datetime'],
            'departure_time' => $trip['departure_datetime'],
            'estimated_arrival' => $trip['estimated_arrival'],
            'estimated_arrival_time' => $trip['estimated_arrival'],
            'duration_minutes' => 110,
            'bus_number' => $trip['bus_number'],
            'bus_type' => $trip['bus_type'],
            'bus_type_code' => (stripos($trip['bus_type'], 'air') !== false) ? 'aircon' : 'ordinary',
            'plate_number' => $trip['plate_number'] ?? '',
            'origin_name' => $trip['board_stop_name'] ?? $trip['origin_name'],
            'destination_name' => $trip['dropoff_stop_name'] ?? $trip['destination_name'],
            'base_fare' => $fareEstimate,
            'status' => $trip['trip_status'],
            'route' => [
                'id' => (int)$trip['route_id'],
                'code' => $trip['route_code'],
                'name' => $trip['route_name'],
                'direction' => $trip['direction'],
                'origin' => $trip['origin_name'],
                'destination' => $trip['destination_name']
            ],
            'bus' => [
                'id' => (int)$trip['bus_id'],
                'bus_number' => $trip['bus_number'],
                'plate_number' => $trip['plate_number'] ?? '',
                'name' => $trip['bus_name'],
                'type' => $trip['bus_type'],
                'seating_capacity' => (int)$trip['seating_capacity'],
                'standing_capacity' => (int)$trip['standing_capacity']
            ],
            'boarding_stop' => [
                'route_stop_id' => $bStopId ?: null,
                'name' => $trip['board_stop_name'] ?? $trip['origin_name']
            ],
            'dropoff_stop' => [
                'route_stop_id' => $dStopId ?: null,
                'name' => $trip['dropoff_stop_name'] ?? $trip['destination_name']
            ],
            'available_seats' => $availableSeats,
            'available_standing' => $availableStanding,
            'fare_estimate' => $fareEstimate
        ];
    }

    sendSuccess('Available trips retrieved.', $results);

} catch (Exception $e) {
    sendServerError('Failed to search trips: ' . $e->getMessage());
}
