<?php
declare(strict_types=1);

const PRIME_APP_ROOT = __DIR__ . '/..';
const PRIME_STORAGE_DIR = PRIME_APP_ROOT . '/storage';
const PRIME_UPLOAD_DIR = PRIME_STORAGE_DIR . '/uploads';
const PRIME_DB_PATH = PRIME_STORAGE_DIR . '/omacon.sqlite';
const PRIME_STRIPE_WEBHOOK_TOLERANCE = 300;

prime_load_env(PRIME_APP_ROOT . '/.env');
prime_initialize_storage();

function prime_load_env(string $path): void
{
    static $loaded = false;

    if ($loaded || !is_file($path)) {
        return;
    }

    $loaded = true;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $trimmed, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function prime_initialize_storage(): void
{
    if (!is_dir(PRIME_STORAGE_DIR)) {
        mkdir(PRIME_STORAGE_DIR, 0775, true);
    }

    if (!is_dir(PRIME_UPLOAD_DIR)) {
        mkdir(PRIME_UPLOAD_DIR, 0775, true);
    }
}

function prime_env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
}

function prime_app_name(): string
{
    return prime_env('APP_NAME', 'Omacon Fund Wall') ?? 'Omacon Fund Wall';
}

function prime_base_url(): string
{
    $configured = prime_env('APP_URL');

    if ($configured !== null) {
        return rtrim($configured, '/');
    }

    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';
    $https = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['SERVER_PORT'] ?? '') === '443') ||
        (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );

    return ($https ? 'https' : 'http') . '://' . $host;
}

function prime_url(string $path = ''): string
{
    return prime_base_url() . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function prime_is_stripe_ready(): bool
{
    return prime_env('STRIPE_SECRET_KEY') !== null;
}

function prime_tiers(): array
{
    return [
        'starter' => [
            'key' => 'starter',
            'label' => 'Starter',
            'amountCents' => 9900,
            'accent' => '#8be29f',
            'summary' => 'A small push for packages, docs, and a live name on the wall.',
        ],
        'booster' => [
            'key' => 'booster',
            'label' => 'Booster',
            'amountCents' => 24900,
            'accent' => '#6ac0ff',
            'summary' => 'Bigger placement on the wall and more lift for release work.',
        ],
        'ship-it' => [
            'key' => 'ship-it',
            'label' => 'Ship-It',
            'amountCents' => 49900,
            'accent' => '#b17bff',
            'summary' => 'Priority placement and a brighter signal for Omacon support.',
        ],
        'legend' => [
            'key' => 'legend',
            'label' => 'Legend',
            'amountCents' => 99900,
            'accent' => '#ffb347',
            'summary' => 'Top-tier backing for distro development, media, and launch momentum.',
        ],
    ];
}

function prime_money(int $amountCents): string
{
    return '$' . number_format($amountCents / 100, 0);
}

function prime_now_iso(): string
{
    return gmdate('c');
}

function prime_compact_text(mixed $value, int $maxLength = 255): string
{
    $text = trim((string) $value);
    $text = preg_replace('/\s+/u', ' ', $text) ?? '';

    if ($text === '') {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLength);
    }

    return substr($text, 0, $maxLength);
}

function prime_clean_email(mixed $value): string
{
    $email = trim((string) $value);

    if ($email === '') {
        return '';
    }

    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function prime_clean_website(mixed $value): string
{
    $website = trim((string) $value);

    if ($website === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $website)) {
        $website = 'https://' . $website;
    }

    return filter_var($website, FILTER_VALIDATE_URL) ? $website : '';
}

function prime_clean_image_path(mixed $value): string
{
    $path = trim((string) $value);

    if ($path === '') {
        return '';
    }

    if (str_starts_with($path, '/storage/uploads/')) {
        $fullPath = PRIME_APP_ROOT . $path;
        return is_file($fullPath) ? $path : '';
    }

    if (preg_match('#^https?://#i', $path) && filter_var($path, FILTER_VALIDATE_URL)) {
        return $path;
    }

    return '';
}

