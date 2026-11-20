<?php
require_once __DIR__ . '/bootstrap.php';
require_login();

$userId = current_user_id();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name  = trim($_POST['name'] ?? '');
        $owner = trim($_POST['repo_owner'] ?? '');
        $repo  = trim($_POST['repo_name'] ?? '');
        $pattern = '/^[A-Za-z0-9_.-]{1,100}$/';

        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'Project name must be 1-100 characters.';
        }
        if ($owner !== '' || $repo !== '') {
            if (!preg_match($pattern, $owner) || !preg_match($pattern, $repo)) {
                $errors[] = 'Fill both repo owner and repo name (letters, numbers, - _ . only), or leave both empty.';
            }
        }

        if (!$errors) {
            $st = db()->prepare('INSERT INTO projects (user_id, name, repo_owner, repo_name) VALUES (?, ?, ?, ?)');
            $st->execute([$userId, $name, $owner ?: null, $repo ?: null]);
            redirect('dashboard.php');
        }
    } elseif ($action === 'delete') {
        $st = db()->prepare('DELETE FROM projects WHERE id = ? AND user_id = ?');
        $st->execute([(int)($_POST['project_id'] ?? 0), $userId]);
        redirect('dashboard.php');
    }
}

// Projects with total finished time (running sessions are NOT counted).
$st = db()->prepare(
    'SELECT p.id, p.name, p.repo_owner, p.repo_name,
            COALESCE(SUM(TIMESTAMPDIFF(SECOND, s.started_at, s.ended_at)), 0) AS secs
     FROM projects p
     LEFT JOIN sessions s ON s.project_id = p.id AND s.ended_at IS NOT NULL
     WHERE p.user_id = ?
     GROUP BY p.id, p.name, p.repo_owner, p.repo_name
     ORDER BY p.name'
);
$st->execute([$userId]);
$projects = $st->fetchAll();

$pageTitle = 'Dashboard';
$extraScripts = ['timer.js'];
require APP_PATH . '/views/header.php';
?>
<div class="card" id="timer">
  <h2>Timer</h2>
  <?php if (!$projects): ?>
    <p class="muted">Create a project below to start tracking time.</p>
  <?php endif; ?>
  <p id="timer-label" class="muted">Loading...</p>
  <div class="timer-display" id="timer-display">00:00:00</div>
  <div class="row">
    <select id="timer-project" style="max-width:300px">
      <?php foreach ($projects as $p): ?>
        <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="button" id="timer-start" class="green" <?= $projects ? '' : 'disabled' ?>>Start</button>
    <button type="button" id="timer-stop" class="danger" disabled>Stop</button>
  </div>
  <p id="timer-msg" class="muted"></p>
</div>

<div class="card">
  <h2>Your projects</h2>
  <?php if (!$projects): ?>
    <p class="muted">No projects yet.</p>
  <?php else: ?>
    <table>
      <tr><th>Name</th><th>Repo</th><th>Total time</th><th></th></tr>
      <?php foreach ($projects as $p): ?>
        <tr>
          <td><a href="project.php?id=<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a></td>
          <td><?= $p['repo_owner'] ? e($p['repo_owner'] . '/' . $p['repo_name']) : '<span class="muted">-</span>' ?></td>
          <td><?= e(format_duration($p['secs'])) ?></td>
          <td>
            <form method="post" class="inline" onsubmit="return confirm('Delete this project and all its data?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>">
              <button type="submit" class="danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2>New project</h2>
  <?php if ($errors): ?>
    <div class="errors"><?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?></div>
  <?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <label for="name">Project name</label>
    <input type="text" id="name" name="name" maxlength="100" required>
    <label for="repo_owner">GitHub owner (optional, public repos only)</label>
    <input type="text" id="repo_owner" name="repo_owner" maxlength="100" placeholder="e.g. torvalds">
    <label for="repo_name">GitHub repo name (optional)</label>
    <input type="text" id="repo_name" name="repo_name" maxlength="100" placeholder="e.g. linux">
    <p><button type="submit">Create project</button></p>
  </form>
</div>
<?php require APP_PATH . '/views/footer.php'; ?>
