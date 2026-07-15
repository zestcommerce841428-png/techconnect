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

$ext = $allowed[$mime];
$subdir = date('Y') . '/' . date('m');
$uploadRoot = __DIR__ . '/../uploads/' . $subdir;
if (!is_dir($uploadRoot)) {
    mkdir($uploadRoot, 0755, true);
}

$filename = bin2hex(random_bytes(16)) . '.' . $ext;
$destPath = $uploadRoot . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file.']);
    exit;
}

$publicPath = '/uploads/' . $subdir . '/' . $filename;

$pdo = db();
$pdo->prepare('INSERT INTO uploads (user_id, path, original_name, mime, size) VALUES (?, ?, ?, ?, ?)')
    ->execute([$user['id'], $publicPath, basename($file['name']), $mime, $file['size']]);

$isImage = str_starts_with($mime, 'image/');
$markdown = $isImage ? "![{$file['name']}]({$publicPath})" : "[{$file['name']}]({$publicPath})";

echo json_encode(['url' => $publicPath, 'markdown' => $markdown]);
