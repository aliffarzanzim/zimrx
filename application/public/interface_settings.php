<?php
declare(strict_types=1);

// Layout configurator: reorder left/right dashboard modules, history sub-sections, and dropdown highlight themes.

require_once __DIR__ . '/init.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/DashboardModules.php';
require_login();
$page_title = "ZimRx - Layout Configurator";
$body_class = trim(($body_class ?? '') . ' zimrx-interface-settings-hold');

$dashboardModules = new \ZimRx\Services\DashboardModules(
    ZIMRX_BASE_DIR . '/views/modules'
);
$availableLeftModules = array_merge([''], $dashboardModules->availableNames('left'));
$availableRightModules = array_merge([''], $dashboardModules->availableNames('right'));
$defaultLeftLayout = $dashboardModules->defaultLayout('left');
$defaultRightLayout = $dashboardModules->defaultLayout('right');

$doctorId = current_user_doctor_id();

function decode_layout_source(PDO $pdo, int $doctorId, string $cookieName, string $settingKey, array $default): array {
    $dbVal = null;
    if ($doctorId > 0 && function_exists('zimrx_db_table_exists') && zimrx_db_table_exists($pdo, 'zimrx_interface_settings')) {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM zimrx_interface_settings WHERE doctor_id = :doc AND setting_scope = 'dashboard' AND setting_key = :k LIMIT 1");
            $stmt->execute(['doc' => $doctorId, 'k' => $settingKey]);
            $val = $stmt->fetchColumn();
            if ($val) {
                $decoded = json_decode((string)$val, true);
                if (is_array($decoded)) {
                    $dbVal = array_values(array_filter($decoded, fn($m) => $m !== ''));
                }
            }
        } catch (Throwable $e) {}
    }

    if ($dbVal !== null) {
        return $dbVal;
    }

    $raw = null;
    if (isset($_COOKIE[$cookieName])) {
        $decoded = json_decode(urldecode($_COOKIE[$cookieName]), true);
        if (!is_array($decoded)) {
            $decoded = json_decode($_COOKIE[$cookieName], true);
        }
        if (is_array($decoded)) {
            $raw = array_values(array_filter($decoded, fn($m) => $m !== ''));
        }
    }

    if ($raw === null) {
        return $default;
    }

    $layoutV = $_COOKIE['zimrx_layout_v'] ?? '';
    if ($layoutV !== '3') {
        if ($settingKey === 'left_layout') {
            $raw = array_values(array_filter($raw, fn($m) => $m !== 'Note' && $m !== 'Paediatric History'));
        } elseif ($settingKey === 'right_layout') {
            $raw = array_values(array_filter($raw, fn($m) => $m !== 'Drug Summary & Interaction'));
        }
    }

    return $raw;
}

$leftLayout  = decode_layout_source($pdo, $doctorId, 'zimrx_left_layout',  'left_layout',  $defaultLeftLayout);
$rightLayout = decode_layout_source($pdo, $doctorId, 'zimrx_right_layout', 'right_layout', $defaultRightLayout);
$leftLayout = array_map(
    static fn(mixed $name): string => is_string($name) ? ($dashboardModules->canonicalName($name) ?? $name) : '',
    $leftLayout
);
$rightLayout = array_map(
    static fn(mixed $name): string => is_string($name) ? ($dashboardModules->canonicalName($name) ?? $name) : '',
    $rightLayout
);

if (($_COOKIE['zimrx_layout_v'] ?? '') !== '3' && !headers_sent()) {
    setcookie('zimrx_layout_v', '3', time() + 31536000, '/', '', false, false);
    setcookie('zimrx_left_layout', json_encode(array_values(array_filter($leftLayout, fn($m) => $m !== '')), JSON_UNESCAPED_UNICODE), time() + 31536000, '/', '', false, false);
    setcookie('zimrx_right_layout', json_encode(array_values(array_filter($rightLayout, fn($m) => $m !== '')), JSON_UNESCAPED_UNICODE), time() + 31536000, '/', '', false, false);
}

