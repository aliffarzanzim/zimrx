<?php
define('ZIMRX_DB_LIGHTWEIGHT', true);
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_login();

header('Content-Type: application/json');

try {
    $q = trim((string)($_GET['q'] ?? ''));
    $category = trim((string)($_GET['category'] ?? ''));
    $pdo = DbConnections::staticDb();

    $where = [];
    $params = [];

    if ($q !== '') {
        $where[] = '(name LIKE :q OR category LIKE :q OR institute LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }

    if ($category !== '') {
        $where[] = 'category = :category';
        $params['category'] = $category;
    }

    $sql = '
        SELECT id, name, category, price, institute
        FROM zimrx_static_investigations
    ';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= '
        ORDER BY
            CASE WHEN name LIKE :starts THEN 0 ELSE 1 END,
            category COLLATE NOCASE,
            name COLLATE NOCASE
        LIMIT 30
    ';
    $params['starts'] = $q !== '' ? $q . '%' : '%';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
