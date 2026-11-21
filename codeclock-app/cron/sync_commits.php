<?php
// Run from cPanel cron, e.g. every 30 minutes:
//   /usr/local/bin/php /home/USER/codeclock-app/cron/sync_commits.php
// Syncs the 10 projects whose last sync ATTEMPT is the oldest.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/sync.php';

// Housekeeping: old throttling rows.
rate_limit_purge();

// Order by the last ATTEMPT (success or failure), never by the last success. That way a
// repo that fails cannot sit at the front of the queue and push everyone else out.
// A project that failed 5 times in a row is only retried once a day.
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
        break;   // no point hammering GitHub for the other projects
    } catch (Throwable $ex) {
        app_log('Cron sync project ' . $row['id'] . ' failed: ' . $ex->getMessage());
    }
    sleep(1);   // be nice to the GitHub API
}
