<?php
// Downloads new commits for one project and saves them.
require_once __DIR__ . '/github.php';

// Returns the number of NEW commits. Throws Exception with a readable message on problems.
function sync_project($projectId)
{
    $pdo = db();

    // Lock: only one sync per project at a time (a lock older than 5 minutes is ignored).
    $lock = $pdo->prepare(
        'UPDATE projects SET sync_started_at = UTC_TIMESTAMP()
         WHERE id = ? AND (sync_started_at IS NULL OR sync_started_at < UTC_TIMESTAMP() - INTERVAL 5 MINUTE)'
    );
    $lock->execute([$projectId]);
    if ($lock->rowCount() === 0) {
        throw new Exception('A sync is already running for this project.');
    }

    try {
        $st = $pdo->prepare('SELECT repo_owner, repo_name, last_synced_at FROM projects WHERE id = ?');
        $st->execute([$projectId]);
        $project = $st->fetch();

        if (!$project || !$project['repo_owner'] || !$project['repo_name']) {
            throw new Exception('This project has no GitHub repo.');
        }

        $startedAt = gmdate('Y-m-d H:i:s');   // saved as last_synced_at after success
        $since = $project['last_synced_at'] ? str_replace(' ', 'T', $project['last_synced_at']) . 'Z' : null;

        $commits = github_fetch_commits($project['repo_owner'], $project['repo_name'], $since);

        // INSERT IGNORE + the UNIQUE (project_id, sha) key = no duplicates.
        $insert = $pdo->prepare('INSERT IGNORE INTO commits (project_id, sha, message, author_date) VALUES (?, ?, ?, ?)');
        $new = 0;

        $pdo->beginTransaction();
        try {
            foreach ($commits as $c) {
                $insert->execute([$projectId, $c['sha'], $c['message'], $c['date']]);
                $new += $insert->rowCount();
            }
            $pdo->commit();
        } catch (Exception $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        $pdo->prepare('UPDATE projects SET last_synced_at = ? WHERE id = ?')->execute([$startedAt, $projectId]);
        return $new;
    } finally {
        $pdo->prepare('UPDATE projects SET sync_started_at = NULL WHERE id = ?')->execute([$projectId]);
    }
}
