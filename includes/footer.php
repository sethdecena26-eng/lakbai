<?php // includes/footer.php ?>
  </main><!-- /.page-content -->
</div><!-- /.main-wrapper -->
</div><!-- /#layout -->

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<?php if (!empty($extraScripts)): ?>
  <?php foreach ($extraScripts as $script): ?>
    <script src="<?= APP_URL . '/assets/js/' . $script ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>
<?php if (!empty($inlineScript)): ?>
<script><?= $inlineScript ?></script>
<?php endif; ?>
</body>
</html>
