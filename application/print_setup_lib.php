<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

function zimrx_bridge_normalize_header_line(string $value): string {
    return trim(preg_replace('/\s+/', ' ', $value));
}

function preview_escape(?string $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function zimrx_bridge_default_print_options(): array {
    return [
        'page_width' => '21',
        'page_height' => '29.7',
        'header_height' => '5.3',
        'header_width' => '21',
        'pt_info_height' => '1.6',
        'pt_info_section_width' => '21',
        'pt_info_width' => '90',
        'left_height' => '20.4',
        'left_width' => '9.0',
        'right_height' => '20.4',
        'right_width' => '12.0',
        'footer_height' => '2.0',
        'footer_width' => '21',
        'pt_info_font' => 'Times New Roman',
        'pt_info_font_size' => '12',
        'pt_info_margin_top' => '5',
        'pt_info_margin_bottom' => '5',
        'left_font' => 'Times New Roman',
        'left_font_size' => '11',
        'left_margin_left' => '70',
        'left_margin_top' => '0',
        'left_line_height' => '10',
        'right_font' => 'Times New Roman',
        'right_font_size' => '11',
        'right_margin_top' => '0',
        'pres_main_height' => '18.8',
        'pres_main_width' => '',
        'pres_main_left_margin' => '40',
        'pres_main_margin_top' => '10',
        'pres_line_height' => '11',
        'pres_gap_height' => '5',
        'rx_font' => 'Lucida Calligraphy',
        'rx_font_size' => '18',
        'rx_block_margin_left' => '10',
        'rx_block_margin_top' => '7',
        'bn_font' => 'SolaimanLipi',
        'bn_font_size' => '10.5',
        'dose_lt_padding' => '0',
        'upd_font' => 'SolaimanLipi',
        'upd_font_size' => '10.5',
        'upd_line_height' => '14',
        'display_header' => 'yes',
        'display_pt_info' => 'yes',
        'header_type' => 'text',
        'preview_header_type' => 'with_header',
        'display_footer' => 'yes',
        'display_barcode' => 'yes',
        'barcode_position' => 'no',
        'visit_number' => 'yes',
        'disp_signature' => 'no',
        'disp_generic' => 'yes',
        'generic_position' => 'below',
        'generic_wrapper' => 'parentheses',
        'generic_font' => 'Times New Roman',
        'generic_font_size' => '10',
        'generic_font_style' => 'italic',
        'generic_margin_left' => '0',
        'generic_margin_top' => '0',
        'disp_rx' => 'yes',
        'drug_row_format' => 'standard',
        'lbl_generic' => 'Generic Name:',
        'lbl_brand' => 'Brand Name Recommendation:',
        'lbl_instruction' => 'Instruction:',
        'lbl_history_medical' => 'Medical History:',
        'lbl_history_treatments' => 'Treatment History:',
        'lbl_history_habits' => 'Habits:',
        'lbl_history_diet' => 'Diet:',
        'lbl_history_hypersensitivity' => 'Hypersensitivity:',
        'lbl_history_drug' => 'Drug History:',
        'print_history_pos_1' => 'medical',
        'print_history_pos_2' => 'treatments',
        'print_history_pos_3' => 'habits',
        'print_history_pos_4' => 'diet',
        'print_history_pos_5' => 'hypersensitivity',
        'print_history_pos_6' => 'drug_history',
        'print_generic_name_format' => 'plain',
        'suffix_prefix_usage' => 'full',
        'dose_language' => 'bengali',
        'duration_language' => 'bengali',
        'instruction_language' => 'bengali',
        'advice_language' => 'bengali',
        'revisit_position' => 'bottom',
        'print_delay' => '2000',
        'display_drug_no' => 'yes',
        'dr_n_gap' => '5',
        'ext_2' => 'per_line',
        'ext_3' => 'left',
        'dec_line_top_1' => 'yes',
        'dec_line_top_2' => 'yes',
        'dec_line_left' => 'yes',
        'dec_line_bottom' => 'yes',
        'info_row' => '2',
        'bullet_text' => '○',
        'drug_bullet' => '•',
        'drug_no_style' => 'period',
        'pc_format' => 'for',
        'dx_format' => 'per_line',
        'dx_bullet' => 'Δ',
        'stamp_path' => '',
        'stamp_opacity' => '1.0',
        'stamp_scale' => '1.0',
        'stamp_angle' => '0',
        'stamp_offset_x' => '0',
        'stamp_offset_y' => '0',
        'stamp_color' => '#000000',
        'stamp_color_enable' => 'no',
        'print_pos_1' => 'pc',
        'print_pos_2' => 'ho',
        'print_pos_3' => 'pe',
        'print_pos_4' => 'reports',
        'print_pos_5' => 'none',
        'print_pos_6' => 'none',
        'print_pos_7' => 'plan',
        'print_pos_8' => 'otnote',
        'print_pos_9' => 'oh',
        'print_pos_10' => 'mh',
        'print_pos_11' => 'advice',
        'print_pos_12' => 'note',
        'print_pos_13' => 'dx',
        'print_pos_14' => 'none',
        'pc_name' => 'Presenting Complaints',
        'history_name' => 'History',
        'oe_name' => 'Physical Examination',
        'dx_name' => 'Dx',
        'ix_name' => 'Investigations',
        'dh_name' => 'D/H',
        'plan_name' => 'Plan',
        'note_name' => 'Note',
        'oh_name' => 'O/H',
        'mh_name' => 'M/H',
        'report_name' => 'Reports',
        'edd_name' => 'OT Note',
        'ttl_1' => '52',
        'ttl_2' => '40',
        'ttl_3' => '37',
        'ttl_4' => '55',
        'ttl_5' => '70',
        'ttl_6' => '67',
        'ttl_7' => '35',
        'ttl_8' => '62',
        'display_name' => 'yes',
        'display_name_t' => 'yes',
        'display_age' => 'yes',
        'display_age_t' => 'yes',
        'display_sex' => 'yes',
        'display_sex_t' => 'yes',
        'display_address' => 'yes',
        'display_address_t' => 'yes',
        'display_mobile' => 'yes',
        'display_mobile_t' => 'yes',
        'display_weight' => 'yes',
        'display_weight_t' => 'yes',
        'display_reg_no' => 'yes',
        'display_reg_no_t' => 'yes',
        'display_date' => 'yes',
        'display_date_t' => 'yes',
        'patient_label_name' => 'Name',
        'patient_label_age' => 'Age',
        'patient_label_sex' => 'Sex',
        'patient_label_address' => 'Address',
        'patient_label_mobile' => 'Mobile',
        'patient_label_weight' => 'Wt',
        'patient_label_reg_no' => 'Reg No.',
        'patient_label_date' => 'Date',
        'header_left_width' => '40',
        'header_logo_width' => '18',
        'header_right_width' => '40',
        'has_onboarded' => '0',
    ];
}

function zimrx_bridge_legacy_right_lines(array $header): array {
    $addressLines = preg_split('/\r\n|\r|\n/', trim((string)($header['chamber_address'] ?? '')));
    $addressLines = array_values(array_filter(array_map('zimrx_bridge_normalize_header_line', $addressLines), static fn($line) => $line !== ''));

    $bmdc = zimrx_bridge_normalize_header_line((string)($header['bmdc_no'] ?? ''));
    if ($bmdc !== '' && stripos($bmdc, 'bmdc') === false) {
        $bmdc = 'BMDC Reg No ' . $bmdc;
    }

    $phone = zimrx_bridge_normalize_header_line((string)($header['chamber_phone'] ?? ''));
    if ($phone !== '' && stripos($phone, 'mobile') === false && stripos($phone, 'phone') === false) {
        $phone = 'Mobile: ' . $phone;
    }

    $noteLines = preg_split('/\r\n|\r|\n/', trim((string)($header['header_note'] ?? '')));
    $noteLines = array_values(array_filter(array_map('zimrx_bridge_normalize_header_line', $noteLines), static fn($line) => $line !== ''));

    return [
        1 => zimrx_bridge_normalize_header_line((string)($header['doctor_name'] ?? current_user_name())),
        2 => zimrx_bridge_normalize_header_line((string)($header['qualifications'] ?? '')),
        3 => zimrx_bridge_normalize_header_line((string)($header['specialty'] ?? '')),
        4 => $addressLines[0] ?? zimrx_bridge_normalize_header_line((string)($header['chamber_name'] ?? '')),
        5 => $addressLines[1] ?? '',
        6 => $addressLines[2] ?? '',
        7 => $bmdc,
        8 => $phone,
        9 => $noteLines[0] ?? '',
        10 => $noteLines[1] ?? '',
    ];
}

function zimrx_bridge_default_left_lines(): array {
    return [
        1 => 'ডা. শাফায়েত মাহমুদ',
        2 => 'এমবিবিএস, এমডি (কার্ডিওলজি), এফসিপিএস (মেডিসিন), বিসিএস(স্বাস্থ্য)',
        3 => 'চিফ কনসালটেন্ট ও বিভাগীয় প্রধান (কার্ডিওলজি)',
        4 => 'এপেক্স কার্ডিয়াক ইনস্টিটিউট',
        5 => 'হৃদরোগ, উচ্চ রক্তচাপ ও মেডিসিন বিশেষজ্ঞ',
        6 => 'বিএমডিসি রেজি নং: A-112233',
        7 => 'মোবাইলঃ ০১৭১০-XXXXXX',
        8 => '',
        9 => '',
        10 => '',
    ];
}

function zimrx_bridge_default_right_lines(): array {
    return [
        1 => 'Dr. Shafayet Mahmud',
        2 => 'MBBS, MD (Cardiology), FCPS (Medicine), BCS (Health)',
        3 => 'Chief Consultant & HOD (Cardiology)',
        4 => 'Apex Cardiac Institute',
        5 => 'Cardiology, Hypertension & Medicine Specialist',
        6 => 'BMDC Reg. No: A-112233',
        7 => 'Mobile: 01710-XXXXXX',
        8 => '',
        9 => '',
        10 => '',
    ];
}

function zimrx_bridge_header_lines(array $header, string $side): array {
    $lines = [];

    // Check if the user has explicitly saved this side of the header.
    $isCustomized = array_key_exists($side . '_block_html', $header) && $header[$side . '_block_html'] !== null;

    $fallback = ($side === 'left') ? zimrx_bridge_default_left_lines() : zimrx_bridge_default_right_lines();

    for ($i = 1; $i <= 10; $i++) {
        $key = $side . '_line_' . $i;
        if ($isCustomized) {
            // Strictly use what is in the database, preserving empty lines.
            $lines[$i] = (string)($header[$key] ?? '');
        } else {
            // Use the fallback auto-population for new users
            $value = trim((string)($header[$key] ?? ''));
            $lines[$i] = $value !== '' ? $value : (string)($fallback[$i] ?? '');
        }
    }

    return $lines;
}

function zimrx_bridge_get_other_qualification(array $header, string $side): string {
    $otherSide = ($side === 'left') ? 'right' : 'left';
    $key = $otherSide . '_line_2';
    if (isset($header[$key]) && trim((string)$header[$key]) !== '') {
        return trim((string)$header[$key]);
    }
    $fallback = ($otherSide === 'left') ? zimrx_bridge_default_left_lines() : zimrx_bridge_default_right_lines();
    return (string)($fallback[2] ?? '');
}

function zimrx_bridge_format_qualifications(string $text, string $otherText): string {
    $text = trim($text);
    $otherText = trim($otherText);
    $items = array_map('trim', explode(',', $text));
    $otherItems = array_map('trim', explode(',', $otherText));
    
    if (count($items) === count($otherItems) && count($items) > 1) {
        $count = count($items);
        $splitIndex = ceil($count / 2);
        
        $firstPart = array_slice($items, 0, $splitIndex);
        $secondPart = array_slice($items, $splitIndex);
        
        $fmt = function($str) {
            $escaped = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
            return preg_replace('/(\S+)\s+(\([^)]+\))/', '$1&nbsp;$2', $escaped);
        };
        
        return $fmt(implode(', ', $firstPart)) . ',<br>' . $fmt(implode(', ', $secondPart));
    } else {
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        return preg_replace('/(\S+)\s+(\([^)]+\))/', '$1&nbsp;$2', $escaped);
    }
}

function zimrx_bridge_visual_block_html(array $header, string $side, array $lines): string {
    $field = $side . '_block_html';
    $html = trim((string)($header[$field] ?? ''));
    if ($html !== '') {
        return $html;
    }

    $chunks = [];
    foreach ($lines as $index => $line) {
        $content = trim((string)$line);
        if ($content === '') {
            continue;
        }
        $lineNumber = $index; // $lines is 1-indexed (keys 1–10), so use $index directly
        $class = $side === 'left'
            ? "zrx-header-line zrx-header-left-line-{$lineNumber}"
            : "zrx-header-line zrx-header-right-line-{$lineNumber}";

        if ($lineNumber === 2) {
            $otherText = zimrx_bridge_get_other_qualification($header, $side);
            $escaped = zimrx_bridge_format_qualifications($content, $otherText);
        } else {
            $escaped = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
            $escaped = preg_replace('/(\S+)\s+(\([^)]+\))/', '$1&nbsp;$2', $escaped);
        }

        // Generate inner HTML with explicit font face, size, and bolding based on line number
        if ($side === 'left') {
            if ($lineNumber === 1) {
                // Bangla name: Kongsho font, size 5 (~18pt), bold, negative bottom margin
                $inner = '<b><font face="kongsho" size="5">' . $escaped . '</font></b>';
                $chunks[] = '<p class="' . $class . '" style="margin-bottom:-3.5px;">' . $inner . '</p>';
            } elseif ($lineNumber === 2 || $lineNumber === 3) {
                // Qualifications / Designation: SolaimanLipi, 11pt, bold
                $inner = '<b><font face="solaimanlipi"><span style="font-size:11pt;">' . $escaped . '</span></font></b>';
                $chunks[] = '<p class="' . $class . '">' . $inner . '</p>';
            } elseif ($lineNumber === 10) {
                // Line 10 (usually footer or seal): AdorshoLipi, 11pt
                $inner = '<font face="adorsholipi"><span style="font-size:11pt;">' . $escaped . '</span></font>';
                $chunks[] = '<p class="' . $class . '">' . $inner . '</p>';
            } else {
                // Standard lines: SolaimanLipi, 11pt
                $inner = '<font face="solaimanlipi"><span style="font-size:11pt;">' . $escaped . '</span></font>';
                $chunks[] = '<p class="' . $class . '">' . $inner . '</p>';
            }
        } else {
            // English side (Right): Times New Roman default
            if ($lineNumber === 1) {
                // English name: Times New Roman, size 4 (~14pt), bold, word-spacing -0.05em
                $inner = '<b><font face="times new roman" size="4"><span style="word-spacing:-0.05em;">' . $escaped . '</span></font></b>';
                $chunks[] = '<p class="' . $class . '">' . $inner . '</p>';
            } elseif ($lineNumber === 2 || $lineNumber === 3) {
                // Qualifications / Designation: Times New Roman, 11pt, bold, word-spacing -0.05em
                $inner = '<b><font face="times new roman"><span style="font-size:11pt;word-spacing:-0.05em;">' . $escaped . '</span></font></b>';
                $chunks[] = '<p class="' . $class . '">' . $inner . '</p>';
            } else {
                // Standard lines: Times New Roman, 11pt, word-spacing -0.05em
                $inner = '<font face="times new roman"><span style="font-size:11pt;word-spacing:-0.05em;">' . $escaped . '</span></font>';
                $chunks[] = '<p class="' . $class . '">' . $inner . '</p>';
            }
        }






    }

    return implode('', $chunks);
}

function zimrx_bridge_load_header_settings(PDO $pdo, int $doctorId): array {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM zimrx_prescription_header_settings WHERE doctor_id = :doctor_id");
    $stmt->execute(['doctor_id' => $doctorId]);
    $exists = (int)$stmt->fetchColumn() > 0;

    if (!$exists) {
        try {
            $pdo->prepare(
                "INSERT INTO zimrx_prescription_header_settings (doctor_id, doctor_name) VALUES (:doctor_id, :doctor_name)"
            )->execute([
                'doctor_id' => $doctorId,
                'doctor_name' => current_user_name(),
            ]);
        } catch (PDOException $e) {
            // Ignore if already inserted concurrently
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM zimrx_prescription_header_settings WHERE doctor_id = :doctor_id LIMIT 1");
    $stmt->execute(['doctor_id' => $doctorId]);
    $header = $stmt->fetch() ?: [];

    if (trim((string)($header['doctor_name'] ?? '')) === '') {
        $header['doctor_name'] = current_user_name();
    }

    return $header;
}

function zimrx_bridge_load_print_options(PDO $pdo, int $doctorId): array {
    $defaults = zimrx_bridge_default_print_options();

    $pdo->prepare(DbSql::insertIgnore('zimrx_prescription_print_layout_settings', 'doctor_id', ':doctor_id'))
        ->execute(['doctor_id' => $doctorId]);

    $stmt = $pdo->prepare("SELECT * FROM zimrx_prescription_print_layout_settings WHERE doctor_id = :doctor_id LIMIT 1");
    $stmt->execute(['doctor_id' => $doctorId]);
    $layout = $stmt->fetch() ?: [];

    $advanced = json_decode((string)($layout['print_settings_json'] ?? '{}'), true);
    if (!is_array($advanced)) {
        $advanced = [];
    }

    $options = $defaults;

    if (isset($layout['page_width_cm'])) {
        $options['page_width'] = (string)$layout['page_width_cm'];
        $options['header_width'] = (string)$layout['page_width_cm'];
        $options['footer_width'] = (string)$layout['page_width_cm'];
    }
    if (isset($layout['page_height_cm'])) {
        $options['page_height'] = (string)$layout['page_height_cm'];
    }
    if (isset($layout['header_height_cm'])) {
        $options['header_height'] = (string)$layout['header_height_cm'];
    }
    if (isset($layout['patient_info_height_cm'])) {
        $options['pt_info_height'] = (string)$layout['patient_info_height_cm'];
    }
    if (isset($layout['left_width_cm'])) {
        $options['left_width'] = (string)$layout['left_width_cm'];
    }
    if (isset($layout['footer_height_cm'])) {
        $options['footer_height'] = (string)$layout['footer_height_cm'];
    }
    if (isset($layout['show_header'])) {
        $options['preview_header_type'] = ((string)$layout['show_header'] === '0') ? 'without_header' : 'with_header';
    }
    if (isset($layout['show_footer'])) {
        $options['display_footer'] = ((string)$layout['show_footer'] === '0') ? 'no' : 'yes';
    }

    $sameNameFields = [
        'header_width', 'pt_info_section_width', 'footer_width',
        'header_type', 'preview_header_type', 'display_footer', 'footer_height', 'display_barcode',
        'revisit_position', 'print_delay', 'dr_n_gap', 'bullet_text', 'drug_bullet', 'drug_no_style', 'drug_row_format',
        'generic_position', 'generic_wrapper', 'generic_font', 'generic_font_size', 'generic_font_style', 'generic_margin_left', 'generic_margin_top',
        'print_generic_name_format', 'suffix_prefix_usage', 'lbl_generic', 'lbl_brand', 'lbl_instruction',
        'dose_language', 'duration_language', 'instruction_language', 'advice_language',
        'pt_info_font', 'pt_info_font_size', 'pt_info_margin_top', 'pt_info_margin_bottom',
        'left_height', 'left_width', 'left_font', 'left_font_size', 'left_margin_left',
        'left_margin_top', 'left_line_height', 'right_height', 'right_width', 'right_font',
        'right_font_size', 'right_margin_top', 'pres_main_height', 'pres_main_width',
        'pres_main_left_margin', 'pres_main_margin_top', 'pres_line_height', 'pres_gap_height',
        'rx_font', 'rx_font_size', 'rx_block_margin_left', 'rx_block_margin_top',
        'bn_font', 'bn_font_size', 'dose_lt_padding', 'upd_font', 'upd_font_size', 'upd_line_height',
        'print_pos_1', 'print_pos_2', 'print_pos_3', 'print_pos_4', 'print_pos_5', 'print_pos_6',
        'print_pos_7', 'print_pos_8', 'print_pos_9', 'print_pos_10', 'print_pos_11', 'print_pos_12',
        'print_pos_13', 'print_pos_14', 'pc_name', 'history_name', 'oe_name', 'dx_name', 'ix_name',
        'dh_name', 'plan_name', 'note_name', 'oh_name', 'mh_name', 'report_name', 'edd_name',
        'lbl_history_medical', 'lbl_history_treatments', 'lbl_history_habits', 'lbl_history_diet',
        'lbl_history_hypersensitivity', 'lbl_history_drug',
        'print_history_pos_1', 'print_history_pos_2', 'print_history_pos_3', 'print_history_pos_4', 'print_history_pos_5', 'print_history_pos_6',
        'display_name', 'display_name_t',
        'display_age', 'display_age_t', 'display_sex', 'display_sex_t', 'display_address',
        'display_address_t', 'display_mobile', 'display_mobile_t', 'display_weight', 'display_weight_t',
        'display_reg_no', 'display_reg_no_t', 'display_date', 'display_date_t',
        'patient_label_name', 'patient_label_age', 'patient_label_sex', 'patient_label_address',
        'patient_label_mobile', 'patient_label_weight', 'patient_label_reg_no', 'patient_label_date',
        'stamp_path', 'stamp_opacity', 'stamp_scale', 'stamp_angle', 'stamp_offset_x', 'stamp_offset_y', 'stamp_color', 'stamp_color_enable',
        'pc_format',
        'dx_format',
        'dx_bullet',
        'has_onboarded',
    ];


    foreach ($sameNameFields as $field) {
        if (array_key_exists($field, $advanced) && ($advanced[$field] !== '' || str_starts_with($field, 'patient_label_'))) {
            $options[$field] = (string)$advanced[$field];
        }
    }

    $legacyAliases = [
        'display_visit_no' => 'visit_number',
        'display_signature' => 'disp_signature',
        'display_generic_name' => 'disp_generic',
        'display_rx' => 'disp_rx',
        'dx_format' => 'ext_2',
        'report_position' => 'ext_3',
        'pt_info_top_line' => 'dec_line_top_1',
        'pt_info_bottom_line' => 'dec_line_top_2',
        'prescription_left_line' => 'dec_line_left',
        'prescription_bottom_line' => 'dec_line_bottom',
        'pt_info_row' => 'info_row',
        'revisit_date' => 'barcode_position',
        'otnote_name' => 'edd_name',
    ];

    foreach ($legacyAliases as $current => $legacy) {
        if (array_key_exists($current, $advanced) && $advanced[$current] !== '') {
            $options[$legacy] = (string)$advanced[$current];
        }
    }

    if (trim((string)$options['right_width']) === '') {
        $pageWidth = (float)$options['page_width'];
        $leftWidth = (float)$options['left_width'];
        $options['right_width'] = (string)max(0, round($pageWidth - $leftWidth, 1));
    }

    if (trim((string)($options['header_width'] ?? '')) === '') {
        $options['header_width'] = (string)$options['page_width'];
    }
    if (trim((string)($options['pt_info_section_width'] ?? '')) === '') {
        $options['pt_info_section_width'] = (string)$options['page_width'];
    }
    if (trim((string)($options['footer_width'] ?? '')) === '') {
        $options['footer_width'] = (string)$options['page_width'];
    }

    if (trim((string)$options['left_height']) === '' || trim((string)$options['right_height']) === '') {
        $bodyHeight = max(
            0,
            (float)$options['page_height']
            - (float)$options['header_height']
            - (float)$options['pt_info_height']
            - (float)$options['footer_height']
        );
        if (trim((string)$options['left_height']) === '') {
            $options['left_height'] = (string)$bodyHeight;
        }
        if (trim((string)$options['right_height']) === '') {
            $options['right_height'] = (string)$bodyHeight;
        }
    }

    return $options;
}

function zimrx_bridge_legacy_form_to_payload(array $input): array {
    $payload = [];

    $coreMap = [
        'page_width' => 'page_width_cm',
        'page_height' => 'page_height_cm',
        'header_height' => 'header_height_cm',
        'pt_info_height' => 'patient_info_height_cm',
        'left_width' => 'left_width_cm',
        'footer_height' => 'footer_height_cm',
    ];
    foreach ($coreMap as $legacy => $current) {
        if (array_key_exists($legacy, $input)) {
            $payload[$current] = trim((string)$input[$legacy]);
        }
    }

    $sameNameFields = [
        'header_height', 'header_width', 'pt_info_height', 'pt_info_section_width', 'pt_info_font', 'pt_info_font_size', 'pt_info_margin_left',
        'pt_info_margin_top', 'pt_info_margin_bottom', 'pt_info_line_height', 'left_height',
        'left_width', 'left_font', 'left_font_size', 'left_margin_left', 'left_margin_top',
        'left_margin_bottom', 'left_line_height', 'right_height', 'right_width', 'right_font',
        'right_font_size', 'right_margin_top', 'right_margin_bottom',
        'right_line_height', 'pres_main_height', 'pres_main_width', 'pres_main_left_margin',
        'pres_main_margin_top', 'pres_main_margin_bottom', 'pres_line_height', 'pres_gap_height',
        'rx_font', 'rx_font_size', 'rx_block_margin_left', 'rx_block_margin_top',
        'bn_font', 'bn_font_size', 'dose_lt_padding', 'upd_font', 'upd_font_size', 'upd_line_height',
        'header_type', 'preview_header_type', 'display_header', 'display_pt_info', 'display_footer', 'footer_height', 'footer_width', 'display_barcode',
        'revisit_position', 'print_delay', 'display_drug_no', 'dr_n_gap', 'bullet_text', 'drug_bullet', 'drug_no_style', 'drug_row_format',
        'generic_position', 'generic_wrapper', 'generic_font', 'generic_font_size', 'generic_font_style', 'generic_margin_left', 'generic_margin_top',
        'print_generic_name_format', 'suffix_prefix_usage',
        'dose_language', 'duration_language', 'instruction_language', 'advice_language',
        'print_pos_1', 'print_pos_2', 'print_pos_3', 'print_pos_4', 'print_pos_5', 'print_pos_6',
        'print_pos_7', 'print_pos_8', 'print_pos_9', 'print_pos_10', 'print_pos_11', 'print_pos_12',
        'print_pos_13', 'print_pos_14', 'pc_name', 'history_name', 'oe_name', 'dx_name', 'ix_name',
        'dh_name', 'plan_name', 'note_name', 'oh_name', 'mh_name', 'report_name', 'edd_name',
        'lbl_history_medical', 'lbl_history_treatments', 'lbl_history_habits', 'lbl_history_diet',
        'lbl_history_hypersensitivity', 'lbl_history_drug',
        'display_name', 'display_name_t',
        'display_age', 'display_age_t', 'display_sex', 'display_sex_t', 'display_address',
        'display_address_t', 'display_mobile', 'display_mobile_t', 'display_weight', 'display_weight_t',
        'display_reg_no', 'display_reg_no_t', 'display_date', 'display_date_t',
        'patient_label_name', 'patient_label_age', 'patient_label_sex', 'patient_label_address',
        'patient_label_mobile', 'patient_label_weight', 'patient_label_reg_no', 'patient_label_date',
        'stamp_path', 'stamp_opacity', 'stamp_scale', 'stamp_angle', 'stamp_offset_x', 'stamp_offset_y', 'stamp_color', 'stamp_color_enable',
        'pc_format',
        'dx_format',
        'dx_bullet',
    ];
    foreach ($sameNameFields as $field) {
        if (array_key_exists($field, $input)) {
            $payload[$field] = trim((string)$input[$field]);
        }
    }

    $legacyAliases = [
        'visit_number' => 'display_visit_no',
        'disp_signature' => 'display_signature',
        'disp_generic' => 'display_generic_name',
        'disp_rx' => 'display_rx',
        'ext_2' => 'dx_format',
        'ext_3' => 'report_position',
        'dec_line_top_1' => 'pt_info_top_line',
        'dec_line_top_2' => 'pt_info_bottom_line',
        'dec_line_left' => 'prescription_left_line',
        'dec_line_bottom' => 'prescription_bottom_line',
        'info_row' => 'pt_info_row',
        'barcode_position' => 'revisit_date',
        'edd_name' => 'otnote_name',
    ];
    foreach ($legacyAliases as $legacy => $current) {
        if (array_key_exists($legacy, $input)) {
            $payload[$current] = trim((string)$input[$legacy]);
        }
    }

    if (!isset($payload['preview_header_type']) && isset($input['preview_header_type'])) {
        $payload['preview_header_type'] = trim((string)$input['preview_header_type']);
    }
    if (!isset($payload['display_footer']) && isset($input['display_footer'])) {
        $payload['display_footer'] = trim((string)$input['display_footer']);
    }

    return $payload;
}

function zimrx_bridge_save_print_setup(PDO $pdo, int $doctorId, array $data): void {
    $layoutFields = [
        'page_width_cm', 'page_height_cm', 'header_height_cm', 'patient_info_height_cm',
        'left_width_cm', 'footer_height_cm', 'body_font_size_pt', 'rx_font_size_pt',
        'line_height_pt', 'show_header', 'show_footer'
    ];
    $advancedFields = [
        'header_height', 'header_width', 'pt_info_height', 'pt_info_section_width', 'pt_info_font', 'pt_info_font_size', 'pt_info_margin_left',
        'pt_info_margin_top', 'pt_info_margin_bottom', 'pt_info_line_height', 'left_height',
        'left_width', 'left_font', 'left_font_size', 'left_margin_left', 'left_margin_top',
        'left_margin_bottom', 'left_line_height', 'right_height', 'right_width', 'right_font',
        'right_font_size', 'right_margin_left', 'right_margin_top', 'right_margin_bottom',
        'right_line_height', 'pres_main_height', 'pres_main_width', 'pres_main_left_margin',
        'pres_main_margin_top', 'pres_main_margin_bottom', 'pres_line_height', 'pres_gap_height',
        'rx_font', 'rx_font_size', 'rx_block_margin_left', 'rx_block_margin_top',
        'bn_font', 'bn_font_size', 'dose_lt_padding', 'upd_font', 'upd_font_size', 'upd_line_height',
        'header_type', 'preview_header_type', 'display_header', 'display_pt_info', 'display_footer', 'footer_height', 'footer_width', 'display_barcode',
        'revisit_date', 'display_visit_no', 'display_signature', 'display_generic_name', 'display_rx',
        'generic_position', 'generic_wrapper', 'generic_font', 'generic_font_size', 'generic_font_style', 'generic_margin_left', 'generic_margin_top', 'drug_row_format',
        'lbl_generic', 'lbl_brand', 'lbl_instruction',
        'print_generic_name_format', 'suffix_prefix_usage',
        'dose_language', 'duration_language', 'instruction_language', 'advice_language',
        'revisit_position', 'print_delay', 'display_drug_no', 'dr_n_gap', 'dx_format',
        'report_position', 'pt_info_top_line', 'pt_info_bottom_line', 'prescription_left_line',
        'prescription_bottom_line', 'bullet_text', 'drug_bullet', 'drug_no_style', 'pt_info_row', 'pt_info_width',
        'print_pos_1', 'print_pos_2', 'print_pos_3', 'print_pos_4', 'print_pos_5', 'print_pos_6',
        'print_pos_7', 'print_pos_8', 'print_pos_9', 'print_pos_10', 'print_pos_11', 'print_pos_12',
        'print_pos_13', 'print_pos_14', 'pc_name', 'history_name', 'oe_name', 'dx_name', 'ix_name',
        'dh_name', 'plan_name', 'note_name', 'oh_name', 'mh_name', 'report_name', 'otnote_name',
        'print_history_pos_1', 'print_history_pos_2', 'print_history_pos_3', 'print_history_pos_4', 'print_history_pos_5', 'print_history_pos_6',
        'lbl_history_medical', 'lbl_history_treatments', 'lbl_history_habits', 'lbl_history_diet', 'lbl_history_hypersensitivity', 'lbl_history_drug',
        'display_name', 'display_name_t',
        'display_age', 'display_age_t', 'display_sex', 'display_sex_t', 'display_address',
        'display_address_t', 'display_mobile', 'display_mobile_t', 'display_weight', 'display_weight_t',
        'display_reg_no', 'display_reg_no_t', 'display_date', 'display_date_t',
        'patient_label_name', 'patient_label_age', 'patient_label_sex', 'patient_label_address',
        'patient_label_mobile', 'patient_label_weight', 'patient_label_reg_no', 'patient_label_date',
        'header_left_width', 'header_logo_width', 'header_right_width',
        'logo_scale', 'logo_rotation', 'logo_opacity', 'logo_offset_x', 'logo_offset_y',
        'stamp_path', 'stamp_opacity', 'stamp_scale', 'stamp_angle', 'stamp_offset_x', 'stamp_offset_y',
        'stamp_color', 'stamp_color_enable', 'has_onboarded',
        'pc_format', 'dx_bullet'
    ];
    $headerFields = [
        'doctor_name', 'qualifications', 'specialty', 'bmdc_no', 'chamber_name',
        'chamber_address', 'chamber_phone', 'header_note', 'footer_note', 'logo_path',
        'display_logo', 'bg_color', 'header_type', 'full_body_header_path', 'footer_html', 'left_block_html', 'right_block_html',
        'bg_image_path', 'bg_image_opacity', 'bg_image_scale', 'bg_image_angle', 'bg_image_offset_x', 'bg_image_offset_y',
        'left_line_1', 'left_line_2', 'left_line_3', 'left_line_4', 'left_line_5',
        'left_line_6', 'left_line_7', 'left_line_8', 'left_line_9', 'left_line_10',
        'right_line_1', 'right_line_2', 'right_line_3', 'right_line_4', 'right_line_5',
        'right_line_6', 'right_line_7', 'right_line_8', 'right_line_9', 'right_line_10'
    ];

    $pdo->prepare(DbSql::insertIgnore('zimrx_prescription_print_layout_settings', 'doctor_id', ':doctor_id'))
        ->execute(['doctor_id' => $doctorId]);

    $existingLayoutStmt = $pdo->prepare("SELECT print_settings_json FROM zimrx_prescription_print_layout_settings WHERE doctor_id = :doctor_id LIMIT 1");
    $existingLayoutStmt->execute(['doctor_id' => $doctorId]);
    $existingAdvanced = json_decode((string)($existingLayoutStmt->fetchColumn() ?: '{}'), true);
    if (!is_array($existingAdvanced)) {
        $existingAdvanced = [];
    }

    $layoutSql = [];
    $layoutParams = ['doctor_id' => $doctorId];
    foreach ($layoutFields as $field) {
        if (array_key_exists($field, $data)) {
            $layoutSql[] = "{$field} = :{$field}";
            $layoutParams[$field] = trim((string)$data[$field]);
        }
    }

    $advancedData = $existingAdvanced;
    foreach ($advancedFields as $field) {
        if (array_key_exists($field, $data)) {
            $advancedData[$field] = trim((string)$data[$field]);
        }
    }

    $advancedToCore = [
        'header_height' => 'header_height_cm',
        'pt_info_height' => 'patient_info_height_cm',
        'left_width' => 'left_width_cm',
        'footer_height' => 'footer_height_cm',
    ];
    foreach ($advancedToCore as $advancedField => $layoutField) {
        if (array_key_exists($advancedField, $advancedData) && $advancedData[$advancedField] !== '') {
            $layoutSql[] = "{$layoutField} = :{$layoutField}";
            $layoutParams[$layoutField] = trim((string)$advancedData[$advancedField]);
        }
    }

    if (isset($advancedData['display_footer'])) {
        $layoutSql[] = "show_footer = :show_footer";
        $layoutParams['show_footer'] = strtolower((string)$advancedData['display_footer']) === 'no' ? '0' : '1';
    }

    if (isset($advancedData['preview_header_type'])) {
        $layoutSql[] = "show_header = :show_header";
        $layoutParams['show_header'] = strtolower((string)$advancedData['preview_header_type']) === 'without_header' ? '0' : '1';
    }

    if ($advancedData !== $existingAdvanced) {
        $layoutSql[] = "print_settings_json = :print_settings_json";
        $layoutParams['print_settings_json'] = json_encode($advancedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if ($layoutSql) {
        $layoutSql[] = "updated_at = CURRENT_TIMESTAMP";
        $stmt = $pdo->prepare("UPDATE zimrx_prescription_print_layout_settings SET " . implode(', ', $layoutSql) . " WHERE doctor_id = :doctor_id");
        $stmt->execute($layoutParams);
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM zimrx_prescription_header_settings WHERE doctor_id = :doctor_id");
    $stmt->execute(['doctor_id' => $doctorId]);
    $exists = (int)$stmt->fetchColumn() > 0;

    if (!$exists) {
        try {
            $pdo->prepare(
                "INSERT INTO zimrx_prescription_header_settings (doctor_id, doctor_name) VALUES (:doctor_id, :doctor_name)"
            )->execute([
                'doctor_id' => $doctorId,
                'doctor_name' => current_user_name(),
            ]);
        } catch (PDOException $e) {
            // Ignore if already inserted concurrently
        }
    }

    $headerSql = [];
    $headerParams = ['doctor_id' => $doctorId];
    foreach ($headerFields as $field) {
        if (array_key_exists($field, $data)) {
            $headerSql[] = "{$field} = :{$field}";
            $value = (string)$data[$field];
            if ($field === 'bg_color') {
                $value = strtoupper(ltrim(trim($value), '#'));
            } elseif (!in_array($field, ['footer_html', 'left_block_html', 'right_block_html'], true)) {
                $value = trim($value);
            }
            $headerParams[$field] = $value;
        }
    }
    if ($headerSql) {
        $headerSql[] = "updated_at = CURRENT_TIMESTAMP";
        $stmt = $pdo->prepare("UPDATE zimrx_prescription_header_settings SET " . implode(', ', $headerSql) . " WHERE doctor_id = :doctor_id");
        $stmt->execute($headerParams);
    }
}

function zimrx_bridge_reset_print_setup(PDO $pdo, int $doctorId): void {
    $currentOptions = zimrx_bridge_load_print_options($pdo, $doctorId);
    $defaultOptions = zimrx_bridge_default_print_options();

    // Preserve Page Setup dimensions & Stamp settings when resetting print layout options
    $preservedKeys = [
        'page_width', 'page_height',
        'header_height', 'header_width',
        'pt_info_height', 'pt_info_section_width', 'pt_info_width',
        'left_height', 'left_width',
        'right_height', 'right_width',
        'footer_height', 'footer_width',
        'stamp_path', 'stamp_opacity', 'stamp_scale', 'stamp_angle',
        'stamp_offset_x', 'stamp_offset_y', 'stamp_color', 'stamp_color_enable'
    ];

    foreach ($preservedKeys as $key) {
        if (isset($currentOptions[$key])) {
            $defaultOptions[$key] = $currentOptions[$key];
        }
    }

    $payload = zimrx_bridge_legacy_form_to_payload($defaultOptions);
    zimrx_bridge_save_print_setup($pdo, $doctorId, $payload);
}

function zimrx_bridge_header_preview_payload(array $header): array {
    $leftLines = zimrx_bridge_header_lines($header, 'left');
    $rightLines = zimrx_bridge_header_lines($header, 'right');

    return [
        'bn' => array_values($leftLines),
        'en' => array_values($rightLines),
        'logo' => trim((string)($header['logo_path'] ?? '')),
    ];
}

function zimrx_bridge_footer_html(array $header): string {
    $footerHtml = trim((string)($header['footer_html'] ?? ''));
    if ($footerHtml === '') {
        $footerHtml = nl2br(htmlspecialchars((string)($header['footer_note'] ?? ''), ENT_QUOTES, 'UTF-8'));
    }
    return $footerHtml;
}

function zimrx_bridge_sample_preview_data(array $header): array {
    return [
        'header' => zimrx_bridge_header_preview_payload($header),
        'patient' => [
            'name' => 'Sample Patient',
            'age' => '36Y',
            'sex' => 'Male',
            'date' => date('d/m/Y'),
            'address' => 'Dhanmondi, Dhaka',
            'regno' => '1236',
            'weight' => '53 Kg',
            'mobile' => '01710000000',
            'ref_by' => '',
            'visit_no' => '1',
        ],
        'clinical' => [
            'pc' => ['Chest pain', 'Shortness of breath'],
            'history' => [
                'medical' => ['HTN', 'DM', 'Asthma'],
                'treatments' => [
                    ['procedure' => 'RICA proximal stent angioplasty', 'year' => '2026'],
                ],
                'habits' => ['Smoking'],
                'diet' => 'Standard',
                'hypersensitivity' => ['Shrimp', 'Paracetamol'],
                'drug_history' => ['Metronidazole 400mg (1+0+1)'],
            ],
            'ho' => ['HTN', 'DM', 'Asthma'],
            'pe' => [
                ['name' => 'BP', 'value' => '90/60 mmHg'],
                ['name' => 'Pulse', 'value' => '66 b/min'],
                ['name' => 'SpO2', 'value' => '98 %'],
            ],
            'oe' => [
                ['name' => 'BP', 'value' => '90/60 mmHg'],
                ['name' => 'Pulse', 'value' => '66 b/min'],
                ['name' => 'SpO2', 'value' => '98 %'],
            ],
            'reports' => [
                ['name' => 'CBC', 'date' => date('d/M/y'), 'value' => 'Normal'],
            ],
            'dh' => ['Metronidazole 400mg (1+0+1)'],
            'plan' => ['Follow-up', 'Lifestyle changes'],
            'ix' => ['CBC', 'RBS', 'ECG'],
            'dx' => ['IHD', 'DM'],
            'oh' => [],
            'mh' => [],
            'note' => [],
            'otnote' => [],
            'text_pad' => '',
            'drugs' => [
                [
                    'brand' => 'TAB. ANRIL SR 2.6 mg',
                    'brand_name' => 'Anril SR',
                    'pres_new_upper' => 'TAB. ANRIL SR 2.6 mg',
                    'full_form_brand_name' => 'TABLET ANRIL SR 2.6 mg',
                    'generic' => 'Glyceryl Trinitrate (nitroglycerine)',
                    'generic_name' => 'Glyceryl Trinitrate (nitroglycerine)',
                    'prescribe_generic_short' => 'Glyceryl Trinitrate (nitroglycerine) 2.6 mg SR tablet',
                    'prescribe_generic_full' => 'Glyceryl Trinitrate (nitroglycerine) 2.6 mg SR tablet',
                    'labelled_generic_short' => 'TAB. GLYCERYL TRINITRATE (NITROGLYCERINE) 2.6 mg (SR)',
                    'labelled_generic_full' => 'TABLET GLYCERYL TRINITRATE (NITROGLYCERINE) 2.6 mg (SR)',
                    'dose' => '1+0+1',
                    'food' => 'After food',
                    'duration' => 'Continue',
                ],
                [
                    'brand' => 'TAB. ATOVA 10 mg',
                    'brand_name' => 'Atova',
                    'pres_new_upper' => 'TAB. ATOVA 10 mg',
                    'full_form_brand_name' => 'TABLET ATOVA 10 mg',
                    'generic' => 'Atorvastatin',
                    'generic_name' => 'Atorvastatin',
                    'prescribe_generic_short' => 'Atorvastatin 10 mg tablet',
                    'prescribe_generic_full' => 'Atorvastatin 10 mg tablet',
                    'labelled_generic_short' => 'TAB. ATORVASTATIN 10 mg',
                    'labelled_generic_full' => 'TABLET ATORVASTATIN 10 mg',
                    'dose' => '0+0+1',
                    'food' => 'Before sleep',
                    'duration' => '1 month',
                ],
            ],
            'advice' => ['Take medicines regularly.', 'Return for review if symptoms worsen.'],
            'revisit' => '',
        ],
        'footer' => zimrx_bridge_footer_html($header),
    ];
}
