<?php
require_once __DIR__ . '/includes/auth.php';

// Section labels keyed by the sort_order blocks used in the category seeds.
function category_section(int $sortOrder): string
{
    return match (true) {
        $sortOrder < 100 => 'Popular',
        $sortOrder < 200 => 'Technology',
        $sortOrder < 300 => 'Career & Jobs',
        $sortOrder < 400 => 'Education & Exams',
        $sortOrder < 500 => 'Money & Finance',
        $sortOrder < 600 => 'Health & Wellness',
        $sortOrder < 700 => 'Lifestyle & Home',
        $sortOrder < 740 => 'India & Local',
        $sortOrder < 800 => 'Travel',
        $sortOrder < 900 => 'Entertainment & Sports',
        $sortOrder < 1000 => 'Science & Society',
        $sortOrder < 1100 => 'E-commerce & Online Business',
        $sortOrder < 1200 => 'Tools & Technology',
        $sortOrder < 1300 => 'Business Types',
        $sortOrder < 1400 => 'Courses & Careers',
        $sortOrder < 1500 => 'Banking & Investments',
        default => 'Everyday Life',
    };
}

$rows = db()->query(
    "SELECT c.*, COUNT(q.id) AS question_count
     FROM categories c
     LEFT JOIN questions q ON q.category_id = c.id AND q.status <> 'draft'
     WHERE c.is_active = 1
     GROUP BY c.id
     ORDER BY c.sort_order, c.name"
)->fetchAll();

$sections = [];
foreach ($rows as $c) {
    $sections[category_section((int) $c['sort_order'])][] = $c;
}

$pageTitle = 'Browse all topics — ' . SITE_NAME;
$pageDescription = 'Explore every topic on ' . SITE_NAME . ' — from technology and careers to exams, finance, health and everyday life.';
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-5xl mx-auto">
  <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
      <h1 class="text-2xl font-bold">Browse all topics</h1>
      <p class="text-sm text-slate-500 mt-1"><?= count($rows) ?> topics — find your area and start asking or answering.</p>
    </div>
    <label for="cat-search" class="sr-only">Search topics</label>
    <input id="cat-search" type="search" placeholder="Search topics… e.g. GST, cricket, resume"
           class="w-full sm:w-72 border rounded-lg px-3 py-2 text-sm" autocomplete="off">
  </div>

  <?php foreach ($sections as $label => $cats): ?>
    <section data-cat-section class="mb-6">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-2"><?= e($label) ?></h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
        <?php foreach ($cats as $c): ?>
          <a href="/c/<?= e($c['slug']) ?>" data-cat-card data-cat-name="<?= e(mb_strtolower($c['name'] . ' ' . ($c['description'] ?? ''))) ?>"
             class="flex items-start gap-3 bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-3 hover:border-indigo-400 hover:shadow-sm transition">
            <span class="text-xl leading-none mt-0.5"><?= e($c['icon'] ?? '📁') ?></span>
            <span class="min-w-0">
              <span class="block text-sm font-medium truncate"><?= e($c['name']) ?></span>
              <span class="block text-xs text-slate-500 truncate"><?= e($c['description'] ?? '') ?></span>
              <?php if ((int) $c['question_count'] > 0): ?>
                <span class="block text-xs text-indigo-600 mt-0.5"><?= (int) $c['question_count'] ?> question<?= (int) $c['question_count'] === 1 ? '' : 's' ?></span>
              <?php endif; ?>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <p id="cat-no-results" class="hidden text-sm text-slate-500 py-8 text-center">No topics match your search. <a href="/ask" class="text-indigo-600 hover:underline">Ask in General Discussion</a> instead.</p>
</div>

<script>
(function () {
  var input = document.getElementById('cat-search');
  if (!input) return;
  var cards = Array.prototype.slice.call(document.querySelectorAll('[data-cat-card]'));
  var sections = Array.prototype.slice.call(document.querySelectorAll('[data-cat-section]'));
  var empty = document.getElementById('cat-no-results');
  input.addEventListener('input', function () {
    var q = input.value.trim().toLowerCase();
    var any = false;
    cards.forEach(function (card) {
      var show = !q || card.getAttribute('data-cat-name').indexOf(q) !== -1;
      card.classList.toggle('hidden', !show);
      if (show) any = true;
    });
    sections.forEach(function (sec) {
      var visible = sec.querySelector('[data-cat-card]:not(.hidden)');
      sec.classList.toggle('hidden', !visible);
    });
    empty.classList.toggle('hidden', any);
  });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
