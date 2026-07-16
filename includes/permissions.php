<?php
/** Extra abilities a moderator can be granted beyond the baseline role, so an
 * admin doesn't have to hand out full admin access just to let someone manage
 * the blog. Admins implicitly have every permission. */
function permission_defs(): array
{
    return [
        'manage_blog' => 'Manage blog posts',
        'manage_jobs' => 'Manage job listings',
        'manage_pages' => 'Manage CMS pages',
        'manage_experts' => 'Approve expert marketplace applications',
        'manage_categories' => 'Manage categories and tags',
    ];
}

function user_can(array $user, string $permission): bool
{
    if ($user['role'] === 'admin') return true;
    if ($user['role'] !== 'moderator') return false;
    static $cache = [];
    if (!isset($cache[$user['id']])) {
        $stmt = db()->prepare('SELECT permission_key FROM user_permissions WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $cache[$user['id']] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    return in_array($permission, $cache[$user['id']], true);
}

/** Gates an admin page: admins always pass, moderators need the specific permission. */
function require_permission(array $user, string $permission): void
{
    if (!user_can($user, $permission)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}
