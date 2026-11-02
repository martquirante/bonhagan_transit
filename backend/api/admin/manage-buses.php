<?php
/**
 * BONHAGAN TRANSIT — Admin Manage Buses & Types API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/admin-middleware.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';
require_once dirname(dirname(__DIR__)) . '/helpers/validation.php';
require_once dirname(dirname(__DIR__)) . '/helpers/audit-logger.php';

$admin = requireAdmin();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$pdo = Database::getConnection();

// GET: List buses and bus types
if ($method === 'GET') {
    try {
        $stmtBuses = $pdo->query("
            SELECT b.id, b.bus_number, b.plate_number, b.bus_name, b.seating_capacity, b.standing_capacity,
                   b.status, b.created_at,
                   bt.id AS bus_type_id, bt.type_name AS bus_type
            FROM public.buses b
            JOIN public.bus_types bt ON bt.id = b.bus_type_id
            ORDER BY b.bus_number ASC
        ");
        $buses = $stmtBuses->fetchAll();

        $stmtTypes = $pdo->query("SELECT id, type_name, description, is_active FROM public.bus_types ORDER BY type_name ASC");
        $types = $stmtTypes->fetchAll();

        sendSuccess('Buses retrieved.', [
            'buses' => $buses,
            'bus_types' => $types
        ]);
    } catch (Exception $e) {
        sendServerError('Failed to fetch buses.');
    }
}

validateCsrfToken();
$input = getJsonInput(true);

// POST: Create new bus
if ($method === 'POST') {
    $validator = Validator::make($input)
        ->required('bus_number')
        ->required('plate_number')
        ->required('bus_type_id')
        ->integer('bus_type_id')
        ->required('seating_capacity')
        ->integer('seating_capacity');

    if ($validator->fails()) {
        sendError('Validation failed.', $validator->getErrors(), 422);
    }

    $busNumber = trim($input['bus_number']);
    $plateNumber = strtoupper(trim($input['plate_number']));
    $busTypeId = (int)$input['bus_type_id'];
    $busName = isset($input['bus_name']) ? trim($input['bus_name']) : $busNumber;
    $seatingCap = (int)$input['seating_capacity'];
    $standingCap = isset($input['standing_capacity']) ? (int)$input['standing_capacity'] : 0;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO public.buses (bus_type_id, bus_number, plate_number, bus_name, seating_capacity, standing_capacity, status, created_at, updated_at)
            VALUES (:type_id, :bus_no, :plate, :name, :seat_cap, :stand_cap, 'active', NOW(), NOW())
            RETURNING id, bus_number, plate_number, bus_name, seating_capacity, standing_capacity, status
        ");
        $stmt->execute([
            ':type_id' => $busTypeId,
            ':bus_no' => $busNumber,
            ':plate' => $plateNumber,
            ':name' => $busName,
            ':seat_cap' => $seatingCap,
            ':stand_cap' => $standingCap
        ]);
        $bus = $stmt->fetch();
        $busId = (int)$bus['id'];

        // Automatically generate seats (Rows of 4: A, B, C, D)
        $rows = ceil($seatingCap / 4);
        $count = 0;
        for ($r = 1; $r <= $rows; $r++) {
            foreach (['A', 'B', 'C', 'D'] as $col) {
                if ($count >= $seatingCap) break;
                $seatNumber = $r . $col;
                $stmtSeat = $pdo->prepare("
                    INSERT INTO public.bus_seats (bus_id, seat_number, seat_row, seat_column, seat_type, is_active)
                    VALUES (:b_id, :s_num, :r, :c, 'regular', TRUE)
                ");
                $stmtSeat->execute([
                    ':b_id' => $busId,
                    ':s_num' => $seatNumber,
                    ':r' => $r,
                    ':c' => ord($col) - 64
                ]);
                $count++;
            }
        }

        AuditLogger::log('create_bus', 'buses', (string)$busId, ['number' => $busNumber], (string)$admin['id']);
        $pdo->commit();

        sendSuccess('Bus and seat configuration created successfully.', ['bus' => $bus], 201);

    } catch (Exception $e) {
        $pdo->rollBack();
        sendError('Failed to create bus: ' . $e->getMessage(), [], 400);
    }
}

// PUT / PATCH: Update Bus status or details
if ($method === 'PUT' || $method === 'PATCH') {
    $busId = (int)($input['bus_id'] ?? 0);
    if ($busId <= 0) {
        sendError('bus_id is required.', [], 400);
    }

    $status = isset($input['status']) ? trim($input['status']) : null;
    $busName = isset($input['bus_name']) ? trim($input['bus_name']) : null;

    try {
        $stmt = $pdo->prepare("
            UPDATE public.buses
            SET status = COALESCE(:status, status),
                bus_name = COALESCE(:name, bus_name),
                updated_at = NOW()
            WHERE id = :id
            RETURNING id, bus_number, plate_number, bus_name, status
        ");
        $stmt->execute([
            ':status' => $status,
            ':name' => $busName,
            ':id' => $busId
        ]);
        $bus = $stmt->fetch();

        if (!$bus) {
            sendNotFound('Bus not found.');
        }

        AuditLogger::log('update_bus', 'buses', (string)$busId, ['status' => $status], (string)$admin['id']);
        sendSuccess('Bus updated successfully.', ['bus' => $bus]);

    } catch (Exception $e) {
        sendServerError('Failed to update bus.');
    }
}

