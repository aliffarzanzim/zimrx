<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_login();

$page_title = 'ZimRx - Settings';
$extra_css = ['assets/css/pages/settings.css'];
include __DIR__ . '/header.php';
?>

<main class="zrx-settings-index zrx-page-container">
    <header class="zrx-settings-page-hero">
        <div class="zrx-settings-page-hero-copy">
            <h1>Settings</h1>
            <p>Configure workspace, clinical, printing, and account settings.</p>
        </div>
    </header>

    <div class="zrx-settings-index-grid">
        <section class="zrx-settings-index-section zrx-settings-index-workspace" aria-labelledby="settings-workspace-title">
            <h2 id="settings-workspace-title"><?= zrx_icon('grid', 18) ?> Application and workspace</h2>
            <div class="zrx-settings-link-grid">
                <a class="zrx-settings-link" href="interface_settings.php"><span>Interface Settings</span><small>Arrange prescription workspace modules and fields.</small></a>
                <a class="zrx-settings-link" href="header_menu_settings.php"><span>Header &amp; Menu Settings</span><small>Set application identity and configure the navigation menu.</small></a>
                <a class="zrx-settings-link" href="practice_profiles.php"><span>Practice Profiles</span><small>Manage chamber profiles, patient sharing, and practice identity.</small></a>
                <a class="zrx-settings-link" href="appointment_settings.php"><span>Appointment Settings</span><small>Configure appointment scheduling.</small></a>
            <?php if (!zimrx_is_multi_doctor($pdo)): ?>
                <a class="zrx-settings-link" href="emr_settings.php"><span>EMR Settings</span><small>Configure electronic medical record options.</small></a>
            <?php endif; ?>
                <a class="zrx-settings-link" href="profile_settings.php"><span>Profile Settings</span><small>Manage your clinician profile.</small></a>
                <a class="zrx-settings-link" href="doctor_assistants.php"><span>Staff Management</span><small>Manage assistant access.</small></a>
            </div>
        </section>

        <section class="zrx-settings-index-section" aria-labelledby="settings-print-title">
            <h2 id="settings-print-title"><?= zrx_icon('printer', 18) ?> Printing and page layout</h2>
            <div class="zrx-settings-link-grid">
                <a class="zrx-settings-link" href="page_setup.php"><span>Page Setup</span><small>Configure printed page dimensions and layout.</small></a>
                <a class="zrx-settings-link" href="print_setup.php"><span>Print Setup</span><small>Choose the clinical details included in printouts.</small></a>
                <a class="zrx-settings-link" href="header_footer_background_setup.php"><span>Header, Footer and Background</span><small>Configure prescription page branding.</small></a>
            </div>
        </section>

        <section class="zrx-settings-index-section zrx-settings-index-clinical" aria-labelledby="settings-clinical-title">
            <h2 id="settings-clinical-title"><?= zrx_icon('clipboard', 18) ?> Clinical configuration</h2>
            <div class="zrx-settings-link-grid">
                <a class="zrx-settings-link" href="patient_particulars_settings.php"><span>Patient Particulars</span><small>Choose standard-field placement and manage custom fields.</small></a>
                <a class="zrx-settings-link" href="visit_form_settings.php"><span>Visit Form Modules</span><small>Build structured forms for prescription visits.</small></a>
                <a class="zrx-settings-link" href="history_settings.php"><span>History Catalog</span><small>Manage available medical and family history entries.</small></a>
                <a class="zrx-settings-link" href="physical_examination_settings.php"><span>Physical Examination Catalog</span><small>Manage examination findings and sections.</small></a>
                <a class="zrx-settings-link" href="presenting_complaint_settings.php"><span>Presenting Complaint Catalog</span><small>Manage complaint suggestions and ordering.</small></a>
                <a class="zrx-settings-link" href="directory_settings.php"><span>Address and Occupation Directories</span><small>Manage address and occupation suggestions.</small></a>
                <a class="zrx-settings-link" href="instruction_template.php"><span>Instruction Presets</span><small>Manage saved medication instructions.</small></a>
                <a class="zrx-settings-link" href="dose_template.php"><span>Dose Presets</span><small>Manage reusable medication doses.</small></a>
                <a class="zrx-settings-link" href="duration_template.php"><span>Duration Presets</span><small>Manage reusable treatment durations.</small></a>
                <a class="zrx-settings-link" href="advice_template.php"><span>Advice Templates</span><small>Manage reusable patient advice.</small></a>
                <a class="zrx-settings-link" href="manufacturer_preferences.php"><span>Manufacturer Preferences</span><small>Configure preferred manufacturers.</small></a>
            </div>
        </section>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>
