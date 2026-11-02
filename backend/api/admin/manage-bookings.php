<?php
/**
 * BONHAGAN TRANSIT — Admin Manage Bookings API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/admin-middleware.php';

$admin = requireAdmin();
$pdo = Database::getConnection();

$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$tripId = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $sql = "
        SELECT b.id, b.booking_reference, b.booking_status, b.total_amount,
               b.payment_deadline, b.booking_date, b.created_at,
               u.full_name AS passenger_name, u.email AS passenger_email, u.mobile_number,
               t.trip_code, t.departure_datetime,
               r.route_name, r.direction,
               bus.bus_number,
               pay.payment_method, pay.payment_status,
               (SELECT COUNT(*) FROM public.booking_passengers bp WHERE bp.booking_id = b.id) AS passenger_count
        FROM public.bookings b
        JOIN public.profiles u ON u.id = b.user_id
        JOIN public.trips t ON t.id = b.trip_id
        JOIN public.routes r ON r.id = t.route_id
        JOIN public.buses bus ON bus.id = t.bus_id
        LEFT JOIN public.payments pay ON pay.booking_id = b.id
        WHERE 1=1
    ";

    $params = [];
    if (!empty($status)) {
        $sql .= " AND b.booking_status = :status ";
        $params[':status'] = $status;
    }
    if ($tripId > 0) {
        $sql .= " AND b.trip_id = :trip_id ";
        $params[':trip_id'] = $tripId;
    }
    if (!empty($search)) {
        $sql .= " AND (b.booking_reference ILIKE :search OR u.full_name ILIKE :search OR u.email ILIKE :search) ";
        $params[':search'] = "%{$search}%";
    }

    $sql .= " ORDER BY b.created_at DESC LIMIT 100 ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();

    sendSuccess('Bookings retrieved.', [
        'count' => count($bookings),
        'bookings' => $bookings
    ]);

} catch (Exception $e) {
    sendServerError('Failed to fetch bookings.');
}

