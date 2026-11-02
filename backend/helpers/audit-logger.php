<?php
/**
 * BONHAGAN TRANSIT — Audit Logger Helper
 */

require_once dirname(__DIR__) . '/config/database.php';

class AuditLogger {
    /**
     * Log a sensitive or important system action
     */
    public static function log(
        string $action,
        ?string $affectedTable = null,
        ?string $recordId = null,
        ?array $details = null,
        ?string $userId = null
    ): void {
        try {
            // Determine user ID from session if not provided
            if ($userId === null && !empty($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
            }

            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            // Validate IP for INET type in PostgreSQL
            if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
                $ipAddress = '127.0.0.1';
            }

            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO public.audit_logs (user_id, action, affected_table, record_id, details, ip_address)
                VALUES (:user_id, :action, :affected_table, :record_id, :details, :ip_address)
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':action' => $action,
                ':affected_table' => $affectedTable,
                ':record_id' => $recordId,
                ':details' => $details ? json_encode($details) : null,
                ':ip_address' => $ipAddress
            ]);

        } catch (Exception $e) {
            // Failsafe: Write to file so application is never stopped by audit log failure
            $logDir = dirname(__DIR__) . '/storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }
            $file = $logDir . '/audit_errors.log';
            @file_put_contents(
                $file,
                "[" . date('Y-m-d H:i:s') . "] Failed to write DB audit log: " . $e->getMessage() . PHP_EOL,
                FILE_APPEND
            );
        }
    }
}

