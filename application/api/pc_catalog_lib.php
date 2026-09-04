<?php
require_once __DIR__ . '/rx_regimen_lib.php';

function pc_supported_sources(): array {
    return [
        'most_used' => 'Most Used P/C',
        'custom'    => 'Custom P/C',
        'static_pc' => 'System P/C',
    ];
}

function pc_priority_default_rows(): array {
    $rows = [];
    foreach (pc_supported_sources() as $source => $label) {
        $rows[] = [
            'source' => $source,
            'label' => $label,
            'sort_order' => count($rows) + 1,
            'is_enabled' => 1,
        ];
    }
    return $rows;
}

function pc_lookup_db_path(string $filename): string {
    return ZIMRX_ASSETS_DB_DIR . '/' . $filename;
}

function pc_source_label(string $source): string {
    if ($source === 'snomed' || $source === 'static_pc') {
        return 'System P/C';
    }
    $sources = pc_supported_sources();
    return $sources[$source] ?? strtoupper($source);
}

function pc_catalog_db(string $filename): ?PDO {
    static $connections = [];
    if (array_key_exists($filename, $connections)) {
        return $connections[$filename];
    }

    $dbFile = pc_lookup_db_path($filename);
    if (!is_file($dbFile)) {
        $connections[$filename] = null;
        return null;
    }

    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $connections[$filename] = $pdo;
    return $pdo;
}

function pc_fts_prefix_query(string $query): string {
    preg_match_all('/[\p{L}\p{N}]+/u', $query, $matches);
    $tokens = array_slice($matches[0] ?? [], 0, 8);
    $tokens = array_filter($tokens, static function ($token) {
        return mb_strlen($token, 'UTF-8') >= 1;
    });

    return implode(' ', array_map(static function ($token) {
        return $token . '*';
    }, $tokens));
}

function pc_seed_term_groups(): array {
    return [
        ['fever'],
        ['cough'],
        ['headache'],
        ['pain'],
        ['abdominal pain'],
        ['chest pain'],
        ['dyspnea', 'breathlessness'],
        ['nausea'],
        ['vomiting'],
        ['diarrhea', 'diarrhoea'],
        ['dysuria'],
        ['constipation'],
        ['sore throat'],
        ['dizziness'],
        ['itching'],
        ['generalized rash', 'rash'],
        ['spasm'],
        ['chill'],
    ];
}

function pc_duration_defaults(): array {
    return ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '20', '30'];
}

function pc_unit_defaults(): array {
    return [
        'Day', 'Days',
        'Week', 'Weeks',
        'Month', 'Months',
        'Year', 'Years',
        'Hour', 'Hours',
        'Minute', 'Minutes',
        'Episode', 'Episodes',
        'Time', 'Times',
        'Attack', 'Attacks'
    ];
}

function pc_ensure_priority_rows(PDO $pdo, int $doctorId): void {
    $userPdo = rx_user_pdo();
    $doctorId = max(1, $doctorId);

    foreach (pc_priority_default_rows() as $row) {
        $stmt = $userPdo->prepare(
            "INSERT OR IGNORE INTO zimrx_user_pc_settings (
                doctor_id, setting_key, source, term, sort_order, is_enabled, created_at, updated_at
            ) VALUES (
                :doctor_id, 'source_priority', :source, '', :sort_order, :is_enabled, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )"
        );
        $stmt->execute([
            'doctor_id' => $doctorId,
            'source' => $row['source'],
            'sort_order' => $row['sort_order'],
            'is_enabled' => $row['is_enabled'],
        ]);
    }
}

