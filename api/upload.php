<?php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in to upload files.']);
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    http_response_code(419);
    echo json_encode(['error' => 'Invalid session, please refresh.']);
    exit;
}

if (!rate_limit('upload', 20, 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'Upload limit reached. Please try again later.']);
    exit;
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload failed.']);
    exit;
}

$file = $_FILES['file'];
$maxSize = (int) setting('max_upload_mb', '5') * 1024 * 1024;
if ($file['size'] > $maxSize) {
    http_response_code(413);
    echo json_encode(['error' => 'File too large. Max 5MB.']);
    exit;
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!isset($allowed[$mime])) {
    http_response_code(415);
    echo json_encode(['error' => 'Unsupported file type. Allowed: jpg, png, gif, webp, pdf.']);
    exit;
}

// Optimise before storing: bytes we never store are bytes we never pay to store
// OR to serve. Falls back to the original on any failure.
require_once __DIR__ . '/../includes/image_optimizer.php';
$rawBytes = (string) file_get_contents($file['tmp_name']);
$originalSize = strlen($rawBytes);
[$bytes, $mime, $ext] = optimize_image($rawBytes, $mime);
if ($ext === 'bin') {
    $ext = $allowed[$mime] ?? 'bin';
}

$storagePath = date('Y') . '/' . date('m') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;

// Routed through the storage abstraction so the same upload works on local disk
// or any S3-compatible bucket, decided by admin settings rather than by code.
require_once __DIR__ . '/../includes/storage/storage.php';
try {
    $publicPath = storage()->put($storagePath, $bytes, $mime);
} catch (Throwable $e) {
    error_log('upload failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file. Please try again.']);
    exit;
}
$storedSize = strlen($bytes);

$pdo = db();
// Record the STORED size, not the uploaded size — this table is what the storage
// dashboard bills against, so it must reflect what actually occupies the bucket.
$pdo->prepare('INSERT INTO uploads (user_id, path, original_name, mime, size) VALUES (?, ?, ?, ?, ?)')
    ->execute([$user['id'], $publicPath, basename($file['name']), $mime, $storedSize]);

$isImage = str_starts_with($mime, 'image/');
$markdown = $isImage ? "![{$file['name']}]({$publicPath})" : "[{$file['name']}]({$publicPath})";

echo json_encode([
    'url' => $publicPath,
    'markdown' => $markdown,
    'original_size' => $originalSize,
    'stored_size' => $storedSize,
    'saved_percent' => $originalSize > 0 ? round((1 - $storedSize / $originalSize) * 100) : 0,
]);
