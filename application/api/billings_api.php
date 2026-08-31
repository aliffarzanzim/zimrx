<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

$pdo = DbConnections::userdata();
$doctorId = max(1, (int)(function_exists('current_user_doctor_id') ? current_user_doctor_id() : 1));

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

try {
    if ($action === 'save_payment') {
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        $paidAmount = max(0, (float)($_POST['paid_amount'] ?? 0));
        $discount = max(0, (float)($_POST['discount'] ?? 0));
        $discountNote = trim((string)($_POST['discount_note'] ?? ''));
        $paymentMethod = trim((string)($_POST['payment_method'] ?? 'Cash'));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($paymentId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid payment ID']);
            exit;
        }

        // Get current payment
        $stmt = $pdo->prepare("SELECT * FROM zimrx_payments WHERE id = :id");
        $stmt->execute(['id' => $paymentId]);
        $curr = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$curr) {
            echo json_encode(['success' => false, 'error' => 'Payment record not found']);
            exit;
        }

        $visitFee = (float)($curr['visit_fee'] ?? 0);
        $netPayable = max(0, $visitFee - $discount);
        $status = ($netPayable > 0 && $paidAmount >= $netPayable) ? 'paid' : ($paidAmount > 0 ? 'partial' : 'due');

        $upStmt = $pdo->prepare(
            "UPDATE zimrx_payments 
             SET paid_amount = :paid, discount = :disc, discount_note = :disc_note, 
                 payment_method = :method, payment_status = :status, notes = :notes,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $upStmt->execute([
            'paid' => $paidAmount,
            'disc' => $discount,
            'disc_note' => $discountNote,
            'method' => $paymentMethod,
            'status' => $status,
            'notes' => $notes,
            'id' => $paymentId
        ]);

        // Sync to appointment if linked
        if (!empty($curr['appointment_id'])) {
            $syncStmt = $pdo->prepare(
                "UPDATE zimrx_appointments 
                 SET paid_amount = :paid, discount = :disc, discount_note = :disc_note, payment_updated_at = CURRENT_TIMESTAMP
                 WHERE id = :aid"
            );
            $syncStmt->execute([
                'paid' => $paidAmount,
                'disc' => $discount,
                'disc_note' => $discountNote,
                'aid' => (int)$curr['appointment_id']
            ]);
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $paymentId,
                'paid_amount' => $paidAmount,
                'discount' => $discount,
                'net_payable' => $netPayable,
                'due_amount' => max(0, $netPayable - $paidAmount),
                'payment_method' => $paymentMethod,
                'payment_status' => $status
            ]
        ]);
        exit;
    }

    if ($action === 'create_transaction') {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $patientName = trim((string)($_POST['patient_name'] ?? ''));
        $serviceType = trim((string)($_POST['service_type'] ?? 'Consultation'));
        $visitFee = max(0, (float)($_POST['visit_fee'] ?? 0));
        $discount = max(0, (float)($_POST['discount'] ?? 0));
        $discountNote = trim((string)($_POST['discount_note'] ?? ''));
        $paidAmount = max(0, (float)($_POST['paid_amount'] ?? 0));
        $paymentMethod = trim((string)($_POST['payment_method'] ?? 'Cash'));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if (!$patientName && $patientId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Patient information required']);
            exit;
        }

        // If no existing patient ID but name provided, try to find or create
        if ($patientId <= 0 && $patientName) {
            $fStmt = $pdo->prepare("SELECT id FROM zimrx_patients WHERE full_name = :name LIMIT 1");
            $fStmt->execute(['name' => $patientName]);
            $foundId = $fStmt->fetchColumn();
            if ($foundId) {
                $patientId = (int)$foundId;
            } else {
                $regNo = 'REG-' . date('ymd') . '-' . rand(100, 999);
                $insP = $pdo->prepare("INSERT INTO zimrx_patients (full_name, reg_no, created_at) VALUES (:name, :reg, CURRENT_TIMESTAMP)");
                $insP->execute(['name' => $patientName, 'reg' => $regNo]);
                $patientId = (int)$pdo->lastInsertId();
            }
        }

        $netPayable = max(0, $visitFee - $discount);
        $status = ($netPayable > 0 && $paidAmount >= $netPayable) ? 'paid' : ($paidAmount > 0 ? 'partial' : 'due');

        $insStmt = $pdo->prepare(
            "INSERT INTO zimrx_payments 
             (patient_id, doctor_id, visit_fee, discount, discount_note, paid_amount, payment_method, payment_status, service_type, notes, created_at, updated_at)
             VALUES (:pid, :doc, :fee, :disc, :disc_note, :paid, :method, :status, :service, :notes, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
        );
        $insStmt->execute([
            'pid' => $patientId,
            'doc' => $doctorId,
            'fee' => $visitFee,
            'disc' => $discount,
            'disc_note' => $discountNote,
            'paid' => $paidAmount,
            'method' => $paymentMethod,
            'status' => $status,
            'service' => $serviceType,
            'notes' => $notes
        ]);

        $newId = (int)$pdo->lastInsertId();
        $receiptNo = sprintf('INV-%s-%04d', date('Y'), $newId);
        $upReceipt = $pdo->prepare("UPDATE zimrx_payments SET receipt_no = :rno WHERE id = :id");
        $upReceipt->execute(['rno' => $receiptNo, 'id' => $newId]);

        echo json_encode([
            'success' => true,
            'payment_id' => $newId,
            'receipt_no' => $receiptNo
        ]);
        exit;
    }

    if ($action === 'get_receipt') {
        $paymentId = (int)($_GET['payment_id'] ?? 0);
        if ($paymentId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid payment ID']);
            exit;
        }

        $stmt = $pdo->prepare(
            "SELECT 
                 p.*,
                 coalesce(nullif(pat.full_name, ''), app.patient_name, 'Patient #' || p.patient_id) AS patient_name,
                 coalesce(nullif(pat.reg_no, ''), app.reg_no, '') AS reg_no,
                 coalesce(nullif(pat.mobile, ''), app.mobile, '') AS mobile,
                 pat.age,
                 pat.gender,
                 pat.address
             FROM zimrx_payments p
             LEFT JOIN zimrx_patients pat ON pat.id = p.patient_id
             LEFT JOIN zimrx_appointments app ON app.id = p.appointment_id
             WHERE p.id = :id"
        );
        $stmt->execute(['id' => $paymentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'Receipt not found']);
            exit;
        }

        // Get doctor profile info
        $docStmt = $pdo->prepare("SELECT * FROM zimrx_doctors WHERE id = :id LIMIT 1");
        $docStmt->execute(['id' => (int)($row['doctor_id'] ?: $doctorId)]);
        $doc = $docStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        echo json_encode([
            'success' => true,
            'receipt' => [
                'receipt_no' => $row['receipt_no'] ?: sprintf('INV-%s-%04d', date('Y'), $row['id']),
                'date' => date('d M Y, h:i A', strtotime($row['created_at'])),
                'doctor_name' => $doc['name'] ?? 'Consultant Physician',
                'doctor_qualifications' => $doc['qualifications'] ?? 'MBBS',
                'doctor_speciality' => $doc['speciality'] ?? 'General Medicine',
                'clinic_name' => 'ZimRx Healthcare & Consultation',
                'patient_name' => $row['patient_name'],
                'reg_no' => $row['reg_no'],
                'mobile' => $row['mobile'],
                'age_gender' => trim(($row['age'] ?? '') . ' / ' . ($row['gender'] ?? '')),
                'service_type' => $row['service_type'] ?: 'Doctor Consultation',
                'gross_fee' => (float)$row['visit_fee'],
                'discount' => (float)$row['discount'],
                'discount_note' => $row['discount_note'] ?? '',
                'net_payable' => max(0, (float)$row['visit_fee'] - (float)$row['discount']),
                'paid_amount' => (float)$row['paid_amount'],
                'due_amount' => max(0, (float)$row['visit_fee'] - (float)$row['discount'] - (float)$row['paid_amount']),
                'payment_method' => $row['payment_method'] ?: 'Cash',
                'payment_status' => strtoupper($row['payment_status'] ?: 'PAID'),
                'notes' => $row['notes'] ?? ''
            ]
        ]);
        exit;
    }

    if ($action === 'search_patients') {
        $q = trim((string)($_GET['q'] ?? ''));
        if (strlen($q) < 1) {
            echo json_encode(['success' => true, 'patients' => []]);
            exit;
        }

        $stmt = $pdo->prepare(
            "SELECT id, full_name, reg_no, mobile, age, gender 
             FROM zimrx_patients 
             WHERE full_name LIKE :q OR mobile LIKE :q OR reg_no LIKE :q
             ORDER BY id DESC LIMIT 10"
        );
        $stmt->execute(['q' => "%{$q}%"]);
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'patients' => $patients]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
