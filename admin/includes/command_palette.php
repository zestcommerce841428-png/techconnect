<?php
/**
 * Admin command palette (Ctrl/Cmd+K).
 *
 * With 40+ admin tools the sidebar is a scroll-hunt. This gives keyboard-first
 * navigation: fuzzy subsequence matching (typing "usm" finds "User Management"),
 * arrow-key selection, Enter to go. Rendered from the same RBAC rules as the
 * sidebar, so a moderator never sees an admin-only destination.
 */
$isAdminRole = ($admin['role'] ?? '') === 'admin';

// [label, url, keywords, adminOnly]
$paletteItems = [
    ['Dashboard', '/admin/dashboard', 'home overview stats', false],
    ['Users', '/admin/users', 'members accounts roles ban', false],
    ['Moderation', '/admin/moderation', 'reports flags queue abuse', false],
    ['Feedback', '/admin/feedback', 'bugs reports ideas', false],
    ['Import Questions', '/admin/import_questions', 'csv bulk seed content', true],
    ['Merge Questions', '/admin/merge_questions', 'duplicate combine', false],
    ['Suggested Edits', '/admin/suggested_edits', 'revisions review', false],
    ['Jobs', '/admin/jobs', 'careers board vacancies', false],
    ['Uploads', '/admin/uploads', 'media files images', false],
    ['Pages', '/admin/pages', 'cms content static', false],
    ['Blog', '/admin/blog', 'posts articles writing', false],
    ['Trash', '/admin/trash', 'deleted restore recover undo bin', false],
    ['Categories', '/admin/categories', 'topics taxonomy', false],
    ['Tags', '/admin/tags', 'labels taxonomy', false],
    ['Announcements', '/admin/announcements', 'banner notice broadcast', false],
    ['Testimonials', '/admin/testimonials', 'reviews quotes', false],
    ['Changelog', '/admin/changelog', 'releases updates', false],
    ['Roadmap', '/admin/roadmap', 'planned features votes', false],
    ['Analytics', '/admin/analytics', 'traffic charts insights', false],
    ['Experts', '/admin/experts', 'consultants approvals', false],
    ['Groups', '/admin/groups', 'communities', false],
    ['My 2FA', '/admin/security_2fa', 'two factor totp security', false],
    ['Audit Log', '/admin/audit_log', 'history who changed what', true],
    ['Security', '/admin/security', 'brute force failed logins locked attacks monitoring', true],
    ['IP Blocks', '/admin/ip_blocks', 'ban firewall security', true],
    ['Redirects', '/admin/redirects', 'urls 301 seo', true],
    ['Newsletter', '/admin/newsletter', 'email campaign subscribers', true],
    ['Site Health', '/admin/site_health', 'system checks status', true],
    ['Mod Permissions', '/admin/moderator_permissions', 'rbac roles access', true],
    ['Badges', '/admin/badges', 'achievements gamification', true],
    ['Orders', '/admin/orders', 'payments purchases', true],
    ['Payment Settings', '/admin/payment_settings', 'stripe razorpay paypal gateway', true],
    ['Currencies', '/admin/currencies', 'money exchange rates', true],
    ['Social Login', '/admin/social_login_settings', 'oauth google facebook', true],
    ['Storage', '/admin/storage_settings', 's3 r2 cloudflare aws bucket cdn uploads media', true],
    ['Integrations', '/admin/integrations', 'telegram tawk api', true],
    ['Webhooks', '/admin/webhooks', 'events callbacks', true],
    ['Expert Payouts', '/admin/expert_payouts', 'money consultants', true],
    ['Community Polls', '/admin/site_polls', 'vote survey', true],
    ['Ad Slots', '/admin/ad_slots', 'ads monetization banners', true],
    ['Branding', '/admin/site_settings', 'logo name tagline theme', true],
    ['Settings', '/admin/settings', 'configuration global options', true],
    ['View public site', '/index', 'front end home', false],
];
$paletteItems = array_values(array_filter($paletteItems, fn($i) => !$i[3] || $isAdminRole));
?>
<div id="cmdk" class="hidden fixed inset-0 z-[100]" role="dialog" aria-modal="true" aria-label="Command palette">
  <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" data-cmdk-close></div>
  <div class="relative mx-auto mt-[12vh] w-[92%] max-w-lg bg-white rounded-xl shadow-2xl overflow-hidden">
    <input id="cmdk-input" type="text" autocomplete="off" placeholder="Jump to… (try &quot;users&quot;, &quot;seo&quot;, &quot;payments&quot;)"
           class="w-full px-4 py-3.5 text-sm border-0 border-b border-slate-200 focus:outline-none focus:ring-0"
           aria-controls="cmdk-list" aria-autocomplete="list">
    <ul id="cmdk-list" class="max-h-80 overflow-y-auto py-1 text-sm" role="listbox"></ul>
    <div class="px-4 py-2 bg-slate-50 border-t text-[11px] text-slate-400 flex gap-3">
      <span><kbd class="font-sans">↑↓</kbd> navigate</span>
      <span><kbd class="font-sans">↵</kbd> open</span>
      <span><kbd class="font-sans">esc</kbd> close</span>
    </div>
  </div>
