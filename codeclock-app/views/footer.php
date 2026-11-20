</main>
<script src="assets/js/app.js"></script>
<?php foreach (($extraScripts ?? []) as $script): ?>
<script src="assets/js/<?= e($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
