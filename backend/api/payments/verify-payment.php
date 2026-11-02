<?php
/**
 * BONHAGAN TRANSIT — Verify / Confirm Payment API
 * Authorized staff/admin verifies terminal payments; webhooks confirm online payments.
 */

require_once dirname(dirname(__DIR__)) . '/middleware/staff-middleware.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';
require_once dirname(dirname(__DIR__)) . '/helpers/validation.php';
require_once dirname(dirname(__DIR__)) . '/services/PaymentService.php';

$staff = requireStaff();
validateCsrfToken();

$input = getJsonInput(true);

$validator = Validator::make($input)
    ->required('payment_id')
    ->integer('payment_id');

if ($validator->fails()) {
    sendError('Validation failed.', $validator->getErrors(), 422);
}

$paymentId = (int)$input['payment_id'];
$notes = isset($input['notes']) ? trim($input['notes']) : null;

try {
    $confirmedPayment = PaymentService::confirmPayment(
        $paymentId,
        $staff['id'],
        'staff_terminal_counter',
        ['notes' => $notes, 'staff_name' => $staff['full_name']]
    );

    sendSuccess('Payment successfully verified and confirmed. Tickets have been issued.', [
        'payment' => $confirmedPayment
    ]);

} catch (InvalidArgumentException $e) {
    sendNotFound($e->getMessage());
} catch (Exception $e) {
    sendServerError('Payment confirmation error: ' . $e->getMessage());
}

