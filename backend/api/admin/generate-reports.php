<?php
/**
 * BONHAGAN TRANSIT — Admin Reports & Analytics API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/admin-middleware.php';

$admin = requireAdmin();
$type = isset($_GET['type']) ? trim($_GET['type']) : 'revenue';
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');

$pdo = Database::getConnection();

try {
    if ($type === 'revenue') {
        $stmtTotal = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total_revenue, COUNT(*) AS paid_count
            FROM public.payments
            WHERE payment_status = 'paid'
              AND DATE(paid_at) >= :s_date AND DATE(paid_at) <= :e_date
        ");
        $stmtTotal->execute([':s_date' => $startDate, ':e_date' => $endDate]);
        $totals = $stmtTotal->fetch();

        // Breakdown by method
        $stmtMethods = $pdo->prepare("
            SELECT payment_method, COUNT(*) AS count, SUM(amount) AS total
            FROM public.payments
            WHERE payment_status = 'paid'
              AND DATE(paid_at) >= :s_date AND DATE(paid_at) <= :e_date
            GROUP BY payment_method
        ");
        $stmtMethods->execute([':s_date' => $startDate, ':e_date' => $endDate]);

        // Daily trend
        $stmtDaily = $pdo->prepare("
            SELECT DATE(paid_at) AS date, COUNT(*) AS transactions, SUM(amount) AS daily_revenue
            FROM public.payments
            WHERE payment_status = 'paid'
              AND DATE(paid_at) >= :s_date AND DATE(paid_at) <= :e_date
            GROUP BY DATE(paid_at)
            ORDER BY DATE(paid_at) ASC
        ");
        $stmtDaily->execute([':s_date' => $startDate, ':e_date' => $endDate]);

        sendSuccess('Revenue report generated.', [
            'type' => 'revenue',
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => $totals,
            'methods' => $stmtMethods->fetchAll(),
            'daily_trend' => $stmtDaily->fetchAll()
        ]);

    } else if ($type === 'trips') {
        $stmtTrips = $pdo->prepare("
            SELECT t.trip_code, t.departure_datetime, t.status,
                   r.route_name, b.bus_number,
                   (SELECT COUNT(*) FROM public.booking_passengers bp JOIN public.bookings bk ON bk.id = bp.booking_id WHERE bk.trip_id = t.id) AS booked_passengers,
                   (SELECT COUNT(*) FROM public.boarding_records br JOIN public.tickets tk ON tk.id = br.ticket_id JOIN public.booking_passengers bp2 ON bp2.id = tk.passenger_id JOIN public.bookings bk2 ON bk2.id = bp2.booking_id WHERE bk2.trip_id = t.id) AS boarded_passengers
            FROM public.trips t
            JOIN public.routes r ON r.id = t.route_id
            JOIN public.buses b ON b.id = t.bus_id
            WHERE DATE(t.departure_datetime) >= :s_date AND DATE(t.departure_datetime) <= :e_date
            ORDER BY t.departure_datetime DESC
        ");
        $stmtTrips->execute([':s_date' => $startDate, ':e_date' => $endDate]);

        sendSuccess('Trips performance report generated.', [
            'type' => 'trips',
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'trips' => $stmtTrips->fetchAll()
        ]);

    } else if ($type === 'audit') {
        $stmtAudit = $pdo->prepare("
            SELECT al.id, al.action, al.affected_table, al.record_id, al.details, al.ip_address, al.created_at,
                   p.full_name AS user_name, p.role AS user_role
            FROM public.audit_logs al
            LEFT JOIN public.profiles p ON p.id = al.user_id
            WHERE DATE(al.created_at) >= :s_date AND DATE(al.created_at) <= :e_date
            ORDER BY al.created_at DESC LIMIT 200
        ");
        $stmtAudit->execute([':s_date' => $startDate, ':e_date' => $endDate]);
        $logs = $stmtAudit->fetchAll();
        foreach ($logs as &$l) {
            if (is_string($l['details'])) {
                $l['details'] = json_decode($l['details'], true);
            }
        }

        sendSuccess('Audit log report generated.', [
            'type' => 'audit',
            'logs' => $logs
        ]);

    } else {
        sendError('Unknown report type. Supported: revenue, trips, audit.', [], 400);
    }

} catch (Exception $e) {
    sendServerError('Failed to generate report.');
}

