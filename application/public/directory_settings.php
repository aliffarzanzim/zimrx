<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_login();

$page_title = 'ZimRx - Address and Occupation Directories';
$extra_css = [
    'assets/css/pages/settings.css',
    'assets/css/modules/modals_dialogs.css',
];
include __DIR__ . '/header.php';
?>

<main class="zrx-directory-settings-page zrx-page-container">
    <header class="zrx-settings-index-header zrx-settings-page-hero">
        <div class="zrx-settings-page-hero-copy">
            <a class="zrx-settings-page-back" href="settings.php">Settings</a>
            <h1>Address and Occupation Directories</h1>
            <p>Manage the suggestions used in patient particulars. Guide popups contain instructions only.</p>
        </div>
    </header>

    <nav class="zrx-directory-tabs" aria-label="Directory settings">
        <button type="button" class="active" data-directory-tab="occupation" aria-pressed="true">Occupation</button>
        <button type="button" data-directory-tab="address" aria-pressed="false">Address</button>
    </nav>
    <div id="zrx-directory-settings-root" class="zrx-directory-settings-root" aria-live="polite"></div>
</main>

<script src="assets/js/pages/directory_settings_manager.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/directory_settings_manager.js') ?>"></script>
<script src="assets/js/pages/directory_settings.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/directory_settings.js') ?>"></script>
<?php include __DIR__ . '/footer.php'; ?>
