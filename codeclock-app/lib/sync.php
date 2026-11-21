<?php
// Downloads new commits for one project and saves them.
require_once __DIR__ . '/github.php';

// $source is 'web' (a visitor pressed the button) or 'cron'.
// Returns the number of NEW commits.
// Throws UserFacingException (safe to show) or another Exception (log it, show nothing).
function sync_project($projectId, $source = 'web')
{
    $pdo = db();

    // Lock: only one sync per project at a time (a lock older than 5 minutes is ignored).
    // The attempt time is stored right away, whether the sync later works or fails, so a
    // broken repo goes to the back of the cron queue instead of blocking it.
    // Visitors also wait 60 seconds between two syncs of the same project.
    $sql = 'UPDATE projects
            SET sync_started_at = UTC_TIMESTAMP(), last_sync_attempt_at = UTC_TIMESTAMP()
            WHERE id = ? AND (sync_started_at IS NULL OR sync_started_at < UTC_TIMESTAMP() - INTERVAL 5 MINUTE)';
    if ($source === 'web') {
        $sql .= ' AND (last_sync_attempt_at IS NULL OR last_sync_attempt_at < UTC_TIMESTAMP() - INTERVAL 60 SECOND)';
    }
    $lock = $pdo->prepare($sql);
    $lock->execute([$projectId]);
    if ($lock->rowCount() === 0) {
        throw new UserFacingException('A sync is already running or finished a moment ago. Wait a minute and try again.');
    }

    try {
        $st = $pdo->prepare('SELECT repo_owner, repo_name, last_synced_at FROM projects WHERE id = ?');
        $st->execute([$projectId]);
        $project = $st->fetch();

        if (!$project || !$project['repo_owner'] || !$project['repo_name']) {
            throw new UserFacingException('This project has no GitHub repo.');
        }

        $startedAt = gmdate('Y-m-d H:i:s');   // saved as last_synced_at after success
        $since = $project['last_synced_at'] ? str_replace(' ', 'T', $project['last_synced_at']) . 'Z' : null;

        $commits = github_fetch_commits($project['repo_owner'], $project['repo_name'], $since, $source);

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
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        $pdo->prepare('UPDATE projects SET last_synced_at = ?, sync_failures = 0 WHERE id = ?')
            ->execute([$startedAt, $projectId]);
        return $new;
    } catch (GithubRateLimitException $ex) {
        // Not this project's fault: forget the attempt so it is retried first next time.
        $pdo->prepare('UPDATE projects SET last_sync_attempt_at = NULL WHERE id = ?')->execute([$projectId]);
        throw $ex;
    } catch (Throwable $ex) {
        // Count consecutive failures. The cron job backs off from projects that keep failing.
        $pdo->prepare('UPDATE projects SET sync_failures = LEAST(sync_failures + 1, 100) WHERE id = ?')->execute([$projectId]);
        throw $ex;
    } finally {
        $pdo->prepare('UPDATE projects SET sync_started_at = NULL WHERE id = ?')->execute([$projectId]);
    }
}
