<?php
/** @var array $q expects: id, title, slug, vote_score, answer_count, view_count, status,
 * created_at, username, location_city, location_country, tags (comma string), is_sponsored */
?>
<a href="/q/<?= e($q['slug']) ?>" class="block border border-slate-200 rounded-xl p-4 sm:p-5 bg-white shadow-card hover:shadow-card-hover hover:border-indigo-300 transition-all">
  <div class="flex items-start justify-between gap-4">
    <div class="flex-1 min-w-0">
      <h3 class="font-semibold text-slate-900 leading-snug">
        <?= e($q['title']) ?>
        <?php if (!empty($q['is_sponsored'])): ?>
          <span class="ml-1 text-xs font-normal bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded-full">Sponsored</span>
        <?php endif; ?>
        <?php if (($q['status'] ?? '') === 'closed'): ?>
          <span class="ml-1 text-xs font-normal bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded-full">Closed</span>
        <?php endif; ?>
      </h3>
      <div class="mt-1.5 text-xs text-slate-500 flex flex-wrap gap-x-3 gap-y-1">
        <?php if (!empty($q['category_name'])): ?>
          <span><?= e($q['category_icon'] ? $q['category_icon'] . ' ' : '') . e($q['category_name']) ?></span>
        <?php endif; ?>
        <span>by <?= e($q['username']) ?></span>
        <span><?= time_ago($q['created_at']) ?></span>
        <?php if (!empty($q['location_city']) || !empty($q['location_country'])): ?>
          <span>📍 <?= e(trim(($q['location_city'] ?? '') . ', ' . ($q['location_country'] ?? ''), ', ')) ?></span>
        <?php endif; ?>
      </div>
      <?php if (!empty($q['tags'])): ?>
        <div class="mt-2.5 flex flex-wrap gap-1.5">
          <?php foreach (explode(',', $q['tags']) as $tag): ?>
            <span class="text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full"><?= e($tag) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="flex flex-col items-center gap-1 text-xs text-slate-500 shrink-0 text-center w-12 sm:w-14">
      <div>
        <div class="font-bold text-sm text-slate-900"><?= (int) $q['vote_score'] ?></div>
        <div>votes</div>
      </div>
      <div>
        <div class="font-bold text-sm <?= $q['answer_count'] > 0 ? 'text-green-700' : 'text-slate-900' ?>"><?= (int) $q['answer_count'] ?></div>
        <div>answers</div>
      </div>
    </div>
  </div>
</a>
