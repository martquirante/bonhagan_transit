<?php
/**
 * BONHAGAN TRANSIT — Mark Notifications as Read API
 */

require_once dirname(dirname(__DIR__)) . '/middleware/auth-middleware.php';
require_once dirname(dirname(__DIR__)) . '/middleware/csrf-middleware.php';
require_once dirname(dirname(__DIR__)) . '/helpers/json-helper.php';

$user = requireAuth();
validateCsrfToken();

$input = getJsonInput(false);
$notificationId = isset($input['notification_id']) ? (int)$input['notification_id'] : 0;
$markAll = isset($input['mark_all']) && (bool)$input['mark_all'];

try {
    $pdo = Database::getConnection();

    if ($markAll) {
        $stmt = $pdo->prepare("UPDATE public.notifications SET is_read = TRUE WHERE user_id = :u_id AND is_read = FALSE");
        $stmt->execute([':u_id' => $user['id']]);
        sendSuccess('All notifications marked as read.');
    } else if ($notificationId > 0) {
        $stmt = $pdo->prepare("UPDATE public.notifications SET is_read = TRUE WHERE id = :id AND user_id = :u_id");
        $stmt->execute([':id' => $notificationId, ':u_id' => $user['id']]);
        sendSuccess('Notification marked as read.');
    } else {
        sendError('Please provide notification_id or set mark_all to true.', [], 400);
    }

} catch (Exception $e) {
    sendServerError('Failed to update notifications.');
}

