<?php
require_once __DIR__ . '/../bootstrap.php';

$userId = api_guard();

$st = db()->prepare(
    'UPDATE sessions s
     JOIN projects p ON p.id = s.project_id
     SET s.ended_at = UTC_TIMESTAMP()
     WHERE p.user_id = ? AND s.ended_at IS NULL'
);
$st->execute([$userId]);

if ($st->rowCount() === 0) {
    json_response(['error' => 'No timer is running'], 409);
}

json_response(['ok' => true]);
