<?php
require_once 'auth.php';
require_login();
require_once 'db.php';
require_once 'print_setup_lib.php';

$serverSnapshotJson = 'null';
$revisionId  = (int)($_GET['revision_id'] ?? 0);
$visitParam  = trim((string)($_GET['visit_id'] ?? $_GET['visit_record_id'] ?? ''));

$doctorId = current_user_doctor_id();
if ($doctorId <= 0) {
    http_response_code(403);
    exit;
}

if ($revisionId > 0) {
    try {
        // JOIN through zimrx_visits to verify ownership before returning data.
        $stmtR = $pdo->prepare(
            'SELECT r.clinical_snapshot_json, r.prescription_html
             FROM zimrx_visit_revisions r
             INNER JOIN zimrx_visits v ON v.id = r.visit_record_id AND v.doctor_id = :doctor_id
             WHERE r.id = :id
             LIMIT 1'
        );
        $stmtR->execute(['id' => $revisionId, 'doctor_id' => $doctorId]);
        $revRow = $stmtR->fetch(PDO::FETCH_ASSOC);
        if ($revRow && !empty($revRow['clinical_snapshot_json'])) {
            $serverSnapshotJson = json_encode(json_decode($revRow['clinical_snapshot_json'], true), JSON_UNESCAPED_UNICODE);
        }
    } catch (Throwable $e) {
        error_log('[ZimRx] Unable to load prescription revision preview: ' . $e->getMessage());
    }
} elseif ($visitParam !== '') {
    try {
        $stmtS = is_numeric($visitParam)
            ? $pdo->prepare('SELECT clinical_snapshot_json, prescription_html FROM zimrx_visits WHERE id = :id AND doctor_id = :doctor_id LIMIT 1')
            : $pdo->prepare('SELECT clinical_snapshot_json, prescription_html FROM zimrx_visits WHERE visit_id = :id AND doctor_id = :doctor_id LIMIT 1');
        $stmtS->execute(['id' => $visitParam, 'doctor_id' => $doctorId]);
        $visitRow = $stmtS->fetch(PDO::FETCH_ASSOC);
        if ($visitRow && !empty($visitRow['clinical_snapshot_json'])) {
            $serverSnapshotJson = json_encode(json_decode($visitRow['clinical_snapshot_json'], true), JSON_UNESCAPED_UNICODE);
        }
    } catch (Throwable $e) {
        error_log('[ZimRx] Unable to load prescription visit preview: ' . $e->getMessage());
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
        'breast_exam' => $options['breast_exam_name'] ?? 'Breast Examination',
        'local_exam' => $options['local_exam_name'] ?? 'Local Examination',
        'ophthalmology' => $options['ophthalmology_name'] ?? 'Ophthalmology',
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

<script id="previewOptionsData" type="application/json"><?= json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script id="previewDefaultDataJson" type="application/json"><?= json_encode($sampleData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script id="previewServerSnapshotData" type="application/json"><?= $serverSnapshotJson ?: "null" ?></script>
<script src="assets/js/layout/prescription_preview_render.js"></script>
</body>
</html>
