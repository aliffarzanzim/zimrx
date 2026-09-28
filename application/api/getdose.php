<?php
declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/rx_template_lib.php';

try {
    $term = isset($_GET['term']) ? trim($_GET['term']) : '';
    echo json_encode(rx_phrase_suggestions_for_type('dose', $term, rx_active_doctor_id(), 100), JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('[ZimRx] getdose error: ' . $e->getMessage());
    echo json_encode([]);
}
?>
