<?php
/**
 * BONHAGAN TRANSIT — Environment & Session Configuration
 */

// Prevent multiple inclusions
if (defined('BONHAGAN_ENV_LOADED')) {
    return;
}
define('BONHAGAN_ENV_LOADED', true);

// 1. Locate and parse .env
$envPaths = [
    dirname(__DIR__) . '/.env',
    dirname(dirname(__DIR__)) . '/.env'
];

$envLoaded = false;
foreach ($envPaths as $path) {
    if (file_exists($path) && is_readable($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);
                // Strip optional surrounding quotes
                if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                    $val = substr($val, 1, -1);
                }
                if (!array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $val;
                    putenv("{$key}={$val}");
                }
            }
        }
        $envLoaded = true;
        break;
    }
}

/**
 * Get environment variable with fallback
 */
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed {
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        $val = getenv($key);
        return $val !== false ? $val : $default;
    }
}

// 2. Set Default Timezone
$tz = env('APP_TIMEZONE', 'Asia/Manila');
if (!empty($tz)) {
    date_default_timezone_set($tz);
}

// 3. Configure and Start Secure Session
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        $sessionName = env('SESSION_NAME', 'BONHAGAN_SESSION');
        session_name($sessionName);

        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

        session_set_cookie_params([
            'lifetime' => 86400 * 7, // 7 days
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    } else if (php_sapi_name() === 'cli') {
        @session_start();
    }
}
