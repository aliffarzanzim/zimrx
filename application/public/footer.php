<?php
declare(strict_types=1);

// Application-wide footer: copyright notice, software version, and support modal trigger.
if (!defined('ZIMRX_BASE_DIR') || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'footer.php') {
    http_response_code(403);
    exit('Direct access forbidden.');
}

include_once ZIMRX_BASE_DIR . '/views/modules/support_modal.php';
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
