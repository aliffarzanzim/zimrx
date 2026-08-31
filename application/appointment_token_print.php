<?php
require_once 'auth.php';
require_login();
require_once 'db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Appointment id is required.';
    exit;
}

function token_settings(PDO $pdo, int $doctorId): array {
    $defaults = [
        'token_fields' => ['name', 'age', 'sex', 'reg', 'visit_no', 'visit_id', 'visit_fee', 'discount', 'paid'],
    ];
    $stmt = $pdo->prepare("SELECT settings_json FROM zimrx_appointment_settings WHERE doctor_id = :doctor_id LIMIT 1");
    $stmt->execute(['doctor_id' => $doctorId]);
    $row = $stmt ? $stmt->fetch() : null;
    if (!$row) {
        return $defaults;
    }
    $settings = json_decode((string)$row['settings_json'], true);
    if (!is_array($settings)) {
        return $defaults;
    }
    return array_merge($defaults, $settings);
}

function money_text($value): string {
    $amount = (float)($value ?? 0);
    return fmod($amount, 1.0) === 0.0 ? (string)(int)$amount : number_format($amount, 2);
}

function token_date(?string $date, ?string $time): string {
    $dt = DateTime::createFromFormat('Y-m-d H:i', trim((string)$date . ' ' . (string)$time));
    if (!$dt) {
        $dt = DateTime::createFromFormat('Y-m-d', (string)$date);
    }
    return $dt ? $dt->format('d-M-Y h:i A') : '';
}

$requestedDoctorId = (int)($_GET['doctor_id'] ?? 0);
$allowedDoctors = zimrx_doctor_options_for_user($pdo, current_user_id(), current_user_role(), current_user_doctor_id());
$allowedDoctorIds = array_map(fn($row) => (int)$row['id'], $allowedDoctors);
$doctorId = in_array($requestedDoctorId, $allowedDoctorIds, true)
    ? $requestedDoctorId
    : (count($allowedDoctorIds) === 1 ? $allowedDoctorIds[0] : current_user_doctor_id());
$stmt = $pdo->prepare(
    "SELECT a.*, p.full_name, p.reg_no AS patient_reg_no
     FROM zimrx_appointments a
     LEFT JOIN zimrx_patients p ON p.id = a.patient_id
     WHERE a.id = :id AND a.doctor_id = :doctor_id"
);
$stmt->execute(['id' => $id, 'doctor_id' => $doctorId]);
$appointment = $stmt->fetch();
if (!$appointment) {
    http_response_code(404);
    echo 'Appointment not found.';
    exit;
}

$settings = token_settings($pdo, (int)($appointment['doctor_id'] ?? $doctorId));
$fields = array_flip($settings['token_fields'] ?? []);
$doctorStmt = $pdo->prepare("SELECT display_name FROM zimrx_doctors WHERE id = :doctor_id LIMIT 1");
$doctorStmt->execute(['doctor_id' => (int)($appointment['doctor_id'] ?? $doctorId)]);
$doctor = $doctorStmt ? ($doctorStmt->fetchColumn() ?: 'Doctor') : 'Doctor';
$fee = (float)($appointment['visit_fee'] ?? 0);
$discount = (float)($appointment['discount'] ?? 0);
$paid = (float)($appointment['paid_amount'] ?? 0);
$serial = str_pad((string)($appointment['appointment_no'] ?? ''), 3, '0', STR_PAD_LEFT);

$rows = [];
if (isset($fields['name'])) $rows[] = ['Name', $appointment['patient_name'] ?: $appointment['full_name']];
if (isset($fields['age'])) $rows[] = ['Age', trim(($appointment['age'] ?? '') . ' ' . ($appointment['age_unit'] ?? ''))];
if (isset($fields['sex'])) $rows[] = ['Sex', $appointment['gender'] ?? ''];
$rows[] = ['Mobile', $appointment['mobile'] ?? ''];
$rows[] = ['Address', $appointment['address'] ?? ''];
if (isset($fields['visit_no'])) $rows[] = ['Visit No', $appointment['visit_no'] ?? ''];
if (isset($fields['visit_id'])) $rows[] = ['Visit ID', $appointment['visit_id'] ?? ''];
if (isset($fields['visit_fee'])) $rows[] = ['Visit Fee', money_text($fee)];
if (isset($fields['discount'])) $rows[] = ['Discount', money_text($discount)];
if (isset($fields['paid'])) $rows[] = ['Paid', money_text($paid)];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ZimRx Appointment Token</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <style>
        @font-face {
            font-family: SolaimanLipi;
            src: url("assets/fonts/SolaimanLipi.ttf") format("truetype");
        }
        body {
            margin: 0;
            background: #fff;
            color: #111827;
            font-family: Arial, SolaimanLipi, sans-serif;
        }
        .token-wrap {
            width: 320px;
            margin: 12px auto;
            font-size: 14px;
        }
        .token-title {
            text-align: center;
            font-weight: 800;
            line-height: 1.35;
            letter-spacing: 0.02em;
        }
        .token-date,
        .token-reg {
            margin-top: 8px;
            text-align: center;
            font-weight: 700;
        }
        table {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }
        td {
            padding: 3px 2px;
            vertical-align: top;
        }
        .label {
            width: 78px;
            font-weight: 700;
        }
        .colon {
            width: 8px;
            text-align: center;
        }
        .serial {
            margin-top: 12px;
            text-align: center;
            font-weight: 800;
            line-height: 1.45;
        }
        .serial strong {
            font-size: 34px;
            letter-spacing: 0.08em;
        }
        @media print {
            @page { size: 80mm auto; margin: 4mm; }
            .token-wrap { margin: 0 auto; }
        }
    </style>
    <script>
        window.addEventListener('load', () => window.print());
    </script>
</head>
<body>
    <div class="token-wrap">
        <div class="token-title">
            -----------------------------------------------------<br>
            APPOINTMENT TOKEN for<br>
            <?= htmlspecialchars($doctor) ?><br>
            -----------------------------------------------------
        </div>
        <div class="token-date"><?= htmlspecialchars(token_date($appointment['appointment_date'] ?? '', $appointment['appointment_time'] ?? '')) ?></div>
        <?php if (isset($fields['reg'])): ?>
        <div class="token-reg">Reg No: <?= htmlspecialchars($appointment['reg_no'] ?: $appointment['patient_reg_no']) ?></div>
        <?php endif; ?>
        <table>
            <tbody>
                <?php foreach ($rows as [$label, $value]): ?>
                <tr>
                    <td class="label"><?= htmlspecialchars($label) ?></td>
                    <td class="colon">:</td>
                    <td><?= htmlspecialchars((string)$value) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="serial">
            -----------------------------------------------------<br>
            Serial:<br>
            <strong><?= htmlspecialchars($serial) ?></strong><br>
            -----------------------------------------------------
        </div>
    </div>
</body>
</html>
