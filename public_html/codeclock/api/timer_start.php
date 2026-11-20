<?php
ob_start();
require_once __DIR__ . '/../bootstrap.php';

$userId = api_guard();
$projectId = (int)($_POST['project_id'] ?? 0);

// The project must belong to the logged-in user.
$st = db()->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
$st->execute([$projectId, $userId]);
if (!$st->fetch()) {
    json_response(['error' => 'Project not found'], 404);
}

// Only one running session per user.
$st = db()->prepare(
    'SELECT s.id FROM sessions s
     JOIN projects p ON p.id = s.project_id
     WHERE p.user_id = ? AND s.ended_at IS NULL LIMIT 1'
);
$st->execute([$userId]);
if ($st->fetch()) {
    json_response(['error' => 'A timer is already running'], 409);
}

// The SERVER sets the time, never the browser.
$st = db()->prepare('INSERT INTO sessions (project_id, started_at) VALUES (?, UTC_TIMESTAMP())');
$st->execute([$projectId]);

json_response(['ok' => true]);
