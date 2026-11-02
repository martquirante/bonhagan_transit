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
    public static function createBooking(string $userId, int $tripId, array $passengersList): array {
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

            if (strtotime($trip['departure_datetime']) < time()) {
                throw new RuntimeException("Trip has already departed. Cannot book past departures.");
            }

            // 2. Fetch all route stops for this trip's route to validate sequences
            $stmtStops = $pdo->prepare("
                SELECT id, sequence_number FROM public.route_stops
                WHERE route_id = :route_id
            ");
            $stmtStops->execute([':route_id' => $trip['route_id']]);
            $routeStopSeq = [];
            foreach ($stmtStops->fetchAll() as $rs) {
                $routeStopSeq[$rs['id']] = (int)$rs['sequence_number'];
            }

            $bookingTotal = 0.00;
            $preparedPassengers = [];

            // 3. Validate each passenger, check segment sequences, calculate server-side fares
            foreach ($passengersList as $idx => $p) {
                $pName = trim((string)($p['passenger_name'] ?? ''));
                if (empty($pName)) {
                    throw new InvalidArgumentException("Passenger name is required for passenger #" . ($idx + 1));
                }

                $pType = strtolower(trim((string)($p['passenger_type'] ?? 'regular')));
                if (!in_array($pType, ['regular', 'student', 'senior_citizen'], true)) {
                    $pType = 'regular';
                }

                $boardStopId = (int)($p['boarding_stop_id'] ?? $p['boarding_route_stop_id'] ?? 0);
                $dropStopId = (int)($p['dropoff_stop_id'] ?? $p['dropoff_route_stop_id'] ?? 0);

                if (!isset($routeStopSeq[$boardStopId])) {
                    throw new InvalidArgumentException("Boarding stop for {$pName} does not exist on this route.");
                }
                if (!isset($routeStopSeq[$dropStopId])) {
                    throw new InvalidArgumentException("Drop-off stop for {$pName} does not exist on this route.");
                }

                $startSeq = $routeStopSeq[$boardStopId];
                $endSeq = $routeStopSeq[$dropStopId];

                if ($startSeq >= $endSeq) {
                    throw new InvalidArgumentException("Boarding stop must precede drop-off stop for passenger {$pName}.");
                }

                $allocType = strtolower(trim((string)($p['allocation_type'] ?? 'seat')));
                $seatId = !empty($p['seat_id']) ? (int)$p['seat_id'] : null;

                if ($allocType === 'seat') {
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
