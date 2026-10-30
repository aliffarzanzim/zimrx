<?php
declare(strict_types=1);

// Thermal appointment slip: prints patient serial token, consultation fee, and doctor queue position.

require_once __DIR__ . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/Services/PracticeProfileService.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Appointment id is required.';
    exit;
}

function token_settings(PDO $pdo, int $profileId, int $doctorId): array {
    $defaults = [
        'token_fields' => ['name', 'age', 'sex', 'reg', 'visit_no', 'visit_id', 'visit_fee', 'discount', 'paid'],
    ];
    $settings = (new \ZimRx\Services\PracticeProfileService($pdo))->settings($profileId, $doctorId, 'appointment');
    return array_merge($defaults, is_array($settings) ? $settings : []);
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

$activePracticeProfile = (new \ZimRx\Services\PracticeProfileService($pdo))->activeForCurrentUser();
$profileId = (int)($activePracticeProfile['id'] ?? 0);
$doctorId = (int)($activePracticeProfile['doctor_id'] ?? 0);
if ($profileId <= 0 || $doctorId <= 0) {
    http_response_code(409);
    echo 'Select an active practice profile before printing an appointment token.';
    exit;
}
$stmt = $pdo->prepare(
    "SELECT a.*, p.full_name, p.reg_no AS patient_reg_no
     FROM zimrx_appointments a
     LEFT JOIN zimrx_patients p ON p.id = a.patient_id
        AND (p.profile_id = :patient_profile_id OR p.profile_id IN (
            SELECT source_profile.id FROM zimrx_doctor_profiles source_profile
            WHERE source_profile.doctor_id = :patient_profile_doctor
              AND source_profile.is_active = 1 AND source_profile.is_deleted = 0
              AND source_profile.share_patient_particulars = 1
        ))
     WHERE a.id = :id AND a.doctor_id = :doctor_id AND a.profile_id = :profile_id"
);
$stmt->execute([
    'id' => $id,
    'doctor_id' => $doctorId,
    'profile_id' => $profileId,
    'patient_profile_id' => $profileId,
    'patient_profile_doctor' => $doctorId,
]);
$appointment = $stmt->fetch();
if (!$appointment) {
    http_response_code(404);
    echo 'Appointment not found.';
    exit;
}

$settings = token_settings($pdo, $profileId, (int)($appointment['doctor_id'] ?? $doctorId));
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
    <link rel="stylesheet" href="assets/css/pages/appointment_token_print.css?v=<?= filemtime(__DIR__ . '/assets/css/pages/appointment_token_print.css') ?>">
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
