<?php
declare(strict_types=1);

function zrx_trim_text(mixed $value): string {
    if (is_array($value)) {
        $parts = [];
        foreach ($value as $item) {
            $text = zrx_trim_text($item);
            if ($text !== '') {
                $parts[] = $text;
            }
        }
        return trim(implode(' ', $parts));
    }

    return trim(preg_replace('/\s+/u', ' ', (string)$value));
}

function zrx_drug_print_value(array $drug, array $keys, string $fallback = ''): string {
    foreach ($keys as $key) {
        $value = zrx_trim_text($drug[$key] ?? '');
        if ($value !== '') {
            return $value;
        }
    }

    return zrx_trim_text($fallback);
}

function zrx_drug_brand_for_print(array $drug, array $options): string {
    $isShort = (string)($options['suffix_prefix_usage'] ?? 'full') === 'short';
    $keys = $isShort
        ? ['pres_new_upper', 'prescribe_brand_short', 'full_form_brand_name', 'prescribe_brand_full', 'brand_name', 'brand']
        : ['full_form_brand_name', 'prescribe_brand_full', 'pres_new_upper', 'prescribe_brand_short', 'brand_name', 'brand'];

    return zrx_drug_print_value($drug, $keys, $drug['brand'] ?? '');
}

function zrx_drug_generic_for_print(array $drug, array $options): string {
    $format = (string)($options['print_generic_name_format'] ?? 'plain');
    $isShort = (string)($options['suffix_prefix_usage'] ?? 'full') === 'short';
    $plain = zrx_drug_print_value($drug, ['generic_name', 'generic'], $drug['generic'] ?? '');

    if ($format === 'prescribe') {
        $keys = $isShort
            ? ['prescribe_generic_short', 'prescribe_generic_full', 'generic_name', 'generic']
            : ['prescribe_generic_full', 'prescribe_generic_short', 'generic_name', 'generic'];
        return zrx_drug_print_value($drug, $keys, $plain);
    }

    if ($format === 'labelled') {
        $keys = $isShort
            ? ['labelled_generic_short', 'labelled_generic_full', 'generic_name', 'generic']
            : ['labelled_generic_full', 'labelled_generic_short', 'generic_name', 'generic'];
        return zrx_drug_print_value($drug, $keys, $plain);
    }

    return $plain;
}

function zrx_drug_number_label(int $number, array $options): string {
    return match ((string)($options['drug_no_style'] ?? 'period')) {
        'round_brackets' => '(' . $number . ')',
        'closing_bracket' => $number . ')',
        'square_brackets' => '[' . $number . ']',
        default => $number . '.',
    };
}

function zrx_drug_language_value(array $drug, string $field, array $options): string {
    $language = (string)($options[$field . '_language'] ?? 'bengali');
    $preferred = zrx_trim_text($drug[$field . '_' . $language] ?? '');
    return $preferred !== '' ? $preferred : zrx_trim_text($drug[$field] ?? '');
}

