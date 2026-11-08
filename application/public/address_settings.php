<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_login();

$page_title = 'ZimRx - Address Settings';
$extra_css = [
    'assets/css/pages/settings.css',
    'assets/css/modules/modals_dialogs.css',
    'assets/css/pages/geo_studio/address_settings.css',
];
include __DIR__ . '/header.php';
?>

<main class="zrx-directory-settings-page zrx-page-container">
    <header class="zrx-settings-page-hero">
        <div class="zrx-settings-page-hero-copy">
            <a class="zrx-settings-page-back" href="settings.php">Settings</a>
            <h1>Address Settings</h1>
            <p>Configure address search and manage saved suggestions.</p>
        </div>
    </header>
    <section id="zrx-address-pack-settings-root" class="zrx-address-pack-settings" aria-live="polite">
        <p>Loading address packs...</p>
    </section>
    <div id="zrx-address-settings-root" class="zrx-directory-settings-root" aria-live="polite"></div>
</main>

<script src="assets/js/pages/address_settings_manager.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/address_settings_manager.js') ?>"></script>
<script src="assets/js/pages/address_settings.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/address_settings.js') ?>"></script>
<script type="module" src="assets/js/pages/geo_studio/address_pack_settings.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/geo_studio/address_pack_settings.js') ?>"></script>
<?php include __DIR__ . '/footer.php'; ?>
