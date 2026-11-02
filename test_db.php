<?php

echo "=== Neon Database Connection Test ===" . PHP_EOL;

// Parse .env file
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    die("Error: .env file not found at $envPath" . PHP_EOL);
}

$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$env = [];
foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line) || str_starts_with($line, '#')) {
        continue;
    }
    if (strpos($line, '=') !== false) {
        list($key, $value) = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }
}

$host = $env['DB_HOST'] ?? '';
$port = $env['DB_PORT'] ?? '5432';
$dbname = $env['DB_DATABASE'] ?? '';
$username = $env['DB_USERNAME'] ?? '';
$password = $env['DB_PASSWORD'] ?? '';
$sslmode = $env['DB_SSLMODE'] ?? 'require';

echo "Host: " . $host . PHP_EOL;
echo "Database: " . $dbname . PHP_EOL;
echo "User: " . $username . PHP_EOL;
echo "Port: " . $port . PHP_EOL;
echo "Connecting to Neon via PHP PDO (pgsql)..." . PHP_EOL;

try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslmode}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10,
    ]);

    $stmt = $pdo->query("SELECT version() AS version, current_database() AS db, current_user AS user, NOW() AS server_time;");
    $result = $stmt->fetch();

    echo PHP_EOL . "STATUS: CONNECTION SUCCESSFUL!" . PHP_EOL;
    echo "Postgres Version : " . $result['version'] . PHP_EOL;
    echo "Current Database : " . $result['db'] . PHP_EOL;
    echo "Current User     : " . $result['user'] . PHP_EOL;
    echo "Server Time (UTC): " . $result['server_time'] . PHP_EOL;

    // Check existing tables if any
    $tablesStmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name;");
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

    echo PHP_EOL . "Public Tables in Database (" . count($tables) . "):" . PHP_EOL;
    if (count($tables) > 0) {
        foreach ($tables as $t) {
            echo " - " . $t . PHP_EOL;
        }
    } else {
        echo " (No tables created yet - clean database ready for schema/migrations)" . PHP_EOL;
    }

} catch (PDOException $e) {
    echo PHP_EOL . "STATUS: CONNECTION FAILED!" . PHP_EOL;
    echo "Error Message: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

