<?php
$initialBrand = $initialDrugDetail['brand'] ?? null;
$initialClinical = $initialDrugDetail['clinical'] ?? [];
$initialVariants = $initialDrugDetail['variants'] ?? [];
$initialSidebarMode = $initialSidebarState['mode'] ?? 'brand';
$initialSidebarQuery = $initialSidebarState['query'] ?? '';
$initialSidebarResults = $initialSidebarState['results'] ?? [];
$initialActiveBrandId = (string)($initialBrand['id'] ?? '');
$initialSidebarTop = max(0, (int)($_GET['sidebar_top'] ?? 0));
$shouldRestoreSidebarScroll = $initialSidebarTop > 0 && in_array($initialSidebarMode, ['brand', 'generic', 'indication', 'class'], true);
$isInitialNewDrugMode = $initialSidebarMode === 'new';
$isInitialEditDrugMode = $initialSidebarMode === 'edit';
$isInitialDeleteDrugMode = $initialSidebarMode === 'delete';
$isInitialDocsMode = $initialSidebarMode === 'docs';
$initialSearchPlaceholder = 'Search ' . ucfirst($initialSidebarMode) . '...';
if ($initialSidebarMode === 'docs') $initialSearchPlaceholder = 'Search bookmarks...';
if ($initialSidebarMode === 'new') $initialSearchPlaceholder = 'Search new drugs...';

function drug_db_view_e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function drug_db_view_js_call(string $functionName, ...$args): string {
    $encoded = array_map(static function ($arg) {
        return json_encode((string)$arg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT);
    }, $args);
    return $functionName . '(' . implode(', ', $encoded) . ')';
}

function drug_db_view_truthy($value) {
    if ($value === true || $value === 1) return true;
    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'y'], true);
}

function drug_db_view_first_present(...$values) {
    foreach ($values as $value) {
        if ($value !== null && $value !== '' && trim((string)$value) !== '') {
            return $value;
        }
    }
    return '';
}

function drug_db_view_split_class_labels($value) {
    $parts = array_map(static function ($part) {
        return trim(preg_replace('/\s+/', ' ', (string)$part));
    }, explode(',', (string)$value));

    $seen = [];
    $labels = [];
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $key = strtolower($part);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $labels[] = $part;
    }

    return $labels;
}

function drug_db_view_render_class_links($value) {
    $labels = drug_db_view_split_class_labels($value);
    if (!$labels) {
        return 'N/A';
    }

    $html = '<div class="class-link-list">';
    foreach ($labels as $label) {
        $escapedLabel = drug_db_view_e($label);
        $jsLabel = str_replace("'", "\\'", $label);
        $html .= '<span class="class-link" onclick="openMoaTable(\'' . drug_db_view_e($jsLabel) . '\')">' . $escapedLabel . '</span>';
    }
    $html .= '</div>';

    return $html;
}

function drug_db_view_preg_style($category) {
    $palette = [
        'A' => '#34d399',
        'B' => '#34d399',
        'C' => '#fbbf24',
        'D' => '#fb923c',
        'X' => '#f87171',
    ];

    $key = strtoupper(trim((string)$category));
    $color = $palette[$key] ?? '#94a3b8';
    return 'color: ' . $color . '; text-decoration-color: ' . $color . ';';
}

function drug_db_view_header_icon_file($form, $name = '') {
    $formNorm = strtolower(trim(preg_replace('/\s+/', ' ', (string)$form)));
    $upperName = strtoupper(trim((string)$name));

    if (str_starts_with($upperName, 'TAB.')) return 'tablet.svg';
    if (str_starts_with($upperName, 'CAP.')) return 'capsule.svg';
    if (str_starts_with($upperName, 'SYP.') || str_starts_with($upperName, 'SUSP.')) return 'syrup.svg';
    if (str_starts_with($upperName, 'INJ.')) return 'injection.svg';
    if (str_starts_with($upperName, 'SUPP.')) return 'suppository.svg';
    if (str_starts_with($upperName, 'OINT.')) return 'ointment.svg';
    if (str_starts_with($upperName, 'CRM.')) return 'cream.svg';

    $matches = [
        'suppository' => 'suppository.svg',
        'capsule' => 'capsule.svg',
        'syrup' => 'syrup.svg',
        'suspension' => 'syrup.svg',
        'oral suspension' => 'syrup.svg',
        'oral solution' => 'syrup.svg',
        'paediatric drops' => 'dropper.svg',
        'pediatric drops' => 'dropper.svg',
        'drops' => 'drops.svg',
        'eye drops' => 'drops.svg',
        'nasal spray' => 'nasal-spray.svg',
        'inhaler' => 'inhaler.svg',
        'injection' => 'injection.svg',
        'infusion' => 'iv-infusion.svg',
        'cream' => 'cream.svg',
        'ointment' => 'ointment.svg',
        'gel' => 'ointment.svg',
        'spray' => 'spray.svg',
        'mouthwash' => 'mouthwash.svg',
        'solution' => 'solution.svg',
        'patch' => 'patch.svg',
        'tablet' => 'tablet.svg',
        'flash tablet' => 'tablet.svg',
        'dispersible tablet' => 'tablet.svg',
    ];

    foreach ($matches as $needle => $icon) {
        if ($formNorm !== '' && str_contains($formNorm, $needle)) {
            return $icon;
        }
    }

    return 'fallback.svg';
}

