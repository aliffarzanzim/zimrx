<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/Services/PatientIpsExportService.php';

$currentDoctorId = current_user_doctor_id();
if ($currentDoctorId <= 0) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo (string)json_encode(['error' => 'Doctor scope is required to export patient records.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$patientId = (int)($_GET['patient_id'] ?? 0);
$visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : null;

if ($patientId <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo (string)json_encode(['error' => 'A valid patient_id parameter is required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = DbConnections::userdata();
    $stmt = $pdo->prepare(
        'SELECT id, reg_no, full_name FROM zimrx_patients WHERE id = :id AND doctor_id = :doctor_id LIMIT 1'
    );
    $stmt->execute(['id' => $patientId, 'doctor_id' => $currentDoctorId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo (string)json_encode(['error' => 'Patient record not found or access denied.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $exportService = new \ZimRx\Services\PatientIpsExportService($pdo);
    $ipsJson = $exportService->exportPatientIpsJson($patientId, $currentDoctorId, $visitId, true);

    $safeRegNo = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)($patient['reg_no'] ?: $patient['id']));
    $filename = "ips-patient-{$safeRegNo}.json";

    header('Content-Type: application/fhir+json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-cache, no-store, must-revalidate, private');

    echo $ipsJson;
    exit;
} catch (Throwable $e) {
    error_log('[ZimRx] IPS export error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo (string)json_encode(['error' => 'Failed to generate International Patient Summary export.'], JSON_UNESCAPED_UNICODE);
    exit;
}
