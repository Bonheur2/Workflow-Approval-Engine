
</div>
<script>
document.addEventListener('click', function (event) {
  document.querySelectorAll('details.user-menu[open]').forEach(function (menu) {
    if (!menu.contains(event.target)) {
      menu.removeAttribute('open');
    }
  });
});
</script>
</body>
</html>
