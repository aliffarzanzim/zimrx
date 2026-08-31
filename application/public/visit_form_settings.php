<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/VisitFormModules.php';
require_login();

$definitions = \ZimRx\Services\VisitFormModules::loadForDoctor($pdo, current_user_doctor_id());
$page_title = 'ZimRx - Visit Form Modules';
$extra_css = ['assets/css/pages/settings.css', 'assets/css/pages/visit_form_settings.css'];
include __DIR__ . '/header.php';
?>

<main class="zrx-visit-form-settings zrx-page-container">
    <header class="zrx-settings-page-hero">
        <div class="zrx-settings-page-hero-copy">
            <a class="zrx-settings-page-back" href="settings.php">Settings</a>
            <h1>Visit Form Modules</h1>
            <p>Create simple declarative clinical forms for the prescription workspace. Values are saved with the visit and become part of its revision history.</p>
        </div>
    </header>
    <div class="visit-form-builder-toolbar">
        <p>Supported fields: text, paragraph, number, date, and choice. Form packages contain definitions only; they cannot run code.</p>
        <div>
            <input type="file" id="visit-form-package-file" accept="application/json,.json" hidden>
            <button type="button" id="visit-form-import" class="btn btn-outline btn-sm">Import Package</button>
            <button type="button" id="visit-form-export" class="btn btn-outline btn-sm">Export Package</button>
            <button type="button" id="visit-form-add-module" class="btn btn-outline btn-sm">Add Module</button>
        </div>
    </div>
    <div id="visit-form-modules" class="visit-form-module-list" aria-live="polite"></div>
    <footer class="visit-form-builder-footer">
        <span id="visit-form-settings-status" role="status" aria-live="polite"></span>
        <button type="button" id="visit-form-save" class="btn btn-primary btn-sm">Save Modules</button>
    </footer>
</main>
<script id="visit-form-definitions" type="application/json"><?= json_encode($definitions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="assets/js/pages/visit_form_settings.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/visit_form_settings.js') ?>"></script>
<?php include __DIR__ . '/footer.php'; ?>
