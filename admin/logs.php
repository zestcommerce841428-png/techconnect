<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Logs — Admin';
require __DIR__ . '/includes/admin_header.php';

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Admins only.');
}

/**
 * Reads the JSON-lines application log.
 *
 * The logs/ directory is (correctly) denied over HTTP, so until now the only way
 * to read a production error was to open an FTP client — which means in practice
 * nobody reads them, and errors go unnoticed. This surfaces them in the panel.
 *
 * Files are read from the end and capped: a log can grow to tens of MB, and
 * loading that into memory on shared hosting would exhaust the limit and take
 * the page down with the very error you came to investigate.
 */
const LOG_DIR = __DIR__ . '/../logs';
const LOG_MAX_BYTES = 2 * 1024 * 1024; // only ever tail the last 2MB
const LOG_MAX_LINES = 500;

/** Available log dates, newest first. */
function log_dates(): array
{
    $dates = [];
    foreach (glob(LOG_DIR . '/app-*.log') ?: [] as $path) {
        if (preg_match('/app-(\d{4}-\d{2}-\d{2})\.log$/', $path, $m)) {
            $dates[] = $m[1];
        }
    }
    rsort($dates);
    return $dates;
}

/** Tails a log file without loading the whole thing into memory. */
function read_log(string $date): array
{
    // Date is regex-validated by the caller; rebuild the path rather than
    // accepting anything path-like from the query string.
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return [];
    }
    $path = LOG_DIR . '/app-' . $date . '.log';
    if (!is_file($path)) {
        return [];
    }
    $size = filesize($path);
    $fh = @fopen($path, 'rb');
    if (!$fh) return [];
    if ($size > LOG_MAX_BYTES) {
        fseek($fh, -LOG_MAX_BYTES, SEEK_END);
        fgets($fh); // discard the partial first line
    }
    $lines = [];
    while (($line = fgets($fh)) !== false) {
        $row = json_decode(trim($line), true);
        if (is_array($row)) {
            $lines[] = $row;
        }
    }
    fclose($fh);
    return array_slice(array_reverse($lines), 0, LOG_MAX_LINES);
}

$dates = log_dates();
$date = in_array($_GET['date'] ?? '', $dates, true) ? $_GET['date'] : ($dates[0] ?? date('Y-m-d'));
$level = in_array($_GET['level'] ?? '', ['error', 'warn', 'info', 'debug'], true) ? $_GET['level'] : '';
$q = trim($_GET['q'] ?? '');

$entries = read_log($date);
$counts = ['error' => 0, 'warn' => 0, 'info' => 0, 'debug' => 0];
foreach ($entries as $e) {
    $lv = $e['level'] ?? 'info';
    if (isset($counts[$lv])) $counts[$lv]++;
}
if ($level !== '') {
    $entries = array_values(array_filter($entries, fn($e) => ($e['level'] ?? '') === $level));
}
if ($q !== '') {
    $needle = mb_strtolower($q);
    $entries = array_values(array_filter($entries, function ($e) use ($needle) {
        return str_contains(mb_strtolower(json_encode($e)), $needle);
    }));
}

$levelStyles = [
    'error' => 'bg-red-100 text-red-800',
    'warn' => 'bg-amber-100 text-amber-800',
    'info' => 'bg-slate-100 text-slate-600',
    'debug' => 'bg-slate-100 text-slate-400',
];
?>
<h1 class="text-2xl font-bold mb-1">📋 Application logs</h1>
<p class="text-sm text-slate-500 mb-5">
  Structured logs written by the app. The <code>logs/</code> folder is blocked over HTTP, so this is the only
  way to read them without FTP. Showing the most recent <?= LOG_MAX_LINES ?> entries per day.
</p>

<?php if (!$dates): ?>
  <div class="bg-white border rounded-lg p-8 text-center">
    <div class="text-3xl mb-2">🌱</div>
    <p class="font-medium">No log files yet</p>
    <p class="text-sm text-slate-500 mt-1">Entries appear here when the app logs a warning or error — for example a failed login.</p>
  </div>
<?php else: ?>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
  <?php foreach (['error' => '🔴', 'warn' => '🟠', 'info' => '🔵', 'debug' => '⚪'] as $lv => $icon): ?>
    <a href="?date=<?= e($date) ?><?= $level === $lv ? '' : '&level=' . $lv ?>"
       class="bg-white border rounded-lg p-3 <?= $level === $lv ? 'ring-2 ring-indigo-400' : 'hover:border-indigo-300' ?>">
      <div class="text-xs text-slate-500"><?= $icon ?> <?= ucfirst($lv) ?></div>
      <div class="text-xl font-bold <?= $lv === 'error' && $counts[$lv] > 0 ? 'text-red-600' : '' ?>"><?= $counts[$lv] ?></div>
    </a>
  <?php endforeach; ?>
</div>

<form method="get" class="bg-white border rounded-lg p-3 mb-4 flex flex-wrap items-end gap-2">
  <div>
    <label for="ldate" class="block text-xs font-medium mb-1">Date</label>
    <select id="ldate" name="date" class="border rounded px-2 py-2 text-sm">
      <?php foreach ($dates as $d): ?>
        <option value="<?= e($d) ?>" <?= $d === $date ? 'selected' : '' ?>><?= e($d) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label for="llevel" class="block text-xs font-medium mb-1">Level</label>
    <select id="llevel" name="level" class="border rounded px-2 py-2 text-sm">
      <option value="">All</option>
      <?php foreach (['error', 'warn', 'info', 'debug'] as $lv): ?>
        <option value="<?= $lv ?>" <?= $level === $lv ? 'selected' : '' ?>><?= ucfirst($lv) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="flex-1 min-w-[160px]">
    <label for="lq" class="block text-xs font-medium mb-1">Search</label>
    <input id="lq" type="search" name="q" value="<?= e($q) ?>" placeholder="message, IP, URI…" class="w-full border rounded px-3 py-2 text-sm">
  </div>
  <button class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Apply</button>
  <?php if ($level || $q): ?><a href="?date=<?= e($date) ?>" class="text-sm text-slate-500 px-2">Clear</a><?php endif; ?>
</form>

<div class="bg-white border rounded-lg divide-y">
  <?php foreach ($entries as $en): $ctx = $en['context'] ?? []; ?>
    <div class="p-3 text-sm">
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-[10px] px-1.5 py-0.5 rounded <?= $levelStyles[$en['level'] ?? 'info'] ?? '' ?>"><?= e($en['level'] ?? 'info') ?></span>
        <span class="font-medium"><?= e($en['message'] ?? '') ?></span>
        <span class="text-xs text-slate-400 ml-auto"><?= e(isset($en['ts']) ? date('H:i:s', strtotime($en['ts'])) : '') ?></span>
      </div>
      <div class="text-xs text-slate-500 mt-1 flex flex-wrap gap-x-3">
        <?php if (!empty($en['uri'])): ?><span><?= e($en['uri']) ?></span><?php endif; ?>
        <?php if (!empty($en['ip'])): ?><span class="font-mono"><?= e($en['ip']) ?></span><?php endif; ?>
        <?php if (!empty($en['user_id'])): ?><span>user #<?= (int) $en['user_id'] ?></span><?php endif; ?>
      </div>
      <?php if ($ctx && $ctx !== []): ?>
        <pre class="mt-1.5 text-[11px] bg-slate-50 border rounded p-2 overflow-x-auto"><?= e(json_encode($ctx, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$entries): ?>
    <div class="p-8 text-center text-sm text-slate-500">No entries match these filters for <?= e($date) ?>.</div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
