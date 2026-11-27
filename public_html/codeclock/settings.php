<?php
require_once __DIR__ . '/bootstrap.php';
require_login();

$userId = current_user_id();
$errors = [];
$saved  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $login = trim(input_str($_POST, 'github_login'));

    if ($login !== '' && !is_valid_github_name($login)) {
        $errors[] = 'GitHub login: letters, numbers, - _ . only.';
    }
    if (!$errors) {
        db()->prepare('UPDATE users SET github_login = ? WHERE id = ?')
            ->execute([$login !== '' ? $login : null, $userId]);
        $saved = true;
    }
}

$st = db()->prepare('SELECT github_login FROM users WHERE id = ?');
$st->execute([$userId]);
$current = (string)$st->fetchColumn();

$pageTitle = 'Settings';
require APP_PATH . '/views/header.php';
?>
<div class="card">
  <h2>GitHub account</h2>
  <p class="muted">Only commits written by this GitHub user count in your sessions and stats.
     Leave empty to count every commit in the linked repos.</p>
  <?php if ($saved): ?><p>Saved.</p><?php endif; ?>
  <?php if ($errors): ?>
    <div class="errors"><?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?></div>
  <?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label for="github_login">Your GitHub login</label>
    <input type="text" id="github_login" name="github_login" maxlength="100"
           value="<?= e($current) ?>" placeholder="e.g. octocat">
    <p><button type="submit">Save</button></p>
  </form>
</div>
<?php require APP_PATH . '/views/footer.php'; ?>