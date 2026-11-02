<?php
/**
 * BONHAGAN TRANSIT — Get Published Schedules API
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/helpers/response.php';

$routeId = isset($_GET['route_id']) ? (int)$_GET['route_id'] : 0;
$direction = isset($_GET['direction']) ? strtolower(trim($_GET['direction'])) : '';
$date = isset($_GET['date']) ? trim($_GET['date']) : '';
$limit = isset($_GET['limit']) ? min(300, max(1, (int)$_GET['limit'])) : 250;

try {
    $pdo = Database::getConnection();

    $sql = "
        SELECT t.id AS trip_id, t.trip_code, t.departure_datetime, t.estimated_arrival, t.status,
               r.id AS route_id, r.route_code, r.route_name, r.direction, r.origin_name, r.destination_name,
               b.bus_number, b.plate_number, bt.type_name AS bus_type,
               CASE WHEN LOWER(bt.type_name) LIKE '%air%' THEN 'aircon' ELSE 'ordinary' END AS bus_type_code,
               b.seating_capacity
        FROM public.trips t
        JOIN public.routes r ON r.id = t.route_id
        JOIN public.buses b ON b.id = t.bus_id
        JOIN public.bus_types bt ON bt.id = b.bus_type_id
        WHERE t.status IN ('scheduled', 'boarding', 'delayed')
    ";

    $params = [];
    if (!empty($date)) {
        $sql .= " AND DATE(t.departure_datetime) = :date ";
        $params[':date'] = $date;
    } else {
        $sql .= " AND DATE(t.departure_datetime) >= CURRENT_DATE ";
    }

    if ($routeId > 0) {
        $sql .= " AND r.id = :route_id ";
        $params[':route_id'] = $routeId;
    }
    if (in_array($direction, ['northbound', 'southbound'], true)) {
        $sql .= " AND r.direction = :direction ";
        $params[':direction'] = $direction;
    }

    $sql .= " ORDER BY t.departure_datetime ASC LIMIT :limit ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Provide both direct array and wrapped property for max compatibility
    sendSuccess('Schedules retrieved.', $schedules);

} catch (Exception $e) {
    sendServerError('Failed to load schedules.');
}
