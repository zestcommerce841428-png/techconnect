<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Blog — Admin';
require __DIR__ . '/includes/admin_header.php';

require_once __DIR__ . '/../includes/permissions.php';
require_permission($admin, 'manage_blog');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'duplicate') {
        require_once __DIR__ . '/../includes/audit.php';
        $srcId = (int) $_POST['id'];
        $src = $pdo->prepare('SELECT * FROM blog_posts WHERE id = ?');
        $src->execute([$srcId]);
        $orig = $src->fetch();
        if ($orig) {
            // Always lands as a draft with a fresh slug — duplicating a live post
            // must never publish a second copy or collide on the unique slug.
            $newTitle = mb_substr($orig['title'] . ' (copy)', 0, 200);
            $pdo->prepare('INSERT INTO blog_posts (author_id, blog_category_id, title, slug, excerpt, body, cover_image, meta_description, status, published_at, publish_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, "draft", NULL, NULL)')
                ->execute([
                    $admin['id'], $orig['blog_category_id'], $newTitle, unique_slug('blog_posts', $newTitle),
                    $orig['excerpt'], $orig['body'], $orig['cover_image'], $orig['meta_description'],
                ]);
            $newId = (int) $pdo->lastInsertId();
            audit_log($admin['id'], 'blog_post_duplicated', 'blog_post', $newId, 'from #' . $srcId);
            flash_set('success', 'Post duplicated as a draft — edit and publish when ready.');
            redirect('/admin/blog?id=' . $newId);
        }
        flash_set('error', 'Post not found.');
        redirect('/admin/blog');
    }

    if ($action === 'delete') {
        require_once __DIR__ . '/../includes/audit.php';
        $id = (int) $_POST['id'];
        if (soft_deletes_ready()) {
            // Soft delete: move to Trash, recoverable from /admin/trash.
            $pdo->prepare('UPDATE blog_posts SET deleted_at = NOW(), deleted_by = ? WHERE id = ? AND deleted_at IS NULL')
                ->execute([$admin['id'], $id]);
            audit_log($admin['id'], 'blog_post_trashed', 'blog_post', $id);
            flash_set('success', 'Post moved to Trash — restore it any time from Trash.');
            redirect('/admin/trash?from=blog');
        }
        // Pre-migration 031: keep the original hard-delete behaviour rather than
        // erroring on a column that does not exist yet.
        $pdo->prepare('DELETE FROM blog_posts WHERE id = ?')->execute([$id]);
        audit_log($admin['id'], 'blog_post_deleted', 'blog_post', $id);
        flash_set('success', 'Post deleted.');
        redirect('/admin/blog');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $coverImage = trim($_POST['cover_image'] ?? '');
    $metaDescription = trim($_POST['meta_description'] ?? '');
    $categoryId = (int) ($_POST['blog_category_id'] ?? 0) ?: null;
    $status = $_POST['status'] === 'published' ? 'published' : 'draft';
    $scheduleAt = trim($_POST['schedule_at'] ?? '');
    $scheduledTimestamp = null;
    if ($status === 'draft' && $scheduleAt !== '' && strtotime($scheduleAt) > time()) {
        $scheduledTimestamp = date('Y-m-d H:i:s', strtotime($scheduleAt));
    }

    if (mb_strlen($title) < 3 || mb_strlen($body) < 20) {
        flash_set('error', 'Title and body are required.');
        redirect('/admin/blog' . ($id ? '?id=' . $id : ''));
    }

    if ($id) {
        $stmt = $pdo->prepare('SELECT status, published_at FROM blog_posts WHERE id = ?');
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        $publishedAt = $existing['published_at'];
        if ($status === 'published' && !$publishedAt) {
            $publishedAt = date('Y-m-d H:i:s');
        }
        $pdo->prepare('UPDATE blog_posts SET title=?, excerpt=?, body=?, cover_image=?, meta_description=?, blog_category_id=?, status=?, published_at=?, publish_at=? WHERE id=?')
            ->execute([$title, $excerpt ?: null, $body, $coverImage ?: null, $metaDescription ?: null, $categoryId, $status, $publishedAt, $scheduledTimestamp, $id]);
        flash_set('success', $scheduledTimestamp ? 'Post scheduled for ' . date('M j, Y g:i A', strtotime($scheduledTimestamp)) . '.' : 'Post updated.');
    } else {
        $slug = unique_slug('blog_posts', $title);
        $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
        $pdo->prepare('INSERT INTO blog_posts (author_id, blog_category_id, title, slug, excerpt, body, cover_image, meta_description, status, published_at, publish_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$admin['id'], $categoryId, $title, $slug, $excerpt ?: null, $body, $coverImage ?: null, $metaDescription ?: null, $status, $publishedAt, $scheduledTimestamp]);
        flash_set('success', $scheduledTimestamp ? 'Post scheduled for ' . date('M j, Y g:i A', strtotime($scheduledTimestamp)) . '.' : 'Post created.');
    }
    redirect('/admin/blog');
}

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$editing = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch();
}

