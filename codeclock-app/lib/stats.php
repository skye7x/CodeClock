<?php
// Statistics queries. Running sessions (ended_at IS NULL) are never counted.

// Time and commit count per project.
function hours_per_project($userId)
{
    $st = db()->prepare(
        'SELECT p.id, p.name,
                COALESCE(SUM(TIMESTAMPDIFF(SECOND, s.started_at, s.ended_at)), 0) AS secs,
                (SELECT COUNT(*) FROM commits c WHERE c.project_id = p.id) AS commit_count
         FROM projects p
         LEFT JOIN sessions s ON s.project_id = p.id AND s.ended_at IS NOT NULL
         WHERE p.user_id = ?
         GROUP BY p.id, p.name
         ORDER BY secs DESC'
    );
    $st->execute([$userId]);
    return $st->fetchAll();
}

// Seconds per day for the last $days days (including today). Days are UTC.
// A session is counted on the day it STARTED.
function hours_per_day($userId, $days)
{
    $days = max(1, (int)$days);
    $st = db()->prepare(
        'SELECT DATE(s.started_at) AS d,
                SUM(TIMESTAMPDIFF(SECOND, s.started_at, s.ended_at)) AS secs
         FROM sessions s
         JOIN projects p ON p.id = s.project_id
         WHERE p.user_id = ? AND s.ended_at IS NOT NULL
           AND s.started_at >= UTC_DATE() - INTERVAL ' . ($days - 1) . ' DAY
         GROUP BY DATE(s.started_at)
         ORDER BY d'
    );
    $st->execute([$userId]);
    return $st->fetchAll();
}
