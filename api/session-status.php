<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

prime_require_method('GET');
prime_assert_stripe_ready();

$sessionId = prime_compact_text($_GET['session_id'] ?? '', 120);

if ($sessionId === '') {
    prime_json_response(['error' => 'Missing session_id.'], 422);
}

try {
    $session = prime_stripe_request('GET', '/v1/checkout/sessions/' . rawurlencode($sessionId));
    $savedDonor = null;

    if (($session['payment_status'] ?? '') === 'paid') {
        $savedDonor = prime_persist_checkout_session($session);
    }

    prime_json_response([
        'sessionId' => $sessionId,
        'status' => $session['status'] ?? null,
        'paymentStatus' => $session['payment_status'] ?? null,
        'customerEmail' => $session['customer_details']['email'] ?? null,
        'donor' => $savedDonor,
    ]);
} catch (Throwable $throwable) {
    prime_json_response([
        'error' => $throwable->getMessage(),
    ], 500);
}
