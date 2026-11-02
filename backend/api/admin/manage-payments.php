<?php
/**
 * BONHAGAN TRANSIT — Admin Manage & Monitor Payments API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/admin-middleware.php';

$admin = requireAdmin();
$pdo = Database::getConnection();

$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$methodFilter = isset($_GET['method']) ? trim($_GET['method']) : '';

try {
    $sql = "
        SELECT p.id, p.payment_reference, p.payment_method, p.payment_provider,
               p.amount, p.payment_status, p.paid_at, p.expires_at, p.created_at,
               b.booking_reference,
               u.full_name AS passenger_name, u.email AS passenger_email,
               (
                   SELECT json_agg(json_build_object(
                       'event_type', pl.event_type,
                       'event_details', pl.event_details,
                       'created_at', pl.created_at
                   ))
                   FROM public.payment_logs pl
                   WHERE pl.payment_id = p.id
               ) AS logs
        FROM public.payments p
        JOIN public.bookings b ON b.id = p.booking_id
        JOIN public.profiles u ON u.id = b.user_id
        WHERE 1=1
    ";

    $params = [];
    if (!empty($status)) {
        $sql .= " AND p.payment_status = :status ";
        $params[':status'] = $status;
    }
    if (!empty($methodFilter)) {
        $sql .= " AND p.payment_method = :method ";
        $params[':method'] = $methodFilter;
    }

    $sql .= " ORDER BY p.created_at DESC LIMIT 100 ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();

    foreach ($payments as &$p) {
        if (is_string($p['logs'])) {
            $p['logs'] = json_decode($p['logs'], true);
        }
    }

    sendSuccess('Payments retrieved.', [
        'count' => count($payments),
        'payments' => $payments
    ]);

} catch (Exception $e) {
    sendServerError('Failed to fetch payments.');
}

