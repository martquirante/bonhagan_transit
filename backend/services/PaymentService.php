<?php
/**
 * BONHAGAN TRANSIT — Payment Processing Service
 * Supports provider-independent online payment workflow and terminal payment handling.
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/TicketService.php';
require_once __DIR__ . '/NotificationService.php';
require_once dirname(__DIR__) . '/helpers/audit-logger.php';

class PaymentService {
    /**
     * Create initial payment record for a booking
     */
    public static function createPayment(
        int $bookingId,
        string $paymentMethod,
        ?string $provider = null,
        ?int $deadlineHours = 2
    ): array {
        $pdo = Database::getConnection();

        // 1. Fetch booking
        $stmtB = $pdo->prepare("
            SELECT b.id, b.booking_reference, b.total_amount, b.booking_status, b.user_id,
                   t.departure_datetime
            FROM public.bookings b
            JOIN public.trips t ON t.id = b.trip_id
            WHERE b.id = :id
        ");
        $stmtB->execute([':id' => $bookingId]);
        $booking = $stmtB->fetch();

        if (!$booking) {
            throw new InvalidArgumentException("Booking not found.");
        }

        if ($booking['booking_status'] === 'confirmed') {
            throw new RuntimeException("This booking is already confirmed and paid.");
        }

        if (in_array($booking['booking_status'], ['cancelled', 'expired'])) {
            throw new RuntimeException("Cannot process payment for a {$booking['booking_status']} booking.");
        }

        // Calculate deadline
        $departureTs = strtotime($booking['departure_datetime']);
        $terminalDeadlineTs = min(time() + (3600 * $deadlineHours), $departureTs - 1800); // 30 min before trip or deadline
        $expiresAt = date('Y-m-d H:i:s', max(time() + 900, $terminalDeadlineTs)); // At least 15 min

        $paymentRef = 'PAY-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO public.payments (
                    booking_id, payment_reference, payment_method, payment_provider,
                    amount, payment_status, expires_at, created_at, updated_at
                )
                VALUES (
                    :booking_id, :ref, :method, :provider,
                    :amount, 'pending', :expires_at, NOW(), NOW()
                )
                RETURNING id, payment_reference, payment_method, payment_provider, amount, payment_status, expires_at
            ");
            $stmt->execute([
                ':booking_id' => $bookingId,
                ':ref' => $paymentRef,
                ':method' => in_array($paymentMethod, ['online', 'terminal']) ? $paymentMethod : 'terminal',
                ':provider' => $provider,
                ':amount' => $booking['total_amount'],
                ':expires_at' => $expiresAt
            ]);
            $payment = $stmt->fetch();

            // Update booking payment deadline
            $stmtUpB = $pdo->prepare("UPDATE public.bookings SET payment_deadline = :deadline, updated_at = NOW() WHERE id = :id");
            $stmtUpB->execute([':deadline' => $expiresAt, ':id' => $bookingId]);

            // Log event
            $stmtLog = $pdo->prepare("
                INSERT INTO public.payment_logs (payment_id, event_type, event_details, created_at)
                VALUES (:payment_id, 'payment_initiated', :details, NOW())
            ");
            $stmtLog->execute([
                ':payment_id' => $payment['id'],
                ':details' => json_encode([
                    'method' => $paymentMethod,
                    'provider' => $provider,
                    'amount' => (float)$booking['total_amount'],
                    'expires_at' => $expiresAt
                ])
            ]);

            $pdo->commit();
            return $payment;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Mark payment as paid (by terminal staff verification or online webhook confirmation)
     */
    public static function confirmPayment(
        int $paymentId,
        ?string $verifierUserId = null,
        string $eventSource = 'terminal',
        ?array $extraDetails = null
    ): array {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT p.id, p.booking_id, p.payment_reference, p.amount, p.payment_status, p.payment_method,
                   b.booking_reference, b.user_id, b.trip_id
            FROM public.payments p
            JOIN public.bookings b ON b.id = p.booking_id
            WHERE p.id = :id
            FOR UPDATE
        ");
        $stmt->execute([':id' => $paymentId]);
        $payment = $stmt->fetch();

        if (!$payment) {
            throw new InvalidArgumentException("Payment record not found.");
        }

        if ($payment['payment_status'] === 'paid') {
            return $payment; // Already confirmed
        }

        $pdo->beginTransaction();
        try {
            // 1. Update payment to paid
            $stmtUpPay = $pdo->prepare("
                UPDATE public.payments
                SET payment_status = 'paid', paid_at = NOW(), updated_at = NOW()
                WHERE id = :id
            ");
            $stmtUpPay->execute([':id' => $paymentId]);

            // 2. Update booking to confirmed
            $stmtUpBk = $pdo->prepare("
                UPDATE public.bookings
                SET booking_status = 'confirmed', updated_at = NOW()
                WHERE id = :id
            ");
            $stmtUpBk->execute([':id' => $payment['booking_id']]);

            // 3. Confirm seat allocations
            $stmtUpAlloc = $pdo->prepare("
                UPDATE public.passenger_allocations pa
                SET allocation_status = 'confirmed', hold_expires_at = NULL
                FROM public.booking_passengers bp
                WHERE bp.id = pa.passenger_id AND bp.booking_id = :booking_id
            ");
            $stmtUpAlloc->execute([':booking_id' => $payment['booking_id']]);

            // 4. Log payment event
            $stmtLog = $pdo->prepare("
                INSERT INTO public.payment_logs (payment_id, event_type, event_details, created_at)
                VALUES (:payment_id, 'payment_confirmed', :details, NOW())
            ");
            $stmtLog->execute([
                ':payment_id' => $paymentId,
                ':details' => json_encode(array_merge([
                    'source' => $eventSource,
                    'verified_by' => $verifierUserId,
                    'confirmed_at' => date('Y-m-d H:i:s')
                ], $extraDetails ?? []))
            ]);

            // 5. Issue individual electronic tickets for each passenger
            TicketService::issueTicketsForBooking((int)$payment['booking_id']);

            // 6. Notify passenger
            NotificationService::notifyPaymentReceived(
                $payment['user_id'],
                $payment['payment_reference'],
                (float)$payment['amount']
            );
            NotificationService::notifyBookingConfirmed(
                $payment['user_id'],
                $payment['booking_reference'],
                (float)$payment['amount']
            );

            // 7. Audit log
            AuditLogger::log(
                'confirm_payment',
                'payments',
                (string)$paymentId,
                ['booking_id' => $payment['booking_id'], 'source' => $eventSource],
                $verifierUserId
            );

            $pdo->commit();

            $payment['payment_status'] = 'paid';
            $payment['paid_at'] = date('Y-m-d H:i:s');
            return $payment;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

