<?php
// GET /api/v1/convert_currency.php?amount_cents=<int>&to=<code> — converts a base-currency
// cent amount into another enabled currency using the site's stored exchange rates.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/currency.php';
require_once __DIR__ . '/../../includes/api_auth.php';
if (!api_rate_limit_ip()) api_json(['error' => 'Rate limit exceeded'], 429);

$amountCents = (int) ($_GET['amount_cents'] ?? -1);
$to = strtoupper(trim($_GET['to'] ?? ''));
if ($amountCents < 0) api_json(['error' => 'amount_cents is required and must be >= 0'], 422);
if ($to === '' || !currency_get($to)) api_json(['error' => 'to must be a supported currency code'], 422);

api_json(['data' => [
    'from' => setting('currency', 'USD'),
    'to' => $to,
    'amount_cents' => $amountCents,
    'converted_cents' => currency_convert($amountCents, $to),
    'formatted' => format_money($amountCents, $to),
]]);
