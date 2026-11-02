<?php
/**
 * BONHAGAN TRANSIT — Admin Dashboard Analytics API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/admin-middleware.php';

$admin = requireAdmin();

try {
    $pdo = Database::getConnection();

    // 1. Total revenue
    $stmtRev = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM public.payments WHERE payment_status = 'paid'");
    $totalRevenue = (float)$stmtRev->fetchColumn();

    // 2. Today's revenue
    $stmtTodayRev = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM public.payments WHERE payment_status = 'paid' AND DATE(paid_at) = CURRENT_DATE");
    $todayRevenue = (float)$stmtTodayRev->fetchColumn();

    // 3. Total bookings count
    $stmtBookings = $pdo->query("SELECT COUNT(*) FROM public.bookings");
    $totalBookings = (int)$stmtBookings->fetchColumn();

    // 4. Bookings by status
    $stmtBkStatus = $pdo->query("SELECT booking_status, COUNT(*) AS count FROM public.bookings GROUP BY booking_status");
    $bookingBreakdown = $stmtBkStatus->fetchAll();

    // 5. Active buses
    $stmtBuses = $pdo->query("SELECT COUNT(*) FROM public.buses WHERE status = 'active'");
    $activeBuses = (int)$stmtBuses->fetchColumn();

    // 6. Today's trips
    $stmtTrips = $pdo->query("SELECT COUNT(*) FROM public.trips WHERE DATE(departure_datetime) = CURRENT_DATE");
    $todayTrips = (int)$stmtTrips->fetchColumn();

    // 7. Pending discounts
    $stmtDisc = $pdo->query("SELECT COUNT(*) FROM public.discount_verifications WHERE verification_status = 'pending'");
    $pendingDiscounts = (int)$stmtDisc->fetchColumn();

    // 8. Total registered passengers
    $stmtPassengers = $pdo->query("SELECT COUNT(*) FROM public.profiles WHERE role = 'passenger'");
    $totalPassengers = (int)$stmtPassengers->fetchColumn();

    // 9. Recent bookings
    $stmtRecent = $pdo->query("
        SELECT b.id, b.booking_reference, b.booking_status, b.total_amount, b.created_at,
               p.full_name AS passenger_name, t.trip_code
        FROM public.bookings b
        JOIN public.profiles p ON p.id = b.user_id
        JOIN public.trips t ON t.id = b.trip_id
        ORDER BY b.created_at DESC LIMIT 5
    ");
    $recentBookings = $stmtRecent->fetchAll();

    sendSuccess('Dashboard analytics retrieved.', [
        'statistics' => [
            'total_revenue' => $totalRevenue,
            'today_revenue' => $todayRevenue,
            'total_bookings' => $totalBookings,
            'active_buses' => $activeBuses,
            'today_trips' => $todayTrips,
            'pending_discounts' => $pendingDiscounts,
            'total_passengers' => $totalPassengers,
            'booking_breakdown' => $bookingBreakdown
        ],
        'recent_bookings' => $recentBookings
    ]);

} catch (Exception $e) {
    sendServerError('Failed to fetch dashboard statistics.');
}

