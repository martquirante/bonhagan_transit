<?php
/**
 * BONHAGAN TRANSIT — Fare Calculation Service
 * Server-side calculation based on route stops, bus types, active matrices, and verified discounts.
 */

require_once dirname(__DIR__) . '/config/database.php';

class FareService {
    /**
     * Calculate fares for a passenger trip segment
     *
     * @param int $routeId
     * @param int $busTypeId
     * @param int $boardingStopId (route_stops.id)
     * @param int $dropoffStopId (route_stops.id)
     * @param string $passengerType ('regular', 'student', 'senior_citizen')
     * @return array ['base_fare' => float, 'discount_amount' => float, 'final_fare' => float, 'discount_percent' => float]
     */
    public static function calculate(
        int $routeId,
        int $busTypeId,
        int $boardingStopId,
        int $dropoffStopId,
        string $passengerType = 'regular'
    ): array {
        $pdo = Database::getConnection();

        // 1. Validate route stops and sequences
        $stmt = $pdo->prepare("
            SELECT id, sequence_number, distance_from_origin
            FROM public.route_stops
            WHERE id IN (:board_id, :drop_id) AND route_id = :route_id
        ");
        $stmt->execute([
            ':board_id' => $boardingStopId,
            ':drop_id' => $dropoffStopId,
            ':route_id' => $routeId
        ]);
        $stops = $stmt->fetchAll();

        $boardingStop = null;
        $dropoffStop = null;

        foreach ($stops as $s) {
            if ($s['id'] == $boardingStopId) {
                $boardingStop = $s;
            }
            if ($s['id'] == $dropoffStopId) {
                $dropoffStop = $s;
            }
        }

        if (!$boardingStop || !$dropoffStop) {
            throw new InvalidArgumentException("Selected boarding or drop-off stop does not belong to this route.");
        }

        if ($boardingStop['sequence_number'] >= $dropoffStop['sequence_number']) {
            throw new InvalidArgumentException("Boarding stop must occur before the drop-off stop.");
        }

        // 2. Check for explicit Fare Rule in active Fare Matrix
        $stmtRule = $pdo->prepare("
            SELECT fr.fare_amount
            FROM public.fare_rules fr
            JOIN public.fare_matrices fm ON fm.id = fr.fare_matrix_id
            WHERE fm.route_id = :route_id
              AND fm.bus_type_id = :bus_type_id
              AND fm.is_active = TRUE
              AND fr.origin_route_stop_id = :board_id
              AND fr.destination_route_stop_id = :drop_id
            ORDER BY fm.effective_from DESC
            LIMIT 1
        ");
        $stmtRule->execute([
            ':route_id' => $routeId,
            ':bus_type_id' => $busTypeId,
            ':board_id' => $boardingStopId,
            ':drop_id' => $dropoffStopId
        ]);
        $rule = $stmtRule->fetch();

        if ($rule) {
            $baseFare = (float)$rule['fare_amount'];
        } else {
            // 3. Fallback to matrix base_rate + per_km_rate
            $stmtMatrix = $pdo->prepare("
                SELECT base_rate, per_km_rate
                FROM public.fare_matrices
                WHERE route_id = :route_id
                  AND bus_type_id = :bus_type_id
                  AND is_active = TRUE
                ORDER BY effective_from DESC
                LIMIT 1
            ");
            $stmtMatrix->execute([
                ':route_id' => $routeId,
                ':bus_type_id' => $busTypeId
            ]);
            $matrix = $stmtMatrix->fetch();

            $baseRate = $matrix ? (float)$matrix['base_rate'] : 15.00;
            $perKmRate = $matrix ? (float)$matrix['per_km_rate'] : 2.50;

            $distance = abs((float)$dropoffStop['distance_from_origin'] - (float)$boardingStop['distance_from_origin']);
            $baseFare = round($baseRate + ($distance * $perKmRate), 2);
        }

        // 4. Determine discount percentage
        $discountPercent = 0.00;
        if (in_array($passengerType, ['student', 'senior_citizen'], true)) {
            $discountName = $passengerType === 'student' ? 'Student' : 'Senior Citizen';
            $stmtDisc = $pdo->prepare("
                SELECT discount_percentage
                FROM public.discount_types
                WHERE LOWER(discount_name) = LOWER(:name) AND is_active = TRUE
                LIMIT 1
            ");
            $stmtDisc->execute([':name' => $discountName]);
            $disc = $stmtDisc->fetch();
            if ($disc) {
                $discountPercent = (float)$disc['discount_percentage'];
            } else {
                $discountPercent = 20.00; // Baseline fallback
            }
        }

        $discountAmount = round(($baseFare * ($discountPercent / 100.0)), 2);
        $finalFare = max(0.00, round($baseFare - $discountAmount, 2));

        return [
            'base_fare' => $baseFare,
            'discount_amount' => $discountAmount,
            'final_fare' => $finalFare,
            'discount_percent' => $discountPercent
        ];
    }
}

