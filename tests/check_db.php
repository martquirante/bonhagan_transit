<?php
require_once __DIR__ . '/../backend/config/database.php';
$pdo = Database::getConnection();

echo "=== BUSES ===\n";
$stmt = $pdo->query("SELECT b.id, b.bus_number, b.plate_number, b.bus_name, bt.type_name, b.seating_capacity FROM public.buses b LEFT JOIN public.bus_types bt ON b.bus_type_id = bt.id ORDER BY b.id");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['id']} | {$r['bus_number']} | {$r['plate_number']} | {$r['type_name']} | {$r['bus_name']} ({$r['seating_capacity']} seats)\n";
}

echo "\n=== ROUTES ===\n";
$stmt = $pdo->query("SELECT id, route_code, route_name, direction, origin_name, destination_name FROM public.routes ORDER BY id");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['id']} | {$r['route_code']} | {$r['route_name']} | {$r['direction']}\n";
}

echo "\n=== STOPS ===\n";
$stmt = $pdo->query("SELECT id, stop_name, address FROM public.stops ORDER BY id");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['id']} | {$r['stop_name']}\n";
}

echo "\n=== TRIPS COUNT ===\n";
$stmt = $pdo->query("SELECT COUNT(*) as c FROM public.trips");
echo "Total trips in DB: " . $stmt->fetch(PDO::FETCH_ASSOC)['c'] . "\n";

echo "\n=== RECENT TRIPS ===\n";
$stmt = $pdo->query("SELECT t.id, t.trip_code, r.route_name, b.bus_number, t.departure_datetime, t.status FROM public.trips t JOIN public.routes r ON t.route_id = r.id JOIN public.buses b ON t.bus_id = b.id ORDER BY t.departure_datetime DESC LIMIT 10");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['id']} | {$r['trip_code']} | {$r['bus_number']} | {$r['departure_datetime']} | {$r['status']}\n";
}

