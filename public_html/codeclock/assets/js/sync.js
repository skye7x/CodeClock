// "Sync commits" button on the project page.
(function () {
  var btn = document.querySelector('.sync-btn');
  var msg = document.getElementById('sync-msg');
  var csrf = document.querySelector('meta[name="csrf"]').content;
  if (!btn) return;

  btn.addEventListener('click', function () {
    btn.disabled = true;
    msg.textContent = 'Syncing...';

    fetch('api/sync.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': csrf, 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ project_id: btn.dataset.project })
    })
      .then(function (r) {
        return r.json().catch(function () { return { error: 'Sync failed (HTTP ' + r.status + ').' }; });
      })
      .then(function (res) {
        if (res.error) {
          msg.textContent = res.error;
          btn.disabled = false;
        } else {
          msg.textContent = res.new_commits + ' new commits. Reloading...';
          setTimeout(function () { window.location.reload(); }, 800);
        }
      })
      .catch(function () {
        msg.textContent = 'Network error.';
        btn.disabled = false;
      });
  });
})();
