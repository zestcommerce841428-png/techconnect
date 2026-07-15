<?php
/** @var array $q expects: id, title, slug, vote_score, answer_count, view_count, status,
 * created_at, username, location_city, location_country, tags (comma string), is_sponsored */
?>
<a href="/question.php?slug=<?= e($q['slug']) ?>" class="block border rounded-lg p-4 bg-white hover:border-indigo-400 transition">
  <div class="flex items-start justify-between gap-4">
    <div class="flex-1">
      <h3 class="font-medium text-slate-900">
        <?= e($q['title']) ?>
        <?php if (!empty($q['is_sponsored'])): ?>
          <span class="ml-1 text-xs bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded">Sponsored</span>
        <?php endif; ?>
      </h3>
      <div class="mt-1 text-xs text-slate-500 flex flex-wrap gap-x-3 gap-y-1">
        <span>by <?= e($q['username']) ?></span>
        <span><?= time_ago($q['created_at']) ?></span>
        <?php if (!empty($q['location_city']) || !empty($q['location_country'])): ?>
          <span>📍 <?= e(trim(($q['location_city'] ?? '') . ', ' . ($q['location_country'] ?? ''), ', ')) ?></span>
        <?php endif; ?>
      </div>
      <?php if (!empty($q['tags'])): ?>
        <div class="mt-2 flex flex-wrap gap-1">
          <?php foreach (explode(',', $q['tags']) as $tag): ?>
            <span class="text-xs bg-slate-100 text-slate-700 px-2 py-0.5 rounded"><?= e($tag) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="flex flex-col items-center gap-1 text-xs text-slate-600 shrink-0">
      <div class="font-semibold text-slate-900"><?= (int) $q['vote_score'] ?></div>
      <div>votes</div>
      <div class="mt-2 font-semibold <?= $q['answer_count'] > 0 ? 'text-green-700' : 'text-slate-900' ?>"><?= (int) $q['answer_count'] ?></div>
      <div>answers</div>
    </div>
  </div>
</a>
