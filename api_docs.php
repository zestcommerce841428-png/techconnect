<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'API Documentation — ' . SITE_NAME;
$pageDescription = 'Public REST API reference for ' . SITE_NAME . '.';
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-3xl mx-auto">
  <h1 class="text-2xl font-bold mb-2">API documentation</h1>
  <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">
    All endpoints return JSON. Read endpoints are public; write endpoints require an API key from
    <a href="/api_keys" class="text-indigo-600 hover:underline">your API keys page</a>, sent as
    <code class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded text-xs">Authorization: Bearer tc_...</code>.
    Keys can be scoped to <strong>read-only</strong> or <strong>full access</strong> when generated — a read-only key
    is rejected with a 403 on any write endpoint (like <code class="bg-slate-100 dark:bg-slate-800 px-1 rounded text-xs">POST /api/v1/ask.php</code>),
    so it's safe to hand out to scripts that should never be able to post content.
  </p>

  <div class="space-y-6">
    <?php
    $groups = [
      'Q&A' => [
        ['GET', '/api/v1/questions.php?page=1&tag=php&category=technology', 'List recent questions, optionally filtered by tag or category.'],
        ['GET', '/api/v1/question.php?slug=your-question-slug', 'Fetch a single question with its answers.'],
        ['POST', '/api/v1/ask.php', 'Create a question. Requires API key. Body: title, body, category, tags (comma-separated).'],
        ['GET', '/api/v1/answers.php?slug=your-question-slug', "List a question's answers."],
        ['POST', '/api/v1/answer.php', 'Post an answer. Requires API key. Body: slug, body.'],
        ['GET', '/api/v1/comments.php?parent_type=question&parent_id=1', 'List comments on a question or answer.'],
        ['POST', '/api/v1/comment.php', 'Post a comment. Requires API key. Body: parent_type (question|answer), parent_id, body.'],
        ['POST', '/api/v1/vote.php', 'Upvote/downvote a question or answer (toggles on repeat). Requires API key. Body: votable_type, votable_id, value (1 or -1).'],
        ['POST', '/api/v1/save_question.php', 'Bookmark/unbookmark a question (toggle). Requires API key. Body: slug.'],
      ],
      'Discovery & taxonomy' => [
        ['GET', '/api/v1/tags.php', 'List the top 100 tags by usage.'],
        ['GET', '/api/v1/categories.php', 'List active categories.'],
        ['GET', '/api/v1/search.php?q=keyword', 'Full-text search across questions.'],
        ['GET', '/api/v1/leaderboard.php', 'Top 50 users by reputation.'],
        ['GET', '/api/v1/roadmap.php', 'Public roadmap items and their vote counts.'],
        ['GET', '/api/v1/changelog.php', 'Published changelog entries.'],
        ['GET', '/api/v1/testimonials.php', 'Published testimonials.'],
        ['GET', '/api/v1/currencies.php', 'Supported currencies and exchange rates.'],
        ['GET', '/api/v1/site_info.php', 'Public site settings (name, tagline, base currency) for building third-party clients.'],
      ],
      'People' => [
        ['GET', '/api/v1/users.php?username=someone', 'Fetch a public profile summary.'],
        ['GET', '/api/v1/me.php', 'Confirm your API key is valid and see which account it belongs to.'],
        ['GET', '/api/v1/reputation.php?username=someone', "A user's reputation event history."],
        ['GET', '/api/v1/badges.php', 'The full badge catalog.'],
        ['GET', '/api/v1/user_badges.php?username=someone', 'Badges a specific user has earned.'],
        ['POST', '/api/v1/follow.php', 'Follow a user or tag. Requires API key. Body: followable_type (user|tag), followable.'],
        ['POST', '/api/v1/unfollow.php', 'Unfollow a user or tag. Requires API key. Same body as follow.'],
        ['GET', '/api/v1/notifications.php', 'Your own notifications. Requires API key.'],
      ],
      'Groups, jobs & experts' => [
        ['GET', '/api/v1/groups.php', 'List public groups.'],
        ['GET', '/api/v1/group.php?slug=group-slug', 'Fetch a single public group.'],
        ['GET', '/api/v1/jobs.php', 'List active job listings.'],
        ['GET', '/api/v1/job.php?slug=job-slug', 'Fetch a single job listing.'],
        ['GET', '/api/v1/experts.php', 'List approved experts.'],
        ['GET', '/api/v1/expert.php?username=someone', "Fetch a single expert's profile."],
        ['GET', '/api/v1/group_members.php?slug=group-slug', 'Members of a public group.'],
        ['POST', '/api/v1/group_join.php', 'Join a public group. Requires API key. Body: slug.'],
        ['POST', '/api/v1/group_leave.php', 'Leave a group. Requires API key. Body: slug.'],
      ],
      'Content & collections' => [
        ['GET', '/api/v1/blog_posts.php?page=1', 'List published blog posts.'],
        ['GET', '/api/v1/blog_post.php?slug=post-slug', 'Fetch a single published blog post.'],
        ['GET', '/api/v1/collections.php?username=someone', "A user's public collections."],
        ['GET', '/api/v1/collection.php?username=someone&slug=collection-slug', 'A single public collection and its questions.'],
        ['GET', '/api/v1/question_revisions.php?slug=your-question-slug', 'Edit history for a question.'],
        ['GET', '/api/v1/answer_revisions.php?answer_id=1', 'Edit history for an answer.'],
        ['GET', '/api/v1/site_poll.php', 'The currently active site-wide poll with live vote counts.'],
        ['GET', '/api/v1/announcements.php', 'Currently enabled site announcements.'],
        ['GET', '/api/v1/convert_currency.php?amount_cents=1000&to=EUR', 'Convert a base-currency cent amount into another supported currency.'],
      ],
      'Your account (requires API key)' => [
        ['GET', '/api/v1/drafts.php', 'List your own draft questions.'],
        ['POST', '/api/v1/draft_save.php', 'Create or update a draft. Body: title, body, category (optional), id (optional, to update).'],
        ['GET', '/api/v1/saved_searches.php', 'List your saved searches.'],
        ['POST', '/api/v1/saved_search_save.php', 'Save a search. Body: label, query.'],
        ['POST', '/api/v1/saved_search_delete.php', 'Delete a saved search you own. Body: id.'],
        ['POST', '/api/v1/roadmap_vote.php', 'Vote for a roadmap item (toggles on repeat). Body: id.'],
        ['GET', '/api/v1/account_summary.php', 'One-call overview: reputation, Pro status, question/answer/badge counts, unread notifications.'],
        ['GET', '/api/v1/orders.php', 'Your own past orders.'],
        ['GET', '/api/v1/invoices.php', 'Your own invoices.'],
        ['GET', '/api/v1/subscription.php', 'Your Pro membership status.'],
        ['GET', '/api/v1/notification_prefs.php', 'Your notification email preferences.'],
        ['POST', '/api/v1/notification_prefs_update.php', 'Update preferences. Body: any of email_on_answer, email_on_mention, email_on_message, email_tag_digest (booleans).'],
        ['POST', '/api/v1/notifications_mark_read.php', "Mark one notification (body: id) or all of yours read (omit id)."],
        ['GET', '/api/v1/is_following.php?followable_type=question&followable=slug', 'Check whether you follow a user, tag, or question.'],
        ['POST', '/api/v1/block_user.php', 'Block a user. Body: username.'],
        ['POST', '/api/v1/unblock_user.php', 'Unblock a user. Body: username.'],
        ['GET', '/api/v1/blocked_users.php', 'Users you have blocked.'],
        ['GET', '/api/v1/consultations.php', 'Your consultations, as requester or expert.'],
        ['POST', '/api/v1/consultation_request.php', 'Request a paid consultation with an expert. Body: expert_username, message (optional).'],
      ],
      'Moderation & admin (requires an admin/moderator API key)' => [
        ['GET', '/api/v1/reports.php', 'Open user-submitted reports queue.'],
        ['GET', '/api/v1/spam_flags.php', 'Pending automated spam flags.'],
        ['GET', '/api/v1/suggested_edits.php', 'Pending community-suggested edits.'],
        ['GET', '/api/v1/audit_log.php', 'Recent admin action log. Admin-only (moderators are not allowed).'],
        ['GET', '/api/v1/webhooks.php', 'List configured webhooks (secrets never included). Admin-only.'],
        ['POST', '/api/v1/webhook_create.php', 'Register a webhook. Admin-only. Body: label, url (https only), events (comma-separated). Returns the secret once.'],
        ['POST', '/api/v1/webhook_delete.php', 'Remove a webhook. Admin-only. Body: id.'],
        ['GET', '/api/v1/webhook_deliveries.php?webhook_id=1', 'Delivery log for one webhook. Admin-only.'],
      ],
    ];
    foreach ($groups as $groupName => $endpoints): ?>
      <h2 class="text-base font-semibold mt-6 mb-2"><?= e($groupName) ?></h2>
      <?php foreach ($endpoints as [$method, $path, $desc]): ?>
        <div class="card p-4 mb-2">
          <div class="flex items-center gap-2 mb-1">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded <?= $method === 'GET' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' ?>"><?= $method ?></span>
            <code class="text-sm"><?= e($path) ?></code>
          </div>
          <p class="text-sm text-slate-600 dark:text-slate-400"><?= e($desc) ?></p>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>

  <h2 class="text-lg font-semibold mt-8 mb-2">Rate limits</h2>
  <p class="text-sm text-slate-600 dark:text-slate-400">
    Write endpoints are limited per API key, typically 20-60 requests/hour depending on the endpoint. A 429 response with
    <code class="bg-slate-100 dark:bg-slate-800 px-1 rounded text-xs">{"error":"Rate limit exceeded"}</code> means back off and retry later.
    Read endpoints need no API key and are rate-limited by IP at 120 requests/minute.
  </p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
