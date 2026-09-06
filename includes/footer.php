</div><!-- /container-fluid -->
<footer class="page-footer">
  &copy; <?= date('Y') ?> <?= APP_NAME ?> &mdash; All rights reserved.
</footer>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/password-toggle.js"></script>
<script>
(function () {
  var themeToggle = document.querySelector('.theme-toggle');
  var savedTheme = localStorage.getItem('dmp-theme');
  var setTheme = function (dark) {
    document.body.classList.toggle('theme-dark', dark);
    if (themeToggle) {
      themeToggle.innerHTML = '<span>' + (dark ? 'Light mode' : 'Dark mode') + '</span><i class="bi bi-' + (dark ? 'sun' : 'moon') + '"></i>';
      themeToggle.setAttribute('aria-label', dark ? 'Switch to light theme' : 'Switch to dark theme');
    }
  };
  setTheme(savedTheme === 'dark');
  if (themeToggle) {
    themeToggle.addEventListener('click', function () {
      var dark = !document.body.classList.contains('theme-dark');
      localStorage.setItem('dmp-theme', dark ? 'dark' : 'light');
      setTheme(dark);
    });
  }
})();
</script>
</body>
</html>