function pc_priority_rows(PDO $pdo, int $doctorId): array {
    pc_ensure_priority_rows($pdo, $doctorId);
    $supportedSources = pc_supported_sources();
    $userPdo = rx_user_pdo();

    $stmt = $userPdo->prepare(
        "SELECT source, sort_order, is_enabled
         FROM zimrx_user_pc_settings
         WHERE doctor_id = :doctor_id AND setting_key = 'source_priority'"
    );
    $stmt->execute(['doctor_id' => max(1, $doctorId)]);
    $rows = $stmt->fetchAll();

    $mapped = [];
    foreach ($rows as $row) {
        $source = rx_clean($row['source'] ?? '');
        if (!isset($supportedSources[$source])) {
            continue;
        }
        $mapped[$source] = [
            'source' => $source,
            'label' => pc_source_label($source),
            'sort_order' => max(1, (int)($row['sort_order'] ?? 1)),
            'is_enabled' => (int)($row['is_enabled'] ?? 0) === 1 ? 1 : 0,
        ];
    }

    foreach (pc_priority_default_rows() as $row) {
        if (!isset($mapped[$row['source']])) {
            $mapped[$row['source']] = $row;
        }
    }

    $result = array_values($mapped);
    usort($result, static function ($a, $b) {
        return ($a['sort_order'] <=> $b['sort_order']) ?: strcmp($a['source'], $b['source']);
    });

    return array_values(array_map(static function ($row, $index) {
        $row['sort_order'] = $index + 1;
        return $row;
    }, $result, array_keys($result)));
}

function pc_source_rank_map(array $priorityRows): array {
    $rank = [];
    foreach ($priorityRows as $index => $row) {
        $rank[$row['source']] = $index + 1;
    }
    foreach (pc_supported_sources() as $source => $_label) {
        if (!isset($rank[$source])) {
            $rank[$source] = 100 + count($rank);
        }
    }
    return $rank;
}

function pc_hidden_key(string $source, string $term): string {
    return $source . '|' . rx_norm($term);
}

function pc_hidden_map(PDO $pdo, int $doctorId): array {
    $userPdo = rx_user_pdo();
    $stmt = $userPdo->prepare(
        "SELECT source, term
         FROM zimrx_user_pc_settings
         WHERE doctor_id = :doctor_id AND setting_key = 'hidden_term'"
    );
    $stmt->execute(['doctor_id' => max(1, $doctorId)]);

    $hidden = [];
    foreach ($stmt->fetchAll() as $row) {
        $source = rx_clean($row['source'] ?? '');
        $term = rx_clean($row['term'] ?? '');
        if ($term === '') {
            continue;
        }
        $normTerm = rx_norm($term);
        if ($source !== '') {
            $hidden[pc_hidden_key($source, $term)] = true;
        }
        // Universal term suppression key
        $hidden[$normTerm] = true;
    }

    return $hidden;
}

function pc_hidden_terms(PDO $pdo, int $doctorId): array {
    $userPdo = rx_user_pdo();
    $stmt = $userPdo->prepare(
        "SELECT source, term, updated_at
         FROM zimrx_user_pc_settings
         WHERE doctor_id = :doctor_id AND setting_key = 'hidden_term'
         ORDER BY updated_at DESC, id DESC"
    );
    $stmt->execute(['doctor_id' => max(1, $doctorId)]);

    $items = [];
    $seen = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $source = rx_clean($row['source'] ?? '');
        $term = rx_clean($row['term'] ?? '');
        if ($term === '') {
            continue;
        }
        $normKey = rx_norm($term);
        if (isset($seen[$normKey])) {
            continue;
        }
        $seen[$normKey] = true;
        $items[] = [
            'source' => $source ?: 'static_pc',
            'source_label' => pc_source_label($source ?: 'static_pc'),
            'term' => $term,
            'is_hidden' => true,
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }
    return $items;
}

function pc_is_hidden(array $hiddenMap, string $source, string $term): bool {
    $termNorm = rx_norm($term);
    if ($termNorm === '') {
        return false;
    }
    // Matches either source-specific suppression or universal complaint suppression
    return isset($hiddenMap[pc_hidden_key($source, $term)]) || isset($hiddenMap[$termNorm]);
}

