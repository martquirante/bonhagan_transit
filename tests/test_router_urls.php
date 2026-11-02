<?php
/**
 * Automated Test Suite for All URL Routes, Redirects, and Static Assets
 */

$testRoutes = [
    // 1. Root & Public Clean URLs
    '/'                              => [200],
    '/home'                          => [200],
    '/routes'                        => [200],
    '/schedules'                     => [200],
    '/services'                      => [200],
    '/about'                         => [200],
    '/contact'                       => [200],
    '/login'                         => [200],
    '/register'                      => [200],
    
    // 2. Passenger Clean URLs
    '/book'                          => [200],
    '/book-trip'                     => [200],
    '/passenger'                     => [200],
    '/passenger/dashboard'           => [200],
    '/passenger/book'                => [200],
    '/passenger/book-trip'           => [200],
    '/passenger/seat-selection'      => [200],
    '/seat-selection'                => [200],
    '/passenger/passenger-details'   => [200],
    '/passenger-details'             => [200],
    '/passenger/checkout'            => [200],
    '/checkout'                      => [200],
    '/passenger/booking-confirmation'=> [200],
    '/booking-confirmation'          => [200],
    '/passenger/my-tickets'          => [200],
    '/my-tickets'                    => [200],
    '/passenger/ticket-details'      => [200],
    '/ticket-details'                => [200],
    '/passenger/booking-history'     => [200],
    '/booking-history'               => [200],
    '/passenger/profile'             => [200],
    '/profile'                       => [200],
    '/passenger/notifications'       => [200],
    '/notifications'                 => [200],
    '/passenger/trip-details'        => [200],
    '/trip-details'                  => [200],

    // 3. Admin Clean URLs
    '/admin'                         => [200],
    '/admin/dashboard'               => [200],
    '/admin/buses'                   => [200],
    '/admin/routes'                  => [200],
    '/admin/stops'                   => [200],
    '/admin/schedules'               => [200],
    '/admin/bookings'                => [200],
    '/admin/passengers'              => [200],
    '/admin/discounts'               => [200],
    '/admin/payments'                => [200],
    '/admin/staff'                   => [200],
    '/admin/reports'                 => [200],
    '/admin/settings'                => [200],

    // 4. Staff Clean URLs
    '/staff'                         => [200],
    '/staff/dashboard'               => [200],
    '/staff/assigned-trips'          => [200],
    '/staff/passenger-manifest'      => [200],
    '/staff/qr-scanner'              => [200],
    '/staff/incidents'               => [200],

    // 5. User-Reported Failing URL & .html aliases (must 301 redirect or 200 serve, NEVER 404)
    '/passenger/book-trip.html'      => [200, 301],
    '/book-trip.html'                => [200, 301],
    '/routes.html'                   => [200, 301],
    '/schedules.html'                => [200, 301],
    '/services.html'                 => [200, 301],
    '/about.html'                    => [200, 301],
    '/contact.html'                  => [200, 301],
    '/login.html'                    => [200, 301],
    '/register.html'                 => [200, 301],
    '/passenger/seat-selection.html' => [200, 301],
    '/passenger/passenger-details.html' => [200, 301],
    '/passenger/checkout.html'       => [200, 301],
    '/passenger/my-tickets.html'     => [200, 301],
    '/passenger/ticket-details.html' => [200, 301],
    '/passenger/booking-history.html'=> [200, 301],
    '/passenger/profile.html'        => [200, 301],
    '/passenger/dashboard.html'      => [200, 301],
    '/admin/dashboard.html'          => [200, 301],
    '/admin/buses.html'              => [200, 301],
    '/staff/dashboard.html'          => [200, 301],
    '/staff/qr-scanner.html'         => [200, 301],

    // 6. Legacy paths
    '/frontend'                      => [200, 301],
    '/frontend/index.html'           => [200, 301],
    '/index.html'                    => [200, 301],
    '/pages/public/routes.html'      => [200, 301],
    '/frontend/pages/public/routes.html' => [200, 301],
    '/pages/passenger/book-trip.html'=> [200, 301],
    '/frontend/pages/passenger/book-trip.html' => [200, 301],

    // 7. Static Assets
    '/css/main.css'                  => [200],
    '/js/config/api-config.js'       => [200],
    '/js/core/router.js'             => [200],
    '/assets/images/logo/bonhagan-logo.png' => [200],
    '/assets/images/buses/aircon-bus-cutout.png' => [200],
];

echo "=========================================================\n";
echo "  BONHAGAN TRANSIT — ROUTE & URL HEALTH AUDIT\n";
echo "=========================================================\n\n";

$passCount = 0;
$failCount = 0;
$failingList = [];

foreach ($testRoutes as $path => $expectedCodes) {
    $url = "http://localhost:8000" . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);

    if (in_array($httpCode, $expectedCodes, true)) {
        $detail = $httpCode === 301 ? " -> $redirectUrl" : "";
        echo " [PASS] [$httpCode] $path$detail\n";
        $passCount++;
    } else {
        echo " [FAIL] [$httpCode] $path (expected " . implode('/', $expectedCodes) . ")\n";
        $failCount++;
        $failingList[] = "[$httpCode] $path";
    }
}

// Also test redirect resolution (follow location to ensure target returns 200)
echo "\n--- Testing 301 Follow-Through Resolution ---\n";
$followTests = [
    '/passenger/book-trip.html',
    '/pages/public/routes.html',
    '/frontend/pages/passenger/book-trip.html',
    '/login.html',
    '/admin/buses.html'
];

foreach ($followTests as $p) {
    $ch = curl_init("http://localhost:8000" . $p);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_exec($ch);
    $finalCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    if ($finalCode === 200) {
        echo " [PASS] Follow $p => $effectiveUrl [200 OK]\n";
        $passCount++;
    } else {
        echo " [FAIL] Follow $p => $effectiveUrl [$finalCode]\n";
        $failCount++;
        $failingList[] = "Follow $p => [$finalCode]";
    }
}

echo "\n=========================================================\n";
echo "  SUMMARY: $passCount PASSED, $failCount FAILED\n";
echo "=========================================================\n";

if ($failCount > 0) {
    exit(1);
}
