<?php
if (!defined('ZIMRX_DB_LIGHTWEIGHT')) {
    define('ZIMRX_DB_LIGHTWEIGHT', true);
}
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/drug_catalog_lib.php';

function rx_system_pdo(): PDO {
    return DbConnections::systemDb();
}

function rx_static_pdo(): PDO {
    return DbConnections::staticDb();
}

function rx_user_pdo(): PDO {
    return DbConnections::userdata();
}

function rx_table_exists(PDO $pdo, string $table): bool {
    return DbSchema::tableExists($pdo, $table);
}

function rx_clean($value): string {
    return trim((string)($value ?? ''));
}

function rx_norm($value): string {
    $value = rx_clean($value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return mb_strtolower($value, 'UTF-8');
}

function rx_norm_compact($value): string {
    return preg_replace('/[\s\.\-]+/u', '', rx_norm($value));
}

function rx_json_string_list($value): array {
    if (is_array($value)) {
        $items = $value;
    } else {
        $text = rx_clean($value);
        if ($text === '' || $text === '[]') {
            return [];
        }

        $decoded = json_decode($text, true);
        $items = is_array($decoded) ? $decoded : explode(',', $text);
    }

    $seen = [];
    $results = [];
    foreach ($items as $item) {
        $text = rx_clean($item);
        if ($text === '') {
            continue;
        }
        $key = rx_norm($text);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $results[] = $text;
    }
    return $results;
}

function rx_dosage_form_key($value): string {
    $key = preg_replace('/[^a-z0-9]+/u', '', mb_strtolower(rx_clean($value), 'UTF-8'));
    $map = [
        'tab' => 'tablet',
        'tabs' => 'tablet',
        'tablet' => 'tablet',
        'tablets' => 'tablet',
        'cap' => 'capsule',
        'caps' => 'capsule',
        'capsule' => 'capsule',
        'capsules' => 'capsule',
        'syp' => 'syrup',
        'syr' => 'syrup',
        'syrup' => 'syrup',
        'susp' => 'suspension',
        'suspension' => 'suspension',
        'inj' => 'injection',
        'injection' => 'injection',
        'drop' => 'drops',
        'drops' => 'drops',
        'oint' => 'ointment',
        'ointment' => 'ointment',
        'supp' => 'suppository',
        'suppository' => 'suppository',
    ];
    return $map[$key] ?? $key;
}

function rx_resolve_master(?string $catalogId = null, ?string $sourceBrandId = null, ?string $genericId = null): ?array {
    $pdo = rx_system_pdo();

    if (rx_clean($catalogId) !== '') {
        $row = drug_catalog_fetch_brand($pdo, rx_clean($catalogId));
        if ($row) {
            return $row;
        }
    }

    if (rx_clean($sourceBrandId) !== '') {
        $row = drug_catalog_fetch_brand($pdo, rx_clean($sourceBrandId));
        if ($row) {
            return $row;
        }
    }

    if (rx_clean($genericId) !== '') {
        $stmt = $pdo->prepare(
            "SELECT " . drug_catalog_select_sql() . "
             " . drug_catalog_from_sql() . "
             WHERE CAST(p.generic_id AS TEXT) = CAST(:generic_id AS TEXT)
             ORDER BY CAST(p.manufacturer_preference AS INTEGER) ASC,
                      CAST(p.form_order AS INTEGER) ASC,
                      p.brand_name ASC
             LIMIT 1"
        );
        $stmt->execute(['generic_id' => rx_clean($genericId)]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
    }

    return null;
}

function rx_context_from_request(array $data): array {
    $catalogId = rx_clean($data['brand_id'] ?? $data['catalog_id'] ?? '');
    $systemBrandId = rx_clean($data['system_brand_id'] ?? '');
    $genericId = rx_clean($data['generic_id'] ?? '');
    $master = rx_resolve_master($catalogId, $systemBrandId, $genericId);

    return [
        'catalog_id' => $master ? rx_clean($master['id']) : $catalogId,
        'brand_id' => $master ? rx_clean($master['brand_id']) : $systemBrandId,
        'generic_id' => $master ? rx_clean($master['generic_id']) : $genericId,
        'brand_name' => $master ? (rx_clean($master['pres_new_upper']) ?: rx_clean($master['full_form_brand_name'])) : rx_clean($data['brand'] ?? $data['brand_name'] ?? ''),
        'generic_name' => $master ? rx_clean($master['generic']) : rx_clean($data['generic'] ?? $data['generic_name'] ?? ''),
        'strength' => $master ? rx_clean($master['strength']) : rx_clean($data['strength'] ?? ''),
        'form' => $master ? rx_clean($master['form']) : rx_clean($data['form'] ?? ''),
    ];
}

function rx_regimen_payload(array $row): array {
    return [
        'dose' => rx_clean($row['dose'] ?? ''),
        'instruction' => rx_clean($row['instruction'] ?? ''),
        'duration' => rx_clean($row['duration'] ?? ''),
        'brand_id' => rx_clean($row['brand_id'] ?? ''),
        'catalog_id' => rx_clean($row['catalog_id'] ?? ''),
        'generic_id' => rx_clean($row['generic_id'] ?? ''),
        'brand_name' => rx_clean($row['brand_name'] ?? ''),
        'generic_name' => rx_clean($row['generic_name'] ?? ''),
        'strength' => rx_clean($row['strength'] ?? ''),
        'form' => rx_clean($row['form'] ?? ''),
    ];
}

function rx_json($payload): void {
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function rx_active_doctor_id(): int {
    return function_exists('current_user_doctor_id') ? current_user_doctor_id() : 1;
}

function rx_instruction_usage_table(PDO $userPdo): string {
    if (rx_table_exists($userPdo, 'zimrx_user_drug_instructionss')) {
        return 'zimrx_user_drug_instructionss';
    }
    if (rx_table_exists($userPdo, 'zimrx_user_instructions')) {
        return 'zimrx_user_instructions';
    }
    return 'zimrx_user_drug_instructions';
}

function rx_instruction_template_default_settings(): array {
    return [
        'show_mode' => 'serial',
        'show_custom_typed' => 1,
    ];
}

function rx_instruction_template_settings_table(): string {
    return 'zimrx_user_drug_instructionss_settings';
}

function rx_instruction_template_ensure_schema(?PDO $userPdo = null): void {
    $userPdo = $userPdo ?: rx_user_pdo();
    $instructionTable = rx_instruction_usage_table($userPdo);

    if ($instructionTable === 'zimrx_user_drug_instructionss' || $instructionTable === 'zimrx_user_instructions') {
        $userPdo->exec(
            "CREATE TABLE IF NOT EXISTS {$instructionTable} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usage_count INTEGER NOT NULL DEFAULT 0,
                static_id INTEGER,
                doctor_id INTEGER NOT NULL DEFAULT 1,
                instruction_en TEXT,
                instruction_bn TEXT,
                search_alias TEXT,
                is_pinned INTEGER NOT NULL DEFAULT 0,
                is_hidden INTEGER NOT NULL DEFAULT 0,
                sort_order INTEGER NOT NULL DEFAULT 0,
                default_dosage_form TEXT NOT NULL DEFAULT '[]',
                default_instruction_in_another_row INTEGER NOT NULL DEFAULT 0,
                is_edited INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );

        $columns = [
            'usage_count' => "INTEGER NOT NULL DEFAULT 0",
            'static_id' => 'INTEGER',
            'doctor_id' => "INTEGER NOT NULL DEFAULT 1",
            'instruction_en' => 'TEXT',
            'instruction_bn' => 'TEXT',
            'search_alias' => 'TEXT',
            'is_pinned' => "INTEGER NOT NULL DEFAULT 0",
            'is_hidden' => "INTEGER NOT NULL DEFAULT 0",
            'sort_order' => "INTEGER NOT NULL DEFAULT 0",
            'default_dosage_form' => "TEXT NOT NULL DEFAULT '[]'",
            'default_instruction_in_another_row' => "INTEGER NOT NULL DEFAULT 0",
            'is_edited' => "INTEGER NOT NULL DEFAULT 0",
            'created_at' => "TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP",
            'updated_at' => "TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP",
        ];

        foreach ($columns as $name => $definition) {
            if (!DbSchema::columnExists($userPdo, $instructionTable, $name)) {
                $userPdo->exec("ALTER TABLE {$instructionTable} ADD COLUMN {$name} {$definition}");
            }
        }

        $userPdo->exec("UPDATE {$instructionTable} SET doctor_id = 1 WHERE doctor_id IS NULL OR doctor_id <= 0");
        $userPdo->exec("UPDATE {$instructionTable} SET usage_count = 0 WHERE usage_count IS NULL");
        $userPdo->exec("UPDATE {$instructionTable} SET search_alias = '' WHERE search_alias IS NULL");
        $userPdo->exec("UPDATE {$instructionTable} SET default_dosage_form = '[]' WHERE COALESCE(default_dosage_form, '') = ''");
        $userPdo->exec("UPDATE {$instructionTable} SET default_instruction_in_another_row = 0 WHERE default_instruction_in_another_row IS NULL");
        $userPdo->exec("UPDATE {$instructionTable} SET static_id = 0 WHERE static_id IS NULL");
        $userPdo->exec("UPDATE {$instructionTable} SET is_edited = 1 WHERE static_id = 0 AND COALESCE(is_edited, 0) = 0");
        $userPdo->exec("CREATE INDEX IF NOT EXISTS idx_rx_instruction_doctor_static ON {$instructionTable}(doctor_id, static_id)");
        $userPdo->exec("CREATE INDEX IF NOT EXISTS idx_rx_instruction_doctor_usage ON {$instructionTable}(doctor_id, usage_count DESC, sort_order ASC, id ASC)");
    }

    $settingsTable = rx_instruction_template_settings_table();
    $userPdo->exec(
        "CREATE TABLE IF NOT EXISTS {$settingsTable} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL DEFAULT 1,
            instruction_id INTEGER NOT NULL DEFAULT 0,
            setting_key TEXT NOT NULL,
            setting_value TEXT,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(doctor_id, instruction_id, setting_key)
        )"
    );
}

function rx_static_instruction_rows(): array {
    static $rows = null;
    if ($rows !== null) {
        return $rows;
    }

    $staticPdo = rx_static_pdo();
    if (!rx_table_exists($staticPdo, 'zimrx_static_instructions')) {
        $rows = [];
        return $rows;
    }

    $stmt = $staticPdo->query(
        "SELECT id, instruction_en, instruction_bn, search_alias, sort_order, default_dosage_form, default_instruction_in_another_row
         FROM zimrx_static_instructions
         ORDER BY sort_order ASC, id ASC"
    );
    $rows = $stmt->fetchAll();
    return $rows;
}

function rx_static_instruction_map(): array {
    $map = [];
    foreach (rx_static_instruction_rows() as $row) {
        $map[(int)($row['id'] ?? 0)] = $row;
    }
    return $map;
}

function rx_instruction_template_settings(?int $doctorId = null): array {
    $doctorId = $doctorId ?: rx_active_doctor_id();
    $userPdo = rx_user_pdo();
    rx_instruction_template_ensure_schema($userPdo);

    $defaults = rx_instruction_template_default_settings();
    $table = rx_instruction_template_settings_table();
    $stmt = $userPdo->prepare(
        "SELECT setting_key, setting_value
         FROM {$table}
         WHERE doctor_id = :doctor_id
           AND instruction_id = 0
           AND setting_key IN ('show_mode', 'show_custom_typed')"
    );
    $stmt->execute(['doctor_id' => $doctorId]);
    $row = [];
    foreach ($stmt->fetchAll() as $settingRow) {
        $row[(string)($settingRow['setting_key'] ?? '')] = (string)($settingRow['setting_value'] ?? '');
    }

    $showMode = rx_clean($row['show_mode'] ?? $defaults['show_mode']);
    if (!in_array($showMode, ['serial', 'usage'], true)) {
        $showMode = $defaults['show_mode'];
    }

    return [
        'show_mode' => $showMode,
        'show_custom_typed' => (int)($row['show_custom_typed'] ?? $defaults['show_custom_typed']) === 1 ? 1 : 0,
    ];
}

function rx_instruction_template_row_kind(array $row): string {
    $staticId = (int)($row['static_id'] ?? 0);
    if ($staticId === 99) {
        return 'added';
    }
    if ($staticId === 0) {
        return 'custom_typed';
    }
    return 'system';
}

function rx_instruction_template_system_is_edited(array $row, array $staticRow): int {
    $rowForms = json_encode(rx_json_string_list($row['default_dosage_form'] ?? '[]'), JSON_UNESCAPED_UNICODE);
    $staticForms = json_encode(rx_json_string_list($staticRow['default_dosage_form'] ?? '[]'), JSON_UNESCAPED_UNICODE);
    $rowAnother = (int)($row['default_instruction_in_another_row'] ?? 0) === 1 ? 1 : 0;
    $staticAnother = (int)($staticRow['default_instruction_in_another_row'] ?? 0) === 1 ? 1 : 0;

    return (
        rx_norm($row['instruction_bn'] ?? '') !== rx_norm($staticRow['instruction_bn'] ?? '')
        || rx_norm($row['instruction_en'] ?? '') !== rx_norm($staticRow['instruction_en'] ?? '')
        || rx_norm($row['search_alias'] ?? '') !== rx_norm($staticRow['search_alias'] ?? '')
        || $rowForms !== $staticForms
        || $rowAnother !== $staticAnother
    ) ? 1 : 0;
}

function rx_instruction_template_rows(?int $doctorId = null, bool $includeHidden = true): array {
    $doctorId = $doctorId ?: rx_active_doctor_id();
    $userPdo = rx_user_pdo();
    rx_instruction_template_ensure_schema($userPdo);
    $settings = rx_instruction_template_settings($doctorId);
    $table = rx_instruction_usage_table($userPdo);
    $usageRows = [];

    if (rx_table_exists($userPdo, $table)) {
        $stmt = $userPdo->prepare(
            "SELECT id, usage_count, static_id, doctor_id, instruction_en, instruction_bn, search_alias,
                    is_pinned, is_hidden, sort_order, default_dosage_form, default_instruction_in_another_row,
                    is_edited, created_at, updated_at
             FROM {$table}
             WHERE doctor_id = :doctor_id"
        );
        $stmt->execute(['doctor_id' => $doctorId]);
        $usageRows = $stmt->fetchAll();
    }

    $staticMap = rx_static_instruction_map();
    $usageByStaticId = [];
    $extraRows = [];

    foreach ($usageRows as $row) {
        $staticId = (int)($row['static_id'] ?? 0);
        if ($staticId > 0 && $staticId !== 99 && isset($staticMap[$staticId])) {
            $usageByStaticId[$staticId] = $row;
            continue;
        }
        $extraRows[] = $row;
    }

    $results = [];
    foreach (rx_static_instruction_rows() as $staticRow) {
        $staticId = (int)($staticRow['id'] ?? 0);
        $userRow = $usageByStaticId[$staticId] ?? [];
        $merged = [
            'id' => (int)($userRow['id'] ?? 0),
            'static_id' => $staticId,
            'doctor_id' => $doctorId,
            'usage_count' => (int)($userRow['usage_count'] ?? 0),
            'instruction_en' => rx_clean($userRow['instruction_en'] ?? $staticRow['instruction_en'] ?? ''),
            'instruction_bn' => rx_clean($userRow['instruction_bn'] ?? $staticRow['instruction_bn'] ?? ''),
            'search_alias' => rx_clean($userRow['search_alias'] ?? $staticRow['search_alias'] ?? ''),
            'is_pinned' => (int)($userRow['is_pinned'] ?? 0),
            'is_hidden' => (int)($userRow['is_hidden'] ?? 0),
            'sort_order' => (int)($userRow['sort_order'] ?? $staticRow['sort_order'] ?? 0),
            'default_dosage_form' => rx_clean($userRow['default_dosage_form'] ?? $staticRow['default_dosage_form'] ?? '[]'),
            'default_instruction_in_another_row' => (int)($userRow['default_instruction_in_another_row'] ?? $staticRow['default_instruction_in_another_row'] ?? 0) === 1 ? 1 : 0,
            'is_edited' => (int)($userRow['is_edited'] ?? 0),
            'created_at' => (string)($userRow['created_at'] ?? ''),
            'updated_at' => (string)($userRow['updated_at'] ?? ''),
        ];
        $merged['kind'] = rx_instruction_template_row_kind($merged);
        $merged['is_edited'] = rx_instruction_template_system_is_edited($merged, $staticRow);

        if (!$includeHidden && $merged['is_hidden'] === 1) {
            continue;
        }

        $results[] = $merged;
    }

    foreach ($extraRows as $row) {
        $item = [
            'id' => (int)($row['id'] ?? 0),
            'static_id' => (int)($row['static_id'] ?? 0),
            'doctor_id' => $doctorId,
            'usage_count' => (int)($row['usage_count'] ?? 0),
            'instruction_en' => rx_clean($row['instruction_en'] ?? ''),
            'instruction_bn' => rx_clean($row['instruction_bn'] ?? ''),
            'search_alias' => rx_clean($row['search_alias'] ?? ''),
            'is_pinned' => (int)($row['is_pinned'] ?? 0),
            'is_hidden' => (int)($row['is_hidden'] ?? 0),
            'sort_order' => (int)($row['sort_order'] ?? 0),
            'default_dosage_form' => rx_clean($row['default_dosage_form'] ?? '[]'),
            'default_instruction_in_another_row' => (int)($row['default_instruction_in_another_row'] ?? 0) === 1 ? 1 : 0,
            'is_edited' => (int)($row['is_edited'] ?? 0),
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
        $item['kind'] = rx_instruction_template_row_kind($item);

        if (!$includeHidden && $item['is_hidden'] === 1) {
            continue;
        }
        if ($item['kind'] === 'custom_typed' && $settings['show_custom_typed'] !== 1 && !$includeHidden) {
            continue;
        }

        $results[] = $item;
    }

    usort($results, function (array $a, array $b) use ($settings): int {
        $hiddenCmp = ($a['is_hidden'] ?? 0) <=> ($b['is_hidden'] ?? 0);
        if ($hiddenCmp !== 0) {
            return $hiddenCmp;
        }

        $pinCmp = ($b['is_pinned'] ?? 0) <=> ($a['is_pinned'] ?? 0);
        if ($pinCmp !== 0) {
            return $pinCmp;
        }

        if (($settings['show_mode'] ?? 'serial') === 'usage') {
            $usageCmp = ($b['usage_count'] ?? 0) <=> ($a['usage_count'] ?? 0);
            if ($usageCmp !== 0) {
                return $usageCmp;
            }
        }

        return ($a['sort_order'] <=> $b['sort_order'])
            ?: strcmp(rx_norm($a['instruction_bn'] ?? ''), rx_norm($b['instruction_bn'] ?? ''));
    });

    return $results;
}

function rx_instruction_matches_term(array $row, string $term): bool {
    $term = rx_norm($term);
    if ($term === '') {
        return true;
    }

    $haystacks = [
        rx_clean($row['instruction_bn'] ?? ''),
        rx_clean($row['instruction_en'] ?? ''),
        rx_clean($row['search_alias'] ?? ''),
    ];

    foreach ($haystacks as $haystack) {
        if ($haystack !== '' && mb_stripos($haystack, $term, 0, 'UTF-8') !== false) {
            return true;
        }
        if ($haystack !== '' && str_contains(rx_norm($haystack), $term)) {
            return true;
        }
    }

    return false;
}

function rx_instruction_suggestions(string $term = '', ?int $doctorId = null, int $limit = 100): array {
    $doctorId = $doctorId ?: rx_active_doctor_id();
    $results = [];

    foreach (rx_instruction_template_rows($doctorId, false) as $row) {
        $item = $row + [
            'value' => rx_clean($row['instruction_bn'] ?? ''),
            'label' => rx_clean($row['instruction_bn'] ?? ''),
        ];

        if ($item['value'] === '' || !rx_instruction_matches_term($item, $term)) {
            continue;
        }

        $results[] = $item;
    }

    return array_slice($results, 0, $limit);
}

function rx_instruction_default_for_form(string $form, ?int $doctorId = null): ?array {
    $formKey = rx_dosage_form_key($form);
    if ($formKey === '') {
        return null;
    }

    $doctorId = $doctorId ?: rx_active_doctor_id();
    foreach (rx_instruction_template_rows($doctorId, false) as $row) {
        foreach (rx_json_string_list($row['default_dosage_form'] ?? '[]') as $candidateForm) {
            if (rx_dosage_form_key($candidateForm) === $formKey) {
                return $row;
            }
        }
    }

    return null;
}

function rx_find_static_instruction_match(string $value): ?array {
    $needle = rx_norm($value);
    if ($needle === '') {
        return null;
    }

    foreach (rx_static_instruction_rows() as $row) {
        $candidates = [
            rx_clean($row['instruction_bn'] ?? ''),
            rx_clean($row['instruction_en'] ?? ''),
            rx_clean($row['search_alias'] ?? ''),
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && rx_norm($candidate) === $needle) {
                return $row;
            }
        }
    }

    return null;
}
