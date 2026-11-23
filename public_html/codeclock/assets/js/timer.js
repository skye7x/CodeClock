(function () {
  var csrf = document.querySelector('meta[name="csrf"]').content;
  var display = document.getElementById('timer-display');
  var label = document.getElementById('timer-label');
  var message = document.getElementById('timer-msg');
  var select = document.getElementById('timer-project');
  var startBtn = document.getElementById('timer-start');
  var stopBtn = document.getElementById('timer-stop');

  var startedMs = null;   // when the running session started (ms)
  var offsetMs = 0;       // server clock minus browser clock
  var ticker = null;

  function pad(n) { return n < 10 ? '0' + n : '' + n; }

  function showTime() {
    var secs = Math.max(0, Math.floor((Date.now() + offsetMs - startedMs) / 1000));
    display.textContent = pad(Math.floor(secs / 3600)) + ':' + pad(Math.floor((secs % 3600) / 60)) + ':' + pad(secs % 60);
  }

  function post(url, data) {
    var body = new URLSearchParams(data || {});
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': csrf, 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body
    }).then(function (r) { return r.json(); });
  }

  function render(status) {
    clearInterval(ticker);
    message.textContent = '';
    if (status.error) {   // e.g. the session expired: go back to the login page
      window.location.href = 'login.php';
      return;
    }
    if (status.running) {
      startedMs = new Date(status.started_at).getTime();
      offsetMs = new Date(status.server_now).getTime() - Date.now();
      label.textContent = 'Working on: ' + status.project_name;
      showTime();
      ticker = setInterval(showTime, 1000);
      startBtn.disabled = true;
      select.value = String(status.project_id);   // show the project that is really running
      select.disabled = true;
      stopBtn.disabled = false;
    } else {
      display.textContent = '00:00:00';
      label.textContent = 'No timer running';
      startBtn.disabled = false;
      select.disabled = false;
      stopBtn.disabled = true;
    }
  }

  // $notice (optional) is shown AFTER the status is drawn; render() clears the message line,
  // so an error set before loadStatus() used to disappear immediately.
  function loadStatus(notice) {
    fetch('api/timer_status.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (status) {
        render(status);
        if (notice) { message.textContent = notice; }
      })
      .catch(function () { message.textContent = 'Could not load timer status.'; });
  }

  startBtn.addEventListener('click', function () {
    post('api/timer_start.php', { project_id: select.value }).then(function (res) {
      loadStatus(res.error || '');
    }).catch(function () {
      message.textContent = 'Could not start the timer.';
    });
  });

  stopBtn.addEventListener('click', function () {
    post('api/timer_stop.php').then(function (res) {
      if (res.error) { loadStatus(res.error); return; }
      window.location.reload();   // refresh totals
    }).catch(function () {
      message.textContent = 'Could not stop the timer.';
    });
  });

  loadStatus();
})();
