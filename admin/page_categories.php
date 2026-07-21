<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Page categories — Admin';
require __DIR__ . '/includes/admin_header.php';

require_once __DIR__ . '/../includes/permissions.php';
require_permission($admin, 'manage_pages');
require_once __DIR__ . '/includes/category_manager.php';
?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-2xl font-bold">Page categories</h1>
  <a href="/admin/pages" class="text-sm text-indigo-600 hover:underline">&larr; Back to Pages</a>
</div>
<?php render_category_manager($admin, 'page_categories', 'pages', 'page_category_id', 'page_category', 'page_categories_ready'); ?>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