$availableHistoryModules = ['medical', 'treatment', 'habits', 'diet-hypersensitivity', 'drug-history', ''];
$historyModuleLabels = [
    'medical'             => 'Medical History',
    'treatment'          => 'Treatment History',
    'habits'             => 'Habits',
    'diet-hypersensitivity' => 'Diet & Hypersensitivity',
    'drug-history'       => 'Drug History',
    ''                   => '-- None --',
];
$defaultHistoryLayout = ['medical', 'treatment', 'habits', 'diet-hypersensitivity', 'drug-history'];
$historyLayout = decode_layout_source($pdo, $doctorId, 'zimrx_history_layout', 'history_layout', $defaultHistoryLayout);
while (count($historyLayout) < 5) $historyLayout[] = '';

function render_setup_selects(array $layout, string $side, array $options): string {
    $html = '';
    foreach ($layout as $i => $current) {
        $current = $layout[$i] ?? '';
        $label   = 'Order #' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
        $html .= '<div class="form-group">';
        $html .= '<label class="form-label" for="interface-' . $side . '-order-' . $i . '">' . htmlspecialchars($label) . '</label>';
        $html .= '<select class="form-select zrx-select" id="interface-' . $side . '-order-' . $i . '" data-side="' . $side . '" data-index="' . $i . '">';
        foreach ($options as $opt) {
            $selected = ($opt === $current) ? ' selected' : '';
            $label_text = ($opt === '') ? '-- None --' : htmlspecialchars($opt);
            $html .= '<option value="' . htmlspecialchars($opt) . '"' . $selected . '>' . $label_text . '</option>';
        }
        $html .= '</select>';
        $html .= '<button type="button" class="interface-order-remove" aria-label="Remove ' . htmlspecialchars($label) . '">Remove</button>';
        $html .= '</div>';
    }
    return $html;
}

function render_history_selects(array $layout, array $labelMap): string {
    $html = '';
    $options = array_keys($labelMap);
    for ($i = 0; $i < 5; $i++) {
        $current = $layout[$i] ?? '';
        $label   = 'Order #' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
        $html .= '<div class="form-group">';
        $html .= '<label class="form-label" for="interface-history-order-' . $i . '">' . htmlspecialchars($label) . '</label>';
        $html .= '<select class="form-select zrx-select" id="interface-history-order-' . $i . '" data-side="history" data-index="' . $i . '">';
        foreach ($options as $opt) {
            $selected = ($opt === $current) ? ' selected' : '';
            $label_text = htmlspecialchars($labelMap[$opt] ?? $opt);
            $html .= '<option value="' . htmlspecialchars($opt) . '"' . $selected . '>' . $label_text . '</option>';
        }
        $html .= '</select>';
        $html .= '</div>';
    }
    return $html;
}

