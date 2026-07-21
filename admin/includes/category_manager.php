<?php
/**
 * Shared CRUD for a simple (id, name, slug) category table, used by both
 * admin/blog_categories.php and admin/page_categories.php. blog_categories
 * and page_categories are structurally identical on purpose — one template
 * instead of two files that drift the way the nav lists did earlier.
 *
 * @param string $table       'blog_categories' | 'page_categories'
 * @param string $postTable   the table that references this category
 * @param string $postFkCol   the FK column on $postTable
 * @param string $entityLabel singular label for audit_log target_type, e.g. 'blog_category'
 * @param callable $readyCheck returns bool once the schema exists — a string function name
 *                             (page_categories_ready) or a closure (blog_categories.php passes
 *                             fn() => true, since blog_categories shipped in migration 003 and
 *                             is always ready). Must be `callable`, not `string`: a Closure is
 *                             not a string, and PHP does not coerce between them.
 */
function render_category_manager(array $admin, string $table, string $postTable, string $postFkCol, string $entityLabel, callable $readyCheck): void
{
    $pdo = db();
    $ready = $readyCheck();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if (!$ready) {
            flash_set('error', 'This feature is not available yet — the required migration has not been applied.');
            redirect($_SERVER['REQUEST_URI']);
        }
        require_once __DIR__ . '/../../includes/audit.php';
        $action = $_POST['action'] ?? '';
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'delete') {
            // ON DELETE SET NULL on the FK: deleting a category un-categorises its
            // posts rather than failing or cascading them away. Confirmed by schema,
            // not assumed, before this was written as safe-by-default.
            $pdo->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$id]);
            audit_log($admin['id'], $entityLabel . '_deleted', $entityLabel, $id);
            flash_set('success', 'Category deleted. Posts that used it are now uncategorised, not deleted.');
        } else {
            $name = trim($_POST['name'] ?? '');
            if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
                flash_set('error', 'Category name must be 2-80 characters.');
                redirect($_SERVER['REQUEST_URI']);
            }
            $dup = $pdo->prepare("SELECT id FROM {$table} WHERE name = ? AND id != ?");
            $dup->execute([$name, $id]);
            if ($dup->fetchColumn()) {
                flash_set('error', 'A category with that name already exists.');
                redirect($_SERVER['REQUEST_URI']);
            }
            if ($id) {
                $pdo->prepare("UPDATE {$table} SET name = ? WHERE id = ?")->execute([$name, $id]);
                audit_log($admin['id'], $entityLabel . '_renamed', $entityLabel, $id, $name);
                flash_set('success', 'Category renamed.');
            } else {
                $slug = unique_slug($table, $name);
                $pdo->prepare("INSERT INTO {$table} (name, slug) VALUES (?, ?)")->execute([$name, $slug]);
                audit_log($admin['id'], $entityLabel . '_created', $entityLabel, (int) $pdo->lastInsertId(), $name);
                flash_set('success', 'Category created.');
            }
        }
        redirect($_SERVER['REQUEST_URI']);
    }

    $categories = [];
    if ($ready) {
        $categories = $pdo->query(
            "SELECT c.id, c.name, c.slug, COUNT(p.id) AS post_count
             FROM {$table} c LEFT JOIN {$postTable} p ON p.{$postFkCol} = c.id
             GROUP BY c.id ORDER BY c.name"
        )->fetchAll();
    }
    ?>
    <?php if (!$ready): ?>
      <div class="mb-4 rounded border border-amber-300 bg-amber-50 text-amber-800 px-4 py-3 text-sm">
        Migration 034 has not been applied yet, so categories cannot be created. Existing pages are unaffected.
      </div>
    <?php endif; ?>
    <div class="bg-white border rounded-lg p-4 max-w-lg">
      <form method="post" class="flex gap-2 mb-4">
        <?= csrf_field() ?>
        <input type="text" name="name" placeholder="New category name" required maxlength="80" class="flex-1 border rounded px-3 py-2 text-sm" <?= $ready ? '' : 'disabled' ?>>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded text-sm" <?= $ready ? '' : 'disabled' ?>>Add</button>
      </form>
      <?php if (!$categories): ?>
        <p class="text-sm text-slate-500">No categories yet.</p>
      <?php endif; ?>
      <div class="space-y-1">
        <?php foreach ($categories as $c): ?>
          <div class="flex items-center justify-between text-sm border-b pb-1.5">
            <form method="post" class="flex-1 flex items-center gap-2">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <input type="text" name="name" value="<?= e($c['name']) ?>" maxlength="80" class="flex-1 border rounded px-2 py-1 text-sm bg-transparent border-transparent hover:border-slate-200 focus:border-slate-300 focus:bg-white">
              <button type="submit" class="text-xs text-indigo-600 hover:underline shrink-0">Save</button>
            </form>
            <span class="text-xs text-slate-400 mx-2 shrink-0"><?= (int) $c['post_count'] ?></span>
            <form method="post" onsubmit="return confirm(<?= (int) $c['post_count'] > 0 ? "'This category has {$c['post_count']} item(s), which will become uncategorised. Delete anyway?'" : "'Delete this category?'" ?>);">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <button class="text-xs text-red-600 hover:underline shrink-0">Delete</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
}
