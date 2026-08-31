<?php
require_once 'auth.php';
require_once 'db.php';
require_once __DIR__ . '/api/zrx_icons.php';
include_once 'coffee_modal.php';
// Dynamically check which page we are on to highlight the active menu link
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

// Set a default page title if one isn't provided
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
    <!-- Professional Modern Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Flatpickr for the Professional Date Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Global Custom CSS -->
    <link rel="stylesheet" href="assets/css/global.css?v=<?= filemtime(__DIR__ . '/assets/css/global.css') ?>">
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
    <link rel="stylesheet" href="assets/css/chat.css?v=<?= file_exists(__DIR__ . '/assets/css/chat.css') ? filemtime(__DIR__ . '/assets/css/chat.css') : '1' ?>">
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
        } catch (Throwable $e) {}
    }
    ?>
    <script>
    window.ZimRxIconsMap = <?= json_encode(ZimRxIcon::getAll(), JSON_UNESCAPED_SLASHES) ?>;
    window.ZimRxSavedTableColumns = <?= json_encode($zrx_saved_tbl_cols, JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="assets/js/layout/zrx_icons.js?v=<?= file_exists(__DIR__ . '/assets/js/layout/zrx_icons.js') ? filemtime(__DIR__ . '/assets/js/layout/zrx_icons.js') : '1' ?>"></script>
    <script src="assets/js/layout/zrx_dropdown.js?v=<?= file_exists(__DIR__ . '/assets/js/layout/zrx_dropdown.js') ? filemtime(__DIR__ . '/assets/js/layout/zrx_dropdown.js') : '1' ?>"></script>
    <script src="assets/js/emr_scanner.js" defer></script>
    <script src="assets/js/chat.js?v=<?= file_exists(__DIR__ . '/assets/js/chat.js') ? filemtime(__DIR__ . '/assets/js/chat.js') : '1' ?>" defer></script>
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
                <a href="admin_patients.php" class="nav-link <?= $current_page == 'admin_patients.php' ? 'active' : '' ?>">Manage Patients</a>
                <a href="admin_payments.php" class="nav-link <?= $current_page == 'admin_payments.php' ? 'active' : '' ?>">Payments</a>
                <a href="admin_settings.php" class="nav-link <?= $current_page == 'admin_settings.php' ? 'active' : '' ?>">Settings</a>
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
        <!-- ── Templates Menu Panel ── -->
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

        <!-- ── Billings & Stats Menu Panel ── -->
        <div id="finance-menu-panel" class="floating-nav-menu" hidden>
            <div class="floating-nav-subtitle">Financials &amp; Analytics</div>
            <a href="billings.php" class="nav-link <?= $current_page == 'billings.php' ? 'active' : '' ?>">Billings</a>
            <a href="performance_dashboard.php" class="nav-link <?= $current_page == 'performance_dashboard.php' ? 'active' : '' ?>">Performance Dashboard</a>
        </div>

        <!-- ── Settings Menu Panel ── -->
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

        <!-- ── Help Menu Panel ── -->
        <div id="help-menu-panel" class="floating-nav-menu" hidden>
            <div class="floating-nav-subtitle">Clinical Reference</div>
            <a href="#" class="nav-link">Study Materials</a>
            <a href="#" class="nav-link">National Guidelines</a>
            <a href="#" class="nav-link">Medical Calculators</a>

            <div class="floating-nav-subtitle">Software &amp; Support</div>
            <button type="button" class="nav-link nav-button" onclick="zimrxOpenCoffeeModal()">Buy me a Coffee ☕</button>
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
            <div style="font-size: 0.85rem; color: #cbd5e1; font-weight: 500;">
                <span style="color: #38bdf8; font-weight: 700;"><?= htmlspecialchars(current_user_name()) ?></span>
            </div>
            <a href="logout.php" class="nav-link" style="color: #ef4444; background: rgba(239, 68, 68, 0.1);">Logout</a>
        </div>
        <?php endif; ?>
    </header>
    <?php if ($user_role === 'doctor'): ?>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const initFloatingMenu = (toggleId, panelId) => {
            const toggle = document.getElementById(toggleId);
            const menu = document.getElementById(panelId);
            if (!toggle || !menu) {
                return null;
            }

            let closeTimer = null;

            const positionMenu = () => {
                const rect = toggle.getBoundingClientRect();
                const menuWidth = menu.offsetWidth || 235;
                const left = Math.min(rect.left, window.innerWidth - menuWidth - 12);
                menu.style.top = `${Math.round(rect.bottom + 6)}px`;
                menu.style.left = `${Math.max(12, Math.round(left))}px`;
            };

            const openMenu = () => {
                window.clearTimeout(closeTimer);
                // Close other menus
                menus.forEach(m => {
                    if (m && m.menu !== menu && !m.menu.hidden) {
                        m.closeMenu();
                    }
                });
                menu.hidden = false;
                menu.classList.add('open');
                toggle.setAttribute('aria-expanded', 'true');
                window.requestAnimationFrame(positionMenu);
            };

            const closeMenu = () => {
                window.clearTimeout(closeTimer);
                menu.classList.remove('open');
                menu.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
            };

            const scheduleClose = () => {
                window.clearTimeout(closeTimer);
                closeTimer = window.setTimeout(closeMenu, 140);
            };

            toggle.addEventListener('mouseenter', openMenu);
            toggle.addEventListener('mouseleave', scheduleClose);
            toggle.addEventListener('focus', openMenu);
            toggle.addEventListener('click', (event) => {
                event.preventDefault();
                if (menu.hidden) {
                    openMenu();
                    return;
                }
                closeMenu();
            });

            menu.addEventListener('mouseenter', () => window.clearTimeout(closeTimer));
            menu.addEventListener('mouseleave', scheduleClose);

            return { toggle, menu, openMenu, closeMenu, positionMenu };
        };

        const menus = [
            initFloatingMenu('template-menu-toggle', 'template-menu-panel'),
            initFloatingMenu('finance-menu-toggle', 'finance-menu-panel'),
            initFloatingMenu('setup-menu-toggle', 'setup-menu-panel'),
            initFloatingMenu('help-menu-toggle', 'help-menu-panel')
        ].filter(Boolean);

        document.addEventListener('click', (event) => {
            menus.forEach(m => {
                if (m && !m.menu.hidden) {
                    if (event.target !== m.toggle && !m.toggle.contains(event.target) && !m.menu.contains(event.target)) {
                        m.closeMenu();
                    }
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                menus.forEach(m => { if (m) m.closeMenu(); });
            }
        });

        window.addEventListener('resize', () => {
            menus.forEach(m => { if (m && !m.menu.hidden) m.positionMenu(); });
        });

        window.addEventListener('scroll', () => {
            menus.forEach(m => { if (m && !m.menu.hidden) m.positionMenu(); });
        }, true);
    });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // ── Top Navigation Horizontal Overflow & Scroll Controller ────
        const nav = document.getElementById('app-top-nav');
        const wrap = document.getElementById('top-nav-wrapper');
        const btnLeft = document.getElementById('nav-scroll-left');
        const btnRight = document.getElementById('nav-scroll-right');

        if (nav && wrap) {
            const updateNavOverflow = () => {
                const scrollWidth = nav.scrollWidth;
                const clientWidth = nav.clientWidth;
                const scrollLeft = nav.scrollLeft;
                const maxScroll = Math.max(0, scrollWidth - clientWidth);

                // If all menus fit comfortably on screen, hide all arrows and edge fades completely
                if (maxScroll <= 2) {
                    wrap.classList.remove('has-overflow-left', 'has-overflow-right');
                    if (btnLeft) {
                        btnLeft.hidden = true;
                        btnLeft.classList.remove('is-visible');
                    }
                    if (btnRight) {
                        btnRight.hidden = true;
                        btnRight.classList.remove('is-visible');
                    }
                    return;
                }

                // Left arrow only shows if scrolled away from start (items hidden on the left)
                const hasLeft = scrollLeft > 4;
                // Right arrow only shows if the last menu item is not fully reached (items hidden on the right)
                const hasRight = (scrollLeft + clientWidth) < (scrollWidth - 4);

                wrap.classList.toggle('has-overflow-left', hasLeft);
                wrap.classList.toggle('has-overflow-right', hasRight);

                if (btnLeft) {
                    btnLeft.hidden = !hasLeft;
                    btnLeft.classList.toggle('is-visible', hasLeft);
                }
                if (btnRight) {
                    btnRight.hidden = !hasRight;
                    btnRight.classList.toggle('is-visible', hasRight);
                }
            };

            updateNavOverflow();
            window.addEventListener('resize', updateNavOverflow);
            nav.addEventListener('scroll', updateNavOverflow, { passive: true });

            if (btnLeft) {
                btnLeft.addEventListener('click', (e) => {
                    e.preventDefault();
                    nav.scrollBy({ left: -140, behavior: 'smooth' });
                });
            }
            if (btnRight) {
                btnRight.addEventListener('click', (e) => {
                    e.preventDefault();
                    nav.scrollBy({ left: 140, behavior: 'smooth' });
                });
            }

            // Mouse wheel horizontal scrolling
            nav.addEventListener('wheel', (e) => {
                if (nav.scrollWidth > nav.clientWidth) {
                    if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
                        e.preventDefault();
                        nav.scrollLeft += e.deltaY;
                        updateNavOverflow();
                    }
                }
            }, { passive: false });

            // Mouse click & drag to scroll
            let isDown = false;
            let startX = 0;
            let scrollStart = 0;
            nav.addEventListener('mousedown', (e) => {
                if (e.target.closest('.nav-button, .nav-link, button, a')) return;
                isDown = true;
                nav.classList.add('is-dragging');
                startX = e.pageX - nav.offsetLeft;
                scrollStart = nav.scrollLeft;
            });
            window.addEventListener('mouseup', () => {
                isDown = false;
                nav.classList.remove('is-dragging');
            });
            nav.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - nav.offsetLeft;
                const walk = (x - startX) * 1.5;
                nav.scrollLeft = scrollStart - walk;
                updateNavOverflow();
            });
        }
    });
    </script>
    <?php endif; ?>

