<?php require_once dirname(__DIR__) . '/includes/analytics.php'; ?>
<footer class="catalogue-footer">
    <span>&copy; <?= date('Y') ?> <?= escape_html(APP_NAME) ?></span>
    <span>Catalogue powered by the Sanzida's Closet database</span>
</footer>
<?= analytics_script() ?>