<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/markdown.php';

header('Content-Type: application/json');

if (!current_user()) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
if (!hash_equals($_SESSION['csrf_token'] ?? '', $data['csrf_token'] ?? '')) {
    http_response_code(419);
    echo json_encode(['error' => 'Invalid session, please refresh.']);
    exit;
}

$body = mb_substr((string) ($data['body'] ?? ''), 0, 20000);
echo json_encode(['html' => render_markdown($body)]);
