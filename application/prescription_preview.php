<?php
require_once 'auth.php';
require_login();
require_once 'db.php';
require_once 'print_setup_lib.php';

$serverSnapshotJson = 'null';
$revisionId = (int)($_GET['revision_id'] ?? 0);
$visitParam = trim((string)($_GET['visit_id'] ?? $_GET['visit_record_id'] ?? ''));

if ($revisionId > 0) {
    try {
        $stmtR = $pdo->prepare("SELECT clinical_snapshot_json, prescription_html FROM zimrx_visit_revisions WHERE id = :id LIMIT 1");
        $stmtR->execute(['id' => $revisionId]);
        $revRow = $stmtR->fetch(PDO::FETCH_ASSOC);
        if ($revRow && !empty($revRow['clinical_snapshot_json'])) {
            $serverSnapshotJson = json_encode(json_decode($revRow['clinical_snapshot_json'], true), JSON_UNESCAPED_UNICODE);
        }
    } catch (Throwable $e) {
    }
} elseif ($visitParam !== '') {
    try {
        $stmtS = is_numeric($visitParam)
            ? $pdo->prepare("SELECT clinical_snapshot_json, prescription_html FROM zimrx_visits WHERE id = :id LIMIT 1")
            : $pdo->prepare("SELECT clinical_snapshot_json, prescription_html FROM zimrx_visits WHERE visit_id = :id LIMIT 1");
        $stmtS->execute(['id' => $visitParam]);
        $visitRow = $stmtS->fetch(PDO::FETCH_ASSOC);
        if ($visitRow && !empty($visitRow['clinical_snapshot_json'])) {
            $serverSnapshotJson = json_encode(json_decode($visitRow['clinical_snapshot_json'], true), JSON_UNESCAPED_UNICODE);
        }
    } catch (Throwable $e) {
    }
}

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

function zrx_non_empty(mixed $value): bool {
    return zrx_trim_text($value) !== '';
}

function zrx_clean_list(mixed $items): array {
    if (!is_array($items)) {
        return [];
    }

    $clean = [];
    foreach ($items as $item) {
        $text = zrx_trim_text($item);
        if ($text !== '') {
            $clean[] = $text;
        }
    }

    return $clean;
}

function zrx_is_placeholder_sidebar_item(string $text, string $slot): bool {
    $slot = strtolower($slot);
    if (!in_array($slot, ['oh', 'mh'], true)) {
        return false;
    }

    $normalized = trim(preg_replace('/\s+/u', ' ', $text));
    if ($normalized === '') {
        return true;
    }

    if (str_contains($normalized, ':')) {
        [, $value] = array_pad(explode(':', $normalized, 2), 2, '');
        $value = trim($value);
        return $value === '' || preg_match('/^(years?|months?|days?)$/iu', $value) === 1;
    }

    return preg_match('/^(married for|alc|para|gravida|age of menarche|mp|mc|lmp|edd)$/iu', $normalized) === 1;
}

function zrx_clean_sidebar_list(mixed $items, string $slot): array {
    $clean = [];
    foreach (zrx_clean_list($items) as $item) {
        if (!zrx_is_placeholder_sidebar_item($item, $slot)) {
            $clean[] = $item;
        }
    }

    return $clean;
}

function zrx_field_label(string $field): string {
    $labels = [
        'name' => 'Name',
        'age' => 'Age',
        'sex' => 'Sex',
        'date' => 'Date',
        'address' => 'Address',
        'regno' => 'Reg No.',
        'bmi_weigh' => 'Wt',
        'mobile' => 'Mobile',
        'ref_by' => 'Ref By',
        'visit_no' => 'Visit No',
    ];

    return $labels[$field] ?? '';
}

function zrx_option_field_key(string $field): string {
    return match ($field) {
        'bmi_weigh' => 'weight',
        'regno' => 'reg_no',
        default => $field,
    };
}

function zrx_patient_value_key(string $field): string {
    return $field === 'bmi_weigh' ? 'weight' : $field;
}

function zrx_show_patient_value(string $field, array $options): bool {
    if ($field === '' || zrx_field_label($field) === '') {
        return false;
    }

    $key = 'display_' . zrx_option_field_key($field);
    return (($options[$key] ?? 'yes') === 'yes');
}

function zrx_show_patient_label(string $field, array $options): bool {
    $key = 'display_' . zrx_option_field_key($field) . '_t';
    return (($options[$key] ?? 'yes') === 'yes');
}

function zrx_patient_label(string $field, array $options): string {
    $key = 'patient_label_' . zrx_option_field_key($field);
    return array_key_exists($key, $options) ? zrx_trim_text($options[$key]) : zrx_field_label($field);
}

function zrx_patient_order_html(int $order, array $patient, array $options, bool $singleRow): string {
    $fieldOrder = [
        1 => 'name',
        2 => 'age',
        3 => 'sex',
        4 => 'date',
        5 => 'address',
        6 => 'regno',
        7 => 'bmi_weigh',
        8 => 'mobile'
    ];
    $field = $fieldOrder[$order] ?? '';
    if (!zrx_show_patient_value($field, $options)) {
        return '';
    }

    $value = zrx_trim_text($patient[zrx_patient_value_key($field)] ?? '');
    if ($field === 'bmi_weigh' && preg_match('/^(kg|kgs|kilogram|kilograms|lb|lbs)$/iu', $value)) {
        $value = '';
    }
    $showLabel = zrx_show_patient_label($field, $options);
    $label = zrx_patient_label($field, $options);
    $html = '';

    $labelClass = 'zrx-patient-label zrx-patient-label-slot-' . $order;
    $colonClass = 'zrx-patient-colon';

    if ($showLabel && $label !== '') {
        if ($singleRow) {
            $html .= '<td class="' . $labelClass . '">' . preview_escape($label) . ' :</td>';
        } else {
            $html .= '<td class="' . $labelClass . '">' . preview_escape($label) . '</td>';
            $html .= '<td class="' . $colonClass . '">:</td>';
        }
    }

    $valueClass = 'zrx-patient-value zrx-patient-position-' . $order;

    $dataFullText = '';
    if ($field === 'address') {
        $valueClass .= ' zrx-patient-address-value';
        $dataFullText = ' data-full-text="' . preview_escape($value) . '"';
    }

    $html .= '<td id="preview-field-' . $order . '" class="' . $valueClass . '"' . $dataFullText . '>' . preview_escape($value) . '</td>';
    return $html;
}

function zrx_patient_table(array $patient, array $options): string {
    $singleRow = (string)($options['info_row'] ?? '2') === '1';

    ob_start();
    ?>
    <table class="zrx-patient-table">
        <tbody>
        <?php if ($singleRow): ?>
            <tr>
                <?php for ($i = 1; $i <= 8; $i++): ?>
                    <?= zrx_patient_order_html($i, $patient, $options, true) ?>
                <?php endfor; ?>
            </tr>
        <?php else: ?>
            <tr>
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <?= zrx_patient_order_html($i, $patient, $options, false) ?>
                <?php endfor; ?>
            </tr>
            <tr>
                <?php for ($i = 5; $i <= 8; $i++): ?>
                    <?= zrx_patient_order_html($i, $patient, $options, false) ?>
                <?php endfor; ?>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php
    return (string)ob_get_clean();
}

function zrx_code39_patterns(): array {
    return [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn', 'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw',
        'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn',
        'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww',
        'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn',
        'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn', 'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw',
        'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '$' => 'nwnwnwnnn',
        '/' => 'nwnwnnnwn', '+' => 'nwnnnwnwn', '%' => 'nnnwnwnwn', '*' => 'nwnnwnwnn',
    ];
}

function zrx_barcode_html(string $value): string {
    $value = strtoupper(trim($value));
    if ($value === '') {
        return '';
    }

    $patterns = zrx_code39_patterns();
    $clean = preg_replace('/[^0-9A-Z\-. $\/+%]/', '', $value);
    if ($clean === '') {
        return '';
    }
    $encoded = '*' . $clean . '*';

    $narrowWidth = 1.3;
    $wideWidth = 3.25;
    $barHeight = 32;
    $quietZone = 14;

    $charBlockWidth = ($wideWidth * 3) + ($narrowWidth * 6);
    $len = strlen($encoded);

    $rects = '';
    $texts = '';
    $x = $quietZone;

    for ($c = 0; $c < $len; $c++) {
        $char = $encoded[$c];
        $pattern = $patterns[$char] ?? $patterns['-'];
        $charStartX = $x;

        for ($i = 0; $i < 9; $i++) {
            $isBar = ($i % 2 === 0);
            $width = ($pattern[$i] === 'w') ? $wideWidth : $narrowWidth;
            if ($isBar) {
                $rects .= '<rect x="' . round($x, 2) . '" y="0" width="' . round($width, 2) . '" height="' . $barHeight . '" fill="#000000"/>';
            }
            $x += $width;
        }

        $charCenterX = $charStartX + ($charBlockWidth / 2);
        $texts .= '<text x="' . round($charCenterX, 2) . '" y="' . ($barHeight + 14) . '" font-family="Consolas, \'Lucida Console\', \'Courier New\', monospace" font-size="13" font-weight="bold" fill="#000000" text-anchor="middle">' . preview_escape($char) . '</text>';

        $x += $narrowWidth;
    }

    $totalWidth = round($x + $quietZone - $narrowWidth, 2);
    $totalHeight = $barHeight + 18;

    $svg = '<svg class="zrx-barcode-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $totalWidth . ' ' . $totalHeight . '" width="' . $totalWidth . 'px" height="' . $totalHeight . 'px" shape-rendering="crispEdges">'
         . '<rect x="0" y="0" width="' . $totalWidth . '" height="' . $totalHeight . '" fill="#ffffff"/>'
         . $rects
         . $texts
         . '</svg>';

    return '<div id="preview-barcode" class="zrx-barcode" data-code="' . preview_escape($clean) . '">' . $svg . '</div>';
}

function zrx_section_table(string $title, array $rows, string $modifier = ''): string {
    if (!$rows) {
        return '';
    }

    $classes = trim('zrx-clinical-section ' . $modifier);

    ob_start();
    ?>
    <div class="<?= preview_escape($classes) ?>">
        <div class="zrx-section-title"><?= preview_escape($title) ?></div>
        <table class="zrx-section-table">
            <tbody>
            <?= implode('', $rows) ?>
            </tbody>
        </table>
    </div>
    <?php
    return (string)ob_get_clean();
}

function zrx_simple_section(array $items, string $title, string $bullet, string $modifier, string $slot, array $options = []): string {
    $rows = [];
    $pcFormat = $options['pc_format'] ?? 'parentheses';
    $countUnits = ['episode', 'episodes', 'attack', 'attacks', 'time', 'times', 'occasion', 'occasions'];

    foreach (zrx_clean_sidebar_list($items, $slot) as $item) {
        $text = $item;
        if ($slot === 'pc') {
            if (preg_match('/^(.+?)\s*\(([^)]+)\)$/u', $item, $matches)) {
                $complaint = trim($matches[1]);
                $durationText = trim($matches[2]);

                if (preg_match('/^([\d\.\-\s০-৯]+)\s*(.+)$/u', $durationText, $durMatches)) {
                    $count = trim($durMatches[1]);
                    $unit = trim($durMatches[2]);
                    $unitLower = strtolower($unit);

                    $isCountUnit = false;
                    foreach ($countUnits as $cu) {
                        if (str_contains($unitLower, $cu)) {
                            $isCountUnit = true;
                            break;
                        }
                    }

                    if ($isCountUnit) {
                        if ($pcFormat === 'for') {
                            $text = $count . ' ' . $unit . ' of ' . $complaint;
                        } elseif ($pcFormat === 'hyphen') {
                            $text = $complaint . ' - ' . $count . ' ' . $unit;
                        } else {
                            $text = $complaint . ' (' . $count . ' ' . $unit . ')';
                        }
                    } else {
                        if ($pcFormat === 'for') {
                            $text = $complaint . ' for ' . $count . ' ' . $unit;
                        } elseif ($pcFormat === 'hyphen') {
                            $text = $complaint . ' - ' . $count . ' ' . $unit;
                        } else {
                            $text = $complaint . ' (' . $count . ' ' . $unit . ')';
                        }
                    }
                } else {
                    if ($pcFormat === 'for') {
                        $text = $complaint . ' for ' . $durationText;
                    } elseif ($pcFormat === 'hyphen') {
                        $text = $complaint . ' - ' . $durationText;
                    } else {
                        $text = $complaint . ' (' . $durationText . ')';
                    }
                }
            }
        }
        $rows[] = '<tr><td class="zrx-bullet-cell">' . preview_escape($bullet) . '</td><td>' . preview_escape($text) . '</td></tr>';
    }

    return zrx_section_table($title, $rows, $modifier);
}

