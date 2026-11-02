<?php
require_once __DIR__ . '/../backend/config/database.php';
$pdo = Database::getConnection();

$stmt = $pdo->query("
    SELECT r.id as route_id, r.route_code, r.route_name, r.direction,
           COUNT(rs.id) as stops_count
    FROM public.routes r
    LEFT JOIN public.route_stops rs ON r.id = rs.route_id
    GROUP BY r.id, r.route_code, r.route_name, r.direction
    ORDER BY r.id
");

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Route {$r['route_id']}: {$r['route_code']} | {$r['route_name']} | Direction: {$r['direction']} | Stops: {$r['stops_count']}\n";
}

echo "\n--- Stops for Route 1 ---\n";
$stmt = $pdo->query("SELECT rs.sequence_number, rs.distance_from_origin, s.stop_name FROM public.route_stops rs JOIN public.stops s ON rs.stop_id = s.id WHERE rs.route_id = 1 ORDER BY rs.sequence_number");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  #{$r['sequence_number']}: {$r['stop_name']} ({$r['distance_from_origin']} km)\n";
}

echo "\n--- Stops for Route 2 ---\n";
$stmt = $pdo->query("SELECT rs.sequence_number, rs.distance_from_origin, s.stop_name FROM public.route_stops rs JOIN public.stops s ON rs.stop_id = s.id WHERE rs.route_id = 2 ORDER BY rs.sequence_number");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  #{$r['sequence_number']}: {$r['stop_name']} ({$r['distance_from_origin']} km)\n";
}

