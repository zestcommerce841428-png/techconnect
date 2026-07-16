<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Security — Admin';
require __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/login_alert.php'; // device_label()

if ($admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Admins only.');
}

$pdo = db();

/** Scalar query that tolerates a missing table (mid-migration) instead of 500ing. */
function sec_val(string $sql, array $params = [], $default = 0)
{
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $v = $stmt->fetchColumn();
        return $v === false ? $default : $v;
    } catch (Throwable $e) {
        return $default;
    }
}
function sec_rows(string $sql, array $params = []): array
{
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

$bfReady = (bool) sec_val("SELECT 1 FROM information_schema.tables
                           WHERE table_schema = DATABASE() AND table_name = 'failed_logins'", [], 0);

// ---- Actions -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'unlock' && ($uid = (int) ($_POST['user_id'] ?? 0))) {
        $pdo->prepare('UPDATE users SET locked_until = NULL WHERE id = ?')->execute([$uid]);
        $pdo->prepare("DELETE FROM failed_logins WHERE user_id = ? AND reason <> 'locked'")->execute([$uid]);
        audit_log($admin['id'], 'account_unlocked', 'user', $uid);
        flash_set('success', 'Account unlocked.');
    } elseif ($action === 'block_ip' && ($ip = trim($_POST['ip'] ?? '')) !== '') {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            flash_set('error', 'That is not a valid IP address.');
        } else {
            $pdo->prepare('INSERT IGNORE INTO ip_blocks (ip_address, reason, blocked_by) VALUES (?, ?, ?)')
                ->execute([$ip, 'Blocked from security dashboard', $admin['id']]);
            audit_log($admin['id'], 'ip_blocked', 'ip', null, $ip);
            flash_set('success', 'IP ' . $ip . ' blocked.');
        }
    }
    redirect('/admin/security');
}

