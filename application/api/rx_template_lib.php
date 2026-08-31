<?php
require_once __DIR__ . '/rx_regimen_lib.php';

function rx_template_config(string $type): array {
    $type = strtolower(trim($type));
    $configs = [
        'dose' => [
            'type' => 'dose',
            'title' => 'Dose Template',
            'static_table' => 'zimrx_static_doses',
            'user_table' => 'zimrx_user_drug_doses',
            'settings_table' => 'zimrx_user_drug_doses_settings',
            'settings_id_column' => 'dose_id',
            'bn_column' => 'dosage_bn',
            'en_column' => 'dosage_en',
            'label_bn' => 'Dose Bangla',
            'label_en' => 'Dose English',
            'has_default_form' => true,
        ],
        'duration' => [
            'type' => 'duration',
            'title' => 'Duration Template',
            'static_table' => 'zimrx_static_durations',
            'user_table' => 'zimrx_user_drug_durations',
            'settings_table' => 'zimrx_user_drug_durations_settings',
            'settings_id_column' => 'duration_id',
            'bn_column' => 'duration_bn',
            'en_column' => 'duration_en',
            'label_bn' => 'Duration Bangla',
            'label_en' => 'Duration English',
            'has_default_form' => true,
        ],
        'advice' => [
            'type' => 'advice',
            'title' => 'Advice Template',
            'static_table' => 'zimrx_static_advices',
            'user_table' => 'zimrx_user_advices',
            'settings_table' => 'zimrx_user_advices_settings',
            'settings_id_column' => 'advice_id',
            'bn_column' => 'body',
            'en_column' => 'advice_en',
            'label_bn' => 'Advice Bangla',
            'label_en' => 'Advice English',
            'has_default_form' => false,
        ],
    ];
    if (!isset($configs[$type])) {
        throw new InvalidArgumentException('Unknown template type.');
    }
    return $configs[$type];
}

function rx_phrase_default_settings(): array {
    return ['show_mode' => 'serial', 'show_custom_typed' => 1];
}

function rx_phrase_ensure_schema(string $type, ?PDO $userPdo = null): void {
    $config = rx_template_config($type);
    $userPdo = $userPdo ?: rx_user_pdo();
    $table = $config['user_table'];
    $bn = $config['bn_column'];
    $en = $config['en_column'];

    if ($type === 'advice') {
        $userPdo->exec(
            "CREATE TABLE IF NOT EXISTS {$table} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                doctor_id INTEGER NOT NULL DEFAULT 1,
                name TEXT NOT NULL DEFAULT '',
                body TEXT,
                usage_count INTEGER NOT NULL DEFAULT 0,
                is_pinned INTEGER NOT NULL DEFAULT 0,
                is_hidden INTEGER NOT NULL DEFAULT 0,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                static_id INTEGER NOT NULL DEFAULT 0,
                is_edited INTEGER NOT NULL DEFAULT 0
            )"
        );
    } else {
        $userPdo->exec(
            "CREATE TABLE IF NOT EXISTS {$table} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                doctor_id INTEGER NOT NULL DEFAULT 1,
                {$bn} TEXT,
                {$en} TEXT,
                usage_count INTEGER NOT NULL DEFAULT 0,
                is_pinned INTEGER NOT NULL DEFAULT 0,
                is_hidden INTEGER NOT NULL DEFAULT 0,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                static_id INTEGER NOT NULL DEFAULT 0,
                search_alias TEXT,
                default_dosage_form TEXT NOT NULL DEFAULT '[]',
                is_edited INTEGER NOT NULL DEFAULT 0
            )"
        );
    }

    $columns = [
        'doctor_id' => 'INTEGER NOT NULL DEFAULT 1',
        $bn => 'TEXT',
        $en => 'TEXT',
        'usage_count' => 'INTEGER NOT NULL DEFAULT 0',
        'is_pinned' => 'INTEGER NOT NULL DEFAULT 0',
        'is_hidden' => 'INTEGER NOT NULL DEFAULT 0',
        'sort_order' => 'INTEGER NOT NULL DEFAULT 0',
        'created_at' => 'TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'static_id' => 'INTEGER NOT NULL DEFAULT 0',
        'is_edited' => 'INTEGER NOT NULL DEFAULT 0',
    ];
    if ($type !== 'advice') {
        $columns['search_alias'] = 'TEXT';
    }
    if ($type === 'advice') {
        $columns['name'] = "TEXT NOT NULL DEFAULT ''";
        $columns['category_bn'] = "TEXT NOT NULL DEFAULT ''";
        $columns['category_en'] = "TEXT NOT NULL DEFAULT ''";
        $columns['category_search_alias'] = 'TEXT';
    }
    if ($config['has_default_form']) {
        $columns['default_dosage_form'] = "TEXT NOT NULL DEFAULT '[]'";
    }

    foreach ($columns as $column => $definition) {
        if (!DbSchema::columnExists($userPdo, $table, $column)) {
            $userPdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }

    $settingsTable = $config['settings_table'];
    $settingsIdColumn = $config['settings_id_column'];
    $userPdo->exec(
        "CREATE TABLE IF NOT EXISTS {$settingsTable} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL DEFAULT 1,
            {$settingsIdColumn} INTEGER NOT NULL DEFAULT 0,
            setting_key TEXT NOT NULL,
            setting_value TEXT,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(doctor_id, {$settingsIdColumn}, setting_key)
        )"
    );
    if (!DbSchema::columnExists($userPdo, $settingsTable, $settingsIdColumn)) {
        $userPdo->exec("ALTER TABLE {$settingsTable} ADD COLUMN {$settingsIdColumn} INTEGER NOT NULL DEFAULT 0");
    }
    $userPdo->exec(
        "CREATE UNIQUE INDEX IF NOT EXISTS idx_{$settingsTable}_doctor_setting
         ON {$settingsTable}(doctor_id, {$settingsIdColumn}, setting_key)"
    );

    $userPdo->exec("CREATE INDEX IF NOT EXISTS idx_{$table}_doctor_static ON {$table}(doctor_id, static_id)");
    $userPdo->exec("CREATE INDEX IF NOT EXISTS idx_{$table}_doctor_usage ON {$table}(doctor_id, usage_count DESC, sort_order ASC, id ASC)");
}

