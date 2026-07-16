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
        $sortOrder < 1600 => 'Everyday Life',
        $sortOrder < 1700 => 'Cities',
        $sortOrder < 1800 => 'Programming & Frameworks',
        $sortOrder < 1850 => 'School & College Subjects',
        $sortOrder < 1900 => 'Health Specialities',
        $sortOrder < 1950 => 'Food & Cooking',
        $sortOrder < 2000 => 'Sports',
        $sortOrder < 2100 => 'Entertainment',
        $sortOrder < 2200 => 'Career Fields',
        $sortOrder < 2300 => 'Home Services',
        $sortOrder < 2400 => 'Community & Society',
        $sortOrder < 2500 => 'Selling & Online Business',
        $sortOrder < 2600 => 'Indian States',
        $sortOrder < 2700 => 'Countries & Going Abroad',
        $sortOrder < 2800 => 'Tools & Platforms',
        $sortOrder < 2900 => 'Hobbies & Crafts',
        $sortOrder < 3000 => 'Pets & Animals',
        $sortOrder < 3100 => 'Research & Study Abroad',
        $sortOrder < 3200 => 'Vehicles',
        $sortOrder < 3300 => 'Money — Deeper Topics',
        $sortOrder < 3400 => 'Faith & Festivals',
        $sortOrder < 3500 => 'Learning Languages',
        $sortOrder < 3600 => 'Farming & Agri-business',
        $sortOrder < 3700 => 'Relationships & Life Stages',
        $sortOrder < 3800 => 'Creator Economy',
        $sortOrder < 3900 => 'Documents & Civic Help',
        $sortOrder < 4000 => 'Knowledge & Curiosity',
        default => 'Small Business Ideas',
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
$activeCount = 0;
foreach ($rows as $c) {
    $sections[category_section((int) $c['sort_order'])][] = $c;
    if ((int) $c['question_count'] > 0) $activeCount++;
}

$pageTitle = 'Browse all topics — ' . SITE_NAME;
$pageDescription = 'Explore every topic on ' . SITE_NAME . ' — from technology and careers to exams, finance, health and everyday life.';
require __DIR__ . '/includes/header.php';
?>
<style>
/* Scoped card styles. With 700+ cards, repeating ~250 bytes of utility classes
   per card added ~180KB to the page; short class names keep the DOM light.
   Page-local on purpose — no global stylesheet rebuild needed. */
.cg { display: grid; grid-template-columns: 1fr; gap: .5rem; }
@media (min-width: 640px) { .cg { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 1024px) { .cg { grid-template-columns: repeat(3, 1fr); } }
.cc { display: flex; align-items: flex-start; gap: .75rem; background: #fff; border: 1px solid #e2e8f0;
      border-radius: .5rem; padding: .75rem; transition: border-color .15s, box-shadow .15s; }
.cc:hover { border-color: #818cf8; box-shadow: 0 1px 2px rgba(0,0,0,.05); }
.ci { font-size: 1.25rem; line-height: 1; margin-top: .125rem; }
.cb { min-width: 0; }
.cn, .cd, .cq { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cn { font-size: .875rem; font-weight: 500; }
.cd { font-size: .75rem; color: #64748b; }
.cq { font-size: .75rem; color: #4f46e5; margin-top: .125rem; }
.dark .cc { background: #0f172a; border-color: #1e293b; }
.dark .cd { color: #94a3b8; }
.dark .cq { color: #818cf8; }
</style>
<div class="max-w-5xl mx-auto">
  <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
      <h1 class="text-2xl font-bold">Browse all topics</h1>
      <p class="text-sm text-slate-500 mt-1"><?= count($rows) ?> topics — find your area and start asking or answering.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <label for="cat-search" class="sr-only">Search topics</label>
      <input id="cat-search" type="search" placeholder="Search topics… e.g. GST, cricket, resume"
             class="w-full sm:w-64 border rounded-lg px-3 py-2 text-sm" autocomplete="off">
      <?php if ($activeCount > 0 && $activeCount < count($rows)): ?>
        <label class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400 whitespace-nowrap cursor-pointer">
          <input type="checkbox" id="cat-only-active"> Only topics with questions (<?= $activeCount ?>)
        </label>
      <?php endif; ?>
    </div>
  </div>

  <?php foreach ($sections as $label => $cats): ?>
    <section data-cat-section class="mb-6">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-2"><?= e($label) ?></h2>
      <div class="cg">
        <?php foreach ($cats as $c): $n = (int) $c['question_count']; ?>
          <a href="/c/<?= e($c['slug']) ?>" class="cc"<?= $n ? ' data-has-q' : '' ?>><span class="ci"><?= e($c['icon'] ?? '📁') ?></span><span class="cb"><span class="cn"><?= e($c['name']) ?></span><span class="cd"><?= e($c['description'] ?? '') ?></span><?php if ($n): ?><span class="cq"><?= $n ?> question<?= $n === 1 ? '' : 's' ?></span><?php endif; ?></span></a>
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
  // Index text once from the DOM rather than shipping a duplicate copy of every
  // name+description in data- attributes (that cost ~43KB across 700+ cards).
  var cards = Array.prototype.slice.call(document.querySelectorAll('.cc')).map(function (el) {
    return { el: el, text: (el.textContent || '').toLowerCase() };
  });
  var sections = Array.prototype.slice.call(document.querySelectorAll('[data-cat-section]'));
  var empty = document.getElementById('cat-no-results');
  var onlyActive = document.getElementById('cat-only-active');

  function apply() {
    var q = input.value.trim().toLowerCase();
    var activeOnly = onlyActive && onlyActive.checked;
    var any = false;
    cards.forEach(function (card) {
      var show = (!q || card.text.indexOf(q) !== -1) && (!activeOnly || card.el.hasAttribute('data-has-q'));
      card.el.classList.toggle('hidden', !show);
      if (show) any = true;
    });
    sections.forEach(function (sec) {
      var visible = sec.querySelector('.cc:not(.hidden)');
      sec.classList.toggle('hidden', !visible);
    });
    empty.classList.toggle('hidden', any);
  }

  input.addEventListener('input', apply);
  if (onlyActive) onlyActive.addEventListener('change', apply);
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
