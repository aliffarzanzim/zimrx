<?php
require_once 'auth.php';
require_login();
require_once 'db.php';
require_once 'print_setup_lib.php';

function bridge_selected(string $current, string $target): string {
    return $current === $target ? 'selected' : '';
}

function bridge_render_font_options(string $selectedFont, array $fontOptions, array $banglaFontOptions, array $rxFontOptions): void {
    echo '<optgroup label="English Fonts">';
    foreach ($fontOptions as $font) {
        $sel = $selectedFont === $font ? 'selected' : '';
        echo '<option value="' . preview_escape($font) . '" ' . $sel . '>' . preview_escape($font) . '</option>';
    }
    echo '</optgroup>';
    echo '<optgroup label="Bangla Fonts">';
    foreach ($banglaFontOptions as $value => $label) {
        $sel = $selectedFont === $value ? 'selected' : '';
        echo '<option value="' . preview_escape($value) . '" ' . $sel . '>' . preview_escape($label) . '</option>';
    }
    echo '</optgroup>';
    echo '<optgroup label="Rx fonts">';
    foreach ($rxFontOptions as $value => $label) {
        $sel = $selectedFont === $value ? 'selected' : '';
        echo '<option value="' . preview_escape($value) . '" ' . $sel . '>' . preview_escape($label) . '</option>';
    }
    echo '</optgroup>';
}

$page_title = 'ZimRx - Print Setup';
$extra_css = ['assets/css/print_layout_editor.css'];
$doctorId = current_user_doctor_id();
$options = zimrx_bridge_load_print_options($pdo, $doctorId);

// Force right_width default to 11.0 (overriding the old legacy bridge default of 12.0)
if (empty($options['right_width']) || (float)$options['right_width'] === 12.0) {
    $options['right_width'] = '11.0';
}

$fontOptions = ['Times New Roman', 'Arial', 'Calibri', 'Tahoma', 'Georgia', 'Gabriola', 'Courier New', 'Comic Sans', 'Bradley Hand ITC'];
$banglaFontOptions = [
    'SolaimanLipi' => 'SolaimanLipi',
    'AdorshoLipi' => 'Adorsho Lipi',
    'Kongsho' => 'Kongsho',
    'BenSenHandwriting' => 'BenSen Handwriting',
    'Nikosh' => 'Nikosh',
    'Siyamrupali' => 'Siyam Rupali',
    'KumarkhaliUnicode' => 'Kumarkhali Unicode',
    'MangalikUnicode' => 'Mangalik Unicode',
];
$rxFontOptions = [
    'Lucida Calligraphy' => 'Lucida Calligraphy',
    'AkayaKanadaka' => 'Akaya Kanadaka',
    'Birthstone' => 'Birthstone',
    'Charm' => 'Charm',
    'Cookie' => 'Cookie',
    'Damion' => 'Damion',
    'Engagement' => 'Engagement',
    'HappyMonkey' => 'Happy Monkey',
    'JimNightshade' => 'Jim Nightshade',
    'Kings' => 'Kings',
    'Macondo' => 'Macondo',
    'Metamorphous' => 'Metamorphous',
    'MonteCarlo' => 'MonteCarlo',
    'Parisienne' => 'Parisienne',
    'ShantellSans' => 'Shantell Sans',
    'TeXGyreChorus' => 'TeX Gyre Chorus',
];
$allFonts = array_merge(
    array_combine($fontOptions, $fontOptions),
    $banglaFontOptions,
    $rxFontOptions
);
if (!isset($allFonts[(string)($options['bn_font'] ?? '')])) {
    $options['bn_font'] = 'SolaimanLipi';
}
if (!isset($allFonts[(string)($options['upd_font'] ?? '')])) {
    $options['upd_font'] = 'SolaimanLipi';
}
$yesNo = ['yes' => 'Yes', 'no' => 'No'];
$printLanguages = ['bengali' => 'Bengali', 'english' => 'English'];
$historyBulletOptions = [
    '○' => '○ Open Circle',
    '•' => '• Filled Circle',
    '▪' => '▪ Square',
    '–' => '– Dash',
    '›' => '› Arrow',
];
$drugBulletOptions = [
    '•' => '• Filled Circle',
    '○' => '○ Open Circle',
    '▪' => '▪ Square',
    '–' => '– Dash',
    '›' => '› Arrow',
];
$drugNoStyleOptions = [
    'period' => '1. 2. 3.',
    'round_brackets' => '(1) (2) (3)',
    'closing_bracket' => '1) 2) 3)',
    'square_brackets' => '[1] [2] [3]',
];
$pcFormatOptions = [
    'parentheses' => 'Chest pain (4 Hours) / Vomiting (3 Episodes)',
    'for' => 'Chest pain for 4 Hours / 3 Episodes of Vomiting',
    'hyphen' => 'Chest pain - 4 Hours / Vomiting - 3 Episodes',
];
$headerTypes = ['text' => 'Text Header', 'image' => 'Image Header'];
$previewHeaderTypes = ['with_header' => 'With Header', 'without_header' => 'Without Header'];
$revisitPositions = ['top' => 'After Prescription', 'bottom' => 'Page Bottom'];
$dxFormats = [
    'per_line' => 'Each Dx new line',
    'single_line' => 'All Dx in Single Line (HTN ē DM ē IHD)',
];
$dxBulletOptions = [
    'Δ' => 'Δ Delta',
    '○' => '○ Open Circle',
    '•' => '• Filled Circle',
    '▪' => '▪ Square',
    '–' => '– Dash',
    '›' => '› Arrow',
];
$reportPositions = ['left' => 'Left Side', 'right' => 'Right Side'];
$printPosOptions = [
    'pc' => 'P/C', 'ho' => 'History', 'pe' => 'P/E', 'reports' => 'Reports',
    'plan' => 'Plan', 'otnote' => 'OT Note', 'oh' => 'O/H',
    'mh' => 'M/H', 'advice' => 'Ix/Investigation', 'note' => 'Note', 'dx' => 'Dx', 'none' => 'None',
];
$printHistoryPosOptions = [
    'medical' => 'Medical History',
    'treatments' => 'Treatment History',
    'habits' => 'Habits',
    'diet' => 'Diet',
    'hypersensitivity' => 'Hypersensitivity',
    'drug_history' => 'Drug History',
    'none' => 'None',
];
$patientFieldOptions = [
    'name' => 'Name', 'age' => 'Age', 'sex' => 'Sex', 'date' => 'Date', 'address' => 'Address',
    'regno' => 'Reg No', 'bmi_weigh' => 'Weight', 'mobile' => 'Mobile', 'ref_by' => 'Ref By', 'visit_no' => 'Visit No',
];
$visibilityFields = [
    'name' => 'Name', 'age' => 'Age', 'sex' => 'Sex', 'date' => 'Date',
    'address' => 'Address', 'reg_no' => 'Reg No', 'weight' => 'Weight', 'mobile' => 'Mobile',
];

include 'header.php';
?>

