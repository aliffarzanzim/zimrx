<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/physical_examination_lib.php';

$doctorId = current_user_doctor_id();
$peDoctorConfig = physical_exam_get_doctor_config($doctorId);
$peSettingsPage = true;
$page_title = 'ZimRx - Physical Examination Settings';
$extra_css = [
    'assets/css/modules/history.css',
    'assets/css/pages/settings.css',
    'assets/css/pages/physical_examination_settings.css',
];
include __DIR__ . '/header.php';
?>

<main class="zrx-clinical-settings-page zrx-page-container">
    <header class="zrx-settings-page-hero">
        <div class="zrx-settings-page-hero-copy">
            <a class="zrx-settings-page-back" href="settings.php">Settings</a>
            <h1>Physical Examination Settings</h1>
            <p>Choose which findings appear in the examination table, adjust their order, or add practice-specific parameters.</p>
        </div>
    </header>

    <?php include ZIMRX_BASE_DIR . '/views/modules/pe_settings.php'; ?>
</main>

<script src="assets/js/modules/pe.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/modules/pe.js') ?>"></script>
<?php include __DIR__ . '/footer.php'; ?>
