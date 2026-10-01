<?php
declare(strict_types=1);

// Dose phrase suggestions endpoint: returns dose templates and learned expressions for prescription rows.

header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/rx_template_lib.php';

try {
    $term = isset($_GET['term']) ? trim($_GET['term']) : '';
    echo json_encode(rx_phrase_suggestions_for_type('dose', $term, rx_active_doctor_id(), 100), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[ZimRx] getdose error: ' . $e->getMessage());
    echo json_encode([]);
}
?>
