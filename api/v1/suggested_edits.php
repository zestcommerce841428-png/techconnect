<?php
// GET /api/v1/suggested_edits.php — pending community-suggested edits. Requires an admin/moderator API key.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

$user = api_authenticate();
if (!$user) api_json(['error' => 'Invalid or missing API key'], 401);
if (!in_array($user['role'], ['admin', 'moderator'], true)) api_json(['error' => 'Moderator access required'], 403);
if (!api_rate_limit($user['key_id'], 60, 60)) api_json(['error' => 'Rate limit exceeded'], 429);

$stmt = db()->query(
    "SELECT se.id, se.target_type, se.target_id, se.proposed_title, se.edit_summary, se.created_at, u.username AS proposer
     FROM suggested_edits se JOIN users u ON u.id = se.proposer_id
     WHERE se.status = 'pending' ORDER BY se.created_at ASC LIMIT 100"
);
api_json(['data' => array_map(fn($e) => [
    'id' => (int) $e['id'], 'target_type' => $e['target_type'], 'target_id' => (int) $e['target_id'],
    'proposed_title' => $e['proposed_title'], 'edit_summary' => $e['edit_summary'],
    'proposer' => $e['proposer'], 'created_at' => $e['created_at'],
], $stmt->fetchAll())]);
