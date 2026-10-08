// Prescription print setup editor managing margins, slot visibility, layout order, and preview iframe.
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

    // Preview element highlighting on input focus
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

    // Left history slot labels sync
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

    // History slot order and deduplication
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
        setPreviewStyle('.zrx-patient-strip', 'fontFamily', `"${textValue('pt_info_font', 'Tinos')}"`);
        setPreviewStyle('.zrx-left-content', 'marginLeft', `${numberValue('left_margin_left', 70)}px`);
        setPreviewStyle('.zrx-left-content', 'marginTop', `${numberValue('left_margin_top', 0)}px`);
        setPreviewStyle('.zrx-left-content', 'fontSize', `${numberValue('left_font_size', 11)}pt`);
        setPreviewStyle('.zrx-left-content', 'fontFamily', `"${textValue('left_font', 'Tinos')}"`);
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
        setPreviewStyle('.zrx-drug-brand', 'fontFamily', `"${textValue('right_font', 'Tinos')}"`);
        setPreviewStyle('.zrx-drug-dose, .zrx-drug-instruction, .zrx-drug-duration', 'fontSize', `${numberValue('bn_font_size', 10.5)}pt`);
        setPreviewStyle('.zrx-drug-dose, .zrx-drug-instruction, .zrx-drug-duration', 'fontFamily', `"${textValue('bn_font', 'SolaimanLipi')}"`);
        setPreviewStyle('.zrx-advice-table td', 'fontSize', `${numberValue('upd_font_size', 10.5)}pt`);
        setPreviewStyle('.zrx-advice-table td', 'fontFamily', `"${textValue('upd_font', 'SolaimanLipi')}"`);
        setPreviewStyle('.zrx-advice-table td', 'lineHeight', `${numberValue('upd_line_height', 14)}pt`);

        setPreviewStyle('.zrx-rx-mark', 'visibility', showRx ? 'visible' : 'hidden');
        setPreviewStyle('.zrx-rx-symbol', 'marginLeft', `${numberValue('rx_block_margin_left', 10)}px`);
        setPreviewStyle('.zrx-rx-symbol', 'marginTop', `${numberValue('rx_block_margin_top', 7)}px`);
        setPreviewStyle('.zrx-rx-symbol', 'fontSize', `${numberValue('rx_font_size', 18)}pt`);
        setPreviewStyle('.zrx-rx-symbol', 'fontFamily', `"${textValue('rx_font', 'Marck Script')}"`);

        // Visibility and display live updates
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
            setPreviewStyle('.zrx-drug-generic', 'fontFamily', '"Tinos", serif');
            setPreviewStyle('.zrx-drug-generic', 'fontSize', `${brandFontSize}pt`);
            setPreviewStyle('.zrx-drug-generic', 'fontStyle', 'normal');
            setPreviewStyle('.zrx-drug-generic', 'fontWeight', 'normal');
            setPreviewStyle('.zrx-drug-generic', 'marginLeft', `${genericMarginLeft}px`);
            setPreviewStyle('.zrx-drug-generic', 'marginTop', `${genericMarginTop}px`);
        } else {
            setPreviewStyle('.zrx-drug-generic', 'display', showGeneric ? (genericPosition === 'below' ? 'block' : 'inline-block') : 'none');
            setPreviewStyle('.zrx-drug-generic', 'fontFamily', `"${textValue('generic_font', 'Tinos')}"`);
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

        // Toggle label editor row visibility
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
                    const formData = new URLSearchParams();
                    formData.append('csrf_token', window.ZimRxCsrfToken || '');
                    const response = await fetch('api/reset_print_setup.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-CSRF-Token': window.ZimRxCsrfToken || ''
                        },
                        body: formData.toString()
                    });
                    const res = await response.json().catch(() => null);
                    if (res && res.ok) {
                        window.location.reload();
                    } else {
                        showToast(res && res.error ? res.error : 'Reset failed', 'error');
                    }
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
            const response = await fetch('api/print_setup_save.php', {
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
