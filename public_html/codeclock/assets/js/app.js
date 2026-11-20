// Shows UTC times from the database in the visitor's local time.
// In HTML:  <span class="utc-time" data-utc="2026-11-20 10:00:00">...</span>
document.querySelectorAll('.utc-time').forEach(function (el) {
  var date = new Date(el.dataset.utc.replace(' ', 'T') + 'Z');
  if (!isNaN(date)) {
    el.textContent = date.toLocaleString();
  }
});
