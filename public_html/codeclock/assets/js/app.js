// Shows UTC times from the database in the visitor's local time.
// In HTML:  <span class="utc-time" data-utc="2026-11-20 10:00:00">...</span>
document.querySelectorAll('.utc-time').forEach(function (el) {
  var date = new Date(el.dataset.utc.replace(' ', 'T') + 'Z');
  if (!isNaN(date)) {
    el.textContent = date.toLocaleString();
  }
});

// Bar widths from data-width="0..100". Done here (not with style="...") so the page
// can run under a Content-Security-Policy that forbids inline styles.
document.querySelectorAll('[data-width]').forEach(function (el) {
  var w = parseInt(el.dataset.width, 10);
  if (!isNaN(w)) {
    el.style.width = Math.min(100, Math.max(0, w)) + '%';
  }
});

// <form data-confirm="Are you sure?"> asks before submitting.
// Done here (not with onsubmit="...") for the same Content-Security-Policy reason.
document.querySelectorAll('form[data-confirm]').forEach(function (form) {
  form.addEventListener('submit', function (event) {
    if (!window.confirm(form.dataset.confirm)) {
      event.preventDefault();
    }
  });
});
