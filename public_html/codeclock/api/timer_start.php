<?php
ob_start();
require_once __DIR__ . '/../bootstrap.php';

$userId = api_guard();
$projectId = (int)($_POST['project_id'] ?? 0);

$pdo = db();
$error = null;   // [message, http status]

// Check + insert must be ONE atomic step. Locking the user's row makes two parallel
// requests (two devices, two browsers) wait for each other, so only one can start a timer.
$pdo->beginTransaction();
try {
    $pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE')->execute([$userId]);

    // The project must belong to the logged-in user.
    $st = $pdo->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
    $st->execute([$projectId, $userId]);
    if (!$st->fetch()) {
        $error = ['Project not found', 404];
    } else {
        // Only one running session per user.
        $st = $pdo->prepare(
            'SELECT s.id FROM sessions s
             JOIN projects p ON p.id = s.project_id
             WHERE p.user_id = ? AND s.ended_at IS NULL LIMIT 1'
        );
        $st->execute([$userId]);
        if ($st->fetch()) {
            $error = ['A timer is already running', 409];
        } else {
            // The SERVER sets the time, never the browser.
            $pdo->prepare('INSERT INTO sessions (project_id, started_at) VALUES (?, UTC_TIMESTAMP())')
                ->execute([$projectId]);
        }
    }
    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log('Timer start error: ' . $ex->getMessage());
    json_response(['error' => 'Could not start the timer'], 500);
}

if ($error) {
    json_response(['error' => $error[0]], $error[1]);
}
json_response(['ok' => true]);
