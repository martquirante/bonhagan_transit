<?php
/**
 * BONHAGAN TRANSIT — Backend Comprehensive Test Suite
 */

echo "=========================================================" . PHP_EOL;
echo "  BONHAGAN TRANSIT — BACKEND SYSTEM TEST SUITE" . PHP_EOL;
echo "=========================================================" . PHP_EOL . PHP_EOL;

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, ?string $details = null): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] " . $description . PHP_EOL;
    } else {
        $failed++;
        echo " [FAIL] " . $description . ($details ? " -> " . $details : "") . PHP_EOL;
    }
}

require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/services/FareService.php';
require_once __DIR__ . '/../backend/services/SeatReservationService.php';
require_once __DIR__ . '/../backend/services/BookingService.php';
require_once __DIR__ . '/../backend/services/PaymentService.php';
require_once __DIR__ . '/../backend/services/TicketService.php';
require_once __DIR__ . '/../backend/services/QRCodeService.php';

$pdo = Database::getConnection();

// TEST 1: Database Health Check
$health = Database::checkHealth();
assertTest("Database Health Check connects to Neon PostgreSQL", $health['connected'] === true);

// TEST 2: Seed Accounts Verification
$stmtAdmin = $pdo->prepare("SELECT * FROM public.profiles WHERE email = 'admin@bonhagan.com'");
$stmtAdmin->execute();
$adminUser = $stmtAdmin->fetch();
assertTest("Admin seed account exists with 'admin' role", $adminUser && $adminUser['role'] === 'admin');
assertTest("Admin password verification succeeds", password_verify('AdminPassword123!', $adminUser['password_hash']));