$extra_css = [
    'assets/css/pages/setup.css',
    'assets/css/pages/interface_settings.css',
    'assets/css/modules/pres_particulars.css',
    'assets/css/modules/rx.css',
    'assets/css/modules/modals_dialogs.css',
];
include 'header.php';
?>

    <main class="setup-container zrx-page-container interface-settings-page">
        <header class="setup-header zrx-settings-page-hero">
            <div class="zrx-settings-page-hero-copy">
                <a class="zrx-settings-page-back" href="settings.php">Settings</a>
                <h1>Interface Settings</h1>
                <p>Arrange prescription modules and configure dashboard display preferences.</p>
            </div>
        </header>

        <div class="setup-grid">
            <div class="setup-section">
                <h2>Left Panel Elements</h2>
                <div id="left-side-setup">
                    <?= render_setup_selects($leftLayout, 'left', $availableLeftModules) ?>
                </div>
                <div class="interface-order-actions">
                    <span class="interface-order-count" data-order-count="left" aria-live="polite"></span>
                    <button type="button" class="btn btn-outline interface-order-add" data-order-add="left">Add order</button>
                </div>
            </div>

            <div class="setup-section">
                <h2>Right Panel Elements</h2>
                <div id="right-side-setup">
                    <?= render_setup_selects($rightLayout, 'right', $availableRightModules) ?>
                </div>
                <div class="interface-order-actions">
                    <span class="interface-order-count" data-order-count="right" aria-live="polite"></span>
                    <button type="button" class="btn btn-outline interface-order-add" data-order-add="right">Add order</button>
                </div>
            </div>
        </div>

        <hr class="setup-divider">

        <div class="setup-section">
            <h2>History Panel Elements <small class="setup-heading-desc">(Set the display order of sub-sections inside the History module.)</small></h2>
            <div id="history-side-setup" class="setup-history-grid">
                <?= render_history_selects($historyLayout, $historyModuleLabels) ?>
            </div>
        </div>

        <hr class="setup-divider">

        <!-- Dropdown Highlight Theme & Style -->
        <div class="setup-section">
            <h2>Dropdown Highlight Style</h2>
            
            <div class="setup-theme-layout-grid">
                <!-- Presets selection grid -->
                <div class="dropdown-presets-grid" id="dropdown-presets-container">
                    <label class="dd-theme-card" data-theme="slate-gray" data-bg="#868e96" data-text="#ffffff">
                        <input type="radio" name="dropdown_theme" value="slate-gray" checked>
                        <div class="dd-theme-preview dd-theme-preview-gray">Aa</div>
                        <div class="dd-theme-info">
                            <strong>Neutral Slate (Default)</strong>
                            <span>Soft neutral slate (White text)</span>
                        </div>
                    </label>

                    <label class="dd-theme-card" data-theme="subtle-tint" data-bg="#ebedf0" data-text="#0f172a">
                        <input type="radio" name="dropdown_theme" value="subtle-tint">
                        <div class="dd-theme-preview dd-theme-preview-slate">Aa</div>
                        <div class="dd-theme-info">
                            <strong>Subtle Tint</strong>
                            <span>Soft neutral tint (Dark text)</span>
                        </div>
                    </label>

                    <label class="dd-theme-card" data-theme="charcoal" data-bg="#475569" data-text="#ffffff">
                        <input type="radio" name="dropdown_theme" value="charcoal">
                        <div class="dd-theme-preview dd-theme-preview-dark">Aa</div>
                        <div class="dd-theme-info">
                            <strong>Charcoal Slate</strong>
                            <span>Deep slate (White text)</span>
                        </div>
                    </label>

                    <label class="dd-theme-card" data-theme="theme-blue" data-bg="#2563eb" data-text="#ffffff">
                        <input type="radio" name="dropdown_theme" value="theme-blue">
                        <div class="dd-theme-preview dd-theme-preview-blue">Aa</div>
                        <div class="dd-theme-info">
                            <strong>Theme Primary Blue</strong>
                            <span>Royal blue (White text)</span>
                        </div>
                    </label>

                    <label class="dd-theme-card" data-theme="soft-blue" data-bg="#eff6ff" data-text="#1d4ed8">
                        <input type="radio" name="dropdown_theme" value="soft-blue">
                        <div class="dd-theme-preview dd-theme-preview-softblue">Aa</div>
                        <div class="dd-theme-info">
                            <strong>Soft Blue Tint</strong>
                            <span>Light sky tint (Blue text)</span>
                        </div>
                    </label>

                    <label class="dd-theme-card" data-theme="emerald" data-bg="#16a34a" data-text="#ffffff">
                        <input type="radio" name="dropdown_theme" value="emerald">
                        <div class="dd-theme-preview dd-theme-preview-green">Aa</div>
                        <div class="dd-theme-info">
                            <strong>Emerald Green</strong>
                            <span>Clinical emerald (White text)</span>
                        </div>
                    </label>

                    <label class="dd-theme-card dd-theme-card-custom" data-theme="custom" data-bg="#868e96" data-text="#ffffff">
                        <input type="radio" name="dropdown_theme" value="custom">
                        <div class="dd-theme-preview dd-theme-preview-custom" id="dd-custom-preview-swatch">Aa</div>
                        <div class="dd-theme-info">
                            <strong>Custom Colors...</strong>
                            <span>Pick your exact highlight background & text color</span>
                        </div>
                    </label>
                </div>

                <!-- Live Interactive Dropdown Preview -->
                <div class="dropdown-live-demo-card setup-preview-card">
                    <div class="setup-preview-header">
                        <span class="setup-preview-label">Live Preview</span>
                    </div>
                    <div id="dropdown-preview-content" class="setup-preview-anchor">
                        <div class="setup-preview-controls">
                            <label for="dropdown-preview-type">Show dropdown</label>
                            <select id="dropdown-preview-type" class="form-select zrx-select">
                                <option value="global">Global Dropdown</option>
                                <option value="registration">Registration Dropdown</option>
                                <option value="phone">Phone Number Dropdown</option>
                                <option value="rx">Rx Dropdown</option>
                            </select>
                        </div>
                        <div id="dropdown-preview-global-area" class="setup-preview-global-area" hidden>
                            <label for="dropdown-preview-global-select">Interface modules</label>
                            <select id="dropdown-preview-global-select" class="form-select zrx-select"></select>
                        </div>
                        <div id="dropdown-preview-search-area" class="setup-preview-search-area" hidden>
                            <label id="dropdown-preview-search-label" for="dropdown-preview-search">Search</label>
                            <div id="dropdown-preview-search-wrapper" class="autocomplete-wrapper setup-preview-search-wrapper">
                                <input id="dropdown-preview-search" class="form-control setup-preview-search" type="search" autocomplete="off">
                                <ul id="dropdown-preview-results" class="zrx-dropdown autocomplete-list" aria-label="Live dropdown results"></ul>
                            </div>
                            <p id="dropdown-preview-note" class="setup-preview-note">Results load from local application data as you type.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Color Pickers (Visible when 'custom' is selected) -->
            <div id="dd-custom-controls" class="setup-custom-editor-box">
                <div class="zrx-flex-ac">
                    <label class="setup-field-inline-label">Highlight Background:</label>
                    <input type="color" id="dd-custom-bg-picker" value="#ebedf0" class="setup-color-picker-input">
                    <input type="text" id="dd-custom-bg-hex" value="#ebedf0" class="setup-color-hex-input">
                </div>
                <div id="dd-custom-text-wrapper" class="zrx-flex-ac">
                    <label class="setup-field-inline-label">Text Color:</label>
                    <input type="color" id="dd-custom-text-picker" value="#ffffff" class="setup-color-picker-input">
                    <input type="text" id="dd-custom-text-hex" value="#ffffff" class="setup-color-hex-input">
                </div>
                <div class="zrx-flex-ac-6">
                    <label class="setup-checkbox-label">
                        <input type="checkbox" id="dd-custom-original-text-cb" class="setup-checkbox-input">
                        Keep Original Text Colors
                    </label>
                </div>
            </div>
        </div>

        <div class="setup-footer">
            <button id="btn-reset-settings" class="btn btn-outline print-setup-reset-btn">Reset to Defaults</button>
            <button id="btn-save-settings" class="btn btn-primary">Save Configuration</button>
        </div>
    </main>

    <div id="setup-toast" class="zrx-modal-overlay" hidden>
        <div class="zrx-modal-dialog setup-toast-panel" role="dialog" aria-modal="true" aria-labelledby="setup-toast-message">
            <span class="setup-toast-icon" aria-hidden="true">&#10003;</span>
            <strong id="setup-toast-message">Saved successfully</strong>
            <button type="button" id="setup-toast-close" class="btn btn-primary">Okay</button>
        </div>
    </div>

    <div id="setup-confirm-modal" class="zrx-modal-overlay" hidden>
        <div class="zrx-modal-dialog setup-confirm-panel" role="dialog" aria-modal="true" aria-labelledby="setup-confirm-title">
            <strong id="setup-confirm-title" class="setup-confirm-title">Reset to Defaults?</strong>
            <p class="setup-confirm-text">Are you sure you want to restore the layout to default settings? This cannot be undone.</p>
            <div class="setup-confirm-actions">
                <button type="button" id="confirm-reset-cancel" class="btn btn-outline setup-confirm-btn">Cancel</button>
                <button type="button" id="confirm-reset-yes" class="btn btn-primary setup-confirm-btn-danger">Yes, Reset</button>
            </div>
        </div>
    </div>

    <script id="dashboard-module-config" type="application/json"><?= json_encode($dashboardModules->clientConfig(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?></script>
    <script src="assets/js/layout/config.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/config.js') ?>"></script>
    <script src="assets/js/layout/dashboard.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/dashboard.js') ?>"></script>
    <script src="assets/js/modules/dosage_form_icons.js?v=<?= filemtime(__DIR__ . '/assets/js/modules/dosage_form_icons.js') ?>"></script>
    <script src="assets/js/pages/dropdown_highlight_preview.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/dropdown_highlight_preview.js') ?>"></script>
    <script src="assets/js/layout/help_guidelines.js"></script>
    <script src="assets/js/layout/table_column_resizer.js"></script>
    <script src="assets/js/layout/grid_navigation.js"></script>
    <script src="assets/js/layout/boot.js"></script>
<?php include 'footer.php'; ?>
