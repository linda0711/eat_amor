<!-- JS Global -->
  <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
  <?php if (isset($extraJs)): ?>
    <?php foreach ($extraJs as $js): ?>
      <script src="<?= BASE_URL . '/assets/js/' . $js ?>"></script>
    <?php endforeach; ?>
  <?php endif; ?>
</body>
</html>