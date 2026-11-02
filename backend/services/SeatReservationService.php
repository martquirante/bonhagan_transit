<?php
/**
 * BONHAGAN TRANSIT — Seat & Standing Reservation Service
 * Implements segment-based allocation and concurrency locking.
 */

require_once dirname(__DIR__) . '/config/database.php';

class SeatReservationService {
    /**
     * Clean up expired seat holds
     */
    public static function cleanupExpiredHolds(): int {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE public.passenger_allocations
            SET allocation_status = 'expired'
            WHERE allocation_status = 'held'
              AND hold_expires_at IS NOT NULL
              AND hold_expires_at <= NOW()
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Get seat map and availability for a specific trip and travel segment
     *
     * @param int $tripId
     * @param int $boardingRouteStopId
     * @param int $dropoffRouteStopId
     * @return array
     */
    public static function getSeatMap(int $tripId, int $boardingRouteStopId, int $dropoffRouteStopId): array {
        self::cleanupExpiredHolds();
        $pdo = Database::getConnection();

        // 1. Get trip and bus information
        $stmtTrip = $pdo->prepare("
            SELECT t.id, t.bus_id, t.standing_enabled, t.standing_capacity_override,
                   b.bus_number, b.seating_capacity, b.standing_capacity
            FROM public.trips t
            JOIN public.buses b ON b.id = t.bus_id
            WHERE t.id = :trip_id
        ");
        $stmtTrip->execute([':trip_id' => $tripId]);
        $trip = $stmtTrip->fetch();

        if (!$trip) {
            throw new InvalidArgumentException("Trip not found.");
        }

        // 2. Get sequence numbers for requested segment
        $stmtSeq = $pdo->prepare("
            SELECT id, sequence_number FROM public.route_stops
            WHERE id IN (:b_id, :d_id)
        ");
        $stmtSeq->execute([':b_id' => $boardingRouteStopId, ':d_id' => $dropoffRouteStopId]);
        $seqMap = [];
        foreach ($stmtSeq->fetchAll() as $row) {
            $seqMap[$row['id']] = (int)$row['sequence_number'];
        }

        if (!isset($seqMap[$boardingRouteStopId]) || !isset($seqMap[$dropoffRouteStopId])) {
            throw new InvalidArgumentException("Invalid boarding or drop-off stops.");
        }

        $newStart = $seqMap[$boardingRouteStopId];
        $newEnd = $seqMap[$dropoffRouteStopId];

        if ($newStart >= $newEnd) {
            throw new InvalidArgumentException("Boarding stop must precede drop-off stop.");
        }

        // 3. Fetch all seats for the bus
        $stmtSeats = $pdo->prepare("
            SELECT id AS seat_id, seat_number, seat_row, seat_column, seat_type, is_active
            FROM public.bus_seats
            WHERE bus_id = :bus_id AND is_active = TRUE
            ORDER BY seat_row ASC, seat_column ASC, seat_number ASC
        ");
        $stmtSeats->execute([':bus_id' => $trip['bus_id']]);
        $seats = $stmtSeats->fetchAll();

        // 4. Find all currently occupied / held seats for overlapping segments
        // Overlap condition: GREATEST(rs_board.sequence_number, :new_start) < LEAST(rs_drop.sequence_number, :new_end)
        $stmtOccupied = $pdo->prepare("
            SELECT pa.seat_id, pa.allocation_status, pa.hold_expires_at,
                   rs_board.sequence_number AS board_seq,
                   rs_drop.sequence_number AS drop_seq
            FROM public.passenger_allocations pa
            JOIN public.booking_passengers bp ON bp.id = pa.passenger_id
            JOIN public.route_stops rs_board ON rs_board.id = bp.boarding_route_stop_id
            JOIN public.route_stops rs_drop ON rs_drop.id = bp.dropoff_route_stop_id
            WHERE pa.trip_id = :trip_id
              AND pa.allocation_type = 'seat'
              AND pa.allocation_status IN ('held', 'confirmed')
              AND (pa.allocation_status = 'confirmed' OR (pa.allocation_status = 'held' AND pa.hold_expires_at > NOW()))
              AND GREATEST(rs_board.sequence_number, :new_start) < LEAST(rs_drop.sequence_number, :new_end)
        ");
        $stmtOccupied->execute([
            ':trip_id' => $tripId,
            ':new_start' => $newStart,
            ':new_end' => $newEnd
        ]);
        $occupiedRows = $stmtOccupied->fetchAll();

        $occupiedSeatMap = [];
        foreach ($occupiedRows as $occ) {
            $occupiedSeatMap[$occ['seat_id']] = $occ['allocation_status'];
        }

        // 5. Build seat list with calculated status for this segment
        $seatList = [];
        $availableSeatsCount = 0;
        foreach ($seats as $s) {
            $status = 'available';
            if (isset($occupiedSeatMap[$s['seat_id']])) {
                $status = $occupiedSeatMap[$s['seat_id']] === 'confirmed' ? 'occupied' : 'held';
            } else {
                $availableSeatsCount++;
            }

            $seatList[] = [
                'seat_id' => (int)$s['seat_id'],
                'seat_number' => $s['seat_number'],
                'seat_row' => (int)$s['seat_row'],
                'seat_column' => (int)$s['seat_column'],
                'seat_type' => $s['seat_type'],
                'status' => $status
            ];
        }

        // 6. Standing capacity calculation for this segment
        $standingCapacity = $trip['standing_capacity_override'] !== null
            ? (int)$trip['standing_capacity_override']
            : (int)$trip['standing_capacity'];

        $standingEnabled = (bool)$trip['standing_enabled'] && $standingCapacity > 0;

        $stmtStanding = $pdo->prepare("
            SELECT COUNT(*) AS active_standing
            FROM public.passenger_allocations pa
            JOIN public.booking_passengers bp ON bp.id = pa.passenger_id
            JOIN public.route_stops rs_board ON rs_board.id = bp.boarding_route_stop_id
            JOIN public.route_stops rs_drop ON rs_drop.id = bp.dropoff_route_stop_id
            WHERE pa.trip_id = :trip_id
              AND pa.allocation_type = 'standing'
              AND pa.allocation_status IN ('held', 'confirmed')
              AND (pa.allocation_status = 'confirmed' OR (pa.allocation_status = 'held' AND pa.hold_expires_at > NOW()))
              AND GREATEST(rs_board.sequence_number, :new_start) < LEAST(rs_drop.sequence_number, :new_end)
        ");
        $stmtStanding->execute([
            ':trip_id' => $tripId,
            ':new_start' => $newStart,
            ':new_end' => $newEnd
        ]);
        $standingOccupied = (int)$stmtStanding->fetchColumn();
        $availableStanding = $standingEnabled ? max(0, $standingCapacity - $standingOccupied) : 0;

        return [
            'trip_id' => $tripId,
            'bus_number' => $trip['bus_number'],
            'seating_capacity' => (int)$trip['seating_capacity'],
            'available_seats_count' => $availableSeatsCount,
            'standing_enabled' => $standingEnabled,
            'standing_capacity' => $standingCapacity,
            'standing_occupied' => $standingOccupied,
            'available_standing_count' => $availableStanding,
            'seats' => $seatList
        ];
    }

    /**
     * Check if a specific seat is available for the segment
     */
    public static function isSeatAvailable(
        PDO $pdo,
        int $tripId,
        int $seatId,
        int $startSeq,
        int $endSeq
    ): bool {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM public.passenger_allocations pa
            JOIN public.booking_passengers bp ON bp.id = pa.passenger_id
            JOIN public.route_stops rs_board ON rs_board.id = bp.boarding_route_stop_id
            JOIN public.route_stops rs_drop ON rs_drop.id = bp.dropoff_route_stop_id
            WHERE pa.trip_id = :trip_id
              AND pa.seat_id = :seat_id
              AND pa.allocation_type = 'seat'
              AND pa.allocation_status IN ('held', 'confirmed')
              AND (pa.allocation_status = 'confirmed' OR (pa.allocation_status = 'held' AND pa.hold_expires_at > NOW()))
              AND GREATEST(rs_board.sequence_number, :start_seq) < LEAST(rs_drop.sequence_number, :end_seq)
            LIMIT 1
        ");
        $stmt->execute([
            ':trip_id' => $tripId,
            ':seat_id' => $seatId,
            ':start_seq' => $startSeq,
            ':end_seq' => $endSeq
        ]);
        return $stmt->fetch() === false;
    }
}
