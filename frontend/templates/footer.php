
</div>
<script>
document.addEventListener('click', function (event) {
  document.querySelectorAll('details.user-menu[open]').forEach(function (menu) {
    if (!menu.contains(event.target)) {
      menu.removeAttribute('open');
    }
  });
});

document.querySelectorAll('.kv-add').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var container = btn.previousElementSibling;
    var maxRows = parseInt(container.dataset.maxRows, 10) || 50;
    var rows = container.querySelectorAll('.kv-row');
    var nextIndex = rows.length + 1;
    if (nextIndex > maxRows) {
      return;
    }
    var row = document.createElement('div');
    row.className = 'kv-row';
    row.innerHTML =
      '<input type="text" name="field_key_' + nextIndex + '" placeholder="field name, e.g. amount">' +
      '<input type="text" name="field_value_' + nextIndex + '" placeholder="value, e.g. 12000">' +
      '<button type="button" class="kv-remove" aria-label="Remove field">&times;</button>';
    container.appendChild(row);
  });
});

document.addEventListener('click', function (event) {
  if (!event.target.classList.contains('kv-remove')) {
    return;
  }
  var row = event.target.closest('.kv-row');
  var container = row.parentElement;
  row.remove();
  container.querySelectorAll('.kv-row').forEach(function (r, idx) {
    var n = idx + 1;
    r.querySelector('input[name^="field_key_"]').name = 'field_key_' + n;
    r.querySelector('input[name^="field_value_"]').name = 'field_value_' + n;
  });
});
</script>
</body>
</html>
