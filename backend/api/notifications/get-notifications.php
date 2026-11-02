<?php
/**
 * BONHAGAN TRANSIT — Get User Notifications API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/auth-middleware.php';

$user = requireAuth();

try {
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare("
        SELECT id, title, message, notification_type, is_read, created_at
        FROM public.notifications
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([':user_id' => $user['id']]);
    $notifications = $stmt->fetchAll();

    $unreadCount = 0;
    foreach ($notifications as $n) {
        if (!$n['is_read']) {
            $unreadCount++;
        }
    }

    sendSuccess('Notifications retrieved.', [
        'unread_count' => $unreadCount,
        'notifications' => $notifications
    ]);

} catch (Exception $e) {
    sendServerError('Failed to fetch notifications.');
}

