<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_login();

$page_title = 'ZimRx - Occupation Settings';
$extra_css = [
    'assets/css/pages/settings.css',
    'assets/css/modules/modals_dialogs.css',
];
include __DIR__ . '/header.php';
?>

<main class="zrx-directory-settings-page zrx-page-container">
    <header class="zrx-settings-page-hero">
        <div class="zrx-settings-page-hero-copy">
            <a class="zrx-settings-page-back" href="settings.php">Settings</a>
            <h1>Occupation Settings</h1>
            <p>Manage occupation suggestions available in patient particulars.</p>
        </div>
    </header>
    <div id="zrx-occupation-settings-root" class="zrx-directory-settings-root" aria-live="polite"></div>
</main>

<script src="assets/js/pages/occupation_settings_manager.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/occupation_settings_manager.js') ?>"></script>
<script src="assets/js/pages/occupation_settings.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/occupation_settings.js') ?>"></script>
<?php include __DIR__ . '/footer.php'; ?>
