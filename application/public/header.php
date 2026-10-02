<?php
declare(strict_types=1);

// Application-wide header: navigation bar, global CSS/JS assets, CSRF injection, and dropdown themes.

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/api/zrx_icons.php';

// Active route detection for navigation bar highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = current_user_role();
$is_multi_doctor = isset($pdo) ? zimrx_is_multi_doctor($pdo) : false;

$setup_menu_pages = [
    'profile_settings.php', 'setup.php', 'page_setup.php', 'print_setup.php',
    'header_footer_background_setup.php', 'appointment_settings.php', 'front_desk_settings.php', 'health_card_settings.php',
    'invoice_settings.php', 'emr_settings.php', 'backup_restore.php', 'audit_log.php',
    'doctor_assistants.php',
];
$setup_menu_active = in_array($current_page, $setup_menu_pages, true);

$template_menu_pages = [
    'instruction_template.php', 'dose_template.php', 'duration_template.php',
    'advice_template.php', 'regimen_templates.php', 'full_prescription_template.php',
    'investigation_template.php', 'drug_template.php', 'referral_settings.php',
    'drug_company_priority.php',
];
$template_menu_active = in_array($current_page, $template_menu_pages, true);

$finance_menu_pages = ['billings.php', 'performance_dashboard.php'];
$finance_menu_active = in_array($current_page, $finance_menu_pages, true);

$help_menu_pages = [
    'study_materials.php', 'treatment_guidelines.php', 'medical_calculators.php',
    'documentation.php', 'updates.php', 'about.php',
];
$help_menu_active = in_array($current_page, $help_menu_pages, true);