function zrx_render_drug_rows(array $drugs, array $options): string {
    $displayDrugNo = ($options['display_drug_no'] ?? 'yes') !== 'no';
    $showGeneric = ($options['disp_generic'] ?? 'yes') === 'yes';
    $drugBullet = zrx_trim_text($options['drug_bullet'] ?? '');
    if ($drugBullet === '' || $drugBullet === 'â€¢') {
        $drugBullet = '•';
    }

    $html = '';
    $counter = 1;
    $isFirstDrug = true;
    foreach ($drugs as $drug) {
        if (!is_array($drug)) {
            continue;
        }

        $brand = zrx_drug_brand_for_print($drug, $options);
        $generic = zrx_drug_generic_for_print($drug, $options);
        $drugRowFormat = (string)($options['drug_row_format'] ?? 'standard');

        $dose = zrx_drug_language_value($drug, 'dose', $options);
        $instruction = zrx_drug_language_value($drug + ['instruction' => $drug['food'] ?? ''], 'instruction', $options);
        $duration = zrx_drug_language_value($drug, 'duration', $options);

        if ($brand === '' && $generic === '' && $dose === '' && $instruction === '' && $duration === '') {
            continue;
        }

        $isContinuation = ($brand === '' && $generic === '');

        if (!$isContinuation) {
            if (!$isFirstDrug) {
                $html .= '<tr class="zrx-drug-gap-row"><td colspan="4" class="zrx-drug-gap">&nbsp;</td></tr>';
            }
            $isFirstDrug = false;
        }

        $drugName = $brand !== '' ? $brand : $generic;

        if (!$isContinuation) {
            if ($displayDrugNo) {
                $numberHtml = '<td class="zrx-drug-number">' . preview_escape(zrx_drug_number_label($counter, $options)) . '</td>';
            } else {
                $numberHtml = '<td class="zrx-drug-number zrx-drug-bullet">' . preview_escape($drugBullet) . '</td>';
            }
        } else {
            $numberHtml = '<td class="zrx-drug-number"></td>';
        }

        $isGenericMode = (($options['prescribing_mode'] ?? '') === 'generic') || !empty($drug['is_generic_mode']);
        $alreadyHasGeneric = ($generic !== '' && stripos($drugName, $generic) !== false);
        $canShowGenericSubtitle = $showGeneric && $generic !== '' && $generic !== $drugName && !$isGenericMode && !$alreadyHasGeneric;

        if ($drugRowFormat === 'labelled') {
            $brandFontSize = preview_escape((string)($options['right_font_size'] ?? '11')) . 'pt';
            $lblGeneric = (string)($options['lbl_generic'] ?? 'Generic Name:');
            if (trim($lblGeneric) === '') $lblGeneric = 'Generic Name:';
            $lblBrand = (string)($options['lbl_brand'] ?? 'Brand Name Recommendation:');
            if (trim($lblBrand) === '') $lblBrand = 'Brand Name Recommendation:';
            $lblInstruction = (string)($options['lbl_instruction'] ?? 'Instruction:');
            if (trim($lblInstruction) === '') $lblInstruction = 'Instruction:';

            $html .= '<tr class="zrx-drug-name-row">' . $numberHtml . '<td colspan="3" class="zrx-drug-name-cell">';
            if ($generic !== '') {
                $html .= '<div class="zrx-drug-subrow"><span class="zrx-lbl-generic">' . preview_escape($lblGeneric) . ' </span><span class="zrx-drug-generic" data-generic="' . preview_escape($generic) . '" style="font-size: ' . $brandFontSize . '; ' . ($showGeneric ? 'display: inline-block;' : 'display: none;') . '">' . preview_escape($generic) . '</span></div>';
            }
            if ($brand !== '' && !$isGenericMode && $brand !== $generic && !$alreadyHasGeneric) {
                $html .= '<div class="zrx-drug-subrow"><span class="zrx-lbl-brand">' . preview_escape($lblBrand) . ' </span><span class="zrx-drug-brand">' . preview_escape($brand) . '</span></div>';
            }
            if ($instruction !== '' || $dose !== '' || $duration !== '') {
                $visClass = $isContinuation ? ' zrx-vis-hidden' : '';
                $html .= '<div><span class="zrx-lbl-instruction' . $visClass . '">' . preview_escape($lblInstruction) . ' </span><span class="zrx-drug-dose">' . preview_escape($dose) . '</span><span class="zrx-drug-instruction">' . ($instruction !== '' ? '- ' . preview_escape($instruction) : '') . '</span><span class="zrx-drug-duration">' . ($duration !== '' ? '- ' . preview_escape($duration) : '') . '</span></div>';
            }
            $html .= '</td></tr>';
        } else {
            if (!$isContinuation) {
                $html .= '<tr class="zrx-drug-name-row">' . $numberHtml;
                $html .= '<td colspan="3" class="zrx-drug-name-cell"><span class="zrx-drug-brand">' . preview_escape($drugName) . '</span>';
                if ($canShowGenericSubtitle) {
                    $gWrapper = (string)($options['generic_wrapper'] ?? 'none');
                    $gFormatted = $generic;
                    if ($gWrapper === 'parentheses') $gFormatted = '(' . $generic . ')';
                    elseif ($gWrapper === 'brackets') $gFormatted = '[' . $generic . ']';
                    elseif ($gWrapper === 'hyphen') $gFormatted = '- ' . $generic;
                    $isBelow = (($options['generic_position'] ?? 'below') === 'below');
                    $html .= ' <span class="zrx-drug-generic" data-generic="' . preview_escape($generic) . '" style="display: ' . ($isBelow ? 'block' : 'inline-block') . ';">' . preview_escape($gFormatted) . '</span>';
                }
                $html .= '</td></tr>';
            }

            if ($dose !== '' || $instruction !== '' || $duration !== '') {
                $html .= '<tr class="zrx-drug-detail-row"><td class="zrx-drug-detail-pad"></td><td class="zrx-drug-dose">' . preview_escape($dose) . '</td><td class="zrx-drug-instruction">' . ($instruction !== '' ? '- ' . preview_escape($instruction) : '') . '</td><td class="zrx-drug-duration">' . ($duration !== '' ? '- ' . preview_escape($duration) : '') . '</td></tr>';
            }
        }

        if (!$isContinuation) {
            $counter++;
        }
    }

    return $html;
}

function zrx_render_advice_table(array $items, array $options): string {
    $rows = [];
    $language = (string)($options['advice_language'] ?? 'bengali');
    foreach ($items as $item) {
        if (is_array($item)) {
            $item = $item[$language] ?? ($item['value'] ?? '');
        }
        $item = zrx_trim_text($item);
        if ($item === '') {
            continue;
        }
        $rows[] = '<tr><td class="zrx-advice-bullet">▪</td><td>' . preview_escape($item) . '</td></tr>';
    }

    if (!$rows) {
        return '';
    }

    return '<table class="zrx-advice-table"><tbody id="preview-advice-rows"><tr><td colspan="2"><u><b>&#x0989;&#x09AA;&#x09A6;&#x09C7;&#x09B6;&#x0983;</b></u></td></tr>' . implode('', $rows) . '</tbody></table>';
}
