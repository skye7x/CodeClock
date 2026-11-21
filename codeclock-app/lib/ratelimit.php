<?php
// Generic sliding-window rate limiter. Every event is one row in rate_events.
// Used for login throttling, registration, manual syncs and the GitHub API budget.

function rate_limit_count($action, $subject, $windowSeconds)
{
    $st = db()->prepare(
        'SELECT COUNT(*) FROM rate_events
         WHERE action = ? AND subject = ?
           AND created_at > (UTC_TIMESTAMP() - INTERVAL ' . (int)$windowSeconds . ' SECOND)'
    );
    $st->execute([$action, substr((string)$subject, 0, 64)]);
    return (int)$st->fetchColumn();
}

function rate_limit_record($action, $subject)
{
    $st = db()->prepare('INSERT INTO rate_events (action, subject, created_at) VALUES (?, ?, UTC_TIMESTAMP())');
    $st->execute([$action, substr((string)$subject, 0, 64)]);
}

// Counts one event and says whether the caller is still inside the limit.
// The event is recorded BEFORE the count is read, so a burst of parallel requests
// cannot all see "0 so far": at most $limit of them can ever get through.
function rate_limit_hit($action, $subject, $limit, $windowSeconds)
{
    // Flood guard: once a bucket is far over its limit, stop storing more rows.
    if (rate_limit_count($action, $subject, $windowSeconds) >= $limit * 3) {
        return false;
    }

    rate_limit_record($action, $subject);

    // Housekeeping on ~1 in 200 calls, so the table cannot grow forever even without cron.
    if (random_int(1, 200) === 1) {
        rate_limit_purge();
    }

    return rate_limit_count($action, $subject, $windowSeconds) <= $limit;
}

function rate_limit_clear($action, $subject)
{
    $st = db()->prepare('DELETE FROM rate_events WHERE action = ? AND subject = ?');
    $st->execute([$action, substr((string)$subject, 0, 64)]);
}

// Windows are at most 1 hour, so anything older than a day is dead weight.
function rate_limit_purge()
{
    db()->exec('DELETE FROM rate_events WHERE created_at < (UTC_TIMESTAMP() - INTERVAL 1 DAY)');
}
