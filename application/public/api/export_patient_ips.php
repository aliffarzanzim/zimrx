<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/Services/PatientIpsExportService.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/PracticeProfileService.php';

$activeProfile = (new \ZimRx\Services\PracticeProfileService(DbConnections::userdata()))->activeForCurrentUser();
$currentDoctorId = (int)($activeProfile['doctor_id'] ?? 0);
$currentProfileId = (int)($activeProfile['id'] ?? 0);
if ($currentDoctorId <= 0 || $currentProfileId <= 0) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo (string)json_encode(['error' => 'Doctor scope is required to export patient records.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$patientId = (int)($_GET['patient_id'] ?? 0);
$visitId = null;
if (isset($_GET['visit_id'])) {
    $parsedVisitId = filter_var($_GET['visit_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($parsedVisitId === false) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo (string)json_encode(['error' => 'A valid visit_id parameter is required.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $visitId = $parsedVisitId;
}

if ($patientId <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo (string)json_encode(['error' => 'A valid patient_id parameter is required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = DbConnections::userdata();
    $stmt = $pdo->prepare(
        'SELECT id, reg_no, full_name FROM zimrx_patients p
         WHERE p.id = :id AND p.doctor_id = :doctor_id
           AND (p.profile_id = :profile_id OR p.profile_id IN (
               SELECT source_profile.id FROM zimrx_doctor_profiles source_profile
               WHERE source_profile.doctor_id = :share_doctor_id
                 AND source_profile.is_active = 1 AND source_profile.share_patient_particulars = 1
           ))
         LIMIT 1'
    );
    $stmt->execute([
        'id' => $patientId,
        'doctor_id' => $currentDoctorId,
        'profile_id' => $currentProfileId,
        'share_doctor_id' => $currentDoctorId,
    ]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo (string)json_encode(['error' => 'Patient record not found or access denied.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($visitId !== null && $visitId > 0) {
        $visitStmt = $pdo->prepare(
            'SELECT 1 FROM zimrx_visits v
             WHERE v.id = :visit_id AND v.patient_id = :patient_id AND v.doctor_id = :doctor_id
               AND (v.profile_id = :profile_id OR v.profile_id IN (
                   SELECT source_profile.id FROM zimrx_doctor_profiles source_profile
                   WHERE source_profile.doctor_id = :share_doctor_id
                     AND source_profile.is_active = 1 AND source_profile.share_prescriptions = 1
               ))
             LIMIT 1'
        );
        $visitStmt->execute([
            'visit_id' => $visitId,
            'patient_id' => $patientId,
            'doctor_id' => $currentDoctorId,
            'profile_id' => $currentProfileId,
            'share_doctor_id' => $currentDoctorId,
        ]);
        if ($visitStmt->fetchColumn() === false) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo (string)json_encode(['error' => 'Visit record not found or access denied.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $exportService = new \ZimRx\Services\PatientIpsExportService($pdo);
    $ipsJson = $exportService->exportPatientIpsJson($patientId, $currentDoctorId, $currentProfileId, $visitId, true);

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
