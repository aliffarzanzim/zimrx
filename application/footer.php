<?php
// footer.php — shared app footer
// coffee_modal.php handles its own include-once guard
include_once __DIR__ . '/modules/coffee_modal.php';
$app_version = '1.0.0';
?>

<!-- ── App Footer ──────────────────────────────────────────────────────── -->
<footer class="app-footer">
    <span>&copy; <?= date('Y') ?> ZimRx EMR System</span>
    <span class="app-footer-sep">|</span>
    <span>Version <?= htmlspecialchars($app_version) ?></span>
    <span class="app-footer-sep">|</span>
    <button type="button" class="app-footer-coffee" onclick="zimrxOpenCoffeeModal()">
        <span class="coffee-icon">☕</span>
        <span class="coffee-label">Buy me a coffee?</span>
    </button>
</footer>



</body>
</html>