function prime_avatar_svg(string $label, string $from, string $to): string
{
    $initials = strtoupper(substr(preg_replace('/[^a-z0-9]+/i', '', $label) ?: 'DW', 0, 2));
    $safeInitials = htmlspecialchars($initials, ENT_QUOTES, 'UTF-8');

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 160" role="img" aria-label="{$safeInitials}">
  <defs>
    <linearGradient id="g" x1="0" x2="1" y1="0" y2="1">
      <stop offset="0%" stop-color="{$from}" />
      <stop offset="100%" stop-color="{$to}" />
    </linearGradient>
  </defs>
  <rect width="160" height="160" rx="28" fill="url(#g)" />
  <circle cx="80" cy="80" r="48" fill="rgba(7,9,12,.18)" />
  <text x="80" y="96" text-anchor="middle" font-size="54" font-family="Arial, sans-serif" fill="#fcf6ef" font-weight="700">{$safeInitials}</text>
</svg>
SVG;

    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

function prime_seed_donors(): array
{
    return [
        [
            'company' => 'Dotfile Syndicate',
            'contact' => 'Rin Vale',
            'email' => 'rin@dotfilesyndicate.dev',
            'headline' => 'Backing Omacon so the desktop stays weird, fast, and opinionated.',
            'bio' => 'Shell scripts, wallpapers, keybinds, and terminal tooling. We donate because a distro with taste deserves gas in the tank.',
            'website' => 'https://dotfilesyndicate.dev',
            'tier' => 'legend',
            'amountCents' => 99900,
            'image' => prime_avatar_svg('Dotfile Syndicate', '#9656ff', '#53c6ff'),
        ],
        [
            'company' => 'Pane Forge',
            'contact' => 'Micah Trent',
            'email' => 'micah@paneforge.io',
            'headline' => 'Funding release work, docs, and the parts people actually feel.',
            'bio' => 'We care about ergonomics, package polish, and fast onboarding. Omacon has the right mix of sharp defaults and fun.',
            'website' => 'https://paneforge.io',
            'tier' => 'ship-it',
            'amountCents' => 49900,
            'image' => prime_avatar_svg('Pane Forge', '#12b981', '#0ea5e9'),
        ],
        [
            'company' => 'Kernel Roast',
            'contact' => 'Jae Soto',
            'email' => 'jae@kernelroast.com',
            'headline' => 'Coffee money for packages, wallpapers, docs, and release nights.',
            'bio' => 'A tiny roastery for people living in terminals and Hyprland. Happy to back the distro and the community orbiting it.',
            'website' => 'https://kernelroast.com',
            'tier' => 'booster',
            'amountCents' => 24900,
            'image' => prime_avatar_svg('Kernel Roast', '#ff6c42', '#ffb347'),
        ],
    ];
}

function prime_seed_demo_database(PDO $pdo): void
{
    $seedVersion = 'omacon-demo-v3';
    $storedVersionStatement = $pdo->prepare('SELECT value FROM app_meta WHERE key = :key LIMIT 1');
    $storedVersionStatement->execute([':key' => 'seed_version']);
    $storedVersion = $storedVersionStatement->fetchColumn();
    $count = (int) $pdo->query('SELECT COUNT(*) FROM donors')->fetchColumn();
    $liveCount = (int) $pdo->query('SELECT COUNT(*) FROM donors WHERE payment_status <> "seeded"')->fetchColumn();

    if ($count > 0 && $liveCount > 0) {
        if ($storedVersion !== $seedVersion) {
            $meta = $pdo->prepare('INSERT INTO app_meta (key, value) VALUES (:key, :value)
                ON CONFLICT(key) DO UPDATE SET value = excluded.value');
            $meta->execute([':key' => 'seed_version', ':value' => $seedVersion]);
        }
        return;
    }

    if ($count > 0 && $storedVersion === $seedVersion) {
        return;
    }

    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM donors');
    $pdo->exec('DELETE FROM feed_events');

    $insert = $pdo->prepare(
        'INSERT INTO donors (
            id, company, contact, email, headline, bio, website, image,
            tier, amount_cents, stripe_session_id, payment_status, joined_at, created_at, updated_at
        ) VALUES (
            :id, :company, :contact, :email, :headline, :bio, :website, :image,
            :tier, :amount_cents, :stripe_session_id, :payment_status, :joined_at, :created_at, :updated_at
        )'
    );

    foreach (prime_seed_donors() as $index => $donor) {
        $stamp = gmdate('c', time() - (($index + 1) * 540));
        $insert->execute([
            ':id' => 'seed-' . ($index + 1),
            ':company' => $donor['company'],
            ':contact' => $donor['contact'],
            ':email' => $donor['email'],
            ':headline' => $donor['headline'],
            ':bio' => $donor['bio'],
            ':website' => $donor['website'],
            ':image' => $donor['image'],
            ':tier' => $donor['tier'],
            ':amount_cents' => $donor['amountCents'],
            ':stripe_session_id' => null,
            ':payment_status' => 'seeded',
            ':joined_at' => $stamp,
            ':created_at' => $stamp,
            ':updated_at' => $stamp,
        ]);
    }

    $feedInsert = $pdo->prepare(
        'INSERT INTO feed_events (kind, message, created_at) VALUES (:kind, :message, :created_at)'
    );
    $feedInsert->execute([
        ':kind' => 'seed',
        ':message' => 'Loaded three demo backers so the Omacon wall has life before Stripe is connected.',
        ':created_at' => prime_now_iso(),
    ]);
    $feedInsert->execute([
        ':kind' => 'seed',
        ':message' => 'Drop in real Stripe keys later and the rest of the app can stay exactly as-is.',
        ':created_at' => prime_now_iso(),
    ]);

    $meta = $pdo->prepare('INSERT INTO app_meta (key, value) VALUES (:key, :value)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $meta->execute([':key' => 'seed_version', ':value' => $seedVersion]);
    $pdo->commit();
}

function prime_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO('sqlite:' . PRIME_DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS donors (
            id TEXT PRIMARY KEY,
            company TEXT NOT NULL,
            contact TEXT NOT NULL,
            email TEXT NOT NULL DEFAULT "",
            headline TEXT NOT NULL DEFAULT "",
            bio TEXT NOT NULL DEFAULT "",
            website TEXT NOT NULL DEFAULT "",
            image TEXT NOT NULL DEFAULT "",
            tier TEXT NOT NULL,
            amount_cents INTEGER NOT NULL,
            stripe_session_id TEXT UNIQUE,
            payment_status TEXT NOT NULL DEFAULT "seeded",
            joined_at TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS feed_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kind TEXT NOT NULL DEFAULT "info",
            message TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS app_meta (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )'
    );
    prime_seed_demo_database($pdo);

    return $pdo;
}

function prime_add_feed_event(string $kind, string $message): void
{
    $statement = prime_db()->prepare(
        'INSERT INTO feed_events (kind, message, created_at) VALUES (:kind, :message, :created_at)'
    );

    $statement->execute([
        ':kind' => prime_compact_text($kind, 40) ?: 'info',
        ':message' => prime_compact_text($message, 255),
        ':created_at' => prime_now_iso(),
    ]);
}

function prime_fetch_donors(): array
{
    $rows = prime_db()->query(
        'SELECT id, company, contact, email, headline, bio, website, image, tier, amount_cents,
                stripe_session_id, payment_status, joined_at
         FROM donors
         ORDER BY amount_cents DESC, datetime(joined_at) DESC'
    )->fetchAll();

    return array_map(static function (array $row): array {
        return [
            'id' => $row['id'],
            'company' => $row['company'],
            'contact' => $row['contact'],
            'email' => $row['email'],
            'headline' => $row['headline'],
            'bio' => $row['bio'],
            'website' => $row['website'],
            'image' => $row['image'],
            'tier' => $row['tier'],
            'amountCents' => (int) $row['amount_cents'],
            'paymentStatus' => $row['payment_status'],
            'joinedAt' => $row['joined_at'],
            'stripeSessionId' => $row['stripe_session_id'],
        ];
    }, $rows);
}

function prime_fetch_feed(int $limit = 14): array
{
    $statement = prime_db()->prepare(
        'SELECT id, kind, message, created_at
         FROM feed_events
         ORDER BY id DESC
         LIMIT :limit'
    );
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'kind' => $row['kind'],
            'message' => $row['message'],
            'createdAt' => $row['created_at'],
        ];
    }, $statement->fetchAll());
}

