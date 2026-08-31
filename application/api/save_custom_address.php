<?php
define('ZIMRX_DB_LIGHTWEIGHT', true);
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

// Read JSON POST data
$input = json_decode(file_get_contents('php://input'), true);
$full_address = isset($input['address']) ? trim($input['address']) : '';

if (!$full_address) { echo json_encode(['status' => 'empty']); exit; }

try {
    $pdo_user = DbConnections::userdata();
    $pdo_static = DbConnections::staticDb();
    $doctor_id = (int)($_SESSION['doctor_id'] ?? 1);

    // Clean and normalize parts
    $raw_parts = explode(',', $full_address);
    $clean_parts = [];
    foreach ($raw_parts as $p) {
        $p = trim($p);
        if (strlen($p) >= 2) {
            $clean_parts[] = $p;
        }
    }

    if (empty($clean_parts)) {
        echo json_encode(['status' => 'empty']);
        exit;
    }

    $inserted = 0;

    $stmt_upsert = $pdo_user->prepare("
        INSERT INTO zimrx_user_address (doctor_id, name, usage_count, updated_at) 
        VALUES (:doctor_id, :name, 1, CURRENT_TIMESTAMP)
        ON CONFLICT(doctor_id, name) DO UPDATE SET 
            usage_count = usage_count + 1,
            updated_at = CURRENT_TIMESTAMP
    ");

    $stmt_check = $pdo_static->prepare("
        SELECT 1 FROM zimrx_static_address_districts WHERE name = :p OR bn_name = :p
        UNION SELECT 1 FROM zimrx_static_address_upazillas WHERE name = :p OR bn_name = :p
        UNION SELECT 1 FROM zimrx_static_address_unions WHERE name = :p OR bn_name = :p
        UNION SELECT 1 FROM zimrx_static_address_thana WHERE name = :p OR bn_name = :p
        UNION SELECT 1 FROM zimrx_static_address_postoffice WHERE name = :p OR bn_name = :p
    ");

    // 1. Save individual parts that are not present in static national database
    foreach ($clean_parts as $part) {
        $stmt_check->execute(['p' => $part]);
        if (!$stmt_check->fetch()) {
            $stmt_upsert->execute(['doctor_id' => $doctor_id, 'name' => $part]);
            $inserted++;
        }
    }

    // 2. ALSO save the full multi-part combination
    if (count($clean_parts) >= 2) {
        $combination = implode(', ', $clean_parts);
        $stmt_upsert->execute(['doctor_id' => $doctor_id, 'name' => $combination]);
        $inserted++;
    }

    echo json_encode(['status' => 'success', 'learned_items' => $inserted]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>