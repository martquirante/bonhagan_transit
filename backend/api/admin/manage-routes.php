<?php
/**
 * BONHAGAN TRANSIT — Admin Manage Routes API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/admin-middleware.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';
require_once dirname(dirname(__DIR__)) . '/helpers/validation.php';
require_once dirname(dirname(__DIR__)) . '/helpers/audit-logger.php';

$admin = requireAdmin();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = Database::getConnection();

if ($method === 'GET') {
    try {
        $stmt = $pdo->query("
            SELECT r.id, r.route_code, r.route_name, r.direction, r.origin_name, r.destination_name,
                   r.description, r.status, r.created_at,
                   (SELECT COUNT(*) FROM public.route_stops rs WHERE rs.route_id = r.id) AS stops_count
            FROM public.routes r
            ORDER BY r.direction ASC, r.route_code ASC
        ");
        sendSuccess('Routes retrieved.', ['routes' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        sendServerError('Failed to fetch routes.');
    }
}

validateCsrfToken();
$input = getJsonInput(true);

if ($method === 'POST') {
    $validator = Validator::make($input)
        ->required('route_code')
        ->required('route_name')
        ->required('direction')
        ->in('direction', ['northbound', 'southbound'])
        ->required('origin_name')
        ->required('destination_name');

    if ($validator->fails()) {
        sendError('Validation failed.', $validator->getErrors(), 422);
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO public.routes (route_code, route_name, direction, origin_name, destination_name, description, status, created_at, updated_at)
            VALUES (:code, :name, :dir, :origin, :dest, :desc, 'active', NOW(), NOW())
            RETURNING id, route_code, route_name, direction, origin_name, destination_name, status
        ");
        $stmt->execute([
            ':code' => strtoupper(trim($input['route_code'])),
            ':name' => trim($input['route_name']),
            ':dir' => strtolower(trim($input['direction'])),
            ':origin' => trim($input['origin_name']),
            ':dest' => trim($input['destination_name']),
            ':desc' => $input['description'] ?? null
        ]);
        $route = $stmt->fetch();

        AuditLogger::log('create_route', 'routes', (string)$route['id'], ['code' => $route['route_code']], (string)$admin['id']);
        sendSuccess('Route created successfully.', ['route' => $route], 201);

    } catch (Exception $e) {
        sendError('Failed to create route: ' . $e->getMessage(), [], 400);
    }
}

if ($method === 'PUT' || $method === 'PATCH') {
    $routeId = (int)($input['route_id'] ?? 0);
    if ($routeId <= 0) sendError('route_id is required.', [], 400);

    try {
        $stmt = $pdo->prepare("
            UPDATE public.routes
            SET route_name = COALESCE(:name, route_name),
                status = COALESCE(:status, status),
                description = COALESCE(:desc, description),
                updated_at = NOW()
            WHERE id = :id
            RETURNING id, route_code, route_name, status
        ");
        $stmt->execute([
            ':name' => $input['route_name'] ?? null,
            ':status' => $input['status'] ?? null,
            ':desc' => $input['description'] ?? null,
            ':id' => $routeId
        ]);
        $route = $stmt->fetch();
        if (!$route) sendNotFound('Route not found.');

        AuditLogger::log('update_route', 'routes', (string)$routeId, [], (string)$admin['id']);
        sendSuccess('Route updated.', ['route' => $route]);
    } catch (Exception $e) {
        sendServerError('Failed to update route.');
    }
}

