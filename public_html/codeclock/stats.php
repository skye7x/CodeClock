<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_PATH . '/lib/stats.php';
require_login();

$userId = current_user_id();
$days = (($_GET['days'] ?? '7') === '30') ? 30 : 7;

$perProject = hours_per_project($userId);

// Fill days with no sessions with 0, so the list has no gaps.
$byDate = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $byDate[gmdate('Y-m-d', strtotime("-$i days"))] = 0;
}
foreach (hours_per_day($userId, $days) as $row) {
    if (isset($byDate[$row['d']])) {
        $byDate[$row['d']] = (int)$row['secs'];
    }
}
$max = max($byDate) ?: 1;

$pageTitle = 'Stats';
require APP_PATH . '/views/header.php';
?>
<div class="card">
  <h2>Time per project</h2>
  <?php if (!$perProject): ?>
    <p class="muted">No projects yet.</p>
  <?php else: ?>
    <table>
      <tr><th>Project</th><th>Total time</th><th>Commits</th><th>Commits / hour</th></tr>
      <?php foreach ($perProject as $p): ?>
        <?php $hours = $p['secs'] / 3600; ?>
        <tr>
          <td><?= e($p['name']) ?></td>
          <td><?= e(format_duration($p['secs'])) ?></td>
          <td><?= (int)$p['commit_count'] ?></td>
          <td><?= $hours > 0 ? e(number_format($p['commit_count'] / $hours, 2)) : '-' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Last <?= (int)$days ?> days (UTC)</h2>
  <p>
    <a href="stats.php?days=7">7 days</a> |
    <a href="stats.php?days=30">30 days</a>
  </p>
  <table>
    <?php foreach ($byDate as $date => $secs): ?>
      <tr>
        <td style="width:110px"><?= e($date) ?></td>
        <td>
          <div class="bar-wrap"><div class="bar" style="width: <?= (int)round($secs / $max * 100) ?>%"></div></div>
        </td>
        <td style="width:90px"><?= e(format_duration($secs)) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php require APP_PATH . '/views/footer.php'; ?>
