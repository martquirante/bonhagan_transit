<?php
/**
 * BONHAGAN TRANSIT — Admin Manage Fare Matrices & Rules API
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
        $stmtMatrices = $pdo->query("
            SELECT fm.id, fm.effective_from, fm.effective_until, fm.base_rate, fm.per_km_rate, fm.is_active,
                   r.route_code, r.route_name,
                   bt.type_name AS bus_type,
                   (SELECT COUNT(*) FROM public.fare_rules fr WHERE fr.fare_matrix_id = fm.id) AS rules_count
            FROM public.fare_matrices fm
            JOIN public.routes r ON r.id = fm.route_id
            JOIN public.bus_types bt ON bt.id = fm.bus_type_id
            ORDER BY fm.is_active DESC, fm.effective_from DESC
        ");
        sendSuccess('Fare configuration retrieved.', ['matrices' => $stmtMatrices->fetchAll()]);
    } catch (Exception $e) {
        sendServerError('Failed to fetch fare configuration.');
    }
}

validateCsrfToken();
$input = getJsonInput(true);

if ($method === 'POST') {
    $action = $input['action'] ?? 'create_matrix';

    if ($action === 'create_matrix') {
        $validator = Validator::make($input)
            ->required('route_id')->integer('route_id')
            ->required('bus_type_id')->integer('bus_type_id')
            ->required('base_rate')->numeric('base_rate')
            ->required('per_km_rate')->numeric('per_km_rate');

        if ($validator->fails()) sendError('Validation failed.', $validator->getErrors(), 422);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO public.fare_matrices (route_id, bus_type_id, effective_from, base_rate, per_km_rate, is_active, created_at)
                VALUES (:r_id, :b_type, CURRENT_DATE, :base, :per_km, TRUE, NOW())
                RETURNING id, route_id, bus_type_id, base_rate, per_km_rate, is_active
            ");
            $stmt->execute([
                ':r_id' => (int)$input['route_id'],
                ':b_type' => (int)$input['bus_type_id'],
                ':base' => (float)$input['base_rate'],
                ':per_km' => (float)$input['per_km_rate']
            ]);
            $matrix = $stmt->fetch();
            AuditLogger::log('create_fare_matrix', 'fare_matrices', (string)$matrix['id'], [], (string)$admin['id']);
            sendSuccess('Fare matrix created.', ['fare_matrix' => $matrix], 201);
        } catch (Exception $e) {
            sendError('Failed to create fare matrix: ' . $e->getMessage(), [], 400);
        }
    } else if ($action === 'create_rule') {
        $validator = Validator::make($input)
            ->required('fare_matrix_id')->integer('fare_matrix_id')
            ->required('origin_route_stop_id')->integer('origin_route_stop_id')
            ->required('destination_route_stop_id')->integer('destination_route_stop_id')
            ->required('fare_amount')->numeric('fare_amount');

        if ($validator->fails()) sendError('Validation failed.', $validator->getErrors(), 422);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO public.fare_rules (fare_matrix_id, origin_route_stop_id, destination_route_stop_id, fare_amount, created_at)
                VALUES (:m_id, :o_id, :d_id, :fare, NOW())
                ON CONFLICT (fare_matrix_id, origin_route_stop_id, destination_route_stop_id)
                DO UPDATE SET fare_amount = EXCLUDED.fare_amount
                RETURNING id, fare_matrix_id, fare_amount
            ");
            $stmt->execute([
                ':m_id' => (int)$input['fare_matrix_id'],
                ':o_id' => (int)$input['origin_route_stop_id'],
                ':d_id' => (int)$input['destination_route_stop_id'],
                ':fare' => (float)$input['fare_amount']
            ]);
            sendSuccess('Fare rule saved.', ['fare_rule' => $stmt->fetch()], 201);
        } catch (Exception $e) {
            sendError('Failed to save fare rule: ' . $e->getMessage(), [], 400);
        }
    }
}

