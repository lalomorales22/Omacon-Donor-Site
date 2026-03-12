<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

prime_require_method('POST');

if (!isset($_FILES['logo']) || !is_array($_FILES['logo'])) {
    prime_json_response(['error' => 'No logo upload found.'], 422);
}

$file = $_FILES['logo'];

if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    prime_json_response(['error' => 'The upload failed before the file reached the app.'], 422);
}

$size = (int) ($file['size'] ?? 0);

if ($size <= 0 || $size > 2_500_000) {
    prime_json_response(['error' => 'Upload a PNG, JPG, GIF, or WEBP logo under 2.5MB.'], 422);
}

$tmpName = (string) ($file['tmp_name'] ?? '');
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($tmpName) ?: '';
$allowed = [
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];

if (!isset($allowed[$mime])) {
    prime_json_response(['error' => 'Unsupported image type. Use PNG, JPG, GIF, or WEBP.'], 422);
}

$filename = 'logo-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
$targetPath = PRIME_UPLOAD_DIR . '/' . $filename;

if (!move_uploaded_file($tmpName, $targetPath)) {
    prime_json_response(['error' => 'Failed to save the uploaded logo.'], 500);
}

prime_json_response([
    'url' => '/storage/uploads/' . $filename,
]);