$blogCategories = $pdo->query('SELECT id, name FROM blog_categories ORDER BY name')->fetchAll();
$posts = $pdo->query(
    'SELECT p.id, p.title, p.slug, p.status, p.published_at, p.publish_at, u.username FROM blog_posts p JOIN users u ON u.id = p.author_id WHERE 1=1' . sd_filter('p') . ' ORDER BY p.created_at DESC LIMIT 50'
)->fetchAll();
?>
<h1 class="text-2xl font-bold mb-4">Blog</h1>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2">
    <div class="bg-white border rounded-lg p-4">
      <h2 class="font-semibold mb-3"><?= $editing ? 'Edit: ' . e($editing['title']) : 'New post' ?></h2>
      <form method="post" class="space-y-3">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $editing['id'] ?? '' ?>">
        <div>
          <label class="block text-sm font-medium mb-1">Title</label>
          <input type="text" name="title" required class="w-full border rounded px-3 py-2 text-sm" value="<?= e($editing['title'] ?? '') ?>">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Excerpt</label>
          <input type="text" name="excerpt" maxlength="300" class="w-full border rounded px-3 py-2 text-sm" value="<?= e($editing['excerpt'] ?? '') ?>">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Body (Markdown)</label>
          <textarea name="body" required rows="12" class="w-full border rounded px-3 py-2 text-sm font-mono"><?= e($editing['body'] ?? '') ?></textarea>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium mb-1">Cover image URL</label>
            <input type="text" name="cover_image" class="w-full border rounded px-3 py-2 text-sm" value="<?= e($editing['cover_image'] ?? '') ?>">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Category</label>
            <select name="blog_category_id" class="w-full border rounded px-3 py-2 text-sm">
              <option value="">None</option>
              <?php foreach ($blogCategories as $bc): ?>
                <option value="<?= $bc['id'] ?>" <?= ($editing['blog_category_id'] ?? null) == $bc['id'] ? 'selected' : '' ?>><?= e($bc['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Meta description (SEO)</label>
          <input type="text" name="meta_description" maxlength="255" class="w-full border rounded px-3 py-2 text-sm" value="<?= e($editing['meta_description'] ?? '') ?>">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Status</label>
          <select name="status" class="border rounded px-3 py-2 text-sm">
            <option value="draft" <?= ($editing['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="published" <?= ($editing['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
          </select>
          <div>
            <label class="block text-xs text-slate-500 mb-1">Schedule for later (optional, only applies while Draft)</label>
            <input type="datetime-local" name="schedule_at" value="<?= !empty($editing['publish_at']) ? e(date('Y-m-d\TH:i', strtotime($editing['publish_at']))) : '' ?>" class="border rounded px-3 py-2 text-sm">
          </div>
        </div>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Save post</button>
        <?php if ($editing): ?><a href="/admin/blog" class="text-sm text-slate-500 hover:underline ml-2">Cancel</a><?php endif; ?>
      </form>
    </div>
  </div>

  <div class="lg:col-span-1">
    <div class="bg-white border rounded-lg p-4">
      <h2 class="font-semibold mb-3 text-sm">All posts</h2>
      <div class="space-y-2">
        <?php foreach ($posts as $p): ?>
          <div class="flex items-center justify-between text-sm border-b pb-1">
            <div class="min-w-0">
              <a href="/admin/blog?id=<?= $p['id'] ?>" class="text-indigo-600 hover:underline"><?= e($p['title']) ?></a>
              <div class="text-xs text-slate-400"><?= $p['status'] === 'draft' && $p['publish_at'] ? 'scheduled for ' . date('M j, g:i A', strtotime($p['publish_at'])) : e($p['status']) ?> &middot; by <?= e($p['username']) ?></div>
              <div class="flex gap-2 mt-0.5">
                <a href="/blog/<?= e($p['slug']) ?>" target="_blank" rel="noopener" class="text-[11px] text-slate-500 hover:text-indigo-600">
                  <?= $p['status'] === 'published' ? 'View' : '👁 Preview' ?>
                </a>
                <form method="post" class="inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="duplicate">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <button class="text-[11px] text-slate-500 hover:text-indigo-600">⧉ Duplicate</button>
                </form>
              </div>
            </div>
            <form method="post" onsubmit="return confirm('Delete this post?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button class="text-xs text-red-600 hover:underline">Delete</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
