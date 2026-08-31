<?php

function zimrx_user_drug_pdo(): PDO {
    $pdo = DbConnections::userdata();
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    zimrx_user_drug_ensure_schema($pdo);
    return $pdo;
}

function zimrx_user_drug_ensure_schema(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS zimrx_user_drug_hidden (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL DEFAULT 1,
            system_brand_id TEXT NOT NULL,
            brand_snapshot TEXT,
            is_active INTEGER NOT NULL DEFAULT 1,
            hidden_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            restored_at TEXT
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS zimrx_user_drug_override (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL DEFAULT 1,
            system_brand_id TEXT NOT NULL,
            local_drug_id TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS zimrx_user_drug_prescribe_index (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL DEFAULT 1,
            source_type TEXT NOT NULL DEFAULT 'custom',
            local_drug_id TEXT NOT NULL,
            system_brand_id TEXT,
            generic_id TEXT,
            brand_name TEXT NOT NULL,
            generic_name TEXT,
            manufacturer_name TEXT,
            strength TEXT,
            form TEXT,
            std_form TEXT,
            price TEXT,
            packsize TEXT,
            prescribe_brand_short TEXT,
            prescribe_brand_full TEXT,
            short_prescription TEXT,
            long_prescription TEXT,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uid_user_drug_hidden_brand ON zimrx_user_drug_hidden(doctor_id, system_brand_id)");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uid_user_drug_override_brand ON zimrx_user_drug_override(doctor_id, system_brand_id)");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uid_user_drug_local_id ON zimrx_user_drug_prescribe_index(local_drug_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_drug_brand_name ON zimrx_user_drug_prescribe_index(brand_name)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_drug_generic_name ON zimrx_user_drug_prescribe_index(generic_name)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_drug_system_brand_id ON zimrx_user_drug_prescribe_index(system_brand_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_drug_active ON zimrx_user_drug_prescribe_index(is_active, source_type)");
}

function zimrx_user_drug_clean_text($value): string {
    return trim((string)($value ?? ''));
}

function zimrx_user_drug_local_id(): string {
    return 'user_' . str_replace('.', '', uniqid('', true));
}

function zimrx_user_drug_default_short(array $data): string {
    return trim(implode(' ', array_filter([
        zimrx_user_drug_clean_text($data['brand_name'] ?? ''),
        zimrx_user_drug_clean_text($data['strength'] ?? ''),
    ])));
}

function zimrx_user_drug_default_long(array $data): string {
    return trim(implode(' ', array_filter([
        zimrx_user_drug_clean_text($data['brand_name'] ?? ''),
        zimrx_user_drug_clean_text($data['strength'] ?? ''),
        zimrx_user_drug_clean_text($data['form'] ?? ''),
    ])));
}

function zimrx_user_drug_save(array $data, int $doctorId = 1): array {
    $pdo = zimrx_user_drug_pdo();
    $sourceType = zimrx_user_drug_clean_text($data['source_type'] ?? 'custom') === 'override' ? 'override' : 'custom';
    $systemBrandId = zimrx_user_drug_clean_text($data['system_brand_id'] ?? '');
    $localDrugId = zimrx_user_drug_clean_text($data['local_drug_id'] ?? '');

    if ($sourceType === 'override' && $systemBrandId === '') {
        throw new InvalidArgumentException('Missing system brand id for edit.');
    }

    if ($sourceType === 'override') {
        $existing = zimrx_user_drug_fetch_index($systemBrandId, $doctorId);
        $localDrugId = $existing['local_drug_id'] ?? ($localDrugId ?: 'override_' . $systemBrandId);
    } elseif ($localDrugId === '') {
        $localDrugId = zimrx_user_drug_local_id();
    }

    $brandName = zimrx_user_drug_clean_text($data['brand_name'] ?? '');
    $genericName = zimrx_user_drug_clean_text($data['generic_name'] ?? $data['generic'] ?? '');
    if ($brandName === '' || $genericName === '') {
        throw new InvalidArgumentException('Brand and generic are required.');
    }

    $payload = [
        'doctor_id' => $doctorId,
        'source_type' => $sourceType,
        'local_drug_id' => $localDrugId,
        'system_brand_id' => $sourceType === 'override' ? $systemBrandId : '',
        'generic_id' => zimrx_user_drug_clean_text($data['generic_id'] ?? ($sourceType === 'custom' ? 'user_' . $localDrugId : '')),
        'brand_name' => $brandName,
        'generic_name' => $genericName,
        'manufacturer_name' => zimrx_user_drug_clean_text($data['manufacturer_name'] ?? $data['manufacturer'] ?? 'Custom'),
        'strength' => zimrx_user_drug_clean_text($data['strength'] ?? ''),
        'form' => zimrx_user_drug_clean_text($data['form'] ?? ''),
        'std_form' => zimrx_user_drug_clean_text($data['std_form'] ?? $data['form_new'] ?? $data['form'] ?? ''),
        'price' => zimrx_user_drug_clean_text($data['price'] ?? ''),
        'packsize' => zimrx_user_drug_clean_text($data['packsize'] ?? ''),
        'prescribe_brand_short' => zimrx_user_drug_clean_text($data['short_prescription'] ?? $data['prescribe_brand_short'] ?? ''),
        'prescribe_brand_full' => zimrx_user_drug_clean_text($data['long_prescription'] ?? $data['prescribe_brand_full'] ?? ''),
        'short_prescription' => zimrx_user_drug_clean_text($data['short_prescription'] ?? ''),
        'long_prescription' => zimrx_user_drug_clean_text($data['long_prescription'] ?? ''),
    ];

    if ($payload['prescribe_brand_short'] === '') {
        $payload['prescribe_brand_short'] = zimrx_user_drug_default_short($payload);
    }
    if ($payload['prescribe_brand_full'] === '') {
        $payload['prescribe_brand_full'] = zimrx_user_drug_default_long($payload);
    }
    if ($payload['short_prescription'] === '') {
        $payload['short_prescription'] = $payload['prescribe_brand_short'];
    }
    if ($payload['long_prescription'] === '') {
        $payload['long_prescription'] = $payload['prescribe_brand_full'];
    }

    $stmt = $pdo->prepare(
        "INSERT INTO zimrx_user_drug_prescribe_index (
            doctor_id, source_type, local_drug_id, system_brand_id, generic_id,
            brand_name, generic_name, manufacturer_name, strength, form, std_form,
            price, packsize, prescribe_brand_short, prescribe_brand_full,
            short_prescription, long_prescription, is_active, updated_at
        ) VALUES (
            :doctor_id, :source_type, :local_drug_id, :system_brand_id, :generic_id,
            :brand_name, :generic_name, :manufacturer_name, :strength, :form, :std_form,
            :price, :packsize, :prescribe_brand_short, :prescribe_brand_full,
            :short_prescription, :long_prescription, 1, CURRENT_TIMESTAMP
        )
        ON CONFLICT(local_drug_id) DO UPDATE SET
            source_type = excluded.source_type,
            system_brand_id = excluded.system_brand_id,
            generic_id = excluded.generic_id,
            brand_name = excluded.brand_name,
            generic_name = excluded.generic_name,
            manufacturer_name = excluded.manufacturer_name,
            strength = excluded.strength,
            form = excluded.form,
            std_form = excluded.std_form,
            price = excluded.price,
            packsize = excluded.packsize,
            prescribe_brand_short = excluded.prescribe_brand_short,
            prescribe_brand_full = excluded.prescribe_brand_full,
            short_prescription = excluded.short_prescription,
            long_prescription = excluded.long_prescription,
            is_active = 1,
            updated_at = CURRENT_TIMESTAMP"
    );
    $stmt->execute($payload);

    if ($sourceType === 'override') {
        $override = $pdo->prepare(
            "INSERT INTO zimrx_user_drug_override (doctor_id, system_brand_id, local_drug_id, updated_at)
             VALUES (:doctor_id, :system_brand_id, :local_drug_id, CURRENT_TIMESTAMP)
             ON CONFLICT(doctor_id, system_brand_id) DO UPDATE SET
                local_drug_id = excluded.local_drug_id,
                updated_at = CURRENT_TIMESTAMP"
        );
        $override->execute([
            'doctor_id' => $doctorId,
            'system_brand_id' => $systemBrandId,
            'local_drug_id' => $localDrugId,
        ]);
    }

    return zimrx_user_drug_index_to_catalog_row(zimrx_user_drug_fetch_index($localDrugId, $doctorId) ?: []);
}

function zimrx_user_drug_fetch_index(string $id, int $doctorId = 1): ?array {
    $pdo = zimrx_user_drug_pdo();
    $stmt = $pdo->prepare(
        "SELECT *
         FROM zimrx_user_drug_prescribe_index
         WHERE doctor_id = :doctor_id
           AND is_active = 1
           AND (local_drug_id = :id OR system_brand_id = :id)
         ORDER BY CASE WHEN local_drug_id = :id THEN 0 ELSE 1 END
         LIMIT 1"
    );
    $stmt->execute(['doctor_id' => $doctorId, 'id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function zimrx_user_drug_hidden_ids(int $doctorId = 1): array {
    $pdo = zimrx_user_drug_pdo();
    $stmt = $pdo->prepare("SELECT system_brand_id FROM zimrx_user_drug_hidden WHERE doctor_id = :doctor_id AND is_active = 1");
    $stmt->execute(['doctor_id' => $doctorId]);
    return array_fill_keys(array_map('strval', array_column($stmt->fetchAll(), 'system_brand_id')), true);
}

function zimrx_user_drug_override_ids(int $doctorId = 1): array {
    $pdo = zimrx_user_drug_pdo();
    $stmt = $pdo->prepare("SELECT system_brand_id FROM zimrx_user_drug_override WHERE doctor_id = :doctor_id");
    $stmt->execute(['doctor_id' => $doctorId]);
    return array_fill_keys(array_map('strval', array_column($stmt->fetchAll(), 'system_brand_id')), true);
}

function zimrx_user_manufacturer_preferences(int $doctorId = 1): array {
    static $cache = [];
    if (isset($cache[$doctorId])) {
        return $cache[$doctorId];
    }
    $pdo = zimrx_user_drug_pdo();
    $stmt = $pdo->prepare("SELECT manufacturer_name, manufacturer_id, sort_order, is_hidden FROM zimrx_user_manufacturer_sorting WHERE doctor_id = :doc");
    $stmt->execute(['doc' => $doctorId]);
    $prefs = ['ranks' => [], 'hidden_names' => [], 'hidden_ids' => []];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $name = trim((string)$r['manufacturer_name']);
        $mid = (string)$r['manufacturer_id'];
        $so = (int)$r['sort_order'];
        $hide = (int)($r['is_hidden'] ?? 0);
        if ($hide === 1) {
            if ($name !== '') $prefs['hidden_names'][strtolower($name)] = true;
            if ($mid !== '') $prefs['hidden_ids'][$mid] = true;
        } elseif ($so > 0) {
            if ($name !== '') $prefs['ranks'][strtolower($name)] = $so;
            if ($mid !== '') $prefs['ranks']['id_' . $mid] = $so;
        }
    }
    return $cache[$doctorId] = $prefs;
}

function zimrx_user_drug_filter_system_rows(array $rows, int $doctorId = 1): array {
    $hidden = zimrx_user_drug_hidden_ids($doctorId);
    $overrides = zimrx_user_drug_override_ids($doctorId);
    $manPrefs = zimrx_user_manufacturer_preferences($doctorId);

    $filtered = array_values(array_filter($rows, static function (array $row) use ($hidden, $overrides, $manPrefs): bool {
        $id = (string)($row['brand_id'] ?? $row['id'] ?? '');
        if ($id !== '' && (isset($hidden[$id]) || isset($overrides[$id]))) {
            return false;
        }
        $manName = strtolower(trim((string)($row['manufacturer'] ?? $row['manufacturer_name'] ?? '')));
        if ($manName !== '' && isset($manPrefs['hidden_names'][$manName])) {
            return false;
        }
        $mid = (string)($row['manufacturer_id'] ?? '');
        if ($mid !== '' && isset($manPrefs['hidden_ids'][$mid])) {
            return false;
        }
        return true;
    }));

    if (!empty($manPrefs['ranks'])) {
        usort($filtered, static function (array $a, array $b) use ($manPrefs): int {
            $mNameA = strtolower(trim((string)($a['manufacturer'] ?? $a['manufacturer_name'] ?? '')));
            $mNameB = strtolower(trim((string)($b['manufacturer'] ?? $b['manufacturer_name'] ?? '')));
            $rankA = $manPrefs['ranks'][$mNameA] ?? ($manPrefs['ranks']['id_' . ($a['manufacturer_id'] ?? '')] ?? 9999);
            $rankB = $manPrefs['ranks'][$mNameB] ?? ($manPrefs['ranks']['id_' . ($b['manufacturer_id'] ?? '')] ?? 9999);
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }
            return 0;
        });
    }

    return $filtered;
}

function zimrx_user_drug_search_rows(string $query = '', int $limit = 30, int $doctorId = 1): array {
    $pdo = zimrx_user_drug_pdo();
    $query = trim($query);
    if ($query === '') {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM zimrx_user_drug_prescribe_index
             WHERE doctor_id = :doctor_id AND is_active = 1
             ORDER BY updated_at DESC, brand_name ASC
             LIMIT " . max(1, (int)$limit)
        );
        $stmt->execute(['doctor_id' => $doctorId]);
    } else {
        $like = '%' . $query . '%';
        $stmt = $pdo->prepare(
            "SELECT *
             FROM zimrx_user_drug_prescribe_index
             WHERE doctor_id = :doctor_id
               AND is_active = 1
               AND (
                    brand_name LIKE :q OR generic_name LIKE :q
                    OR prescribe_brand_short LIKE :q OR prescribe_brand_full LIKE :q
               )
             ORDER BY
                CASE
                    WHEN brand_name LIKE :qStart THEN 0
                    WHEN prescribe_brand_short LIKE :qStart THEN 0
                    WHEN generic_name LIKE :qStart THEN 1
                    ELSE 2
                END,
                updated_at DESC,
                brand_name ASC
             LIMIT " . max(1, (int)$limit)
        );
        $stmt->execute([
            'doctor_id' => $doctorId,
            'q' => $like,
            'qStart' => $query . '%',
        ]);
    }

    return array_map('zimrx_user_drug_index_to_catalog_row', $stmt->fetchAll() ?: []);
}

function zimrx_user_drug_custom_rows(string $query = '', int $doctorId = 1): array {
    $pdo = zimrx_user_drug_pdo();
    $query = trim($query);
    $sql = "SELECT *
            FROM zimrx_user_drug_prescribe_index
            WHERE doctor_id = :doctor_id
              AND source_type = 'custom'
              AND is_active = 1";
    $params = ['doctor_id' => $doctorId];

    if ($query !== '') {
        $sql .= " AND (
                    brand_name LIKE :q OR generic_name LIKE :q
                    OR prescribe_brand_short LIKE :q OR prescribe_brand_full LIKE :q
                  )";
        $params['q'] = '%' . $query . '%';
    }

    $sql .= " ORDER BY brand_name COLLATE NOCASE ASC, strength COLLATE NOCASE ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return array_map('zimrx_user_drug_index_to_catalog_row', $stmt->fetchAll() ?: []);
}

function zimrx_user_drug_override_rows(string $query = '', int $doctorId = 1): array {
    $pdo = zimrx_user_drug_pdo();
    $query = trim($query);
    $sql = "SELECT *
            FROM zimrx_user_drug_prescribe_index
            WHERE doctor_id = :doctor_id
              AND source_type = 'override'
              AND is_active = 1";
    $params = ['doctor_id' => $doctorId];

    if ($query !== '') {
        $sql .= " AND (
                    brand_name LIKE :q OR generic_name LIKE :q
                    OR prescribe_brand_short LIKE :q OR prescribe_brand_full LIKE :q
                  )";
        $params['q'] = '%' . $query . '%';
    }

    $sql .= " ORDER BY updated_at DESC, brand_name COLLATE NOCASE ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return array_map('zimrx_user_drug_index_to_catalog_row', $stmt->fetchAll() ?: []);
}

