<?php
// Upload and update header brand logo for the active doctor.
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/print_setup_lib.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!zimrx_verify_csrf()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF verification failed.']);
    exit;
}

try {
    if (empty($_FILES['logo']) || !is_array($_FILES['logo'])) {
        throw new RuntimeException('No logo file received.');
    }

    $file = $_FILES['logo'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Logo upload failed.');
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('Invalid upload.');
    }

    $imageInfo = @getimagesize($tmpPath);
    $mime = (string)($imageInfo['mime'] ?? '');
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, GIF, or WEBP logos are allowed.');
    }

    $profile = (new \ZimRx\Services\PracticeProfileService($pdo))->activeForCurrentUser();
    $profileId = (int)($profile['id'] ?? 0);
    $doctorId = (int)($profile['doctor_id'] ?? 0);
    if ($profileId <= 0 || $doctorId <= 0) {
        throw new RuntimeException('An active practice profile is required.');
    }
    $targetDir = ZIMRX_UPLOADS_DIR . '/header-logos';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0750, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Unable to create header logo directory.');
    }

    $filename = sprintf('profile-%d-%d-%s.%s', $profileId, time(), bin2hex(random_bytes(8)), $allowed[$mime]);
    $targetPath = $targetDir . '/' . $filename;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('Could not save uploaded logo.');
    }

    $publicPath = '/uploads/header-logos/' . $filename;
    zimrx_print_save_setup($pdo, $profileId, ['logo_path' => $publicPath]);

    echo json_encode([
        'ok' => true,
        'logo_path' => $publicPath,
    ]);
} catch (RuntimeException $e) {
    error_log('[ZimRx] upload_header_logo error: ' . $e->getMessage());
    echo json_encode(['error' => 'An error occurred while uploading header logo.']);
} catch (Throwable $e) {
    error_log('[ZimRx] upload_header_logo error: ' . $e->getMessage());
    echo json_encode(['error' => 'An error occurred while uploading header logo.']);
}
