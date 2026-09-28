<?php
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/print_setup_lib.php';

ini_set('display_errors', '0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!zimrx_verify_csrf()) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF verification failed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        $data = $_POST;
    }
    $doctorId = current_user_doctor_id();
    if ($doctorId <= 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    zimrx_bridge_save_print_setup($pdo, $doctorId, $data);

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    error_log('[ZimRx] save_print_setup error: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to save print setup.']);
}
