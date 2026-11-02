<?php
/**
 * BONHAGAN TRANSIT — Unified Local Development Server Router
 * 
 * Usage: php -S localhost:8000 router.php
 * 
 * Handles:
 * - Directs / to /frontend/index.html
 * - Clean URLs: /routes, /schedules, /services, /about, /contact, /login, /register
 * - Passenger Portal: /book-trip, /passenger/book-trip, /passenger/seat-selection, etc.
 * - Admin & Staff Portals: /admin/*, /staff/*
 * - Automatic 301 canonical redirects for .html and legacy /pages/* or /frontend/* paths
 * - Backend API routing (/api/*) to backend/public/index.php
 * - Static assets (/css/*, /js/*, /assets/*) with proper MIME types
 * - Zero 404s for any valid page or alias
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rawurldecode($uri);

// Normalize trailing slashes (except root)
if ($uri !== '/' && str_ends_with($uri, '/')) {
    $cleanUri = rtrim($uri, '/');
    $query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: $cleanUri$query", true, 301);
    exit;
}

// 1. API requests routed to backend public entry point
if (str_starts_with($uri, '/api/')) {
    $apiRel = substr($uri, 5);
    $apiTarget = __DIR__ . '/backend/api/' . $apiRel;
    if (file_exists($apiTarget) && !is_dir($apiTarget)) {
        require $apiTarget;
        exit;
    }
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require __DIR__ . '/backend/public/index.php';
    exit;
}

// 2. Storage uploads
if (str_starts_with($uri, '/storage/uploads/')) {
    $filePath = __DIR__ . '/backend' . $uri;
    if (file_exists($filePath) && !is_dir($filePath)) {
        serveFile($filePath);
        exit;
    }
}

// 3. Static asset files (CSS, JS, images, fonts, icons, JSON, etc.)
$assetExts = [
    'css', 'js', 'mjs', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg',
    'ico', 'woff', 'woff2', 'ttf', 'otf', 'json', 'pdf', 'mp4', 'map'
];
$uriExt = strtolower(pathinfo($uri, PATHINFO_EXTENSION));

if (in_array($uriExt, $assetExts, true)) {
    // Strip prefixes if asset was requested with relative directory offset
    $strippedUri = preg_replace('#^/(pages/(public|passenger|admin|staff)|passenger|admin|staff)#', '', $uri);
    
    $assetCandidates = [
        __DIR__ . '/frontend' . $uri,
        __DIR__ . $uri,
        __DIR__ . '/frontend' . $strippedUri,
        __DIR__ . $strippedUri,
    ];
    
    foreach ($assetCandidates as $cand) {
        if (file_exists($cand) && !is_dir($cand)) {
            serveFile($cand);
            exit;
        }
    }
}

// 4. Master Page Registry: Clean URL => Physical File Path
$canonicalPages = [
    // Public Web Pages
    '/'                              => __DIR__ . '/frontend/index.html',
    '/home'                          => __DIR__ . '/frontend/index.html',
    '/routes'                        => __DIR__ . '/frontend/pages/public/routes.html',
    '/schedules'                     => __DIR__ . '/frontend/pages/public/schedules.html',
    '/services'                      => __DIR__ . '/frontend/pages/public/services.html',
    '/about'                         => __DIR__ . '/frontend/pages/public/about.html',
    '/contact'                       => __DIR__ . '/frontend/pages/public/contact.html',
    '/login'                         => __DIR__ . '/frontend/pages/public/login.html',
    '/register'                      => __DIR__ . '/frontend/pages/public/register.html',

    // Passenger Booking Engine & Portal
    '/book'                          => __DIR__ . '/frontend/pages/passenger/book-trip.html',
    '/book-trip'                     => __DIR__ . '/frontend/pages/passenger/book-trip.html',
    '/passenger'                     => __DIR__ . '/frontend/pages/passenger/dashboard.html',
    '/passenger/dashboard'           => __DIR__ . '/frontend/pages/passenger/dashboard.html',
    '/passenger/book'                => __DIR__ . '/frontend/pages/passenger/book-trip.html',
    '/passenger/book-trip'           => __DIR__ . '/frontend/pages/passenger/book-trip.html',
    '/passenger/seat-selection'      => __DIR__ . '/frontend/pages/passenger/seat-selection.html',
    '/seat-selection'                => __DIR__ . '/frontend/pages/passenger/seat-selection.html',
    '/passenger/passenger-details'   => __DIR__ . '/frontend/pages/passenger/passenger-details.html',
    '/passenger-details'             => __DIR__ . '/frontend/pages/passenger/passenger-details.html',
    '/passenger/checkout'            => __DIR__ . '/frontend/pages/passenger/checkout.html',
    '/checkout'                      => __DIR__ . '/frontend/pages/passenger/checkout.html',
    '/passenger/booking-confirmation'=> __DIR__ . '/frontend/pages/passenger/booking-confirmation.html',
    '/passenger/confirmation'        => __DIR__ . '/frontend/pages/passenger/booking-confirmation.html',
    '/booking-confirmation'          => __DIR__ . '/frontend/pages/passenger/booking-confirmation.html',
    '/passenger/my-tickets'          => __DIR__ . '/frontend/pages/passenger/my-tickets.html',
    '/my-tickets'                    => __DIR__ . '/frontend/pages/passenger/my-tickets.html',
    '/passenger/ticket-details'      => __DIR__ . '/frontend/pages/passenger/ticket-details.html',
    '/ticket-details'                => __DIR__ . '/frontend/pages/passenger/ticket-details.html',
    '/passenger/booking-history'     => __DIR__ . '/frontend/pages/passenger/booking-history.html',
    '/booking-history'               => __DIR__ . '/frontend/pages/passenger/booking-history.html',
    '/passenger/notifications'       => __DIR__ . '/frontend/pages/passenger/notifications.html',
    '/notifications'                 => __DIR__ . '/frontend/pages/passenger/notifications.html',
    '/passenger/profile'             => __DIR__ . '/frontend/pages/passenger/profile.html',
    '/profile'                       => __DIR__ . '/frontend/pages/passenger/profile.html',
    '/passenger/trip-details'        => __DIR__ . '/frontend/pages/passenger/trip-details.html',
    '/trip-details'                  => __DIR__ . '/frontend/pages/passenger/trip-details.html',

    // Admin Portal
    '/admin'                         => __DIR__ . '/frontend/pages/admin/dashboard.html',
    '/admin/dashboard'               => __DIR__ . '/frontend/pages/admin/dashboard.html',
    '/admin/buses'                   => __DIR__ . '/frontend/pages/admin/buses.html',
    '/admin/routes'                  => __DIR__ . '/frontend/pages/admin/routes.html',
    '/admin/stops'                   => __DIR__ . '/frontend/pages/admin/stops.html',
    '/admin/schedules'               => __DIR__ . '/frontend/pages/admin/schedules.html',
    '/admin/bookings'                => __DIR__ . '/frontend/pages/admin/bookings.html',
    '/admin/passengers'              => __DIR__ . '/frontend/pages/admin/passengers.html',
    '/admin/discounts'               => __DIR__ . '/frontend/pages/admin/discounts.html',
    '/admin/payments'                => __DIR__ . '/frontend/pages/admin/payments.html',
    '/admin/staff'                   => __DIR__ . '/frontend/pages/admin/staff.html',
    '/admin/reports'                 => __DIR__ . '/frontend/pages/admin/reports.html',
    '/admin/settings'                => __DIR__ . '/frontend/pages/admin/settings.html',

    // Staff Portal
    '/staff'                         => __DIR__ . '/frontend/pages/staff/dashboard.html',
    '/staff/dashboard'               => __DIR__ . '/frontend/pages/staff/dashboard.html',
    '/staff/assigned-trips'          => __DIR__ . '/frontend/pages/staff/assigned-trips.html',
    '/staff/passenger-manifest'      => __DIR__ . '/frontend/pages/staff/passenger-manifest.html',
    '/staff/qr-scanner'              => __DIR__ . '/frontend/pages/staff/qr-scanner.html',
    '/staff/incidents'               => __DIR__ . '/frontend/pages/staff/incidents.html',
];

// Direct match for clean URLs
if (isset($canonicalPages[$uri])) {
    serveFile($canonicalPages[$uri]);
    exit;
}

// 5. Intelligent 301 Redirects for .html and Legacy Long Paths
// Remove .html extension
$withoutHtml = preg_replace('/\.html$/i', '', $uri);

// Try resolving $withoutHtml directly in $canonicalPages
if ($withoutHtml !== $uri && isset($canonicalPages[$withoutHtml])) {
    $query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: " . $withoutHtml . $query, true, 301);
    exit;
}

// Normalize legacy prefixes: /frontend, /pages/public, /pages/passenger, etc.
$normalized = $withoutHtml;
$normalized = preg_replace('#^/frontend#', '', $normalized);
$normalized = preg_replace('#^/pages/public#', '', $normalized);
$normalized = preg_replace('#^/pages/#', '/', $normalized);

if ($normalized === '' || $normalized === '/index') {
    $normalized = '/';
}

if (isset($canonicalPages[$normalized])) {
    $query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: " . $normalized . $query, true, 301);
    exit;
}

// Check if page exists under /passenger/, /admin/, or /staff/
$aliases = [
    '/passenger' . $normalized,
    '/admin' . $normalized,
    '/staff' . $normalized,
];

foreach ($aliases as $alias) {
    if (isset($canonicalPages[$alias])) {
        $query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
        header("Location: " . $alias . $query, true, 301);
        exit;
    }
}

// 6. Direct Physical HTML File Fallback (serves directly if found on disk)
$directCandidates = [
    __DIR__ . $uri,
    __DIR__ . '/frontend' . $uri,
    __DIR__ . '/frontend/pages' . $uri,
    __DIR__ . '/frontend/pages/public' . $uri,
    __DIR__ . '/frontend/pages/passenger' . $uri,
    __DIR__ . '/frontend/pages/admin' . $uri,
    __DIR__ . '/frontend/pages/staff' . $uri,
    // With .html
    __DIR__ . $uri . '.html',
    __DIR__ . '/frontend' . $uri . '.html',
    __DIR__ . '/frontend/pages' . $uri . '.html',
    __DIR__ . '/frontend/pages/public' . $uri . '.html',
    __DIR__ . '/frontend/pages/passenger' . $uri . '.html',
    __DIR__ . '/frontend/pages/admin' . $uri . '.html',
    __DIR__ . '/frontend/pages/staff' . $uri . '.html',
];

foreach ($directCandidates as $cand) {
    if (file_exists($cand) && !is_dir($cand)) {
        serveFile($cand);
        exit;
    }
}

// 7. Not Found (404) — Branded, Informative Fallback UI
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Station Not Found (404) — Bonhagan Transit</title>
    <link rel="icon" type="image/png" href="/assets/images/logo/bonhagan-logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/main.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0F172A;
            color: #FFFFFF;
            padding: 1.5rem;
            margin: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .error-card {
            background: rgba(30, 41, 59, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 24px;
            padding: 3rem 2.5rem;
            max-width: 520px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .error-code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 4rem;
            font-weight: 800;
            color: #FECC02;
            line-height: 1;
            margin-bottom: 1rem;
        }
        .error-title {
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0 0 0.75rem 0;
            color: #FFFFFF;
        }
        .error-desc {
            color: #94A3B8;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .error-actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .error-btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: #FECC02;
            color: #0F172A;
            font-weight: 700;
            padding: 0.85rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .error-btn-primary:hover {
            background: #E5B800;
            transform: translateY(-2px);
        }
        .error-btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: transparent;
            color: #CBD5E1;
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .error-btn-secondary:hover {
            color: #FFFFFF;
            border-color: #FECC02;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-code">404</div>
        <h1 class="error-title">Station Not Found</h1>
        <p class="error-desc">
            The stop or page you requested (<code><?= htmlspecialchars($uri) ?></code>) does not exist on Route 6.
        </p>
        <div class="error-actions">
            <a href="/" class="error-btn-primary">Return to Homepage</a>
            <a href="/book-trip" class="error-btn-secondary">Book a Trip</a>
            <a href="/routes" class="error-btn-secondary">View Corridor Stops & Fares</a>
        </div>
    </div>
</body>
</html>
<?php
exit;

function serveFile(string $path): void {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    
    $mimes = [
        'css'   => 'text/css; charset=UTF-8',
        'js'    => 'application/javascript; charset=UTF-8',
        'mjs'   => 'application/javascript; charset=UTF-8',
        'json'  => 'application/json; charset=UTF-8',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'webp'  => 'image/webp',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'html'  => 'text/html; charset=UTF-8',
        'htm'   => 'text/html; charset=UTF-8',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
        'pdf'   => 'application/pdf',
        'mp4'   => 'video/mp4',
    ];
    
    $mime = $mimes[$ext] ?? 'application/octet-stream';
    
    header("Content-Type: $mime");
    header("Content-Length: " . filesize($path));
    header("Cache-Control: no-cache, must-revalidate");
    readfile($path);
}
