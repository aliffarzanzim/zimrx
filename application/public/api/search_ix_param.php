<?php
// Search laboratory investigation parameters, standard units, and reference values.
declare(strict_types=1);

define('ZIMRX_DB_LIGHTWEIGHT', true);
require_once dirname(__DIR__) . '/init.php';
require_login();

header('Content-Type: application/json');

try {
    $q = trim((string)($_GET['q'] ?? ''));
    $pdo = DbConnections::staticDb();

    $where = [];
    $params = [];

    if ($q !== '') {
        $where[] = '(short_name LIKE :q OR full_name LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }

    $sql = '
        SELECT id, short_name, full_name, default_unit, all_units
        FROM zimrx_static_investigations_param
    ';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= '
        ORDER BY
            CASE 
                WHEN short_name LIKE :starts THEN 0 
                WHEN full_name LIKE :starts THEN 1 
                ELSE 2 
            END,
            short_name COLLATE NOCASE,
            full_name COLLATE NOCASE
        LIMIT 35
    ';
    $params['starts'] = $q !== '' ? $q . '%' : '%';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as &$row) {
        $rawUnits = (string)($row['all_units'] ?? '[]');
        $decoded = json_decode($rawUnits, true);
        $row['units'] = is_array($decoded) ? $decoded : [];
    }
    unset($row);

    echo json_encode($results, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[ZimRx] search_ix_param error: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to search investigation parameters.'], JSON_UNESCAPED_UNICODE);
}
