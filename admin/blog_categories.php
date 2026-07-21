<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Blog categories — Admin';
require __DIR__ . '/includes/admin_header.php';

require_once __DIR__ . '/../includes/permissions.php';
require_permission($admin, 'manage_blog');
require_once __DIR__ . '/includes/category_manager.php';
?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-2xl font-bold">Blog categories</h1>
  <a href="/admin/blog" class="text-sm text-indigo-600 hover:underline">&larr; Back to Blog</a>
</div>
<?php render_category_manager($admin, 'blog_categories', 'blog_posts', 'blog_category_id', 'blog_category', fn() => true); ?>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
