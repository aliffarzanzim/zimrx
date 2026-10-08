<?php
declare(strict_types=1);

// Layout configurator: reorder left/right dashboard modules, history sub-sections, and dropdown highlight themes.

require_once __DIR__ . '/init.php';
require_login();
$page_title = "ZimRx - Layout Configurator";

// --- Read layout from cookie (same source as prescription.php pre-rendering) ---
$availableLeftModules  = ["P/C", "AI Analyzer", "History", "P/E", "Breast Examination", "Local Examination", "Burn Assessment", "ENT Examination", "Dental Chart", "Diabetic Foot", "Dermatology", "Psychiatry", "Orthopaedics", "Urology", "Neurology", "Cardiology", "Pulmonology", "Endocrinology", "Dx", "Ix", "Plan", "Note", "O/H", "M/H", "Paediatric History", ""];
$availableRightModules = ["Rx", "Drug Summary & Interaction", "Advice", "Report Entry", "Upload Reports & Documents", "Calculators", "Ophthalmology", "Text Pad", "OT Note", "Font Format", ""];

$defaultLeftLayout  = ["P/C", "AI Analyzer", "History", "P/E", "Breast Examination", "Local Examination", "Burn Assessment", "ENT Examination", "Dental Chart", "Diabetic Foot", "Dermatology", "Psychiatry", "Orthopaedics", "Urology", "Neurology", "Cardiology", "Pulmonology", "Endocrinology", "Dx", "Ix", "Plan", "Note", "O/H", "M/H", "Paediatric History"];
$defaultRightLayout = ["Rx", "Drug Summary & Interaction", "Advice", "Report Entry", "Upload Reports & Documents", "Calculators", "Ophthalmology", "Text Pad", "OT Note", "Font Format"];

function decode_layout_cookie(string $name, array $default): array {
    if (!isset($_COOKIE[$name])) return $default;
    $decoded = json_decode(urldecode($_COOKIE[$name]), true);
    return is_array($decoded) ? $decoded : $default;
}

$leftLayout  = decode_layout_cookie('zimrx_left_layout',  $defaultLeftLayout);
$rightLayout = decode_layout_cookie('zimrx_right_layout', $defaultRightLayout);

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
$historyLayout = decode_layout_cookie('zimrx_history_layout', $defaultHistoryLayout);
while (count($historyLayout) < 5) $historyLayout[] = '';

// Pad to 32 slots
while (count($leftLayout)  < 32) $leftLayout[]  = '';
while (count($rightLayout) < 32) $rightLayout[] = '';

