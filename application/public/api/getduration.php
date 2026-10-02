<?php
declare(strict_types=1);

// Duration phrase suggestions endpoint: returns duration templates and learned units for prescription rows.

header('Content-Type: application/json');
require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/rx_template_lib.php';

try {
    $term = isset($_GET['term']) ? trim($_GET['term']) : '';
    echo json_encode(rx_phrase_suggestions_for_type('duration', $term, rx_active_doctor_id(), 100), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[ZimRx] getduration error: ' . $e->getMessage());
    echo json_encode([]);
}
?>
