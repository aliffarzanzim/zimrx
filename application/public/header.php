<?php
declare(strict_types=1);

// Application-wide header: navigation bar, global CSS/JS assets, CSRF injection, and dropdown themes.

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'header.php') {
    http_response_code(403);
    exit('Direct access forbidden.');
}

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/api/zrx_icons.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/PatientCustomFields.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/VisitFormModules.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/PracticeProfileService.php';
require_once ZIMRX_BASE_DIR . '/lib/app_name_logo.php';
require_once ZIMRX_BASE_DIR . '/lib/app_navigation.php';

if (!headers_sent()) {
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; font-src 'self' data:; img-src 'self' data: blob:; connect-src 'self' http://127.0.0.1:* http://localhost:*; frame-ancestors 'self'; base-uri 'self'; form-action 'self';");
}

// Active route detection for navigation bar highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$pageNavigationGroups = require ZIMRX_BASE_DIR . '/config/page_navigation.php';
$activeSectionHeaderPage = '';
foreach ($pageNavigationGroups as $navigationGroup) {
    $groupHeaderPage = basename((string)($navigationGroup['header_page'] ?? ''));
    if ($groupHeaderPage === '') {
        continue;
    }
    foreach (($navigationGroup['items'] ?? []) as $navigationItem) {
        $itemPage = basename((string)(parse_url((string)($navigationItem['href'] ?? ''), PHP_URL_PATH) ?: ''));
        if ($itemPage !== '' && $itemPage === $current_page) {
            $activeSectionHeaderPage = $groupHeaderPage;
            break 2;
        }
    }
}
$appointments_nav_active = $current_page === 'appointments.php' || $activeSectionHeaderPage === 'appointments.php';
$user_role = current_user_role();
$is_multi_doctor = isset($pdo) ? zimrx_is_multi_doctor($pdo) : false;
$has_chat_colleagues = false;
if (isset($pdo) && $pdo instanceof PDO && is_logged_in()) {
    $chatColleaguesStmt = $pdo->prepare("SELECT COUNT(*) FROM zimrx_user_accounts WHERE is_active = 1 AND id <> :current_user_id AND role IN ('doctor', 'assistant')");
    $chatColleaguesStmt->execute([':current_user_id' => current_user_id()]);
    $has_chat_colleagues = (int)$chatColleaguesStmt->fetchColumn() > 0;
}
$practiceProfiles = [];
$activePracticeProfile = null;
if (isset($pdo) && $pdo instanceof PDO && is_logged_in() && DbSchema::tableExists($pdo, 'zimrx_doctor_profiles')) {
    $practiceProfileService = new \ZimRx\Services\PracticeProfileService($pdo);
    $practiceProfiles = $practiceProfileService->availableForCurrentUser();
    $activePracticeProfile = $practiceProfileService->activeForCurrentUser();
}

$setup_menu_pages = [
    'settings.php', 'header_menu_settings.php', 'directory_settings.php', 'patient_particulars_settings.php', 'visit_form_settings.php', 'history_settings.php', 'physical_examination_settings.php', 'presenting_complaint_settings.php', 'profile_settings.php', 'interface_settings.php', 'page_setup.php', 'print_setup.php',
    'header_footer_background_setup.php', 'appointment_settings.php', 'emr_settings.php', 'doctor_assistants.php',
    'instruction_template.php', 'dose_template.php', 'duration_template.php', 'advice_template.php', 'manufacturer_preferences.php',
];
$setup_menu_active = in_array($current_page, $setup_menu_pages, true);

$finance_menu_pages = ['billings.php', 'performance_dashboard.php'];
$finance_menu_active = in_array($current_page, $finance_menu_pages, true);

$help_menu_pages = [];
$help_menu_active = in_array($current_page, $help_menu_pages, true);

