<?php
declare(strict_types=1);

// Appointments & queue controller: loads doctor context, daily queue, and followup statistics.

require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/visit_identity.php';
require_once __DIR__ . '/lib/Services/AppointmentService.php';

zimrx_ensure_visit_identity_schema($pdo);

$appointmentService = new AppointmentService($pdo);

$appointmentDoctorOptions = zimrx_doctor_options_for_user(
    $pdo,
    current_user_id(),
    current_user_role(),
    current_user_doctor_id()
);

$initialDoctorId = current_user_role() === 'assistant' && count($appointmentDoctorOptions) !== 1
    ? 0
    : (int)($appointmentDoctorOptions[0]['id'] ?? current_user_doctor_id());

$initialDoctorName = '';
foreach ($appointmentDoctorOptions as $doctorOption) {
    if ((int)$doctorOption['id'] === $initialDoctorId) {
        $initialDoctorName = (string)$doctorOption['display_name'];
        break;
    }
}

$initialQueueDateIso = AppointmentService::dmyToIso(date('d/m/Y'));
$initialQueueDateDisplay = AppointmentService::isoToDmy($initialQueueDateIso);

$targetDoctorId = $initialDoctorId > 0 ? $initialDoctorId : current_user_doctor_id();
$initialSettings = $appointmentService->getSettings($targetDoctorId);
$initialAppointments = $initialDoctorId > 0 
    ? $appointmentService->getDailyQueue($initialDoctorId, $initialQueueDateIso, $initialSettings) 
    : [];
$initialFollowups = $initialDoctorId > 0 
    ? $appointmentService->getFollowupPatients($initialDoctorId) 
    : [];

$initialAppointmentNo = $initialDoctorId > 0 
    ? (string)$appointmentService->getNextAppointmentNo($initialDoctorId, $initialQueueDateIso, $initialSettings) 
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

$page_title = "ZimRx - Appointments";
$body_class = trim(($body_class ?? '') . ' zimrx-appointments-hold');
$extra_css = ['assets/css/pages/appointments.css'];

include 'header.php';
require __DIR__ . '/views/appointments_view.php';
include 'footer.php';
