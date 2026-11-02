<?php
/**
 * BONHAGAN TRANSIT — Check Segment Availability API
 */

require_once dirname(dirname(__DIR__)) . '/services/SeatReservationService.php';
require_once dirname(dirname(__DIR__)) . '/helpers/response.php';

$tripId = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;
$boardingStopId = isset($_GET['boarding_stop_id']) ? (int)$_GET['boarding_stop_id'] : 0;
$dropoffStopId = isset($_GET['dropoff_stop_id']) ? (int)$_GET['dropoff_stop_id'] : 0;

if ($tripId <= 0 || $boardingStopId <= 0 || $dropoffStopId <= 0) {
    sendError('Missing required parameters.', ['params' => 'trip_id, boarding_stop_id, and dropoff_stop_id are required.'], 400);
}

try {
    $map = SeatReservationService::getSeatMap($tripId, $boardingStopId, $dropoffStopId);
    sendSuccess('Availability calculated.', [
        'trip_id' => $tripId,
        'available_seats_count' => $map['available_seats_count'],
        'standing_enabled' => $map['standing_enabled'],
        'available_standing_count' => $map['available_standing_count']
    ]);
} catch (Exception $e) {
    sendError($e->getMessage(), [], 400);
}

