<?php
/**
 * BONHAGAN TRANSIT — Notification Service
 */

require_once dirname(__DIR__) . '/config/database.php';

class NotificationService {
    public static function create(
        string $userId,
        string $title,
        string $message,
        string $type = 'general'
    ): bool {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO public.notifications (user_id, title, message, notification_type, is_read, created_at)
                VALUES (:user_id, :title, :message, :type, FALSE, NOW())
            ");
            return $stmt->execute([
                ':user_id' => $userId,
                ':title' => $title,
                ':message' => $message,
                ':type' => $type
            ]);
        } catch (Exception $e) {
            error_log('[BONHAGAN NOTIFICATION ERROR] ' . $e->getMessage());
            return false;
        }
    }

    public static function notifyBookingConfirmed(string $userId, string $bookingRef, float $amount): void {
        self::create(
            $userId,
            "Booking Confirmed",
            "Your booking {$bookingRef} for ₱" . number_format($amount, 2) . " has been successfully confirmed. E-tickets are now ready.",
            "booking"
        );
    }

    public static function notifyPaymentReceived(string $userId, string $paymentRef, float $amount): void {
        self::create(
            $userId,
            "Payment Successful",
            "We received your payment of ₱" . number_format($amount, 2) . " (Ref: {$paymentRef}).",
            "payment"
        );
    }

    public static function notifyDiscountResult(string $userId, string $discountName, string $status, ?string $notes = null): void {
        $statusText = $status === 'approved' ? 'approved' : 'rejected';
        $msg = "Your {$discountName} discount request has been {$statusText}.";
        if ($notes) {
            $msg .= " Notes: {$notes}";
        }
        self::create($userId, "Discount Verification: " . ucfirst($statusText), $msg, "discount");
    }

    public static function notifyTripStatus(string $userId, string $tripCode, string $newStatus, ?string $remarks = null): void {
        $msg = "Trip {$tripCode} status updated to: " . strtoupper($newStatus) . ".";
        if ($remarks) {
            $msg .= " Details: {$remarks}";
        }
        self::create($userId, "Trip Update: {$tripCode}", $msg, "trip_delay");
    }
}

