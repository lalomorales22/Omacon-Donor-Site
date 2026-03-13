<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

prime_require_method('POST');
prime_require_same_origin();
prime_assert_stripe_ready();

$payload = prime_request_json();
$tiers = prime_tiers();
$tierKey = isset($tiers[$payload['tier'] ?? '']) ? (string) $payload['tier'] : 'starter';
$tier = $tiers[$tierKey];

$company = prime_compact_text($payload['company'] ?? '', 80);
$contact = prime_compact_text($payload['contact'] ?? '', 80);
$email = prime_clean_email($payload['email'] ?? '');
$headline = prime_compact_text($payload['headline'] ?? '', 120);
$bio = prime_compact_text($payload['bio'] ?? '', 360);
$website = prime_clean_website($payload['website'] ?? '');
$image = prime_clean_image_path($payload['image'] ?? '');

if ($company === '' || $contact === '' || $email === '') {
    prime_json_response([
        'error' => 'Company, contact, and email are required before launching Stripe Checkout.',
    ], 422);
}

$lineItems = [[
    'quantity' => 1,
    'price_data' => [
        'currency' => 'usd',
        'unit_amount' => $tier['amountCents'],
        'product_data' => [
            'name' => prime_app_name() . ' // ' . $tier['label'] . ' Donation',
            'description' => $tier['summary'],
        ],
    ],
]];

$params = [
    'mode' => 'payment',
    'success_url' => prime_url('/?payment=success&session_id={CHECKOUT_SESSION_ID}'),
    'cancel_url' => prime_url('/?payment=cancelled'),
    'customer_email' => $email,
    'billing_address_collection' => 'auto',
    'submit_type' => 'donate',
    'line_items' => $lineItems,
    'metadata' => [
        'company' => $company,
        'contact' => $contact,
        'email' => $email,
        'headline' => $headline,
        'bio' => $bio,
        'website' => $website,
        'tier' => $tierKey,
        'image' => $image,
    ],
];

try {
    $session = prime_stripe_request('POST', '/v1/checkout/sessions', $params);
    prime_add_feed_event('checkout', $company . ' opened a Stripe checkout session for the ' . $tier['label'] . ' tier.');
    prime_json_response([
        'sessionId' => $session['id'] ?? null,
        'url' => $session['url'] ?? null,
    ]);
} catch (Throwable $throwable) {
    prime_json_response([
        'error' => $throwable->getMessage(),
    ], 500);
}
