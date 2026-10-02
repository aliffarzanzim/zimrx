<?php
declare(strict_types=1);

// Static advice catalog endpoint: loads standard bilingual clinical advice items sorted by category.

require_once dirname(__DIR__) . '/init.php';
require_login();

header('Content-Type: application/json');

try {
    $pdo = DbConnections::staticDb();

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
} catch (Throwable $e) {
    error_log('[ZimRx] get_static_advice error: ' . $e->getMessage());
    echo json_encode([]);
}
