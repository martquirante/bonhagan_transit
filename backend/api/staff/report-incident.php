<?php
/**
 * BONHAGAN TRANSIT — Report Trip Incident API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/staff-middleware.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';
require_once dirname(dirname(__DIR__)) . '/helpers/validation.php';
require_once dirname(dirname(__DIR__)) . '/helpers/audit-logger.php';
require_once dirname(dirname(__DIR__)) . '/services/NotificationService.php';

$staff = requireStaff();
validateCsrfToken();

$input = getJsonInput(true);

$validator = Validator::make($input)
    ->required('trip_id')
    ->integer('trip_id')
    ->required('incident_type')
    ->minLength('incident_type', 3)
    ->required('description')
    ->minLength('description', 10);

if ($validator->fails()) {
    sendError('Validation failed.', $validator->getErrors(), 422);
}

$tripId = (int)$input['trip_id'];
$type = trim($input['incident_type']);
$desc = trim($input['description']);
$updateTripStatus = isset($input['new_trip_status']) ? trim($input['new_trip_status']) : null;

try {
    $pdo = Database::getConnection();

    // Verify trip exists
    $stmtT = $pdo->prepare("SELECT id, trip_code, status FROM public.trips WHERE id = :id");
    $stmtT->execute([':id' => $tripId]);
    $trip = $stmtT->fetch();

    if (!$trip) {
        sendNotFound('Trip not found.');
    }

    $pdo->beginTransaction();

    // Insert Incident
    $stmt = $pdo->prepare("
        INSERT INTO public.incidents (trip_id, reported_by, incident_type, description, incident_status, created_at, updated_at)
        VALUES (:t_id, :rep_by, :type, :desc, 'reported', NOW(), NOW())
        RETURNING id, trip_id, incident_type, description, incident_status, created_at
    ");
    $stmt->execute([
        ':t_id' => $tripId,
        ':rep_by' => $staff['id'],
        ':type' => $type,
        ':desc' => $desc
    ]);
    $incident = $stmt->fetch();

    // Optionally update trip status if requested (e.g. 'delayed')
    if ($updateTripStatus && in_array($updateTripStatus, ['delayed', 'cancelled', 'completed', 'departed'], true)) {
        $oldStatus = $trip['status'];
        $stmtUp = $pdo->prepare("UPDATE public.trips SET status = :status, updated_at = NOW() WHERE id = :id");
        $stmtUp->execute([':status' => $updateTripStatus, ':id' => $tripId]);

        // Insert trip status log
        $stmtLog = $pdo->prepare("
            INSERT INTO public.trip_status_logs (trip_id, previous_status, new_status, updated_by, remarks, created_at)
            VALUES (:t_id, :prev, :new, :by, :rem, NOW())
        ");
        $stmtLog->execute([
            ':t_id' => $tripId,
            ':prev' => $oldStatus,
            ':new' => $updateTripStatus,
            ':by' => $staff['id'],
            ':rem' => "Status changed due to incident: {$type}"
        ]);

        // Notify booked passengers
        $stmtPass = $pdo->prepare("
            SELECT DISTINCT b.user_id
            FROM public.bookings b
            WHERE b.trip_id = :id AND b.booking_status = 'confirmed'
        ");
        $stmtPass->execute([':id' => $tripId]);
        foreach ($stmtPass->fetchAll() as $row) {
            NotificationService::notifyTripStatus($row['user_id'], $trip['trip_code'], $updateTripStatus, $desc);
        }
    }

    AuditLogger::log('report_incident', 'incidents', (string)$incident['id'], ['type' => $type], (string)$staff['id']);

    $pdo->commit();

    sendSuccess('Incident report submitted successfully.', [
        'incident' => $incident
    ], 201);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendServerError('Failed to submit incident report.');
}

