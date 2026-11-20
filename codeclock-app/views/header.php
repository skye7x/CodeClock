<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf" content="<?= e(csrf_token()) ?>">
  <title><?= e($pageTitle ?? 'CodeClock') ?> - CodeClock</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="top">
  <a class="brand" href="index.php">CodeClock</a>
  <?php if (current_user_id()): ?>
    <nav>
      <a href="dashboard.php">Dashboard</a>
      <a href="stats.php">Stats</a>
      <form method="post" action="logout.php" class="inline">
        <?= csrf_field() ?>
        <button type="submit" class="link">Log out</button>
      </form>
    </nav>
  <?php endif; ?>
</header>
<main>
