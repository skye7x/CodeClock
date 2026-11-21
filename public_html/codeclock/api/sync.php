<?php
ob_start();
require_once __DIR__ . '/../bootstrap.php';
require_once APP_PATH . '/lib/sync.php';

$userId = api_guard();
$projectId = (int)($_POST['project_id'] ?? 0);

// A sync can take a minute. Release the session lock now, or every other request
// from this user (even the timer) would wait until the sync is finished.
session_write_close();

$st = db()->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
$st->execute([$projectId, $userId]);
if (!$st->fetch()) {
    json_response(['error' => 'Project not found'], 404);
}

// Each manual sync costs GitHub requests, and they are a shared, limited resource.
if (!rate_limit_hit('sync:user', $userId, 10, 3600)) {
    json_response(['error' => 'Too many sync requests. Try again in a while.'], 429);
}

try {
    $new = sync_project($projectId, 'web');
    json_response(['ok' => true, 'new_commits' => $new]);
} catch (GithubRateLimitException $ex) {
    json_response(['error' => $ex->getMessage()], 429);
} catch (UserFacingException $ex) {
    json_response(['error' => $ex->getMessage()], 400);
} catch (Throwable $ex) {
    // Details (cURL errors, SQL) go to the log only.
    app_log('Sync error (project ' . $projectId . '): ' . get_class($ex) . ': ' . $ex->getMessage());
    json_response(['error' => 'Sync failed. Please try again later.'], 500);
}