function prime_fetch_stats(): array
{
    $donors = prime_fetch_donors();
    $totalRaisedCents = array_reduce(
        $donors,
        static fn (int $carry, array $donor): int => $carry + (int) $donor['amountCents'],
        0
    );

    $topTier = 'starter';

    foreach ($donors as $donor) {
        if (($donor['tier'] ?? 'starter') === 'legend') {
            $topTier = 'legend';
            break;
        }

        if (($donor['tier'] ?? 'starter') === 'ship-it') {
            $topTier = 'ship-it';
        } elseif (($donor['tier'] ?? 'starter') === 'booster' && $topTier === 'starter') {
            $topTier = 'booster';
        }
    }

    return [
        'totalRaisedCents' => $totalRaisedCents,
        'donorCount' => count($donors),
        'averageGiftCents' => count($donors) > 0 ? (int) floor($totalRaisedCents / count($donors)) : 0,
        'highestTier' => $topTier,
    ];
}

function prime_api_payload(): array
{
    return [
        'appName' => prime_app_name(),
        'baseUrl' => prime_base_url(),
        'stripeReady' => prime_is_stripe_ready(),
        'tiers' => array_values(prime_tiers()),
        'donors' => prime_fetch_donors(),
        'feed' => prime_fetch_feed(),
        'stats' => prime_fetch_stats(),
    ];
}

