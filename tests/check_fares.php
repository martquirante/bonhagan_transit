<?php
require_once __DIR__ . '/../backend/config/database.php';
$pdo = Database::getConnection();

$stmt = $pdo->query("
    SELECT fm.id, fm.route_id, r.route_code, bt.type_name, fm.base_rate, fm.per_km_rate, fm.is_active,
           COUNT(fr.id) as rules_count
    FROM public.fare_matrices fm
    JOIN public.routes r ON fm.route_id = r.id
    JOIN public.bus_types bt ON fm.bus_type_id = bt.id
    LEFT JOIN public.fare_rules fr ON fm.id = fr.fare_matrix_id
    GROUP BY fm.id, fm.route_id, r.route_code, bt.type_name, fm.base_rate, fm.per_km_rate, fm.is_active
    ORDER BY fm.id
");

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Matrix {$r['id']}: Route {$r['route_id']} ({$r['route_code']}) | {$r['type_name']} | Base: ₱{$r['base_rate']} | Per KM: ₱{$r['per_km_rate']} | Rules: {$r['rules_count']}\n";
}

