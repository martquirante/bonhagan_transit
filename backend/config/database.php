<?php
/**
 * BONHAGAN TRANSIT — Neon PostgreSQL Database Connection
 * Uses PHP PDO with prepared statements and exception handling.
 */

require_once __DIR__ . '/environment.php';

class Database {
    private static ?PDO $connection = null;

    /**
     * Get singleton PDO connection instance
     *
     * @return PDO
     * @throws RuntimeException If database connection fails
     */
    public static function getConnection(): PDO {
        if (self::$connection !== null) {
            return self::$connection;
        }

        try {
            $databaseUrl = env('DATABASE_URL');

            if (!empty($databaseUrl)) {
                // Parse standard PostgreSQL URL
                $parts = parse_url($databaseUrl);
                $host = $parts['host'] ?? 'localhost';
                $port = $parts['port'] ?? 5432;
                $user = isset($parts['user']) ? urldecode($parts['user']) : '';
                $pass = isset($parts['pass']) ? urldecode($parts['pass']) : '';
                $dbname = isset($parts['path']) ? ltrim($parts['path'], '/') : '';

                // Extract query parameters like sslmode
                $sslmode = 'require';
                if (!empty($parts['query'])) {
                    parse_str($parts['query'], $query);
                    if (!empty($query['sslmode'])) {
                        $sslmode = $query['sslmode'];
                    }
                }
            } else {
                $host = env('DB_HOST', 'localhost');
                $port = env('DB_PORT', 5432);
                $dbname = env('DB_DATABASE', 'neondb');
                $user = env('DB_USERNAME', 'neondb_owner');
                $pass = env('DB_PASSWORD', '');
                $sslmode = env('DB_SSLMODE', 'require');
            }

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslmode}";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => true,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_TIMEOUT => 10,
            ];

            self::$connection = new PDO($dsn, $user, $pass, $options);
            return self::$connection;

        } catch (PDOException $e) {
            // Log securely to file; never expose database credentials or details to caller
            error_log('[BONHAGAN DB ERROR] ' . $e->getMessage());

            if (env('APP_DEBUG') === 'true' && php_sapi_name() === 'cli') {
                throw new RuntimeException("Database connection failed: " . $e->getMessage());
            }

            throw new RuntimeException("Database connection error. Please contact the administrator.");
        }
    }

    /**
     * Test connection health safely
     */
    public static function checkHealth(): array {
        try {
            $pdo = self::getConnection();
            $stmt = $pdo->query("SELECT version() AS version, current_database() AS db, NOW() AS time;");
            $data = $stmt->fetch();
            return [
                'status' => 'healthy',
                'connected' => true,
                'database' => $data['db'] ?? 'unknown',
                'server_time' => $data['time'] ?? 'unknown'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'connected' => false,
                'error' => 'Unable to connect to Neon PostgreSQL'
            ];
        }
    }
}