function prime_request_json(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

function prime_require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        prime_json_response(['error' => 'Method not allowed.'], 405);
    }
}

function prime_json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function prime_assert_stripe_ready(): void
{
    if (!prime_is_stripe_ready()) {
        prime_json_response([
            'error' => 'Stripe is not configured yet. Add STRIPE_SECRET_KEY to .env first.',
        ], 503);
    }
}

function prime_stripe_request(string $method, string $path, array $params = []): array
{
    prime_assert_stripe_ready();

    $url = 'https://api.stripe.com' . $path;
    $method = strtoupper($method);

    $curl = curl_init();

    if ($curl === false) {
        throw new RuntimeException('Failed to initialize Stripe request.');
    }

    $headers = [
        'Authorization: Bearer ' . prime_env('STRIPE_SECRET_KEY'),
        'Accept: application/json',
    ];

    if ($method === 'GET' && $params !== []) {
        $url .= '?' . http_build_query($params);
    } elseif ($params !== []) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);

    $raw = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($raw === false || $error !== '') {
        throw new RuntimeException('Stripe request failed: ' . $error);
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('Stripe returned an invalid response.');
    }

    if ($status >= 400) {
        $message = $decoded['error']['message'] ?? 'Stripe returned an error.';
        throw new RuntimeException($message, $status);
    }

    return $decoded;
}

function prime_verify_stripe_signature(string $payload, string $signatureHeader, string $secret): bool
{
    $parts = [];

    foreach (explode(',', $signatureHeader) as $segment) {
        [$key, $value] = array_pad(explode('=', trim($segment), 2), 2, null);

        if ($key !== null && $value !== null) {
            $parts[$key][] = $value;
        }
    }

    $timestamp = isset($parts['t'][0]) ? (int) $parts['t'][0] : 0;
    $signatures = $parts['v1'] ?? [];

    if ($timestamp <= 0 || $signatures === []) {
        return false;
    }

    if (abs(time() - $timestamp) > PRIME_STRIPE_WEBHOOK_TOLERANCE) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }

    return false;
}