function rx_phrase_static_rows(string $type): array {
    $config = rx_template_config($type);
    $staticPdo = rx_static_pdo();
    if (!rx_table_exists($staticPdo, $config['static_table'])) {
        return [];
    }

    if ($type === 'advice') {
        $stmt = $staticPdo->query(
            "SELECT
                id,
                advice_bn AS body,
                advice_en,
                category_en AS name,
                category_bn,
                category_en,
                category_search_alias,
                sort_order,
                '[]' AS default_dosage_form
             FROM {$config['static_table']}
             ORDER BY sort_order ASC, id ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    $bn = $config['bn_column'];
    $en = $config['en_column'];
    $stmt = $staticPdo->query(
        "SELECT id, {$bn}, {$en}, search_alias, sort_order, default_dosage_form
         FROM {$config['static_table']}
         ORDER BY sort_order ASC, id ASC"
    );
    return $stmt->fetchAll() ?: [];
}

function rx_phrase_static_map(string $type): array {
    $map = [];
    foreach (rx_phrase_static_rows($type) as $row) {
        $map[(int)($row['id'] ?? 0)] = $row;
    }
    return $map;
}

function rx_phrase_settings(string $type, ?int $doctorId = null): array {
    $doctorId = $doctorId ?: rx_active_doctor_id();
    $config = rx_template_config($type);
    $userPdo = rx_user_pdo();
    rx_phrase_ensure_schema($type, $userPdo);
    $defaults = rx_phrase_default_settings();
    $settingsIdColumn = $config['settings_id_column'];

    $stmt = $userPdo->prepare(
        "SELECT setting_key, setting_value
         FROM {$config['settings_table']}
         WHERE doctor_id = :doctor_id
           AND {$settingsIdColumn} = 0
           AND setting_key IN ('show_mode', 'show_custom_typed')"
    );
    $stmt->execute(['doctor_id' => $doctorId]);
    $settings = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $settings[(string)$row['setting_key']] = (string)$row['setting_value'];
    }

    $showMode = rx_clean($settings['show_mode'] ?? $defaults['show_mode']);
    if (!in_array($showMode, ['serial', 'usage'], true)) {
        $showMode = $defaults['show_mode'];
    }

    return [
        'show_mode' => $showMode,
        'show_custom_typed' => (int)($settings['show_custom_typed'] ?? $defaults['show_custom_typed']) === 1 ? 1 : 0,
    ];
}

function rx_phrase_save_settings(PDO $userPdo, string $type, int $doctorId, array $settings): void {
    $config = rx_template_config($type);
    $settingsIdColumn = $config['settings_id_column'];
    $defaults = rx_phrase_default_settings();
    $showMode = rx_clean($settings['show_mode'] ?? $defaults['show_mode']);
    if (!in_array($showMode, ['serial', 'usage'], true)) {
        $showMode = $defaults['show_mode'];
    }
    $showCustomTyped = (int)($settings['show_custom_typed'] ?? $defaults['show_custom_typed']) === 1 ? 1 : 0;

    $stmt = $userPdo->prepare(
        "INSERT INTO {$config['settings_table']} (doctor_id, {$settingsIdColumn}, setting_key, setting_value, updated_at)
         VALUES (:doctor_id, 0, :setting_key, :setting_value, " . DbSql::now() . ")
         " . DbSql::upsert(
             "doctor_id, {$settingsIdColumn}, setting_key",
             ['setting_value', 'updated_at'],
             ['updated_at' => DbSql::now()]
         )
    );
    foreach (['show_mode' => $showMode, 'show_custom_typed' => (string)$showCustomTyped] as $key => $value) {
        $stmt->execute(['doctor_id' => $doctorId, 'setting_key' => $key, 'setting_value' => $value]);
    }
}