function pc_learned_terms(PDO $pdo, int $doctorId, string $category, string $term, string $mode, int $limit): array {
    $userPdo = rx_user_pdo();
    if (!rx_table_exists($userPdo, 'zimrx_user_pc') || $limit < 1) {
        return [];
    }

    $orderSql = $mode === 'recent'
        ? "CASE
                WHEN :term = '' THEN 0
                WHEN term = :term_exact COLLATE NOCASE THEN 0
                WHEN term LIKE :term_prefix COLLATE NOCASE THEN 1
                ELSE 2
           END,
           updated_at DESC,
           usage_count DESC,
           id DESC"
        : "CASE
                WHEN :term = '' THEN 0
                WHEN term = :term_exact COLLATE NOCASE THEN 0
                WHEN term LIKE :term_prefix COLLATE NOCASE THEN 1
                ELSE 2
           END,
           usage_count DESC,
           updated_at DESC,
           id DESC";

    $stmt = $userPdo->prepare(
        "SELECT term, usage_count, created_at, updated_at
         FROM zimrx_user_pc
         WHERE doctor_id = :doctor_id
           AND category = :category
           AND (:term = '' OR term LIKE :term_like COLLATE NOCASE)
         ORDER BY $orderSql
         LIMIT :limit"
    );
    $stmt->bindValue(':doctor_id', max(1, $doctorId), PDO::PARAM_INT);
    $stmt->bindValue(':category', $category, PDO::PARAM_STR);
    $stmt->bindValue(':term', $term, PDO::PARAM_STR);
    $stmt->bindValue(':term_like', '%' . $term . '%', PDO::PARAM_STR);
    $stmt->bindValue(':term_prefix', $term . '%', PDO::PARAM_STR);
    $stmt->bindValue(':term_exact', $term, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function pc_custom_terms(PDO $pdo, int $doctorId, string $term = '', int $limit = 20): array {
    $userPdo = rx_user_pdo();
    if (!rx_table_exists($userPdo, 'zimrx_user_pc') || $limit < 1) {
        return [];
    }

    $stmt = $userPdo->prepare(
        "SELECT c.term, c.created_at, c.updated_at,
                COALESCE(u.usage_count, c.usage_count, 0) AS usage_count
         FROM zimrx_user_pc c
         LEFT JOIN zimrx_user_pc u
           ON u.doctor_id = c.doctor_id
          AND u.category = 'PC'
          AND u.term = c.term COLLATE NOCASE
         WHERE c.doctor_id = :doctor_id
           AND c.category = 'custom'
           AND (:term = '' OR c.term LIKE :term_like COLLATE NOCASE)
         ORDER BY
            CASE
                WHEN :term = '' THEN 0
                WHEN c.term = :term_exact COLLATE NOCASE THEN 0
                WHEN c.term LIKE :term_prefix COLLATE NOCASE THEN 1
                ELSE 2
            END,
            COALESCE(u.usage_count, c.usage_count, 0) DESC,
            c.updated_at DESC,
            c.term ASC
         LIMIT :limit"
    );
    $stmt->bindValue(':doctor_id', max(1, $doctorId), PDO::PARAM_INT);
    $stmt->bindValue(':term', $term, PDO::PARAM_STR);
    $stmt->bindValue(':term_like', '%' . $term . '%', PDO::PARAM_STR);
    $stmt->bindValue(':term_prefix', $term . '%', PDO::PARAM_STR);
    $stmt->bindValue(':term_exact', $term, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function pc_custom_term_exists(PDO $pdo, int $doctorId, string $term): bool {
    static $cache = [];
    $userPdo = rx_user_pdo();
    if (!rx_table_exists($userPdo, 'zimrx_user_pc')) {
        return false;
    }

    $cacheKey = max(1, $doctorId) . '|' . rx_norm($term);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $stmt = $userPdo->prepare(
        "SELECT 1
         FROM zimrx_user_pc
         WHERE doctor_id = :doctor_id
           AND category = 'custom'
           AND term = :term COLLATE NOCASE
         LIMIT 1"
    );
    $stmt->execute([
        'doctor_id' => max(1, $doctorId),
        'term' => rx_clean($term),
    ]);
    $cache[$cacheKey] = (bool)$stmt->fetchColumn();
    return $cache[$cacheKey];
}

function pc_custom_durations(PDO $pdo, int $doctorId, string $term = '', int $limit = 50): array {
    $userPdo = rx_user_pdo();
    if (!rx_table_exists($userPdo, 'zimrx_user_pc') || $limit < 1) {
        return [];
    }

    $defaults = pc_duration_defaults();
    $placeholders = implode(',', array_fill(0, count($defaults), '?'));

    $sql = "SELECT c.term,
                   MAX(c.created_at) AS created_at,
                   MAX(c.updated_at) AS updated_at,
                   MAX(COALESCE(u.usage_count, c.usage_count, 0)) AS usage_count
            FROM zimrx_user_pc c
            LEFT JOIN zimrx_user_pc u
              ON u.doctor_id = c.doctor_id
             AND u.category = 'pc_duration'
             AND u.term = c.term COLLATE NOCASE
            WHERE c.doctor_id = ?
              AND (
                   c.category = 'custom_duration'
                   OR (c.category = 'pc_duration' AND c.term NOT IN ($placeholders))
              )
              AND (? = '' OR c.term LIKE ? COLLATE NOCASE)
            GROUP BY c.term";

    $stmt = $userPdo->prepare($sql);
    $params = [max(1, $doctorId)];
    foreach ($defaults as $d) {
        $params[] = (string)$d;
    }
    $params[] = $term;
    $params[] = '%' . $term . '%';

    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    usort($rows, static function ($a, $b) {
        $aNum = is_numeric($a['term']) ? (float)$a['term'] : null;
        $bNum = is_numeric($b['term']) ? (float)$b['term'] : null;
        if ($aNum !== null && $bNum !== null) {
            return $aNum <=> $bNum;
        }
        if ($aNum !== null) return -1;
        if ($bNum !== null) return 1;
        return strnatcasecmp((string)$a['term'], (string)$b['term']);
    });

    return array_slice($rows, 0, $limit);
}

function pc_custom_duration_exists(PDO $pdo, int $doctorId, string $term): bool {
    $userPdo = rx_user_pdo();
    if (!rx_table_exists($userPdo, 'zimrx_user_pc')) {
        return false;
    }

    $stmt = $userPdo->prepare(
        "SELECT 1
         FROM zimrx_user_pc
         WHERE doctor_id = :doctor_id
           AND category IN ('custom_duration', 'pc_duration')
           AND term = :term COLLATE NOCASE
         LIMIT 1"
    );
    $stmt->execute([
        'doctor_id' => max(1, $doctorId),
        'term' => rx_clean($term),
    ]);
    return (bool)$stmt->fetchColumn();
}

function pc_static_pc_exact_match(string $term): bool {
    static $cache = [];
    $term = rx_clean($term);
    if ($term === '') {
        return false;
    }

    $cacheKey = rx_norm($term);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $db = pc_catalog_db('zimrx_static.db');
    if (!$db instanceof PDO) {
        return false;
    }

    $stmt = $db->prepare(
        "SELECT 1
         FROM zimrx_static_pc
         WHERE term = :term COLLATE NOCASE
         LIMIT 1"
    );
    $stmt->execute(['term' => $term]);
    $cache[$cacheKey] = (bool)$stmt->fetchColumn();
    return $cache[$cacheKey];
}

function pc_snomed_exact_match(string $term): bool {
    return pc_static_pc_exact_match($term);
}

function pc_icd_exact_match(string $term): bool {
    static $cache = [];
    $term = rx_clean($term);
    if ($term === '') {
        return false;
    }

    $cacheKey = rx_norm($term);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $db = pc_catalog_db('zimrx_icd11_dx.db');
    if (!$db instanceof PDO) {
        return false;
    }

    $stmt = $db->prepare(
        "SELECT 1
         FROM master_dx_index
         WHERE search_term = :term COLLATE NOCASE
         LIMIT 1"
    );
    $stmt->execute(['term' => $term]);
    $cache[$cacheKey] = (bool)$stmt->fetchColumn();
    return $cache[$cacheKey];
}

function pc_source_candidates_for_term(PDO $pdo, int $doctorId, string $term): array {
    $term = rx_clean($term);
    if ($term === '') {
        return [];
    }

    $candidates = [];
    if (pc_custom_term_exists($pdo, $doctorId, $term)) {
        $candidates[] = 'custom';
    }
    if (pc_static_pc_exact_match($term)) {
        $candidates[] = 'static_pc';
    }
    return $candidates;
}

function pc_match_priority(string $label, string $term): array {
    $labelNorm = rx_norm($label);
    $termNorm = rx_norm($term);
    $matchRank = 5;

    if ($termNorm === '') {
        $matchRank = 0;
    } elseif ($labelNorm === $termNorm) {
        $matchRank = 0;
    } elseif (str_starts_with($labelNorm, $termNorm . ' ')) {
        $matchRank = 1;
    } elseif (str_starts_with($labelNorm, $termNorm)) {
        $matchRank = 2;
    } elseif (preg_match('/(?:^|[\s,\-\/\(])' . preg_quote($termNorm, '/') . '(?=$|[\s,\-\/\)])/', $labelNorm)) {
        $matchRank = 3;
    } elseif (strpos($labelNorm, $termNorm) !== false) {
        $matchRank = 4;
    }

    return [
        $matchRank,
        abs(mb_strlen($labelNorm, 'UTF-8') - mb_strlen($termNorm, 'UTF-8')),
        $labelNorm,
    ];
}

function pc_sort_static_pc_matches(array $rows, string $term): array {
    usort($rows, static function ($a, $b) use ($term) {
        [$aMatch, $aLengthDelta, $aLabelNorm] = pc_match_priority((string)($a['preferred_term'] ?? ''), $term);
        [$bMatch, $bLengthDelta, $bLabelNorm] = pc_match_priority((string)($b['preferred_term'] ?? ''), $term);
        if ($aMatch !== $bMatch) {
            return $aMatch <=> $bMatch;
        }

        if ($aLengthDelta !== $bLengthDelta) {
            return $aLengthDelta <=> $bLengthDelta;
        }

        $aCategory = strtolower((string)($a['category'] ?? ''));
        $bCategory = strtolower((string)($b['category'] ?? ''));
        $aCategoryRank = $aCategory === 'finding' ? 0 : 1;
        $bCategoryRank = $bCategory === 'finding' ? 0 : 1;
        if ($aCategoryRank !== $bCategoryRank) {
            return $aCategoryRank <=> $bCategoryRank;
        }

        return strcmp($aLabelNorm, $bLabelNorm);
    });

    return $rows;
}

function pc_sort_snomed_matches(array $rows, string $term): array {
    return pc_sort_static_pc_matches($rows, $term);
}

function pc_classify_term_source(PDO $pdo, int $doctorId, string $term, array $priorityRows = []): string {
    $candidates = pc_source_candidates_for_term($pdo, $doctorId, $term);
    if (!$candidates) {
        return 'most_used';
    }

    $rank = pc_source_rank_map($priorityRows ?: pc_priority_rows($pdo, $doctorId));
    usort($candidates, static function ($a, $b) use ($rank) {
        return ($rank[$a] ?? 999) <=> ($rank[$b] ?? 999);
    });

    return $candidates[0] ?? 'most_used';
}

function pc_static_term_exists(string $term): bool {
    static $cache = [];
    $termClean = trim((string)preg_replace('/\s+/u', ' ', $term));
    if ($termClean === '') {
        return false;
    }
    $termNorm = mb_strtolower($termClean, 'UTF-8');
    if (array_key_exists($termNorm, $cache)) {
        return $cache[$termNorm];
    }
    $db = pc_catalog_db('zimrx_static.db');
    if (!$db instanceof PDO) {
        return false;
    }
    $stmt = $db->prepare(
        "SELECT 1 FROM zimrx_static_pc WHERE term = :term COLLATE NOCASE LIMIT 1"
    );
    $stmt->execute(['term' => $termClean]);
    $exists = (bool)$stmt->fetchColumn();
    $cache[$termNorm] = $exists;
    return $exists;
}

function pc_static_pc_search(string $term, int $limit = 25): array {
    $db = pc_catalog_db('zimrx_static.db');
    if (!$db instanceof PDO || $limit < 1) {
        return [];
    }

    if ($term === '') {
        static $staticPcSeedCache = null;
        static $staticPcSeedLimit = 0;
        if ($staticPcSeedCache !== null && $staticPcSeedLimit >= $limit) {
            return array_slice($staticPcSeedCache, 0, $limit);
        }

        $seeds = [];
        $seedGroups = pc_seed_term_groups();
        foreach ($seedGroups as $terms) {
            $seeds = array_merge($seeds, $terms);
        }

        $placeholders = implode(',', array_fill(0, count($seeds), '?'));
        $stmt = $db->prepare(
            "SELECT id AS concept_id, term AS preferred_term, category
             FROM zimrx_static_pc
             WHERE term COLLATE NOCASE IN ($placeholders)"
        );
        $stmt->execute($seeds);

        $rowMap = [];
        foreach ($stmt->fetchAll() as $row) {
            $rowMap[strtolower((string)$row['preferred_term'])] = $row;
        }

        $results = [];
        $seen = [];
        foreach ($seedGroups as $terms) {
            foreach ($terms as $seed) {
                $seedKey = strtolower($seed);
                if (isset($rowMap[$seedKey])) {
                    $row = $rowMap[$seedKey];
                    $key = (string)$row['concept_id'];
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $results[] = $row;
                    }
                    break;
                }
            }
            if (count($results) >= $limit) {
                break;
            }
        }

        $staticPcSeedCache = $results;
        $staticPcSeedLimit = $limit;
        return array_slice($results, 0, $limit);
    }

    $ftsQuery = pc_fts_prefix_query($term);
    $hasFts = (bool)$db->query(
        "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'fts_zimrx_static_pc' LIMIT 1"
    )->fetchColumn();

    if ($hasFts && $ftsQuery !== '') {
        $stmt = $db->prepare(
            "
            WITH prefix_matches AS (
                SELECT
                    id AS concept_id,
                    term AS preferred_term,
                    category,
                    CASE category WHEN 'finding' THEN 0 ELSE 1 END AS category_order,
                    -100.0 AS rank,
                    0 AS source_order
                FROM zimrx_static_pc
                WHERE term LIKE :prefix COLLATE NOCASE
            ),
            fts_matches AS (
                SELECT
                    p.id AS concept_id,
                    p.term AS preferred_term,
                    p.category,
                    CASE p.category WHEN 'finding' THEN 0 ELSE 1 END AS category_order,
                    bm25(fts_zimrx_static_pc) AS rank,
                    1 AS source_order
                FROM fts_zimrx_static_pc f
                JOIN zimrx_static_pc p ON p.id = f.rowid
                WHERE fts_zimrx_static_pc MATCH :fts_query
            ),
            combined AS (
                SELECT * FROM prefix_matches
                UNION ALL
                SELECT * FROM fts_matches
            ),
            deduped AS (
                SELECT
                    concept_id,
                    preferred_term,
                    category,
                    category_order,
                    source_order,
                    rank,
                    ROW_NUMBER() OVER (
                        PARTITION BY concept_id
                        ORDER BY
                            source_order ASC,
                            category_order ASC,
                            rank ASC,
                            preferred_term ASC
                    ) AS row_priority
                FROM combined
            )
            SELECT concept_id, preferred_term, category
            FROM deduped
            WHERE row_priority = 1
            ORDER BY
                source_order ASC,
                category_order ASC,
                rank ASC,
                preferred_term ASC
            LIMIT :limit
            "
        );
        $stmt->bindValue(':prefix', $term . '%', PDO::PARAM_STR);
        $stmt->bindValue(':fts_query', $ftsQuery, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_slice(pc_sort_static_pc_matches($stmt->fetchAll(), $term), 0, $limit);
    }

    $stmt = $db->prepare(
        "
        SELECT id AS concept_id, term AS preferred_term, category
        FROM zimrx_static_pc
        WHERE term LIKE :term_like COLLATE NOCASE
        ORDER BY
            CASE
                WHEN term = :term_exact COLLATE NOCASE THEN 0
                WHEN term LIKE :prefix COLLATE NOCASE THEN 1
                ELSE 2
            END,
            CASE category WHEN 'finding' THEN 0 ELSE 1 END,
            term ASC
        LIMIT :limit
        "
    );
    $stmt->bindValue(':term_like', '%' . $term . '%', PDO::PARAM_STR);
    $stmt->bindValue(':term_exact', $term, PDO::PARAM_STR);
    $stmt->bindValue(':prefix', $term . '%', PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return array_slice(pc_sort_static_pc_matches($stmt->fetchAll(), $term), 0, $limit);
}

function pc_snomed_search(string $term, int $limit = 25): array {
    return pc_static_pc_search($term, $limit);
}

function pc_icd_search(string $term, int $limit = 15): array {
    $db = pc_catalog_db('zimrx_icd11_dx.db');
    if (!$db instanceof PDO || $limit < 1) {
        return [];
    }

    if ($term === '') {
        static $icdSeedCache = null;
        static $icdSeedLimit = 0;
        if ($icdSeedCache !== null && $icdSeedLimit >= $limit) {
            return array_slice($icdSeedCache, 0, $limit);
        }

        $seeds = [];
        $seedGroups = pc_seed_term_groups();
        foreach ($seedGroups as $terms) {
            $seeds = array_merge($seeds, $terms);
        }

        $placeholders = implode(',', array_fill(0, count($seeds), '?'));
        $stmt = $db->prepare(
            "SELECT code, search_term, CAST(is_official AS INTEGER) AS is_official
             FROM master_dx_index
             WHERE is_official = 1 AND search_term COLLATE NOCASE IN ($placeholders)"
        );
        $stmt->execute($seeds);

        $rowMap = [];
        foreach ($stmt->fetchAll() as $row) {
            $rowMap[strtolower((string)$row['search_term'])] = $row;
        }

        $results = [];
        $seen = [];
        foreach ($seedGroups as $terms) {
            foreach ($terms as $seed) {
                $seedKey = strtolower($seed);
                if (isset($rowMap[$seedKey])) {
                    $row = $rowMap[$seedKey];
                    $key = (string)$row['code'];
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $results[] = $row;
                    }
                    break;
                }
            }
            if (count($results) >= $limit) {
                break;
            }
        }

        $icdSeedCache = $results;
        $icdSeedLimit = $limit;
        return array_slice($results, 0, $limit);
    }

    $ftsQuery = pc_fts_prefix_query($term);
    if ($ftsQuery === '') {
        return [];
    }

    $stmt = $db->prepare(
        "
        WITH prefix_matches AS (
            SELECT
                code,
                search_term,
                CAST(is_official AS INTEGER) AS is_official,
                -100.0 AS rank,
                0 AS source_order
            FROM master_dx_index
            WHERE search_term LIKE :prefix COLLATE NOCASE
        ),
        fts_matches AS (
            SELECT
                code,
                search_term,
                CAST(is_official AS INTEGER) AS is_official,
                bm25(dx_search_idx) AS rank,
                1 AS source_order
            FROM dx_search_idx
            WHERE dx_search_idx MATCH :fts_query
        ),
        combined AS (
            SELECT * FROM prefix_matches
            UNION ALL
            SELECT * FROM fts_matches
        ),
        deduped AS (
            SELECT
                code,
                search_term,
                is_official,
                source_order,
                rank,
                ROW_NUMBER() OVER (
                    PARTITION BY code
                    ORDER BY
                        source_order ASC,
                        is_official DESC,
                        rank ASC,
                        search_term ASC
                ) AS row_priority
            FROM combined
        )
        SELECT code, search_term, is_official
        FROM deduped
        WHERE row_priority = 1
        ORDER BY
            source_order ASC,
            is_official DESC,
            rank ASC,
            search_term ASC
        LIMIT :limit
        "
    );
    $stmt->bindValue(':prefix', $term . '%', PDO::PARAM_STR);
    $stmt->bindValue(':fts_query', $ftsQuery, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