$page_title = isset($page_title) ? $page_title : "ZimRx - Professional EMR";
$zrx_app_name_logo = isset($pdo) && $pdo instanceof PDO ? zimrx_app_name_logo_settings($pdo) : [
    'display_name' => 'ZimRx', 'browser_tab_title' => 'ZimRx EMR System', 'logo_layout' => 'icon_name', 'app_logo' => '',
];
$page_title = zimrx_format_page_title((string)$page_title, $zrx_app_name_logo['browser_tab_title']);
$body_class = isset($body_class) ? trim((string)$body_class) : '';
$home_page = $user_role === 'admin' ? 'admin.php' : ($user_role === 'assistant' ? 'appointments.php' : 'prescription.php');
$allowedDropdownThemes = ['slate-gray', 'subtle-tint', 'charcoal', 'theme-blue', 'soft-blue', 'emerald', 'custom'];
$zrx_dd_theme = $_COOKIE['zimrx_dropdown_theme'] ?? 'slate-gray';
$zrx_dd_bg = $_COOKIE['zimrx_dropdown_hover_bg'] ?? '#868e96';
$zrx_dd_text = $_COOKIE['zimrx_dropdown_hover_text'] ?? '#ffffff';
if (!in_array($zrx_dd_theme, $allowedDropdownThemes, true)) {
    $zrx_dd_theme = 'slate-gray';
}
if (!is_string($zrx_dd_bg) || preg_match('/\A#[0-9a-fA-F]{6}\z/', $zrx_dd_bg) !== 1) {
    $zrx_dd_bg = '#868e96';
}
if (!is_string($zrx_dd_text) || preg_match('/\A#[0-9a-fA-F]{6}\z/', $zrx_dd_text) !== 1) {
    $zrx_dd_text = '#ffffff';
}

require_once ZIMRX_BASE_DIR . '/lib/Services/LocaleService.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/CountryService.php';

$zrx_locales = \ZimRx\Services\LocaleService::default();
$zrx_avail_header_langs = $zrx_locales->availableForCatalog('header');
$zrx_avail_help_langs = $zrx_locales->availableForCatalog('help_guidelines');

$zrx_practice_country = '';
$zrx_interface_lang = '';
$zrx_help_initial_lang = '';
$zrx_clinical_config = '';

if (isset($pdo) && $pdo instanceof PDO && function_exists('zimrx_db_table_exists') && zimrx_db_table_exists($pdo, 'zimrx_app_config')) {
    try {
        $st = $pdo->prepare("SELECT config_key, config_value FROM zimrx_app_config WHERE config_key IN ('practice_country', 'help_lang', 'clinical_lang', 'interface_lang')");
        $st->execute();
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $val = trim((string)$row['config_value']);
            match ($row['config_key']) {
                'practice_country' => $zrx_practice_country = strtoupper($val),
                'interface_lang'   => $zrx_interface_lang = strtolower($val),
                'help_lang'        => $zrx_help_initial_lang = strtolower($val),
                'clinical_lang'    => $zrx_clinical_config = strtolower($val),
                default            => null,
            };
        }
    } catch (Throwable $e) {}
}

// 1. Dynamic interface language resolution (request -> config -> country default -> fallback 'en')
if (!empty($_GET['interface_lang'])) {
    $zrx_interface_lang = strtolower(trim((string)$_GET['interface_lang']));
} elseif (empty($zrx_interface_lang)) {
    $zrx_interface_lang = ($zrx_practice_country !== '') ? (\ZimRx\Services\CountryService::getDefaultClinicalLanguage($zrx_practice_country) ?? 'en') : 'en';
}
if (!array_key_exists($zrx_interface_lang, $zrx_avail_header_langs)) {
    $zrx_interface_lang = 'en';
}

// 2. Dynamic help language resolution
if (empty($zrx_help_initial_lang)) {
    $zrx_help_initial_lang = $zrx_interface_lang;
}
if (!array_key_exists($zrx_help_initial_lang, $zrx_avail_help_langs)) {
    $zrx_help_initial_lang = array_key_exists($zrx_interface_lang, $zrx_avail_help_langs)
        ? $zrx_interface_lang
        : (string)(array_key_first($zrx_avail_help_langs) ?? 'en');
}

// 3. Clinical language resolution
$zrx_clinical_lang = function_exists('zimrx_clinical_language')
    ? zimrx_clinical_language($pdo ?? null, current_user_doctor_id())
    : ($zrx_clinical_config !== '' ? $zrx_clinical_config : $zrx_interface_lang);

$zrx_initial_guidelines = [];
$zrx_hdr_strings = [];
try {
    $zrx_initial_guidelines = $zrx_locales->catalog($zrx_help_initial_lang, 'help_guidelines');
    $zrx_hdr_strings = $zrx_locales->catalog($zrx_interface_lang, 'header');
} catch (Throwable $e) {}