function rx_phrase_row_kind(array $row): string {
    $staticId = (int)($row['static_id'] ?? 0);
    if ($staticId === 99) {
        return 'added';
    }
    if ($staticId === 0) {
        return 'custom_typed';
    }
    return 'system';
}

function rx_phrase_rows(string $type, ?int $doctorId = null, bool $includeHidden = true): array {
    $doctorId = $doctorId ?: rx_active_doctor_id();
    $config = rx_template_config($type);
    $userPdo = rx_user_pdo();
    rx_phrase_ensure_schema($type, $userPdo);
    $settings = rx_phrase_settings($type, $doctorId);
    $bn = $config['bn_column'];
    $en = $config['en_column'];

    $stmt = $userPdo->prepare("SELECT * FROM {$config['user_table']} WHERE doctor_id = :doctor_id");
    $stmt->execute(['doctor_id' => $doctorId]);
    $userRows = $stmt->fetchAll() ?: [];
    $staticMap = rx_phrase_static_map($type);
    $byStatic = [];
    $extra = [];

    foreach ($userRows as $row) {
        $staticId = (int)($row['static_id'] ?? 0);
        if ($staticId > 0 && $staticId !== 99 && isset($staticMap[$staticId])) {
            $byStatic[$staticId] = $row;
        } else {
            $extra[] = $row;
        }
    }

    $rows = [];
    foreach (rx_phrase_static_rows($type) as $staticRow) {
        $staticId = (int)($staticRow['id'] ?? 0);
        $userRow = $byStatic[$staticId] ?? [];
        $row = [
            'id' => (int)($userRow['id'] ?? 0),
            'static_id' => $staticId,
            'doctor_id' => $doctorId,
            'usage_count' => (int)($userRow['usage_count'] ?? 0),
            $bn => rx_clean($userRow[$bn] ?? $staticRow[$bn] ?? ''),
            $en => rx_clean($userRow[$en] ?? $staticRow[$en] ?? ''),
            'is_pinned' => (int)($userRow['is_pinned'] ?? 0),
            'is_hidden' => (int)($userRow['is_hidden'] ?? 0),
            'sort_order' => (int)($userRow['sort_order'] ?? $staticRow['sort_order'] ?? 0),
            'default_dosage_form' => rx_clean($userRow['default_dosage_form'] ?? $staticRow['default_dosage_form'] ?? '[]'),
            'is_edited' => (int)($userRow['is_edited'] ?? 0),
            'kind' => 'system',
        ];
        if ($type === 'advice') {
            $row['category_bn'] = rx_clean($userRow['category_bn'] ?? $staticRow['category_bn'] ?? '');
            $row['category_en'] = rx_clean($userRow['category_en'] ?? $staticRow['category_en'] ?? $staticRow['name'] ?? '');
            $row['category_search_alias'] = rx_clean($userRow['category_search_alias'] ?? $staticRow['category_search_alias'] ?? '');
            $row['name'] = $row['category_en'];
        } else {
            $row['search_alias'] = rx_clean($userRow['search_alias'] ?? $staticRow['search_alias'] ?? '');
        }
        $row['value_bn'] = $row[$bn];
        $row['value_en'] = $row[$en];
        if (!$includeHidden && (int)$row['is_hidden'] === 1) {
            continue;
        }
        $rows[] = $row;
    }

    foreach ($extra as $row) {
        $item = [
            'id' => (int)($row['id'] ?? 0),
            'static_id' => (int)($row['static_id'] ?? 0),
            'doctor_id' => $doctorId,
            'usage_count' => (int)($row['usage_count'] ?? 0),
            $bn => rx_clean($row[$bn] ?? ''),
            $en => rx_clean($row[$en] ?? ''),
            'is_pinned' => (int)($row['is_pinned'] ?? 0),
            'is_hidden' => (int)($row['is_hidden'] ?? 0),
            'sort_order' => (int)($row['sort_order'] ?? 0),
            'default_dosage_form' => rx_clean($row['default_dosage_form'] ?? '[]'),
            'is_edited' => (int)($row['is_edited'] ?? 0),
        ];
        if ($type === 'advice') {
            $item['category_bn'] = rx_clean($row['category_bn'] ?? '');
            $item['category_en'] = rx_clean($row['category_en'] ?? $row['name'] ?? '');
            $item['category_search_alias'] = rx_clean($row['category_search_alias'] ?? '');
            $item['name'] = $item['category_en'];
        } else {
            $item['search_alias'] = rx_clean($row['search_alias'] ?? '');
        }
        $item['kind'] = rx_phrase_row_kind($item);
        $item['value_bn'] = $item[$bn];
        $item['value_en'] = $item[$en];
        if (!$includeHidden && (int)$item['is_hidden'] === 1) {
            continue;
        }
        if (!$includeHidden && $item['kind'] === 'custom_typed' && (int)$settings['show_custom_typed'] !== 1) {
            continue;
        }
        $rows[] = $item;
    }

    usort($rows, static function (array $a, array $b) use ($settings): int {
        $hiddenCmp = ((int)($a['is_hidden'] ?? 0)) <=> ((int)($b['is_hidden'] ?? 0));
        if ($hiddenCmp !== 0) return $hiddenCmp;
        $pinCmp = ((int)($b['is_pinned'] ?? 0)) <=> ((int)($a['is_pinned'] ?? 0));
        if ($pinCmp !== 0) return $pinCmp;
        if (($settings['show_mode'] ?? 'serial') === 'usage') {
            $usageCmp = ((int)($b['usage_count'] ?? 0)) <=> ((int)($a['usage_count'] ?? 0));
            if ($usageCmp !== 0) return $usageCmp;
        }
        return ((int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0))
            ?: strcmp(rx_norm($a['value_bn'] ?? ''), rx_norm($b['value_bn'] ?? ''));
    });

    return $rows;
}

