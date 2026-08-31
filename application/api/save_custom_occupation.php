<?php
define('ZIMRX_DB_LIGHTWEIGHT', true);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../particulars_audit_lib.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$occupation = isset($input['occupation']) ? trim((string)$input['occupation']) : '';

if (!$occupation || strlen($occupation) < 2) {
    echo json_encode(['status' => 'empty']);
    exit;
}

try {
    $doctorId = function_exists('current_user_doctor_id') ? current_user_doctor_id() : 1;
    $pdo_user = DbConnections::userdata();

    zimrx_record_user_occupation($pdo_user, $doctorId, $occupation);

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
