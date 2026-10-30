<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_login();

$pcSettingsOnly = true;
$page_title = 'ZimRx - Presenting Complaint Settings';
$extra_css = [
    'assets/css/modules/pc.css',
    'assets/css/pages/settings.css',
    'assets/css/pages/presenting_complaint_settings.css',
];
include __DIR__ . '/header.php';
?>

<main class="zrx-pc-settings-page zrx-page-container">
    <header class="zrx-settings-page-hero">
        <div class="zrx-settings-page-hero-copy">
            <a class="zrx-settings-page-back" href="settings.php">Settings</a>
            <h1>Presenting Complaint Settings</h1>
            <p>Manage suggestion sources, custom terms, duration values, units, and usage ranking.</p>
        </div>
    </header>

    <?php include ZIMRX_BASE_DIR . '/views/modules/pc.php'; ?>
</main>

<script src="assets/js/modules/pc.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/modules/pc.js') ?>"></script>
<script src="assets/js/pages/presenting_complaint_settings.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/pages/presenting_complaint_settings.js') ?>"></script>
<?php include __DIR__ . '/footer.php'; ?>