function rx_phrase_matches(array $row, string $term): bool {
    $term = rx_norm($term);
    if ($term === '') return true;
    foreach (['value_bn', 'value_en', 'search_alias', 'category_bn', 'category_en', 'category_search_alias'] as $field) {
        if (!isset($row[$field])) continue;
        $value = rx_clean($row[$field] ?? '');
        if ($value !== '' && (mb_stripos($value, $term, 0, 'UTF-8') !== false || str_contains(rx_norm($value), $term))) {
            return true;
        }
    }
    return false;
}

function rx_phrase_suggestions_for_type(string $type, string $term = '', ?int $doctorId = null, int $limit = 100): array {
    $results = [];
    foreach (rx_phrase_rows($type, $doctorId, false) as $row) {
        if (!rx_phrase_matches($row, $term)) {
            continue;
        }
        $value = rx_clean($row['value_bn'] ?? '');
        if ($value === '') {
            continue;
        }
        $results[] = ['value' => $value, 'label' => $value] + $row;
        if (count($results) >= $limit) {
            break;
        }
    }
    return $results;
}

function rx_phrase_find_static_match(string $type, string $value): ?array {
    $needle = rx_norm($value);
    if ($needle === '') return null;
    foreach (rx_phrase_static_rows($type) as $row) {
        foreach (['value_bn', 'value_en', 'search_alias', 'category_bn', 'category_en', 'category_search_alias'] as $field) {
            $candidate = $row[$field] ?? null;
            if ($candidate === null) {
                $config = rx_template_config($type);
                if ($field === 'value_bn') {
                    $candidate = $row[$config['bn_column']] ?? '';
                } elseif ($field === 'value_en') {
                    $candidate = $row[$config['en_column']] ?? '';
                } else {
                    $candidate = $row[$field] ?? ($row['search_alias'] ?? '');
                }
            }
            if (rx_clean($candidate) !== '' && rx_norm($candidate) === $needle) {
                return $row;
            }
        }
    }
    return null;
}

