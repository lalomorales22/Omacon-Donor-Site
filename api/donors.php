<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

prime_require_method('GET');
prime_json_response(prime_api_payload());
