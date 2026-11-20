<?php
require_once __DIR__ . '/bootstrap.php';

if (current_user_id()) {
    redirect('dashboard.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();

    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $ip       = client_ip();

    if (too_many_attempts($email, $ip)) {
        $errors[] = 'Too many failed attempts. Try again in 15 minutes.';
    } else {
        $st = db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $st->execute([$email]);
        $user = $st->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            clear_attempts($email);
            login_user($user['id']);
            redirect('dashboard.php');
        }

        record_failed_attempt($email, $ip);
        $errors[] = 'Wrong email or password.';   // same message for both cases on purpose
    }
}

$pageTitle = 'Log in';
require APP_PATH . '/views/header.php';
?>
<div class="card">
  <h1>Log in</h1>
  <?php if ($errors): ?>
    <div class="errors"><?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?></div>
  <?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($email) ?>" required>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>
    <p><button type="submit">Log in</button></p>
  </form>
  <p class="muted">No account yet? <a href="register.php">Register</a></p>
</div>
<?php require APP_PATH . '/views/footer.php'; ?>