function rx_phrase_learn(string $type, string $value, int $doctorId): void {
    $value = rx_clean($value);
    if ($value === '') return;

    $config = rx_template_config($type);
    $userPdo = rx_user_pdo();
    rx_phrase_ensure_schema($type, $userPdo);
    $table = $config['user_table'];
    $bn = $config['bn_column'];
    $en = $config['en_column'];
    $static = rx_phrase_find_static_match($type, $value);

    if ($static) {
        $staticId = (int)($static['id'] ?? 0);
        $stmt = $userPdo->prepare("SELECT id FROM {$table} WHERE doctor_id = :doctor_id AND static_id = :static_id LIMIT 1");
        $stmt->execute(['doctor_id' => $doctorId, 'static_id' => $staticId]);
        $existingId = (int)($stmt->fetchColumn() ?: 0);
        if ($existingId > 0) {
            $userPdo->prepare("UPDATE {$table} SET usage_count = usage_count + 1, updated_at = " . DbSql::now() . " WHERE id = :id")
                ->execute(['id' => $existingId]);
            return;
        }
        $defaultForm = $config['has_default_form'] ? rx_clean($static['default_dosage_form'] ?? '[]') : '[]';
        $columns = "doctor_id, static_id, {$bn}, {$en}, usage_count, sort_order, is_edited, created_at, updated_at";
        $values = ":doctor_id, :static_id, :bn, :en, 1, :sort_order, 0, " . DbSql::now() . ", " . DbSql::now();
        if ($type !== 'advice') {
            $columns .= ", search_alias";
            $values .= ", :search_alias";
        }
        if ($config['has_default_form']) {
            $columns .= ', default_dosage_form';
            $values .= ', :default_dosage_form';
        }
        $stmt = $userPdo->prepare(
            "INSERT INTO {$table} ({$columns}) VALUES ({$values})"
        );
    $params = [
        'doctor_id' => $doctorId,
        'static_id' => $staticId,
        'bn' => rx_clean($static[$bn] ?? $static['body'] ?? ''),
        'en' => rx_clean($static[$en] ?? $static['name'] ?? ''),
        'sort_order' => (int)($static['sort_order'] ?? 0),
    ];
    if ($type !== 'advice') {
        $params['search_alias'] = rx_clean($static['search_alias'] ?? '');
    }
        if ($type === 'advice') {
            $columns .= ', name, category_bn, category_en, category_search_alias';
            $values .= ', :name, :category_bn, :category_en, :category_search_alias';
            $params['name'] = rx_clean($static['category_en'] ?? $static['name'] ?? '');
            $params['category_bn'] = rx_clean($static['category_bn'] ?? '');
            $params['category_en'] = rx_clean($static['category_en'] ?? $static['name'] ?? '');
            $params['category_search_alias'] = rx_clean($static['category_search_alias'] ?? '');
        }
        if ($config['has_default_form']) {
            $params['default_dosage_form'] = $defaultForm;
        }
        $stmt->execute($params);
        return;
    }

    $stmt = $userPdo->prepare("SELECT id FROM {$table} WHERE doctor_id = :doctor_id AND static_id = 0 AND {$bn} = :value LIMIT 1");
    $stmt->execute(['doctor_id' => $doctorId, 'value' => $value]);
    $existingId = (int)($stmt->fetchColumn() ?: 0);
    if ($existingId > 0) {
        $userPdo->prepare("UPDATE {$table} SET usage_count = usage_count + 1, updated_at = " . DbSql::now() . " WHERE id = :id")
            ->execute(['id' => $existingId]);
        return;
    }

    $sortOrder = (int)$userPdo->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM {$table} WHERE doctor_id = " . (int)$doctorId)->fetchColumn();
    $columns = "doctor_id, static_id, {$bn}, {$en}, usage_count, sort_order, is_edited, created_at, updated_at";
    $values = ":doctor_id, 0, :bn, '', 1, :sort_order, 1, " . DbSql::now() . ", " . DbSql::now();
    if ($type !== 'advice') {
        $columns .= ", search_alias";
        $values .= ", ''";
    }
    $params = ['doctor_id' => $doctorId, 'bn' => $value, 'sort_order' => $sortOrder];
    if ($type === 'advice') {
        $columns = "doctor_id, static_id, {$bn}, {$en}, name, category_bn, category_en, category_search_alias, usage_count, sort_order, is_edited, created_at, updated_at";
        $values = ":doctor_id, 0, :bn, '', :name, :category_bn, :category_en, '', 1, :sort_order, 1, " . DbSql::now() . ", " . DbSql::now();
        $params['name'] = '';
        $params['category_bn'] = '';
        $params['category_en'] = '';
    }
    if ($config['has_default_form']) {
        $columns .= ', default_dosage_form';
        $values .= ", '[]'";
    }
    $stmt = $userPdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$values})");
    $stmt->execute($params);
}

