<?php
/**
 * BONHAGAN TRANSIT — Multi-Passenger Booking Service
 * Handles transactional reservation, segment validation, server-side fare calculation, and locking.
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/FareService.php';
require_once __DIR__ . '/SeatReservationService.php';
require_once __DIR__ . '/NotificationService.php';
require_once dirname(__DIR__) . '/helpers/audit-logger.php';

class BookingService {
    /**
     * Create a new booking with multiple passengers
     *
     * @param string $userId (UUID)
     * @param int $tripId
     * @param array $passengersList Array of passenger descriptors
     * @return array
     */
    public static function createBooking(string $userId, int $tripId, array $passengersList, array $context = []): array {
        if (empty($passengersList)) {
            throw new InvalidArgumentException("At least one passenger is required for a booking.");
        }

        $pdo = Database::getConnection();
        SeatReservationService::cleanupExpiredHolds();

        $pdo->beginTransaction();
        try {
            // 1. Lock and retrieve trip details
            $stmtTrip = $pdo->prepare("
                SELECT t.id, t.trip_code, t.route_id, t.bus_id, t.departure_datetime, t.status,
                       t.standing_enabled, t.standing_capacity_override,
                       b.bus_type_id, b.bus_number, b.standing_capacity
                FROM public.trips t
                JOIN public.buses b ON b.id = t.bus_id
                WHERE t.id = :trip_id
                FOR UPDATE OF t
            ");
            $stmtTrip->execute([':trip_id' => $tripId]);
            $trip = $stmtTrip->fetch();

            if (!$trip) {
                throw new InvalidArgumentException("Trip not found.");
            }

            if (!in_array($trip['status'], ['scheduled', 'boarding'], true)) {
                throw new RuntimeException("Trip is {$trip['status']}. Bookings are closed for this trip.");
            }

            // Allow active boarding trips, or scheduled departures within terminal grace window (45 mins)
            $depTs = strtotime($trip['departure_datetime']);
            if ($trip['status'] !== 'boarding' && $depTs < (time() - 2700)) {
                throw new RuntimeException("Trip has already departed. Cannot book past departures.");
            }

            // 2. Fetch all route stops for this trip's route to validate sequences and map identifiers
            $stmtStops = $pdo->prepare("
                SELECT rs.id, rs.stop_id, rs.sequence_number, rs.route_id, s.stop_name
                FROM public.route_stops rs
                JOIN public.stops s ON s.id = rs.stop_id
                WHERE rs.route_id = :route_id
                ORDER BY rs.sequence_number ASC
            ");
            $stmtStops->execute([':route_id' => $trip['route_id']]);
            $allRouteStops = $stmtStops->fetchAll();

            if (empty($allRouteStops)) {
                throw new RuntimeException("Route stops not configured for this trip corridor.");
            }

            $routeStopById = [];      // route_stops.id -> row
            $routeStopByStopId = [];  // stops.id -> row
            $routeStopBySeq = [];     // sequence_number -> row

            foreach ($allRouteStops as $rs) {
                $routeStopById[(int)$rs['id']] = $rs;
                $routeStopByStopId[(int)$rs['stop_id']] = $rs;
                $routeStopBySeq[(int)$rs['sequence_number']] = $rs;
            }

            $firstStop = $allRouteStops[0];
            $lastStop = $allRouteStops[count($allRouteStops) - 1];

            $resolveRouteStop = function($val, bool $isDropoff) use ($routeStopById, $routeStopByStopId, $routeStopBySeq, $firstStop, $lastStop) {
                if ($val === null || $val === '') {
                    return $isDropoff ? $lastStop : $firstStop;
                }
                $digits = preg_replace('/\D/', '', (string)$val);
                if ($digits === '') {
                    return $isDropoff ? $lastStop : $firstStop;
                }
                $num = (int)$digits;
                // 1. Direct route_stops.id
                if (isset($routeStopById[$num])) {
                    return $routeStopById[$num];
                }
                // 2. Stop ID
                if (isset($routeStopByStopId[$num])) {
                    return $routeStopByStopId[$num];
                }
                // 3. UI 1..8 dropdown indices (1 -> 7, 8 -> 14)
                $normalized = ($num >= 1 && $num <= 8) ? ($num + 6) : $num;
                if (isset($routeStopByStopId[$normalized])) {
                    return $routeStopByStopId[$normalized];
                }
                // 4. Sequence number
                if (isset($routeStopBySeq[$num])) {
                    return $routeStopBySeq[$num];
                }
                return $isDropoff ? $lastStop : $firstStop;
            };

            // Booking-level fallback stops
            $rootOrigin = $context['origin_stop_id'] ?? $context['boarding_route_stop_id'] ?? $context['boarding_stop_id'] ?? null;
            $rootDest = $context['destination_stop_id'] ?? $context['dropoff_route_stop_id'] ?? $context['dropoff_stop_id'] ?? null;
            $defaultBoardStop = $resolveRouteStop($rootOrigin, false);
            $defaultDropStop = $resolveRouteStop($rootDest, true);

            $bookingTotal = 0.00;
            $preparedPassengers = [];

            // 3. Validate each passenger, check segment sequences, calculate server-side fares
            foreach ($passengersList as $idx => $p) {
                $pName = trim((string)($p['passenger_name'] ?? $p['full_name'] ?? $p['name'] ?? ''));
                if (empty($pName)) {
                    $pName = "Passenger " . ($idx + 1);
                }

                $rawType = strtolower(trim((string)($p['passenger_type'] ?? $p['discount_type'] ?? 'regular')));
                if (in_array($rawType, ['student', 'senior', 'senior_citizen', 'pwd', 'pwd_discount'], true)) {
                    $pType = ($rawType === 'student') ? 'student' : 'senior_citizen';
                } else {
                    $pType = 'regular';
                }

                $pBoardVal = $p['boarding_route_stop_id'] ?? $p['boarding_stop_id'] ?? null;
                $boardStop = ($pBoardVal !== null && $pBoardVal !== '') ? $resolveRouteStop($pBoardVal, false) : $defaultBoardStop;

                $pDropVal = $p['dropoff_route_stop_id'] ?? $p['dropoff_stop_id'] ?? null;
                $dropStop = ($pDropVal !== null && $pDropVal !== '') ? $resolveRouteStop($pDropVal, true) : $defaultDropStop;

                $boardStopId = (int)$boardStop['id'];
                $dropStopId = (int)$dropStop['id'];
                $startSeq = (int)$boardStop['sequence_number'];
                $endSeq = (int)$dropStop['sequence_number'];

                if ($startSeq >= $endSeq) {
                    throw new InvalidArgumentException("Boarding stop must precede drop-off stop for passenger {$pName}.");
                }

                $allocType = strtolower(trim((string)($p['allocation_type'] ?? 'seat')));
                $seatId = !empty($p['seat_id']) ? (int)$p['seat_id'] : null;
                $seatNum = trim((string)($p['seat_number'] ?? ''));

                if ($allocType === 'seat') {
                    if (!$seatId && !empty($seatNum)) {
                        $stmtSeat = $pdo->prepare("SELECT id FROM public.bus_seats WHERE bus_id = :b_id AND seat_number = :num LIMIT 1");
                        $stmtSeat->execute([':b_id' => $trip['bus_id'], ':num' => $seatNum]);
                        $sId = $stmtSeat->fetchColumn();
                        if ($sId) {
                            $seatId = (int)$sId;
                        }
                    }

                    if (!$seatId) {
                        throw new InvalidArgumentException("Seat selection is required for passenger {$pName}.");
                    }

                    // Check seat availability on this specific segment
                    $isAvailable = SeatReservationService::isSeatAvailable($pdo, $tripId, $seatId, $startSeq, $endSeq);
                    if (!$isAvailable) {
                        throw new RuntimeException("Selected seat is no longer available for {$pName}'s segment.");
                    }
                } else if ($allocType === 'standing') {
                    // Check if standing capacity is enabled and available
                    $standingCapacity = $trip['standing_capacity_override'] !== null
                        ? (int)$trip['standing_capacity_override']
                        : (int)$trip['standing_capacity'];

                    if (!$trip['standing_enabled'] || $standingCapacity <= 0) {
                        throw new RuntimeException("Standing capacity is not authorized for this trip.");
                    }

                    // Count active standing allocations overlapping this segment
                    $stmtStandingCount = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM public.passenger_allocations pa
                        JOIN public.booking_passengers bp ON bp.id = pa.passenger_id
                        JOIN public.route_stops rs_b ON rs_b.id = bp.boarding_route_stop_id
                        JOIN public.route_stops rs_d ON rs_d.id = bp.dropoff_route_stop_id
                        WHERE pa.trip_id = :trip_id
                          AND pa.allocation_type = 'standing'
                          AND pa.allocation_status IN ('held', 'confirmed')
                          AND (pa.allocation_status = 'confirmed' OR (pa.allocation_status = 'held' AND pa.hold_expires_at > NOW()))
                          AND GREATEST(rs_b.sequence_number, :start_seq) < LEAST(rs_d.sequence_number, :end_seq)
                    ");
                    $stmtStandingCount->execute([
                        ':trip_id' => $tripId,
                        ':start_seq' => $startSeq,
                        ':end_seq' => $endSeq
                    ]);
                    $activeStanding = (int)$stmtStandingCount->fetchColumn();

                    if ($activeStanding >= $standingCapacity) {
                        throw new RuntimeException("Authorized standing capacity ({$standingCapacity}) is fully booked for passenger {$pName}'s segment.");
                    }
                } else {
                    throw new InvalidArgumentException("Invalid allocation type for {$pName}. Expected 'seat' or 'standing'.");
                }

                // Compute official server-side fare
                $fareDetails = FareService::calculate(
                    (int)$trip['route_id'],
                    (int)$trip['bus_type_id'],
                    $boardStopId,
                    $dropStopId,
                    $pType
                );

                $bookingTotal += $fareDetails['final_fare'];

                $preparedPassengers[] = [
                    'name' => $pName,
                    'type' => $pType,
                    'mobile' => trim((string)($p['mobile_number'] ?? '')),
                    'board_stop_id' => $boardStopId,
                    'drop_stop_id' => $dropStopId,
                    'start_seq' => $startSeq,
                    'end_seq' => $endSeq,
                    'alloc_type' => $allocType,
                    'seat_id' => $seatId,
                    'base_fare' => $fareDetails['base_fare'],
                    'discount_amount' => $fareDetails['discount_amount'],
                    'final_fare' => $fareDetails['final_fare'],
                    'discount_document' => $p['discount_document'] ?? null
                ];
            }

            // 4. Generate unique Booking Reference
            $bookingRef = 'BK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            // Temporary hold expires in 15 minutes
            $holdExpiresAt = date('Y-m-d H:i:s', time() + 900);

            // 5. Insert Booking
            $stmtInsBooking = $pdo->prepare("
                INSERT INTO public.bookings (
                    booking_reference, user_id, trip_id, booking_status,
                    total_amount, payment_deadline, booking_date, created_at, updated_at
                )
                VALUES (
                    :ref, :user_id, :trip_id, 'pending',
                    :total, :deadline, NOW(), NOW(), NOW()
                )
                RETURNING id, booking_reference, booking_status, total_amount, payment_deadline, booking_date
            ");
            $stmtInsBooking->execute([
                ':ref' => $bookingRef,
                ':user_id' => $userId,
                ':trip_id' => $tripId,
                ':total' => $bookingTotal,
                ':deadline' => $holdExpiresAt
            ]);
            $createdBooking = $stmtInsBooking->fetch();
            $bookingId = (int)$createdBooking['id'];

            // 6. Insert passengers and seat/standing allocations
            $insertedPassengers = [];

            foreach ($preparedPassengers as $prep) {
                $stmtInsPass = $pdo->prepare("
                    INSERT INTO public.booking_passengers (
                        booking_id, passenger_name, passenger_type, mobile_number,
                        boarding_route_stop_id, dropoff_route_stop_id,
                        base_fare, discount_amount, final_fare, created_at
                    )
                    VALUES (
                        :b_id, :p_name, :p_type, :mobile,
                        :b_stop, :d_stop,
                        :base_fare, :discount, :final_fare, NOW()
                    )
                    RETURNING id, passenger_name, passenger_type, base_fare, discount_amount, final_fare
                ");
                $stmtInsPass->execute([
                    ':b_id' => $bookingId,
                    ':p_name' => $prep['name'],
                    ':p_type' => $prep['type'],
                    ':mobile' => $prep['mobile'],
                    ':b_stop' => $prep['board_stop_id'],
                    ':d_stop' => $prep['drop_stop_id'],
                    ':base_fare' => $prep['base_fare'],
                    ':discount' => $prep['discount_amount'],
                    ':final_fare' => $prep['final_fare']
                ]);
                $passRecord = $stmtInsPass->fetch();
                $passengerId = (int)$passRecord['id'];

                // Insert passenger allocation
                $stmtInsAlloc = $pdo->prepare("
                    INSERT INTO public.passenger_allocations (
                        passenger_id, trip_id, allocation_type, seat_id,
                        allocation_status, hold_expires_at, created_at
                    )
                    VALUES (
                        :p_id, :t_id, :alloc_type, :seat_id,
                        'held', :expires, NOW()
                    )
                ");
                $stmtInsAlloc->execute([
                    ':p_id' => $passengerId,
                    ':t_id' => $tripId,
                    ':alloc_type' => $prep['alloc_type'],
                    ':seat_id' => $prep['seat_id'],
                    ':expires' => $holdExpiresAt
                ]);

                // Create pending ticket record
                $stmtTicket = $pdo->prepare("
                    INSERT INTO public.tickets (
                        passenger_id, ticket_reference, qr_token, ticket_status, created_at
                    )
                    VALUES (
                        :p_id, :ref, gen_random_uuid(), 'pending', NOW()
                    )
                ");
                $stmtTicket->execute([
                    ':p_id' => $passengerId,
                    ':ref' => 'TKT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)))
                ]);

                $insertedPassengers[] = array_merge($passRecord, [
                    'allocation_type' => $prep['alloc_type'],
                    'seat_id' => $prep['seat_id']
                ]);
            }

            // 7. Audit log
            AuditLogger::log(
                'create_booking',
                'bookings',
                (string)$bookingId,
                [
                    'reference' => $bookingRef,
                    'passenger_count' => count($preparedPassengers),
                    'total_amount' => $bookingTotal
                ],
                $userId
            );

            // 8. Notification
            NotificationService::create(
                $userId,
                "Booking Created ({$bookingRef})",
                "Your booking for " . count($preparedPassengers) . " passenger(s) totaling ₱" . number_format($bookingTotal, 2) . " has been created. Please complete payment before {$holdExpiresAt}.",
                "booking"
            );

            $pdo->commit();

            return [
                'booking' => $createdBooking,
                'passengers' => $insertedPassengers,
                'hold_expires_at' => $holdExpiresAt
            ];

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
