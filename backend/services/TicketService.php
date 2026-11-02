<?php
/**
 * BONHAGAN TRANSIT — Electronic Ticketing Service
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/QRCodeService.php';

class TicketService {
    /**
     * Issue tickets for all passengers in a confirmed booking
     */
    public static function issueTicketsForBooking(int $bookingId): array {
        $pdo = Database::getConnection();

        // Verify booking status
        $stmtBooking = $pdo->prepare("
            SELECT id, booking_reference, booking_status, user_id
            FROM public.bookings
            WHERE id = :id
        ");
        $stmtBooking->execute([':id' => $bookingId]);
        $booking = $stmtBooking->fetch();

        if (!$booking || $booking['booking_status'] !== 'confirmed') {
            throw new RuntimeException("Tickets can only be issued for confirmed bookings.");
        }

        // Fetch passengers
        $stmtPass = $pdo->prepare("
            SELECT id, passenger_name
            FROM public.booking_passengers
            WHERE booking_id = :booking_id
        ");
        $stmtPass->execute([':booking_id' => $bookingId]);
        $passengers = $stmtPass->fetchAll();

        $issuedTickets = [];

        foreach ($passengers as $p) {
            // Check if ticket already exists
            $stmtCheck = $pdo->prepare("SELECT id, ticket_reference, qr_token, ticket_status FROM public.tickets WHERE passenger_id = :p_id");
            $stmtCheck->execute([':p_id' => $p['id']]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                // Update to valid if pending
                if ($existing['ticket_status'] === 'pending') {
                    $stmtUp = $pdo->prepare("UPDATE public.tickets SET ticket_status = 'valid', issued_at = NOW() WHERE id = :id");
                    $stmtUp->execute([':id' => $existing['id']]);
                    $existing['ticket_status'] = 'valid';
                }
                $issuedTickets[] = $existing;
                continue;
            }

            // Generate unique ticket reference: TKT-YYYYMMDD-XXXXXX
            $randomCode = strtoupper(bin2hex(random_bytes(3)));
            $ticketRef = 'TKT-' . date('Ymd') . '-' . $randomCode;
            $qrToken = QRCodeService::generateToken();

            $stmtIns = $pdo->prepare("
                INSERT INTO public.tickets (passenger_id, ticket_reference, qr_token, ticket_status, issued_at, created_at)
                VALUES (:passenger_id, :ref, :qr_token, 'valid', NOW(), NOW())
                RETURNING id, ticket_reference, qr_token, ticket_status, issued_at
            ");
            $stmtIns->execute([
                ':passenger_id' => $p['id'],
                ':ref' => $ticketRef,
                ':qr_token' => $qrToken
            ]);
            $issuedTickets[] = $stmtIns->fetch();
        }

        return $issuedTickets;
    }

    /**
     * Validate and record passenger boarding
     */
    public static function validateBoarding(string $qrTokenOrRef, string $staffUserId, string $method = 'qr', ?string $notes = null): array {
        $pdo = Database::getConnection();

        // 1. Fetch ticket and trip details
        $stmt = $pdo->prepare("
            SELECT t.id AS ticket_id, t.ticket_reference, t.ticket_status, t.qr_token, t.issued_at,
                   bp.passenger_name, bp.passenger_type, bp.final_fare,
                   b.booking_reference, b.trip_id,
                   tr.trip_code, tr.departure_datetime, tr.bus_id,
                   bus.bus_number, bus.bus_name,
                   r.route_name, r.direction,
                   rs_board.sequence_number AS board_seq, s_board.stop_name AS board_stop,
                   rs_drop.sequence_number AS drop_seq, s_drop.stop_name AS drop_stop,
                   pa.allocation_type, pa.seat_id, bs.seat_number
            FROM public.tickets t
            JOIN public.booking_passengers bp ON bp.id = t.passenger_id
            JOIN public.bookings b ON b.id = bp.booking_id
            JOIN public.trips tr ON tr.id = b.trip_id
            JOIN public.buses bus ON bus.id = tr.bus_id
            JOIN public.routes r ON r.id = tr.route_id
            JOIN public.route_stops rs_board ON rs_board.id = bp.boarding_route_stop_id
            JOIN public.stops s_board ON s_board.id = rs_board.stop_id
            JOIN public.route_stops rs_drop ON rs_drop.id = bp.dropoff_route_stop_id
            JOIN public.stops s_drop ON s_drop.id = rs_drop.stop_id
            LEFT JOIN public.passenger_allocations pa ON pa.passenger_id = bp.id
            LEFT JOIN public.bus_seats bs ON bs.id = pa.seat_id
            WHERE t.qr_token::text = :token OR t.ticket_reference = :token
            LIMIT 1
        ");
        $stmt->execute([':token' => $qrTokenOrRef]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            throw new InvalidArgumentException("Ticket not found. Invalid QR token or reference.");
        }

        // 2. Validate current status
        if ($ticket['ticket_status'] === 'boarded') {
            // Fetch previous boarding info
            $stmtPrev = $pdo->prepare("
                SELECT br.boarded_at, p.full_name AS verified_by_name
                FROM public.boarding_records br
                LEFT JOIN public.profiles p ON p.id = br.verified_by
                WHERE br.ticket_id = :ticket_id
                ORDER BY br.boarded_at DESC LIMIT 1
            ");
            $stmtPrev->execute([':ticket_id' => $ticket['ticket_id']]);
            $prev = $stmtPrev->fetch();
            $boardTime = $prev ? $prev['boarded_at'] : 'Unknown';
            $verifier = $prev ? $prev['verified_by_name'] : 'Staff';

            throw new RuntimeException("ALREADY BOARDED: Ticket was already scanned and used on {$boardTime} by {$verifier}. Duplicate boarding rejected.");
        }

        if ($ticket['ticket_status'] !== 'valid') {
            throw new RuntimeException("INVALID TICKET: Ticket status is '{$ticket['ticket_status']}'. Boarding not permitted.");
        }

        // 3. Record boarding inside transaction
        $pdo->beginTransaction();
        try {
            $stmtRec = $pdo->prepare("
                INSERT INTO public.boarding_records (ticket_id, verified_by, boarded_at, verification_method, notes)
                VALUES (:ticket_id, :verified_by, NOW(), :method, :notes)
            ");
            $stmtRec->execute([
                ':ticket_id' => $ticket['ticket_id'],
                ':verified_by' => $staffUserId,
                ':method' => in_array($method, ['qr', 'manual']) ? $method : 'qr',
                ':notes' => $notes
            ]);

            $stmtUp = $pdo->prepare("
                UPDATE public.tickets
                SET ticket_status = 'boarded'
                WHERE id = :id
            ");
            $stmtUp->execute([':id' => $ticket['ticket_id']]);

            $pdo->commit();

            $ticket['ticket_status'] = 'boarded';
            $ticket['boarded_at'] = date('Y-m-d H:i:s');
            return $ticket;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

