<?php
require_once 'auth.php';
require_login();
require_once 'db.php';
require_once 'print_setup_lib.php';

$page_title = 'ZimRx - Page Setup';
$extra_css = ['assets/css/page_setup.css'];
$doctorId = current_user_doctor_id();
$options = zimrx_bridge_load_print_options($pdo, $doctorId);

$header = zimrx_bridge_load_header_settings($pdo, $doctorId);
$headerPayload = zimrx_bridge_header_preview_payload($header);
$displayLogo = strtolower((string)($header['display_logo'] ?? (!empty($header['logo_path']) ? 'yes' : 'yes'))) === 'no' ? 'no' : 'yes';
$headerLogoUrl = trim((string)($header['logo_path'] ?? ''));
$leftHeaderLines = array_values($headerPayload['bn'] ?? []);
$rightHeaderLines = array_values($headerPayload['en'] ?? []);
$options['bgcolor'] = strtoupper(ltrim((string)($header['bg_color'] ?? 'FFFFFF'), '#'));
$footerHtml = zimrx_bridge_footer_html($header);

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

<div class="zps-page">
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
        If you are using a pre-printed pad, measure the pad with a scale in centimeters (cm) and enter the corresponding dimensions here. You can turn off header and patient info printing on the <a href="print_setup.php" style="color: var(--primary); text-decoration: underline; font-weight: 500;">Print Setup page</a>.
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
                                    <?= zimrx_bridge_visual_block_html($header, 'left', $leftHeaderLines) ?>
                                </div>

                                <?php if ($displayLogo === 'yes' && $headerLogoUrl !== ''): ?>
                                    <div class="zrx-header-logo">
                                        <img src="<?= preview_escape((string)$headerLogoUrl) ?>" alt="Header logo">
                                    </div>
                                <?php endif; ?>

                                <div class="zrx-header-text zrx-header-right">
                                    <?= zimrx_bridge_visual_block_html($header, 'right', $rightHeaderLines) ?>
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
                                <button type="button" id="btn-page-sizes-help" class="zps-help-circle-btn" title="Common Page Sizes" style="margin-left: 8px;">?</button>
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
    <div class="print-setup-toast-panel" role="dialog" aria-modal="true" style="width: min(100%, 360px); text-align: center; gap: 1.25rem;">
        <span class="print-setup-toast-icon" style="color: #b91c1c; border-color: #fecaca; background: #fef2f2;" aria-hidden="true">&#9888;</span>
        <strong style="font-size: 1.1rem; color: #1e293b;">Reset to Defaults?</strong>
        <p style="font-size: 0.875rem; color: #64748b; margin: 0;">Are you sure you want to restore the sizes to default settings? This cannot be undone.</p>
        <div style="display: flex; gap: 0.75rem; width: 100%; justify-content: center; margin-top: 0.5rem;">
            <button type="button" id="confirm-page-reset-cancel" class="btn btn-outline" style="flex: 1; padding: 0.5rem 1rem;">Cancel</button>
            <button type="button" id="confirm-page-reset-yes" class="btn btn-primary" style="flex: 1; background-color: #dc2626; border-color: #dc2626; padding: 0.5rem 1rem;">Yes, Reset</button>
        </div>
    </div>
</div>

