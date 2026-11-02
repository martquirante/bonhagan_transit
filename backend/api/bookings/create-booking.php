<?php
/**
 * BONHAGAN TRANSIT — Create Multi-Passenger Booking API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/auth-middleware.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';
require_once dirname(dirname(__DIR__)) . '/helpers/validation.php';
require_once dirname(dirname(__DIR__)) . '/services/BookingService.php';

$user = requireAuth();
validateCsrfToken();

$input = getJsonInput(true);

$validator = Validator::make($input)
    ->required('trip_id')
    ->integer('trip_id')
    ->required('passengers');

if ($validator->fails() || !is_array($input['passengers']) || empty($input['passengers'])) {
    sendError('Validation failed.', array_merge(
        $validator->getErrors(),
        empty($input['passengers']) ? ['passengers' => 'At least one passenger must be included.'] : []
    ), 422);
}

$tripId = (int)$input['trip_id'];
$passengers = $input['passengers'];

try {
    $result = BookingService::createBooking($user['id'], $tripId, $passengers);
    sendSuccess('Booking created successfully. Please settle payment to confirm your seats.', $result, 201);
} catch (InvalidArgumentException $e) {
    sendError($e->getMessage(), ['booking' => $e->getMessage()], 422);
} catch (RuntimeException $e) {
    sendError($e->getMessage(), ['conflict' => $e->getMessage()], 409);
} catch (Exception $e) {
    sendServerError('An unexpected error occurred while creating your booking.');
}

