<?php
/**
 * BONHAGAN TRANSIT — Admin Manage Trips & Schedules API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/admin-middleware.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';
require_once dirname(dirname(__DIR__)) . '/helpers/validation.php';
require_once dirname(dirname(__DIR__)) . '/helpers/audit-logger.php';
require_once dirname(dirname(__DIR__)) . '/services/NotificationService.php';

$admin = requireAdmin();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = Database::getConnection();

// GET: List all trips with filters
if ($method === 'GET') {
    try {
        $stmt = $pdo->query("
            SELECT t.id, t.trip_code, t.departure_datetime, t.estimated_arrival, t.status,
                   t.standing_enabled, t.standing_capacity_override, t.notes,
                   r.route_name, r.direction,
                   b.bus_number, bt.type_name AS bus_type,
                   (
                       SELECT json_agg(json_build_object(
                           'staff_id', p.id,
                           'staff_name', p.full_name,
                           'role', ts.assignment_role
                       ))
                       FROM public.trip_staff ts
                       JOIN public.profiles p ON p.id = ts.staff_id
                       WHERE ts.trip_id = t.id
                   ) AS assigned_staff
            FROM public.trips t
            JOIN public.routes r ON r.id = t.route_id
            JOIN public.buses b ON b.id = t.bus_id
            JOIN public.bus_types bt ON bt.id = b.bus_type_id
            ORDER BY t.departure_datetime DESC LIMIT 100
        ");
        $trips = $stmt->fetchAll();
        foreach ($trips as &$t) {
            if (is_string($t['assigned_staff'])) {
                $t['assigned_staff'] = json_decode($t['assigned_staff'], true);
            }
        }
        sendSuccess('Trips retrieved.', ['trips' => $trips]);
    } catch (Exception $e) {
        sendServerError('Failed to fetch trips.');
    }
}

validateCsrfToken();
$input = getJsonInput(true);

// POST: Schedule new trip
if ($method === 'POST') {
    $validator = Validator::make($input)
        ->required('route_id')->integer('route_id')
        ->required('bus_id')->integer('bus_id')
        ->required('departure_datetime')
        ->required('estimated_arrival');

    if ($validator->fails()) {
        sendError('Validation failed.', $validator->getErrors(), 422);
    }

    $routeId = (int)$input['route_id'];
    $busId = (int)$input['bus_id'];
    $depTime = $input['departure_datetime'];
    $arrTime = $input['estimated_arrival'];
    $standingEnabled = isset($input['standing_enabled']) ? (bool)$input['standing_enabled'] : false;
    $standingOverride = isset($input['standing_capacity_override']) ? (int)$input['standing_capacity_override'] : null;

    if (strtotime($arrTime) <= strtotime($depTime)) {
        sendError('Estimated arrival must be after departure datetime.', [], 422);
    }

    $tripCode = 'TRIP-' . date('Ymd-Hi', strtotime($depTime)) . '-' . strtoupper(bin2hex(random_bytes(2)));

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO public.trips (
                trip_code, route_id, bus_id, departure_datetime, estimated_arrival,
                status, standing_enabled, standing_capacity_override, notes, created_at, updated_at
            )
            VALUES (
                :code, :r_id, :b_id, :dep, :arr,
                'scheduled', :stand_en, :stand_over, :notes, NOW(), NOW()
            )
            RETURNING id, trip_code, status
        ");
        $stmt->execute([
            ':code' => $tripCode,
            ':r_id' => $routeId,
            ':b_id' => $busId,
            ':dep' => $depTime,
            ':arr' => $arrTime,
            ':stand_en' => $standingEnabled ? 'true' : 'false',
            ':stand_over' => $standingOverride,
            ':notes' => $input['notes'] ?? null
        ]);
        $trip = $stmt->fetch();
        $tripId = (int)$trip['id'];

        // Assign staff if provided
        if (!empty($input['staff_assignments']) && is_array($input['staff_assignments'])) {
            $stmtStaff = $pdo->prepare("
                INSERT INTO public.trip_staff (trip_id, staff_id, assignment_role)
                VALUES (:t_id, :s_id, :role)
            ");
            foreach ($input['staff_assignments'] as $sa) {
                if (!empty($sa['staff_id']) && !empty($sa['role'])) {
                    $stmtStaff->execute([
                        ':t_id' => $tripId,
                        ':s_id' => $sa['staff_id'],
                        ':role' => $sa['role']
                    ]);
                }
            }
        }

        AuditLogger::log('create_trip', 'trips', (string)$tripId, ['code' => $tripCode], (string)$admin['id']);
        $pdo->commit();

        sendSuccess('Trip scheduled successfully.', ['trip' => $trip], 201);

    } catch (Exception $e) {
        $pdo->rollBack();
        sendError('Failed to schedule trip: ' . $e->getMessage(), [], 400);
    }
}

// PUT / PATCH: Update trip status or standing options
if ($method === 'PUT' || $method === 'PATCH') {
    $tripId = (int)($input['trip_id'] ?? 0);
    if ($tripId <= 0) sendError('trip_id is required.', [], 400);

    try {
        $stmtCurr = $pdo->prepare("SELECT id, status, trip_code FROM public.trips WHERE id = :id");
        $stmtCurr->execute([':id' => $tripId]);
        $curr = $stmtCurr->fetch();
        if (!$curr) sendNotFound('Trip not found.');

        $pdo->beginTransaction();

        $newStatus = $input['status'] ?? $curr['status'];
        $standingEnabled = isset($input['standing_enabled']) ? (bool)$input['standing_enabled'] : null;

        $stmtUp = $pdo->prepare("
            UPDATE public.trips
            SET status = :status,
                standing_enabled = COALESCE(:stand_en, standing_enabled),
                updated_at = NOW()
            WHERE id = :id
            RETURNING id, trip_code, status, standing_enabled
        ");
        $stmtUp->execute([
            ':status' => $newStatus,
            ':stand_en' => $standingEnabled !== null ? ($standingEnabled ? 'true' : 'false') : null,
            ':id' => $tripId
        ]);
        $updated = $stmtUp->fetch();

        if ($newStatus !== $curr['status']) {
            $stmtLog = $pdo->prepare("
                INSERT INTO public.trip_status_logs (trip_id, previous_status, new_status, updated_by, remarks, created_at)
                VALUES (:t_id, :prev, :new, :by, :rem, NOW())
            ");
            $stmtLog->execute([
                ':t_id' => $tripId,
                ':prev' => $curr['status'],
                ':new' => $newStatus,
                ':by' => $admin['id'],
                ':rem' => $input['remarks'] ?? 'Updated by Administrator'
            ]);
        }

        AuditLogger::log('update_trip', 'trips', (string)$tripId, ['status' => $newStatus], (string)$admin['id']);
        $pdo->commit();

        sendSuccess('Trip updated.', ['trip' => $updated]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        sendServerError('Failed to update trip.');
    }
}

