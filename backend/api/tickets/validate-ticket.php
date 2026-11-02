<?php
/**
 * BONHAGAN TRANSIT — Validate Ticket & Record Boarding API
 * Staff / Admin scans QR code or verifies ticket reference at the bus door.
 */

require_once dirname(dirname(__DIR__)) . '/middleware/staff-middleware.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';
require_once dirname(dirname(__DIR__)) . '/helpers/validation.php';
require_once dirname(dirname(__DIR__)) . '/services/TicketService.php';

$staff = requireStaff();
validateCsrfToken();

$input = getJsonInput(true);

$validator = Validator::make($input)
    ->required('token'); // Can be QR token (UUID) or ticket_reference

if ($validator->fails()) {
    sendError('Validation failed.', $validator->getErrors(), 422);
}

$token = trim($input['token']);
$method = isset($input['method']) && in_array($input['method'], ['qr', 'manual'], true) ? $input['method'] : 'qr';
$notes = isset($input['notes']) ? trim($input['notes']) : null;

try {
    $boardedTicket = TicketService::validateBoarding(
        $token,
        $staff['id'],
        $method,
        $notes
    );

    sendSuccess('Boarding verified successfully. Passenger permitted to board.', [
        'ticket' => $boardedTicket
    ]);

} catch (InvalidArgumentException $e) {
    sendNotFound($e->getMessage());
} catch (RuntimeException $e) {
    // E.g. ALREADY BOARDED or INVALID STATUS
    sendError($e->getMessage(), ['boarding' => $e->getMessage()], 409);
} catch (Exception $e) {
    sendServerError('Failed to validate boarding ticket.');
}

