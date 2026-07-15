<?php
/**
 * Display-currency conversion layer. All money is still stored, charged, and
 * settled in the site's single base currency (site_settings.currency) — real
 * multi-currency *charging* would require per-gateway settlement currency
 * support, which is out of scope here. This layer only affects what price a
 * visitor *sees*; checkout always shows the base-currency amount actually charged.
 */
function currency_list(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = db()->query('SELECT * FROM currencies WHERE is_enabled = 1 ORDER BY code')->fetchAll();
    }
    return $cache;
}

function currency_get(string $code): ?array
{
    foreach (currency_list() as $c) {
        if ($c['code'] === strtoupper($code)) return $c;
    }
    return null;
}

/** The visitor's preferred display currency: logged-in preference, else cookie, else the site base currency. */
function display_currency(): string
{
    $base = setting('currency', 'USD');
    $user = function_exists('current_user') ? current_user() : null;
    if ($user && !empty($user['display_currency']) && currency_get($user['display_currency'])) {
        return $user['display_currency'];
    }
    if (!empty($_COOKIE['display_currency']) && currency_get($_COOKIE['display_currency'])) {
        return strtoupper($_COOKIE['display_currency']);
    }
    return $base;
}

/** Converts a base-currency cent amount into the target currency's cent amount. */
function currency_convert(int $baseCents, string $toCode): int
{
    $base = setting('currency', 'USD');
    if ($toCode === $base) return $baseCents;
    $target = currency_get($toCode);
    if (!$target) return $baseCents;
    return (int) round($baseCents * (float) $target['rate_to_base']);
}

/** Formats a base-currency cent amount in the visitor's display currency, e.g. "€45.00". */
function format_money(int $baseCents, ?string $toCode = null): string
{
    $toCode = $toCode ?? display_currency();
    $target = currency_get($toCode) ?? ['symbol' => '', 'code' => setting('currency', 'USD')];
    $converted = currency_convert($baseCents, $toCode);
    $decimals = $toCode === 'JPY' ? 0 : 2;
    return $target['symbol'] . number_format($converted / 100, $decimals);
}
