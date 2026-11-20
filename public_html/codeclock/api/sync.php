<?php
require_once __DIR__ . '/../bootstrap.php';
require_once APP_PATH . '/lib/sync.php';

$userId = api_guard();
$projectId = (int)($_POST['project_id'] ?? 0);

$st = db()->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
$st->execute([$projectId, $userId]);
if (!$st->fetch()) {
    json_response(['error' => 'Project not found'], 404);
}

try {
    $new = sync_project($projectId);
    json_response(['ok' => true, 'new_commits' => $new]);
} catch (PDOException $ex) {
    app_log('Sync DB error: ' . $ex->getMessage());
    json_response(['error' => 'Database error'], 500);
} catch (Exception $ex) {
    json_response(['error' => $ex->getMessage()], 400);
}