function zrx_render_dx_section(array $items, string $title, string $bullet, array $options = []): string {
    $format = $options['dx_format'] ?? 'per_line';
    $cleaned = [];
    foreach (zrx_clean_sidebar_list($items, 'dx') as $item) {
        $text = zrx_trim_text($item);
        if ($text !== '') {
            $cleaned[] = preview_escape($text);
        }
    }
    if (!$cleaned) {
        return '';
    }

    if ($format === 'single_line') {
        $separator = ' ē ';
        $line = implode($separator, $cleaned);
        $rows = ['<tr><td class="zrx-bullet-cell">' . preview_escape($bullet) . '</td><td>' . $line . '</td></tr>'];
    } else {
        $rows = array_map(fn($text) => '<tr><td class="zrx-bullet-cell">' . preview_escape($bullet) . '</td><td>' . $text . '</td></tr>', $cleaned);
    }

    return zrx_section_table($title, $rows, 'zrx-clinical-section--dx');
}

function zrx_render_pe_section(array $items, string $title): string {
    $rows = [];
    foreach ($items as $item) {
        $name = '';
        $value = '';
        if (is_array($item)) {
            $name = zrx_trim_text($item['name'] ?? '');
            $value = zrx_trim_text($item['value'] ?? '');
        } else {
            $value = zrx_trim_text($item);
        }

        if ($name === '' && $value === '') {
            continue;
        }

        $colon = ($name !== '' && $value !== '') ? ':' : '';
        $rows[] = '<tr><td class="zrx-label-cell">' . preview_escape($name) . '</td><td class="zrx-colon-cell">' . $colon . '</td><td>' . preview_escape($value) . '</td></tr>';
    }

    return zrx_section_table($title, $rows, 'zrx-clinical-section--pe zrx-clinical-section--oe');
}

function zrx_render_oe_section(array $items, string $title): string {
    return zrx_render_pe_section($items, $title);
}

function zrx_render_report_section(array $items, string $title): string {
    $rows = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $name = zrx_trim_text($item['name'] ?? '');
        $date = zrx_trim_text($item['date'] ?? '');
        $value = zrx_trim_text($item['value'] ?? '');
        if ($name === '' && $date === '' && $value === '') {
            continue;
        }

        $nameHtml = $name !== '' ? '<b>' . preview_escape($name) . '</b>' : '';
        $dateHtml = $date !== '' ? '<br><span class="zrx-report-date">' . preview_escape($date) . '</span>' : '';
        $valueHtml = $value !== '' ? ' : ' . preview_escape($value) : '';
        $rows[] = '<tr><td class="zrx-report-name">' . $nameHtml . $dateHtml . '</td><td class="zrx-report-value">' . $valueHtml . '</td></tr>';
    }

    return zrx_section_table($title, $rows, 'zrx-clinical-section--reports');
}

function zrx_history_list(mixed $items): array {
    if (!is_array($items)) {
        return zrx_clean_list([$items]);
    }

    $clean = [];
    foreach ($items as $item) {
        if (is_array($item)) {
            $text = zrx_trim_text($item['label'] ?? $item['value'] ?? $item['name'] ?? '');
        } else {
            $text = zrx_trim_text($item);
        }
        if ($text !== '') {
            $clean[] = $text;
        }
    }

    return $clean;
}

function zrx_history_treatments(mixed $items): array {
    if (!is_array($items)) {
        return [];
    }

    $clean = [];
    foreach ($items as $item) {
        if (is_array($item)) {
            $procedure = zrx_trim_text($item['procedure'] ?? $item['name'] ?? '');
            $year = zrx_trim_text($item['year'] ?? '');
            if ($procedure === '' && $year === '') {
                continue;
            }
            $clean[] = $procedure !== '' && $year !== '' ? $procedure . ' (' . $year . ')' : ($procedure ?: $year);
        } else {
            $text = zrx_trim_text($item);
            if ($text !== '') {
                $clean[] = $text;
            }
        }
    }

    return $clean;
}

function zrx_history_payload(array $clinical): array {
    $history = $clinical['history'] ?? [];
    if (!is_array($history)) {
        $history = [];
    }

    $diet = zrx_trim_text($history['diet'] ?? '');
    if ($diet === 'Standard / Normal') {
        $diet = 'Standard';
    }

    return [
        'medical' => zrx_history_list($history['medical'] ?? ($clinical['ho'] ?? [])),
        'treatments' => zrx_history_treatments($history['treatments'] ?? []),
        'habits' => zrx_history_list($history['habits'] ?? []),
        'diet' => $diet,
        'hypersensitivity' => zrx_history_list($history['hypersensitivity'] ?? []),
        'drug_history' => zrx_history_list($history['drug_history'] ?? ($clinical['dh'] ?? [])),
    ];
}

function zrx_render_history_section(array $clinical, string $bullet, array $options): string {
    $history = zrx_history_payload($clinical);
    $submoduleHtml = [];

    // 1. medical
    $medicalItems = $history['medical'] ?? [];
    if ($medicalItems) {
        $medicalLabel = $options['lbl_history_medical'] ?? 'Medical History:';
        $submoduleHtml['medical'] = '<tr><td><span class="zrx-history-label zrx-history-label--medical">' . preview_escape($medicalLabel) . '</span> ' . preview_escape(implode(', ', $medicalItems)) . '</td></tr>';
    }

    // 2. treatment
    $treatmentItems = $history['treatments'] ?? [];
    if ($treatmentItems) {
        $treatmentLabel = $options['lbl_history_treatments'] ?? 'Treatment History:';
        $submoduleHtml['treatment'] = '<tr><td><span class="zrx-history-label zrx-history-label--treatments">' . preview_escape($treatmentLabel) . '</span> ' . preview_escape(implode(', ', $treatmentItems)) . '</td></tr>';
    }

    // 3. habits
    $habitsItems = $history['habits'] ?? [];
    if ($habitsItems) {
        $habitsLabel = $options['lbl_history_habits'] ?? 'Habits:';
        $submoduleHtml['habits'] = '<tr><td><span class="zrx-history-label zrx-history-label--habits">' . preview_escape($habitsLabel) . '</span> ' . preview_escape(implode(', ', $habitsItems)) . '</td></tr>';
    }

    // 4. diet-hypersensitivity
    $dietHypersensitivityHtml = '';
    if (($history['diet'] ?? '') !== '') {
        $dietLabel = $options['lbl_history_diet'] ?? 'Diet:';
        $dietHypersensitivityHtml .= '<tr><td><span class="zrx-history-label zrx-history-label--diet">' . preview_escape($dietLabel) . '</span> ' . preview_escape($history['diet']) . '</td></tr>';
    }
    if (($history['hypersensitivity'] ?? [])) {
        $hypersensitivityLabel = $options['lbl_history_hypersensitivity'] ?? 'Hypersensitivity:';
        $dietHypersensitivityHtml .= '<tr><td><span class="zrx-history-label zrx-history-label--hypersensitivity">' . preview_escape($hypersensitivityLabel) . '</span> ' . preview_escape(implode(', ', $history['hypersensitivity'])) . '</td></tr>';
    }
    if ($dietHypersensitivityHtml !== '') {
        $submoduleHtml['diet-hypersensitivity'] = $dietHypersensitivityHtml;
    }

    // 5. drug-history
    $drugHistory = $history['drug_history'] ?? [];
    if ($drugHistory) {
        $drugLabel = $options['lbl_history_drug'] ?? 'Drug History:';
        $drugLines = '';
        foreach ($drugHistory as $drug) {
            $drugLines .= '<span class="zrx-history-bullet">&#9675; ' . preview_escape($drug) . '</span>';
        }
        $submoduleHtml['drug-history'] = '<tr><td><span class="zrx-history-label zrx-history-label--drug-history">' . preview_escape($drugLabel) . '</span>' . $drugLines . '</td></tr>';
    }

    // Sort according to layout
    $defaultHistoryLayout = ['medical', 'treatment', 'habits', 'diet-hypersensitivity', 'drug-history'];
    $historyLayout = $defaultHistoryLayout;
    if (isset($_COOKIE['zimrx_history_layout'])) {
        $decoded = json_decode(urldecode($_COOKIE['zimrx_history_layout']), true);
        if (is_array($decoded)) {
            $historyLayout = $decoded;
        }
    }

    $rows = [];
    foreach ($historyLayout as $subName) {
        if ($subName !== '' && isset($submoduleHtml[$subName])) {
            $rows[] = $submoduleHtml[$subName];
        }
    }

    if (!$rows) {
        return '';
    }

    return zrx_section_table('History', $rows, 'zrx-clinical-section--history');
}

function zrx_preview_section_title(?string $title, string $fallback, array $fallbackAliases = []): string {
    $title = zrx_trim_text($title ?? '');
    $aliases = array_merge([$fallback], $fallbackAliases);
    if ($title === '') {
        return $fallback;
    }
    foreach ($aliases as $alias) {
        if (strcasecmp($title, $alias) === 0) {
            return $fallback;
        }
    }
    return $title;
}

