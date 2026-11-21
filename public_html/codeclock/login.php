<?php
require_once __DIR__ . '/bootstrap.php';

if (current_user_id()) {
    redirect('dashboard.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();

    $email    = strtolower(trim(input_str($_POST, 'email')));
    $password = input_str($_POST, 'password');
    $ip       = client_ip();

    // The attempt is recorded BEFORE the password is checked, so parallel guesses are counted.
    if (!login_attempt_allowed($email, $ip)) {
        $errors[] = 'Too many attempts. Try again in 15 minutes.';
    } else {
        $user = false;
        if ($email !== '' && strlen($email) <= 255) {
            $st = db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
            $st->execute([$email]);
            $user = $st->fetch();
        }

        // Unknown e-mail? Check against a dummy hash anyway, so the response time
        // does not reveal which e-mails have an account.
        $valid = password_verify($password, $user ? $user['password_hash'] : DUMMY_PASSWORD_HASH);

        if ($user && $valid) {
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
            login_attempt_succeeded($email, $ip);
            login_user($user['id']);
            redirect('dashboard.php');
        }

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
    <input type="email" id="email" name="email" value="<?= e(mb_substr($email, 0, 255)) ?>" maxlength="255" required>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>
    <p><button type="submit">Log in</button></p>
  </form>
  <p class="muted">No account yet? <a href="register.php">Register</a></p>
</div>
<?php require APP_PATH . '/views/footer.php'; ?>