</div>

<button type="button" data-cmdk-open
        class="fixed bottom-5 right-5 z-40 md:hidden w-12 h-12 rounded-full bg-slate-900 text-white text-lg shadow-lg"
        aria-label="Open command palette">⌘</button>

<script>
(function () {
  var items = <?= json_encode(array_map(fn($i) => ['label' => $i[0], 'url' => $i[1], 'kw' => $i[2]], $paletteItems), JSON_UNESCAPED_SLASHES) ?>;
  var modal = document.getElementById('cmdk');
  var input = document.getElementById('cmdk-input');
  var list = document.getElementById('cmdk-list');
  var active = 0, shown = [];

  // Subsequence match: "usm" matches "UserManagement". Scores exact prefix
  // matches highest so the obvious answer stays at the top.
  function score(item, q) {
    if (!q) return 1;
    var hay = (item.label + ' ' + item.kw).toLowerCase();
    var label = item.label.toLowerCase();
    if (label.startsWith(q)) return 1000;
    if (label.indexOf(q) !== -1) return 500;
    if (hay.indexOf(q) !== -1) return 250;
    var qi = 0;
    for (var i = 0; i < label.length && qi < q.length; i++) {
      if (label[i] === q[qi]) qi++;
    }
    return qi === q.length ? 100 : 0;
  }

  function render() {
    var q = input.value.trim().toLowerCase();
    shown = items.map(function (it) { return { it: it, s: score(it, q) }; })
                 .filter(function (r) { return r.s > 0; })
                 .sort(function (a, b) { return b.s - a.s; })
                 .slice(0, 12)
                 .map(function (r) { return r.it; });
    if (active >= shown.length) active = 0;
    list.innerHTML = '';
    if (!shown.length) {
      list.innerHTML = '<li class="px-4 py-6 text-center text-slate-400">No matching tool</li>';
      return;
    }
    shown.forEach(function (it, i) {
      var li = document.createElement('li');
      li.setAttribute('role', 'option');
      li.setAttribute('aria-selected', i === active ? 'true' : 'false');
      li.className = 'px-4 py-2.5 cursor-pointer flex items-center justify-between ' +
                     (i === active ? 'bg-indigo-50 text-indigo-800' : 'hover:bg-slate-50');
      li.innerHTML = '<span class="font-medium"></span><span class="text-[11px] text-slate-400"></span>';
      li.children[0].textContent = it.label;
      li.children[1].textContent = it.url;
      li.addEventListener('click', function () { window.location.href = it.url; });
      list.appendChild(li);
    });
  }

  function open() {
    modal.classList.remove('hidden');
    input.value = '';
    active = 0;
    render();
    input.focus();
  }
  function close() { modal.classList.add('hidden'); }

  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      modal.classList.contains('hidden') ? open() : close();
      return;
    }
    if (modal.classList.contains('hidden')) return;
    if (e.key === 'Escape') { close(); }
    else if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, shown.length - 1); render(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); render(); }
    else if (e.key === 'Enter' && shown[active]) { e.preventDefault(); window.location.href = shown[active].url; }
  });

  input.addEventListener('input', function () { active = 0; render(); });
  document.querySelectorAll('[data-cmdk-close]').forEach(function (el) { el.addEventListener('click', close); });
  document.querySelectorAll('[data-cmdk-open]').forEach(function (el) { el.addEventListener('click', open); });
})();
</script>
