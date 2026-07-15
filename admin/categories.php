<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Categories — Admin';
require __DIR__ . '/includes/admin_header.php';

require_once __DIR__ . '/../includes/permissions.php';
require_permission($admin, 'manage_categories');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([(int) $_POST['id']]);
        flash_set('success', 'Category deleted.');
        redirect('/admin/categories');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (mb_strlen($name) < 2) {
        flash_set('error', 'Category name is required.');
        redirect('/admin/categories');
    }

    if ($id) {
        $pdo->prepare('UPDATE categories SET name=?, description=?, icon=?, sort_order=?, is_active=? WHERE id=?')
            ->execute([$name, $description ?: null, $icon ?: null, $sortOrder, $isActive, $id]);
        flash_set('success', 'Category updated.');
    } else {
        $slug = unique_slug('categories', $name);
        $pdo->prepare('INSERT INTO categories (name, slug, description, icon, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$name, $slug, $description ?: null, $icon ?: null, $sortOrder, $isActive]);
        flash_set('success', 'Category created.');
    }
    redirect('/admin/categories');
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY sort_order ASC, name ASC')->fetchAll();
?>
<h1 class="text-2xl font-bold mb-4">Categories</h1>
<div class="bg-white border rounded-lg p-4 mb-6">
  <h2 class="font-semibold mb-3 text-sm">Add category</h2>
  <form method="post" class="grid grid-cols-2 md:grid-cols-6 gap-2 items-end">
    <?= csrf_field() ?>
    <div class="col-span-2">
      <label class="block text-xs font-medium mb-1">Name</label>
      <input type="text" name="name" required class="w-full border rounded px-2 py-1.5 text-sm">
    </div>
    <div class="col-span-2">
      <label class="block text-xs font-medium mb-1">Description</label>
      <input type="text" name="description" class="w-full border rounded px-2 py-1.5 text-sm">
    </div>
    <div>
      <label class="block text-xs font-medium mb-1">Icon (emoji)</label>
      <input type="text" name="icon" maxlength="10" class="w-full border rounded px-2 py-1.5 text-sm">
    </div>
    <div>
      <label class="block text-xs font-medium mb-1">Order</label>
      <input type="number" name="sort_order" value="0" class="w-full border rounded px-2 py-1.5 text-sm">
    </div>
    <div class="col-span-2">
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" checked> Active</label>
    </div>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded text-sm">Add</button>
  </form>
</div>

<div class="bg-white border rounded-lg overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="bg-slate-100 text-left">
      <tr><th class="p-3">Icon</th><th class="p-3">Name</th><th class="p-3">Slug</th><th class="p-3">Order</th><th class="p-3">Active</th><th class="p-3">Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($categories as $c): ?>
        <tr class="border-t">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <td class="p-2"><input type="text" name="icon" value="<?= e($c['icon'] ?? '') ?>" maxlength="10" class="w-14 border rounded px-1 py-1 text-sm"></td>
            <td class="p-2"><input type="text" name="name" value="<?= e($c['name']) ?>" class="w-full border rounded px-2 py-1 text-sm"></td>
            <td class="p-2 text-slate-400">/c/<?= e($c['slug']) ?></td>
            <td class="p-2"><input type="number" name="sort_order" value="<?= (int) $c['sort_order'] ?>" class="w-16 border rounded px-1 py-1 text-sm"></td>
            <td class="p-2"><input type="checkbox" name="is_active" <?= $c['is_active'] ? 'checked' : '' ?>></td>
            <td class="p-2 flex gap-2">
              <input type="hidden" name="description" value="<?= e($c['description'] ?? '') ?>">
              <button class="text-xs text-indigo-600 hover:underline">Save</button>
            </td>
          </form>
          <td class="p-2">
            <form method="post" onsubmit="return confirm('Delete this category?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button class="text-xs text-red-600 hover:underline">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
