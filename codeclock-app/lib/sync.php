<?php
require_once __DIR__ . '/github.php';

const SYNC_WEB_COOLDOWN = 15;   // seconds between manual syncs of ONE project (hourly caps are in api/sync.php + github.php)

function sync_project($projectId, $source = 'web')
{
    $pdo = db();

    $sql = 'UPDATE projects
            SET sync_started_at = UTC_TIMESTAMP(), last_sync_attempt_at = UTC_TIMESTAMP()
            WHERE id = ? AND (sync_started_at IS NULL OR sync_started_at < UTC_TIMESTAMP() - INTERVAL 5 MINUTE)';
    if ($source === 'web') {
        $sql .= ' AND (last_sync_attempt_at IS NULL OR last_sync_attempt_at < UTC_TIMESTAMP() - INTERVAL ' . SYNC_WEB_COOLDOWN . ' SECOND)';
    }
    $lock = $pdo->prepare($sql);
    $lock->execute([$projectId]);
    if ($lock->rowCount() === 0) {
        // Say WHICH of the two it was, and for how long.
        $st = $pdo->prepare(
            'SELECT TIMESTAMPDIFF(SECOND, sync_started_at, UTC_TIMESTAMP()) AS running,
                    TIMESTAMPDIFF(SECOND, last_sync_attempt_at, UTC_TIMESTAMP()) AS since_attempt
             FROM projects WHERE id = ?'
        );
        $st->execute([$projectId]);
        $row = $st->fetch();
        if ($row && $row['running'] !== null && (int)$row['running'] < 300) {
            throw new UserFacingException('A sync of this project is already running (started ' . (int)$row['running'] . ' s ago). Try again in a moment.');
        }
        $wait = max(1, SYNC_WEB_COOLDOWN - (int)($row['since_attempt'] ?? 0));
        throw new UserFacingException('This project was synced a moment ago. Try again in ' . $wait . ' s.');
    }

    try {
        $st = $pdo->prepare('SELECT repo_owner, repo_name, last_synced_at, created_at FROM projects WHERE id = ?');
        $st->execute([$projectId]);
        $project = $st->fetch();

        if (!$project || !$project['repo_owner'] || !$project['repo_name']) {
            throw new UserFacingException('This project has no GitHub repo.');
        }

        $startedAt = gmdate('Y-m-d H:i:s');
        // Never pull the whole history of a repo: commits from before the project existed
        // cannot fall inside any session. First sync starts 1 day before the project was created;
        // later syncs overlap the previous one by 7 days (catches late pushes and rewritten history).
        $sinceTs = $project['last_synced_at']
            ? strtotime($project['last_synced_at'] . ' UTC') - 7 * 86400
            : strtotime($project['created_at'] . ' UTC') - 86400;
        $since   = gmdate('Y-m-d\TH:i:s\Z', $sinceTs);
        $sinceDb = gmdate('Y-m-d H:i:s', $sinceTs);

        $fetched  = github_fetch_commits($project['repo_owner'], $project['repo_name'], $since, $source);
        $commits  = $fetched['commits'];

        // INSERT ... ON DUPLICATE KEY UPDATE: new commits are added, and author_login is refreshed
        // for rows that already exist (older rows, or a verified e-mail added on GitHub later).
        $insert = $pdo->prepare(
            'INSERT INTO commits (project_id, sha, message, author_date, author_login) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE author_login = VALUES(author_login)'
        );
        $new = 0;

        $pdo->beginTransaction();
        try {
            foreach ($commits as $c) {
                $insert->execute([$projectId, $c['sha'], $c['message'], $c['date'], $c['author_login']]);
                if ($insert->rowCount() === 1) {   // 1 = inserted, 2 = updated, 0 = unchanged
                    $new++;
                }
            }

            // Rebase / amend / force-push: the old SHAs vanish from GitHub but would stay here forever
            // as "ghost" commits. Inside the window we just fetched, drop everything GitHub no longer lists.
            // Only when the fetch was COMPLETE - a cut-off list would delete real commits.
            if ($fetched['complete']) {
                $keep = [];
                foreach ($commits as $c) {
                    $keep[$c['sha']] = true;
                }
                $st = $pdo->prepare('SELECT sha FROM commits WHERE project_id = ? AND author_date >= ?');
                $st->execute([$projectId, $sinceDb]);
                $del = $pdo->prepare('DELETE FROM commits WHERE project_id = ? AND sha = ?');
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $sha) {
                    if (!isset($keep[$sha])) {
                        $del->execute([$projectId, $sha]);
                    }
                }
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        // If the page limit cut the list off, do NOT move last_synced_at: the next sync starts from the
        // same point and the cron keeps going instead of leaving a silent gap.
        if ($fetched['complete']) {
            $pdo->prepare('UPDATE projects SET last_synced_at = ?, sync_failures = 0 WHERE id = ?')
                ->execute([$startedAt, $projectId]);
        } else {
            app_log('Sync project ' . $projectId . ': history longer than the page limit, not marked as fully synced');
        }
        return $new;
    } catch (GithubRateLimitException $ex) {
        // forget and use next time
        $pdo->prepare('UPDATE projects SET last_sync_attempt_at = NULL WHERE id = ?')->execute([$projectId]);
        throw $ex;
    } catch (Throwable $ex) {
        $pdo->prepare('UPDATE projects SET sync_failures = LEAST(sync_failures + 1, 100) WHERE id = ?')->execute([$projectId]);
        throw $ex;
    } finally {
        $pdo->prepare('UPDATE projects SET sync_started_at = NULL WHERE id = ?')->execute([$projectId]);
    }
}