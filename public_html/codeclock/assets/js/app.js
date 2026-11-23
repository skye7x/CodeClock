// In HTML:  <span class="utc-time" data-utc="2026-11-20 10:00:00">...</span>
document.querySelectorAll('.utc-time').forEach(function (el) {
  var date = new Date(el.dataset.utc.replace(' ', 'T') + 'Z');
  if (!isNaN(date)) {
    el.textContent = date.toLocaleString();
  }
});

document.querySelectorAll('[data-width]').forEach(function (el) {
  var w = parseInt(el.dataset.width, 10);
  if (!isNaN(w)) {
    el.style.width = Math.min(100, Math.max(0, w)) + '%';
  }
});

document.querySelectorAll('form[data-confirm]').forEach(function (form) {
  form.addEventListener('submit', function (event) {
    if (!window.confirm(form.dataset.confirm)) {
      event.preventDefault();
    }
  });
});