function render_setup_selects(array $layout, string $side, array $options, int $total = 32): string {
    $html = '';
    for ($i = 0; $i < $total; $i++) {
        $current = $layout[$i] ?? '';
        $label   = 'Order #' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
        $html .= '<div class="form-group">';
        $html .= '<label class="form-label">' . htmlspecialchars($label) . '</label>';
        $html .= '<select class="form-select" data-side="' . $side . '" data-index="' . $i . '">';
        foreach ($options as $opt) {
            $selected = ($opt === $current) ? ' selected' : '';
            $label_text = ($opt === '') ? '-- None --' : htmlspecialchars($opt);
            $html .= '<option value="' . htmlspecialchars($opt) . '"' . $selected . '>' . $label_text . '</option>';
        }
        $html .= '</select>';
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
        $html .= '<label class="form-label">' . htmlspecialchars($label) . '</label>';
        $html .= '<select class="form-select" data-side="history" data-index="' . $i . '">';
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

$extra_css = ['assets/css/pages/setup.css'];
include 'header.php';
?>

    <div class="setup-container">
        <div class="setup-header">
            <h1>Customize Dashboard Layout</h1>
            <p>Assign modules to left/right panels. Leave empty to skip an order.</p>
        </div>

        <div class="setup-grid">
            <div class="setup-section">
                <h2>Left Panel Elements (20 Max)</h2>
                <div id="left-side-setup">
                    <?= render_setup_selects($leftLayout, 'left', $availableLeftModules) ?>
                </div>
            </div>

            <div class="setup-section">
                <h2>Right Panel Elements (20 Max)</h2>
                <div id="right-side-setup">
                    <?= render_setup_selects($rightLayout, 'right', $availableRightModules) ?>
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
            <h2>Dropdown Highlight Style <small class="setup-heading-desc">(Choose the hover/active selection appearance across all autocompletes and dropdown menus.)</small></h2>
            
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
                        <span class="setup-preview-hint">Hover or click below</span>
                    </div>
                    <div class="setup-preview-anchor">
                        <div class="setup-preview-input">
                            Dropdown button <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </div>
                        <ul id="demo-dd-menu" class="setup-preview-dropdown">
                            <li class="demo-dd-item setup-preview-item">
                                <div class="demo-code setup-preview-brand">A260416002</div>
                                <strong class="demo-name setup-preview-name">Rahim Uddin</strong>
                                <span class="demo-meta setup-preview-company">0172222222 | Mirpur, Dhaka</span>
                            </li>
                            <li class="demo-dd-item active is-selected" id="demo-dd-active-item">
                                <div class="demo-code setup-preview-brand">A260416003</div>
                                <strong class="demo-name setup-preview-name">Momena Begum</strong>
                                <span class="demo-meta setup-preview-company">0173333333 | Uttara, Dhaka</span>
                            </li>
                            <li class="demo-dd-item setup-preview-item">
                                <div class="demo-code setup-preview-brand">A260416004</div>
                                <strong class="demo-name setup-preview-name">Arif Hasan</strong>
                                <span class="demo-meta setup-preview-company">0171111111 | Kazipara, Dhaka</span>
                            </li>
                        </ul>
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
                    <span class="setup-hint-sm">(Preserves blue codes, dark names & muted meta)</span>
                </div>
            </div>
        </div>

        <div class="setup-footer">
            <button id="btn-reset-settings" class="btn btn-outline print-setup-reset-btn">Reset to Defaults</button>
            <button id="btn-save-settings" class="btn btn-primary">Save Configuration</button>
        </div>
    </div>

    <div id="setup-toast" class="print-setup-toast" hidden>
        <div class="print-setup-toast-panel" role="dialog" aria-modal="true" aria-labelledby="setup-toast-message">
            <span class="print-setup-toast-icon" aria-hidden="true">&#10003;</span>
            <strong id="setup-toast-message">Saved successfully</strong>
            <button type="button" id="setup-toast-close" class="btn btn-primary">Okay</button>
        </div>
    </div>

    <div id="setup-confirm-modal" class="print-setup-toast" hidden>
        <div class="print-setup-toast-panel setup-confirm-panel" role="dialog" aria-modal="true">
            <span class="print-setup-toast-icon setup-confirm-icon" aria-hidden="true">&#9888;</span>
            <strong class="setup-confirm-title">Reset to Defaults?</strong>
            <p class="setup-confirm-text">Are you sure you want to restore the layout to default settings? This cannot be undone.</p>
            <div class="setup-confirm-actions">
                <button type="button" id="confirm-reset-cancel" class="btn btn-outline setup-confirm-btn">Cancel</button>
                <button type="button" id="confirm-reset-yes" class="btn btn-primary setup-confirm-btn-danger">Yes, Reset</button>
            </div>
        </div>
    </div>

    <script src="assets/js/layout/config.js"></script>
    <script src="assets/js/layout/dashboard.js"></script>
    <script src="assets/js/layout/help_guidelines.js"></script>
    <script src="assets/js/layout/table_column_resizer.js"></script>
    <script src="assets/js/layout/grid_navigation.js"></script>
    <script src="assets/js/layout/boot.js"></script>
<?php include 'footer.php'; ?>
