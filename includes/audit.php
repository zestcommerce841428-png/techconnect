<?php
function audit_log(int $adminId, string $action, ?string $targetType = null, ?int $targetId = null, ?string $details = null): void
{
    db()->prepare('INSERT INTO audit_log (admin_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$adminId, $action, $targetType, $targetId, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
}
