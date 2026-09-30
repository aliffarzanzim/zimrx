<?php
declare(strict_types=1);

// Application-wide footer: copyright notice, software version, and support modal trigger.
include_once __DIR__ . '/modules/support_modal.php';
$app_version = '1.0.0';
?>

<footer class="app-footer">
    <span>&copy; <?= date('Y') ?> ZimRx EMR System</span>
    <span class="app-footer-sep">|</span>
    <span>Version <?= htmlspecialchars($app_version) ?></span>
    <span class="app-footer-sep">|</span>
    <button type="button" class="app-footer-support" onclick="zimrxOpenSupportModal()">
        <span class="support-icon">❤️</span>
        <span class="support-label">Support ZimRx</span>
    </button>
</footer>



</body>
</html>
