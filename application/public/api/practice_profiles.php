<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/Services/PracticeProfileService.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/PracticeProfileLifecycleService.php';

header('Content-Type: application/json; charset=utf-8');

function practice_profile_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$service = new \ZimRx\Services\PracticeProfileService($pdo);
$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? 'list'));

try {
    if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        practice_profile_response(['ok' => true, 'profiles' => $service->availableForCurrentUser()]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        practice_profile_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
    }
    if (!zimrx_verify_csrf()) {
        practice_profile_response(['ok' => false, 'error' => 'CSRF verification failed.'], 403);
    }

    if ($action === 'activate') {
        $profile = $service->activate((int)($_POST['profile_id'] ?? 0));
        practice_profile_response(['ok' => true, 'profile' => $profile]);
    }

    if (!in_array(current_user_role(), ['doctor', 'admin', 'superadmin'], true)) {
        practice_profile_response(['ok' => false, 'error' => 'Only doctors and administrators can manage profiles.'], 403);
    }

    $doctorId = (int)($_POST['doctor_id'] ?? current_user_doctor_id());
    if ($action === 'save') {
        $profileId = $service->save((int)($_POST['profile_id'] ?? 0), $doctorId, $_POST);
        practice_profile_response(['ok' => true, 'profile_id' => $profileId]);
    }
    if ($action === 'archive') {
        $service->archive((int)($_POST['profile_id'] ?? 0), $doctorId);
        practice_profile_response(['ok' => true]);
    }
    if ($action === 'reactivate') {
        $service->reactivate((int)($_POST['profile_id'] ?? 0), $doctorId);
        practice_profile_response(['ok' => true]);
    }
    if ($action === 'remove') {
        (new \ZimRx\Services\PracticeProfileLifecycleService($pdo))->removeProfile(
            (int)($_POST['profile_id'] ?? 0),
            $doctorId
        );
        practice_profile_response(['ok' => true]);
    }
    if ($action === 'delete_data') {
        (new \ZimRx\Services\PracticeProfileLifecycleService($pdo))->deleteProfileAndPatientData(
            (int)($_POST['profile_id'] ?? 0),
            $doctorId,
            trim((string)($_POST['confirmation'] ?? ''))
        );
        practice_profile_response(['ok' => true]);
    }

    practice_profile_response(['ok' => false, 'error' => 'Unknown action.'], 400);
} catch (RuntimeException|\InvalidArgumentException $error) {
    practice_profile_response(['ok' => false, 'error' => $error->getMessage()], 422);
} catch (Throwable $error) {
    error_log('[ZimRx] Practice profile request failed: ' . $error->getMessage());
    practice_profile_response(['ok' => false, 'error' => 'The practice profile request could not be completed.'], 500);
}
