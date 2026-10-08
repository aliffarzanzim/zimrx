<?php
declare(strict_types=1);

// Static advice catalog endpoint: loads standard bilingual clinical advice items sorted by category.

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/rx_template_lib.php';

header('Content-Type: application/json');

try {
    $rows = rx_phrase_static_rows('advice');
    usort($rows, static function (array $a, array $b): int {
        $catCmp = strcasecmp((string)($a['category_en'] ?? ''), (string)($b['category_en'] ?? ''));
        if ($catCmp !== 0) {
            return $catCmp;
        }
        $sortCmp = ((int)($a['sort_order'] ?? 0)) <=> ((int)($b['sort_order'] ?? 0));
        if ($sortCmp !== 0) {
            return $sortCmp;
        }
        return strcasecmp((string)($a['body'] ?? ''), (string)($b['body'] ?? ''));
    });

    echo json_encode(array_values($rows), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[ZimRx] get_static_advice error: ' . $e->getMessage());
    echo json_encode([]);
}
