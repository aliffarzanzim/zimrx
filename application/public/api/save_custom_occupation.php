<?php
// Saves new or learned patient occupation to the user database.
declare(strict_types=1);

define('ZIMRX_DB_LIGHTWEIGHT', true);
require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/particulars_audit_lib.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed. POST required.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!zimrx_verify_csrf(is_string($csrfToken) ? $csrfToken : null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF verification failed.']);
    exit;
}

$occupation = isset($input['occupation']) ? trim((string)$input['occupation']) : '';

if (!$occupation || strlen($occupation) < 2) {
    echo json_encode(['status' => 'empty']);
    exit;
}

try {
    $doctorId = current_user_doctor_id();
    if ($doctorId <= 0) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Valid doctor account required.']);
        exit;
    }
    $pdo_user = DbConnections::userdata();

    zimrx_record_user_occupation($pdo_user, $doctorId, $occupation);

    echo json_encode(['status' => 'success']);
} catch (Throwable $e) {
    error_log('[ZimRx] save_custom_occupation error: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to save custom occupation.']);
}
