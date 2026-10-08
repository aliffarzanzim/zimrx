<?php
declare(strict_types=1);

// Page setup manager: physical sheet dimensions, header/sidebar/footer region heights and widths in centimeters.

require_once __DIR__ . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/print_setup_lib.php';

$page_title = 'ZimRx - Page Setup';
$extra_css = ['assets/css/pages/page_setup.css'];
$doctorId = current_user_doctor_id();
$options = zimrx_print_load_options($pdo, $doctorId);

$header = zimrx_print_load_header_settings($pdo, $doctorId);
$headerPayload = zimrx_print_header_preview_payload($header);
$displayLogo = strtolower((string)($header['display_logo'] ?? (!empty($header['logo_path']) ? 'yes' : 'yes'))) === 'no' ? 'no' : 'yes';
$headerLogoUrl = trim((string)($header['logo_path'] ?? ''));
$leftHeaderLines = array_values($headerPayload['bn'] ?? []);
$rightHeaderLines = array_values($headerPayload['en'] ?? []);
$options['bgcolor'] = strtoupper(ltrim((string)($header['bg_color'] ?? 'FFFFFF'), '#'));
$footerHtml = zimrx_print_footer_html($header);

if (trim((string)($options['header_width'] ?? '')) === '') {
    $options['header_width'] = (string)($options['page_width'] ?? '21');
}
if (trim((string)($options['pt_info_section_width'] ?? '')) === '') {
    $options['pt_info_section_width'] = (string)($options['page_width'] ?? '21');
}
if (trim((string)($options['footer_width'] ?? '')) === '') {
    $options['footer_width'] = (string)($options['page_width'] ?? '21');
}
if (trim((string)($options['right_width'] ?? '')) === '') {
    $options['right_width'] = (string)max(0, round((float)($options['page_width'] ?? 21) - (float)($options['left_width'] ?? 9), 1));
}

function zps_value(array $options, string $key, string $fallback): string {
    $value = trim((string)($options[$key] ?? ''));
    return preview_escape($value !== '' ? $value : $fallback);
}

$sections = [
    [
        'title' => 'Complete Prescription Size',
        'index' => '1',
        'part' => 'page',
        'fields' => [
            ['id' => 'page_height', 'name' => 'page_height', 'label' => 'Height', 'value' => zps_value($options, 'page_height', '29.7')],
            ['id' => 'page_width', 'name' => 'page_width', 'label' => 'Width', 'value' => zps_value($options, 'page_width', '21')],
        ],
    ],
    [
        'title' => 'Header Size',
        'index' => '2',
        'part' => 'header',
        'fields' => [
            ['id' => 'header_height', 'name' => 'header_height', 'label' => 'Height', 'value' => zps_value($options, 'header_height', '5.3')],
            ['id' => 'header_width', 'name' => 'header_width', 'label' => 'Width', 'value' => zps_value($options, 'header_width', '21')],
        ],
    ],
    [
        'title' => 'Patient Info',
        'index' => '3',
        'part' => 'patient',
        'fields' => [
            ['id' => 'pt_info_height', 'name' => 'pt_info_height', 'label' => 'Height', 'unit' => 'cm', 'step' => '0.1', 'min' => '0', 'value' => zps_value($options, 'pt_info_height', '1.6')],
            ['id' => 'pt_info_width', 'name' => 'pt_info_section_width', 'label' => 'Width', 'unit' => 'cm', 'step' => '0.1', 'min' => '0', 'value' => zps_value($options, 'pt_info_section_width', '21')],
            ['id' => 'pt_info_table_width', 'name' => 'pt_info_width', 'label' => 'Content Width', 'unit' => '%', 'step' => '1', 'min' => '10', 'max' => '100', 'value' => zps_value($options, 'pt_info_width', '90')],
        ],
    ],
    [
        'title' => 'History Part',
        'index' => '4',
        'part' => 'left',
        'fields' => [
            ['id' => 'left_height', 'name' => 'left_height', 'label' => 'Height', 'value' => zps_value($options, 'left_height', '20')],
            ['id' => 'left_width', 'name' => 'left_width', 'label' => 'Width', 'value' => zps_value($options, 'left_width', '9')],
        ],
    ],
    [
        'title' => 'Main Pad Prescription Part',
        'index' => '5',
        'part' => 'right',
        'fields' => [
            ['id' => 'right_height', 'name' => 'right_height', 'label' => 'Height', 'value' => zps_value($options, 'right_height', '20')],
            ['id' => 'right_width', 'name' => 'right_width', 'label' => 'Width', 'value' => zps_value($options, 'right_width', '11')],
        ],
    ],
    [
        'title' => 'Footer',
        'index' => '6',
        'part' => 'footer',
        'fields' => [
            ['id' => 'footer_height', 'name' => 'footer_height', 'label' => 'Height', 'value' => zps_value($options, 'footer_height', '2')],
            ['id' => 'footer_width', 'name' => 'footer_width', 'label' => 'Width', 'value' => zps_value($options, 'footer_width', '21')],
        ],
    ],
];

