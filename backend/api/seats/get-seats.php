<?php
/**
 * BONHAGAN TRANSIT — Get Seat Map & Segment Availability API
 */

require_once dirname(dirname(__DIR__)) . '/services/SeatReservationService.php';
require_once dirname(dirname(__DIR__)) . '/helpers/response.php';

$tripId = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;
$boardingStopId = isset($_GET['boarding_stop_id']) ? (int)$_GET['boarding_stop_id'] : 0;
$dropoffStopId = isset($_GET['dropoff_stop_id']) ? (int)$_GET['dropoff_stop_id'] : 0;

if ($tripId <= 0 || $boardingStopId <= 0 || $dropoffStopId <= 0) {
    sendError('Missing required parameters.', [
        'params' => 'trip_id, boarding_stop_id, and dropoff_stop_id are all required.'
    ], 400);
}

try {
    $seatData = SeatReservationService::getSeatMap($tripId, $boardingStopId, $dropoffStopId);
    sendSuccess('Seat availability calculated.', $seatData);
} catch (InvalidArgumentException $e) {
    sendError($e->getMessage(), ['segment' => $e->getMessage()], 422);
} catch (Exception $e) {
    sendServerError('Failed to calculate seat availability.');
}

