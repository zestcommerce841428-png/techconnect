<?php
/** Membership helpers for groups. */

function group_role(int $groupId, ?int $userId): ?string
{
    if (!$userId) return null;
    $stmt = db()->prepare('SELECT role FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$groupId, $userId]);
    $role = $stmt->fetchColumn();
    return $role === false ? null : $role;
}

function can_view_group(array $group, ?array $user): bool
{
    if (!$group['is_private']) return true;
    if (!$user) return false;
    if (in_array($user['role'], ['admin', 'moderator'], true)) return true;
    return group_role((int) $group['id'], (int) $user['id']) !== null;
}
