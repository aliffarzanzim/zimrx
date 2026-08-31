<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/rx_template_lib.php';

try {
    $term = isset($_GET['term']) ? trim($_GET['term']) : '';
    echo json_encode(rx_phrase_suggestions_for_type('duration', $term, rx_active_doctor_id(), 100), JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