include 'header.php';
?>

<div class="zps-page zrx-page-container">
    <div class="zps-heading-card">
        <div class="zps-heading">
            <div>
                <h1>Page Setup</h1>
                <p>Set the printed pad size by measuring each prescription part in centimeters.</p>
            </div>
            <div class="zps-heading-actions">
                <a href="prescription_preview.php" target="_blank" class="btn btn-outline">Full Preview</a>
                <a href="print_setup.php" class="btn btn-outline">Print Setup</a>
                <a href="header_footer_background_setup.php" class="btn btn-outline">Header/Footer/BG Setup</a>
                <button type="button" id="zps-factory-reset" class="btn btn-outline print-setup-reset-btn">Reset to Defaults</button>
                <button type="submit" form="page-setup-form" class="btn btn-primary">Save Settings</button>
            </div>
        </div>
    </div>

    <div class="zps-note">
        If you are using a pre-printed pad, measure the pad with a scale in centimeters (cm) and enter the corresponding dimensions here. You can turn off header and patient info printing on the <a href="print_setup.php" class="zps-link-action">Print Setup page</a>.
    </div>

    <form id="page-setup-form" class="zps-grid" method="post">
        <div class="zps-preview-panel">
            <div class="zps-panel-title">
                <h2>Preview</h2>
                <span id="zps-live-dimensions"><?= zps_value($options, 'page_width', '21') ?> x <?= zps_value($options, 'page_height', '29.7') ?> cm</span>
            </div>
            <div class="zps-preview-stage">
                <div id="zps-preview-scale" class="zps-preview-scale">
                    <div id="prev_page_size" class="zps-preview-page" data-preview-part="page">
                        <div id="pageHeader" class="zps-preview-header" data-preview-part="header" style="background: #<?= preview_escape($options['bgcolor']) ?>; border-bottom: <?= ($options['dec_line_top_1'] ?? 'yes') === 'yes' ? '1px solid #000' : 'none' ?>;">
                            <div class="zrx-header-layout <?= ($displayLogo === 'yes' && $headerLogoUrl !== '') ? 'zrx-has-logo' : 'zrx-no-logo' ?>">
                                <div class="zrx-header-text zrx-header-left">
                                    <?= zimrx_print_visual_block_html($header, 'left', $leftHeaderLines) ?>
                                </div>

                                <?php if ($displayLogo === 'yes' && $headerLogoUrl !== ''): ?>
                                    <div class="zrx-header-logo">
                                        <img src="<?= preview_escape((string)$headerLogoUrl) ?>" alt="Header logo">
                                    </div>
                                <?php endif; ?>

                                <div class="zrx-header-text zrx-header-right">
                                    <?= zimrx_print_visual_block_html($header, 'right', $rightHeaderLines) ?>
                                </div>
                            </div>
                        </div>

                        <div class="zps-preview-patient" data-preview-part="patient">
                            <table>
                                <tbody>
                                    <tr>
                                        <td><b>Name</b></td><td>:</td><td class="pos_1">Aminul Islam</td>
                                        <td><b>Age</b></td><td>:</td><td class="pos_2">36Y</td>
                                        <td><b>Sex</b></td><td>:</td><td class="pos_3">M</td>
                                        <td><b>Date</b></td><td>:</td><td class="pos_4"><?= date('d/m/Y') ?></td>
                                    </tr>
                                    <tr>
                                        <td><b>Address</b></td><td>:</td><td class="pos_5">Rangpur</td>
                                        <td><b>Reg No</b></td><td>:</td><td class="pos_6">43</td>
                                        <td><b>Wt</b></td><td>:</td><td class="pos_7">70</td>
                                        <td><b>Mobile</b></td><td>:</td><td class="pos_8">01617101010</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="zps-preview-left" data-preview-part="left">
                            <div class="zps-sidebar-lines">
                                <b>P/C</b>
                                <span>Chest pain</span>
                                <b>P/E</b>
                                <span>BP, Pulse, Temp</span>
                                <b>Investigations</b>
                                <span>CBC, RBS</span>
                            </div>
                        </div>

                        <div class="zps-preview-right" data-preview-part="right">
                            <div class="zps-rx">Rx.</div>
                            <div class="zps-rx-lines">
                                <b>1. TAB. SAMPLE 500 mg</b>
                                <span>1+0+1 - after meal - 5 days</span>
                                <b>2. CAP. SAMPLE 20 mg</b>
                                <span>1+0+0 - before meal - 1 month</span>
                            </div>
                        </div>

                        <div class="zps-preview-footer" data-preview-part="footer" style="border-top: <?= ($options['dec_line_bottom'] ?? 'yes') === 'yes' ? '1px solid #000' : 'none' ?>;">
                            <?= $footerHtml !== '' ? $footerHtml : 'Chamber address, serial/contact note and visiting time.' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="zps-controls-panel">
            <div class="zps-panel-title">
                <h2>Page Dimensions</h2>
                <span>All sizes are in cm</span>
            </div>

            <table class="zps-settings-table">
                <tbody>
                <?php foreach ($sections as $section): ?>
                    <tr class="zps-section-row" data-part="<?= preview_escape($section['part']) ?>">
                        <td colspan="4">
                            <b><?= preview_escape($section['title']) ?> : (<?= preview_escape($section['index']) ?>)</b>
                            <?php if ($section['part'] === 'page'): ?>
                                <button type="button" id="btn-page-sizes-help" class="zps-help-circle-btn" title="Common Page Sizes" class="zps-help-circle-btn zps-help-btn-spacing">?</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php foreach ($section['fields'] as $field): ?>
                        <?php $isPercent = ($field['unit'] ?? 'cm') === '%'; ?>
                        <tr class="zps-input-row" data-part="<?= preview_escape($section['part']) ?>">
                            <td><?= preview_escape($field['label']) ?> <?= $isPercent ? 'in percentage (%)' : 'in centimeter' ?></td>
                            <td>:</td>
                            <td>
                                <input
                                    type="number"
                                    step="<?= preview_escape((string)($field['step'] ?? '0.1')) ?>"
                                    min="<?= preview_escape((string)($field['min'] ?? '0')) ?>"
                                    <?= isset($field['max']) ? 'max="' . preview_escape((string)$field['max']) . '"' : '' ?>
                                    class="zps-size-input"
                                    autocomplete="off"
                                    id="<?= preview_escape($field['id']) ?>"
                                    name="<?= preview_escape($field['name']) ?>"
                                    value="<?= preview_escape($field['value']) ?>"
                                    data-part="<?= preview_escape($section['part']) ?>"
                                >
                            </td>
                            <td><?= $isPercent ? '%' : 'cm' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr><td colspan="4" class="zps-row-gap"></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>