$page_title = isset($page_title) ? $page_title : "ZimRx - Professional EMR";
$body_class = isset($body_class) ? trim((string)$body_class) : '';
$home_page = $user_role === 'admin' ? 'admin.php' : ($user_role === 'assistant' ? 'appointments.php' : 'prescription.php');
$zrx_dd_theme = $_COOKIE['zimrx_dropdown_theme'] ?? 'subtle-tint';
?>
<!DOCTYPE html>
<html lang="en" data-dropdown-theme="<?= htmlspecialchars($zrx_dd_theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link rel="stylesheet" href="assets/vendor/flatpickr/flatpickr.min.css">
    <link rel="stylesheet" href="assets/css/layout/global.css?v=<?= filemtime(__DIR__ . '/assets/css/layout/global.css') ?>">
    <?php
    $zrx_dd_bg = $_COOKIE['zimrx_dropdown_hover_bg'] ?? '';
    $zrx_dd_text = $_COOKIE['zimrx_dropdown_hover_text'] ?? '';
    if (!empty($zrx_dd_bg) && preg_match('/^#[0-9a-fA-F]{3,8}$|^rgba?\([0-9,\.\s]+\)$/', $zrx_dd_bg)):
    ?>
    <style id="zrx-custom-dropdown-theme">
    :root {
        --zrx-dropdown-hover-bg: <?= htmlspecialchars($zrx_dd_bg) ?>;
        --zrx-dropdown-hover-text: <?= htmlspecialchars(!empty($zrx_dd_text) ? $zrx_dd_text : '#ffffff') ?>;
    }
    </style>
    <?php endif; ?>
    <link rel="stylesheet" href="assets/css/pages/chat.css?v=<?= file_exists(__DIR__ . '/assets/css/pages/chat.css') ? filemtime(__DIR__ . '/assets/css/pages/chat.css') : '1' ?>">
    <?php if (!empty($extra_css) && is_array($extra_css)): ?>
        <?php foreach ($extra_css as $css_file): ?>
            <?php $css_path = __DIR__ . '/' . ltrim($css_file, '/'); ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($css_file) ?><?= file_exists($css_path) ? '?v=' . filemtime($css_path) : '' ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <?php
    $zrx_saved_tbl_cols = [];
    if (isset($pdo) && $pdo instanceof PDO && function_exists('current_user_doctor_id')) {
        try {
            $docId = current_user_doctor_id();
            if ($docId) {
                $st = $pdo->prepare("SELECT setting_key, setting_value FROM zimrx_interface_settings WHERE doctor_id = ? AND setting_scope = 'table_columns'");
                $st->execute([$docId]);
                while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                    $decoded = json_decode($r['setting_value'], true);
                    if (is_array($decoded)) {
                        $zrx_saved_tbl_cols[$r['setting_key']] = $decoded;
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[ZimRx] Unable to load saved table-column settings: ' . $e->getMessage());
        }
    }
    ?>
    <script>
    window.ZimRxIconsMap = <?= json_encode(ZimRxIcon::getAll(), JSON_UNESCAPED_SLASHES) ?>;
    window.ZimRxSavedTableColumns = <?= json_encode($zrx_saved_tbl_cols, JSON_UNESCAPED_SLASHES) ?>;
    window.ZimRxCsrfToken = <?= json_encode(function_exists('zimrx_csrf_token') ? zimrx_csrf_token() : '') ?>;
    window.ZimRxUtils = window.ZimRxUtils || {};
    window.ZimRxUtils.escapeHtml = function(value) {
        if (value == null) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };
    window.escapeHtml = window.ZimRxUtils.escapeHtml;
    (function() {
        if (!window.fetch || !window.ZimRxCsrfToken) return;
        const _origFetch = window.fetch;
        window.fetch = function(resource, init) {
            init = init || {};
            const method = (init.method || 'GET').toUpperCase();
            if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
                if (!init.headers) {
                    init.headers = { 'X-CSRF-TOKEN': window.ZimRxCsrfToken };
                } else if (init.headers instanceof Headers) {
                    if (!init.headers.has('X-CSRF-TOKEN')) {
                        init.headers.set('X-CSRF-TOKEN', window.ZimRxCsrfToken);
                    }
                } else if (Array.isArray(init.headers)) {
                    if (!init.headers.some(h => (h[0] || '').toLowerCase() === 'x-csrf-token')) {
                        init.headers.push(['X-CSRF-TOKEN', window.ZimRxCsrfToken]);
                    }
                } else if (typeof init.headers === 'object') {
                    if (!init.headers['X-CSRF-TOKEN'] && !init.headers['x-csrf-token']) {
                        init.headers['X-CSRF-TOKEN'] = window.ZimRxCsrfToken;
                    }
                }
            }
            return _origFetch.call(this, resource, init);
        };
    })();
    </script>
    <script src="assets/js/layout/zrx_icons.js?v=<?= file_exists(__DIR__ . '/assets/js/layout/zrx_icons.js') ? filemtime(__DIR__ . '/assets/js/layout/zrx_icons.js') : '1' ?>"></script>
    <script src="assets/js/layout/zrx_dropdown.js?v=<?= file_exists(__DIR__ . '/assets/js/layout/zrx_dropdown.js') ? filemtime(__DIR__ . '/assets/js/layout/zrx_dropdown.js') : '1' ?>"></script>
    <script src="assets/js/pages/emr_scanner.js" defer></script>
    <script src="assets/js/pages/chat.js?v=<?= file_exists(__DIR__ . '/assets/js/pages/chat.js') ? filemtime(__DIR__ . '/assets/js/pages/chat.js') : '1' ?>" defer></script>
</head>
<body<?= $body_class !== '' ? ' class="' . htmlspecialchars($body_class, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

    <header class="app-header">
        <a class="brand-logo" href="<?= htmlspecialchars($home_page) ?>" aria-label="ZimRx home">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
            </svg>
            <span>ZimRx</span>
        </a>
        <div class="top-nav-wrapper" id="top-nav-wrapper">
            <button type="button" class="nav-scroll-arrow nav-scroll-left" id="nav-scroll-left" aria-label="Scroll navigation left" hidden>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <nav class="top-nav" id="app-top-nav">
                <?php if ($user_role === 'admin'): ?>
                <a href="admin.php" class="nav-link <?= $current_page == 'admin.php' ? 'active' : '' ?>">Dashboard</a>
                <a href="emr.php" class="nav-link <?= $current_page == 'emr.php' ? 'active' : '' ?>">EMR</a>
                <a href="admin_doctors.php" class="nav-link <?= $current_page == 'admin_doctors.php' ? 'active' : '' ?>">Manage Doctors</a>
                <a href="admin_assistants.php" class="nav-link <?= $current_page == 'admin_assistants.php' ? 'active' : '' ?>">Manage Assistants</a>
                <a href="billings.php" class="nav-link <?= $current_page == 'billings.php' ? 'active' : '' ?>">Billings</a>
                <a href="emr_settings.php" class="nav-link <?= $current_page == 'emr_settings.php' ? 'active' : '' ?>">EMR Settings</a>
                <?php elseif ($user_role === 'assistant'): ?>
                <a href="appointments.php" class="nav-link <?= $current_page == 'appointments.php' ? 'active' : '' ?>">Appointments</a>
                <a href="emr.php" class="nav-link <?= $current_page == 'emr.php' ? 'active' : '' ?>">EMR</a>
                <?php else: ?>
                <a href="prescription.php" class="nav-link <?= $current_page == 'prescription.php' ? 'active' : '' ?>">New Prescription</a>
                <a href="appointments.php" class="nav-link <?= $current_page == 'appointments.php' ? 'active' : '' ?>">Appointments</a>
                <a href="emr.php" class="nav-link <?= $current_page == 'emr.php' ? 'active' : '' ?>">EMR</a>
                <a href="drug_db.php" class="nav-link <?= $current_page == 'drug_db.php' ? 'active' : '' ?>">Drug DB</a>
                <button type="button" id="template-menu-toggle" class="nav-link nav-button <?= $template_menu_active ? 'active' : '' ?>" aria-haspopup="true" aria-expanded="false">Templates ▾</button>
                <button type="button" id="finance-menu-toggle" class="nav-link nav-button <?= $finance_menu_active ? 'active' : '' ?>" aria-haspopup="true" aria-expanded="false">Billings &amp; Stats ▾</button>
                <button type="button" id="setup-menu-toggle" class="nav-link nav-button <?= $setup_menu_active ? 'active' : '' ?>" aria-haspopup="true" aria-expanded="false">Settings ▾</button>
                <button type="button" id="help-menu-toggle" class="nav-link nav-button <?= $help_menu_active ? 'active' : '' ?>" aria-haspopup="true" aria-expanded="false">Help ▾</button>
                <?php endif; ?>
            </nav>
            <button type="button" class="nav-scroll-arrow nav-scroll-right" id="nav-scroll-right" aria-label="Scroll navigation right" hidden>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>

        <?php if ($user_role === 'doctor'): ?>
        <!-- Templates Menu Panel -->
        <div id="template-menu-panel" class="floating-nav-menu" hidden>
            <div class="floating-nav-subtitle">Templates</div>
            <a href="#" class="nav-link">Rx Template</a>
            <a href="#" class="nav-link">Full Prescription Template</a>
            <a href="advice_template.php" class="nav-link <?= $current_page == 'advice_template.php' ? 'active' : '' ?>">Advice Templates</a>
            <a href="#" class="nav-link">Investigation Templates</a>

            <div class="floating-nav-subtitle">Presets</div>
            <a href="#" class="nav-link">Drug Presets</a>
            <a href="instruction_template.php" class="nav-link <?= $current_page == 'instruction_template.php' ? 'active' : '' ?>">Instruction Presets</a>
            <a href="dose_template.php" class="nav-link <?= $current_page == 'dose_template.php' ? 'active' : '' ?>">Dose Presets</a>
            <a href="duration_template.php" class="nav-link <?= $current_page == 'duration_template.php' ? 'active' : '' ?>">Duration Presets</a>

            <div class="floating-nav-subtitle">Others</div>
            <a href="#" class="nav-link">Referrals</a>
            <a href="manufacturer_preferences.php" class="nav-link <?= $current_page == 'manufacturer_preferences.php' ? 'active' : '' ?>">Manufacturer Preferences</a>
        </div>

        <!-- Billings & Stats Menu Panel -->
        <div id="finance-menu-panel" class="floating-nav-menu" hidden>
            <div class="floating-nav-subtitle">Financials &amp; Analytics</div>
            <a href="billings.php" class="nav-link <?= $current_page == 'billings.php' ? 'active' : '' ?>">Billings</a>
            <a href="performance_dashboard.php" class="nav-link <?= $current_page == 'performance_dashboard.php' ? 'active' : '' ?>">Performance Dashboard</a>
        </div>

        <!-- Settings Menu Panel -->
        <div id="setup-menu-panel" class="floating-nav-menu" hidden>
            <div class="floating-nav-subtitle">Layout &amp; Print</div>
            <a href="profile_settings.php" class="nav-link <?= $current_page == 'profile_settings.php' ? 'active' : '' ?>">Profile Settings</a>
            <a href="setup.php" class="nav-link <?= $current_page == 'setup.php' ? 'active' : '' ?>">Software Interface Setup</a>
            <a href="page_setup.php" class="nav-link <?= $current_page == 'page_setup.php' ? 'active' : '' ?>">Page Setup</a>
            <a href="print_setup.php" class="nav-link <?= $current_page == 'print_setup.php' ? 'active' : '' ?>">Print Setup</a>
            <a href="header_footer_background_setup.php" class="nav-link <?= $current_page == 'header_footer_background_setup.php' ? 'active' : '' ?>">Header, Footer &amp; BG Setup</a>

            <div class="floating-nav-subtitle">Clinic &amp; Operations</div>
            <a href="appointment_settings.php" class="nav-link <?= $current_page == 'appointment_settings.php' ? 'active' : '' ?>">Appointment Settings</a>
            <a href="#" class="nav-link">Front Desk Screen Settings</a>
            <a href="#" class="nav-link">Health Card Settings</a>
            <a href="#" class="nav-link">Invoice Settings</a>
            <?php if (!$is_multi_doctor): ?>
            <a href="emr_settings.php" class="nav-link <?= $current_page == 'emr_settings.php' ? 'active' : '' ?>">EMR Settings</a>
            <?php endif; ?>

            <div class="floating-nav-subtitle">Administration &amp; Security</div>
            <a href="doctor_assistants.php" class="nav-link <?= $current_page == 'doctor_assistants.php' ? 'active' : '' ?>">Staff Management</a>
            <a href="#" class="nav-link">Backup &amp; Restore</a>
            <a href="#" class="nav-link">Audit Log</a>
        </div>

        <!-- Help Menu Panel -->
        <div id="help-menu-panel" class="floating-nav-menu" hidden>
            <div class="floating-nav-subtitle">Clinical Reference</div>
            <a href="#" class="nav-link">Study Materials</a>
            <a href="#" class="nav-link">National Guidelines</a>
            <a href="#" class="nav-link">Medical Calculators</a>

            <div class="floating-nav-subtitle">Software &amp; Support</div>
            <button type="button" class="nav-link nav-button" onclick="zimrxOpenSupportModal()">❤️ Support ZimRx</button>
            <a href="#" class="nav-link">Documentation</a>
            <a href="#" class="nav-link">Updates</a>
            <a href="#" class="nav-link">About</a>
        </div>
        <?php endif; ?>
        
        <?php if (is_logged_in()): ?>
        <div style="margin-left: auto; display: flex; align-items: center; gap: 14px;">
            <button type="button" class="header-chat-btn" id="btn-header-chat" title="Internal Messages / Team Chat" aria-label="Open Team Chat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <span class="header-chat-badge" id="header-chat-unread-badge" style="display: none;">0</span>
            </button>
            <div style="font-size: 0.85rem; color: var(--zrx-slate-300); font-weight: 500;">
                <span style="color: var(--zrx-sky-400); font-weight: 700;"><?= htmlspecialchars(current_user_name()) ?></span>
            </div>
            <a href="logout.php" class="nav-link" style="color: var(--zrx-red-500); background: var(--zrx-danger-bg);">Logout</a>
        </div>
        <?php endif; ?>
    </header>
    <?php if ($user_role === 'doctor'): ?>
    <script src="assets/js/layout/header_nav.js?v=<?= file_exists(__DIR__ . '/assets/js/layout/header_nav.js') ? filemtime(__DIR__ . '/assets/js/layout/header_nav.js') : '1' ?>" defer></script>
    <?php endif; ?>

