<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/rx_regimen_lib.php';

try {
    $term = isset($_GET['term']) ? trim($_GET['term']) : '';

    $results = array_map(
        fn(array $row) => ['value' => $row['value'], 'label' => $row['label']],
        rx_instruction_suggestions($term, rx_active_doctor_id(), 100)
    );
    echo json_encode($results);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