<div id="page-sizes-modal" class="print-setup-toast" hidden>
    <div class="print-setup-toast-panel" style="width: min(100%, 400px); color: var(--text-dark); border-color: #cbd5e1; text-align: left; align-items: stretch;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; border-bottom: 1px solid #cbd5e1; padding-bottom: 0.5rem;">
            <strong style="font-size: 1.1rem; color: #0f172a;">Common Page Sizes</strong>
            <button type="button" id="page-sizes-close-x" style="background:none; border:none; font-size:1.4rem; cursor:pointer; color:#64748b; font-weight:700; padding:0; line-height:1;">&times;</button>
        </div>
        <p style="font-size: 0.85rem; color:#64748b; margin-bottom: 1rem; margin-top: 0;">Click a preset to apply it as your complete prescription dimensions:</p>
        <div style="display: flex; flex-direction: column; gap: 0.6rem;">
            <button type="button" class="btn btn-outline page-size-opt-btn" data-width="21.0" data-height="29.7" style="display:flex; justify-content:space-between; align-items:center; text-align:left; padding: 0.6rem 0.85rem; width: 100%;">
                <strong>A4 Size</strong>
                <span style="font-size:0.8rem; color:#64748b; font-weight:normal;">21.0 x 29.7 cm</span>
            </button>
            <button type="button" class="btn btn-outline page-size-opt-btn" data-width="14.8" data-height="21.0" style="display:flex; justify-content:space-between; align-items:center; text-align:left; padding: 0.6rem 0.85rem; width: 100%;">
                <strong>A5 Size</strong>
                <span style="font-size:0.8rem; color:#64748b; font-weight:normal;">14.8 x 21.0 cm</span>
            </button>
            <button type="button" class="btn btn-outline page-size-opt-btn" data-width="21.6" data-height="27.9" style="display:flex; justify-content:space-between; align-items:center; text-align:left; padding: 0.6rem 0.85rem; width: 100%;">
                <strong>Letter Size</strong>
                <span style="font-size:0.8rem; color:#64748b; font-weight:normal;">21.6 x 27.9 cm</span>
            </button>
            <button type="button" class="btn btn-outline page-size-opt-btn" data-width="21.6" data-height="35.6" style="display:flex; justify-content:space-between; align-items:center; text-align:left; padding: 0.6rem 0.85rem; width: 100%;">
                <strong>Legal Size</strong>
                <span style="font-size:0.8rem; color:#64748b; font-weight:normal;">21.6 x 35.6 cm</span>
            </button>
            <button type="button" class="btn btn-outline page-size-opt-btn" data-width="17.6" data-height="25.0" style="display:flex; justify-content:space-between; align-items:center; text-align:left; padding: 0.6rem 0.85rem; width: 100%;">
                <strong>B5 Size</strong>
                <span style="font-size:0.8rem; color:#64748b; font-weight:normal;">17.6 x 25.0 cm</span>
            </button>
        </div>
        <div style="display: flex; justify-content: flex-end; margin-top: 1.25rem; border-top: 1px solid #cbd5e1; padding-top: 0.75rem;">
            <button type="button" id="page-sizes-close-btn" class="btn btn-secondary">Close</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('page-setup-form');
    const resetButton = document.getElementById('zps-factory-reset');
    const previewScale = document.getElementById('zps-preview-scale');
    const previewPage = document.getElementById('prev_page_size');
    const pxPerCm = 37.7952755906;
    const scale = 0.62;

    const toast = document.getElementById('print-setup-toast');
    const toastMessage = document.getElementById('print-setup-toast-message');
    const toastClose = document.getElementById('print-setup-toast-close');

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

    const defaults = {
        header_height: '5.3',
        header_width: '21',
        pt_info_height: '1.6',
        pt_info_width: '21',
        pt_info_table_width: '90',
        left_height: '20',
        left_width: '9',
        right_height: '20',
        right_width: '11',
        footer_height: '2',
        footer_width: '21',
        page_height: '29.7',
        page_width: '21'
    };

    const controls = {
        header_height: ['.zps-preview-header', 'height', 'cm'],
        header_width: ['.zps-preview-header', 'width', 'cm'],
        pt_info_height: ['.zps-preview-patient', 'height', 'cm'],
        pt_info_width: ['.zps-preview-patient', 'width', 'cm'],
        pt_info_table_width: ['.zps-preview-patient table', 'width', '%'],
        left_height: ['.zps-preview-left', 'height', 'cm'],
        left_width: ['.zps-preview-left', 'width', 'cm'],
        right_height: ['.zps-preview-right', 'height', 'cm'],
        right_width: ['.zps-preview-right', 'width', 'cm'],
        footer_height: ['.zps-preview-footer', 'height', 'cm'],
        footer_width: ['.zps-preview-footer', 'width', 'cm'],
        page_height: ['.zps-preview-page', 'height', 'cm'],
        page_width: ['.zps-preview-page', 'width', 'cm']
    };

    const getInput = (id) => document.getElementById(id);
    const getNumber = (id, fallback) => {
        const input = getInput(id);
        const value = input ? parseFloat(input.value) : NaN;
        return Number.isFinite(value) ? value : fallback;
    };

    const clearActive = () => {
        document.querySelectorAll('.zps-preview-active').forEach(node => node.classList.remove('zps-preview-active'));
    };

    const setActive = (part) => {
        clearActive();
        document.querySelectorAll(`[data-preview-part="${part}"]`).forEach(node => node.classList.add('zps-preview-active'));
    };

    const applyPreview = () => {
        try {
            Object.entries(controls).forEach(([id, config]) => {
                const input = getInput(id);
                const value = input && input.value !== '' ? input.value : defaults[id];
                const unit = config[2] || 'cm';
                document.querySelectorAll(config[0]).forEach(node => {
                    node.style[config[1]] = `${value}${unit}`;
                });
            });
        } catch (e) {
            console.error('Error updating preview styles:', e);
        }

        try {
            const pageWidth = getNumber('page_width', 21);
            const pageHeight = getNumber('page_height', 29.7);

            // Update live dimensions display text
            const liveDim = document.getElementById('zps-live-dimensions');
            if (liveDim) {
                liveDim.textContent = `${pageWidth} x ${pageHeight} cm`;
            }

            if (previewScale) {
                previewScale.style.width = `${pageWidth * pxPerCm * scale}px`;
                previewScale.style.height = `${pageHeight * pxPerCm * scale}px`;
            }
            if (previewPage) {
                previewPage.style.transform = `scale(${scale})`;
                previewPage.style.transformOrigin = 'top left';
            }
        } catch (e) {
            console.error('Error scaling preview:', e);
        }
    };

    form.querySelectorAll('.zps-size-input').forEach(input => {
        input.addEventListener('focus', () => setActive(input.dataset.part || 'page'));
        input.addEventListener('input', () => {
            setActive(input.dataset.part || 'page');
            applyPreview();
        });
        input.addEventListener('change', applyPreview);
    });

    form.querySelectorAll('[data-part]').forEach(row => {
        row.addEventListener('mouseenter', () => setActive(row.dataset.part || 'page'));
    });

    resetButton.addEventListener('click', () => {
        const confirmModal = document.getElementById('page-setup-confirm-modal');
        const confirmYes = document.getElementById('confirm-page-reset-yes');
        const confirmCancel = document.getElementById('confirm-page-reset-cancel');

        if (confirmModal) {
            confirmModal.hidden = false;

            confirmYes.onclick = () => {
                confirmModal.hidden = true;
                Object.entries(defaults).forEach(([id, value]) => {
                    const input = getInput(id);
                    if (input) input.value = value;
                });
                setActive('page');
                applyPreview();
                showToast('Default sizes loaded. Save to apply.');
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

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            const response = await fetch('print_setup_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(new FormData(form))
            });
            const text = (await response.text()).trim();
            if (!response.ok || text !== '1') {
                throw new Error(text || 'Save failed');
            }
            showToast('Saved successfully');
        } catch (error) {
            showToast(error.message || 'Save failed', 'error');
        }
    });

    // --- Common Page Sizes Modal Logic ---
    const pageSizesModal = document.getElementById('page-sizes-modal');
    const pageSizesCloseX = document.getElementById('page-sizes-close-x');
    const pageSizesCloseBtn = document.getElementById('page-sizes-close-btn');
    const btnPageSizesHelp = document.getElementById('btn-page-sizes-help');

    if (btnPageSizesHelp) {
        btnPageSizesHelp.onclick = () => {
            if (pageSizesModal) pageSizesModal.hidden = false;
        };
    }

    const closePageSizesModal = () => {
        if (pageSizesModal) pageSizesModal.hidden = true;
    };

    if (pageSizesCloseX) pageSizesCloseX.onclick = closePageSizesModal;
    if (pageSizesCloseBtn) pageSizesCloseBtn.onclick = closePageSizesModal;

    pageSizesModal?.addEventListener('click', (e) => {
        if (e.target === pageSizesModal) {
            closePageSizesModal();
        }
    });

    document.querySelectorAll('.page-size-opt-btn').forEach(btn => {
        btn.onclick = () => {
            const w = btn.dataset.width;
            const h = btn.dataset.height;
            const widthInput = document.getElementById('page_width');
            const heightInput = document.getElementById('page_height');
            if (widthInput && heightInput) {
                widthInput.value = w;
                heightInput.value = h;
                // Trigger preview refresh
                widthInput.dispatchEvent(new Event('input', { bubbles: true }));
                widthInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            closePageSizesModal();
        };
    });

    applyPreview();
});
</script>
<?php include 'footer.php'; ?>
