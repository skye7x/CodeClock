<?php
ob_start();
require_once __DIR__ . '/../bootstrap.php';

if (!current_user_id()) {
    json_response(['error' => 'Not logged in'], 401);
}

$st = db()->prepare(
    'SELECT s.id, s.project_id, s.started_at, p.name
     FROM sessions s
     JOIN projects p ON p.id = s.project_id
     WHERE p.user_id = ? AND s.ended_at IS NULL
     LIMIT 1'
);
$st->execute([current_user_id()]);
$row = $st->fetch();

if (!$row) {
    json_response(['running' => false, 'server_now' => gmdate('Y-m-d\TH:i:s\Z')]);
}

json_response([
    'running'      => true,
    'session_id'   => (int)$row['id'],
    'project_id'   => (int)$row['project_id'],
    'project_name' => $row['name'],
    'started_at'   => iso_utc($row['started_at']),
    'server_now'   => gmdate('Y-m-d\TH:i:s\Z'),
]);