$th = static fn (string $key): string => htmlspecialchars((string)($zrx_hdr_strings[$key] ?? ''), ENT_QUOTES, 'UTF-8');
$headerMenuPreferences = isset($pdo) && $pdo instanceof PDO
    ? zimrx_load_header_menu_preferences($pdo)
    : zimrx_default_header_menu_preferences();
$isHeaderMenuLinkActive = static function (string $href) use ($current_page, $appointments_nav_active, $setup_menu_active): bool {
    if (str_starts_with($href, 'https://')) {
        return false;
    }
    $page = basename((string)(parse_url($href, PHP_URL_PATH) ?: ''));
    if ($page === 'appointments.php') {
        return $appointments_nav_active;
    }
    if (in_array($page, ['settings.php', 'header_menu_settings.php', 'interface_settings.php', 'appointment_settings.php', 'emr_settings.php', 'page_setup.php', 'print_setup.php', 'header_footer_background_setup.php', 'profile_settings.php', 'doctor_assistants.php'], true)) {
        return $setup_menu_active;
    }
    return $page !== '' && $page === $current_page;
};
$doctorPrimaryNavigation = [];
foreach ($headerMenuPreferences['items'] as $menuItem) {
    if (!$menuItem['visible']) {
        continue;
    }
    $menuItem['label'] = htmlspecialchars(zimrx_header_menu_label($menuItem, $zrx_hdr_strings), ENT_QUOTES, 'UTF-8');
    $menuItem['panel_id'] = 'header-menu-panel-' . $menuItem['id'];
    if ($menuItem['type'] === 'link') {
        $menuItem['active'] = $isHeaderMenuLinkActive($menuItem['href']);
    } else {
        $menuItem['children'] = array_values(array_filter($menuItem['children'], static fn(array $child): bool => $child['visible']));
        if ($menuItem['children'] === []) {
            continue;
        }
        $menuItem['active'] = false;
        foreach ($menuItem['children'] as &$child) {
            $child['label'] = htmlspecialchars(zimrx_header_menu_label($child, $zrx_hdr_strings), ENT_QUOTES, 'UTF-8');
            $child['active'] = $child['type'] === 'link' && $isHeaderMenuLinkActive($child['href']);
            $menuItem['active'] = $menuItem['active'] || $child['active'];
        }
        unset($child);
    }
    $doctorPrimaryNavigation[] = $menuItem;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($zrx_interface_lang) ?>" data-dropdown-theme="<?= htmlspecialchars($zrx_dd_theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link rel="stylesheet" href="assets/vendor/flatpickr/flatpickr.min.css">
    <link rel="stylesheet" href="assets/css/layout/global.css?v=<?= filemtime(__DIR__ . '/assets/css/layout/global.css') ?>">
    <?php if (!empty($zrx_dd_bg) && $zrx_dd_bg !== '#868e96'): ?>
    <style id="zrx-custom-dropdown-theme">
    :root {
        --zrx-dropdown-hover-bg: <?= htmlspecialchars($zrx_dd_bg) ?>;
        --zrx-dropdown-hover-text: <?= htmlspecialchars(!empty($zrx_dd_text) ? $zrx_dd_text : '#ffffff') ?>;
    }
    </style>
    <?php endif; ?>
    <?php if ($has_chat_colleagues): ?>
        <link rel="stylesheet" href="assets/css/pages/chat.css?v=<?= file_exists(__DIR__ . '/assets/css/pages/chat.css') ? filemtime(__DIR__ . '/assets/css/pages/chat.css') : '1' ?>">
    <?php endif; ?>
    <?php if (!empty($extra_css) && is_array($extra_css)): ?>
        <?php foreach ($extra_css as $css_file): ?>
            <?php $css_path = __DIR__ . '/' . ltrim($css_file, '/'); ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($css_file) ?><?= file_exists($css_path) ? '?v=' . filemtime($css_path) : '' ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <?php
    $zrx_saved_tbl_cols = [];
    $zrx_saved_part_fields = [];
    $zrx_saved_custom_patient_fields = [];
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

                $st2 = $pdo->prepare("SELECT setting_value FROM zimrx_interface_settings WHERE doctor_id = ? AND setting_scope = 'particulars_fields' AND setting_key = 'field_placement' LIMIT 1");
                $st2->execute([$docId]);
                $val = $st2->fetchColumn();
                if ($val) {
                    $decoded = json_decode((string)$val, true);
                    if (is_array($decoded)) {
                        $zrx_saved_part_fields = $decoded;
                    }
                }

                $zrx_saved_custom_patient_fields = \ZimRx\Services\PatientCustomFields::loadForDoctor($pdo, $docId);
            }
        } catch (Throwable $e) {
            error_log('[ZimRx] Unable to load interface settings: ' . $e->getMessage());
        }
    }
    ?>
    <?php $zrx_inline_json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT; ?>
    <script>
    window.ZimRxIconsMap = <?= json_encode(ZimRxIcon::getAll(), JSON_UNESCAPED_SLASHES | $zrx_inline_json_flags) ?>;
    window.ZimRxSavedTableColumns = <?= json_encode($zrx_saved_tbl_cols, JSON_UNESCAPED_SLASHES | $zrx_inline_json_flags) ?>;
    window.ZimRxSavedParticularsFields = <?= json_encode($zrx_saved_part_fields, JSON_UNESCAPED_SLASHES | $zrx_inline_json_flags) ?>;
    window.ZimRxCustomPatientFields = <?= json_encode($zrx_saved_custom_patient_fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | $zrx_inline_json_flags) ?>;
    window.ZimRxCsrfToken = <?= json_encode(function_exists('zimrx_csrf_token') ? zimrx_csrf_token() : '', $zrx_inline_json_flags) ?>;
    window.ZimRxPracticeCountry = <?= json_encode($zrx_practice_country, JSON_UNESCAPED_SLASHES | $zrx_inline_json_flags) ?>;
    window.ZimRxClinicalLang = <?= json_encode($zrx_clinical_lang, $zrx_inline_json_flags) ?>;
    window.ZimRxInterfaceLang = <?= json_encode($zrx_interface_lang, $zrx_inline_json_flags) ?>;
    window.ZimRxHelpInitialLang = <?= json_encode($zrx_help_initial_lang, $zrx_inline_json_flags) ?>;
    window.ZimRxHelpGuidelinesCatalogs = {
        <?= json_encode($zrx_help_initial_lang, $zrx_inline_json_flags) ?>: <?= json_encode($zrx_initial_guidelines, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | $zrx_inline_json_flags) ?>
    };


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
    <script src="assets/js/layout/zrx_icons.js?v=<?= file_exists(__DIR__ . '/assets/js/layout/zrx_icons.js') ? filemtime(__DIR__ . '/assets/js/layout/zrx_icons.js') : '1' ?>" defer></script>
    <script src="assets/js/layout/zrx_modal.js?v=<?= file_exists(__DIR__ . '/assets/js/layout/zrx_modal.js') ? filemtime(__DIR__ . '/assets/js/layout/zrx_modal.js') : '1' ?>" defer></script>
    <script src="assets/js/layout/zrx_dropdown.js?v=<?= file_exists(__DIR__ . '/assets/js/layout/zrx_dropdown.js') ? filemtime(__DIR__ . '/assets/js/layout/zrx_dropdown.js') : '1' ?>" defer></script>
    <script src="assets/vendor/flatpickr/flatpickr.min.js" defer></script>
    <script src="assets/js/layout/zrx_datepicker.js?v=<?= file_exists(__DIR__ . '/assets/js/layout/zrx_datepicker.js') ? filemtime(__DIR__ . '/assets/js/layout/zrx_datepicker.js') : '1' ?>" defer></script>
    <script src="assets/js/pages/emr_scanner.js" defer></script>
    <?php if ($has_chat_colleagues): ?>
        <script src="assets/js/pages/chat.js?v=<?= file_exists(__DIR__ . '/assets/js/pages/chat.js') ? filemtime(__DIR__ . '/assets/js/pages/chat.js') : '1' ?>" defer></script>
    <?php endif; ?>
