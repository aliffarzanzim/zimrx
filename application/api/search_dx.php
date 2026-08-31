<?php
if (!defined('ZIMRX_DB_LIGHTWEIGHT')) {
    define('ZIMRX_DB_LIGHTWEIGHT', true);
}
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

$query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

if (mb_strlen($query, 'UTF-8') < 1) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('dx_fts_prefix_query')) {
    function dx_fts_prefix_query(string $query): string {
        preg_match_all('/[\p{L}\p{N}]+/u', $query, $matches);
        $tokens = array_slice($matches[0] ?? [], 0, 8);
        $tokens = array_filter($tokens, static function ($token) {
            return mb_strlen($token, 'UTF-8') >= 2;
        });

        if (empty($tokens)) {
            return '';
        }

        return implode(' ', array_map(static function ($token) {
            return $token . '*';
        }, $tokens));
    }
}

try {
    $db = DbConnections::staticDb();

    $fts_query = dx_fts_prefix_query($query);
    $prefix = $query . '%';

    if ($fts_query !== '') {
        $stmt = $db->prepare("
            WITH matches AS (
                SELECT id, dx_short, dx_full, acronym, specialty, system_category, priority_tier, 0 AS match_tier, 0.0 AS fts_rank
                FROM zimrx_static_dx
                WHERE acronym LIKE :prefix1 COLLATE NOCASE
                UNION ALL
                SELECT id, dx_short, dx_full, acronym, specialty, system_category, priority_tier, 1 AS match_tier, 0.0 AS fts_rank
                FROM zimrx_static_dx
                WHERE dx_short LIKE :prefix2 COLLATE NOCASE
                UNION ALL
                SELECT id, dx_short, dx_full, acronym, specialty, system_category, priority_tier, 2 AS match_tier, 0.0 AS fts_rank
                FROM zimrx_static_dx
                WHERE dx_full LIKE :prefix3 COLLATE NOCASE
                UNION ALL
                SELECT s.id, s.dx_short, s.dx_full, s.acronym, s.specialty, s.system_category, s.priority_tier, 3 AS match_tier, bm25(fts_zimrx_static_dx) AS fts_rank
                FROM fts_zimrx_static_dx f
                JOIN zimrx_static_dx s ON s.id = f.rowid
                WHERE fts_zimrx_static_dx MATCH :fts_query
            ),
            deduped AS (
                SELECT *, ROW_NUMBER() OVER (
                    PARTITION BY LOWER(dx_short)
                    ORDER BY match_tier ASC, priority_tier ASC, fts_rank ASC
                ) AS rn
                FROM matches
            )
            SELECT id, dx_short, dx_full, acronym, specialty, system_category, priority_tier
            FROM deduped
            WHERE rn = 1
            ORDER BY match_tier ASC, priority_tier ASC, LENGTH(dx_short) ASC, fts_rank ASC
            LIMIT 20
        ");

        $stmt->execute([
            'prefix1' => $prefix,
            'prefix2' => $prefix,
            'prefix3' => $prefix,
            'fts_query' => $fts_query,
        ]);
    } else {
        $stmt = $db->prepare("
            WITH matches AS (
                SELECT id, dx_short, dx_full, acronym, specialty, system_category, priority_tier, 0 AS match_tier
                FROM zimrx_static_dx
                WHERE acronym LIKE :prefix1 COLLATE NOCASE
                UNION ALL
                SELECT id, dx_short, dx_full, acronym, specialty, system_category, priority_tier, 1 AS match_tier
                FROM zimrx_static_dx
                WHERE dx_short LIKE :prefix2 COLLATE NOCASE
                UNION ALL
                SELECT id, dx_short, dx_full, acronym, specialty, system_category, priority_tier, 2 AS match_tier
                FROM zimrx_static_dx
                WHERE dx_full LIKE :prefix3 COLLATE NOCASE
            ),
            deduped AS (
                SELECT *, ROW_NUMBER() OVER (
                    PARTITION BY LOWER(dx_short)
                    ORDER BY match_tier ASC, priority_tier ASC
                ) AS rn
                FROM matches
            )
            SELECT id, dx_short, dx_full, acronym, specialty, system_category, priority_tier
            FROM deduped
            WHERE rn = 1
            ORDER BY match_tier ASC, priority_tier ASC, LENGTH(dx_short) ASC
            LIMIT 20
        ");

        $stmt->execute([
            'prefix1' => $prefix,
            'prefix2' => $prefix,
            'prefix3' => $prefix,
        ]);
    }

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Diagnosis search failed: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

