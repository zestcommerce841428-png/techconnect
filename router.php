<?php
/**
 * Dev-only router for `php -S localhost:8000 router.php`, mirroring the
 * pretty-URL rules in .htaccess so clean URLs can be tested locally
 * (php -S never reads .htaccess — that's an Apache-only mechanism).
 * Not used in production; Hostinger/Apache uses .htaccess directly.
 */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = __DIR__;

$routes = [
    '#^/q/([a-zA-Z0-9-]+)/?$#' => ['question.php', 'slug'],
    '#^/blog/([a-zA-Z0-9-]+)/?$#' => ['blog_post.php', 'slug'],
    '#^/page/([a-zA-Z0-9-]+)/?$#' => ['page.php', 'slug'],
    '#^/c/([a-zA-Z0-9-]+)/?$#' => ['questions.php', 'category'],
    '#^/tag/([a-zA-Z0-9-]+)/?$#' => ['questions.php', 'tag'],
    '#^/u/([a-zA-Z0-9_-]+)/?$#' => ['profile.php', 'username'],
    '#^/go/([a-zA-Z0-9]+)/?$#' => ['go.php', 'slug'],
    '#^/g/([a-zA-Z0-9-]+)/?$#' => ['group.php', 'slug'],
];

$staticRoutes = [
    '/checkout/success' => 'checkout_success.php',
    '/checkout/cancel' => 'checkout_cancel.php',
];
if (isset($staticRoutes[$uri])) {
    chdir($root);
    require $root . '/' . $staticRoutes[$uri];
    return true;
}

if (preg_match('#^/u/([a-zA-Z0-9_-]+)/([a-zA-Z0-9-]+)/?$#', $uri, $m)) {
    $_GET['username'] = $m[1];
    $_GET['slug'] = $m[2];
    chdir($root);
    require $root . '/collection.php';
    return true;
}

foreach ($routes as $pattern => [$target, $param]) {
    if (preg_match($pattern, $uri, $m)) {
        $_GET[$param] = $m[1];
        chdir($root);
        require $root . '/' . $target;
        return true;
    }
}

if ($uri === '/manifest.json') {
    chdir($root);
    require $root . '/manifest.php';
    return true;
}

if ($uri === '/sitemap.xml') {
    require $root . '/sitemap.php';
    return true;
}

// Generic extensionless fallback: /login -> login.php, /admin/users -> admin/users.php
$candidate = $root . rtrim($uri, '/') . '.php';
if ($uri !== '/' && !file_exists($root . $uri) && is_file($candidate)) {
    require $candidate;
    return true;
}

// Static assets and the real homepage: let the built-in server handle those normally.
if ($uri === '/' || preg_match('#\.(css|js|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|map)$#i', $uri) || file_exists($root . $uri)) {
    return false;
}

// Anything else genuinely doesn't exist — mirror the production ErrorDocument 404 behavior
// instead of relying on php -S's own undocumented fallback (which serves index.php for
// unmatched paths when no router is used, masking real 404s during local testing).
require $root . '/404.php';
return true;
