<?php
/**
 * BONHAGAN TRANSIT — Fleet, Fares & Dynamic Schedule Seeder
 *
 * Implements high-frequency commuter operations:
 * - Rush Hours (06:00-09:00 AM & 16:30-20:00 PM): 5 to 8 minute intervals
 * - Non-Rush Hours (04:30-06:00 AM, 09:00-16:30 PM, 20:00-22:30 PM): 15 to 20 minute intervals
 * - 24 Buses (14 Aircon, 10 Ordinary) with 40 seats each (1A - 10D)
 * - Pairwise fare rules for all 8 transit stops in Northbound and Southbound directions
 * - Generates full rolling timetable for Today + next 7 days
 */

require_once __DIR__ . '/../backend/config/database.php';

try {
    $pdo = Database::getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=========================================================\n";
    echo "  BONHAGAN TRANSIT — SEEDING SCHEDULES & FLEET\n";
    echo "=========================================================\n";

    // 1. Bus Types
    $stmt = $pdo->query("SELECT id, type_name FROM public.bus_types");
    $busTypes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $busTypes[strtolower($row['type_name'])] = (int)$row['id'];
    }

    $airconTypeId = $busTypes['air-conditioned'] ?? $busTypes['aircon'] ?? 1;
    $ordinaryTypeId = $busTypes['ordinary'] ?? 2;

    echo "Bus Types: Air-conditioned (ID: {$airconTypeId}), Ordinary (ID: {$ordinaryTypeId})\n";

    // 2. Ensure Fleet (24 active buses: 14 Aircon, 10 Ordinary)
    $busesToSeed = [];
    for ($i = 1; $i <= 14; $i++) {
        $num = sprintf("%03d", 100 + $i);
        $busesToSeed[] = [
            'type_id' => $airconTypeId,
            'bus_number' => "BTH-{$num}",
            'plate_number' => "NDB-{$num}",
            'bus_name' => "Bonhagan Executive {$num}",
            'seating' => 40,
            'standing' => 10,
            'status' => 'active'
        ];
    }
    for ($i = 1; $i <= 10; $i++) {
        $num = sprintf("%03d", 200 + $i);
        $busesToSeed[] = [
            'type_id' => $ordinaryTypeId,
            'bus_number' => "BTH-{$num}",
            'plate_number' => "NDB-{$num}",
            'bus_name' => "Bonhagan Express {$num}",
            'seating' => 40,
            'standing' => 15,
            'status' => 'active'
        ];
    }

    $busInsertStmt = $pdo->prepare("
        INSERT INTO public.buses (bus_type_id, bus_number, plate_number, bus_name, seating_capacity, standing_capacity, status)
        VALUES (:type_id, :bus_number, :plate_number, :bus_name, :seating, :standing, :status)
        ON CONFLICT (bus_number) DO UPDATE SET
            bus_type_id = EXCLUDED.bus_type_id,
            plate_number = EXCLUDED.plate_number,
            bus_name = EXCLUDED.bus_name,
            status = 'active'
        RETURNING id, bus_number, bus_type_id
    ");

    $allBuses = [];
    foreach ($busesToSeed as $b) {
        $busInsertStmt->execute([
            ':type_id' => $b['type_id'],
            ':bus_number' => $b['bus_number'],
            ':plate_number' => $b['plate_number'],
            ':bus_name' => $b['bus_name'],
            ':seating' => $b['seating'],
            ':standing' => $b['standing'],
            ':status' => $b['status']
        ]);
        $res = $busInsertStmt->fetch(PDO::FETCH_ASSOC);
        $allBuses[] = $res;
    }
    echo "Fleet: Registered and verified " . count($allBuses) . " modern buses.\n";

    // 3. Ensure 40 Seats (Rows 1-10, Columns A-D) for each bus
    $seatInsertStmt = $pdo->prepare("
        INSERT INTO public.bus_seats (bus_id, seat_number, seat_row, seat_column, seat_type, is_active)
        VALUES (:bus_id, :seat_num, :row, :col, 'regular', TRUE)
        ON CONFLICT (bus_id, seat_number) DO NOTHING
    ");

    $totalSeatsAdded = 0;
    foreach ($allBuses as $b) {
        $busId = (int)$b['id'];
        for ($r = 1; $r <= 10; $r++) {
            foreach (['A', 'B', 'C', 'D'] as $c) {
                $seatInsertStmt->execute([
                    ':bus_id' => $busId,
                    ':seat_num' => "{$r}{$c}",
                    ':row' => $r,
                    ':col' => ord($c) - 64
                ]);
                $totalSeatsAdded += $seatInsertStmt->rowCount();
            }
        }
    }
    echo "Bus Seats: Verified complete 40-seat layout for entire fleet (Added: {$totalSeatsAdded} seats).\n";

    // 4. Routes and Route Stops
    $routeStmt = $pdo->query("SELECT id, route_code, direction FROM public.routes WHERE route_code IN ('MNL-BUL-NB', 'BUL-MNL-SB')");
    $routes = [];
    while ($r = $routeStmt->fetch(PDO::FETCH_ASSOC)) {
        $routes[$r['direction']] = (int)$r['id'];
    }
    $nbRouteId = $routes['northbound'] ?? 1;
    $sbRouteId = $routes['southbound'] ?? 2;

    echo "Routes: Northbound Route ID = {$nbRouteId}, Southbound Route ID = {$sbRouteId}\n";

    // 5. Ensure Fare Matrices and Pairwise Rules for both routes and both bus types
    $fareMatrixConfig = [
        ['route_id' => $nbRouteId, 'bus_type_id' => $airconTypeId, 'base' => 15.00, 'per_km' => 2.50],
        ['route_id' => $nbRouteId, 'bus_type_id' => $ordinaryTypeId, 'base' => 13.00, 'per_km' => 2.10],
        ['route_id' => $sbRouteId, 'bus_type_id' => $airconTypeId, 'base' => 15.00, 'per_km' => 2.50],
        ['route_id' => $sbRouteId, 'bus_type_id' => $ordinaryTypeId, 'base' => 13.00, 'per_km' => 2.10]
    ];

    $fmInsertStmt = $pdo->prepare("
        INSERT INTO public.fare_matrices (route_id, bus_type_id, effective_from, base_rate, per_km_rate, is_active)
        VALUES (:route_id, :bus_type_id, CURRENT_DATE - INTERVAL '60 days', :base, :per_km, TRUE)
        ON CONFLICT DO NOTHING
        RETURNING id
    ");

    $fmGetStmt = $pdo->prepare("
        SELECT id FROM public.fare_matrices 
        WHERE route_id = :route_id AND bus_type_id = :bus_type_id AND is_active = TRUE 
        ORDER BY id DESC LIMIT 1
    ");

    $fareRuleInsertStmt = $pdo->prepare("
        INSERT INTO public.fare_rules (fare_matrix_id, origin_route_stop_id, destination_route_stop_id, fare_amount)
        VALUES (:matrix_id, :orig_id, :dest_id, :amount)
        ON CONFLICT (fare_matrix_id, origin_route_stop_id, destination_route_stop_id) 
        DO UPDATE SET fare_amount = EXCLUDED.fare_amount
    ");

    foreach ($fareMatrixConfig as $fmc) {
        $fmGetStmt->execute([':route_id' => $fmc['route_id'], ':bus_type_id' => $fmc['bus_type_id']]);
        $existingFm = $fmGetStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingFm) {
            $matrixId = (int)$existingFm['id'];
        } else {
            $fmInsertStmt->execute([
                ':route_id' => $fmc['route_id'],
                ':bus_type_id' => $fmc['bus_type_id'],
                ':base' => $fmc['base'],
                ':per_km' => $fmc['per_km']
            ]);
            $matrixId = (int)$fmInsertStmt->fetchColumn();
        }

        // Fetch route stops in order
        $rsStmt = $pdo->prepare("SELECT id, sequence_number, distance_from_origin FROM public.route_stops WHERE route_id = :route_id ORDER BY sequence_number ASC");
        $rsStmt->execute([':route_id' => $fmc['route_id']]);
        $routeStops = $rsStmt->fetchAll(PDO::FETCH_ASSOC);

        $rulesPopulated = 0;
        for ($i = 0; $i < count($routeStops); $i++) {
            for ($j = $i + 1; $j < count($routeStops); $j++) {
                $orig = $routeStops[$i];
                $dest = $routeStops[$j];
                $dist = abs((float)$dest['distance_from_origin'] - (float)$orig['distance_from_origin']);
                $calculatedFare = round((float)$fmc['base'] + ($dist * (float)$fmc['per_km']), 2);

                $fareRuleInsertStmt->execute([
                    ':matrix_id' => $matrixId,
                    ':orig_id' => (int)$orig['id'],
                    ':dest_id' => (int)$dest['id'],
                    ':amount' => $calculatedFare
                ]);
                $rulesPopulated++;
            }
        }
        echo "Fare Matrix #{$matrixId} (Route {$fmc['route_id']}, BusType {$fmc['bus_type_id']}): Verified {$rulesPopulated} pairwise fare rules.\n";
    }

    // 6. Generate High-Frequency Schedule Grid
    // Rules:
    // - 04:30 - 06:00: Early Morning Non-rush (15 min interval)
    // - 06:00 - 09:00: Morning Rush Hour (5 to 8 min interval)
    // - 09:00 - 16:30: Midday Non-rush (15 min interval)
    // - 16:30 - 20:00: Evening Rush Hour (5 to 8 min interval)
    // - 20:00 - 22:30: Late Night Non-rush (15 to 20 min interval)
    echo "\nGenerating Dynamic Dispatch Timetable...\n";

    function generateDailyTimetable(): array {
        $departures = [];
        // Helper to convert time to minutes from midnight
        $toMins = function($h, $m) { return $h * 60 + $m; };
        $rushIntervalsMorning = [6, 7, 5, 8, 7, 6, 5, 8, 7, 6];
        $rushIntervalsEvening = [5, 7, 6, 8, 5, 7, 6, 8, 6, 7];
        
        $mIdx = 0;
        $eIdx = 0;
        
        $currentMin = $toMins(4, 30); // 04:30 AM
        $endMin = $toMins(22, 30);     // 10:30 PM

        while ($currentMin <= $endMin) {
            $h = intdiv($currentMin, 60);
            $m = $currentMin % 60;
            $timeStr = sprintf("%02d:%02d:00", $h, $m);

            $isRush = false;
            $step = 15;

            if ($currentMin >= $toMins(6, 0) && $currentMin < $toMins(9, 0)) {
                // Morning peak
                $isRush = true;
                $step = $rushIntervalsMorning[$mIdx % count($rushIntervalsMorning)];
                $mIdx++;
            } elseif ($currentMin >= $toMins(16, 30) && $currentMin < $toMins(20, 0)) {
                // Evening peak
                $isRush = true;
                $step = $rushIntervalsEvening[$eIdx % count($rushIntervalsEvening)];
                $eIdx++;
            } elseif ($currentMin >= $toMins(20, 0)) {
                // Late night
                $step = 18;
            } else {
                // Early morning or Midday regular
                $step = 15;
            }

            $departures[] = [
                'time' => $timeStr,
                'is_rush' => $isRush,
                'step_min' => $step
            ];

            $currentMin += $step;
        }

        return $departures;
    }

    $dailyTimetable = generateDailyTimetable();
    echo "Daily Profile: " . count($dailyTimetable) . " departures per route direction per day.\n";

    // Prepare Trip Insert Statement
    $tripInsertStmt = $pdo->prepare("
        INSERT INTO public.trips (trip_code, route_id, bus_id, departure_datetime, estimated_arrival, status, standing_enabled, standing_capacity_override)
        VALUES (:trip_code, :route_id, :bus_id, :departure, :arrival, :status, :standing_enabled, :standing_override)
        ON CONFLICT (trip_code) DO UPDATE SET
            departure_datetime = EXCLUDED.departure_datetime,
            estimated_arrival = EXCLUDED.estimated_arrival,
            status = EXCLUDED.status
    ");

    $today = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $totalTripsSeeded = 0;
    $busCounter = 0;

    // Seed 9 days: Yesterday (-1 / 2026-11-01) + Today + 7 days forward
    for ($dayOffset = -1; $dayOffset <= 7; $dayOffset++) {
        $curDate = clone $today;
        if ($dayOffset < 0) {
            $curDate->modify("{$dayOffset} days");
        } elseif ($dayOffset > 0) {
            $curDate->modify("+{$dayOffset} days");
        }
        $dateStr = $curDate->format('Y-m-d');
        $codeDateStr = $curDate->format('Ymd');

        $nbIndex = 1;
        $sbIndex = 1;

        foreach ($dailyTimetable as $slot) {
            // Northbound Trip
            $nbDep = "{$dateStr} {$slot['time']}";
            $nbDepDt = new DateTime($nbDep, new DateTimeZone('Asia/Manila'));
            $nbArrDt = clone $nbDepDt;
            $nbArrDt->modify('+110 minutes');

            $nbBus = $allBuses[$busCounter % count($allBuses)];
            $busCounter++;

            $nbCode = "TRIP-NB-{$codeDateStr}-" . sprintf("%03d", $nbIndex++);
            $tripInsertStmt->execute([
                ':trip_code' => $nbCode,
                ':route_id' => $nbRouteId,
                ':bus_id' => (int)$nbBus['id'],
                ':departure' => $nbDepDt->format('Y-m-d H:i:s'),
                ':arrival' => $nbArrDt->format('Y-m-d H:i:s'),
                ':status' => 'scheduled',
                ':standing_enabled' => ($nbBus['bus_type_id'] == $ordinaryTypeId) ? 1 : 0,
                ':standing_override' => ($nbBus['bus_type_id'] == $ordinaryTypeId) ? 15 : null
            ]);
            $totalTripsSeeded++;

            // Southbound Trip (offset by 5 mins to balance terminal throughput)
            $sbDepDt = clone $nbDepDt;
            $sbDepDt->modify('+5 minutes');
            $sbArrDt = clone $sbDepDt;
            $sbArrDt->modify('+110 minutes');

            $sbBus = $allBuses[$busCounter % count($allBuses)];
            $busCounter++;

            $sbCode = "TRIP-SB-{$codeDateStr}-" . sprintf("%03d", $sbIndex++);
            $tripInsertStmt->execute([
                ':trip_code' => $sbCode,
                ':route_id' => $sbRouteId,
                ':bus_id' => (int)$sbBus['id'],
                ':departure' => $sbDepDt->format('Y-m-d H:i:s'),
                ':arrival' => $sbArrDt->format('Y-m-d H:i:s'),
                ':status' => 'scheduled',
                ':standing_enabled' => ($sbBus['bus_type_id'] == $ordinaryTypeId) ? 1 : 0,
                ':standing_override' => ($sbBus['bus_type_id'] == $ordinaryTypeId) ? 15 : null
            ]);
            $totalTripsSeeded++;
        }

        echo "  [Day +{$dayOffset} / {$dateStr}]: Seeded " . ($nbIndex + $sbIndex - 2) . " scheduled trips (NB & SB).\n";
    }

    echo "\n=========================================================\n";
    echo "  SEEDING COMPLETE!\n";
    echo "  Total Trips Generated: {$totalTripsSeeded}\n";
    echo "  Fleet Count: " . count($allBuses) . " buses\n";
    echo "  Rush Hour Interval: 5 to 8 minutes\n";
    echo "  Non-Rush Hour Interval: 15 to 18 minutes\n";
    echo "=========================================================\n";

} catch (Exception $e) {
    echo "\n[ERROR] Seeding failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