function drug_db_view_check_icon_svg() {
    return '<svg class="pill-inline-icon pill-inline-icon-check" viewBox="0 0 16 16" aria-hidden="true" width="14" height="14"><path d="M3 8.5 6.2 11.7 13 4.8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

function drug_db_view_external_icon_svg() {
    return '<svg class="pill-inline-icon pill-inline-icon-external" viewBox="0 0 16 16" aria-hidden="true" width="13" height="13"><path d="M9.5 2.5H13.5V6.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 9 13.2 2.8" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 9.5V12.2C13 12.9 12.4 13.5 11.7 13.5H3.8C3.1 13.5 2.5 12.9 2.5 12.2V4.3C2.5 3.6 3.1 3 3.8 3H6.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

function drug_db_view_pubmed_link_html($query) {
    $query = trim((string)$query);
    if ($query === '') {
        return '';
    }

    $href = 'https://pubmed.ncbi.nlm.nih.gov/?term=' . rawurlencode($query);
    return '<a class="header-pubmed-link" href="' . drug_db_view_e($href) . '" target="_blank" rel="noopener">Search PubMed ' . drug_db_view_external_icon_svg() . '</a>';
}

function drug_db_view_snapshot_badge($label, $active, $activeClass, $inactiveText = null) {
    $text = $active ? $label : ($inactiveText ?: ('Not ' . strtolower($label)));
    $class = $active ? $activeClass : 'muted';
    return '<span class="clinical-flag ' . drug_db_view_e($class) . '">' . drug_db_view_e($text) . '</span>';
}

function drug_db_view_parse_warning_items($value) {
    if (!is_string($value) || trim($value) === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function drug_db_view_warning_cards_html($value) {
    $warnings = drug_db_view_parse_warning_items((string)$value);
    if (!$warnings) {
        return '';
    }

    $html = '';
    foreach ($warnings as $warning) {
        $title = trim((string)($warning['alert_type'] ?? $warning['severity'] ?? 'Immediate warning'));
        $severity = trim((string)($warning['severity'] ?? 'Warning'));
        $trigger = trim((string)($warning['trigger_condition'] ?? $warning['trigger'] ?? ''));
        $message = trim((string)($warning['message'] ?? $warning['warning'] ?? ''));

        $html .= '<div class="clinical-warning-hero">';
        $html .= '<div class="clinical-warning-hero-head">';
        $html .= '<span class="clinical-warning-hero-title">' . drug_db_view_e($title) . '</span>';
        $html .= '<span class="clinical-warning-hero-severity">' . drug_db_view_e($severity) . '</span>';
        $html .= '</div>';
        if ($trigger !== '') {
            $html .= '<div class="clinical-warning-hero-trigger">' . drug_db_view_e($trigger) . '</div>';
        }
        if ($message !== '') {
            $html .= '<div class="clinical-warning-hero-body">' . drug_db_view_e($message) . '</div>';
        }
        $html .= '</div>';
    }

    return $html;
}

function drug_db_view_snapshot_html($brand, $clinical) {
    if (!$brand) return '';

    $immediateWarning = trim((string)drug_db_view_first_present($brand['immediate_warning'] ?? '', $clinical['immediate_warning'] ?? ''));
    $badges = [];
    if (drug_db_view_truthy(drug_db_view_first_present($brand['is_antibiotic'] ?? '', $clinical['is_antibiotic'] ?? ''))) $badges[] = drug_db_view_snapshot_badge('Antibiotic', true, 'info');
    if (drug_db_view_truthy(drug_db_view_first_present($brand['is_high_alert_medicine'] ?? '', $clinical['is_high_alert_medicine'] ?? ''))) $badges[] = drug_db_view_snapshot_badge('High alert medicine', true, 'danger');
    if (drug_db_view_truthy(drug_db_view_first_present($brand['require_renal_adjustment'] ?? '', $clinical['require_renal_adjustments'] ?? ''))) $badges[] = drug_db_view_snapshot_badge('Renal dose caution', true, 'warning');
    if (!drug_db_view_truthy(drug_db_view_first_present($brand['is_safe_in_pregnancy'] ?? '', $clinical['is_safe_in_pregnancy'] ?? ''))) $badges[] = drug_db_view_snapshot_badge('Pregnancy caution', true, 'warning');
    if (!drug_db_view_truthy(drug_db_view_first_present($brand['is_safe_in_lactation'] ?? '', $clinical['is_safe_in_lactation'] ?? ''))) $badges[] = drug_db_view_snapshot_badge('Lactation caution', true, 'warning');
    if (!drug_db_view_truthy(drug_db_view_first_present($brand['is_safe_in_hepatic_impairment'] ?? '', $clinical['is_safe_in_hepatic_impairment'] ?? ''))) $badges[] = drug_db_view_snapshot_badge('Hepatic caution', true, 'warning');
    if (!drug_db_view_truthy(drug_db_view_first_present($brand['is_safe_in_paediatrics'] ?? '', $clinical['is_safe_in_paediatric'] ?? ''))) $badges[] = drug_db_view_snapshot_badge('Paediatric caution', true, 'warning');
    if (drug_db_view_truthy(drug_db_view_first_present($brand['requires_tapering'] ?? '', $clinical['requires_tapering'] ?? ''))) $badges[] = drug_db_view_snapshot_badge('Tapering needed', true, 'warning');
    if ($immediateWarning !== '') $badges[] = drug_db_view_snapshot_badge('Immediate warning', true, 'danger');

    if (!$badges) {
        $badges[] = '<span class="clinical-flag safe">No major caution flags in summary</span>';
    }

    $meta = '';
    $usGeneric = drug_db_view_first_present($brand['us_generic_name'] ?? '', $clinical['us_generic_name'] ?? '');
    $atc = drug_db_view_first_present($brand['who_atc_class'] ?? '', $clinical['who_atc_class'] ?? '');
    if ($usGeneric !== '') $meta .= '<span class="clinical-meta-pill">US: ' . drug_db_view_e($usGeneric) . '</span>';
    if ($atc !== '') $meta .= '<span class="clinical-meta-pill">ATC: ' . drug_db_view_e($atc) . '</span>';

    return '<div class="clinical-flag-row">' . implode('', $badges) . $meta . '</div>'
        . drug_db_view_warning_cards_html($immediateWarning);
}

function drug_db_view_variant_pill($variant, $initialBrand) {
    $isExactMatch = ((string)($variant['id'] ?? '') === (string)($initialBrand['id'] ?? ''))
        || (($variant['strength'] ?? '') === ($initialBrand['strength'] ?? '') && ($variant['form_new'] ?? '') === ($initialBrand['form_new'] ?? ''));
    $classes = 'pill' . ($isExactMatch ? ' active' : '') . (!($variant['has_same_company'] ?? false) ? ' other-company' : '');
    $id = drug_db_view_e($variant['id'] ?? '');
    $html = '<div class="' . drug_db_view_e($classes) . '" onclick="loadBrand(\'' . $id . '\')">';
    if ($isExactMatch) $html .= drug_db_view_check_icon_svg();
    $html .= drug_db_view_e(($variant['strength'] ?? '') . ' | ' . ($variant['form_new'] ?? ''));
    if (!($variant['has_same_company'] ?? false) && !$isExactMatch) {
        $html .= ' ' . drug_db_view_external_icon_svg();
    }
    $html .= '</div>';
    return $html;
}

function drug_db_view_format_inline_markdown($value) {
    $html = drug_db_view_e((string)$value);
    $html = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*([^*]+)\*/u', '<em>$1</em>', $html);
    $html = preg_replace('/(^|>|\s)(Pregnancy Category\s*-\s*[A-DX]+)/iu', '$1<span class="clinical-inline-heading">$2</span>', $html);
    $html = preg_replace('/(^|>|\s)([^<:\n]+:)(?=\s|$)/u', '$1<span class="clinical-inline-heading">$2</span>', $html);
    return $html;
}

function drug_db_view_format_text_html($value) {
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }

    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $text);
    $blocks = [];
    $listItems = [];

    $flushList = static function () use (&$blocks, &$listItems) {
        if (!$listItems) {
            return;
        }
        $blocks[] = '<ul class="clinical-bullet-list"><li>' . implode('</li><li>', $listItems) . '</li></ul>';
        $listItems = [];
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            $flushList();
            continue;
        }

        if (preg_match('/^#{1,3}\s+(.+)$/u', $trimmed, $matches)) {
            $flushList();
            $blocks[] = '<h3 class="clinical-md-heading">' . drug_db_view_format_inline_markdown($matches[1]) . '</h3>';
            continue;
        }

        if (preg_match('/^-\s+(.+)$/u', $trimmed, $matches)) {
            $listItems[] = drug_db_view_format_inline_markdown($matches[1]);
            continue;
        }

        $flushList();
        $blocks[] = drug_db_view_format_inline_markdown($trimmed);
    }

    $flushList();

    return implode('<br>', $blocks);
}

function drug_db_view_json_table_html($value) {
    if (!is_string($value) || trim($value) === '') {
        return '';
    }

    $decoded = json_decode($value, true);
    if (!is_array($decoded) || !$decoded) {
        return '';
    }

    $rows = '';
    foreach ($decoded as $key => $cell) {
        $rows .= '<tr><td>' . drug_db_view_e(ucwords(str_replace('_', ' ', (string)$key))) . '</td><td>' . drug_db_view_e((string)$cell) . '</td></tr>';
    }

    return '<table class="alt-table"><tbody>' . $rows . '</tbody></table>';
}

function drug_db_view_json_flow_html($value) {
    if (!is_string($value) || trim($value) === '') {
        return '';
    }

    $decoded = json_decode($value, true);
    if (!is_array($decoded) || !$decoded) {
        return '';
    }

    $html = '<div class="clinical-flow-list">';
    $items = array_values($decoded);
    foreach ($items as $index => $item) {
        $html .= '<div class="clinical-flow-step">';
        $html .= '<div class="clinical-flow-step-card">';
        $html .= '<div class="clinical-flow-step-head"><span class="clinical-flow-badge">' . ($index + 1) . '</span></div>';
        $html .= '<div class="clinical-flow-step-text">' . drug_db_view_format_text_html((string)$item) . '</div>';
        $html .= '</div>';
        if ($index < count($items) - 1) {
            $html .= '<div class="clinical-flow-connector" aria-hidden="true"><span class="clinical-flow-connector-arrow"><svg viewBox="0 0 16 16" width="14" height="14"><path d="M8 3.5V12.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M4.8 9.5 8 12.7 11.2 9.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span></div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';

    return $html;
}

function drug_db_view_initial_accordions_html($brand, $clinical, $pregDesc = '') {
    if (!$brand) {
        return '';
    }

    $pregCat = trim((string)($brand['preg_cat'] ?? ''));
    $pregContent = '';
    if ($pregCat !== '') {
        $pregContent .= 'Pregnancy Category - ' . $pregCat . "\n";
    }
    if (trim((string)$pregDesc) !== '') {
        $pregContent .= trim((string)$pregDesc) . "\n\n";
    }
    $pregContent .= trim((string)($clinical['pregnancy_category_and_lactation_note'] ?? ''));

    $packsize = trim((string)($brand['packsize'] ?? ''));
    $price = trim((string)($brand['price'] ?? ''));
    $priceContent = '';
    if ($packsize !== '') {
        $priceContent .= 'Pack Size : ' . $packsize . "\n";
    }
    if ($price !== '') {
        $priceContent .= 'Unit Price : ' . $price;
    }

    $therapeuticClass = trim((string)($brand['cls'] ?? ''));
    $therapeuticContent = $therapeuticClass !== ''
        ? '<div style="display: flex; justify-content: space-between; align-items: center;"><span>' . drug_db_view_e($therapeuticClass) . '</span><i class="fas fa-eye" onclick="searchByClass(\'' . drug_db_view_e(str_replace("'", "\\'", $therapeuticClass)) . '\')" style="color: var(--accent-blue); cursor: pointer; padding: 5px;" title="Browse this class"></i></div>'
        : '';

    $sections = [
        ['title' => 'Indications', 'val' => drug_db_view_format_text_html($clinical['indication'] ?? '')],
        ['title' => 'Adult dose', 'val' => drug_db_view_format_text_html($clinical['adult_dose'] ?? '')],
        ['title' => 'Child dose', 'val' => drug_db_view_format_text_html($clinical['child_dose'] ?? '')],
        ['title' => 'Renal dose', 'val' => drug_db_view_format_text_html(($clinical['renal_dose'] ?? '') ?: 'No information is available from controlled clinical studies regarding the use of this drug in patients with advanced renal disease.')],
        ['title' => 'Administration', 'val' => drug_db_view_format_text_html($clinical['administration'] ?? '')],
        ['title' => 'Contraindications', 'val' => drug_db_view_format_text_html($clinical['contra_indication'] ?? '')],
        ['title' => 'Side effects', 'val' => drug_db_view_format_text_html($clinical['side_effect'] ?? '')],
        ['title' => 'Precautions & warnings', 'val' => drug_db_view_format_text_html($clinical['precaution'] ?? '')],
        ['title' => 'Pregnancy & Lactation', 'val' => drug_db_view_format_text_html($pregContent)],
        ['title' => 'Trimester safety', 'val' => drug_db_view_json_table_html($clinical['pregnancy_trimester_safety'] ?? '')],
        ['title' => 'Therapeutic Class', 'val' => $therapeuticContent],
        ['title' => 'Mode of Action', 'val' => drug_db_view_format_text_html($clinical['mode_of_action_summary'] ?? '')],
        ['title' => 'Mode of Action Flow', 'val' => drug_db_view_json_flow_html($clinical['mode_of_action_flow'] ?? '')],
        ['title' => 'Interaction', 'val' => drug_db_view_format_text_html($clinical['interaction'] ?? '')],
        ['title' => 'Overdose Effects', 'val' => drug_db_view_format_text_html($clinical['overdose_effect'] ?? '')],
        ['title' => 'Overdose Treatment', 'val' => drug_db_view_format_text_html($clinical['overdose_treatment'] ?? '')],
        ['title' => 'Storage', 'val' => drug_db_view_format_text_html($clinical['storage'] ?? '')],
        ['title' => 'Counselling Pearl', 'val' => drug_db_view_format_text_html($clinical['counselling_pearl'] ?? '')],
        ['title' => 'Pack size & Price', 'val' => drug_db_view_format_text_html($priceContent)],
    ];

    $html = '';
    foreach ($sections as $section) {
        if (trim((string)$section['val']) === '') {
            continue;
        }
        $html .= '<div class="acc-item">';
        $html .= '<div class="acc-header"><span class="acc-title">' . drug_db_view_e($section['title']) . '</span><i class="fas fa-chevron-down acc-icon"></i></div>';
        $html .= '<div class="acc-content html-content">' . $section['val'] . '</div>';
        $html .= '</div>';
    }

    return $html;
}

function drug_db_view_sidebar_row(array $item, string $mode, ?string $activeBrandId = null): string {
    if ($mode === 'brand') {
        $id = drug_db_view_e($item['id'] ?? '');
        $brand = drug_db_view_e($item['brand_name'] ?? '');
        $price = drug_db_view_e($item['price'] ?? '');
        $generic = drug_db_view_e($item['generic'] ?? '');
        $strength = drug_db_view_e($item['strength'] ?? '');
        $form = drug_db_view_e($item['form'] ?? '');
        $manufacturer = drug_db_view_e($item['manufacturer'] ?? '');
        $presName = strtoupper(trim((string)($item['pres_new_upper'] ?? '')));
        $iconFile = 'fallback.svg';
        if (str_starts_with($presName, 'TAB.')) $iconFile = 'tablet.svg';
        elseif (str_starts_with($presName, 'CAP.')) $iconFile = 'capsule.svg';
        elseif (str_starts_with($presName, 'SYP.') || str_starts_with($presName, 'SUSP.')) $iconFile = 'syrup.svg';
        elseif (str_starts_with($presName, 'INJ.') || str_starts_with($presName, 'INF.')) $iconFile = 'injection.svg';
        elseif (str_starts_with($presName, 'SUPP.')) $iconFile = 'suppository.svg';
        elseif (str_starts_with($presName, 'OINT.')) $iconFile = 'ointment.svg';
        elseif (str_starts_with($presName, 'CRM.')) $iconFile = 'cream.svg';
        $rowClass = 'res-row' . ((string)($item['id'] ?? '') === (string)$activeBrandId ? ' active' : '');
        return '
            <div class="' . drug_db_view_e($rowClass) . '" data-brand-id="' . $id . '" onclick="loadBrand(\'' . $id . '\')">
                <img src="assets/images/dosage-form-images/' . drug_db_view_e($iconFile) . '" class="res-icon" alt="">
                <div class="res-info">
                    <div class="res-line-1">
                        <span class="res-brand">' . $brand . '</span>
                        <span class="res-price">&#2547;' . $price . '</span>
                    </div>
                    <div class="res-line-2">' . $generic . '</div>
                    <div class="res-line-3">' . $strength . ' | ' . $form . '</div>
                    <div class="res-line-4">' . $manufacturer . '</div>
                </div>
            </div>
        ';
    }

    if ($mode === 'generic') {
        $name = (string)($item['generic'] ?? '');
        $id = (string)($item['generic_id'] ?? '');
        return '
            <div class="res-row" onclick="' . drug_db_view_e(drug_db_view_js_call('loadGenericBrands', $id, $name)) . '">
                <i class="fas fa-microscope" style="color:#64748b;"></i>
                <div class="res-info">
                    <span class="res-brand" style="color:var(--accent-blue);">' . drug_db_view_e($name) . '</span>
                    <span class="res-price">' . drug_db_view_e($item['brand_count'] ?? '') . ' Brands</span>
                </div>
            </div>
        ';
    }

    if ($mode === 'class') {
        $name = (string)($item['cls'] ?? '');
        return '
            <div class="res-row" onclick="' . drug_db_view_e(drug_db_view_js_call('loadClassDetail', $name)) . '">
                <i class="fas fa-layer-group" style="color:var(--accent-blue); font-size: 1.1rem;"></i>
                <div class="res-info">
                    <span class="res-brand" style="color:var(--navy-dark);">' . drug_db_view_e($name) . '</span>
                </div>
            </div>
        ';
    }

    if ($mode === 'indication') {
        $name = (string)($item['indication_name'] ?? '');
        $id = (string)($item['indication_id'] ?? '');
        return '
            <div class="res-row" onclick="' . drug_db_view_e(drug_db_view_js_call('loadIndicationDetail', $id, $name)) . '">
                <i class="fas fa-stethoscope" style="color:#64748b; font-size: 1.2rem;"></i>
                <div class="res-info">
                    <span class="res-brand" style="color:var(--navy-dark);">' . drug_db_view_e($name) . '</span>
                </div>
            </div>
        ';
    }

    return '';
}
?>
<div class="drug-db-page">
    <aside class="db-sidebar">
        <div class="db-tabs">
            <div class="db-tab<?= $initialSidebarMode === 'brand' ? ' active' : '' ?>" data-type="brand">BRAND</div>
            <div class="db-tab<?= $initialSidebarMode === 'generic' ? ' active' : '' ?>" data-type="generic">GENERIC</div>
            <div class="db-tab<?= $initialSidebarMode === 'indication' ? ' active' : '' ?>" data-type="indication">INDICATION</div>
            <div class="db-tab<?= $initialSidebarMode === 'class' ? ' active' : '' ?>" data-type="class">CLASS</div>
            <div class="db-tab<?= $isInitialNewDrugMode ? ' active' : '' ?>" id="newDrugTab">NEW DRUG</div>
        </div>
        <div class="db-actions">
            <div class="db-subtab<?= $isInitialEditDrugMode ? ' active' : '' ?>" id="editDrugTab">Edit Drug</div>
            <div class="db-subtab<?= $isInitialDeleteDrugMode ? ' active' : '' ?>" id="deleteDrugTab">Delete</div>
            <div class="db-subtab">Update</div>
            <div class="db-subtab compact<?= $isInitialDocsMode ? ' active' : '' ?>" id="docsBooksPapersTab">Docs, Books &amp; Papers</div>
        </div>
        <div class="new-drug-sidebar-panel" id="newDrugSidebarPanel"<?= $isInitialNewDrugMode ? ' style="display:flex;"' : '' ?>>
            <button type="button" class="new-drug-add-btn" id="addNewDrugBtn">
                <i class="fas fa-plus"></i>
                Add New Drug
            </button>
            <div class="new-drug-sidebar-title">New Drugs</div>
            <div class="new-drug-search-box">
                <input type="text" id="newDrugSearchInput" class="db-input" placeholder="Search new drugs...">
            </div>
            <div class="new-drug-results-list" id="newDrugResultsList"></div>
        </div>
        <div class="new-drug-sidebar-panel" id="editDrugSidebarPanel"<?= $isInitialEditDrugMode ? ' style="display:flex;"' : '' ?>>
            <button type="button" class="new-drug-add-btn" id="editDrugPickBtn">
                <i class="fas fa-pen"></i>
                Edit a drug
            </button>
            <div class="new-drug-sidebar-title">Edited Drug</div>
            <div class="new-drug-search-box">
                <input type="text" id="editDrugSearchInput" class="db-input" placeholder="Search edited drugs...">
            </div>
            <div class="new-drug-results-list" id="editDrugResultsList"></div>
        </div>
        <div class="new-drug-sidebar-panel" id="deleteDrugSidebarPanel"<?= $isInitialDeleteDrugMode ? ' style="display:flex;"' : '' ?>>
            <button type="button" class="new-drug-add-btn danger" id="deleteDrugPickBtn">
                <i class="fas fa-trash"></i>
                Delete a drug
            </button>
            <div class="new-drug-sidebar-title">Deleted Drug</div>
            <div class="new-drug-search-box">
                <input type="text" id="deleteDrugSearchInput" class="db-input" placeholder="Search deleted drugs...">
            </div>
            <div class="new-drug-results-list" id="deleteDrugResultsList"></div>
        </div>
        <div class="db-search-box"<?= ($isInitialNewDrugMode || $isInitialEditDrugMode || $isInitialDeleteDrugMode) ? ' style="display:none;"' : '' ?>>
            <input type="text" id="dbSearchInput" class="db-input" placeholder="<?= drug_db_view_e($initialSearchPlaceholder) ?>" value="<?= drug_db_view_e($initialSidebarQuery) ?>">
        </div>
        
        <div class="sidebar-nav" id="sidebarNav">
            <div class="btn-back-sidebar" onclick="goBackToSidebarResults()"><i class="fas fa-arrow-left"></i></div>
            <div class="sidebar-nav-main">
                <div class="sidebar-nav-title" id="sidebarNavTitle">Indication Name</div>
                <button class="btn-moa-table" id="sidebarMoaBtn" onclick="openMoaTable()">
                    <i class="fas fa-table"></i> MOA Table
                </button>
            </div>
        </div>

        <div id="resultsList" class="db-results-list"<?= ($isInitialNewDrugMode || $isInitialEditDrugMode || $isInitialDeleteDrugMode) ? ' style="display:none;"' : '' ?>>
            <?php foreach ($initialSidebarResults as $sidebarItem): ?>
                <?= drug_db_view_sidebar_row($sidebarItem, $initialSidebarMode, $initialActiveBrandId) ?>
            <?php endforeach; ?>
        </div>
        <?php if ($shouldRestoreSidebarScroll): ?>
            <script>
            (function() {
                var list = document.getElementById('resultsList');
                if (!list) return;
                var top = <?= json_encode($initialSidebarTop) ?>;
                var apply = function() { list.scrollTop = top; };
                apply();
                requestAnimationFrame(function() {
                    apply();
                    setTimeout(apply, 60);
                });
            })();
            </script>
        <?php endif; ?>
    </aside>

    <aside class="db-middle-column" id="middleColumn">
        <div class="mid-header">
            <div class="mid-title-row">
                <div class="mid-row-title-cont">
                    <h2 class="mid-title" id="midGenericName">Generic Brands</h2>
                    <span class="mid-count" id="midCountLabel"></span>
                </div>
                <button class="mid-view-btn" onclick="openGenericTable()"><i class="fas fa-table"></i> Table View</button>
            </div>
            <div class="mid-search-group">
                <input type="text" id="midSearchInput" class="mid-input" placeholder="Search company or brand...">
                <select id="midFormFilter" class="mid-select">
                    <option value="">All Dosage Forms</option>
                </select>
            </div>
        </div>
        <div id="midResultsList" class="mid-results">
            <!-- Middle results populated here -->
        </div>
    </aside>

    <main class="db-content">
        <section class="new-drug-workspace" id="newDrugWorkspace"<?= $isInitialNewDrugMode ? ' style="display:flex;"' : '' ?>>
            <div class="new-drug-workspace-empty" id="newDrugWorkspaceEmpty">
                <i class="fas fa-capsules"></i>
                <strong>Select a new drug or add one.</strong>
            </div>

            <form class="drug-editor-body new-drug-inline-form" id="newDrugInlineForm">
                <div class="new-drug-workspace-head">
                    <div>
                        <span class="new-drug-eyebrow">New Drugs</span>
                        <h2 id="newDrugFormTitle">Add New Drug</h2>
                    </div>
                    <button type="button" class="btn-light" id="cancelNewDrugFormBtn">Cancel</button>
                </div>

                <input type="hidden" name="source_type" value="custom">
                <input type="hidden" name="local_drug_id" id="newDrugLocalId">
                <input type="hidden" name="generic_id" id="newDrugGenericId">

                <label>
                    <span>Brand name</span>
                    <input type="text" name="brand_name" id="newDrugBrand" required>
                </label>
                <label>
                    <span>Generic name</span>
                    <input type="text" name="generic_name" id="newDrugGeneric" required>
                </label>
                <label>
                    <span>Strength</span>
                    <input type="text" name="strength" id="newDrugStrength" placeholder="500 mg">
                </label>
                <label>
                    <span>Form</span>
                    <input type="text" name="form" id="newDrugFormName" placeholder="Tablet">
                </label>
                <label>
                    <span>Manufacturer</span>
                    <input type="text" name="manufacturer_name" id="newDrugManufacturer" placeholder="Custom">
                </label>
                <label>
                    <span>Price</span>
                    <input type="text" name="price" id="newDrugPrice">
                </label>
                <label class="wide">
                    <span>Short prescription</span>
                    <input type="text" name="short_prescription" id="newDrugShort" placeholder="Auto generated from brand + strength">
                </label>
                <label class="wide">
                    <span>Long prescription</span>
                    <textarea name="long_prescription" id="newDrugLong" rows="4" placeholder="Auto generated from brand + strength + form"></textarea>
                </label>

                <div class="drug-editor-actions">
                    <button type="submit" class="btn-primary">Save Drug</button>
                </div>
            </form>

            <div class="new-drug-readonly" id="newDrugReadonly">
                <div class="new-drug-workspace-head">
                    <div>
                        <span class="new-drug-eyebrow">New Drug</span>
                        <h2 id="newDrugViewBrand"></h2>
                    </div>
                    <div class="new-drug-view-actions">
                        <button type="button" class="btn-light" id="editCustomDrugBtn">
                            <i class="fas fa-pen"></i> Edit
                        </button>
                        <button type="button" class="btn-danger" id="deleteCustomDrugBtn">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
                <dl class="new-drug-detail-grid">
                    <div><dt>Generic name</dt><dd id="newDrugViewGeneric"></dd></div>
                    <div><dt>Strength</dt><dd id="newDrugViewStrength"></dd></div>
                    <div><dt>Form</dt><dd id="newDrugViewForm"></dd></div>
                    <div><dt>Manufacturer</dt><dd id="newDrugViewManufacturer"></dd></div>
                    <div><dt>Price</dt><dd id="newDrugViewPrice"></dd></div>
                    <div class="wide"><dt>Short prescription</dt><dd id="newDrugViewShort"></dd></div>
                    <div class="wide"><dt>Long prescription</dt><dd id="newDrugViewLong"></dd></div>
                </dl>
            </div>
        </section>

        <section class="new-drug-workspace" id="editDrugWorkspace"<?= $isInitialEditDrugMode ? ' style="display:flex;"' : '' ?>>
            <div class="new-drug-workspace-empty" id="editDrugWorkspaceEmpty">
                <i class="fas fa-pen-to-square"></i>
                <strong>Select an edited drug or search a drug to edit.</strong>
            </div>

            <div class="new-drug-readonly edit-drug-picker" id="editDrugPicker">
                <div class="new-drug-workspace-head">
                    <div>
                        <span class="new-drug-eyebrow">Edit Drug</span>
                        <h2>Search Existing Drug</h2>
                    </div>
                    <button type="button" class="btn-light" id="cancelEditDrugPickerBtn">Cancel</button>
                </div>
                <div class="edit-drug-picker-search">
                    <input type="text" id="editDrugSystemSearchInput" class="db-input" placeholder="Search brand/generic/company...">
                </div>
                <div class="edit-drug-picker-results" id="editDrugSystemResults"></div>
            </div>

            <form class="drug-editor-body new-drug-inline-form" id="editDrugInlineForm">
                <div class="new-drug-workspace-head">
                    <div>
                        <span class="new-drug-eyebrow">Edited Drug</span>
                        <h2 id="editDrugFormTitle">Edit Drug</h2>
                    </div>
                    <button type="button" class="btn-light" id="cancelEditDrugFormBtn">Cancel</button>
                </div>

                <input type="hidden" name="source_type" value="override">
                <input type="hidden" name="system_brand_id" id="editDrugSystemBrandId">
                <input type="hidden" name="local_drug_id" id="editDrugLocalId">
                <input type="hidden" name="generic_id" id="editDrugGenericId">

                <label>
                    <span>Brand name</span>
                    <input type="text" name="brand_name" id="editDrugBrand" required>
                </label>
                <label>
                    <span>Generic name</span>
                    <input type="text" name="generic_name" id="editDrugGeneric" required>
                </label>
                <label>
                    <span>Strength</span>
                    <input type="text" name="strength" id="editDrugStrength" placeholder="500 mg">
                </label>
                <label>
                    <span>Form</span>
                    <input type="text" name="form" id="editDrugFormName" placeholder="Tablet">
                </label>
                <label>
                    <span>Manufacturer</span>
                    <input type="text" name="manufacturer_name" id="editDrugManufacturer" placeholder="Custom">
                </label>
                <label>
                    <span>Price</span>
                    <input type="text" name="price" id="editDrugPrice">
                </label>
                <label class="wide">
                    <span>Short prescription</span>
                    <input type="text" name="short_prescription" id="editDrugShort" placeholder="Auto generated from brand + strength">
                </label>
                <label class="wide">
                    <span>Long prescription</span>
                    <textarea name="long_prescription" id="editDrugLong" rows="4" placeholder="Auto generated from brand + strength + form"></textarea>
                </label>

                <div class="drug-editor-actions">
                    <button type="submit" class="btn-primary">Save Edit</button>
                </div>
            </form>

            <div class="new-drug-readonly" id="editDrugReadonly">
                <div class="new-drug-workspace-head">
                    <div>
                        <span class="new-drug-eyebrow">Edited Drug</span>
                        <h2 id="editDrugViewBrand"></h2>
                    </div>
                    <div class="new-drug-view-actions">
                        <button type="button" class="btn-light" id="editOverrideDrugBtn">
                            <i class="fas fa-pen"></i> Edit
                        </button>
                        <button type="button" class="btn-danger" id="deleteOverrideDrugBtn">
                            <i class="fas fa-trash"></i> Delete Edit
                        </button>
                    </div>
                </div>
                <dl class="new-drug-detail-grid">
                    <div><dt>Generic name</dt><dd id="editDrugViewGeneric"></dd></div>
                    <div><dt>Strength</dt><dd id="editDrugViewStrength"></dd></div>
                    <div><dt>Form</dt><dd id="editDrugViewForm"></dd></div>
                    <div><dt>Manufacturer</dt><dd id="editDrugViewManufacturer"></dd></div>
                    <div><dt>Price</dt><dd id="editDrugViewPrice"></dd></div>
                    <div class="wide"><dt>Short prescription</dt><dd id="editDrugViewShort"></dd></div>
                    <div class="wide"><dt>Long prescription</dt><dd id="editDrugViewLong"></dd></div>
                </dl>
            </div>
        </section>

        <section class="new-drug-workspace" id="deleteDrugWorkspace"<?= $isInitialDeleteDrugMode ? ' style="display:flex;"' : '' ?>>
            <div class="new-drug-workspace-empty" id="deleteDrugWorkspaceEmpty">
                <i class="fas fa-trash"></i>
                <strong>Select a deleted drug or search a drug to delete.</strong>
            </div>

            <div class="new-drug-readonly edit-drug-picker" id="deleteDrugPicker">
                <div class="new-drug-workspace-head">
                    <div>
                        <span class="new-drug-eyebrow">Delete Drug</span>
                        <h2>Search Existing Drug</h2>
                    </div>
                    <button type="button" class="btn-light" id="cancelDeleteDrugPickerBtn">Cancel</button>
                </div>
                <div class="edit-drug-picker-search">
                    <input type="text" id="deleteDrugSystemSearchInput" class="db-input" placeholder="Search brand/generic/company...">
                </div>
                <div class="edit-drug-picker-results" id="deleteDrugSystemResults"></div>
            </div>

            <div class="new-drug-readonly" id="deleteDrugReadonly">
                <div class="new-drug-workspace-head">
                    <div>
                        <span class="new-drug-eyebrow" id="deleteDrugViewLabel">Deleted Drug</span>
                        <h2 id="deleteDrugViewBrand"></h2>
                    </div>
                    <div class="new-drug-view-actions">
                        <button type="button" class="btn-light" id="restoreDeletedDrugBtn">
                            <i class="fas fa-undo"></i> Restore
                        </button>
                        <button type="button" class="btn-danger" id="confirmDeleteDrugBtn">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
                <dl class="new-drug-detail-grid">
                    <div><dt>Generic name</dt><dd id="deleteDrugViewGeneric"></dd></div>
                    <div><dt>Strength</dt><dd id="deleteDrugViewStrength"></dd></div>
                    <div><dt>Form</dt><dd id="deleteDrugViewForm"></dd></div>
                    <div><dt>Manufacturer</dt><dd id="deleteDrugViewManufacturer"></dd></div>
                    <div><dt>Price</dt><dd id="deleteDrugViewPrice"></dd></div>
                    <div class="wide"><dt>Status</dt><dd id="deleteDrugViewStatus"></dd></div>
                </dl>
            </div>
        </section>

        <div id="emptyState" style="<?= ($initialBrand || $isInitialNewDrugMode || $isInitialEditDrugMode || $isInitialDeleteDrugMode || $isInitialDocsMode) ? 'display: none;' : '' ?> text-align: center; padding: 150px 20px; color: #94a3b8;">
            <i class="fas fa-search-plus" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;"></i>
            <p>Search and select a medication to view clinical details.</p>
        </div>

        <div id="drugDetailArea" style="display: <?= ($initialBrand && !$isInitialNewDrugMode && !$isInitialEditDrugMode && !$isInitialDeleteDrugMode && !$isInitialDocsMode) ? 'flex' : 'none' ?>;">
            <!-- Top Navy Box -->
            <div class="navy-header" id="navyHeader">
                <div class="header-quick-note">For quick reference only. Please cross-verify the data against established medical references in case of any confusion.</div>

                <div class="header-top-grid">
                    <div class="header-main">
                        <div class="brand-title">
                            <span id="h_brand"><?= drug_db_view_e($initialBrand['brand_name'] ?? '') ?></span>
                            <span class="brand-strength" id="h_strength"><?= drug_db_view_e($initialBrand['strength'] ?? '') ?></span>
                            <div id="headerIcon" style="margin-left: 20px;"><?= $initialBrand ? '<img src="assets/images/dosage-form-images/' . drug_db_view_e(drug_db_view_header_icon_file($initialBrand['form'] ?? '', $initialBrand['pres_new_upper'] ?? '')) . '" style="width: 32px; height: 32px; filter: invert(1); opacity: 0.9;">' : '' ?></div>
                        </div>
                        <div class="brand-form" id="h_form"><?= drug_db_view_e($initialBrand['form'] ?? '') ?></div>
                        <div class="brand-generic-top" id="h_generic" style="color: #94a3b8;"><?= drug_db_view_e($initialBrand['generic'] ?? '') ?></div>
                        <div class="brand-manufacturer-top" id="h_manufacturer"><?= drug_db_view_e($initialBrand['manufacturer'] ?? '') ?></div>
                    </div>

                    <div class="header-meta" id="headerMeta">
                        <div class="meta-row" id="pregRow">
                            <span class="meta-label-small">Pregnancy Category:</span>
                            <span id="h_preg_letter" class="preg-clickable" onclick="togglePregPopup(event)" style="<?= drug_db_view_e(drug_db_view_preg_style($initialBrand['preg_cat'] ?? '')) ?>"><?= drug_db_view_e(($initialBrand['preg_cat'] ?? '') ?: 'Not Classified') ?></span>
                            <div class="preg-popup" id="pregPopup">
                                <div class="preg-popup-title"><i class="fas fa-info-circle"></i> Pregnancy Details</div>
                                <div class="preg-popup-body" id="pregPopupDesc"><?= drug_db_view_e($initialDrugDetail['preg_desc'] ?? '') ?></div>
                            </div>
                        </div>
                        <div class="meta-row">
                            <span class="meta-label-small">Class:</span>
                            <div class="meta-class-stack">
                                <div class="class-text" id="h_class_text"><?= drug_db_view_render_class_links($initialBrand['cls'] ?? '') ?></div>
                                <div id="h_pubmed_link"><?= drug_db_view_pubmed_link_html($initialClinical['pubmed_query_base'] ?? '') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="clinical-snapshot" id="clinicalSnapshot"><?= drug_db_view_snapshot_html($initialBrand, $initialClinical) ?></div>

                <div class="also-available">
                    <div id="sameCompanySection" style="margin-bottom: 20px;">
                        <span class="also-label">Also available in this company:</span>
                        <div class="pill-container" id="sameCompanyPills">
                            <?php foreach ($initialVariants as $variant): ?>
                                <?php if ($variant['has_same_company'] ?? false): ?>
                                    <?= drug_db_view_variant_pill($variant, $initialBrand) ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div id="otherCompanySection">
                        <span class="also-label">Also available in other company:</span>
                        <div class="pill-container" id="otherCompanyPills">
                            <?php foreach ($initialVariants as $variant): ?>
                                <?php if (!($variant['has_same_company'] ?? false)): ?>
                                    <?= drug_db_view_variant_pill($variant, $initialBrand) ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="bottom-row">
                    <div class="price-tag">Unit Price : <span class="price-val" id="h_price"><?= $initialBrand ? drug_db_view_e(($initialBrand['price'] ?? '') . ' BDT') : '' ?></span></div>
                    <button class="btn-other-brand" onclick="openOtherBrands()">Other Brand</button>
                </div>
            </div>

            <!-- Accordions -->
            <div class="accordion-container" id="clinicalAccordions"><?= drug_db_view_initial_accordions_html($initialBrand, $initialClinical, $initialDrugDetail['preg_desc'] ?? '') ?></div>
        </div>

        <div id="docsBooksPapersArea" class="docs-paper-area" style="display: <?= $isInitialDocsMode ? 'block' : 'none' ?>;">
            <section id="docs-section-books" class="docs-paper-section" data-docs-title="Books" data-docs-keywords="books textbook handbook pharmacology pediatric bnf">
                <div class="docs-paper-section-head">
                    <span>Books</span>
                    <small>Mock bookshelf</small>
                </div>
                <div class="docs-paper-grid">
                    <article><strong>BNF for Children</strong><span>Paediatric dose and safety reference.</span></article>
                    <article><strong>Goodman &amp; Gilman</strong><span>Pharmacology background and mechanisms.</span></article>
                    <article><strong>Nelson Textbook</strong><span>Paediatric clinical context.</span></article>
                </div>
            </section>

            <section id="docs-section-docs" class="docs-paper-section" data-docs-title="Docs" data-docs-keywords="docs guideline protocol local hospital who nice">
                <div class="docs-paper-section-head">
                    <span>Docs</span>
                    <small>Mock guidelines</small>
                </div>
                <div class="docs-paper-grid">
                    <article><strong>WHO Essential Medicines</strong><span>Core medicine list and policy notes.</span></article>
                    <article><strong>NICE Guideline Notes</strong><span>Treatment pathway bookmarks.</span></article>
                    <article><strong>Local Protocol</strong><span>Hospital formulary and dose policy placeholder.</span></article>
                </div>
            </section>

            <section id="docs-section-papers" class="docs-paper-section" data-docs-title="Papers" data-docs-keywords="papers trial review pubmed journal evidence">
                <div class="docs-paper-section-head">
                    <span>Papers</span>
                    <small>Mock evidence links</small>
                </div>
                <div class="docs-paper-grid">
                    <article><strong>Randomized Trials</strong><span>Key efficacy and safety papers.</span></article>
                    <article><strong>Meta-analysis</strong><span>Evidence summary placeholder.</span></article>
                    <article><strong>Case Reports</strong><span>Rare adverse event notes.</span></article>
                </div>
            </section>
        </div>
    </main>
</div>

<div class="modal-overlay" id="drugEditorModal" onclick="closeDrugEditor(event)">
    <div class="modal-content drug-editor-modal" onclick="event.stopPropagation()">
        <div class="modal-head">
            <div class="modal-title" id="drugEditorTitle">New Drug</div>
            <i class="fas fa-times" style="cursor: pointer; font-size: 1.2rem;" onclick="closeDrugEditor()"></i>
        </div>
        <form class="drug-editor-body" id="drugEditorForm">
            <input type="hidden" name="source_type" id="drugEditorSourceType" value="custom">
            <input type="hidden" name="system_brand_id" id="drugEditorSystemBrandId">
            <input type="hidden" name="local_drug_id" id="drugEditorLocalDrugId">
            <input type="hidden" name="generic_id" id="drugEditorGenericId">

            <label>
                <span>Brand name</span>
                <input type="text" name="brand_name" id="drugEditorBrand" required>
            </label>
            <label>
                <span>Generic name</span>
                <input type="text" name="generic_name" id="drugEditorGeneric" required>
            </label>
            <label>
                <span>Strength</span>
                <input type="text" name="strength" id="drugEditorStrength" placeholder="500 mg">
            </label>
            <label>
                <span>Form</span>
                <input type="text" name="form" id="drugEditorFormName" placeholder="Tablet">
            </label>
            <label>
                <span>Manufacturer</span>
                <input type="text" name="manufacturer_name" id="drugEditorManufacturer" placeholder="Custom">
            </label>
            <label>
                <span>Price</span>
                <input type="text" name="price" id="drugEditorPrice">
            </label>
            <label class="wide">
                <span>Short prescription</span>
                <input type="text" name="short_prescription" id="drugEditorShort" placeholder="Auto generated from brand + strength">
            </label>
            <label class="wide">
                <span>Long prescription</span>
                <textarea name="long_prescription" id="drugEditorLong" rows="4" placeholder="Auto generated from brand + strength + form"></textarea>
            </label>

            <div class="drug-editor-actions">
                <button type="button" class="btn-light" onclick="closeDrugEditor()">Cancel</button>
                <button type="submit" class="btn-primary">Save Drug</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="hiddenDrugModal" onclick="closeHiddenDrugModal(event)">
    <div class="modal-content drug-hidden-modal" onclick="event.stopPropagation()">
        <div class="modal-head">
            <div class="modal-title">Delete / Restore Drugs</div>
            <i class="fas fa-times" style="cursor: pointer; font-size: 1.2rem;" onclick="closeHiddenDrugModal()"></i>
        </div>
        <div class="drug-hidden-body">
            <div class="drug-hide-current">
                <div>
                    <strong id="hideCurrentTitle">No drug selected</strong>
                    <span>Deleted drugs are hidden from search, not removed from the database.</span>
                </div>
                <button type="button" id="hideCurrentDrugBtn" class="btn-danger">Hide Selected Drug</button>
            </div>
            <div class="hidden-drug-list" id="hiddenDrugList"></div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="otherBrandsModal" onclick="closeModal(event)">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-head">
            <div class="modal-title" id="modalGenericTitle">Generic Alternatives</div>
            <div class="modal-search">
                <input type="text" id="modalSearchInput" placeholder="Search brand or company name....">
                <select id="modalFormFilter" class="modal-select">
                    <option value="">All Forms</option>
                </select>
            </div>
            <i class="fas fa-times" style="cursor: pointer; font-size: 1.2rem;" onclick="closeModal()"></i>
        </div>
        <div class="modal-body">
            <table class="alt-table" id="altTable">
                <thead>
                    <tr>
                        <th onclick="sortAltTable(0)">Brand Name <i class="fas fa-sort"></i></th>
                        <th onclick="sortAltTable(1)">Strength <i class="fas fa-sort"></i></th>
                        <th onclick="sortAltTable(2)">Form <i class="fas fa-sort"></i></th>
                        <th onclick="sortAltTable(3)">Company Name <i class="fas fa-sort"></i></th>
                        <th onclick="sortAltTable(4)">Price <i class="fas fa-sort"></i></th>
                    </tr>
                </thead>
                <tbody id="altTableBody"></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="moaTableModal" onclick="closeMoaModal(event)">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-head">
            <div class="modal-title" id="moaTableTitle">MOA Table</div>
            <div class="modal-search">
                <input type="text" id="moaSearchInput" placeholder="Search generic, brands, or mode of action...">
            </div>
            <i class="fas fa-times" style="cursor: pointer; font-size: 1.2rem;" onclick="closeMoaModal()"></i>
        </div>
        <div class="modal-body">
            <table class="alt-table moa-table" id="moaTable">
                <thead>
                    <tr>
                        <th>Generic</th>
                        <th>Available Brands</th>
                        <th>Mode of Action</th>
                    </tr>
                </thead>
                <tbody id="moaTableBody"></tbody>
            </table>
        </div>
    </div>
</div>