function zrx_render_left_sections(array $clinical, array $options): string {
    $titles = [
        'pc' => zrx_preview_section_title($options['pc_name'] ?? null, 'Presenting Complaints', ['P/C', 'P/C']),
        'ho' => $options['history_name'] ?? 'History',
        'pe' => zrx_preview_section_title($options['pe_name'] ?? $options['oe_name'] ?? null, 'Physical Examination', ['P/E']),
        'oe' => zrx_preview_section_title($options['pe_name'] ?? $options['oe_name'] ?? null, 'Physical Examination', ['P/E']),
        'reports' => $options['report_name'] ?? 'Reports',
        'dh' => $options['dh_name'] ?? 'D/H',
        'plan' => $options['plan_name'] ?? 'Plan',
        'advice' => $options['ix_name'] ?? 'Investigations',
        'note' => $options['note_name'] ?? 'Note',
        'oh' => $options['oh_name'] ?? 'O/H',
        'mh' => $options['mh_name'] ?? 'M/H',
        'paediatric' => $options['paediatric_name'] ?? 'Paediatric History',
        'ph' => $options['ph_name'] ?? 'Paediatric History',
        'dx' => $options['dx_name'] ?? 'Dx',
        'edd' => $options['edd_name'] ?? 'OT Note',
        'otnote' => $options['edd_name'] ?? 'OT Note',
    ];

    $bullet = zrx_trim_text($options['bullet_text'] ?? '');
    if ($bullet === '' || $bullet === 'â—‹') {
        $bullet = '○';
    }

    $dxBullet = zrx_trim_text($options['dx_bullet'] ?? '');
    if ($dxBullet === '') {
        $dxBullet = $bullet;
    }

    $html = '';
    $historyRendered = false;
    for ($i = 1; $i <= 14; $i++) {
        $slot = (string)($options['print_pos_' . $i] ?? 'none');
        if ($slot === 'none') {
            continue;
        }

        if ($slot === 'ho' || $slot === 'dh' || $slot === 'history') {
            if (!$historyRendered) {
                $html .= zrx_render_history_section($clinical, $bullet, $options);
                $historyRendered = true;
            }
            continue;
        }

        $items = match ($slot) {
            'advice' => $clinical['ix'] ?? [],
            'edd' => $clinical['edd'] ?? ($clinical['otnote'] ?? []),
            default => $clinical[$slot] ?? [],
        };

        if (!is_array($items) || !$items) {
            continue;
        }

        $title = (string)($titles[$slot] ?? ucfirst($slot));
        $html .= match ($slot) {
            'pe', 'oe' => zrx_render_pe_section($items, $title),
            'reports' => zrx_render_report_section($items, $title),
            'dx' => zrx_render_dx_section($items, $title, $dxBullet, $options),
            default => zrx_simple_section($items, $title, $bullet, 'zrx-clinical-section--' . preg_replace('/[^a-z0-9_-]/i', '', $slot), $slot, $options),
        };
    }

    return $html;
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

    ob_start();
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
                echo '<tr class="zrx-drug-gap-row"><td colspan="4" class="zrx-drug-gap">&nbsp;</td></tr>';
            }
            $isFirstDrug = false;
        }

        $drugName = $brand;
        if ($drugName === '' && $generic !== '') {
            $drugName = $generic;
        }

        $numberHtml = '';
        if (!$isContinuation) {
            if ($displayDrugNo) {
                $numberHtml = '<td class="zrx-drug-number">' . preview_escape(zrx_drug_number_label($counter, $options)) . '</td>';
            } else {
                $numberHtml = '<td class="zrx-drug-number zrx-drug-bullet">' . preview_escape($drugBullet) . '</td>';
            }
        } else {
            $numberHtml = '<td class="zrx-drug-number"></td>';
        }

        $drugRowFormat = (string)($options['drug_row_format'] ?? 'standard');

        if ($drugRowFormat === 'labelled') {
            $brandFontSize = preview_escape((string)($options['right_font_size'] ?? '11')) . 'pt';
            $lblGeneric = (string)($options['lbl_generic'] ?? 'Generic Name:');
            if (trim($lblGeneric) === '') $lblGeneric = 'Generic Name:';
            $lblBrand = (string)($options['lbl_brand'] ?? 'Brand Name Recommendation:');
            if (trim($lblBrand) === '') $lblBrand = 'Brand Name Recommendation:';
            $lblInstruction = (string)($options['lbl_instruction'] ?? 'Instruction:');
            if (trim($lblInstruction) === '') $lblInstruction = 'Instruction:';
            ?>
            <tr class="zrx-drug-name-row">
                <?= $numberHtml ?>
                <td colspan="3" class="zrx-drug-name-cell">
                    <?php if ($generic !== ''): ?>
                        <div style="margin-bottom: 3px;"><span style="font-weight: bold; font-family: 'Times New Roman';" class="zrx-lbl-generic"><?= preview_escape($lblGeneric) ?> </span><span class="zrx-drug-generic" data-generic="<?= preview_escape($generic) ?>" style="font-family: 'Times New Roman', serif; font-style: normal; font-weight: normal; font-size: <?= $brandFontSize ?>; <?= $showGeneric ? 'display: inline-block;' : 'display: none;' ?>"><?= preview_escape($generic) ?></span></div>
                    <?php endif; ?>
                    <?php if ($brand !== ''): ?>
                        <div style="margin-bottom: 3px;"><span style="font-weight: bold; font-family: 'Times New Roman';" class="zrx-lbl-brand"><?= preview_escape($lblBrand) ?> </span><span class="zrx-drug-brand" style="font-weight: normal;"><?= preview_escape($brand) ?></span></div>
                    <?php endif; ?>
                    <?php if ($instruction !== '' || $dose !== '' || $duration !== ''): ?>
                        <div><span style="<?= $isContinuation ? 'visibility: hidden;' : '' ?> font-weight: bold; font-family: 'Times New Roman';" class="zrx-lbl-instruction"><?= preview_escape($lblInstruction) ?> </span>
                            <span class="zrx-drug-dose"><?= preview_escape($dose) ?></span>
                            <span class="zrx-drug-instruction"><?= $instruction !== '' ? '- ' . preview_escape($instruction) : '' ?></span>
                            <span class="zrx-drug-duration"><?= $duration !== '' ? '- ' . preview_escape($duration) : '' ?></span>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        } else {
            if (!$isContinuation) {
            ?>
            <tr class="zrx-drug-name-row">
                <?= $numberHtml ?>
                <td colspan="3" class="zrx-drug-name-cell">
                    <span class="zrx-drug-brand"><?= preview_escape($drugName) ?></span>
                    <?php if ($generic !== '' && $generic !== $drugName): ?>
                        <?php
                            $gWrapper = (string)($options['generic_wrapper'] ?? 'none');
                            $gFormatted = $generic;
                            if ($gWrapper === 'parentheses') $gFormatted = '(' . $generic . ')';
                            elseif ($gWrapper === 'brackets') $gFormatted = '[' . $generic . ']';
                            elseif ($gWrapper === 'hyphen') $gFormatted = '- ' . $generic;
                        ?>
                        <span class="zrx-drug-generic" data-generic="<?= preview_escape($generic) ?>" style="<?= $showGeneric ? ((($options['generic_position'] ?? 'below') === 'below') ? 'display: block;' : 'display: inline-block;') : 'display: none;' ?>"><?= preview_escape($gFormatted) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php } ?>
        <?php if ($dose !== '' || $instruction !== '' || $duration !== ''): ?>
            <tr class="zrx-drug-detail-row">
                <td class="zrx-drug-detail-pad"></td>
                <td class="zrx-drug-dose"><?= preview_escape($dose) ?></td>
                <td class="zrx-drug-instruction"><?= $instruction !== '' ? '- ' . preview_escape($instruction) : '' ?></td>
                <td class="zrx-drug-duration"><?= $duration !== '' ? '- ' . preview_escape($duration) : '' ?></td>
            </tr>
        <?php endif; ?>
        <?php
        }
        if (!$isContinuation) {
            $counter++;
        }
    }

    return (string)ob_get_clean();
}

function zrx_render_advice(array $items, array $options): string {
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

    return '<div id="preview-advice-wrap" class="zrx-advice-section"><table class="zrx-advice-table"><tbody id="preview-advice-rows"><tr><td colspan="2"><u><b>&#x0989;&#x09AA;&#x09A6;&#x09C7;&#x09B6;&#x0983;</b></u></td></tr>' . implode('', $rows) . '</tbody></table></div>';
}

$doctorId = current_user_doctor_id();
$header = zimrx_bridge_load_header_settings($pdo, $doctorId);
$options = zimrx_bridge_load_print_options($pdo, $doctorId);
$sampleData = zimrx_bridge_sample_preview_data($header);
$headerPayload = zimrx_bridge_header_preview_payload($header);

$options['display_logo'] = strtolower((string)($header['display_logo'] ?? (!empty($header['logo_path']) ? 'yes' : 'yes'))) === 'no' ? 'no' : 'yes';
$options['bgcolor'] = strtoupper(ltrim((string)($header['bg_color'] ?? 'FFFFFF'), '#'));
$options['header_logo_url'] = trim((string)($header['logo_path'] ?? ''));
$options['footer_text'] = zimrx_bridge_footer_html($header);

$options['bullet_text'] = zrx_trim_text($options['bullet_text'] ?? '') === 'â—‹' ? '○' : ($options['bullet_text'] ?? '○');
$options['drug_bullet'] = zrx_trim_text($options['drug_bullet'] ?? '') === 'â€¢' ? '•' : ($options['drug_bullet'] ?? '•');

$availableFonts = [
    'SolaimanLipi', 'AdorshoLipi', 'Kongsho', 'BenSenHandwriting', 'Nikosh', 'Siyamrupali', 'KumarkhaliUnicode', 'MangalikUnicode',
    'Times New Roman', 'Arial', 'Calibri', 'Tahoma', 'Georgia', 'Gabriola', 'Courier New',
    'Lucida Calligraphy', 'AkayaKanadaka', 'Birthstone', 'Charm', 'Cookie', 'Damion', 'Engagement', 
    'HappyMonkey', 'JimNightshade', 'Kings', 'Macondo', 'Metamorphous', 'MonteCarlo', 'Parisienne', 
    'ShantellSans', 'TeXGyreChorus', 'Comic Sans', 'Bradley Hand ITC'
];
if (!in_array((string)($options['bn_font'] ?? ''), $availableFonts, true)) {
    $options['bn_font'] = 'SolaimanLipi';
}
if (!in_array((string)($options['upd_font'] ?? ''), $availableFonts, true)) {
    $options['upd_font'] = 'SolaimanLipi';
}

$patient = $sampleData['patient'];
$clinical = $sampleData['clinical'];
$leftHeaderLines = array_values($headerPayload['bn'] ?? []);
$rightHeaderLines = array_values($headerPayload['en'] ?? []);

$embedded = isset($_GET['embedded']) && $_GET['embedded'] === '1';

$pageWidth = (float)($options['page_width'] ?? 21);
$pageHeight = (float)($options['page_height'] ?? 29.7);
$headerHeight = (float)($options['header_height'] ?? 5.3);
$patientHeight = (float)($options['pt_info_height'] ?? 1.6);
$footerHeight = (float)($options['footer_height'] ?? 2.0);
$leftWidth = (float)($options['left_width'] ?? 9.0);
$rightWidth = (float)($options['right_width'] ?? max(0, $pageWidth - $leftWidth));
$leftHeight = (float)($options['left_height'] ?? 20.4);
$rightHeight = (float)($options['right_height'] ?? $leftHeight);

$presMainLeftMargin = (float)($options['pres_main_left_margin'] ?? 40);
$presMainLeftMargin = $presMainLeftMargin <= 0 ? 40 : $presMainLeftMargin;
$rxMarginLeft = (float)($options['rx_block_margin_left'] ?? 10);
$rxMarginLeft = $rxMarginLeft <= 0 ? 10 : $rxMarginLeft;
$rxFontSize = (float)($options['rx_font_size'] ?? 18);
$rxFontSize = $rxFontSize < 12 ? 18 : $rxFontSize;
$adviceFontSize = (float)($options['upd_font_size'] ?? 10.5);
$adviceFontSize = $adviceFontSize < 8 ? 10.5 : $adviceFontSize;
$adviceLineHeight = (float)($options['upd_line_height'] ?? 14);
$adviceLineHeight = $adviceLineHeight < 8 ? 14 : $adviceLineHeight;

$headerType = (string)($header['header_type'] ?? ($options['header_type'] ?? 'text'));
$isImageBody = $headerType === 'image';
$fullBodyHeaderPath = trim((string)($header['full_body_header_path'] ?? ''));

$bgImagePath    = trim((string)($header['bg_image_path'] ?? ''));
$bgImageOpacity = (float)($header['bg_image_opacity'] ?? 0.10);
$bgImageScale   = (float)($header['bg_image_scale'] ?? 1.0);
$bgImageAngle   = (float)($header['bg_image_angle'] ?? 0.0);
$bgImageOffsetX = (float)($header['bg_image_offset_x'] ?? 0.0);
$bgImageOffsetY = (float)($header['bg_image_offset_y'] ?? 0.0);

