<?php
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../print_setup_lib.php';

ini_set('display_errors', '0');

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        $data = $_POST;
    }
    $doctorId = current_user_doctor_id();
    zimrx_bridge_save_print_setup($pdo, $doctorId, $data);

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