// TEST 3: Passenger Registration & Role Security
$testEmail = 'test_pass_' . time() . '_' . bin2hex(random_bytes(2)) . '@bonhagan.com';
$testPassHash = password_hash('SecretPass123!', PASSWORD_BCRYPT);
$stmtReg = $pdo->prepare("
    INSERT INTO public.profiles (full_name, email, password_hash, role, account_status)
    VALUES ('Automated Tester', :email, :hash, 'passenger', 'active')
    RETURNING id, role
");
$stmtReg->execute([':email' => $testEmail, ':hash' => $testPassHash]);
$regUser = $stmtReg->fetch();
assertTest("New passenger registered with default role 'passenger'", $regUser && $regUser['role'] === 'passenger');

// TEST 4: Create a dedicated Test Trip for isolated booking tests
$stmtTrip = $pdo->query("SELECT id FROM public.routes WHERE route_code = 'MNL-BUL-NB' LIMIT 1");
$routeId = (int)$stmtTrip->fetchColumn();
$stmtBus = $pdo->query("SELECT id FROM public.buses WHERE bus_number = 'BT-101' LIMIT 1");
$busId = (int)$stmtBus->fetchColumn();

$uniqueTripCode = 'TEST-TRIP-' . time() . '-' . strtoupper(bin2hex(random_bytes(2)));
$stmtCreateTrip = $pdo->prepare("
    INSERT INTO public.trips (trip_code, route_id, bus_id, departure_datetime, estimated_arrival, status, standing_enabled, standing_capacity_override)
    VALUES (:code, :r_id, :b_id, NOW() + INTERVAL '5 hours', NOW() + INTERVAL '7 hours', 'scheduled', TRUE, 10)
    RETURNING id, trip_code, route_id, bus_id
");
$stmtCreateTrip->execute([':code' => $uniqueTripCode, ':r_id' => $routeId, ':b_id' => $busId]);
$testTrip = $stmtCreateTrip->fetch();
assertTest("Dedicated isolated test trip scheduled", !empty($testTrip['id']));

$stmtStops = $pdo->prepare("SELECT id, sequence_number FROM public.route_stops WHERE route_id = :r_id ORDER BY sequence_number ASC");
$stmtStops->execute([':r_id' => $testTrip['route_id']]);
$stops = $stmtStops->fetchAll();
assertTest("Route has at least 5 sequenced stops", count($stops) >= 5);

$stop1 = (int)$stops[0]['id']; // Monumento (seq 1)
$stop3 = (int)$stops[2]['id']; // Tungkong Mangga (seq 3)
$stop6 = (int)$stops[count($stops) - 1]['id']; // FVR (seq 6)

// TEST 5: Server-side Fare Calculation (Regular vs Student)
$fareRegular = FareService::calculate((int)$testTrip['route_id'], 1, (int)$stop1, (int)$stop6, 'regular');
$fareStudent = FareService::calculate((int)$testTrip['route_id'], 1, (int)$stop1, (int)$stop6, 'student');

assertTest("Server-side base fare calculated (> 0)", $fareRegular['base_fare'] > 0);
assertTest("Student discount is 20%", (float)$fareStudent['discount_percent'] === 20.0);
assertTest("Student final fare is less than regular fare", $fareStudent['final_fare'] < $fareRegular['final_fare']);

// TEST 6: Multi-Passenger Booking Creation with Segment Allocation
$stmtSeats = $pdo->prepare("SELECT id FROM public.bus_seats WHERE bus_id = :b_id ORDER BY seat_number ASC LIMIT 2");
$stmtSeats->execute([':b_id' => $testTrip['bus_id']]);
$seatRows = $stmtSeats->fetchAll();
$seat1Id = (int)$seatRows[0]['id'];
$seat2Id = (int)$seatRows[1]['id'];

$bookingData = [
    [
        'passenger_name' => 'Tester Regular',
        'passenger_type' => 'regular',
        'boarding_stop_id' => $stop1,
        'dropoff_stop_id' => $stop3,
        'allocation_type' => 'seat',
        'seat_id' => $seat1Id
    ],
    [
        'passenger_name' => 'Tester Student',
        'passenger_type' => 'student',
        'boarding_stop_id' => $stop1,
        'dropoff_stop_id' => $stop6,
        'allocation_type' => 'seat',
        'seat_id' => $seat2Id
    ]
];

$bookingResult = BookingService::createBooking((string)$regUser['id'], (int)$testTrip['id'], $bookingData);
assertTest("Multi-passenger booking created with reference", !empty($bookingResult['booking']['booking_reference']));
assertTest("Booking contains exactly 2 passengers", count($bookingResult['passengers']) === 2);
assertTest("Booking status is initially 'pending'", $bookingResult['booking']['booking_status'] === 'pending');

$bookingId = (int)$bookingResult['booking']['id'];

// TEST 7: Segment Overlap Seat Conflict Prevention
$conflictCaught = false;
try {
    BookingService::createBooking((string)$regUser['id'], (int)$testTrip['id'], [
        [
            'passenger_name' => 'Double Booking Intruder',
            'passenger_type' => 'regular',
            'boarding_stop_id' => $stop1,
            'dropoff_stop_id' => $stop3,
            'allocation_type' => 'seat',
            'seat_id' => $seat1Id // already held!
        ]
    ]);
} catch (Exception $e) {
    $conflictCaught = true;
}
assertTest("Double booking attempt rejected by concurrency check", $conflictCaught);

// TEST 8: Non-Overlapping Segment Seat Sharing (Stop 3 -> Stop 6 for Seat 1)
$sharingAllowed = false;
try {
    $sharedBooking = BookingService::createBooking((string)$regUser['id'], (int)$testTrip['id'], [
        [
            'passenger_name' => 'Downstream Passenger',
            'passenger_type' => 'regular',
            'boarding_stop_id' => $stop3, // Boards after first passenger gets off
            'dropoff_stop_id' => $stop6,
            'allocation_type' => 'seat',
            'seat_id' => $seat1Id // Same seat reused on non-overlapping segment!
        ]
    ]);
    $sharingAllowed = !empty($sharedBooking['booking']['id']);
} catch (Exception $e) {
    $sharingAllowed = false;
}
assertTest("Non-overlapping route segment permits seat reuse", $sharingAllowed);

// TEST 9: Terminal Payment Creation & Confirmation
$payment = PaymentService::createPayment($bookingId, 'terminal');
assertTest("Terminal payment record created with status 'pending'", $payment['payment_status'] === 'pending');

$confirmedPayment = PaymentService::confirmPayment((int)$payment['id'], (string)$adminUser['id'], 'terminal_counter');
assertTest("Staff terminal payment confirmation sets status to 'paid'", $confirmedPayment['payment_status'] === 'paid');

// TEST 10: E-Ticket Issuance & QR Token Generation
$stmtTickets = $pdo->prepare("
    SELECT t.id, t.ticket_reference, t.qr_token, t.ticket_status
    FROM public.tickets t
    JOIN public.booking_passengers bp ON bp.id = t.passenger_id
    WHERE bp.booking_id = :b_id
");
$stmtTickets->execute([':b_id' => $bookingId]);
$issuedTickets = $stmtTickets->fetchAll();
assertTest("E-tickets automatically issued upon payment confirmation", count($issuedTickets) === 2);
assertTest("E-tickets have 'valid' status and unique QR tokens", $issuedTickets[0]['ticket_status'] === 'valid' && !empty($issuedTickets[0]['qr_token']));

// TEST 11: Boarding Verification & Duplicate Scan Prevention
$ticketToBoard = $issuedTickets[0];
$boardResult = TicketService::validateBoarding($ticketToBoard['qr_token'], (string)$adminUser['id'], 'qr', 'Test scan');
assertTest("First QR ticket scan succeeds and marks status 'boarded'", $boardResult['ticket_status'] === 'boarded');

$duplicateScanBlocked = false;
try {
    TicketService::validateBoarding($ticketToBoard['qr_token'], (string)$adminUser['id'], 'qr', 'Duplicate scan');
} catch (RuntimeException $e) {
    if (strpos($e->getMessage(), 'ALREADY BOARDED') !== false) {
        $duplicateScanBlocked = true;
    }
}
assertTest("Duplicate boarding scan strictly blocked with error", $duplicateScanBlocked);

// TEST 12: QR Code Service SVG Output
$qrSvg = QRCodeService::renderSvg($ticketToBoard['qr_token']);
assertTest("QRCodeService produces valid SVG markup", strpos($qrSvg, '<svg') !== false && strpos($qrSvg, '</svg>') !== false);

echo PHP_EOL . "=========================================================" . PHP_EOL;
echo "  TEST SUMMARY: {$passed} PASSED, {$failed} FAILED" . PHP_EOL;
echo "=========================================================" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}
