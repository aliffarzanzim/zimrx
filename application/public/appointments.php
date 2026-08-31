<?php
declare(strict_types=1);

// Appointments & queue controller: loads doctor context, daily queue, and followup statistics.

require_once __DIR__ . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/emr_identity_lib.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/AppointmentService.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/PracticeProfileService.php';

zimrx_ensure_visit_identity_schema($pdo);

$appointmentService = new AppointmentService($pdo);
$activePracticeProfile = (new \ZimRx\Services\PracticeProfileService($pdo))->activeForCurrentUser();
$activeProfileId = (int)($activePracticeProfile['id'] ?? 0);

$initialDoctorId = (int)($activePracticeProfile['doctor_id'] ?? 0);
if ($initialDoctorId <= 0 || $activeProfileId <= 0) {
    http_response_code(409);
    echo 'Create or select an active practice profile before using appointments.';
    exit;
}

$initialQueueDateIso = AppointmentService::dmyToIso(date('d/m/Y'));
$initialQueueDateDisplay = AppointmentService::isoToDmy($initialQueueDateIso);

$targetDoctorId = $initialDoctorId;
$initialSettings = $appointmentService->getSettings($targetDoctorId, $activeProfileId);
$initialAppointments = $initialDoctorId > 0
    ? $appointmentService->getDailyQueue($initialDoctorId, $initialQueueDateIso, $initialSettings, $activeProfileId)
    : [];
$initialFollowups = $initialDoctorId > 0
    ? $appointmentService->getFollowupPatients($initialDoctorId, $activeProfileId)
    : [];

$initialAppointmentNo = $initialDoctorId > 0
    ? (string)$appointmentService->getNextAppointmentNo($initialDoctorId, $initialQueueDateIso, $initialSettings, $activeProfileId)
    : '';

$initialAppointmentTime = ($initialDoctorId > 0 && $initialAppointmentNo !== '') 
    ? $appointmentService->calculateTime((int)$initialAppointmentNo, $initialQueueDateIso, $initialSettings) 
    : '';

$stats = $appointmentService->calculateStatistics($initialAppointments, $initialFollowups);
$totalAppointments = $stats['total_appointments'];
$pendingCount      = $stats['pending_count'];
$doneCount         = $stats['done_count'];
$totalCollected    = $stats['total_collected'];
$totalFollowups    = $stats['total_followups'];
$dueTodayCount     = $stats['due_today_count'];
$upcomingCount     = $stats['upcoming_count'];
$overdueCount      = $stats['overdue_count'];
$appointmentPageSection = $appointmentPageSection ?? 'queue';
$section_navigation_group = 'appointments';
$section_navigation_active = $appointmentPageSection;
$section_navigation_counts = [
    'queue' => $totalAppointments,
    'followups' => $totalFollowups,
];

$queueFollowupPatient = null;
if ($appointmentPageSection === 'queue') {
    $requestedFollowupVisitId = filter_input(INPUT_GET, 'queue_followup_id', FILTER_VALIDATE_INT);
    if ($requestedFollowupVisitId !== false && $requestedFollowupVisitId !== null && $requestedFollowupVisitId > 0) {
        foreach ($initialFollowups as $followup) {
            if ((int)$followup['visit_id'] === $requestedFollowupVisitId) {
                $queueFollowupPatient = [
                    'patient_id' => (int)$followup['patient_id'],
                    'reg_no' => (string)$followup['reg_no'],
                    'patient_name' => (string)$followup['patient_name'],
                    'mobile' => (string)$followup['mobile'],
                    'age' => (string)$followup['age'],
                    'gender' => (string)$followup['gender'],
                    'address' => (string)$followup['address'],
                ];
                break;
            }
        }
    }
}

$page_title = $appointmentPageSection === 'followups' ? 'ZimRx - Follow-up Patients' : 'ZimRx - Appointments';
$body_class = trim(($body_class ?? '') . ($appointmentPageSection === 'queue' ? ' zimrx-appointments-hold' : ''));
$extra_css = [
    'assets/css/modules/pres_particulars.css',
    'assets/css/modules/modals_dialogs.css',
    'assets/css/pages/appointments.css',
];

include 'header.php';
if ($appointmentPageSection === 'followups') {
    require ZIMRX_BASE_DIR . '/views/followups_view.php';
} else {
    require ZIMRX_BASE_DIR . '/views/appointments_view.php';
}
include 'footer.php';
