<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/medical_history_lib.php';

$doctorId = current_user_doctor_id();
$medHistoryDoctorConfig = med_history_get_doctor_config($doctorId);
$page_title = 'ZimRx - History Settings';
$extra_css = [
    'assets/css/modules/history.css',
    'assets/css/pages/settings.css',
    'assets/css/pages/history_settings.css',
];
include __DIR__ . '/header.php';
?>

<main class="zrx-history-settings-page zrx-page-container">
    <header class="zrx-settings-page-hero">
        <div class="zrx-settings-page-hero-copy">
            <a class="zrx-settings-page-back" href="settings.php">Settings</a>
            <h1>History Settings</h1>
            <p>Manage the medical-history terms and input styles used during clinical entry.</p>
        </div>
    </header>

    <?php include ZIMRX_BASE_DIR . '/views/modules/history_settings.php'; ?>
</main>

<script src="assets/js/modules/history.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/modules/history.js') ?>"></script>
<?php include __DIR__ . '/footer.php'; ?>