function prime_persist_checkout_session(array $session): array
{
    $sessionId = prime_compact_text($session['id'] ?? '', 120);

    if ($sessionId === '') {
        throw new RuntimeException('Stripe session id missing.');
    }

    $existing = prime_db()->prepare('SELECT id FROM donors WHERE stripe_session_id = :session_id LIMIT 1');
    $existing->execute([':session_id' => $sessionId]);
    $existingRow = $existing->fetch();

    if (is_array($existingRow)) {
        $lookup = prime_db()->prepare(
            'SELECT id, company, contact, email, headline, bio, website, image, tier, amount_cents,
                    stripe_session_id, payment_status, joined_at
             FROM donors WHERE id = :id LIMIT 1'
        );
        $lookup->execute([':id' => $existingRow['id']]);
        $row = $lookup->fetch();

        if (is_array($row)) {
            return [
                'id' => $row['id'],
                'company' => $row['company'],
                'contact' => $row['contact'],
                'email' => $row['email'],
                'headline' => $row['headline'],
                'bio' => $row['bio'],
                'website' => $row['website'],
                'image' => $row['image'],
                'tier' => $row['tier'],
                'amountCents' => (int) $row['amount_cents'],
                'paymentStatus' => $row['payment_status'],
                'joinedAt' => $row['joined_at'],
                'stripeSessionId' => $row['stripe_session_id'],
            ];
        }
    }

    $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
    $tiers = prime_tiers();
    $tierKey = isset($tiers[$metadata['tier'] ?? '']) ? (string) $metadata['tier'] : 'starter';
    $tier = $tiers[$tierKey];
    $company = prime_compact_text($metadata['company'] ?? 'Anonymous backer', 80);
    $contact = prime_compact_text($metadata['contact'] ?? ($session['customer_details']['name'] ?? 'Unknown contact'), 80);
    $email = prime_clean_email($metadata['email'] ?? ($session['customer_details']['email'] ?? ''));
    $headline = prime_compact_text($metadata['headline'] ?? '', 120);
    $bio = prime_compact_text($metadata['bio'] ?? '', 360);
    $website = prime_clean_website($metadata['website'] ?? '');
    $image = prime_clean_image_path($metadata['image'] ?? '');
    $amountCents = max(100, (int) ($session['amount_total'] ?? $tier['amountCents']));
    $paymentStatus = prime_compact_text($session['payment_status'] ?? 'paid', 40) ?: 'paid';
    $joinedAt = isset($session['created']) ? gmdate('c', (int) $session['created']) : prime_now_iso();
    $createdAt = prime_now_iso();
    $id = 'donor-' . substr(sha1($sessionId), 0, 12);

    $insert = prime_db()->prepare(
        'INSERT INTO donors (
            id, company, contact, email, headline, bio, website, image,
            tier, amount_cents, stripe_session_id, payment_status, joined_at, created_at, updated_at
         ) VALUES (
            :id, :company, :contact, :email, :headline, :bio, :website, :image,
            :tier, :amount_cents, :stripe_session_id, :payment_status, :joined_at, :created_at, :updated_at
         )'
    );

    $insert->execute([
        ':id' => $id,
        ':company' => $company,
        ':contact' => $contact,
        ':email' => $email,
        ':headline' => $headline,
        ':bio' => $bio,
        ':website' => $website,
        ':image' => $image,
        ':tier' => $tierKey,
        ':amount_cents' => $amountCents,
        ':stripe_session_id' => $sessionId,
        ':payment_status' => $paymentStatus,
        ':joined_at' => $joinedAt,
        ':created_at' => $createdAt,
        ':updated_at' => $createdAt,
    ]);

    prime_add_feed_event('stripe', $company . ' backed Omacon at the ' . $tier['label'] . ' tier through Stripe.');

    return [
        'id' => $id,
        'company' => $company,
        'contact' => $contact,
        'email' => $email,
        'headline' => $headline,
        'bio' => $bio,
        'website' => $website,
        'image' => $image,
        'tier' => $tierKey,
        'amountCents' => $amountCents,
        'paymentStatus' => $paymentStatus,
        'joinedAt' => $joinedAt,
        'stripeSessionId' => $sessionId,
    ];
}