// ---- Metrics -----------------------------------------------------------------
$failed24 = (int) sec_val('SELECT COUNT(*) FROM failed_logins WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
$failed1h = (int) sec_val('SELECT COUNT(*) FROM failed_logins WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)');
$lockedNow = (int) sec_val('SELECT COUNT(*) FROM users WHERE locked_until > NOW()');
$blockedIps = (int) sec_val('SELECT COUNT(*) FROM ip_blocks');
$logins24 = (int) sec_val('SELECT COUNT(*) FROM login_history WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
$twoFaUsers = (int) sec_val('SELECT COUNT(*) FROM users WHERE totp_enabled = 1');
$totalUsers = max(1, (int) sec_val('SELECT COUNT(*) FROM users'));

$lockedAccounts = sec_rows('SELECT id, username, email, locked_until FROM users WHERE locked_until > NOW() ORDER BY locked_until DESC LIMIT 20');

// Attacking IPs: many failures, few successes.
$topIps = sec_rows("SELECT ip_address, COUNT(*) n, COUNT(DISTINCT identity) targets, MAX(created_at) last_seen
                    FROM failed_logins
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND ip_address IS NOT NULL
                    GROUP BY ip_address HAVING n >= 3 ORDER BY n DESC LIMIT 10");

$targetedAccounts = sec_rows("SELECT identity, COUNT(*) n, COUNT(DISTINCT ip_address) ips
                              FROM failed_logins
                              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                              GROUP BY identity HAVING n >= 3 ORDER BY n DESC LIMIT 10");

$recentFailures = sec_rows('SELECT identity, ip_address, user_agent, reason, created_at
                            FROM failed_logins ORDER BY id DESC LIMIT 25');

$blockedList = sec_rows('SELECT ip_address, reason, created_at FROM ip_blocks ORDER BY created_at DESC LIMIT 10');

// 24h failure sparkline.
$byHour = array_fill(0, 24, 0);
foreach (sec_rows("SELECT HOUR(created_at) h, COUNT(*) n FROM failed_logins
                   WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY h") as $r) {
    $byHour[(int) $r['h']] = (int) $r['n'];
}
$peak = max(1, max($byHour));

$reasonStyles = [
    'bad_password' => 'bg-amber-100 text-amber-800',
    'unknown_user' => 'bg-slate-100 text-slate-600',
    'bad_2fa' => 'bg-orange-100 text-orange-800',
    'bad_otp' => 'bg-orange-100 text-orange-800',
    'locked' => 'bg-red-100 text-red-800',
];
?>
<h1 class="text-2xl font-bold mb-1">🛡️ Security</h1>
<p class="text-sm text-slate-500 mb-5">Failed logins, account lockouts and IP activity across the last 24 hours.</p>

<?php if (!$bfReady): ?>
  <div class="bg-white border rounded-lg p-6 mb-6">
    <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded px-3 py-2">
      Brute-force monitoring is not active yet — run <code>migrations/032_brute_force.sql</code> in phpMyAdmin.
      Until then failed logins are only written to the file log and accounts are not locked.
    </p>
  </div>
<?php endif; ?>

<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
  <?php foreach ([
      ['Failed logins (24h)', $failed24, $failed24 > 50 ? 'text-red-600' : 'text-slate-900', '🚫'],
      ['Failed (last hour)', $failed1h, $failed1h > 20 ? 'text-red-600' : 'text-slate-900', '⏱️'],
      ['Locked accounts', $lockedNow, $lockedNow > 0 ? 'text-amber-600' : 'text-slate-900', '🔒'],
      ['Blocked IPs', $blockedIps, 'text-slate-900', '⛔'],
      ['Successful logins (24h)', $logins24, 'text-slate-900', '✅'],
  ] as [$label, $value, $tone, $icon]): ?>
    <div class="bg-white border rounded-lg p-4">
      <div class="flex items-center justify-between">
        <span class="text-xs text-slate-500"><?= e($label) ?></span><span><?= $icon ?></span>
      </div>
      <div class="text-2xl font-bold mt-1 <?= $tone ?>"><?= number_format($value) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="bg-white border rounded-lg p-4 mb-6">
  <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
    <h2 class="text-sm font-semibold">Failed logins by hour (last 24h)</h2>
    <span class="text-xs text-slate-400">2FA adoption: <?= round($twoFaUsers / $totalUsers * 100) ?>% of users</span>
  </div>
  <div class="flex items-end gap-1 h-24" role="img" aria-label="Failed logins per hour over the last 24 hours">
    <?php for ($h = 0; $h < 24; $h++): $v = $byHour[$h]; ?>
      <div class="flex-1 group relative flex items-end h-full">
        <div class="w-full rounded-t-[3px] <?= $v > 0 ? 'bg-red-500' : 'bg-slate-200' ?>"
             style="height:<?= $v > 0 ? max(6, round($v / $peak * 100)) : 2 ?>%"></div>
        <div class="hidden group-hover:block absolute bottom-full mb-1 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10">
          <?= sprintf('%02d:00', $h) ?> — <?= $v ?> failed
        </div>
      </div>
    <?php endfor; ?>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
  <div class="bg-white border rounded-lg">
    <div class="px-4 py-3 border-b"><h2 class="text-sm font-semibold">🔒 Locked accounts</h2></div>
    <div class="divide-y">
      <?php foreach ($lockedAccounts as $u): $mins = max(1, (int) ceil((strtotime($u['locked_until']) - time()) / 60)); ?>
        <div class="p-3 flex items-center justify-between gap-2 text-sm">
          <div class="min-w-0">
            <div class="font-medium truncate"><?= e($u['username']) ?></div>
            <div class="text-xs text-slate-500">unlocks in <?= $mins ?> min</div>
          </div>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="unlock">
            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
            <button class="text-xs bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded">Unlock now</button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if (!$lockedAccounts): ?><div class="p-6 text-center text-sm text-slate-400">No locked accounts.</div><?php endif; ?>
    </div>
  </div>

  <div class="bg-white border rounded-lg">
    <div class="px-4 py-3 border-b"><h2 class="text-sm font-semibold">⚠️ Suspicious IPs (24h)</h2></div>
    <div class="divide-y">
      <?php foreach ($topIps as $r): ?>
        <div class="p-3 flex items-center justify-between gap-2 text-sm">
          <div class="min-w-0">
            <div class="font-mono text-xs truncate"><?= e($r['ip_address']) ?></div>
            <div class="text-xs text-slate-500">
              <?= (int) $r['n'] ?> failures · <?= (int) $r['targets'] ?> account<?= (int) $r['targets'] === 1 ? '' : 's' ?> targeted
              <?php if ((int) $r['targets'] >= 3): ?><span class="text-red-600 font-medium">· credential stuffing</span><?php endif; ?>
            </div>
          </div>
          <form method="post" onsubmit="return confirm('Block <?= e($r['ip_address']) ?> from the entire site?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="block_ip">
            <input type="hidden" name="ip" value="<?= e($r['ip_address']) ?>">
            <button class="text-xs border border-red-200 text-red-600 hover:bg-red-50 px-3 py-1.5 rounded">Block IP</button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if (!$topIps): ?><div class="p-6 text-center text-sm text-slate-400">No suspicious IP activity.</div><?php endif; ?>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
  <div class="bg-white border rounded-lg">
    <div class="px-4 py-3 border-b"><h2 class="text-sm font-semibold">🎯 Most-targeted accounts (24h)</h2></div>
    <div class="divide-y">
      <?php foreach ($targetedAccounts as $t): ?>
        <div class="p-3 text-sm flex items-center justify-between gap-2">
          <span class="truncate"><?= e($t['identity']) ?></span>
          <span class="text-xs text-slate-500 shrink-0"><?= (int) $t['n'] ?> tries from <?= (int) $t['ips'] ?> IP<?= (int) $t['ips'] === 1 ? '' : 's' ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$targetedAccounts): ?><div class="p-6 text-center text-sm text-slate-400">No targeted accounts.</div><?php endif; ?>
    </div>
  </div>

  <div class="bg-white border rounded-lg">
    <div class="px-4 py-3 border-b flex items-center justify-between">
      <h2 class="text-sm font-semibold">Recent failed attempts</h2>
      <a href="/admin/ip_blocks" class="text-xs text-indigo-600">Manage IP blocks</a>
    </div>
    <div class="divide-y max-h-96 overflow-y-auto">
      <?php foreach ($recentFailures as $f): ?>
        <div class="p-3 text-sm">
          <div class="flex items-center justify-between gap-2">
            <span class="truncate font-medium"><?= e($f['identity']) ?></span>
            <span class="text-[10px] px-1.5 py-0.5 rounded <?= $reasonStyles[$f['reason']] ?? 'bg-slate-100' ?>"><?= e(str_replace('_', ' ', $f['reason'])) ?></span>
          </div>
          <div class="text-xs text-slate-500 mt-0.5">
            <?= e($f['ip_address'] ?? '—') ?> · <?= e(device_label((string) $f['user_agent'])) ?> · <?= time_ago($f['created_at']) ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$recentFailures): ?><div class="p-6 text-center text-sm text-slate-400">No failed attempts recorded.</div><?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
