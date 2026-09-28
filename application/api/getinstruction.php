<?php
declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/rx_regimen_lib.php';

try {
    $term = isset($_GET['term']) ? trim($_GET['term']) : '';

    $results = array_map(
        fn(array $row) => ['value' => $row['value'], 'label' => $row['label']],
        rx_instruction_suggestions($term, rx_active_doctor_id(), 100)
    );
    echo json_encode($results);
} catch (Exception $e) {
    error_log('[ZimRx] getinstruction error: ' . $e->getMessage());
    echo json_encode([]);
}
?>
