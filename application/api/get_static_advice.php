<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
require_login();

header('Content-Type: application/json');

try {
    $dbPath = ZIMRX_DB_STATIC;

    if (!file_exists($dbPath)) {
        throw new Exception("Database file not found at: " . $dbPath);
    }

    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("
        SELECT
            id,
            category_en AS name,
            advice_bn AS body,
            advice_en,
            category_bn,
            category_en,
            category_search_alias,
            search_alias
        FROM zimrx_static_advices
        ORDER BY category_en COLLATE NOCASE, sort_order, advice_bn COLLATE NOCASE
    ");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
