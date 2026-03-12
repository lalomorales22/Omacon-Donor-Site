<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

prime_require_method('POST');

$secret = prime_env('STRIPE_WEBHOOK_SECRET');

if ($secret === null) {
    prime_json_response(['error' => 'Missing STRIPE_WEBHOOK_SECRET.'], 503);
}

$payload = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ($payload === false || $payload === '') {
    prime_json_response(['error' => 'Missing request payload.'], 400);
}

if (!prime_verify_stripe_signature($payload, $signatureHeader, $secret)) {
    prime_json_response(['error' => 'Invalid Stripe signature.'], 400);
}

$event = json_decode($payload, true);

if (!is_array($event)) {
    prime_json_response(['error' => 'Invalid event payload.'], 400);
}

$type = (string) ($event['type'] ?? '');
$object = $event['data']['object'] ?? null;

try {
    if (
        is_array($object) &&
        in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)
    ) {
        prime_persist_checkout_session($object);
    }

    if (is_array($object) && $type === 'checkout.session.expired') {
        $company = prime_compact_text($object['metadata']['company'] ?? 'A backer', 80) ?: 'A backer';
        prime_add_feed_event('checkout', $company . ' abandoned an expired checkout session.');
    }

    prime_json_response(['received' => true]);
} catch (Throwable $throwable) {
    prime_json_response([
        'error' => $throwable->getMessage(),
    ], 500);
}
