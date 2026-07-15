<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/manifest+json');

$siteName = setting('site_name', SITE_NAME);
echo json_encode([
    'name' => $siteName,
    'short_name' => $siteName,
    'description' => setting('tagline', 'Ask, answer, connect'),
    'start_url' => '/',
    'display' => 'standalone',
    'background_color' => '#f8fafc',
    'theme_color' => '#0f172a',
    'icons' => [
        ['src' => '/assets/images/android-chrome-192x192.png', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => '/assets/images/android-chrome-512x512.png', 'sizes' => '512x512', 'type' => 'image/png'],
    ],
], JSON_UNESCAPED_SLASHES);
