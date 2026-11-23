<?php
require_once __DIR__ . '/bootstrap.php';

if (current_user_id()) {
    redirect('dashboard.php');
}

const MIN_PASSWORD_LENGTH = 10;

const COMMON_PASSWORDS = [
    'password12', 'password123', 'password1234', '1234567890', '12345678910', '0123456789',
    'qwertyuiop', 'qwerty1234', 'qwerty12345', 'iloveyou12', 'letmein123', 'welcome123',
    'admin12345', 'abc1234567', 'abcdefghij', 'passw0rd12', 'p@ssw0rd12', 'changeme123',
    'codeclock1', 'codeclock12', 'codeclock123', '1q2w3e4r5t', 'zaq12wsxcde', 'monkey1234',
];

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();

    $email     = strtolower(trim(input_str($_POST, 'email')));
    $password  = input_str($_POST, 'password');
    $password2 = input_str($_POST, 'password2');

    // Limits how fast anyone can create accounts (or test which e-mails are taken).
    if (!rate_limit_hit('register:ip', client_ip(), 10, 3600)) {
        $errors[] = 'Too many registrations from your address. Try again later.';
    } else {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors[] = 'Please enter a valid email.';
        }
        if (strlen($password) < MIN_PASSWORD_LENGTH) {
            $errors[] = 'Password must have at least ' . MIN_PASSWORD_LENGTH . ' characters.';
        }
        if (strlen($password) > 72) {
            $errors[] = 'Password is too long (max 72 characters).';
        }
        if (strlen($password) >= MIN_PASSWORD_LENGTH && (
            in_array(strtolower($password), COMMON_PASSWORDS, true)
            || strtolower($password) === $email
            || count(array_unique(str_split($password))) < 4
        )) {
            $errors[] = 'That password is too easy to guess. Choose another one.';
        }
        if ($password !== $password2) {
            $errors[] = 'Passwords do not match.';
        }
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
    <input type="email" id="email" name="email" value="<?= e(mb_substr($email, 0, 255)) ?>" maxlength="255" required>
    <label for="password">Password (10-72 characters)</label>
    <input type="password" id="password" name="password" required>
    <label for="password2">Repeat password</label>
    <input type="password" id="password2" name="password2" required>
    <p><button type="submit">Register</button></p>
  </form>
  <p class="muted">Already have an account? <a href="login.php">Log in</a></p>
</div>
<?php require APP_PATH . '/views/footer.php'; ?>
