<?php
require_once __DIR__ . '/bootstrap.php';
require_login();

$userId = current_user_id();
$projectId = (int)input_str($_GET, 'id');

// Only the owner can open a project.
$st = db()->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
$st->execute([$projectId, $userId]);
$project = $st->fetch();
if (!$project) {
    http_response_code(404);
    exit('Project not found');
}

// Sessions + how many commits fall inside each session's time range.
$st = db()->prepare(
    'SELECT s.id, s.started_at, s.ended_at, s.note,
            TIMESTAMPDIFF(SECOND, s.started_at, COALESCE(s.ended_at, UTC_TIMESTAMP())) AS secs,
            (SELECT COUNT(*) FROM commits c
              WHERE c.project_id = s.project_id
                AND c.author_date BETWEEN s.started_at AND s.ended_at) AS commit_count
     FROM sessions s
     WHERE s.project_id = ?
     ORDER BY s.started_at DESC
     LIMIT 50'
);
$st->execute([$projectId]);
$sessions = $st->fetchAll();

$st = db()->prepare('SELECT sha, message, author_date FROM commits WHERE project_id = ? ORDER BY author_date DESC LIMIT 50');
$st->execute([$projectId]);
$commits = $st->fetchAll();

$hasRepo = !empty($project['repo_owner']) && !empty($project['repo_name']);

$pageTitle = $project['name'];
$extraScripts = $hasRepo ? ['sync.js'] : [];
require APP_PATH . '/views/header.php';
?>
<div class="card">
  <h1><?= e($project['name']) ?></h1>
  <?php if ($hasRepo): ?>
    <p>
      Repo: <a href="https://github.com/<?= e($project['repo_owner']) ?>/<?= e($project['repo_name']) ?>" target="_blank" rel="noopener">
        <?= e($project['repo_owner'] . '/' . $project['repo_name']) ?></a>
    </p>
    <p class="muted">
      Last synced:
      <?php if ($project['last_synced_at']): ?>
        <span class="utc-time" data-utc="<?= e($project['last_synced_at']) ?>"><?= e($project['last_synced_at']) ?> UTC</span>
      <?php else: ?>never<?php endif; ?>
    </p>
    <button type="button" class="sync-btn" data-project="<?= (int)$project['id'] ?>">Sync commits</button>
    <span class="muted" id="sync-msg"></span>
  <?php else: ?>
    <p class="muted">No GitHub repo linked to this project.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Sessions</h2>
  <?php if (!$sessions): ?>
    <p class="muted">No sessions yet. Start the timer on the dashboard.</p>
  <?php else: ?>
    <table>
      <tr><th>Started</th><th>Duration</th><th>Commits</th></tr>
      <?php foreach ($sessions as $s): ?>
        <tr>
          <td><span class="utc-time" data-utc="<?= e($s['started_at']) ?>"><?= e($s['started_at']) ?> UTC</span></td>
          <td>
            <?= e(format_duration($s['secs'])) ?>
            <?php if ($s['ended_at'] === null): ?><span class="muted">(running)</span><?php endif; ?>
          </td>
          <td><?= (int)$s['commit_count'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>

<?php if ($hasRepo): ?>
<div class="card">
  <h2>Latest commits</h2>
  <?php if (!$commits): ?>
    <p class="muted">No commits synced yet.</p>
  <?php else: ?>
    <table>
      <tr><th>SHA</th><th>Message</th><th>Date</th></tr>
      <?php foreach ($commits as $c): ?>
        <tr>
          <td class="sha"><?= e(substr($c['sha'], 0, 7)) ?></td>
          <td><?= e($c['message']) ?></td>
          <td><span class="utc-time" data-utc="<?= e($c['author_date']) ?>"><?= e($c['author_date']) ?> UTC</span></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php require APP_PATH . '/views/footer.php'; ?>
