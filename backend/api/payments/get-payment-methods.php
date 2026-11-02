<?php
/**
 * BONHAGAN TRANSIT — Get Available Payment Methods API
 * Returns active payment gateways, digital wallets, cards, and counter payment options with authentic brand logos.
 */

require_once dirname(dirname(__DIR__)) . '/helpers/response.php';

$paymentMethods = [
    [
        'id' => 'gcash',
        'code' => 'gcash',
        'name' => 'GCash Express',
        'provider' => 'GCash',
        'method_type' => 'online',
        'description' => 'Instant e-wallet clearance & immediate confirmed e-ticket QR',
        'badge' => 'Recommended',
        'badge_type' => 'success',
        'logo_url' => '/assets/images/payments/gcash.svg',
        'fallback_icon' => 'fa-solid fa-mobile-screen-button',
        'accent_color' => '#007DFE',
        'is_default' => true,
        'instructions' => 'Enter your registered GCash mobile number to receive payment authorization.'
    ],
    [
        'id' => 'maya',
        'code' => 'maya',
        'name' => 'Maya / Digital Wallet',
        'provider' => 'Maya',
        'method_type' => 'online',
        'description' => 'Scan QR Ph or pay via linked Maya account',
        'badge' => 'QR Ph Ready',
        'badge_type' => 'info',
        'logo_url' => '/assets/images/payments/maya.svg',
        'fallback_icon' => 'fa-solid fa-wallet',
        'accent_color' => '#00D665',
        'is_default' => false,
        'instructions' => 'Scan the merchant QR code with your Maya app or enter your Maya phone number.'
    ],
    [
        'id' => 'card',
        'code' => 'card',
        'name' => 'Visa / Mastercard Debit & Credit',
        'provider' => 'CreditCard',
        'method_type' => 'online',
        'description' => 'Secure 3D-secure card authentication',
        'badge' => null,
        'badge_type' => null,
        'logo_url' => '/assets/images/payments/cards.svg',
        'fallback_icon' => 'fa-solid fa-credit-card',
        'accent_color' => '#2563EB',
        'is_default' => false,
        'instructions' => 'Supports Philippine and international debit or credit cards powered by Visa and Mastercard.'
    ],
    [
        'id' => 'cash',
        'code' => 'cash',
        'name' => 'Cash at Dispatch Counter or Onboard',
        'provider' => 'Cash',
        'method_type' => 'terminal',
        'description' => 'Pay directly to station teller or conductor prior to departure',
        'badge' => 'Counter / Conductor',
        'badge_type' => 'warning',
        'logo_url' => '/assets/images/payments/cash.svg',
        'fallback_icon' => 'fa-solid fa-money-bill-wave',
        'accent_color' => '#059669',
        'is_default' => false,
        'instructions' => 'Present your Booking Reference code to the terminal cashier or onboard conductor to pay in cash.'
    ]
];

sendSuccess('Available payment channels retrieved successfully.', [
    'channels' => $paymentMethods,
    'count' => count($paymentMethods),
    'currency' => 'PHP'
]);

