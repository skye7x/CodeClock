<?php
require_once __DIR__ . '/bootstrap.php';

if (current_user_id()) {
    redirect('dashboard.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();

    $email     = strtolower(trim($_POST['email'] ?? ''));
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        $errors[] = 'Please enter a valid email.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must have at least 8 characters.';
    }
    if (strlen($password) > 72) {
        $errors[] = 'Password is too long (max 72 characters).';
    }
    if ($password !== $password2) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        try {
            $st = db()->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
            $st->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
            login_user(db()->lastInsertId());
            redirect('dashboard.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {      // duplicate email
                $errors[] = 'This email is already registered.';
            } else {
                app_log('Register error: ' . $ex->getMessage());
                $errors[] = 'Something went wrong. Try again later.';
            }
        }
    }
}

$pageTitle = 'Register';
require APP_PATH . '/views/header.php';
?>
<div class="card">
  <h1>Create account</h1>
  <?php if ($errors): ?>
    <div class="errors"><?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?></div>
  <?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($email) ?>" required>
    <label for="password">Password (min 8 characters)</label>
    <input type="password" id="password" name="password" required>
    <label for="password2">Repeat password</label>
    <input type="password" id="password2" name="password2" required>
    <p><button type="submit">Register</button></p>
  </form>
  <p class="muted">Already have an account? <a href="login.php">Log in</a></p>
</div>
<?php require APP_PATH . '/views/footer.php'; ?>
