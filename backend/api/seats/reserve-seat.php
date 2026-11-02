<?php
/**
 * BONHAGAN TRANSIT — Validate Seat Availability API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/auth-middleware.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';
require_once dirname(dirname(__DIR__)) . '/helpers/validation.php';
require_once dirname(dirname(__DIR__)) . '/services/SeatReservationService.php';

$user = requireAuth();
$input = getJsonInput(true);

$validator = Validator::make($input)
    ->required('trip_id')
    ->integer('trip_id')
    ->required('seat_id')
    ->integer('seat_id')
    ->required('boarding_stop_id')
    ->integer('boarding_stop_id')
    ->required('dropoff_stop_id')
    ->integer('dropoff_stop_id');

if ($validator->fails()) {
    sendError('Validation failed.', $validator->getErrors(), 422);
}

$tripId = (int)$input['trip_id'];
$seatId = (int)$input['seat_id'];
$boardId = (int)$input['boarding_stop_id'];
$dropId = (int)$input['dropoff_stop_id'];

try {
    $pdo = Database::getConnection();
    SeatReservationService::cleanupExpiredHolds();

    $stmtSeq = $pdo->prepare("SELECT id, sequence_number FROM public.route_stops WHERE id IN (:b, :d)");
    $stmtSeq->execute([':b' => $boardId, ':d' => $dropId]);
    $seqs = [];
    foreach ($stmtSeq->fetchAll() as $s) {
        $seqs[$s['id']] = (int)$s['sequence_number'];
    }

    if (!isset($seqs[$boardId]) || !isset($seqs[$dropId]) || $seqs[$boardId] >= $seqs[$dropId]) {
        sendError('Invalid boarding or drop-off stop sequence.', [], 422);
    }

    $available = SeatReservationService::isSeatAvailable($pdo, $tripId, $seatId, $seqs[$boardId], $seqs[$dropId]);

    if (!$available) {
        sendError('Seat is currently occupied or held by another passenger for this segment.', [
            'seat_id' => 'Seat is not available.'
        ], 409);
    }

    sendSuccess('Seat is available for reservation.', [
        'available' => true,
        'seat_id' => $seatId,
        'trip_id' => $tripId
    ]);

} catch (Exception $e) {
    sendServerError('Seat validation error.');
}