<div id="print-setup-toast" class="print-setup-toast" hidden>
    <div class="print-setup-toast-panel" role="dialog" aria-modal="true" aria-labelledby="print-setup-toast-message">
        <span class="print-setup-toast-icon" aria-hidden="true">&#10003;</span>
        <strong id="print-setup-toast-message">Saved successfully</strong>
        <button type="button" id="print-setup-toast-close" class="btn btn-primary">Okay</button>
    </div>
</div>

<div id="page-setup-confirm-modal" class="print-setup-toast" hidden>
    <div class="print-setup-toast-panel zps-confirm-panel" role="dialog" aria-modal="true">
        <span class="print-setup-toast-icon zps-confirm-icon" aria-hidden="true">&#9888;</span>
        <strong class="zps-confirm-title">Reset to Defaults?</strong>
        <p class="zps-confirm-text">Are you sure you want to restore the sizes to default settings? This cannot be undone.</p>
        <div class="zps-confirm-actions">
            <button type="button" id="confirm-page-reset-cancel" class="btn btn-outline zps-confirm-btn">Cancel</button>
            <button type="button" id="confirm-page-reset-yes" class="btn btn-primary zps-confirm-btn-danger">Yes, Reset</button>
        </div>
    </div>
</div>

<div id="page-sizes-modal" class="print-setup-toast" hidden>
    <div class="print-setup-toast-panel zps-preset-modal-panel">
        <div class="zps-preset-modal-header">
            <strong class="zps-preset-modal-title">Common Page Sizes</strong>
            <button type="button" id="page-sizes-close-x" class="zps-preset-modal-close">&times;</button>
        </div>
        <p class="zps-preset-modal-desc">Click a preset to apply it as your complete prescription dimensions:</p>
        <div class="zps-preset-list">
            <button type="button" class="btn btn-outline page-size-opt-btn zps-preset-btn" data-width="21.0" data-height="29.7">
                <strong>A4 Size</strong>
                <span class="zps-preset-dims">21.0 x 29.7 cm</span>
            </button>
            <button type="button" class="btn btn-outline page-size-opt-btn zps-preset-btn" data-width="14.8" data-height="21.0">
                <strong>A5 Size</strong>
                <span class="zps-preset-dims">14.8 x 21.0 cm</span>
            </button>
            <button type="button" class="btn btn-outline page-size-opt-btn zps-preset-btn" data-width="21.6" data-height="27.9">
                <strong>Letter Size</strong>
                <span class="zps-preset-dims">21.6 x 27.9 cm</span>
            </button>
            <button type="button" class="btn btn-outline page-size-opt-btn zps-preset-btn" data-width="21.6" data-height="35.6">
                <strong>Legal Size</strong>
                <span class="zps-preset-dims">21.6 x 35.6 cm</span>
            </button>
            <button type="button" class="btn btn-outline page-size-opt-btn zps-preset-btn" data-width="17.6" data-height="25.0">
                <strong>B5 Size</strong>
                <span class="zps-preset-dims">17.6 x 25.0 cm</span>
            </button>
        </div>
        <div class="zps-preset-modal-footer">
            <button type="button" id="page-sizes-close-btn" class="btn btn-secondary">Close</button>
        </div>
    </div>
</div>

<script src="assets/js/pages/page_setup.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/page_setup.js') ?>"></script>
<?php include 'footer.php'; ?>
