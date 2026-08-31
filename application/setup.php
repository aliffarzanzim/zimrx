<?php
require_once 'auth.php';
require_login();
require_once 'db.php';
$page_title = "ZimRx - Layout Configurator";

// --- Read layout from cookie (same source as prescription.php pre-rendering) ---
$availableLeftModules  = ["P/C", "AI Analyzer", "History", "P/E", "Dx", "Ix", "Plan", "Note", "O/H", "M/H", "Paediatric History", "Bangla Converter", ""];
$availableRightModules = ["Rx", "Drug Summary & Interaction", "Advice", "Report Entry", "Upload Reports & Documents", "Calculators", "Text Pad", "OT Note", "Font Format", ""];

$defaultLeftLayout  = ["P/C", "AI Analyzer", "History", "P/E", "Dx", "Ix", "Plan", "Note", "O/H", "M/H", "Paediatric History", "Bangla Converter"];
$defaultRightLayout = ["Rx", "Drug Summary & Interaction", "Advice", "Report Entry", "Upload Reports & Documents", "Calculators", "Text Pad", "OT Note", "Font Format"];

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

// Pad to 15 slots
while (count($leftLayout)  < 15) $leftLayout[]  = '';
while (count($rightLayout) < 15) $rightLayout[] = '';

function render_setup_selects(array $layout, string $side, array $options, int $total = 15): string {
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

include 'header.php';
?>

    <div class="setup-container">
        <div class="setup-header">
            <h1>Customize Dashboard Layout</h1>
            <p>Assign modules to left/right panels. Leave empty to skip an order.</p>
        </div>

        <div class="setup-grid">
            <div class="setup-section">
                <h2>Left Panel Elements (15 Max)</h2>
                <div id="left-side-setup">
                    <?= render_setup_selects($leftLayout, 'left', $availableLeftModules) ?>
                </div>
            </div>

            <div class="setup-section">
                <h2>Right Panel Elements (15 Max)</h2>
                <div id="right-side-setup">
                    <?= render_setup_selects($rightLayout, 'right', $availableRightModules) ?>
                </div>
            </div>
        </div>

        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">

        <div class="setup-section">
            <h2>History Panel Elements <small style="font-weight: normal; font-size: 0.85rem; color: #64748b; margin-left: 12px;">(Set the display order of sub-sections inside the History module.)</small></h2>
            <div id="history-side-setup" style="display:grid; grid-template-columns: 1fr 1fr; grid-template-rows: repeat(3, auto); grid-auto-flow: column; gap: 0.75rem 3rem;">
                <?= render_history_selects($historyLayout, $historyModuleLabels) ?>
            </div>
        </div>

        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">

        <!-- Dropdown Highlight Theme & Style -->
        <div class="setup-section">
            <h2>Dropdown Highlight Style <small style="font-weight: normal; font-size: 0.85rem; color: #64748b; margin-left: 12px;">(Choose the hover/active selection appearance across all autocompletes and dropdown menus.)</small></h2>
            
            <div style="display: grid; grid-template-columns: 1.25fr 0.75fr; gap: 2rem; align-items: start; margin-top: 1.25rem;">
                <!-- Presets selection grid -->
                <div class="dropdown-presets-grid" id="dropdown-presets-container">
                    <label class="dd-theme-card" data-theme="subtle-tint" data-bg="#ebedf0" data-text="#0f172a">
                        <input type="radio" name="dropdown_theme" value="subtle-tint">
                        <div class="dd-theme-preview" style="background: #ebedf0; color: #0f172a; border: 1px solid #cbd5e1;">Aa</div>
                        <div class="dd-theme-info">
                            <strong>Subtle Tint</strong>
                            <span>Soft neutral tint (Original text colors)</span>
                        </div>
                    </label>

                    <label class="dd-theme-card" data-theme="slate-gray" data-bg="#868e96" data-text="#ffffff">
                        <input type="radio" name="dropdown_theme" value="slate-gray">
                        <div class="dd-theme-preview" style="background: #868e96; color: #ffffff;">Aa</div>
                        <div class="dd-theme-info">
                            <strong>Neutral Slate</strong>
                            <span>Soft neutral slate (White text)</span>
                        </div>
                    </label>

                    <label class="dd-theme-card" data-theme="charcoal" data-bg="#475569" data-text="#ffffff">
                        <input type="radio" name="dropdown_theme" value="charcoal">
                        <div class="dd-theme-preview" style="background: #475569; color: #ffffff;">Aa</div>
                        <div class="dd-theme-info">
                            <strong>Charcoal Slate</strong>
                            <span>Deep slate (White text)</span>
                        </div>
                    </label>

                    <label class="dd-theme-card" data-theme="theme-blue" data-bg="#2563eb" data-text="#ffffff">
                        <input type="radio" name="dropdown_theme" value="theme-blue">
                        <div class="dd-theme-preview" style="background: #2563eb; color: #ffffff;">Aa</div>
                        <div class="dd-theme-info">
                            <strong>Theme Primary Blue</strong>
                            <span>Royal blue (White text)</span>
                        </div>
                    </label>

                    <label class="dd-theme-card" data-theme="soft-blue" data-bg="#eff6ff" data-text="#1d4ed8">
                        <input type="radio" name="dropdown_theme" value="soft-blue">
                        <div class="dd-theme-preview" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">Aa</div>
                        <div class="dd-theme-info">
                            <strong>Soft Blue Tint</strong>
                            <span>Light sky tint (Blue text)</span>
                        </div>
                    </label>

                    <label class="dd-theme-card" data-theme="emerald" data-bg="#16a34a" data-text="#ffffff">
                        <input type="radio" name="dropdown_theme" value="emerald">
                        <div class="dd-theme-preview" style="background: #16a34a; color: #ffffff;">Aa</div>
                        <div class="dd-theme-info">
                            <strong>Emerald Green</strong>
                            <span>Clinical emerald (White text)</span>
                        </div>
                    </label>

                    <label class="dd-theme-card" data-theme="custom" data-bg="#868e96" data-text="#ffffff" style="grid-column: span 2;">
                        <input type="radio" name="dropdown_theme" value="custom">
                        <div class="dd-theme-preview" id="dd-custom-preview-swatch" style="background: #868e96; color: #ffffff; border: 1px dashed #94a3b8;">Aa</div>
                        <div class="dd-theme-info">
                            <strong>Custom Colors...</strong>
                            <span>Pick your exact highlight background & text color</span>
                        </div>
                    </label>
                </div>

                <!-- Live Interactive Dropdown Preview -->
                <div class="dropdown-live-demo-card" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <span style="font-size: 0.78rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em;">Live Preview</span>
                        <span style="font-size: 0.72rem; color: #94a3b8;">Hover or click below</span>
                    </div>
                    <div style="position: relative; width: 100%;">
                        <div style="background: #2563eb; color: #fff; padding: 0.45rem 0.9rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600;">
                            Dropdown button <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </div>
                        <ul id="demo-dd-menu" style="margin-top: 6px; list-style: none; padding: 4px 0; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 10px 25px -5px rgba(15,23,42,0.15); margin-bottom: 0;">
                            <li class="demo-dd-item" style="padding: 6px 12px; font-size: 0.85rem; cursor: pointer; border-radius: 4px; margin: 2px 4px; transition: all 0.1s ease;">
                                <div class="demo-code" style="font-size: 0.72rem; font-weight: 700; color: #0284c7; line-height: 1.2;">A260416002</div>
                                <strong class="demo-name" style="color: #0f172a; font-size: 0.86rem; font-weight: 600; line-height: 1.25; display: block;">Rahim Uddin</strong>
                                <span class="demo-meta" style="font-size: 0.74rem; color: #64748b; display: block; line-height: 1.2;">0172222222 | Mirpur, Dhaka</span>
                            </li>
                            <li class="demo-dd-item active" id="demo-dd-active-item" style="padding: 6px 12px; font-size: 0.85rem; cursor: pointer; border-radius: 4px; margin: 2px 4px; transition: all 0.1s ease; background: #ebedf0;">
                                <div class="demo-code" style="font-size: 0.72rem; font-weight: 700; color: #0284c7; line-height: 1.2;">A260416003</div>
                                <strong class="demo-name" style="color: #0f172a; font-size: 0.86rem; font-weight: 600; line-height: 1.25; display: block;">Momena Begum</strong>
                                <span class="demo-meta" style="font-size: 0.74rem; color: #64748b; display: block; line-height: 1.2;">0173333333 | Uttara, Dhaka</span>
                            </li>
                            <li class="demo-dd-item" style="padding: 6px 12px; font-size: 0.85rem; cursor: pointer; border-radius: 4px; margin: 2px 4px; transition: all 0.1s ease;">
                                <div class="demo-code" style="font-size: 0.72rem; font-weight: 700; color: #0284c7; line-height: 1.2;">A260416004</div>
                                <strong class="demo-name" style="color: #0f172a; font-size: 0.86rem; font-weight: 600; line-height: 1.25; display: block;">Arif Hasan</strong>
                                <span class="demo-meta" style="font-size: 0.74rem; color: #64748b; display: block; line-height: 1.2;">0171111111 | Kazipara, Dhaka</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Custom Color Pickers (Visible when 'custom' is selected) -->
            <div id="dd-custom-controls" style="display: none; margin-top: 1rem; padding: 1.1rem 1.25rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; flex-wrap: wrap; gap: 1.25rem 2rem; align-items: center;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: #334155;">Highlight Background:</label>
                    <input type="color" id="dd-custom-bg-picker" value="#ebedf0" style="width: 36px; height: 32px; border: 1px solid #cbd5e1; border-radius: 4px; cursor: pointer; padding: 0;">
                    <input type="text" id="dd-custom-bg-hex" value="#ebedf0" style="width: 90px; padding: 4px 8px; font-size: 0.82rem; border: 1px solid #cbd5e1; border-radius: 4px; font-family: monospace;">
                </div>
                <div id="dd-custom-text-wrapper" style="display: flex; align-items: center; gap: 8px;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: #334155;">Text Color:</label>
                    <input type="color" id="dd-custom-text-picker" value="#ffffff" style="width: 36px; height: 32px; border: 1px solid #cbd5e1; border-radius: 4px; cursor: pointer; padding: 0;">
                    <input type="text" id="dd-custom-text-hex" value="#ffffff" style="width: 90px; padding: 4px 8px; font-size: 0.82rem; border: 1px solid #cbd5e1; border-radius: 4px; font-family: monospace;">
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <label style="display: flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 600; color: #334155; cursor: pointer; user-select: none;">
                        <input type="checkbox" id="dd-custom-original-text-cb" style="accent-color: #2563eb; width: 16px; height: 16px; cursor: pointer;">
                        Keep Original Text Colors
                    </label>
                    <span style="font-size: 0.75rem; color: #64748b;">(Preserves blue codes, dark names & muted meta)</span>
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
        <div class="print-setup-toast-panel" role="dialog" aria-modal="true" style="width: min(100%, 360px); text-align: center; gap: 1.25rem;">
            <span class="print-setup-toast-icon" style="color: #b91c1c; border-color: #fecaca; background: #fef2f2;" aria-hidden="true">&#9888;</span>
            <strong style="font-size: 1.1rem; color: #1e293b;">Reset to Defaults?</strong>
            <p style="font-size: 0.875rem; color: #64748b; margin: 0;">Are you sure you want to restore the layout to default settings? This cannot be undone.</p>
            <div style="display: flex; gap: 0.75rem; width: 100%; justify-content: center; margin-top: 0.5rem;">
                <button type="button" id="confirm-reset-cancel" class="btn btn-outline" style="flex: 1; padding: 0.5rem 1rem;">Cancel</button>
                <button type="button" id="confirm-reset-yes" class="btn btn-primary" style="flex: 1; background-color: #dc2626; border-color: #dc2626; padding: 0.5rem 1rem;">Yes, Reset</button>
            </div>
        </div>
    </div>

    <script src="assets/js/layout/config.js"></script>
    <script src="assets/js/layout/dashboard.js"></script>
    <script src="assets/js/layout/boot.js"></script>
<?php include 'footer.php'; ?>
