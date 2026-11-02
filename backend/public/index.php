<?php
/**
 * BONHAGAN TRANSIT — Public Backend API Entry Point & Health Check
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/helpers/response.php';

$health = Database::checkHealth();

$apiRoutes = [
    'auth' => [
        'POST /api/auth/register.php' => 'Register new passenger account',
        'POST /api/auth/login.php' => 'Authenticate user and start session',
        'POST /api/auth/logout.php' => 'End current session',
        'GET  /api/auth/current-user.php' => 'Get authenticated user profile and CSRF token'
    ],
    'passengers' => [
        'GET  /api/passengers/get-profile.php' => 'Retrieve passenger profile',
        'POST /api/passengers/update-profile.php' => 'Update personal information',
        'GET  /api/passengers/get-bookings.php' => 'Retrieve passenger booking history'
    ],
    'trips' => [
        'GET  /api/trips/search-trips.php' => 'Search available trips with origin, destination, date, and fares',
        'GET  /api/trips/get-schedules.php' => 'Get published schedules',
        'GET  /api/trips/get-trip-details.php' => 'Get detailed trip info and route stop sequence'
    ],
    'seats' => [
        'GET  /api/seats/get-seats.php' => 'Get seat map and segment availability',
        'POST /api/seats/reserve-seat.php' => 'Validate single seat segment availability'
    ],
    'bookings' => [
        'POST /api/bookings/create-booking.php' => 'Create multi-passenger booking with seat/standing allocations',
        'GET  /api/bookings/get-booking.php' => 'Get booking details by ID or reference',
        'POST /api/bookings/cancel-booking.php' => 'Cancel pending booking and release seats',
        'GET  /api/bookings/check-availability.php' => 'Quick availability check'
    ],
    'discounts' => [
        'POST /api/discounts/submit-discount.php' => 'Submit Student or Senior Citizen document',
        'POST /api/discounts/verify-discount.php' => 'Admin verify/approve/reject discount'
    ],
    'payments' => [
        'POST /api/payments/create-payment.php' => 'Initialize terminal or online payment',
        'POST /api/payments/verify-payment.php' => 'Staff/Admin verify terminal payment or webhook confirmation',
        'GET  /api/payments/payment-status.php' => 'Check payment status'
    ],
    'tickets' => [
        'POST /api/tickets/generate-ticket.php' => 'Issue tickets for confirmed bookings',
        'GET  /api/tickets/get-tickets.php' => 'Retrieve electronic tickets and QR codes',
        'POST /api/tickets/validate-ticket.php' => 'Validate ticket and record passenger boarding'
    ],
    'staff' => [
        'GET  /api/staff/assigned-trips.php' => 'Get assigned trips for staff',
        'GET  /api/staff/passenger-manifest.php' => 'View passenger manifest for trip',
        'GET/POST /api/staff/record-boarding.php' => 'Record and view boarding logs',
        'POST /api/staff/report-incident.php' => 'Submit trip incident report'
    ],
    'admin' => [
        'GET  /api/admin/dashboard-stats.php' => 'Dashboard analytics & key metrics',
        'GET/POST/PUT /api/admin/manage-buses.php' => 'Manage buses, bus types, and seat layouts',
        'GET/POST/PUT /api/admin/manage-routes.php' => 'Manage Northbound/Southbound corridors',
        'GET/POST /api/admin/manage-stops.php' => 'Manage physical stops and route sequences',
        'GET/POST/PUT /api/admin/manage-trips.php' => 'Schedule trips, assign staff, configure standing capacity',
        'GET  /api/admin/manage-bookings.php' => 'View and filter all bookings',
        'GET/POST /api/admin/manage-fares.php' => 'Configure fare matrices and stop rules',
        'GET/PUT  /api/admin/manage-discounts.php' => 'Manage discount rates and pending reviews',
        'GET  /api/admin/manage-payments.php' => 'Monitor payments and transaction logs',
        'GET/POST/PUT /api/admin/manage-staff.php' => 'Manage staff accounts and operational roles',
        'GET  /api/admin/generate-reports.php' => 'Generate financial, trip, and audit reports'
    ],
    'notifications' => [
        'GET  /api/notifications/get-notifications.php' => 'Get user notifications',
        'POST /api/notifications/mark-as-read.php' => 'Mark notifications as read'
    ]
];

sendSuccess('BONHAGAN TRANSIT Backend API is running.', [
    'system' => 'Bonhagan Transit Backend System',
    'environment' => env('APP_ENV', 'development'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Manila'),
    'database' => $health,
    'documentation' => [
        'rubric' => 'IT305W – Advanced Web Applications',
        'endpoints' => $apiRoutes
    ]
]);