$showHeader = !$isImageBody && (($options['preview_header_type'] ?? 'with_header') !== 'without_header');
$showFooter = (($options['display_footer'] ?? 'yes') !== 'no');
$footerHtml = trim((string)($options['footer_text'] ?? ''));
$barcodeText = zrx_trim_text($patient['regno'] ?? '');
$barcodePreviewText = $barcodeText !== '' ? str_pad($barcodeText, 6, '0', STR_PAD_LEFT) : '0000000000';
$visitNoText = zrx_trim_text($patient['visit_no'] ?? '') ?: '1';
$refByText = zrx_trim_text($patient['ref_by'] ?? '');
$textPad = trim((string)($clinical['text_pad'] ?? ''));
$revisit = trim((string)($clinical['revisit'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en" class="zrx-fonts-loading">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $embedded ? 'Preview' : 'ZimRx Preview' ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link rel="preload" href="assets/fonts/SolaimanLipi.ttf" as="font" type="font/ttf" crossorigin>
    <link rel="preload" href="assets/fonts/KongshoOMJ.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="assets/fonts/AdorshoLipi.woff" as="font" type="font/woff" crossorigin>
    <link rel="stylesheet" href="assets/css/print_preview.css?v=15" type="text/css">
    <style>
        .zrx-fonts-loading .zrx-print-page {
            visibility: hidden;
        }

        .zrx-fonts-ready .zrx-print-page,
        .zrx-fonts-timeout .zrx-print-page {
            visibility: visible;
        }

        body {
            margin: 0;
            background: #fff;
        }

        .zrx-print-page {
            position: relative;
            overflow: hidden;
            width: <?= $pageWidth ?>cm;
            min-height: <?= $pageHeight ?>cm;
            <?php if ($isImageBody && $fullBodyHeaderPath !== ''): ?>
            background-image: url('<?= preview_escape($fullBodyHeaderPath) ?>');
            background-size: 100% 100%;
            background-repeat: no-repeat;
            background-position: top center;
            <?php endif; ?>
        }

        .zrx-watermark-layer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-repeat: no-repeat;
            background-position: center center;
            background-size: contain;
            pointer-events: none;
            z-index: 0;
            transform-origin: center center;
        }

        .zrx-stamp-layer {
            position: absolute;
            bottom: 3.5cm;
            right: 2.0cm;
            width: 150px;
            height: 150px;
            background-repeat: no-repeat;
            background-position: center center;
            background-size: contain;
            pointer-events: none;
            z-index: 5;
            transform-origin: center center;
        }

        .zrx-stamp-action-btn {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 20px;
            height: 20px;
            background: #ef4444;
            color: #ffffff;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 11px;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
            z-index: 10;
            line-height: 1;
            padding: 0;
            transition: background 0.15s ease;
        }
        .zrx-stamp-action-btn:hover {
            background: #dc2626;
        }

        .zrx-stamp-resize-handle {
            position: absolute;
            top: -6px;
            left: -6px;
            width: 14px;
            height: 14px;
            background: #3b82f6;
            border: 2px solid #ffffff;
            border-radius: 50%;
            cursor: nwse-resize;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
            z-index: 10;
            display: none;
        }

        .zrx-stamp-rotate-handle {
            position: absolute;
            bottom: -6px;
            right: -6px;
            width: 16px;
            height: 16px;
            background: #10b981;
            border: 2px solid #ffffff;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
            z-index: 10;
            display: none;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            line-height: 1;
            user-select: none;
        }

        .zrx-stamp-layer.zrx-active {
            outline: 2px dashed #3b82f6;
            outline-offset: 2px;
        }
        .zrx-stamp-layer.zrx-active .zrx-stamp-action-btn {
            display: flex;
        }
        .zrx-stamp-layer.zrx-active .zrx-stamp-resize-handle {
            display: block;
        }
        .zrx-stamp-layer.zrx-active .zrx-stamp-rotate-handle {
            display: flex;
        }

        @media print {
            .zrx-stamp-action-btn,
            .zrx-stamp-resize-handle,
            .zrx-stamp-rotate-handle {
                display: none !important;
            }
            .zrx-stamp-layer {
                outline: none !important;
            }
        }

        .zrx-print-header {
            height: <?= $headerHeight ?>cm;
            width: <?= preview_escape((string)($options['header_width'] ?? $pageWidth)) ?>cm;
            border-bottom: <?= ($options['dec_line_top_1'] ?? 'yes') === 'yes' ? '1px solid #000' : 'none' ?>;
            background: <?= $isImageBody ? 'transparent' : '#' . preview_escape((string)$options['bgcolor']) ?>;
            display: block;
        }

        .zrx-header-layout {
            display: <?= $isImageBody ? 'none' : 'flex' ?>;
            visibility: <?= (($options['display_header'] ?? 'yes') === 'no') ? 'hidden' : 'visible' ?>;
        }

        .zrx-patient-strip {
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-sizing: border-box;
            height: <?= $patientHeight ?>cm;
            width: <?= preview_escape((string)($options['pt_info_section_width'] ?? $pageWidth)) ?>cm;
            border-bottom: <?= ($options['dec_line_top_2'] ?? 'yes') === 'yes' ? '1px solid #000' : 'none' ?>;
            font-family: "<?= preview_escape((string)($options['pt_info_font'] ?? 'Times New Roman')) ?>", "Times New Roman", serif;
            font-size: <?= preview_escape((string)($options['pt_info_font_size'] ?? '12')) ?>pt;
        }

        .zrx-patient-table {
            width: <?= preview_escape((string)($options['pt_info_width'] ?? '90')) ?>%;
            margin-top: <?= preview_escape((string)($options['pt_info_margin_top'] ?? '0')) ?>px;
            margin-bottom: <?= preview_escape((string)($options['pt_info_margin_bottom'] ?? '0')) ?>px;
            visibility: <?= (($options['display_pt_info'] ?? 'yes') === 'no') ? 'hidden' : 'visible' ?>;
        }

        .zrx-body-left {
            height: <?= $leftHeight ?>cm;
            width: <?= $leftWidth ?>cm;
        }

        .zrx-body-right {
            height: <?= $rightHeight ?>cm;
            width: <?= $rightWidth ?>cm;
            border-left: <?= ($options['dec_line_left'] ?? 'yes') === 'yes' ? '1px solid #000' : 'none' ?>;
        }

        .zrx-print-footer {
            height: <?= $footerHeight ?>cm;
            width: <?= preview_escape((string)($options['footer_width'] ?? $pageWidth)) ?>cm;
            border-top: <?= ($options['dec_line_bottom'] ?? 'yes') === 'yes' ? '1px solid #000' : 'none' ?>;
            display: <?= $showFooter ? 'block' : 'none' ?>;
        }

        .zrx-left-content {
            margin-left: <?= preview_escape((string)($options['left_margin_left'] ?? '70')) ?>px;
            margin-top: <?= preview_escape((string)($options['left_margin_top'] ?? '0')) ?>px;
            width: calc(100% - <?= preview_escape((string)($options['left_margin_left'] ?? '70')) ?>px);
            font-family: "<?= preview_escape((string)($options['left_font'] ?? 'Times New Roman')) ?>", "Times New Roman", serif;
            font-size: <?= preview_escape((string)($options['left_font_size'] ?? '11')) ?>pt;
        }

        .zrx-left-content table td {
            line-height: <?= preview_escape((string)($options['left_line_height'] ?? '10')) ?>pt;
        }

        .zrx-rx-mark {
            visibility: <?= ($options['disp_rx'] ?? 'yes') === 'no' ? 'hidden' : 'visible' ?>;
        }

        .zrx-rx-symbol {
            margin-left: <?= $rxMarginLeft ?>px;
            margin-top: <?= preview_escape((string)($options['rx_block_margin_top'] ?? '7')) ?>px;
            font-family: "<?= preview_escape((string)($options['rx_font'] ?? 'Lucida Calligraphy')) ?>", "Times New Roman", serif;
            font-size: <?= $rxFontSize ?>pt;
        }

        .zrx-prescription-main {
            margin-left: <?= $presMainLeftMargin ?>px;
            margin-top: <?= preview_escape((string)($options['pres_main_margin_top'] ?? '10')) ?>px;
            width: calc(100% - <?= $presMainLeftMargin ?>px);
            font-family: "<?= preview_escape((string)($options['right_font'] ?? 'Times New Roman')) ?>", "<?= preview_escape((string)($options['bn_font'] ?? 'SolaimanLipi')) ?>", serif;
            font-size: <?= preview_escape((string)($options['right_font_size'] ?? '11')) ?>pt;
        }

        .zrx-drug-table td {
            line-height: <?= preview_escape((string)($options['pres_line_height'] ?? '11')) ?>pt;
        }

        .zrx-drug-gap-row,
        .zrx-drug-gap {
            height: <?= preview_escape((string)($options['pres_gap_height'] ?? '5')) ?>pt;
            line-height: <?= preview_escape((string)($options['pres_gap_height'] ?? '5')) ?>pt;
            font-size: <?= preview_escape((string)($options['pres_gap_height'] ?? '5')) ?>pt;
            padding: 0;
        }

        .zrx-drug-brand {
            font-family: "<?= preview_escape((string)($options['right_font'] ?? 'Times New Roman')) ?>", "Times New Roman", serif;
            font-size: <?= preview_escape((string)($options['right_font_size'] ?? '11')) ?>pt;
        }

        .zrx-drug-generic {
            display: <?= $showGeneric ? ((($options['generic_position'] ?? 'below') === 'below') ? 'block' : 'inline-block') : 'none' ?>;
            font-family: "<?= preview_escape((string)($options['generic_font'] ?? 'Times New Roman')) ?>", "Times New Roman", serif;
            font-size: <?= preview_escape((string)($options['generic_font_size'] ?? '10')) ?>pt;
            <?php
                $gStyle = (string)($options['generic_font_style'] ?? 'italic');
                if ($gStyle === 'italic' || $gStyle === 'italic-bold') echo "font-style: italic;\n";
                else echo "font-style: normal;\n";
                if ($gStyle === 'bold' || $gStyle === 'italic-bold') echo "            font-weight: bold;\n";
                else echo "            font-weight: normal;\n";
            ?>
            margin-left: calc(<?= preview_escape((string)($options['generic_margin_left'] ?? '0')) ?>px + <?= (($options['generic_position'] ?? 'below') === 'side') ? '5' : '0' ?>px);
            margin-top: <?= preview_escape((string)($options['generic_margin_top'] ?? '0')) ?>px;
        }

        .zrx-drug-dose,
        .zrx-drug-instruction,
        .zrx-drug-duration {
            font-family: "<?= preview_escape((string)($options['bn_font'] ?? 'SolaimanLipi')) ?>", "SolaimanLipi", serif;
            font-size: <?= preview_escape((string)($options['bn_font_size'] ?? '10.5')) ?>pt;
        }

        .zrx-drug-dose {
            padding-left: 0;
            text-indent: <?= preview_escape((string)($options['dose_lt_padding'] ?? '0')) ?>px;
        }

        .zrx-drug-number {
            width: <?= preview_escape((string)($options['dr_n_gap'] ?? '5')) ?>px;
        }

        .zrx-advice-table td {
            font-family: "<?= preview_escape((string)($options['upd_font'] ?? 'SolaimanLipi')) ?>", "<?= preview_escape((string)($options['right_font'] ?? 'Times New Roman')) ?>", serif;
            font-size: <?= $adviceFontSize ?>pt;
            line-height: <?= $adviceLineHeight ?>pt;
        }

        .zrx-text-pad {
            font-family: "<?= preview_escape((string)($options['right_font'] ?? 'Times New Roman')) ?>", "<?= preview_escape((string)($options['bn_font'] ?? 'SolaimanLipi')) ?>", serif;
            font-size: <?= preview_escape((string)($options['bn_font_size'] ?? '10.5')) ?>pt;
        }

        <?php if (($options['revisit_position'] ?? 'bottom') === 'top'): ?>
        .zrx-followup {
            position: relative;
            margin-left: <?= $presMainLeftMargin ?>px;
            text-align: left;
        }
        <?php else: ?>
        .zrx-followup {
            position: absolute;
            right: 15%;
            bottom: 2%;
            text-align: right;
        }
        <?php endif; ?>

        <?php for ($i = 1; $i <= 8; $i++): ?>
        .zrx-patient-label-slot-<?= $i ?> {
            <?php if (!empty($options['ttl_' . $i])): ?>
            width: <?= preview_escape((string)$options['ttl_' . $i]) ?>px;
            <?php endif; ?>
        }

        .zrx-patient-position-<?= $i ?> {
            padding-left: <?= preview_escape((string)($options['pos_' . $i . '_margin_left'] ?? '0')) ?>px;
            padding-top: <?= preview_escape((string)($options['pos_' . $i . '_margin_top'] ?? '0')) ?>px;
            <?php if (!empty($options['pos_' . $i . '_width'])): ?>
            min-width: <?= preview_escape((string)$options['pos_' . $i . '_width']) ?>px;
            <?php endif; ?>
            <?= ($i === 4 || $i === 8) ? 'text-align: right;' : '' ?>
        }
        <?php endfor; ?>
    </style>
</head>
<body>
<div class="zrx-print-page">
    <div id="preview-watermark-layer" class="zrx-watermark-layer" style="<?= ($bgImagePath !== '' && !$isImageBody) ? "background-image: url('" . preview_escape($bgImagePath) . "'); opacity: " . $bgImageOpacity . "; transform: translate(" . $bgImageOffsetX . "px, " . $bgImageOffsetY . "px) rotate(" . $bgImageAngle . "deg) scale(" . $bgImageScale . ");" : 'display:none;' ?>"></div>
    <?php
    $stampPath = trim((string)($options['stamp_path'] ?? ''));
    $stampOpacity = (float)($options['stamp_opacity'] ?? 1.0);
    $stampScale = (float)($options['stamp_scale'] ?? 1.0);
    $stampAngle = (float)($options['stamp_angle'] ?? 0.0);
    $stampOffsetX = (float)($options['stamp_offset_x'] ?? 0.0);
    $stampOffsetY = (float)($options['stamp_offset_y'] ?? 0.0);
    $stampColor = trim((string)($options['stamp_color'] ?? '#000000'));
    if ($stampColor === '') $stampColor = '#000000';
    $stampColorEnable = trim((string)($options['stamp_color_enable'] ?? 'no'));
    
    $isSvgStamp = (strtolower(pathinfo($stampPath, PATHINFO_EXTENSION)) === 'svg');
    $isColorEnabled = ($stampColorEnable === 'yes');
    
    $stampTransformStyle = '';
    $stampInnerStyle = '';
    if ($stampPath !== '') {
        $transform = "translate(" . $stampOffsetX . "px, " . $stampOffsetY . "px) rotate(" . $stampAngle . "deg) scale(" . $stampScale . ")";
        $stampTransformStyle = "display: block; transform: " . $transform . ";";
        if ($isSvgStamp && $isColorEnabled) {
            $stampInnerStyle = "opacity: " . $stampOpacity . "; background-image: none; -webkit-mask-image: url('" . preview_escape($stampPath) . "'); mask-image: url('" . preview_escape($stampPath) . "'); -webkit-mask-size: contain; mask-size: contain; -webkit-mask-repeat: no-repeat; mask-repeat: no-repeat; -webkit-mask-position: center; mask-position: center; background-color: " . preview_escape($stampColor) . ";";
        } else {
            $stampInnerStyle = "opacity: " . $stampOpacity . "; background-image: url('" . preview_escape($stampPath) . "'); -webkit-mask-image: none; mask-image: none; background-color: transparent;";
        }
    } else {
        $stampTransformStyle = 'display:none;';
    }
    ?>
    <div id="preview-stamp-layer" class="zrx-stamp-layer" style="<?= $stampTransformStyle ?>">
        <?php if ($stampPath !== ''): ?>
            <div id="preview-stamp-inner" style="width: 100%; height: 100%; background-repeat: no-repeat; background-position: center center; background-size: contain; position: absolute; top: 0; left: 0; z-index: 1; <?= $stampInnerStyle ?>"></div>
        <?php endif; ?>
    </div>
    <div id="pageHeader" class="zrx-print-header">
        <div class="zrx-header-layout <?= ($options['display_logo'] === 'yes' && $options['header_logo_url'] !== '') ? 'zrx-has-logo' : 'zrx-no-logo' ?>">
            <div class="zrx-header-text zrx-header-left" style="width: <?= preview_escape($options['header_left_width'] ?? ($options['display_logo'] === 'yes' ? '40' : '49')) ?>%;">
                <?= zimrx_bridge_visual_block_html($header, 'left', $leftHeaderLines) ?>
            </div>

            <?php if ($options['display_logo'] === 'yes' && $options['header_logo_url'] !== ''): ?>
                <div class="zrx-header-logo" style="width: <?= preview_escape($options['header_logo_width'] ?? '18') ?>%;">
                    <img src="<?= preview_escape((string)$options['header_logo_url']) ?>" alt="Header logo" style="transform: translate(<?= (float)($options['logo_offset_x'] ?? 0) ?>px, <?= (float)($options['logo_offset_y'] ?? 0) ?>px) rotate(<?= (float)($options['logo_rotation'] ?? 0) ?>deg) scale(<?= (float)($options['logo_scale'] ?? 100) / 100 ?>); opacity: <?= (float)($options['logo_opacity'] ?? 100) / 100 ?>;">
                </div>
            <?php endif; ?>

            <div class="zrx-header-text zrx-header-right" style="width: <?= preview_escape($options['header_right_width'] ?? ($options['display_logo'] === 'yes' ? '40' : '49')) ?>%;">
                <?= zimrx_bridge_visual_block_html($header, 'right', $rightHeaderLines) ?>
            </div>
        </div>
    </div>

    <div class="zrx-patient-strip">
        <?= zrx_patient_table($patient, $options) ?>
    </div>

    <div class="zrx-body-left">
        <?php if ($refByText !== ''): ?>
            <div id="preview-ref-by" class="zrx-ref-by"><b>Ref By:</b> <span id="preview-ref-by-val"><?= preview_escape($refByText) ?></span></div>
        <?php endif; ?>

        <div id="preview-barcode-wrap" style="<?= (($options['display_barcode'] ?? 'yes') === 'yes') ? '' : 'display:none;' ?>">
            <?= zrx_barcode_html($barcodePreviewText) ?>
        </div>

        <div id="preview-visit-no" class="zrx-visit-no" style="<?= (($options['visit_number'] ?? 'yes') === 'yes') ? '' : 'display:none;' ?>">
            Visit No: <span id="preview-visit-no-val"><?= preview_escape($visitNoText) ?></span>
        </div>

        <div class="zrx-left-content" id="preview-left-sections">
            <?= zrx_render_left_sections($clinical, $options) ?>
        </div>
    </div>

    <div class="zrx-body-right">
        <div class="zrx-rx-mark"><div class="zrx-rx-symbol">Rx.</div></div>

        <div class="zrx-prescription-main">
            <div class="zrx-drug-list">
                <table class="zrx-drug-table">
                    <tbody id="preview-drug-rows">
                        <?= zrx_render_drug_rows($clinical['drugs'] ?? [], $options) ?>
                    </tbody>
                </table>
            </div>

            <?php if ($textPad !== ''): ?>
                <div id="preview-text-pad-wrap" class="zrx-text-pad"><?= nl2br(preview_escape($textPad)) ?></div>
            <?php else: ?>
                <div id="preview-text-pad-wrap" class="zrx-text-pad" hidden></div>
            <?php endif; ?>

            <?= zrx_render_advice($clinical['advice'] ?? [], $options) ?>

            <?php if ($revisit !== ''): ?>
                <div id="preview-revisit-wrap" class="zrx-followup">
                    <u><b>Follow-up:</b></u><br>
                    <span id="preview-revisit-text"><?= nl2br(preview_escape($revisit)) ?></span>
                </div>
            <?php else: ?>
                <div id="preview-revisit-wrap" class="zrx-followup" hidden><span id="preview-revisit-text"></span></div>
            <?php endif; ?>
        </div>
    </div>

    <div id="preview-footer" class="zrx-print-footer">
        <?= $footerHtml ?>
    </div>
</div>

<script>
let previewOptions = <?= json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const previewDefaultData = <?= json_encode($sampleData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
let previewCurrentData = previewDefaultData;

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return decodeURIComponent(parts.pop().split(';').shift());
    return '';
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function cleanText(value) {
    if (Array.isArray(value)) {
        return value.map(cleanText).filter(Boolean).join(' ').trim();
    }
    return String(value ?? '').replace(/\s+/g, ' ').trim();
}

function cleanList(items) {
    return Array.isArray(items) ? items.map(cleanText).filter(Boolean) : [];
}

function isPlaceholderSidebarItem(text, slot) {
    const normalizedSlot = String(slot || '').toLowerCase();
    if (!['oh', 'mh'].includes(normalizedSlot)) return false;

    const normalized = cleanText(text);
    if (!normalized) return true;

    if (normalized.includes(':')) {
        const value = cleanText(normalized.split(':').slice(1).join(':'));
        return !value || /^(years?|months?|days?)$/i.test(value);
    }

    return /^(married for|alc|para|gravida|age of menarche|mp|mc|lmp|edd)$/i.test(normalized);
}

function cleanSidebarList(items, slot) {
    return cleanList(items).filter(item => !isPlaceholderSidebarItem(item, slot));
}

function fieldLabel(field) {
    const labels = {
        name: 'Name', age: 'Age', sex: 'Sex', date: 'Date',
        address: 'Address', regno: 'Reg No.', bmi_weigh: 'Wt',
        mobile: 'Mobile', ref_by: 'Ref By', visit_no: 'Visit No'
    };
    const optionKey = optionFieldKey(field);
    const configuredLabel = `patient_label_${optionKey}`;
    return Object.prototype.hasOwnProperty.call(previewOptions, configuredLabel)
        ? cleanText(previewOptions[configuredLabel])
        : (labels[field] || '');
}

function optionFieldKey(field) {
    if (field === 'bmi_weigh') return 'weight';
    if (field === 'regno') return 'reg_no';
    return field;
}

function patientValueKey(field) {
    return field === 'bmi_weigh' ? 'weight' : field;
}

function showPatientValue(field) {
    if (!field) return false;
    return (previewOptions[`display_${optionFieldKey(field)}`] || 'yes') === 'yes';
}

function showPatientLabel(field) {
    return (previewOptions[`display_${optionFieldKey(field)}_t`] || 'yes') === 'yes';
}

function patientOrderHtml(order, patient, singleRow) {
    const fieldOrder = {
        1: 'name',
        2: 'age',
        3: 'sex',
        4: 'date',
        5: 'address',
        6: 'regno',
        7: 'bmi_weigh',
        8: 'mobile'
    };
    const field = fieldOrder[order] || '';
    if (!showPatientValue(field)) return '';
    let value = cleanText(patient[patientValueKey(field)] || '');
    if (field === 'bmi_weigh' && /^(kg|kgs|kilogram|kilograms|lb|lbs)$/i.test(value)) {
        value = '';
    }

    const label = fieldLabel(field);
    let html = '';
    if (showPatientLabel(field) && label) {
        if (singleRow) {
            html += `<td class="zrx-patient-label zrx-patient-label-slot-${order}">${escapeHtml(label)} :</td>`;
        } else {
            html += `<td class="zrx-patient-label zrx-patient-label-slot-${order}">${escapeHtml(label)}</td><td class="zrx-patient-colon">:</td>`;
        }
    }
    const addressClass = field === 'address' ? ' zrx-patient-address-value' : '';
    const addressData = field === 'address' ? ` data-full-text="${escapeHtml(value)}"` : '';
    html += `<td id="preview-field-${order}" class="zrx-patient-value zrx-patient-position-${order}${addressClass}"${addressData}>${escapeHtml(value)}</td>`;
    return html;
}

function addressCandidates(value) {
    const text = cleanText(value);
    if (!text) return [''];

    const commaParts = text.split(',').map(cleanText).filter(Boolean);
    if (commaParts.length > 1) {
        const candidates = [];
        for (let count = commaParts.length; count >= 1; count -= 1) {
            candidates.push(commaParts.slice(0, count).join(', '));
        }
        return candidates;
    }

    const words = text.split(/\s+/).filter(Boolean);
    if (words.length <= 1) return [text];

    const candidates = [];
    for (let count = words.length; count >= 1; count -= 1) {
        candidates.push(words.slice(0, count).join(' '));
    }
    return candidates;
}

function textWidthForElement(text, element) {
    const style = window.getComputedStyle(element);
    const canvas = textWidthForElement.canvas || document.createElement('canvas');
    textWidthForElement.canvas = canvas;
    const context = canvas.getContext('2d');
    context.font = `${style.fontStyle} ${style.fontVariant} ${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
    return context.measureText(text).width;
}

function fitPatientAddresses() {
    document.querySelectorAll('.zrx-patient-address-value').forEach(cell => {
        const fullText = cleanText(cell.dataset.fullText || cell.textContent || '');
        if (!fullText) {
            cell.textContent = '';
            cell.removeAttribute('title');
            return;
        }

        cell.textContent = '';
        const style = window.getComputedStyle(cell);
        const padding = (parseFloat(style.paddingLeft) || 0) + (parseFloat(style.paddingRight) || 0);
        const availableWidth = Math.max(0, cell.getBoundingClientRect().width - padding - 1);
        const candidates = addressCandidates(fullText);
        const fitted = candidates.find(candidate => textWidthForElement(candidate, cell) <= availableWidth) || candidates[candidates.length - 1] || fullText;

        cell.textContent = fitted;
        if (fitted !== fullText) {
            cell.title = fullText;
        } else {
            cell.removeAttribute('title');
        }
    });
}

function schedulePatientAddressFit() {
    window.requestAnimationFrame(() => {
        window.requestAnimationFrame(fitPatientAddresses);
    });
}

function fitHistoryLabelGaps() {
    document.querySelectorAll('.zrx-history-label').forEach(label => {
        label.style.marginRight = '';
        const layoutWidth = label.offsetWidth;
        const visualWidth = label.getBoundingClientRect().width;
        const trimmedWidth = Math.max(0, layoutWidth - visualWidth);
        label.style.marginRight = trimmedWidth ? `${-trimmedWidth}px` : '';
    });
}

function scheduleHistoryLabelGapFit() {
    window.requestAnimationFrame(() => {
        window.requestAnimationFrame(fitHistoryLabelGaps);
    });
}

function renderPatientTable(patient) {
    const singleRow = String(previewOptions.info_row || '2') === '1';
    if (singleRow) {
        let row = '';
        for (let i = 1; i <= 8; i += 1) row += patientOrderHtml(i, patient, true);
        return `<table class="zrx-patient-table"><tbody><tr>${row}</tr></tbody></table>`;
    }

    let first = '';
    let second = '';
    for (let i = 1; i <= 4; i += 1) first += patientOrderHtml(i, patient, false);
    for (let i = 5; i <= 8; i += 1) second += patientOrderHtml(i, patient, false);
    return `<table class="zrx-patient-table"><tbody><tr>${first}</tr><tr>${second}</tr></tbody></table>`;
}

function sectionTable(title, rows, modifier) {
    if (!rows.length) return '';
    return `<div class="zrx-clinical-section ${modifier || ''}"><div class="zrx-section-title">${escapeHtml(title)}</div><table class="zrx-section-table"><tbody>${rows.join('')}</tbody></table></div>`;
}

function simpleSection(items, title, bullet, modifier, slot) {
    const pcFormat = previewOptions.pc_format || 'parentheses';
    const countUnits = ['episode', 'episodes', 'attack', 'attacks', 'time', 'times', 'occasion', 'occasions'];
    const rows = cleanSidebarList(items, slot).map(item => {
        let text = item;
        if (slot === 'pc') {
            const matches = item.match(/^(.+?)\s*\(([^)]+)\)$/);
            if (matches) {
                const complaint = matches[1].trim();
                const durationText = matches[2].trim();

                const durMatches = durationText.match(/^([\d\.\-\s০-৯]+)\s*(.+)$/);
                if (durMatches) {
                    const count = durMatches[1].trim();
                    const unit = durMatches[2].trim();
                    const unitLower = unit.toLowerCase();

                    const isCountUnit = countUnits.some(cu => unitLower.includes(cu));
                    if (isCountUnit) {
                        if (pcFormat === 'for') {
                            text = `${count} ${unit} of ${complaint}`;
                        } else if (pcFormat === 'hyphen') {
                            text = `${complaint} - ${count} ${unit}`;
                        } else {
                            text = `${complaint} (${count} ${unit})`;
                        }
                    } else {
                        if (pcFormat === 'for') {
                            text = `${complaint} for ${count} ${unit}`;
                        } else if (pcFormat === 'hyphen') {
                            text = `${complaint} - ${count} ${unit}`;
                        } else {
                            text = `${complaint} (${count} ${unit})`;
                        }
                    }
                } else {
                    if (pcFormat === 'for') {
                        text = `${complaint} for ${durationText}`;
                    } else if (pcFormat === 'hyphen') {
                        text = `${complaint} - ${durationText}`;
                    } else {
                        text = `${complaint} (${durationText})`;
                    }
                }
            }
        }
        return `<tr><td class="zrx-bullet-cell">${escapeHtml(bullet)}</td><td>${escapeHtml(text)}</td></tr>`;
    });
    return sectionTable(title, rows, modifier);
}

function renderDxSection(items, title) {
    const format = previewOptions.dx_format || 'per_line';
    let dxBullet = cleanText(previewOptions.dx_bullet || '');
    if (!dxBullet) {
        let bullet = cleanText(previewOptions.bullet_text || '');
        if (!bullet || bullet === 'â—‹') bullet = '○';
        dxBullet = bullet;
    }

    const cleaned = cleanSidebarList(items, 'dx').map(item => cleanText(item)).filter(Boolean);
    if (!cleaned.length) return '';

    let rows;
    if (format === 'single_line') {
        const line = cleaned.map(t => escapeHtml(t)).join(' ē ');
        rows = [`<tr><td class="zrx-bullet-cell">${escapeHtml(dxBullet)}</td><td>${line}</td></tr>`];
    } else {
        rows = cleaned.map(t => `<tr><td class="zrx-bullet-cell">${escapeHtml(dxBullet)}</td><td>${escapeHtml(t)}</td></tr>`);
    }

    return sectionTable(title, rows, 'zrx-clinical-section--dx');
}

function renderPeSection(items, title) {
    const rows = [];
    (Array.isArray(items) ? items : []).forEach(item => {
        const name = typeof item === 'object' && item ? cleanText(item.name || '') : '';
        const value = typeof item === 'object' && item ? cleanText(item.value || '') : cleanText(item);
        if (!name && !value) return;
        const colon = name && value ? ':' : '';
        rows.push(`<tr><td class="zrx-label-cell">${escapeHtml(name)}</td><td class="zrx-colon-cell">${colon}</td><td>${escapeHtml(value)}</td></tr>`);
    });
    return sectionTable(title, rows, 'zrx-clinical-section--pe zrx-clinical-section--oe');
}
const renderOeSection = renderPeSection;

function renderReportSection(items, title) {
    const rows = [];
    (Array.isArray(items) ? items : []).forEach(item => {
        if (!item || typeof item !== 'object') return;
        const name = cleanText(item.name || '');
        const date = cleanText(item.date || '');
        const value = cleanText(item.value || '');
        if (!name && !date && !value) return;
        const left = `${name ? `<b>${escapeHtml(name)}</b>` : ''}${date ? `<br><span class="zrx-report-date">${escapeHtml(date)}</span>` : ''}`;
        const right = value ? ` : ${escapeHtml(value)}` : '';
        rows.push(`<tr><td class="zrx-report-name">${left}</td><td class="zrx-report-value">${right}</td></tr>`);
    });
    return sectionTable(title, rows, 'zrx-clinical-section--reports');
}

function historyList(items) {
    if (!Array.isArray(items)) {
        const text = cleanText(items);
        return text ? [text] : [];
    }
    return items.map(item => {
        if (item && typeof item === 'object') {
            return cleanText(item.label || item.value || item.name || '');
        }
        return cleanText(item);
    }).filter(Boolean);
}

function historyTreatments(items) {
    if (!Array.isArray(items)) return [];
    return items.map(item => {
        if (item && typeof item === 'object') {
            const procedure = cleanText(item.procedure || item.name || '');
            const year = cleanText(item.year || '');
            if (!procedure && !year) return '';
            return procedure && year ? `${procedure} (${year})` : (procedure || year);
        }
        return cleanText(item);
    }).filter(Boolean);
}

function historyPayload(clinical) {
    const history = clinical && typeof clinical.history === 'object' && clinical.history ? clinical.history : {};
    let diet = cleanText(history.diet || '');
    if (diet === 'Standard / Normal') diet = 'Standard';

    return {
        medical: historyList(history.medical || clinical.ho || []),
        treatments: historyTreatments(history.treatments || []),
        habits: historyList(history.habits || []),
        diet,
        hypersensitivity: historyList(history.hypersensitivity || []),
        drug_history: historyList(history.drug_history || clinical.dh || [])
    };
}

function renderHistorySection(clinical, bullet) {
    const history = historyPayload(clinical || {});
    const submoduleHtml = {};

    const medicalLabel = previewOptions.lbl_history_medical || 'Medical History:';
    const treatmentsLabel = previewOptions.lbl_history_treatments || 'Treatment History:';
    const habitsLabel = previewOptions.lbl_history_habits || 'Habits:';
    const dietLabel = previewOptions.lbl_history_diet || 'Diet:';
    const hypersensitivityLabel = previewOptions.lbl_history_hypersensitivity || 'Hypersensitivity:';
    const drugLabel = previewOptions.lbl_history_drug || 'Drug History:';

    // 1. medical
    const medicalItems = history.medical || [];
    if (medicalItems.length) {
        submoduleHtml['medical'] = `<tr><td><span class="zrx-history-label zrx-history-label--medical">${escapeHtml(medicalLabel)}</span> ${escapeHtml(medicalItems.join(', '))}</td></tr>`;
    }

    // 2. treatment
    const treatmentItems = history.treatments || [];
    if (treatmentItems.length) {
        submoduleHtml['treatment'] = `<tr><td><span class="zrx-history-label zrx-history-label--treatments">${escapeHtml(treatmentsLabel)}</span> ${escapeHtml(treatmentItems.join(', '))}</td></tr>`;
    }

    // 3. habits
    const habitsItems = history.habits || [];
    if (habitsItems.length) {
        submoduleHtml['habits'] = `<tr><td><span class="zrx-history-label zrx-history-label--habits">${escapeHtml(habitsLabel)}</span> ${escapeHtml(habitsItems.join(', '))}</td></tr>`;
    }

    // 4. diet-hypersensitivity
    let dietHypersensitivityHtml = '';
    if (history.diet) {
        dietHypersensitivityHtml += `<tr><td><span class="zrx-history-label zrx-history-label--diet">${escapeHtml(dietLabel)}</span> ${escapeHtml(history.diet)}</td></tr>`;
    }
    if (history.hypersensitivity.length) {
        dietHypersensitivityHtml += `<tr><td><span class="zrx-history-label zrx-history-label--hypersensitivity">${escapeHtml(hypersensitivityLabel)}</span> ${escapeHtml(history.hypersensitivity.join(', '))}</td></tr>`;
    }
    if (dietHypersensitivityHtml) {
        submoduleHtml['diet-hypersensitivity'] = dietHypersensitivityHtml;
    }

    // 5. drug-history
    if (history.drug_history.length) {
        const drugLines = history.drug_history.map(drug => `<span class="zrx-history-bullet">&#9675; ${escapeHtml(drug)}</span>`).join('');
        submoduleHtml['drug-history'] = `<tr><td><span class="zrx-history-label zrx-history-label--drug-history">${escapeHtml(drugLabel)}</span>${drugLines}</td></tr>`;
    }

    // Sort according to layout
    let historyLayout = ['medical', 'treatment', 'habits', 'diet-hypersensitivity', 'drug-history'];
    const cookieVal = getCookie('zimrx_history_layout');
    if (cookieVal) {
        try {
            const decoded = JSON.parse(cookieVal);
            if (Array.isArray(decoded)) {
                historyLayout = decoded;
            }
        } catch(e) {}
    }

    const rows = [];
    historyLayout.forEach(subName => {
        if (subName !== '' && submoduleHtml[subName]) {
            rows.push(submoduleHtml[subName]);
        }
    });

    return sectionTable('History', rows, 'zrx-clinical-section--history');
}

function previewSectionTitle(title, fallback, fallbackAliases = []) {
    const cleaned = cleanText(title || '');
    const aliases = [fallback, ...fallbackAliases].map(alias => String(alias).toLowerCase());
    return !cleaned || aliases.includes(cleaned.toLowerCase()) ? fallback : cleaned;
}

function renderLeftSections(clinical) {
    const titles = {
        pc: previewSectionTitle(previewOptions.pc_name, 'Presenting Complaints', ['P/C', 'P/C']),
        ho: previewOptions.history_name || 'History',
        pe: previewSectionTitle(previewOptions.pe_name || previewOptions.oe_name, 'Physical Examination', ['P/E']),
        oe: previewSectionTitle(previewOptions.pe_name || previewOptions.oe_name, 'Physical Examination', ['P/E']),
        reports: previewOptions.report_name || 'Reports',
        dh: previewOptions.dh_name || 'D/H',
        plan: previewOptions.plan_name || 'Plan',
        advice: previewOptions.ix_name || 'Investigations',
        note: previewOptions.note_name || 'Note',
        oh: previewOptions.oh_name || 'O/H',
        mh: previewOptions.mh_name || 'M/H',
        paediatric: previewOptions.paediatric_name || 'Paediatric History',
        ph: previewOptions.ph_name || 'Paediatric History',
        dx: previewOptions.dx_name || 'Dx',
        edd: previewOptions.edd_name || 'OT Note',
        otnote: previewOptions.edd_name || 'OT Note'
    };

    let bullet = cleanText(previewOptions.bullet_text || '');
    if (!bullet || bullet === 'â—‹') bullet = '○';

    let html = '';
    let historyRendered = false;
    for (let i = 1; i <= 14; i += 1) {
        const slot = previewOptions[`print_pos_${i}`] || 'none';
        if (slot === 'none') continue;

        if (slot === 'ho' || slot === 'dh' || slot === 'history') {
            if (!historyRendered) {
                html += renderHistorySection(clinical, bullet);
                historyRendered = true;
            }
            continue;
        }

        let items = slot === 'advice' ? (clinical.ix || []) : (slot === 'edd' ? (clinical.edd || clinical.otnote || []) : (clinical[slot] || []));
        if (!Array.isArray(items) || !items.length) continue;

        if (slot === 'pe' || slot === 'oe') {
            html += renderPeSection(items, titles.pe || titles.oe);
        } else if (slot === 'reports') {
            html += renderReportSection(items, titles.reports);
        } else if (slot === 'dx') {
            html += renderDxSection(items, titles.dx || 'Dx');
        } else {
            html += simpleSection(items, titles[slot] || slot, bullet, `zrx-clinical-section--${slot.replace(/[^a-z0-9_-]/gi, '')}`, slot);
        }
    }
    return html;
}

function getPrintDrugValue(drug, keys, fallback = '') {
    for (const key of keys) {
        const value = cleanText(drug && drug[key]);
        if (value) return value;
    }
    return cleanText(fallback);
}

function getPrintDrugBrand(drug) {
    const isShort = String(previewOptions.suffix_prefix_usage || 'full') === 'short';
    const keys = isShort
        ? ['pres_new_upper', 'prescribe_brand_short', 'full_form_brand_name', 'prescribe_brand_full', 'brand_name', 'brand']
        : ['full_form_brand_name', 'prescribe_brand_full', 'pres_new_upper', 'prescribe_brand_short', 'brand_name', 'brand'];
    return getPrintDrugValue(drug, keys, drug && drug.brand);
}

function getPrintDrugGeneric(drug) {
    const format = String(previewOptions.print_generic_name_format || 'plain');
    const isShort = String(previewOptions.suffix_prefix_usage || 'full') === 'short';
    const plain = getPrintDrugValue(drug, ['generic_name', 'generic'], drug && drug.generic);

    if (format === 'prescribe') {
        const keys = isShort
            ? ['prescribe_generic_short', 'prescribe_generic_full', 'generic_name', 'generic']
            : ['prescribe_generic_full', 'prescribe_generic_short', 'generic_name', 'generic'];
        return getPrintDrugValue(drug, keys, plain);
    }

    if (format === 'labelled') {
        const keys = isShort
            ? ['labelled_generic_short', 'labelled_generic_full', 'generic_name', 'generic']
            : ['labelled_generic_full', 'labelled_generic_short', 'generic_name', 'generic'];
        return getPrintDrugValue(drug, keys, plain);
    }

    return plain;
}

function getPrintDrugNumberLabel(number) {
    const style = String(previewOptions.drug_no_style || 'period');
    if (style === 'round_brackets') return `(${number})`;
    if (style === 'closing_bracket') return `${number})`;
    if (style === 'square_brackets') return `[${number}]`;
    return `${number}.`;
}

function getPrintLanguageValue(item, field) {
    const language = String(previewOptions[`${field}_language`] || 'bengali');
    const preferred = cleanText(item?.[`${field}_${language}`] || '');
    return preferred || cleanText(item?.[field] || '');
}

function renderDrugRows(drugs) {
    const showGeneric = (previewOptions.disp_generic || 'yes') === 'yes';
    const showDrugNo = (previewOptions.display_drug_no || 'yes') !== 'no';
    let drugBullet = cleanText(previewOptions.drug_bullet || '');
    if (!drugBullet || drugBullet === 'â€¢') drugBullet = '•';

    let html = '';
    let counter = 1;
    let isFirstDrug = true;
    (Array.isArray(drugs) ? drugs : []).forEach(drug => {
        const brand = getPrintDrugBrand(drug);
        const generic = getPrintDrugGeneric(drug);
        const drugRowFormat = String(previewOptions.drug_row_format || 'standard');

        const dose = getPrintLanguageValue(drug, 'dose');
        const instruction = getPrintLanguageValue({
            ...drug,
            instruction: drug.instruction || drug.food || ''
        }, 'instruction');
        const duration = getPrintLanguageValue(drug, 'duration');
        if (!brand && !generic && !dose && !instruction && !duration) return;

        const isContinuation = (!brand && !generic);

        if (!isContinuation) {
            if (!isFirstDrug) {
                html += '<tr><td colspan="4" class="zrx-drug-gap">&nbsp;</td></tr>';
            }
            isFirstDrug = false;
        }

        const drugName = brand || generic;

        let numberHtml = '';
        if (!isContinuation) {
            numberHtml = showDrugNo ? `<td class="zrx-drug-number">${escapeHtml(getPrintDrugNumberLabel(counter))}</td>` : `<td class="zrx-drug-number zrx-drug-bullet">${escapeHtml(drugBullet)}</td>`;
        } else {
            numberHtml = `<td class="zrx-drug-number"></td>`;
        }

        const format = previewOptions.drug_row_format || 'standard';

        if (format === 'labelled') {
            const brandFontSize = (previewOptions.right_font_size || '11') + 'pt';
            const lblGeneric = previewOptions.lbl_generic || 'Generic Name:';
            const lblBrand = previewOptions.lbl_brand || 'Brand Name Recommendation:';
            const lblInstruction = previewOptions.lbl_instruction || 'Instruction:';
            html += `<tr class="zrx-drug-name-row">${numberHtml}<td colspan="3" class="zrx-drug-name-cell">`;
            if (generic) {
                html += `<div style="margin-bottom: 3px;"><span style="font-weight: bold; font-family: 'Times New Roman';" class="zrx-lbl-generic">${escapeHtml(lblGeneric)} </span><span class="zrx-drug-generic" data-generic="${escapeHtml(generic)}" style="font-family: 'Times New Roman', serif; font-style: normal; font-weight: normal; font-size: ${brandFontSize}; ${showGeneric ? 'display: inline-block;' : 'display: none;'}">${escapeHtml(generic)}</span></div>`;
            }
            if (brand) {
                html += `<div style="margin-bottom: 3px;"><span style="font-weight: bold; font-family: 'Times New Roman';" class="zrx-lbl-brand">${escapeHtml(lblBrand)} </span><span class="zrx-drug-brand" style="font-weight: normal;">${escapeHtml(brand)}</span></div>`;
            }
            if (dose || instruction || duration) {
                html += `<div><span style="${isContinuation ? 'visibility: hidden;' : ''} font-weight: bold; font-family: 'Times New Roman';" class="zrx-lbl-instruction">${escapeHtml(lblInstruction)} </span><span class="zrx-drug-dose">${escapeHtml(dose)}</span><span class="zrx-drug-instruction">${instruction ? '- ' + escapeHtml(instruction) : ''}</span><span class="zrx-drug-duration">${duration ? '- ' + escapeHtml(duration) : ''}</span></div>`;
            }
            html += `</td></tr>`;
        } else {
            if (!isContinuation) {
                html += `<tr class="zrx-drug-name-row">${numberHtml}`;
                html += `<td colspan="3" class="zrx-drug-name-cell"><span class="zrx-drug-brand">${escapeHtml(drugName)}</span>`;
                if (showGeneric && generic && generic !== drugName) {
                    let gFormatted = generic;
                    const wrapper = previewOptions.generic_wrapper || 'none';
                    if (wrapper === 'parentheses') gFormatted = '(' + generic + ')';
                    else if (wrapper === 'brackets') gFormatted = '[' + generic + ']';
                    else if (wrapper === 'hyphen') gFormatted = '- ' + generic;
                    const isBelow = (previewOptions.generic_position || 'below') === 'below';
                    html += ` <span class="zrx-drug-generic" data-generic="${escapeHtml(generic)}" style="display: ${isBelow ? 'block' : 'inline-block'};">${escapeHtml(gFormatted)}</span>`;
                }
                html += '</td></tr>';
            }

            if (dose || instruction || duration) {
                html += `<tr class="zrx-drug-detail-row"><td class="zrx-drug-detail-pad"></td><td class="zrx-drug-dose">${escapeHtml(dose)}</td><td class="zrx-drug-instruction">${instruction ? `- ${escapeHtml(instruction)}` : ''}</td><td class="zrx-drug-duration">${duration ? `- ${escapeHtml(duration)}` : ''}</td></tr>`;
            }
        }

        if (!isContinuation) {
            counter += 1;
        }
    });
    return html;
}

function renderAdvice(advice) {
    const language = String(previewOptions.advice_language || 'bengali');
    const items = (Array.isArray(advice) ? advice : [])
        .map((item) => typeof item === 'object'
            ? cleanText(item[language] || item.value || '')
            : cleanText(item))
        .filter(Boolean);
    if (!items.length) return '';
    return `<table class="zrx-advice-table"><tbody id="preview-advice-rows"><tr><td colspan="2"><u><b>&#x0989;&#x09AA;&#x09A6;&#x09C7;&#x09B6;&#x0983;</b></u></td></tr>${items.map(item => `<tr><td class="zrx-advice-bullet">▪</td><td>${escapeHtml(item)}</td></tr>`).join('')}</tbody></table>`;
}

function getStoredPreviewData() {
    const raw = sessionStorage.getItem('zimrx_preview_snapshot') || localStorage.getItem('zimrx_preview_snapshot');
    try { return raw ? JSON.parse(raw) : null; } catch (e) { return null; }
}

function setHtml(selector, html) {
    const node = document.querySelector(selector);
    if (node) node.innerHTML = html;
}

function toggleRef(patient) {
    const current = document.getElementById('preview-ref-by');
    const value = cleanText(patient.ref_by || '');
    if (!value) {
        if (current) current.remove();
        return;
    }
    if (current) {
        const span = document.getElementById('preview-ref-by-val');
        if (span) span.textContent = value;
        return;
    }
    document.querySelector('.zrx-body-left').insertAdjacentHTML('afterbegin', `<div id="preview-ref-by" class="zrx-ref-by"><b>Ref By:</b> <span id="preview-ref-by-val">${escapeHtml(value)}</span></div>`);
}

const code39Patterns = {
    '0': 'nnnwwnwnn', '1': 'wnnwnnnnw', '2': 'nnwwnnnnw', '3': 'wnwwnnnnn',
    '4': 'nnnwwnnnw', '5': 'wnnwwnnnn', '6': 'nnwwwnnnn', '7': 'nnnwnnwnw',
    '8': 'wnnwnnwnn', '9': 'nnwwnnwnn', 'A': 'wnnnnwnnw', 'B': 'nnwnnwnnw',
    'C': 'wnwnnwnnn', 'D': 'nnnnwwnnw', 'E': 'wnnnwwnnn', 'F': 'nnwnwwnnn',
    'G': 'nnnnnwwnw', 'H': 'wnnnnwwnn', 'I': 'nnwnnwwnn', 'J': 'nnnnwwwnn',
    'K': 'wnnnnnnww', 'L': 'nnwnnnnww', 'M': 'wnwnnnnwn', 'N': 'nnnnwnnww',
    'O': 'wnnnwnnwn', 'P': 'nnwnwnnwn', 'Q': 'nnnnnnwww', 'R': 'wnnnnnwwn',
    'S': 'nnwnnnwwn', 'T': 'nnnnwnwwn', 'U': 'wwnnnnnnw', 'V': 'nwwnnnnnw',
    'W': 'wwwnnnnnn', 'X': 'nwnnwnnnw', 'Y': 'wwnnwnnnn', 'Z': 'nwwnwnnnn',
    '-': 'nwnnnnwnw', '.': 'wwnnnnwnn', ' ': 'nwwnnnwnn', '$': 'nwnwnwnnn',
    '/': 'nwnwnnnwn', '+': 'nwnnnwnwn', '%': 'nnnwnwnwn', '*': 'nwnnwnwnn'
};

function barcodeHtml(value) {
    const clean = cleanText(value).toUpperCase().replace(/[^0-9A-Z\-. $/+%]/g, '');
    if (!clean) return '';
    const encoded = `*${clean}*`;

    const narrowWidth = 1.3;
    const wideWidth = 3.25;
    const barHeight = 32;
    const quietZone = 14;

    const charBlockWidth = (wideWidth * 3) + (narrowWidth * 6);
    const len = encoded.length;

    let rects = '';
    let texts = '';
    let x = quietZone;

    for (let c = 0; c < len; c += 1) {
        const char = encoded[c];
        const pattern = code39Patterns[char] || code39Patterns['-'];
        const charStartX = x;

        for (let i = 0; i < 9; i += 1) {
            const isBar = (i % 2 === 0);
            const width = (pattern[i] === 'w') ? wideWidth : narrowWidth;
            if (isBar) {
                rects += `<rect x="${x.toFixed(2)}" y="0" width="${width.toFixed(2)}" height="${barHeight}" fill="#000000"/>`;
            }
            x += width;
        }

        const charCenterX = charStartX + (charBlockWidth / 2);
        texts += `<text x="${charCenterX.toFixed(2)}" y="${barHeight + 14}" font-family="Consolas, 'Lucida Console', 'Courier New', monospace" font-size="13" font-weight="bold" fill="#000000" text-anchor="middle">${escapeHtml(char)}</text>`;

        x += narrowWidth;
    }

    const totalWidth = +(x + quietZone - narrowWidth).toFixed(2);
    const totalHeight = barHeight + 18;

    const svg = `<svg class="zrx-barcode-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${totalWidth} ${totalHeight}" width="${totalWidth}px" height="${totalHeight}px" shape-rendering="crispEdges">` +
        `<rect x="0" y="0" width="${totalWidth}" height="${totalHeight}" fill="#ffffff"/>` +
        rects +
        texts +
        `</svg>`;

    return `<div id="preview-barcode" class="zrx-barcode" data-code="${escapeHtml(clean)}">${svg}</div>`;
}

function toggleBarcode(patient) {
    const current = document.getElementById('preview-barcode');
    const value = cleanText(patient.regno || '');
    if ((previewOptions.display_barcode || 'yes') !== 'yes') {
        if (current) current.remove();
        return;
    }
    const html = barcodeHtml(value ? value.padStart(6, '0') : '0000000000');
    if (current) current.outerHTML = html;
    else document.querySelector('.zrx-left-content').insertAdjacentHTML('beforebegin', html);
}

function toggleVisitNo(patient) {
    const current = document.getElementById('preview-visit-no');
    const value = cleanText(patient.visit_no || '');
    if ((previewOptions.visit_number || 'yes') !== 'yes' || !value) {
        if (current) current.remove();
        return;
    }
    if (current) {
        const span = document.getElementById('preview-visit-no-val');
        if (span) span.textContent = value;
    } else {
        document.querySelector('.zrx-left-content').insertAdjacentHTML('beforebegin', `<div id="preview-visit-no" class="zrx-visit-no">Visit No: <span id="preview-visit-no-val">${escapeHtml(value)}</span></div>`);
    }
}

function applyPreviewData(data) {
    previewCurrentData = data && typeof data === 'object' ? data : previewDefaultData;
    const patient = previewCurrentData.patient || {};
    const clinical = previewCurrentData.clinical || {};

    setHtml('.zrx-patient-strip', renderPatientTable(patient));
    schedulePatientAddressFit();
    toggleRef(patient);
    toggleBarcode(patient);
    toggleVisitNo(patient);

    setHtml('#preview-left-sections', renderLeftSections(clinical));
    scheduleHistoryLabelGapFit();
    setHtml('#preview-drug-rows', renderDrugRows(clinical.drugs || []));

    const textPadWrap = document.getElementById('preview-text-pad-wrap');
    if (textPadWrap) {
        const text = String(clinical.text_pad || '').trim();
        textPadWrap.hidden = !text;
        textPadWrap.innerHTML = text ? escapeHtml(text).replace(/\n/g, '<br>') : '';
    }

    const adviceWrap = document.getElementById('preview-advice-wrap');
    const adviceHtml = renderAdvice(clinical.advice || []);
    if (adviceWrap) {
        adviceWrap.hidden = !adviceHtml;
        adviceWrap.innerHTML = adviceHtml;
    } else if (adviceHtml) {
        document.querySelector('.zrx-prescription-main').insertAdjacentHTML('beforeend', `<div id="preview-advice-wrap" class="zrx-advice-section">${adviceHtml}</div>`);
    }

    const revisitWrap = document.getElementById('preview-revisit-wrap');
    const revisitText = document.getElementById('preview-revisit-text');
    if (revisitWrap && revisitText) {
        const revisit = String(clinical.revisit || '').trim();
        revisitWrap.hidden = !revisit;
        revisitText.innerHTML = revisit ? escapeHtml(revisit).replace(/\n/g, '<br>') : '';
    }
}

const serverSnapshot = <?= $serverSnapshotJson ?: 'null' ?>;
const snapshot = serverSnapshot || getStoredPreviewData();
if (snapshot) {
    applyPreviewData(snapshot);
}
if (window.parent && window.parent !== window) {
    window.parent.postMessage({ type: 'PREVIEW_DOM_READY' }, window.location.origin);
}

window.addEventListener('message', event => {
    if (!event.data) {
        return;
    }

    if (event.data.type === 'SYNC_PREVIEW_DRUG_FORMAT') {
        previewOptions = { ...previewOptions, ...(event.data.options || {}) };
        setHtml('#preview-drug-rows', renderDrugRows((previewCurrentData.clinical || {}).drugs || []));
        return;
    }

    if (event.data.type === 'SYNC_PREVIEW_PATIENT_LABELS') {
        previewOptions = { ...previewOptions, ...(event.data.options || {}) };
        setHtml('.zrx-patient-strip', renderPatientTable(previewCurrentData.patient || {}));
        schedulePatientAddressFit();
        setHtml('#preview-left-sections', renderLeftSections(previewCurrentData.clinical || {}));
        scheduleHistoryLabelGapFit();
        return;
    }

    if (event.data.type !== 'SYNC_PREVIEW_DATA') {
        return;
    }

    applyPreviewData(event.data.data || {});

    const footer = document.getElementById('preview-footer');
    if (footer && event.data.data.footer_html !== undefined) {
        const html = String(event.data.data.footer_html || '').trim();
        footer.innerHTML = html;
        footer.style.display = html && (previewOptions.display_footer || 'yes') !== 'no' ? 'block' : 'none';
    }

    const headerEl = document.getElementById('pageHeader');
    if (headerEl && event.data.data.bgcolor) {
        headerEl.style.background = '#' + event.data.data.bgcolor;
    }
});

(function waitForPrescriptionFonts() {
    const root = document.documentElement;
    const reveal = (className) => {
        root.classList.remove('zrx-fonts-loading');
        root.classList.add(className);
    };

    if (!document.fonts || !document.fonts.ready) {
        reveal('zrx-fonts-ready');
        return;
    }

    let done = false;
    const finish = (className) => {
        if (done) return;
        done = true;
        fitPatientAddresses();
        fitHistoryLabelGaps();
        reveal(className);
        schedulePatientAddressFit();
        scheduleHistoryLabelGapFit();
    };

    window.setTimeout(() => finish('zrx-fonts-timeout'), 900);
    document.fonts.ready.then(() => {
        window.requestAnimationFrame(() => finish('zrx-fonts-ready'));
    }, () => finish('zrx-fonts-timeout'));
})();

// Interactive Stamp Dragging & Resizing/Deletion in Print Preview (for direct preview mode)
(function initStampDragging() {
    const stamp = document.getElementById('preview-stamp-layer');
    if (!stamp) return;

    // Disable ad-hoc actions and handles if inside layout setup iframe
    const isIframe = window.parent && window.parent !== window && window.parent.document.getElementById('zrx-gallery-overlay');
    if (isIframe) {
        return;
    }

    stamp.style.cursor = 'move';
    stamp.style.pointerEvents = 'auto';

    // Inject Action Buttons
    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.className = 'zrx-stamp-action-btn';
    deleteBtn.innerHTML = '✕';
    deleteBtn.title = 'Remove Stamp from this print';
    stamp.appendChild(deleteBtn);

    const resizeHandle = document.createElement('div');
    resizeHandle.className = 'zrx-stamp-resize-handle';
    resizeHandle.title = 'Drag to Resize Stamp';
    stamp.appendChild(resizeHandle);

    const rotateHandle = document.createElement('div');
    rotateHandle.className = 'zrx-stamp-rotate-handle';
    rotateHandle.innerHTML = '↻';
    rotateHandle.title = 'Drag to Rotate Stamp';
    stamp.appendChild(rotateHandle);

    // Click to delete/hide (ad-hoc)
    deleteBtn.addEventListener('mousedown', (e) => e.stopPropagation());
    deleteBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        stamp.style.display = 'none';
    });

    // Proportional drag to resize
    resizeHandle.addEventListener('mousedown', (e) => {
        e.preventDefault();
        e.stopPropagation();

        const startX = e.clientX;
        const startY = e.clientY;
        const rect = stamp.getBoundingClientRect();
        const startWidth = rect.width;

        const onMouseMove = (moveEvent) => {
            const dx = moveEvent.clientX - startX;
            const dy = moveEvent.clientY - startY;
            // Negate the sum since pulling top-left further up/left (negative directions) increases size
            const change = -(dx + dy) / 2;
            const newWidth = Math.max(30, Math.min(500, startWidth + change));

            stamp.style.width = newWidth + 'px';
            stamp.style.height = newWidth + 'px';
        };

        const onMouseUp = () => {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        };

        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    });

    // Drag to Rotate
    rotateHandle.addEventListener('mousedown', (e) => {
        e.preventDefault();
        e.stopPropagation();

        const rect = stamp.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;

        const startX = e.clientX;
        const startY = e.clientY;
        const startAngleRad = Math.atan2(startY - centerY, startX - centerX);
        const startAngleDeg = startAngleRad * (180 / Math.PI);
        const baseAngle = parseFloat(previewOptions.stamp_angle || '0.0');

        const onMouseMove = (moveEvent) => {
            const currentAngleRad = Math.atan2(moveEvent.clientY - centerY, moveEvent.clientX - centerX);
            const currentAngleDeg = currentAngleRad * (180 / Math.PI);
            
            let deltaAngle = currentAngleDeg - startAngleDeg;
            let newAngle = Math.round(baseAngle + deltaAngle);
            
            while (newAngle > 180) newAngle -= 360;
            while (newAngle < -180) newAngle += 360;

            previewOptions.stamp_angle = newAngle;

            const opacity = parseFloat(previewOptions.stamp_opacity || '1.0');
            const scale = parseFloat(previewOptions.stamp_scale || '1.0');
            const clampedX = parseFloat(previewOptions.stamp_offset_x || '0.0');
            const clampedY = parseFloat(previewOptions.stamp_offset_y || '0.0');
            stamp.style.transform = `translate(${clampedX}px, ${clampedY}px) rotate(${newAngle}deg) scale(${scale})`;
        };

        const onMouseUp = () => {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        };

        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    });

    // Move / Drag stamp position
    let isDragging = false;
    let startX = 0;
    let startY = 0;
    let startOffsetX = 0;
    let startOffsetY = 0;

    stamp.addEventListener('mousedown', (e) => {
        e.preventDefault();
        stamp.classList.add('zrx-active');
        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        startOffsetX = parseFloat(previewOptions.stamp_offset_x || '0.0');
        startOffsetY = parseFloat(previewOptions.stamp_offset_y || '0.0');

        const onMouseMove = (moveEvent) => {
            if (!isDragging) return;
            const dx = moveEvent.clientX - startX;
            const dy = moveEvent.clientY - startY;

            const newX = Math.round(startOffsetX + dx);
            const newY = Math.round(startOffsetY + dy);

            const clampedX = Math.max(-1200, Math.min(1200, newX));
            const clampedY = Math.max(-1200, Math.min(1200, newY));

            previewOptions.stamp_offset_x = clampedX;
            previewOptions.stamp_offset_y = clampedY;

            const opacity = parseFloat(previewOptions.stamp_opacity || '1.0');
            const scale = parseFloat(previewOptions.stamp_scale || '1.0');
            const angle = parseFloat(previewOptions.stamp_angle || '0.0');
            stamp.style.transform = `translate(${clampedX}px, ${clampedY}px) rotate(${angle}deg) scale(${scale})`;
        };

        const onMouseUp = () => {
            if (isDragging) {
                isDragging = false;
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
            }
        };

        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    });

    // Toggle active state on click
    stamp.addEventListener('click', (e) => {
        e.stopPropagation();
        stamp.classList.add('zrx-active');
    });

    document.addEventListener('click', (e) => {
        if (!stamp.contains(e.target)) {
            stamp.classList.remove('zrx-active');
        }
    });
})();
</script>
</body>
</html>