function zimrx_user_drug_remove_override(string $systemBrandId, int $doctorId = 1): void {
    $pdo = zimrx_user_drug_pdo();
    $stmt = $pdo->prepare(
        "UPDATE zimrx_user_drug_prescribe_index
         SET is_active = 0, updated_at = CURRENT_TIMESTAMP
         WHERE doctor_id = :doctor_id
           AND source_type = 'override'
           AND system_brand_id = :system_brand_id"
    );
    $stmt->execute(['doctor_id' => $doctorId, 'system_brand_id' => $systemBrandId]);

    $stmt = $pdo->prepare(
        "DELETE FROM zimrx_user_drug_override
         WHERE doctor_id = :doctor_id AND system_brand_id = :system_brand_id"
    );
    $stmt->execute(['doctor_id' => $doctorId, 'system_brand_id' => $systemBrandId]);
}

function zimrx_user_drug_merge_search(array $systemRows, string $query = '', int $limit = 50, int $doctorId = 1): array {
    $rows = array_merge(
        zimrx_user_drug_search_rows($query, min(20, max(1, $limit)), $doctorId),
        zimrx_user_drug_filter_system_rows($systemRows, $doctorId)
    );

    $seen = [];
    $merged = [];
    foreach ($rows as $row) {
        $key = (string)($row['id'] ?? $row['brand_id'] ?? '');
        if ($key === '' || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $merged[] = $row;
        if (count($merged) >= $limit) {
            break;
        }
    }
    return $merged;
}

function zimrx_user_drug_index_to_catalog_row(array $row): array {
    $isOverride = ($row['source_type'] ?? '') === 'override';
    $id = $isOverride && !empty($row['system_brand_id']) ? (string)$row['system_brand_id'] : (string)($row['local_drug_id'] ?? '');
    return [
        'id' => $id,
        'brand_id' => $id,
        'system_brand_id' => (string)($row['system_brand_id'] ?? ''),
        'local_drug_id' => (string)($row['local_drug_id'] ?? ''),
        'source_type' => (string)($row['source_type'] ?? 'custom'),
        'is_user_drug' => 1,
        'pres_new_upper' => (string)($row['prescribe_brand_short'] ?? $row['brand_name'] ?? ''),
        'full_form_brand_name' => (string)($row['prescribe_brand_full'] ?? $row['brand_name'] ?? ''),
        'brand_name' => (string)($row['brand_name'] ?? ''),
        'generic' => (string)($row['generic_name'] ?? ''),
        'generic_name' => (string)($row['generic_name'] ?? ''),
        'us_generic_name' => '',
        'who_atc_class' => '',
        'generic_id' => (string)($row['generic_id'] ?? ''),
        'company_id' => 'user',
        'manufacturer' => (string)($row['manufacturer_name'] ?? 'Custom'),
        'man_short' => (string)($row['manufacturer_name'] ?? 'Custom'),
        'price' => (string)($row['price'] ?? ''),
        'packsize' => (string)($row['packsize'] ?? ''),
        'preg_cat' => '',
        'cls' => '',
        'is_antibiotic' => 0,
        'is_high_alert_medicine' => 0,
        'is_safe_in_pregnancy' => 0,
        'is_safe_in_lactation' => 0,
        'require_renal_adjustment' => 0,
        'is_safe_in_hepatic_impairment' => 0,
        'is_safe_in_paediatrics' => 0,
        'requires_tapering' => 0,
        'immediate_warning' => '',
        'precaution' => '',
        'generic_interaction' => '',
        'man_srt' => -1,
        'type_srt' => 0,
        'form' => (string)($row['form'] ?? ''),
        'form_new' => (string)($row['std_form'] ?? $row['form'] ?? ''),
        'strength' => (string)($row['strength'] ?? ''),
        'short_prescription' => (string)($row['short_prescription'] ?? $row['prescribe_brand_short'] ?? ''),
        'long_prescription' => (string)($row['long_prescription'] ?? $row['prescribe_brand_full'] ?? ''),
    ];
}

function zimrx_user_drug_hide(string $id, array $snapshot = [], int $doctorId = 1): void {
    $pdo = zimrx_user_drug_pdo();
    $userRow = zimrx_user_drug_fetch_index($id, $doctorId);
    if ($userRow && (string)($userRow['local_drug_id'] ?? '') === $id && ($userRow['source_type'] ?? '') === 'custom') {
        $stmt = $pdo->prepare("UPDATE zimrx_user_drug_prescribe_index SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE doctor_id = :doctor_id AND local_drug_id = :id");
        $stmt->execute(['doctor_id' => $doctorId, 'id' => $id]);
    }
    if ($userRow && ($userRow['source_type'] ?? '') === 'override') {
        $stmt = $pdo->prepare("UPDATE zimrx_user_drug_prescribe_index SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE doctor_id = :doctor_id AND system_brand_id = :id");
        $stmt->execute(['doctor_id' => $doctorId, 'id' => $id]);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO zimrx_user_drug_hidden (doctor_id, system_brand_id, brand_snapshot, is_active, hidden_at, restored_at)
         VALUES (:doctor_id, :system_brand_id, :brand_snapshot, 1, CURRENT_TIMESTAMP, NULL)
         ON CONFLICT(doctor_id, system_brand_id) DO UPDATE SET
            brand_snapshot = excluded.brand_snapshot,
            is_active = 1,
            hidden_at = CURRENT_TIMESTAMP,
            restored_at = NULL"
    );
    $stmt->execute([
        'doctor_id' => $doctorId,
        'system_brand_id' => $id,
        'brand_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function zimrx_user_drug_restore(string $id, int $doctorId = 1): void {
    $pdo = zimrx_user_drug_pdo();
    $stmt = $pdo->prepare("UPDATE zimrx_user_drug_hidden SET is_active = 0, restored_at = CURRENT_TIMESTAMP WHERE doctor_id = :doctor_id AND system_brand_id = :id");
    $stmt->execute(['doctor_id' => $doctorId, 'id' => $id]);

    $stmt = $pdo->prepare("UPDATE zimrx_user_drug_prescribe_index SET is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE doctor_id = :doctor_id AND (local_drug_id = :id OR system_brand_id = :id)");
    $stmt->execute(['doctor_id' => $doctorId, 'id' => $id]);
}

function zimrx_user_drug_hidden_list(string $query = '', int $doctorId = 1): array {
    $pdo = zimrx_user_drug_pdo();
    $stmt = $pdo->prepare(
        "SELECT system_brand_id, brand_snapshot, hidden_at
         FROM zimrx_user_drug_hidden
         WHERE doctor_id = :doctor_id AND is_active = 1
         ORDER BY hidden_at DESC"
    );
    $stmt->execute(['doctor_id' => $doctorId]);
    $rows = [];
    $query = strtolower(trim($query));
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $snapshot = json_decode((string)($row['brand_snapshot'] ?? ''), true);
        $snapshot = is_array($snapshot) ? $snapshot : [];
        $item = [
            'id' => (string)$row['system_brand_id'],
            'brand_name' => (string)($snapshot['brand_name'] ?? $snapshot['pres_new_upper'] ?? $row['system_brand_id']),
            'generic_name' => (string)($snapshot['generic_name'] ?? $snapshot['generic'] ?? ''),
            'strength' => (string)($snapshot['strength'] ?? ''),
            'form' => (string)($snapshot['form'] ?? ''),
            'manufacturer' => (string)($snapshot['manufacturer'] ?? $snapshot['manufacturer_name'] ?? ''),
            'price' => (string)($snapshot['price'] ?? ''),
            'hidden_at' => (string)($row['hidden_at'] ?? ''),
        ];
        if ($query !== '') {
            $haystack = strtolower(implode(' ', $item));
            if (strpos($haystack, $query) === false) {
                continue;
            }
        }
        $rows[] = $item;
    }
    return $rows;
}
