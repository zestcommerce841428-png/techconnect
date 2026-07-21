<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/markdown.php';
$pageTitle = 'Pages — Admin';
require __DIR__ . '/includes/admin_header.php';

require_once __DIR__ . '/../includes/permissions.php';
require_permission($admin, 'manage_pages');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        require_once __DIR__ . '/../includes/audit.php';
        $pid = (int) $_POST['id'];
        if (soft_deletes_ready()) {
            // Soft delete: move to Trash, recoverable from /admin/trash.
            $pdo->prepare('UPDATE pages SET deleted_at = NOW(), deleted_by = ? WHERE id = ? AND deleted_at IS NULL')
                ->execute([$admin['id'], $pid]);
            audit_log($admin['id'], 'page_trashed', 'page', $pid);
            flash_set('success', 'Page moved to Trash — restore it any time from Trash.');
            redirect('/admin/trash?from=pages');
        }
        // Pre-migration 031: keep the original hard-delete behaviour rather than
        // erroring on a column that does not exist yet.
        $pdo->prepare('DELETE FROM pages WHERE id = ?')->execute([$pid]);
        audit_log($admin['id'], 'page_deleted', 'page', $pid);
        flash_set('success', 'Page deleted.');
        redirect('/admin/pages');
    }

    if ($action === 'duplicate') {
        require_once __DIR__ . '/../includes/audit.php';
        $srcId = (int) $_POST['id'];
        $src = $pdo->prepare('SELECT * FROM pages WHERE id = ?');
        $src->execute([$srcId]);
        $orig = $src->fetch();
        if ($orig) {
            // Always lands unpublished with a fresh slug — mirrors admin/blog.php's
            // duplicate: a live page must never gain a second published copy or
            // collide on the unique slug.
            $newTitle = mb_substr($orig['title'] . ' (copy)', 0, 200);
            $catCol = page_categories_ready() ? ', page_category_id' : '';
            $catVal = page_categories_ready() ? ', ?' : '';
            $params = [$newTitle, unique_slug('pages', $newTitle), $orig['body'], $orig['meta_description'], $admin['id']];
            if (page_categories_ready()) {
                $params[] = $orig['page_category_id'] ?? null;
            }
            $pdo->prepare("INSERT INTO pages (title, slug, body, meta_description, is_published, show_in_footer, updated_by{$catCol})
                           VALUES (?, ?, ?, ?, 0, 0, ?{$catVal})")
                ->execute($params);
            $newId = (int) $pdo->lastInsertId();
            audit_log($admin['id'], 'page_duplicated', 'page', $newId, 'from #' . $srcId);
            flash_set('success', 'Page duplicated as unpublished — edit and publish when ready.');
            redirect('/admin/pages?id=' . $newId);
        }
        flash_set('error', 'Page not found.');
        redirect('/admin/pages');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $metaDescription = trim($_POST['meta_description'] ?? '');
    $isPublished = isset($_POST['is_published']) ? 1 : 0;
    $showInFooter = isset($_POST['show_in_footer']) ? 1 : 0;
    $categoryId = page_categories_ready() ? ((int) ($_POST['page_category_id'] ?? 0) ?: null) : null;

    if (mb_strlen($title) < 2 || mb_strlen($body) < 10) {
        flash_set('error', 'Title and body are required.');
        redirect('/admin/pages' . ($id ? '?id=' . $id : ''));
    }

    if ($id) {
        if (page_categories_ready()) {
            $pdo->prepare('UPDATE pages SET title=?, body=?, meta_description=?, is_published=?, show_in_footer=?, page_category_id=?, updated_by=? WHERE id=?')
                ->execute([$title, $body, $metaDescription ?: null, $isPublished, $showInFooter, $categoryId, $admin['id'], $id]);
        } else {
            $pdo->prepare('UPDATE pages SET title=?, body=?, meta_description=?, is_published=?, show_in_footer=?, updated_by=? WHERE id=?')
                ->execute([$title, $body, $metaDescription ?: null, $isPublished, $showInFooter, $admin['id'], $id]);
        }
        flash_set('success', 'Page updated.');
    } else {
        $slug = unique_slug('pages', $title);
        if (page_categories_ready()) {
            $pdo->prepare('INSERT INTO pages (slug, title, body, meta_description, is_published, show_in_footer, page_category_id, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$slug, $title, $body, $metaDescription ?: null, $isPublished, $showInFooter, $categoryId, $admin['id']]);
        } else {
            $pdo->prepare('INSERT INTO pages (slug, title, body, meta_description, is_published, show_in_footer, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$slug, $title, $body, $metaDescription ?: null, $isPublished, $showInFooter, $admin['id']]);
        }
        flash_set('success', 'Page created.');
    }
    redirect('/admin/pages');
}

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$editing = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM pages WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch();
}

$catReady = page_categories_ready();
$pageCategories = $catReady ? $pdo->query('SELECT id, name FROM page_categories ORDER BY name')->fetchAll() : [];
$pages = $pdo->query(
    'SELECT p.id, p.slug, p.title, p.is_published, p.show_in_footer, p.updated_at' . ($catReady ? ', pc.name AS category_name' : '') . '
     FROM pages p' . ($catReady ? ' LEFT JOIN page_categories pc ON pc.id = p.page_category_id' : '') . '
     WHERE 1=1' . sd_filter('p') . ' ORDER BY p.title ASC'
)->fetchAll();
?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-2xl font-bold">CMS Pages</h1>
  <a href="/admin/page_categories" class="text-sm text-indigo-600 hover:underline">Manage categories</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2">
    <div class="bg-white border rounded-lg p-4">
      <h2 class="font-semibold mb-3"><?= $editing ? 'Edit: ' . e($editing['title']) : 'New page' ?></h2>
      <form method="post" class="space-y-3">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $editing['id'] ?? '' ?>">
        <div>
          <label class="block text-sm font-medium mb-1">Title</label>
          <input type="text" name="title" required class="w-full border rounded px-3 py-2 text-sm" value="<?= e($editing['title'] ?? '') ?>">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Body (Markdown)</label>
          <textarea name="body" required rows="12" class="w-full border rounded px-3 py-2 text-sm font-mono"><?= e($editing['body'] ?? '') ?></textarea>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium mb-1">Meta description (SEO)</label>
            <input type="text" name="meta_description" maxlength="255" class="w-full border rounded px-3 py-2 text-sm" value="<?= e($editing['meta_description'] ?? '') ?>">
          </div>
          <?php if ($catReady): ?>
            <div>
              <label class="block text-sm font-medium mb-1">Category</label>
              <select name="page_category_id" class="w-full border rounded px-3 py-2 text-sm">
                <option value="">None</option>
                <?php foreach ($pageCategories as $pc): ?>
                  <option value="<?= (int) $pc['id'] ?>" <?= ($editing['page_category_id'] ?? null) == $pc['id'] ? 'selected' : '' ?>><?= e($pc['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
        </div>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" name="is_published" <?= ($editing['is_published'] ?? 1) ? 'checked' : '' ?>> Published
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" name="show_in_footer" <?= ($editing['show_in_footer'] ?? 0) ? 'checked' : '' ?>> Show in footer navigation
        </label>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm">Save page</button>
        <?php if ($editing): ?><a href="/admin/pages" class="text-sm text-slate-500 hover:underline ml-2">Cancel</a><?php endif; ?>
      </form>
    </div>
  </div>

  <div class="lg:col-span-1">
    <div class="bg-white border rounded-lg p-4">
      <h2 class="font-semibold mb-3 text-sm">All pages</h2>
      <div class="space-y-2">
        <?php foreach ($pages as $p): ?>
          <div class="flex items-center justify-between text-sm border-b pb-1">
            <div class="min-w-0">
              <a href="/admin/pages?id=<?= $p['id'] ?>" class="text-indigo-600 hover:underline"><?= e($p['title']) ?></a>
              <div class="text-xs text-slate-400">
                /page/<?= e($p['slug']) ?> <?= $p['is_published'] ? '' : '(draft)' ?>
                <?php if (!empty($p['category_name'])): ?> &middot; <?= e($p['category_name']) ?><?php endif; ?>
              </div>
              <div class="flex gap-2 mt-0.5">
                <a href="/page/<?= e($p['slug']) ?>" target="_blank" rel="noopener" class="text-[11px] text-slate-500 hover:text-indigo-600">
                  <?= $p['is_published'] ? 'View' : '👁 Preview' ?>
                </a>
                <form method="post" class="inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="duplicate">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <button class="text-[11px] text-slate-500 hover:text-indigo-600">⧉ Duplicate</button>
                </form>
              </div>
            </div>
            <form method="post" onsubmit="return confirm('Delete this page?');">
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
