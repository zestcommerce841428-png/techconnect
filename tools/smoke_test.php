<?php
/**
 * Smoke test: requests every key route and asserts the expected HTTP status.
 * Zero dependencies — run before every deploy, against local or production:
 *
 *   php tools/smoke_test.php                        # defaults to http://localhost:8000
 *   php tools/smoke_test.php https://puchonow.in    # production check
 *
 * Exit code 0 = all pass, 1 = failures (usable in scripts/CI).
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$base = rtrim($argv[1] ?? 'http://localhost:8000', '/');

// route => acceptable status codes. 302 entries are auth-gated pages that must
// redirect guests to login (a 200 there would mean an auth regression!).
$routes = [
    '/' => [200],
    '/questions' => [200],
    '/categories' => [200],
    '/tags' => [200],
    '/search?q=test' => [200],
    '/jobs' => [200],
    '/experts' => [200],
    '/groups' => [200],
    '/blog' => [200],
    '/leaderboard' => [200],
    '/stats' => [200],
    '/features' => [200],
    '/faq' => [200],
    '/about' => [200],
    '/privacy' => [200],
    '/terms' => [200],
    '/contact' => [200],
    '/feedback' => [200],
    '/roadmap' => [200],
    '/changelog' => [200],
    '/collections' => [200],
    '/login' => [200],
    '/register' => [200],
    '/forgot_password' => [200],
    '/login_otp' => [200],
    '/api_docs' => [200],
    '/status' => [200],
    '/rss' => [200],
    '/sitemap.xml' => [200],
    '/admin/trash' => [302],
    '/robots.txt' => [200],
    '/manifest.json' => [200],
    '/offline.html' => [200],
    '/sw.js' => [200],
    '/.well-known/security.txt' => [200],
    '/this-page-should-not-exist-xyz' => [404],
    // Regression: "/<existing-page>/<anything>" must 404, not 500. The generic
    // extensionless fallback used to test %{REQUEST_FILENAME}.php, which matched
    // the "/login" prefix and rewrote to the non-existent "/login/foo.php".
    '/login/foo' => [404],
    '/contact/foo' => [404],
    '/blog/x_1' => [404],
    '/s/abc1' => [404],
    '/admin/dashboard/x' => [404],
    // Auth-gated: guests MUST be redirected.
    '/ask' => [302],
    '/inbox' => [302],
    '/drafts' => [302],
    '/saved' => [302],
    '/api_keys' => [302],
    '/notification_settings' => [302],
    '/security_account' => [302],
    '/admin/dashboard' => [302],
    '/admin/users' => [302],
    '/admin/feedback' => [302],
    '/admin/settings' => [302],
    // Secrets: any status is fine (some redirect to a canonical URL) as long as
    // the response body never contains config markers. Checked separately below.
];

// Sequential requests — parallel bursts trip shared-hosting concurrency limits
// and produce false 500s. A ~55-route sweep still finishes in a few seconds.
function http_status(string $url): int
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'PuchoNow-SmokeTest', CURLOPT_FOLLOWLOCATION => false,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $code;
}

$fail = 0;
foreach ($routes as $path => $expected) {
    $code = http_status($base . $path);
    $ok = in_array($code, $expected, true);
    if (!$ok) $fail++;
    printf("%s %-45s %d (expected %s)\n", $ok ? 'PASS' : 'FAIL', $path, $code, implode('/', $expected));
}

// Secret-leak checks: follow redirects, assert body reveals no config.
$secretPaths = ['/.env', '/config.php', '/includes/db.php', '/migrations/028_seed_categories.sql', '/README.md', '/composer.json'];
$markers = ['DB_PASS', 'DB_USER', 'SMTP_PASS', 'define(', 'CREATE TABLE', 'password_hash'];
foreach ($secretPaths as $path) {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 20]);
    $body = (string) curl_exec($ch);
    curl_close($ch);
    $leaked = array_filter($markers, fn($m) => stripos($body, $m) !== false);
    $ok = !$leaked;
    if (!$ok) $fail++;
    printf("%s %-45s %s\n", $ok ? 'PASS' : 'FAIL', $path . ' (no-leak)', $ok ? 'no secrets in body' : 'LEAKED: ' . implode(',', $leaked));
}

$total = count($routes) + count($secretPaths);
printf("\n%d/%d passed against %s\n", $total - $fail, $total, $base);
exit($fail > 0 ? 1 : 0);
