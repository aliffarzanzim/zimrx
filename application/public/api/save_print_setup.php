<?php
// Saves print setup configuration payload (margins, headers, layout parameters) for the active doctor.
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/print_setup_lib.php';

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
    zimrx_print_save_setup($pdo, $doctorId, $data);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('[ZimRx] save_print_setup error: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to save print setup.']);
}
