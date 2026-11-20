<?php
// Run from cPanel cron, e.g. every 30 minutes:
//   /usr/local/bin/php /home/USER/codeclock_app/cron/sync_commits.php
// Syncs the 10 projects that were synced longest ago.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/sync.php';

$st = db()->query(
    'SELECT id FROM projects
     WHERE repo_owner IS NOT NULL AND repo_name IS NOT NULL
     ORDER BY last_synced_at IS NULL DESC, last_synced_at ASC
     LIMIT 10'
);

foreach ($st->fetchAll() as $row) {
    try {
        $new = sync_project((int)$row['id']);
        app_log('Cron sync project ' . $row['id'] . ': ' . $new . ' new commits');
    } catch (Exception $ex) {
        app_log('Cron sync project ' . $row['id'] . ' failed: ' . $ex->getMessage());
    }
    sleep(1);   // be nice to the GitHub API
}
