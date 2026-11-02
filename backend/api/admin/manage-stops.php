<?php
/**
 * BONHAGAN TRANSIT — Admin Manage Stops & Route Sequencing API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/admin-middleware.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';
require_once dirname(dirname(__DIR__)) . '/helpers/validation.php';
require_once dirname(dirname(__DIR__)) . '/helpers/audit-logger.php';

$admin = requireAdmin();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = Database::getConnection();

// GET: List stops or route sequence
if ($method === 'GET') {
    $routeId = isset($_GET['route_id']) ? (int)$_GET['route_id'] : 0;
    try {
        if ($routeId > 0) {
            $stmt = $pdo->prepare("
                SELECT rs.id AS route_stop_id, rs.sequence_number, rs.distance_from_origin, rs.estimated_minutes_from_origin,
                       s.id AS stop_id, s.stop_name, s.address, s.latitude, s.longitude, s.is_active
                FROM public.route_stops rs
                JOIN public.stops s ON s.id = rs.stop_id
                WHERE rs.route_id = :route_id
                ORDER BY rs.sequence_number ASC
            ");
            $stmt->execute([':route_id' => $routeId]);
            sendSuccess('Route sequence retrieved.', ['route_stops' => $stmt->fetchAll()]);
        } else {
            $stmt = $pdo->query("SELECT id, stop_name, address, latitude, longitude, is_active FROM public.stops ORDER BY stop_name ASC");
            sendSuccess('Stops retrieved.', ['stops' => $stmt->fetchAll()]);
        }
    } catch (Exception $e) {
        sendServerError('Failed to fetch stops.');
    }
}

validateCsrfToken();
$input = getJsonInput(true);

// POST: Add new physical stop OR associate stop to route sequence
if ($method === 'POST') {
    $action = $input['action'] ?? 'create_stop';

    if ($action === 'create_stop') {
        $validator = Validator::make($input)->required('stop_name')->maxLength('stop_name', 150);
        if ($validator->fails()) sendError('Validation failed.', $validator->getErrors(), 422);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO public.stops (stop_name, address, latitude, longitude, is_active, created_at)
                VALUES (:name, :addr, :lat, :lng, TRUE, NOW())
                RETURNING id, stop_name, address, latitude, longitude
            ");
            $stmt->execute([
                ':name' => trim($input['stop_name']),
                ':addr' => $input['address'] ?? null,
                ':lat' => $input['latitude'] ?? null,
                ':lng' => $input['longitude'] ?? null
            ]);
            $stop = $stmt->fetch();
            AuditLogger::log('create_stop', 'stops', (string)$stop['id'], ['name' => $stop['stop_name']], (string)$admin['id']);
            sendSuccess('Stop created.', ['stop' => $stop], 201);
        } catch (Exception $e) {
            sendError('Failed to create stop: ' . $e->getMessage(), [], 400);
        }
    } else if ($action === 'add_to_route') {
        $validator = Validator::make($input)
            ->required('route_id')->integer('route_id')
            ->required('stop_id')->integer('stop_id')
            ->required('sequence_number')->integer('sequence_number');
        if ($validator->fails()) sendError('Validation failed.', $validator->getErrors(), 422);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO public.route_stops (route_id, stop_id, sequence_number, distance_from_origin, estimated_minutes_from_origin)
                VALUES (:r_id, :s_id, :seq, :dist, :mins)
                RETURNING id, route_id, stop_id, sequence_number, distance_from_origin, estimated_minutes_from_origin
            ");
            $stmt->execute([
                ':r_id' => (int)$input['route_id'],
                ':s_id' => (int)$input['stop_id'],
                ':seq' => (int)$input['sequence_number'],
                ':dist' => $input['distance_from_origin'] ?? 0.00,
                ':mins' => $input['estimated_minutes_from_origin'] ?? 0
            ]);
            $routeStop = $stmt->fetch();
            sendSuccess('Stop added to route sequence.', ['route_stop' => $routeStop], 201);
        } catch (Exception $e) {
            sendError('Failed to associate stop to route: ' . $e->getMessage(), [], 400);
        }
    }
}

