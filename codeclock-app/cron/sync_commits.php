<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/sync.php';

rate_limit_purge();

$st = db()->query(
    "SELECT id FROM projects
     WHERE repo_owner IS NOT NULL AND repo_name IS NOT NULL
       AND (sync_failures < 5
            OR last_sync_attempt_at IS NULL
            OR last_sync_attempt_at < UTC_TIMESTAMP() - INTERVAL 1 DAY)
     ORDER BY COALESCE(last_sync_attempt_at, '1970-01-01 00:00:00') ASC
     LIMIT 10"
);

foreach ($st->fetchAll() as $row) {
    try {
        $new = sync_project((int)$row['id'], 'cron');
        app_log('Cron sync project ' . $row['id'] . ': ' . $new . ' new commits');
    } catch (GithubRateLimitException $ex) {
        app_log('Cron stopped: ' . $ex->getMessage());
        break;
    } catch (Throwable $ex) {
        app_log('Cron sync project ' . $row['id'] . ' failed: ' . $ex->getMessage());
    }
    sleep(1);   // be nice to the GitHub API
}