<div class="layout-editor-page">
    <div class="layout-editor-heading">
        <div>
            <h1>Print Setup</h1>
            <p>Configure your prescription layout, typography, and print formatting.</p>
        </div>
        <div class="layout-editor-heading-actions">
            <a href="prescription_preview.php" target="_blank" class="btn btn-outline">Full Preview</a>
            <a href="page_setup.php" class="btn btn-outline">Page Setup</a>
            <a href="header_footer_background_setup.php" class="btn btn-outline">Header/Footer/BG Setup</a>
            <button type="button" class="btn btn-outline print-setup-reset-btn" id="factory-reset-btn">Reset to Defaults</button>
            <button type="submit" form="print-setup-form" class="btn btn-primary">Save Settings</button>
        </div>
    </div>

    <div id="print-setup-toast" class="print-setup-toast" hidden>
        <div class="print-setup-toast-panel" role="dialog" aria-modal="true" aria-labelledby="print-setup-toast-message">
            <span class="print-setup-toast-icon" aria-hidden="true">&#10003;</span>
            <strong id="print-setup-toast-message">Saved successfully</strong>
            <button type="button" id="print-setup-toast-close" class="btn btn-primary">Okay</button>
        </div>
    </div>

    <div id="print-setup-confirm-modal" class="print-setup-toast" hidden>
        <div class="print-setup-toast-panel" role="dialog" aria-modal="true" style="width: min(100%, 360px); text-align: center; gap: 1.25rem;">
            <span class="print-setup-toast-icon" style="color: #b91c1c; border-color: #fecaca; background: #fef2f2;" aria-hidden="true">&#9888;</span>
            <strong style="font-size: 1.1rem; color: #1e293b;">Reset to Defaults?</strong>
            <p style="font-size: 0.875rem; color: #64748b; margin: 0;">Are you sure you want to restore the print layout and decoration sizes to default settings? This cannot be undone.</p>
            <div style="display: flex; gap: 0.75rem; width: 100%; justify-content: center; margin-top: 0.5rem;">
                <button type="button" id="confirm-reset-cancel" class="btn btn-outline" style="flex: 1; padding: 0.5rem 1rem;">Cancel</button>
                <button type="button" id="confirm-reset-yes" class="btn btn-primary" style="flex: 1; background-color: #dc2626; border-color: #dc2626; padding: 0.5rem 1rem;">Yes, Reset</button>
            </div>
        </div>
    </div>

    <form id="print-setup-form">
        <div class="layout-editor-grid">

            <!-- Left Side: Live Preview -->
            <div class="layout-preview-shell">
                <div class="layout-preview-toolbar">
                    <div>
                        <h2>Live Preview</h2>
                    </div>
                </div>
                <div class="sheet-stage">
                    <div class="layout-page-scale">
                        <div class="setup-print-paper" id="paper-wrap">
                            <iframe
                                id="setup-preview-frame"
                                class="setup-preview-frame"
                                src="prescription_preview.php?embedded=1"
                                title="Prescription preview"
                            ></iframe>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Config Controls -->
            <div class="layout-controls">

                <!-- Hidden Base Dimensions (Managed via Page Setup) -->
                <input type="hidden" name="page_width" value="<?= preview_escape((string)$options['page_width']) ?>">
                <input type="hidden" name="page_height" value="<?= preview_escape((string)$options['page_height']) ?>">
                <input type="hidden" name="header_height" value="<?= preview_escape((string)$options['header_height']) ?>">
                <input type="hidden" name="footer_height" value="<?= preview_escape((string)$options['footer_height']) ?>">
                <input type="hidden" name="pt_info_height" value="<?= preview_escape((string)$options['pt_info_height']) ?>">
                <input type="hidden" name="pt_info_width" value="<?= preview_escape((string)$options['pt_info_width']) ?>">
                <input type="hidden" name="left_width" value="<?= preview_escape((string)$options['left_width']) ?>">
                <input type="hidden" name="left_height" value="<?= preview_escape((string)$options['left_height']) ?>">
                <input type="hidden" name="right_width" value="<?= preview_escape((string)$options['right_width']) ?>">
                <input type="hidden" name="right_height" value="<?= preview_escape((string)$options['right_height']) ?>">
                <input type="hidden" name="header_type" value="<?= preview_escape((string)$options['header_type']) ?>">
                <input type="hidden" name="preview_header_type" value="<?= preview_escape((string)$options['preview_header_type']) ?>">

                <!-- Unified Layout & Typography Matrix Table -->
                <div class="layout-control-card layout-matrix-card">
                    <div class="layout-card-header">
                        <h2>Typography &amp; Spacing Matrix</h2>
                    </div>
                    <div class="matrix-table-wrap">
                        <table class="setup-matrix-table">
                            <thead>
                                <tr>
                                    <th class="th-element">Element</th>
                                    <th>Font<br>Family</th>
                                    <th>Font Size<br><span class="th-unit">(pt)</span></th>
                                    <th>Margin-L<br><span class="th-unit">(px)</span></th>
                                    <th>Margin-T<br><span class="th-unit">(px)</span></th>
                                    <th>Margin-B<br><span class="th-unit">(px)</span></th>
                                    <th>Line Height<br><span class="th-unit">(pt)</span></th>
                                    <th>Indent<br><span class="th-unit">(px)</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Row: Pt Info -->
                                <tr>
                                    <td class="matrix-row-title">Pt Info</td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-patient-strip">
                                            <select name="pt_info_font">
                                                <?php bridge_render_font_options((string)$options['pt_info_font'], $fontOptions, $banglaFontOptions, $rxFontOptions); ?>
                                            </select>
                                        </label>
                                    </td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-patient-strip">
                                            <input type="number" step="0.5" name="pt_info_font_size" value="<?= preview_escape((string)$options['pt_info_font_size']) ?>">
                                        </label>
                                    </td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-patient-table" title="Pt Info Margin T (px)">
                                            <input type="number" name="pt_info_margin_top" value="<?= preview_escape((string)($options['pt_info_margin_top'] ?? '0')) ?>">
                                        </label>
                                    </td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-patient-table" title="Pt Info Margin B (px)">
                                            <input type="number" name="pt_info_margin_bottom" value="<?= preview_escape((string)($options['pt_info_margin_bottom'] ?? '0')) ?>">
                                        </label>
                                    </td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td class="matrix-disabled-cell"></td>
                                </tr>

                                <!-- Row: Left Side -->
                                <tr>
                                    <td class="matrix-row-title">Left Side</td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-left-content">
                                            <select name="left_font">
                                                <?php bridge_render_font_options((string)$options['left_font'], $fontOptions, $banglaFontOptions, $rxFontOptions); ?>
                                            </select>
                                        </label>
                                    </td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-left-content">
                                            <input type="number" step="0.5" name="left_font_size" value="<?= preview_escape((string)$options['left_font_size']) ?>">
                                        </label>
                                    </td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-left-content" title="Left Margin L (px)">
                                            <input type="number" name="left_margin_left" value="<?= preview_escape((string)$options['left_margin_left']) ?>">
                                        </label>
                                    </td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-left-content" title="Left Margin T (px)">
                                            <input type="number" name="left_margin_top" value="<?= preview_escape((string)$options['left_margin_top']) ?>">
                                        </label>
                                    </td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-left-content table td" title="Left History Line Height (pt)">
                                            <input type="number" step="0.5" name="left_line_height" value="<?= preview_escape((string)$options['left_line_height']) ?>">
                                        </label>
                                    </td>
                                    <td class="matrix-disabled-cell"></td>
                                </tr>

                                <!-- Row: Right Side -->
                                <tr>
                                    <td class="matrix-row-title">Right Side</td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-prescription-main" title="Right Margin T (px)">
                                            <input type="number" name="right_margin_top" value="<?= preview_escape((string)($options['right_margin_top'] ?? '0')) ?>">
                                        </label>
                                    </td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td class="matrix-disabled-cell"></td>
                                </tr>

                                <!-- Row: Prescription (Brand/Name) -->
                                <tr>
                                    <td class="matrix-row-title">Prescription</td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-prescription-main">
                                            <select name="right_font">
                                                <?php bridge_render_font_options((string)$options['right_font'], $fontOptions, $banglaFontOptions, $rxFontOptions); ?>
                                            </select>
                                        </label>
                                    </td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-prescription-main">
                                            <input type="number" step="0.5" name="right_font_size" value="<?= preview_escape((string)$options['right_font_size']) ?>">
                                        </label>
                                    </td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-prescription-main" title="Prescription Margin L (px)">
                                            <input type="number" name="pres_main_left_margin" value="<?= preview_escape((string)$options['pres_main_left_margin']) ?>">
                                        </label>
                                    </td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-prescription-main" title="Prescription Margin T (px)">
                                            <input type="number" name="pres_main_margin_top" value="<?= preview_escape((string)$options['pres_main_margin_top']) ?>">
                                        </label>
                                    </td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-drug-table td" title="Drug Line Height (pt)">
                                            <input type="number" step="0.5" name="pres_line_height" value="<?= preview_escape((string)$options['pres_line_height']) ?>">
                                        </label>
                                    </td>
                                    <td class="matrix-disabled-cell"></td>
                                </tr>

                                <!-- Row: Generic Name -->
                                <tr>
                                    <td class="matrix-row-title">Generic Name</td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-drug-generic">
                                            <select name="generic_font">
                                                <?php bridge_render_font_options((string)$options['generic_font'], $fontOptions, $banglaFontOptions, $rxFontOptions); ?>
                                            </select>
                                        </label>
                                    </td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-drug-generic">
                                            <input type="number" step="0.5" name="generic_font_size" value="<?= preview_escape((string)$options['generic_font_size']) ?>">
                                        </label>
                                    </td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-drug-generic" title="Generic Name Margin L (px)">
                                            <input type="number" name="generic_margin_left" value="<?= preview_escape((string)($options['generic_margin_left'] ?? '0')) ?>">
                                        </label>
                                    </td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-drug-generic" title="Generic Name Margin T (px)">
                                            <input type="number" name="generic_margin_top" value="<?= preview_escape((string)($options['generic_margin_top'] ?? '0')) ?>">
                                        </label>
                                    </td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td class="matrix-disabled-cell"></td>
                                </tr>

                                <!-- Row: Line Gap & Drug No -->
                                <tr>
                                    <td class="matrix-row-title">Line Gap</td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-drug-gap" title="Drug Line Gap (pt)">
                                            <input type="number" step="0.5" name="pres_gap_height" value="<?= preview_escape((string)$options['pres_gap_height']) ?>">
                                        </label>
                                    </td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-drug-number" title="Drug No Gap (px)">
                                            <input type="number" name="dr_n_gap" value="<?= preview_escape((string)$options['dr_n_gap']) ?>" placeholder="No Gap (px)">
                                        </label>
                                    </td>
                                </tr>

                                <!-- Row: Rx -->
                                <tr>
                                    <td class="matrix-row-title">Rx</td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-rx-symbol">
                                            <select name="rx_font">
                                                <?php bridge_render_font_options((string)$options['rx_font'], $fontOptions, $banglaFontOptions, $rxFontOptions); ?>
                                            </select>
                                        </label>
                                    </td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-rx-symbol">
                                            <input type="number" step="0.5" name="rx_font_size" value="<?= preview_escape((string)$options['rx_font_size']) ?>">
                                        </label>
                                    </td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-rx-symbol" title="Rx Margin L (px)">
                                            <input type="number" name="rx_block_margin_left" value="<?= preview_escape((string)$options['rx_block_margin_left']) ?>">
                                        </label>
                                    </td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-rx-symbol" title="Rx Margin T (px)">
                                            <input type="number" name="rx_block_margin_top" value="<?= preview_escape((string)$options['rx_block_margin_top']) ?>">
                                        </label>
                                    </td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td class="matrix-disabled-cell"></td>
                                </tr>

                                <!-- Row: Bangla Instruction -->
                                <tr>
                                    <td class="matrix-row-title">Bangla<br>Instruction</td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-drug-dose, .zrx-drug-instruction, .zrx-drug-duration">
                                            <select name="bn_font">
                                                <?php bridge_render_font_options((string)$options['bn_font'], $fontOptions, $banglaFontOptions, $rxFontOptions); ?>
                                            </select>
                                        </label>
                                    </td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-drug-dose, .zrx-drug-instruction, .zrx-drug-duration">
                                            <input type="number" step="0.5" name="bn_font_size" value="<?= preview_escape((string)$options['bn_font_size']) ?>">
                                        </label>
                                    </td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-drug-dose" title="Dose Left Padding (px)">
                                            <input type="number" name="dose_lt_padding" value="<?= preview_escape((string)$options['dose_lt_padding']) ?>" placeholder="Dose L-Pad (px)">
                                        </label>
                                    </td>
                                </tr>

                                <!-- Row: Advice -->
                                <tr>
                                    <td class="matrix-row-title">Advice</td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-advice-section">
                                            <select name="upd_font">
                                                <?php bridge_render_font_options((string)$options['upd_font'], $fontOptions, $banglaFontOptions, $rxFontOptions); ?>
                                            </select>
                                        </label>
                                    </td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-advice-section">
                                            <input type="number" step="0.5" name="upd_font_size" value="<?= preview_escape((string)$options['upd_font_size']) ?>">
                                        </label>
                                    </td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td class="matrix-disabled-cell"></td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-advice-table td" title="Advice Line Height (pt)">
                                            <input type="number" step="0.5" name="upd_line_height" value="<?= preview_escape((string)$options['upd_line_height']) ?>">
                                        </label>
                                    </td>
                                    <td class="matrix-disabled-cell"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            <!-- Generic Name & Rx Formatting -->
            <div class="layout-control-card">
                <div class="layout-card-header">
                    <h2>Generic Name &amp; Rx Formatting</h2>
                </div>
                <div class="matrix-table-wrap">
                    <table class="setup-generic-table">
                        <tr>
                            <th>Display Generic</th>
                            <td>
                                <select name="disp_generic" class="control-focus" data-target=".zrx-drug-generic">
                                    <?php foreach ($yesNo as $value => $label): ?>
                                        <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)$options['disp_generic'], $value) ?>><?= preview_escape($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <th>Generic Position</th>
                            <td>
                                <select name="generic_position" class="control-focus" data-target=".zrx-drug-generic">
                                    <option value="below" <?= bridge_selected((string)$options['generic_position'], 'below') ?>>Below Brand Name</option>
                                    <option value="side" <?= bridge_selected((string)$options['generic_position'], 'side') ?>>Side by Side (Inline)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Generic Wrapper</th>
                            <td>
                                <select name="generic_wrapper" class="control-focus" data-target=".zrx-drug-generic">
                                    <option value="none" <?= bridge_selected((string)$options['generic_wrapper'], 'none') ?>>Generic</option>
                                    <option value="parentheses" <?= bridge_selected((string)$options['generic_wrapper'], 'parentheses') ?>>(Generic)</option>
                                    <option value="brackets" <?= bridge_selected((string)$options['generic_wrapper'], 'brackets') ?>>[Generic]</option>
                                    <option value="hyphen" <?= bridge_selected((string)$options['generic_wrapper'], 'hyphen') ?>>- Generic</option>
                                </select>
                            </td>
                            <th>Generic Font Style</th>
                            <td>
                                <select name="generic_font_style" class="control-focus" data-target=".zrx-drug-generic">
                                    <option value="normal" <?= bridge_selected((string)$options['generic_font_style'], 'normal') ?>>Normal</option>
                                    <option value="italic" <?= bridge_selected((string)$options['generic_font_style'], 'italic') ?>>Italic</option>
                                    <option value="bold" <?= bridge_selected((string)$options['generic_font_style'], 'bold') ?>>Bold</option>
                                    <option value="italic-bold" <?= bridge_selected((string)$options['generic_font_style'], 'italic-bold') ?>>Italic-Bold</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Display Rx Symbol</th>
                            <td>
                                <select name="disp_rx" class="control-focus" data-target=".zrx-rx-symbol">
                                    <?php foreach ($yesNo as $value => $label): ?>
                                        <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)$options['disp_rx'], $value) ?>><?= preview_escape($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <th>Drug Row Format</th>
                            <td>
                                <select name="drug_row_format">
                                    <option value="standard" <?= bridge_selected((string)($options['drug_row_format'] ?? 'standard'), 'standard') ?>>Standard Format</option>
                                    <option value="labelled" <?= bridge_selected((string)($options['drug_row_format'] ?? 'standard'), 'labelled') ?>>Labelled Block (Generic: ...)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Generic Name Format</th>
                            <td>
                                <select name="print_generic_name_format">
                                    <option value="plain" <?= bridge_selected((string)($options['print_generic_name_format'] ?? 'plain'), 'plain') ?>>Generic Name</option>
                                    <option value="prescribe" <?= bridge_selected((string)($options['print_generic_name_format'] ?? 'plain'), 'prescribe') ?>>Generic Name Suffix Format</option>
                                    <option value="labelled" <?= bridge_selected((string)($options['print_generic_name_format'] ?? 'plain'), 'labelled') ?>>Generic Name Prefix Format</option>
                                </select>
                            </td>
                            <th>Suffix Prefix Usage</th>
                            <td>
                                <select name="suffix_prefix_usage">
                                    <option value="full" <?= bridge_selected((string)($options['suffix_prefix_usage'] ?? 'full'), 'full') ?>>Full (TABLET, SYRUP, INJECTION)</option>
                                    <option value="short" <?= bridge_selected((string)($options['suffix_prefix_usage'] ?? 'full'), 'short') ?>>Short (TAB., SYP., INJ.)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Dose Language</th>
                            <td>
                                <select name="dose_language">
                                    <?php foreach ($printLanguages as $value => $label): ?>
                                        <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)($options['dose_language'] ?? 'bengali'), $value) ?>><?= preview_escape($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <th>Duration Language</th>
                            <td>
                                <select name="duration_language">
                                    <?php foreach ($printLanguages as $value => $label): ?>
                                        <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)($options['duration_language'] ?? 'bengali'), $value) ?>><?= preview_escape($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Instruction Language</th>
                            <td>
                                <select name="instruction_language">
                                    <?php foreach ($printLanguages as $value => $label): ?>
                                        <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)($options['instruction_language'] ?? 'bengali'), $value) ?>><?= preview_escape($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <th>Advice Language</th>
                            <td>
                                <select name="advice_language">
                                    <?php foreach ($printLanguages as $value => $label): ?>
                                        <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)($options['advice_language'] ?? 'bengali'), $value) ?>><?= preview_escape($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr class="labelled-block-editor-row" style="<?= ($options['drug_row_format'] ?? 'standard') === 'labelled' ? '' : 'display: none;' ?>">
                            <th>Generic Label</th>
                            <td>
                                <input type="text" name="lbl_generic" value="<?= preview_escape((string)($options['lbl_generic'] ?? 'Generic Name:')) ?>" placeholder="Generic Name:">
                            </td>
                            <th>Brand Label</th>
                            <td>
                                <input type="text" name="lbl_brand" value="<?= preview_escape((string)($options['lbl_brand'] ?? 'Brand Name Recommendation:')) ?>" placeholder="Brand Name Recommendation:">
                            </td>
                        </tr>
                        <tr class="labelled-block-editor-row" style="<?= ($options['drug_row_format'] ?? 'standard') === 'labelled' ? '' : 'display: none;' ?>">
                            <th>Instruction Label</th>
                            <td colspan="3">
                                <input type="text" name="lbl_instruction" value="<?= preview_escape((string)($options['lbl_instruction'] ?? 'Instruction:')) ?>" placeholder="Instruction:">
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

        </div>
    </div>

        <!-- Advanced Sections -->
        <div class="print-advanced-sections">



            <div class="print-cards-row-2col">
                <div class="print-advanced-card">
                    <h3>Display Configuration</h3>
                    <div style="overflow-x:auto;">
                        <table class="print-advanced-table">
                            <tr>
                                <th>Display Header</th>
                                <td>
                                    <select name="display_header">
                                        <?php foreach ($yesNo as $value => $label): ?>
                                            <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)($options['display_header'] ?? 'yes'), $value) ?>><?= preview_escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <th>Re-Visit Position</th>
                                <td>
                                    <select name="revisit_position">
                                        <?php foreach ($revisitPositions as $value => $label): ?>
                                            <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)$options['revisit_position'], $value) ?>><?= preview_escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>Display Footer</th>
                                <td>
                                    <select name="display_footer">
                                        <?php foreach ($yesNo as $value => $label): ?>
                                            <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)$options['display_footer'], $value) ?>><?= preview_escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <th>Display Visit No</th>
                                <td>
                                    <select name="visit_number">
                                        <?php foreach ($yesNo as $value => $label): ?>
                                            <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)($options['visit_number'] ?? 'yes'), $value) ?>><?= preview_escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>Display Barcode</th>
                                <td>
                                    <select name="display_barcode">
                                        <?php foreach ($yesNo as $value => $label): ?>
                                            <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)$options['display_barcode'], $value) ?>><?= preview_escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <th>Display Patient Info</th>
                                <td>
                                    <select name="display_pt_info">
                                        <?php foreach ($yesNo as $value => $label): ?>
                                            <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)($options['display_pt_info'] ?? 'yes'), $value) ?>><?= preview_escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>Display Drug No</th>
                                <td>
                                    <select name="display_drug_no">
                                        <?php foreach ($yesNo as $value => $label): ?>
                                            <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)$options['display_drug_no'], $value) ?>><?= preview_escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <th>Patient Info Rows</th>
                                <td>
                                    <select name="info_row">
                                        <option value="1" <?= bridge_selected((string)$options['info_row'], '1') ?>>Single Row (Format 1)</option>
                                        <option value="2" <?= bridge_selected((string)$options['info_row'], '2') ?>>Two Rows (Format 2)</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="print-advanced-card">
                    <h3>Decoration &amp; Lines</h3>
                    <div style="overflow-x:auto;">
                        <table class="print-advanced-table">
                            <tr>
                                <th>Pt Info Top Line</th>
                                <td>
                                    <select name="dec_line_top_1">
                                        <?php foreach ($yesNo as $value => $label): ?>
                                            <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)$options['dec_line_top_1'], $value) ?>><?= preview_escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <th>Pt Info Bottom Line</th>
                                <td>
                                    <select name="dec_line_top_2">
                                        <?php foreach ($yesNo as $value => $label): ?>
                                            <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)$options['dec_line_top_2'], $value) ?>><?= preview_escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>Left Separator Line</th>
                                <td>
                                    <select name="dec_line_left">
                                        <?php foreach ($yesNo as $value => $label): ?>
                                            <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)$options['dec_line_left'], $value) ?>><?= preview_escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <th>Footer Top Line</th>
                                <td>
                                    <select name="dec_line_bottom">
                                        <?php foreach ($yesNo as $value => $label): ?>
                                            <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)$options['dec_line_bottom'], $value) ?>><?= preview_escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>History Bullet Icon</th>
                                <td>
                                    <select name="bullet_text">
                                        <?php foreach ($historyBulletOptions as $value => $label): ?>
                                            <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)$options['bullet_text'], $value) ?>><?= preview_escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <th id="drug-marker-style-label">Drug Bullet Icon</th>
                                <td>
                                    <select name="drug_bullet" id="drug-bullet-select">
                                        <?php foreach ($drugBulletOptions as $value => $label): ?>
                                            <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)$options['drug_bullet'], $value) ?>><?= preview_escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="drug_no_style" id="drug-no-style-select" hidden>
                                        <?php foreach ($drugNoStyleOptions as $value => $label): ?>
                                            <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)($options['drug_no_style'] ?? 'period'), $value) ?>><?= preview_escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>Presenting Complaints Format</th>
                                <td colspan="3">
                                    <select name="pc_format" style="width: 100%; max-width: 320px;">
                                        <?php foreach ($pcFormatOptions as $value => $label): ?>
                                            <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)($options['pc_format'] ?? 'parentheses'), $value) ?>><?= preview_escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>Diagnosis Format</th>
                                <td>
                                    <select name="dx_format" id="dx-format-select" style="width: 100%; max-width: 280px;">
                                        <?php foreach ($dxFormats as $value => $label): ?>
                                            <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)($options['dx_format'] ?? 'per_line'), $value) ?>><?= preview_escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <th>Diagnosis Bullet Icon</th>
                                <td>
                                    <select name="dx_bullet" id="dx-bullet-select">
                                        <?php foreach ($dxBulletOptions as $value => $label): ?>
                                            <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)($options['dx_bullet'] ?? '○'), $value) ?>><?= preview_escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="print-advanced-card">
                <h3>Patient Info Configuration</h3>
                <div style="overflow-x:auto;">
                    <table class="print-advanced-table visibility-table">
                        <thead>
                            <tr>
                                <th>Field Name</th>
                                <th>Show Value</th>
                                <th>Show Label</th>
                                <th>Edit Label</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visibilityFields as $key => $label): ?>
                                <tr>
                                    <td><strong><?= preview_escape($label) ?></strong></td>
                                    <td>
                                        <select name="display_<?= $key ?>">
                                            <?php foreach ($yesNo as $value => $yn): ?>
                                                <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)$options['display_' . $key], $value) ?>><?= preview_escape($yn) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="display_<?= $key ?>_t">
                                            <?php foreach ($yesNo as $value => $yn): ?>
                                                <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)$options['display_' . $key . '_t'], $value) ?>><?= preview_escape($yn) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <label class="control-focus" data-target=".zrx-patient-strip">
                                            <input type="text" name="patient_label_<?= $key ?>" value="<?= preview_escape((string)($options['patient_label_' . $key] ?? $label)) ?>" aria-label="<?= preview_escape($label) ?> label">
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="print-advanced-card">
                <h3>Left Side Sections</h3>
                
                <!-- Hidden inputs to preserve values for all module labels -->
                <div id="hidden-module-labels-container">
                    <input type="hidden" name="pc_name" value="<?= preview_escape((string)($options['pc_name'] ?? 'Presenting Complaints')) ?>">
                    <input type="hidden" name="history_name" value="<?= preview_escape((string)($options['history_name'] ?? 'History')) ?>">
                    <input type="hidden" name="oe_name" value="<?= preview_escape((string)($options['oe_name'] ?? 'Physical Examination')) ?>">
                    <input type="hidden" name="report_name" value="<?= preview_escape((string)($options['report_name'] ?? 'Reports')) ?>">
                    <input type="hidden" name="edd_name" value="<?= preview_escape((string)($options['edd_name'] ?? 'OT Note')) ?>">
                    <input type="hidden" name="plan_name" value="<?= preview_escape((string)($options['plan_name'] ?? 'Plan')) ?>">
                    <input type="hidden" name="oh_name" value="<?= preview_escape((string)($options['oh_name'] ?? 'O/H')) ?>">
                    <input type="hidden" name="mh_name" value="<?= preview_escape((string)($options['mh_name'] ?? 'M/H')) ?>">
                    <input type="hidden" name="ix_name" value="<?= preview_escape((string)($options['ix_name'] ?? 'Investigations')) ?>">
                    <input type="hidden" name="note_name" value="<?= preview_escape((string)($options['note_name'] ?? 'Note')) ?>">
                    <input type="hidden" name="dx_name" value="<?= preview_escape((string)($options['dx_name'] ?? 'Dx')) ?>">
                </div>

                <div class="print-advanced-split" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div style="overflow-x:auto;">
                        <table class="print-advanced-table slot-table">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Print Order</th>
                                    <th>Module</th>
                                    <th>Edit Label</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 1; $i <= 7; $i++): ?>
                                    <tr data-slot="<?= $i ?>">
                                        <td><strong>Order <?= str_pad((string)$i, 2, '0', STR_PAD_LEFT) ?></strong></td>
                                        <td>
                                            <select name="print_pos_<?= $i ?>" class="slot-select" data-slot="<?= $i ?>">
                                                <?php foreach ($printPosOptions as $value => $label): ?>
                                                    <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)$options['print_pos_' . $i], $value) ?>><?= preview_escape($label) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" class="slot-label-input" data-slot="<?= $i ?>" placeholder="Label...">
                                        </td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="print-advanced-table slot-table">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Print Order</th>
                                    <th>Module</th>
                                    <th>Edit Label</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 8; $i <= 14; $i++): ?>
                                    <tr data-slot="<?= $i ?>">
                                        <td><strong>Order <?= str_pad((string)$i, 2, '0', STR_PAD_LEFT) ?></strong></td>
                                        <td>
                                            <select name="print_pos_<?= $i ?>" class="slot-select" data-slot="<?= $i ?>">
                                                <?php foreach ($printPosOptions as $value => $label): ?>
                                                    <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)$options['print_pos_' . $i], $value) ?>><?= preview_escape($label) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" class="slot-label-input" data-slot="<?= $i ?>" placeholder="Label...">
                                        </td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <h4 style="margin-top: 1.5rem; margin-bottom: 0.75rem; border-top: 1px solid var(--print-border-soft); padding-top: 1.25rem;">History Sections</h4>
                
                <!-- Hidden inputs for History submodule labels -->
                <div id="hidden-history-labels-container">
                    <input type="hidden" name="lbl_history_medical" value="<?= preview_escape((string)($options['lbl_history_medical'] ?? 'Medical History:')) ?>">
                    <input type="hidden" name="lbl_history_treatments" value="<?= preview_escape((string)($options['lbl_history_treatments'] ?? 'Treatment History:')) ?>">
                    <input type="hidden" name="lbl_history_habits" value="<?= preview_escape((string)($options['lbl_history_habits'] ?? 'Habits:')) ?>">
                    <input type="hidden" name="lbl_history_diet" value="<?= preview_escape((string)($options['lbl_history_diet'] ?? 'Diet:')) ?>">
                    <input type="hidden" name="lbl_history_hypersensitivity" value="<?= preview_escape((string)($options['lbl_history_hypersensitivity'] ?? 'Hypersensitivity:')) ?>">
                    <input type="hidden" name="lbl_history_drug" value="<?= preview_escape((string)($options['lbl_history_drug'] ?? 'Drug History:')) ?>">
                </div>

                <div class="print-advanced-split" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div style="overflow-x:auto;">
                        <table class="print-advanced-table history-slot-table">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Print Order</th>
                                    <th>History Field</th>
                                    <th>Edit Label</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 1; $i <= 3; $i++): ?>
                                    <tr data-history-slot="<?= $i ?>">
                                        <td><strong>Order <?= str_pad((string)$i, 2, '0', STR_PAD_LEFT) ?></strong></td>
                                        <td>
                                            <select name="print_history_pos_<?= $i ?>" class="history-slot-select" data-history-slot="<?= $i ?>">
                                                <?php foreach ($printHistoryPosOptions as $value => $label): ?>
                                                    <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)($options['print_history_pos_' . $i] ?? ''), $value) ?>><?= preview_escape($label) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" class="history-slot-label-input" data-history-slot="<?= $i ?>" placeholder="Label...">
                                        </td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="print-advanced-table history-slot-table">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Print Order</th>
                                    <th>History Field</th>
                                    <th>Edit Label</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 4; $i <= 6; $i++): ?>
                                    <tr data-history-slot="<?= $i ?>">
                                        <td><strong>Order <?= str_pad((string)$i, 2, '0', STR_PAD_LEFT) ?></strong></td>
                                        <td>
                                            <select name="print_history_pos_<?= $i ?>" class="history-slot-select" data-history-slot="<?= $i ?>">
                                                <?php foreach ($printHistoryPosOptions as $value => $label): ?>
                                                    <option value="<?= preview_escape($value) ?>" <?= bridge_selected((string)($options['print_history_pos_' . $i] ?? ''), $value) ?>><?= preview_escape($label) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" class="history-slot-label-input" data-history-slot="<?= $i ?>" placeholder="Label...">
                                        </td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>


        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('print-setup-form');
    const toast = document.getElementById('print-setup-toast');
    const toastMessage = document.getElementById('print-setup-toast-message');
    const toastClose = document.getElementById('print-setup-toast-close');
    const frame = document.getElementById('setup-preview-frame');
    const wrap = document.getElementById('paper-wrap');
    const resetButton = document.getElementById('factory-reset-btn');

    const showToast = (message, type = 'success') => {
        if (!toast || !toastMessage) return;
        toastMessage.textContent = message;
        toast.dataset.type = type;
        toast.hidden = false;
        if (toastClose) toastClose.focus();
    };

    toastClose?.addEventListener('click', () => {
        if (toast) toast.hidden = true;
    });

    toast?.addEventListener('click', (e) => {
        if (e.target === toast) {
            toast.hidden = true;
        }
    });

    // Highlighting Logic
    document.querySelectorAll('.control-focus').forEach(label => {
        const input = label.querySelector('input, select');
        if (!input) return;

        const selector = label.dataset.target;
        if (!selector) return;

        input.addEventListener('focus', () => {
            const doc = frame.contentDocument;
            if (!doc) return;
            doc.querySelectorAll(selector).forEach(el => el.classList.add('highlight-active'));
        });

        input.addEventListener('blur', () => {
            const doc = frame.contentDocument;
            if (!doc) return;
            doc.querySelectorAll(selector).forEach(el => el.classList.remove('highlight-active'));
        });
    });

    const numberValue = (name, fallback = 0) => {
        const field = form.elements[name];
        if (!field) return fallback;
        const value = parseFloat(field.value);
        return Number.isFinite(value) ? value : fallback;
    };

    const textValue = (name, fallback = '') => {
        const field = form.elements[name];
        return field ? field.value : fallback;
    };

    let lastPreviewDrugFormat = '';
    let lastPreviewPatientLabels = '';

    // Left History Slots Label Editing Sync Logic
    const moduleLabelMap = {
        'pc': 'pc_name',
        'ho': 'history_name',
        'pe': 'pe_name',
        'oe': 'oe_name',
        'reports': 'report_name',
        'edd': 'edd_name',
        'plan': 'plan_name',
        'otnote': 'edd_name',
        'oh': 'oh_name',
        'mh': 'mh_name',
        'advice': 'ix_name',
        'note': 'note_name',
        'dx': 'dx_name'
    };

    const updateSlotLabelInput = (slotNum) => {
        const select = form.querySelector(`.slot-select[data-slot="${slotNum}"]`);
        const input = form.querySelector(`.slot-label-input[data-slot="${slotNum}"]`);
        if (!select || !input) return;

        const val = select.value;
        const targetFieldName = moduleLabelMap[val];

        if (!targetFieldName) {
            input.value = '';
            input.placeholder = 'None';
            input.disabled = true;
            input.classList.add('is-control-disabled');
        } else {
            const hiddenField = form.querySelector(`input[name="${targetFieldName}"]`);
            input.value = hiddenField ? hiddenField.value : '';
            input.placeholder = 'Label...';
            input.disabled = false;
            input.classList.remove('is-control-disabled');
        }
    };

    const tidySlots = () => {
        const activeValues = [];
        for (let i = 1; i <= 14; i++) {
            const select = form.querySelector(`.slot-select[data-slot="${i}"]`);
            if (select && select.value !== 'none') {
                activeValues.push(select.value);
            }
        }

        for (let i = 1; i <= 14; i++) {
            const select = form.querySelector(`.slot-select[data-slot="${i}"]`);
            if (select) {
                const newValue = activeValues[i - 1] || 'none';
                if (select.value !== newValue) {
                    select.value = newValue;
                }
                updateSlotLabelInput(i);
            }
        }
    };

    form.querySelectorAll('.slot-select').forEach(select => {
        select.addEventListener('change', (e) => {
            tidySlots();
            refreshPreview();
        });
    });

    form.querySelectorAll('.slot-label-input').forEach(input => {
        input.addEventListener('input', (e) => {
            const slotNum = e.target.dataset.slot;
            const select = form.querySelector(`.slot-select[data-slot="${slotNum}"]`);
            if (!select) return;

            const val = select.value;
            const targetFieldName = moduleLabelMap[val];
            if (!targetFieldName) return;

            const newValue = e.target.value;
            const hiddenField = form.querySelector(`input[name="${targetFieldName}"]`);
            if (hiddenField) {
                hiddenField.value = newValue;
                hiddenField.dispatchEvent(new Event('input', { bubbles: true }));
                hiddenField.dispatchEvent(new Event('change', { bubbles: true }));
            }

            form.querySelectorAll('.slot-select').forEach(otherSelect => {
                if (otherSelect !== select && otherSelect.value === val) {
                    const otherSlotNum = otherSelect.dataset.slot;
                    const otherInput = form.querySelector(`.slot-label-input[data-slot="${otherSlotNum}"]`);
                    if (otherInput) {
                        otherInput.value = newValue;
                    }
                }
            });
        });
    });

    // History Slots Sync and Tidy Logic
    const historyModuleLabelMap = {
        'medical': 'lbl_history_medical',
        'treatments': 'lbl_history_treatments',
        'habits': 'lbl_history_habits',
        'diet': 'lbl_history_diet',
        'hypersensitivity': 'lbl_history_hypersensitivity',
        'drug_history': 'lbl_history_drug'
    };

    const updateHistorySlotLabelInput = (slotNum) => {
        const select = form.querySelector(`.history-slot-select[data-history-slot="${slotNum}"]`);
        const input = form.querySelector(`.history-slot-label-input[data-history-slot="${slotNum}"]`);
        if (!select || !input) return;

        const val = select.value;
        const targetFieldName = historyModuleLabelMap[val];

        if (!targetFieldName) {
            input.value = '';
            input.placeholder = 'None';
            input.disabled = true;
            input.classList.add('is-control-disabled');
        } else {
            const hiddenField = form.querySelector(`input[name="${targetFieldName}"]`);
            input.value = hiddenField ? hiddenField.value : '';
            input.placeholder = 'Label...';
            input.disabled = false;
            input.classList.remove('is-control-disabled');
        }
    };

    const tidyHistorySlots = () => {
        const activeValues = [];
        for (let i = 1; i <= 6; i++) {
            const select = form.querySelector(`.history-slot-select[data-history-slot="${i}"]`);
            if (select && select.value !== 'none') {
                activeValues.push(select.value);
            }
        }

        for (let i = 1; i <= 6; i++) {
            const select = form.querySelector(`.history-slot-select[data-history-slot="${i}"]`);
            if (select) {
                const newValue = activeValues[i - 1] || 'none';
                if (select.value !== newValue) {
                    select.value = newValue;
                }
                updateHistorySlotLabelInput(i);
            }
        }
    };

    form.querySelectorAll('.history-slot-select').forEach(select => {
        select.addEventListener('change', () => {
            tidyHistorySlots();
            refreshPreview();
        });
    });

    form.querySelectorAll('.history-slot-label-input').forEach(input => {
        input.addEventListener('input', (e) => {
            const slotNum = e.target.dataset.historySlot;
            const select = form.querySelector(`.history-slot-select[data-history-slot="${slotNum}"]`);
            if (!select) return;

            const val = select.value;
            const targetFieldName = historyModuleLabelMap[val];
            if (!targetFieldName) return;

            const newValue = e.target.value;
            const hiddenField = form.querySelector(`input[name="${targetFieldName}"]`);
            if (hiddenField) {
                hiddenField.value = newValue;
                hiddenField.dispatchEvent(new Event('input', { bubbles: true }));
                hiddenField.dispatchEvent(new Event('change', { bubbles: true }));
            }

            form.querySelectorAll('.history-slot-select').forEach(otherSelect => {
                if (otherSelect !== select && otherSelect.value === val) {
                    const otherSlotNum = otherSelect.dataset.historySlot;
                    const otherInput = form.querySelector(`.history-slot-label-input[data-history-slot="${otherSlotNum}"]`);
                    if (otherInput) {
                        otherInput.value = newValue;
                    }
                }
            });
        });
    });

    // Tidy slots and set initial values on page load
    tidySlots();
    for (let i = 1; i <= 14; i++) {
        updateSlotLabelInput(i);
    }
    tidyHistorySlots();
    for (let i = 1; i <= 6; i++) {
        updateHistorySlotLabelInput(i);
    }

    const syncPreviewDrugFormat = () => {
        if (!frame || !frame.contentWindow) return;

        const message = {
            type: 'SYNC_PREVIEW_DRUG_FORMAT',
            options: {
                disp_generic: textValue('disp_generic', 'yes'),
                display_drug_no: textValue('display_drug_no', 'yes'),
                drug_bullet: textValue('drug_bullet', '•'),
                drug_no_style: textValue('drug_no_style', 'period'),
                drug_row_format: textValue('drug_row_format', 'standard'),
                print_generic_name_format: textValue('print_generic_name_format', 'plain'),
                suffix_prefix_usage: textValue('suffix_prefix_usage', 'full'),
                generic_position: textValue('generic_position', 'below'),
                generic_wrapper: textValue('generic_wrapper', 'parentheses'),
                lbl_generic: textValue('lbl_generic', 'Generic Name:'),
                lbl_brand: textValue('lbl_brand', 'Brand Name Recommendation:'),
                lbl_instruction: textValue('lbl_instruction', 'Instruction:'),
                dose_language: textValue('dose_language', 'bengali'),
                duration_language: textValue('duration_language', 'bengali'),
                instruction_language: textValue('instruction_language', 'bengali'),
                advice_language: textValue('advice_language', 'bengali')
            }
        };
        const serialized = JSON.stringify(message.options);
        if (serialized === lastPreviewDrugFormat) return;
        lastPreviewDrugFormat = serialized;
        frame.contentWindow.postMessage(message, window.location.origin);
    };

    const syncPreviewPatientLabels = () => {
        if (!frame || !frame.contentWindow) return;

        const labels = {};
        form.querySelectorAll('[name^="patient_label_"]').forEach(field => {
            labels[field.name] = field.value;
        });
        labels.info_row = textValue('info_row', '2');
        labels.pc_format = textValue('pc_format', 'parentheses');
        ['name', 'age', 'sex', 'address', 'mobile', 'weight', 'reg_no', 'date'].forEach(field => {
            labels[`display_${field}`] = textValue(`display_${field}`, 'yes');
            labels[`display_${field}_t`] = textValue(`display_${field}_t`, 'yes');
        });

        // Sync clinical slot order and module headers
        for (let i = 1; i <= 14; i++) {
            labels[`print_pos_${i}`] = textValue(`print_pos_${i}`, 'none');
        }
        for (let i = 1; i <= 6; i++) {
            labels[`print_history_pos_${i}`] = textValue(`print_history_pos_${i}`, 'none');
        }
        const moduleNames = [
            'pc_name', 'history_name', 'oe_name', 'dx_name', 'ix_name', 'dh_name', 'plan_name', 'note_name', 'oh_name', 'mh_name', 'report_name', 'edd_name',
            'lbl_history_medical', 'lbl_history_treatments', 'lbl_history_habits', 'lbl_history_diet', 'lbl_history_hypersensitivity', 'lbl_history_drug'
        ];
        moduleNames.forEach(name => {
            labels[name] = textValue(name, '');
        });

        const serialized = JSON.stringify(labels);
        if (serialized === lastPreviewPatientLabels) return;
        lastPreviewPatientLabels = serialized;
        frame.contentWindow.postMessage({ type: 'SYNC_PREVIEW_PATIENT_LABELS', options: labels }, window.location.origin);
    };

    const setPreviewStyle = (selector, property, value) => {
        const doc = frame.contentDocument;
        if (!doc) return;
        doc.querySelectorAll(selector).forEach(node => {
            node.style[property] = value;
        });
    };

    const refreshPreview = () => {
        const scale = 0.65;
        const pageWidth = numberValue('page_width', 21);
        const pageHeight = numberValue('page_height', 29.7);
        const headerHeight = numberValue('header_height', 5.3);
        const ptInfoHeight = numberValue('pt_info_height', 1.6);
        const leftWidth = numberValue('left_width', 9);
        const footerHeight = numberValue('footer_height', 2);
        const rightWidth = numberValue('right_width', 11);

        // Always guarantee wrap and frame sizing immediately
        if (frame) {
            frame.style.width = `${pageWidth}cm`;
            frame.style.height = `${pageHeight}cm`;
            frame.style.transform = `scale(${scale})`;
        }
        if (wrap) {
            wrap.style.width = `calc(${pageWidth}cm * ${scale})`;
            wrap.style.height = `calc(${pageHeight}cm * ${scale})`;
        }

        const doc = frame ? frame.contentDocument : null;
        if (!doc || !doc.body) return;
        syncPreviewDrugFormat();
        syncPreviewPatientLabels();

        const headerVisible = textValue('preview_header_type', 'with_header') !== 'without_header';
        const footerVisible = textValue('display_footer', 'yes') !== 'no';
        const showRx = textValue('disp_rx', 'yes') === 'yes';

        const bodyHeight = Math.max(0, pageHeight - (headerVisible ? headerHeight : 0) - ptInfoHeight - (footerVisible ? footerHeight : 0));

        setPreviewStyle('.zrx-print-page', 'width', `${pageWidth}cm`);
        setPreviewStyle('.zrx-print-page', 'minHeight', `${pageHeight}cm`);

        setPreviewStyle('.zrx-print-header', 'height', `${headerHeight}cm`);
        setPreviewStyle('.zrx-print-header', 'display', headerVisible ? 'block' : 'none');

        setPreviewStyle('.zrx-patient-strip', 'height', `${ptInfoHeight}cm`);

        setPreviewStyle('.zrx-body-left', 'height', `${bodyHeight}cm`);
        setPreviewStyle('.zrx-body-left', 'width', `${leftWidth}cm`);

        setPreviewStyle('.zrx-body-right', 'height', `${bodyHeight}cm`);
        setPreviewStyle('.zrx-body-right', 'width', `${rightWidth}cm`);

        const footerNode = doc.querySelector('.zrx-print-footer');
        const footerHasContent = footerNode ? footerNode.innerHTML.trim() !== '' : false;
        setPreviewStyle('.zrx-print-footer', 'height', `${footerHeight}cm`);
        setPreviewStyle('.zrx-print-footer', 'display', (footerVisible && footerHasContent) ? 'block' : 'none');

        setPreviewStyle('.zrx-patient-table', 'width', `${numberValue('pt_info_width', 90)}%`);
        setPreviewStyle('.zrx-patient-table', 'marginTop', `${numberValue('pt_info_margin_top', 0)}px`);
        setPreviewStyle('.zrx-patient-table', 'marginBottom', `${numberValue('pt_info_margin_bottom', 0)}px`);
        setPreviewStyle('.zrx-patient-strip', 'fontSize', `${numberValue('pt_info_font_size', 12)}pt`);
        setPreviewStyle('.zrx-patient-strip', 'fontFamily', `"${textValue('pt_info_font', 'Times New Roman')}"`);
        setPreviewStyle('.zrx-left-content', 'marginLeft', `${numberValue('left_margin_left', 70)}px`);
        setPreviewStyle('.zrx-left-content', 'marginTop', `${numberValue('left_margin_top', 0)}px`);
        setPreviewStyle('.zrx-left-content', 'fontSize', `${numberValue('left_font_size', 11)}pt`);
        setPreviewStyle('.zrx-left-content', 'fontFamily', `"${textValue('left_font', 'Times New Roman')}"`);
        setPreviewStyle('.zrx-left-content table td', 'lineHeight', `${numberValue('left_line_height', 10)}pt`);

        setPreviewStyle('.zrx-prescription-main', 'marginLeft', `${numberValue('pres_main_left_margin', 40)}px`);
        setPreviewStyle('.zrx-prescription-main', 'marginTop', `${numberValue('pres_main_margin_top', 10)}px`);

        setPreviewStyle('.zrx-drug-table td', 'lineHeight', `${numberValue('pres_line_height', 11)}pt`);
        setPreviewStyle('.zrx-drug-gap-row, .zrx-drug-gap', 'height', `${numberValue('pres_gap_height', 5)}pt`);
        setPreviewStyle('.zrx-drug-gap-row, .zrx-drug-gap', 'lineHeight', `${numberValue('pres_gap_height', 5)}pt`);
        setPreviewStyle('.zrx-drug-gap-row, .zrx-drug-gap', 'fontSize', `${numberValue('pres_gap_height', 5)}pt`);
        setPreviewStyle('.zrx-drug-gap-row, .zrx-drug-gap', 'padding', '0px');
        setPreviewStyle('.zrx-drug-number', 'width', `${numberValue('dr_n_gap', 5)}px`);
        setPreviewStyle('.zrx-drug-dose', 'paddingLeft', '0px');
        setPreviewStyle('.zrx-drug-dose', 'textIndent', `${numberValue('dose_lt_padding', 0)}px`);
        setPreviewStyle('.zrx-drug-brand', 'fontSize', `${numberValue('right_font_size', 11)}pt`);
        setPreviewStyle('.zrx-drug-brand', 'fontFamily', `"${textValue('right_font', 'Times New Roman')}"`);
        setPreviewStyle('.zrx-drug-dose, .zrx-drug-instruction, .zrx-drug-duration', 'fontSize', `${numberValue('bn_font_size', 10.5)}pt`);
        setPreviewStyle('.zrx-drug-dose, .zrx-drug-instruction, .zrx-drug-duration', 'fontFamily', `"${textValue('bn_font', 'SolaimanLipi')}"`);
        setPreviewStyle('.zrx-advice-table td', 'fontSize', `${numberValue('upd_font_size', 10.5)}pt`);
        setPreviewStyle('.zrx-advice-table td', 'fontFamily', `"${textValue('upd_font', 'SolaimanLipi')}"`);
        setPreviewStyle('.zrx-advice-table td', 'lineHeight', `${numberValue('upd_line_height', 14)}pt`);

        setPreviewStyle('.zrx-rx-mark', 'visibility', showRx ? 'visible' : 'hidden');
        setPreviewStyle('.zrx-rx-symbol', 'marginLeft', `${numberValue('rx_block_margin_left', 10)}px`);
        setPreviewStyle('.zrx-rx-symbol', 'marginTop', `${numberValue('rx_block_margin_top', 7)}px`);
        setPreviewStyle('.zrx-rx-symbol', 'fontSize', `${numberValue('rx_font_size', 18)}pt`);
        setPreviewStyle('.zrx-rx-symbol', 'fontFamily', `"${textValue('rx_font', 'Lucida Calligraphy')}"`);

        // Visibility & Display Config Live Updates
        const displayHeader = textValue('display_header', 'yes') === 'yes';
        setPreviewStyle('.zrx-header-layout', 'visibility', displayHeader ? 'visible' : 'hidden');

        const displayPtInfo = textValue('display_pt_info', 'yes') === 'yes';
        setPreviewStyle('.zrx-patient-table', 'visibility', displayPtInfo ? 'visible' : 'hidden');

        const showBarcode = textValue('display_barcode', 'yes') === 'yes';
        setPreviewStyle('#preview-barcode-wrap', 'display', showBarcode ? 'block' : 'none');

        const showVisitNo = textValue('visit_number', 'yes') === 'yes';
        setPreviewStyle('#preview-visit-no', 'display', showVisitNo ? 'block' : 'none');

        const drugRowFormat = textValue('drug_row_format', 'standard');
        const showGeneric = textValue('disp_generic', 'yes') === 'yes';
        const genericPosition = textValue('generic_position', 'below');
        const genericStyle = textValue('generic_font_style', 'italic');
        const genericMarginLeft = numberValue('generic_margin_left', 0);
        const genericMarginTop = numberValue('generic_margin_top', 0);

        if (drugRowFormat === 'labelled') {
            const brandFontSize = numberValue('right_font_size', 11);
            setPreviewStyle('.zrx-drug-generic', 'display', showGeneric ? 'inline-block' : 'none');
            setPreviewStyle('.zrx-drug-generic', 'fontFamily', '"Times New Roman", serif');
            setPreviewStyle('.zrx-drug-generic', 'fontSize', `${brandFontSize}pt`);
            setPreviewStyle('.zrx-drug-generic', 'fontStyle', 'normal');
            setPreviewStyle('.zrx-drug-generic', 'fontWeight', 'normal');
            setPreviewStyle('.zrx-drug-generic', 'marginLeft', `${genericMarginLeft}px`);
            setPreviewStyle('.zrx-drug-generic', 'marginTop', `${genericMarginTop}px`);
        } else {
            setPreviewStyle('.zrx-drug-generic', 'display', showGeneric ? (genericPosition === 'below' ? 'block' : 'inline-block') : 'none');
            setPreviewStyle('.zrx-drug-generic', 'fontFamily', `"${textValue('generic_font', 'Times New Roman')}"`);
            setPreviewStyle('.zrx-drug-generic', 'fontSize', `${numberValue('generic_font_size', 10)}pt`);
            setPreviewStyle('.zrx-drug-generic', 'fontStyle', (genericStyle === 'italic' || genericStyle === 'italic-bold') ? 'italic' : 'normal');
            setPreviewStyle('.zrx-drug-generic', 'fontWeight', (genericStyle === 'bold' || genericStyle === 'italic-bold') ? 'bold' : 'normal');
            setPreviewStyle('.zrx-drug-generic', 'marginLeft', `${genericMarginLeft + (genericPosition === 'side' ? 5 : 0)}px`);
            setPreviewStyle('.zrx-drug-generic', 'marginTop', `${genericMarginTop}px`);
        }

        const showDrugNo = textValue('display_drug_no', 'yes') === 'yes';
        const drugNoStyle = textValue('drug_no_style', 'period');
        const drugBullet = textValue('drug_bullet', 'â€¢') || 'â€¢';
        const drugMarkerLabel = document.getElementById('drug-marker-style-label');
        const drugBulletSelect = document.getElementById('drug-bullet-select');
        const drugNoStyleSelect = document.getElementById('drug-no-style-select');
        if (drugMarkerLabel) drugMarkerLabel.textContent = showDrugNo ? 'Drug No Style' : 'Drug Bullet Icon';
        if (drugBulletSelect) drugBulletSelect.hidden = showDrugNo;
        if (drugNoStyleSelect) drugNoStyleSelect.hidden = !showDrugNo;

        const formatDrugNumber = (number) => {
            if (drugNoStyle === 'round_brackets') return `(${number})`;
            if (drugNoStyle === 'closing_bracket') return `${number})`;
            if (drugNoStyle === 'square_brackets') return `[${number}]`;
            return `${number}.`;
        };
        doc.querySelectorAll('.zrx-drug-name-row').forEach((row, i) => {
            const cell = row.querySelector('.zrx-drug-number');
            if (cell) {
                cell.textContent = showDrugNo ? formatDrugNumber(i + 1) : drugBullet;
            }
        });

        const histBullet = textValue('bullet_text', '○') || '○';
        const dxBulletVal = textValue('dx_bullet', '') || histBullet;
        doc.querySelectorAll('.zrx-bullet-cell').forEach(cell => {
            const section = cell.closest('.zrx-clinical-section--dx');
            if (section) {
                cell.textContent = dxBulletVal;
            } else {
                const text = cell.textContent.trim();
                cell.textContent = text.replace(/^[^\u0041-\u007A\u0030-\u0039\s]+/, histBullet);
            }
        });
        doc.querySelectorAll('.zrx-history-bullet').forEach(cell => {
            const text = cell.textContent.trim();
            cell.textContent = text.replace(/^[^\u0041-\u007A\u0030-\u0039\s]+/, histBullet);
        });

        const showTopLine1 = textValue('dec_line_top_1', 'yes') === 'yes';
        setPreviewStyle('.zrx-print-header', 'borderBottom', showTopLine1 ? '1px solid #000' : 'none');

        const showTopLine2 = textValue('dec_line_top_2', 'yes') === 'yes';
        setPreviewStyle('.zrx-patient-strip', 'borderBottom', showTopLine2 ? '1px solid #000' : 'none');

        const showLeftLine = textValue('dec_line_left', 'yes') === 'yes';
        setPreviewStyle('.zrx-body-right', 'borderLeft', showLeftLine ? '1px solid #000' : 'none');

        const showFooterLine = textValue('dec_line_bottom', 'yes') === 'yes';
        setPreviewStyle('.zrx-print-footer', 'borderTop', showFooterLine ? '1px solid #000' : 'none');

        const revisitPos = textValue('revisit_position', 'bottom');
        if (revisitPos === 'top') {
            setPreviewStyle('.zrx-followup', 'position', 'relative');
            setPreviewStyle('.zrx-followup', 'marginLeft', `${numberValue('pres_main_left_margin', 40)}px`);
            setPreviewStyle('.zrx-followup', 'textAlign', 'left');
            setPreviewStyle('.zrx-followup', 'right', 'auto');
            setPreviewStyle('.zrx-followup', 'bottom', 'auto');
        } else {
            setPreviewStyle('.zrx-followup', 'position', 'absolute');
            setPreviewStyle('.zrx-followup', 'right', '15%');
            setPreviewStyle('.zrx-followup', 'bottom', '2%');
            setPreviewStyle('.zrx-followup', 'textAlign', 'right');
            setPreviewStyle('.zrx-followup', 'marginLeft', '0px');
        }
        const isGenericEnabled = textValue('disp_generic', 'yes') === 'yes';
        const isLabelledBlock = textValue('drug_row_format', 'standard') === 'labelled';

        // Toggle Label Editor rows visibility
        document.querySelectorAll('.labelled-block-editor-row').forEach(row => {
            row.style.display = isLabelledBlock ? 'table-row' : 'none';
        });

        const lblGeneric = textValue('lbl_generic', 'Generic Name:');
        const lblBrand = textValue('lbl_brand', 'Brand Name Recommendation:');
        const lblInstruction = textValue('lbl_instruction', 'Instruction:');

        doc.querySelectorAll('.zrx-lbl-generic').forEach(el => el.textContent = lblGeneric + ' ');
        doc.querySelectorAll('.zrx-lbl-brand').forEach(el => el.textContent = lblBrand + ' ');
        doc.querySelectorAll('.zrx-lbl-instruction').forEach(el => el.textContent = lblInstruction + ' ');

        const genericDependentNames = [
            'generic_position',
            'generic_wrapper',
            'generic_font_style',
            'disp_rx',
            'drug_row_format',
            'print_generic_name_format',
            'generic_font',
            'generic_font_size',
            'generic_margin_left',
            'generic_margin_top'
        ];

        genericDependentNames.forEach(name => {
            const el = form.elements[name];
            if (!el) return;

            let isDisabled = !isGenericEnabled;

            if (isGenericEnabled && isLabelledBlock) {
                // When labelled block is active, deactivate position, wrapper, font style, and font size
                if (['generic_position', 'generic_wrapper', 'generic_font_style', 'generic_font_size'].includes(name)) {
                    isDisabled = true;
                }
            }

            el.disabled = isDisabled;
            if (isDisabled) {
                el.classList.add('is-control-disabled');
            } else {
                el.classList.remove('is-control-disabled');
            }
        });
    };

    frame.addEventListener('load', () => {
        lastPreviewDrugFormat = '';
        lastPreviewPatientLabels = '';
        refreshPreview();
    });
    window.addEventListener('message', event => {
        if (event.origin !== window.location.origin) return;
        if (event.data && event.data.type === 'PREVIEW_DOM_READY') {
            refreshPreview();
        }
    });
    form.addEventListener('input', refreshPreview);
    form.addEventListener('change', refreshPreview);
    refreshPreview();

    const serializeFullFormData = () => {
        const disabledElems = Array.from(form.querySelectorAll(':disabled'));
        disabledElems.forEach(el => el.disabled = false);
        const data = new URLSearchParams(new FormData(form)).toString();
        // Immediately restore the correct dynamic disabled state for current form values
        refreshPreview();
        return data;
    };

    if (form.elements['drug_row_format']) {
        form.elements['drug_row_format'].addEventListener('change', () => {
            const isLabelledBlock = form.elements['drug_row_format'].value === 'labelled';
            if (form.elements['print_generic_name_format']) {
                form.elements['print_generic_name_format'].value = isLabelledBlock ? 'labelled' : 'plain';
            }
            if (!isLabelledBlock && form.elements['generic_font_style']) {
                form.elements['generic_font_style'].value = 'italic';
            }
            refreshPreview();
        });
    }

    if (form.elements['info_row']) {
        form.elements['info_row'].addEventListener('change', () => {
            const value = form.elements['info_row'].value === '1' ? 'no' : 'yes';
            ['address', 'reg_no', 'weight', 'mobile'].forEach(field => {
                const valueControl = form.elements[`display_${field}`];
                const labelControl = form.elements[`display_${field}_t`];
                if (valueControl) valueControl.value = value;
                if (labelControl) labelControl.value = value;
            });
            refreshPreview();
        });
    }



    resetButton.addEventListener('click', () => {
        const confirmModal = document.getElementById('print-setup-confirm-modal');
        const confirmYes = document.getElementById('confirm-reset-yes');
        const confirmCancel = document.getElementById('confirm-reset-cancel');

        if (confirmModal) {
            confirmModal.hidden = false;

            confirmYes.onclick = async () => {
                confirmModal.hidden = true;
                try {
                    const response = await fetch('reset_print_setup.php');
                    if ((await response.text()).trim() === '1') window.location.reload();
                    else showToast('Reset failed', 'error');
                } catch (e) { showToast('Network error', 'error'); }
            };

            const closeConfirm = () => {
                confirmModal.hidden = true;
            };

            confirmCancel.onclick = closeConfirm;
            confirmModal.onclick = (e) => {
                if (e.target === confirmModal) {
                    closeConfirm();
                }
            };
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const formData = serializeFullFormData();
            const response = await fetch('print_setup_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            });
            if ((await response.text()).trim() === '1') {
                showToast('Saved successfully');
                frame.contentWindow.location.reload();
            } else {
                showToast('Save error', 'error');
            }
        } catch (e) {
            showToast('Network error', 'error');
        }
    });
});
</script>
<?php include 'footer.php'; ?>