</head>
<body<?= $body_class !== '' ? ' class="' . htmlspecialchars($body_class, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

    <header class="app-header">
        <?php
            $logoLayout = $zrx_app_name_logo['logo_layout'];
            $useNavbarLogo = in_array($logoLayout, ['logo_name', 'logo'], true) && $zrx_app_name_logo['app_logo'] !== '';
            $showAppIcon = $logoLayout === 'icon_name' || (in_array($logoLayout, ['logo_name', 'logo'], true) && !$useNavbarLogo);
            $showAppName = in_array($logoLayout, ['icon_name', 'name', 'logo_name'], true) || !$useNavbarLogo;
        ?>
        <a class="app-logo<?= $logoLayout === 'logo' && $useNavbarLogo ? ' app-logo--logo-only' : '' ?>" href="<?= htmlspecialchars($home_page, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($zrx_app_name_logo['display_name'], ENT_QUOTES, 'UTF-8') ?> home">
            <?php if ($useNavbarLogo): ?>
                <img class="app-logo-image" src="<?= htmlspecialchars($zrx_app_name_logo['app_logo'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($zrx_app_name_logo['display_name'], ENT_QUOTES, 'UTF-8') ?>">
            <?php elseif ($showAppIcon): ?>
                <?= zrx_icon('activity', 24) ?>
            <?php endif; ?>
            <?php if ($showAppName): ?><span><?= htmlspecialchars($zrx_app_name_logo['display_name'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
        </a>
        <div class="top-nav-wrapper" id="top-nav-wrapper">
            <button type="button" class="nav-scroll-arrow nav-scroll-left" id="nav-scroll-left" aria-label="<?= $th('scroll_nav_left_aria') ?>" hidden>
                <?= zrx_icon('chevron-left', 12) ?>
            </button>
            <nav class="top-nav" id="app-top-nav">
                <?php if ($user_role === 'admin'): ?>
                <a href="admin.php" class="nav-link <?= $current_page == 'admin.php' ? 'active' : '' ?>"><?= $th('nav_dashboard') ?></a>
                <a href="emr.php" class="nav-link <?= $current_page == 'emr.php' ? 'active' : '' ?>"><?= $th('nav_emr') ?></a>
                <a href="admin_doctors.php" class="nav-link <?= $current_page == 'admin_doctors.php' ? 'active' : '' ?>"><?= $th('nav_manage_doctors') ?></a>
                <a href="admin_assistants.php" class="nav-link <?= $current_page == 'admin_assistants.php' ? 'active' : '' ?>"><?= $th('nav_manage_assistants') ?></a>
                <a href="billings.php" class="nav-link <?= $current_page == 'billings.php' ? 'active' : '' ?>"><?= $th('nav_billings') ?></a>
                <a href="emr_settings.php" class="nav-link <?= $current_page == 'emr_settings.php' ? 'active' : '' ?>"><?= $th('nav_emr_settings') ?></a>
                <?php elseif ($user_role === 'assistant'): ?>
                <a href="appointments.php" class="nav-link <?= $appointments_nav_active ? 'active' : '' ?>"><?= $th('nav_appointments') ?></a>
                <a href="emr.php" class="nav-link <?= $current_page == 'emr.php' ? 'active' : '' ?>"><?= $th('nav_emr') ?></a>
                <?php else: ?>
                <?php foreach ($doctorPrimaryNavigation as $navigationItem): ?>
                    <?php $menuWidth = in_array($navigationItem['width'], ['compact', 'standard', 'wide'], true) ? $navigationItem['width'] : 'standard'; ?>
                    <?php if ($navigationItem['type'] === 'link'): ?>
                        <a href="<?= htmlspecialchars($navigationItem['href'], ENT_QUOTES, 'UTF-8') ?>" class="nav-link nav-size-<?= $menuWidth ?> <?= $navigationItem['active'] ? 'active' : '' ?>" data-nav-width="<?= $menuWidth ?>"<?= str_starts_with($navigationItem['href'], 'https://') ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= $navigationItem['label'] ?></a>
                    <?php else: ?>
                        <button type="button" id="header-menu-toggle-<?= htmlspecialchars($navigationItem['id'], ENT_QUOTES, 'UTF-8') ?>" class="nav-link nav-button nav-size-<?= $menuWidth ?> <?= $navigationItem['active'] ? 'active' : '' ?>" data-nav-width="<?= $menuWidth ?>" data-nav-menu-toggle data-nav-menu-panel="<?= htmlspecialchars($navigationItem['panel_id'], ENT_QUOTES, 'UTF-8') ?>" aria-controls="<?= htmlspecialchars($navigationItem['panel_id'], ENT_QUOTES, 'UTF-8') ?>" aria-haspopup="true" aria-expanded="false"><?= $navigationItem['label'] ?> <?= zrx_icon('chevron-down', 11) ?></button>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php endif; ?>
            </nav>
            <button type="button" class="nav-scroll-arrow nav-scroll-right" id="nav-scroll-right" aria-label="<?= $th('scroll_nav_right_aria') ?>" hidden>
                <?= zrx_icon('chevron-right', 12) ?>
            </button>
        </div>

        <?php if (count($practiceProfiles) > 1 && $activePracticeProfile !== null): ?>
            <div class="header-practice-profile">
                <select id="header-practice-profile-select" class="zrx-form-control zrx-select" aria-label="Chamber" data-active-profile="<?= (int)$activePracticeProfile['id'] ?>">
                    <?php foreach ($practiceProfiles as $practiceProfile): ?>
                        <option value="<?= (int)$practiceProfile['id'] ?>" <?= (int)$practiceProfile['id'] === (int)$activePracticeProfile['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$practiceProfile['profile_name'], ENT_QUOTES, 'UTF-8') ?><?= in_array($user_role, ['admin', 'superadmin', 'assistant'], true) ? ' - ' . htmlspecialchars((string)$practiceProfile['doctor_name'], ENT_QUOTES, 'UTF-8') : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($user_role === 'doctor'): ?>
        <?php foreach ($doctorPrimaryNavigation as $navigationItem): ?>
            <?php if ($navigationItem['type'] !== 'group') { continue; } ?>
            <div id="<?= htmlspecialchars($navigationItem['panel_id'], ENT_QUOTES, 'UTF-8') ?>" class="floating-nav-menu" aria-labelledby="header-menu-toggle-<?= htmlspecialchars($navigationItem['id'], ENT_QUOTES, 'UTF-8') ?>" hidden>
                <?php foreach ($navigationItem['children'] as $child): ?>
                    <?php if ($child['type'] === 'action' && $child['action'] === 'support'): ?>
                        <button type="button" class="nav-link nav-button" data-nav-action="support"><?= $child['label'] ?></button>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($child['href'], ENT_QUOTES, 'UTF-8') ?>" class="nav-link <?= $child['active'] ? 'active' : '' ?>"<?= str_starts_with($child['href'], 'https://') ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= $child['label'] ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (is_logged_in()): ?>
        <div style="margin-left: auto; display: flex; align-items: center; gap: 14px;">
            <?php if ($has_chat_colleagues): ?>
                <button type="button" class="header-chat-btn" id="btn-header-chat" title="<?= $th('chat_btn_title') ?>" aria-label="<?= $th('chat_btn_aria') ?>">
                    <?= zrx_icon('message-square', 22) ?>
                    <span class="header-chat-badge" id="header-chat-unread-badge" style="display: none;">0</span>
                </button>
            <?php endif; ?>
            <div style="font-size: 0.85rem; color: var(--zrx-slate-300); font-weight: 500;">
                <span style="color: var(--zrx-sky-400); font-weight: 700;"><?= htmlspecialchars(current_user_name()) ?></span>
            </div>
            <a href="logout.php" class="nav-link" style="color: var(--zrx-red-500); background: var(--zrx-danger-bg);"><?= $th('nav_logout') ?></a>
        </div>
        <?php endif; ?>
    </header>
    <?php if (in_array($current_page, ['prescription.php', 'appointments.php', 'interface_settings.php'], true)): ?>
        <?php include ZIMRX_BASE_DIR . '/views/components/help_guidelines_modal.php'; ?>
    <?php endif; ?>
    <?php if ($user_role === 'doctor'): ?>
    <script src="assets/js/layout/header_nav.js?v=<?= file_exists(__DIR__ . '/assets/js/layout/header_nav.js') ? filemtime(__DIR__ . '/assets/js/layout/header_nav.js') : '1' ?>" defer></script>
    <?php endif; ?>
    <script src="assets/js/layout/practice_profile_switcher.js?v=<?= file_exists(__DIR__ . '/assets/js/layout/practice_profile_switcher.js') ? filemtime(__DIR__ . '/assets/js/layout/practice_profile_switcher.js') : '1' ?>" defer></script>

