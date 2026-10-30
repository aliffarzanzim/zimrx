<?php
// Upload and configure full-body image prescription header for the active doctor.
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
    if (empty($_FILES['image_body']) || !is_array($_FILES['image_body'])) {
        throw new RuntimeException('প্রথমে ফাইল সিলেক্ট করুন এরপর আপলোড বাটন চাপুন।');
    }

    $file = $_FILES['image_body'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload failed.');
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('Invalid upload.');
    }

    $origName = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    $allowedExts = [
        'svg'  => 'svg',
        'png'  => 'png',
        'jpg'  => 'jpg',
        'jpeg' => 'jpg',
        'webp' => 'webp',
    ];

    if (!isset($allowedExts[$ext])) {
        throw new RuntimeException('Only SVG, PNG, JPG, or WEBP images are allowed.');
    }

    // Validate file integrity, MIME type, and safe SVG structure
    if ($ext === 'svg') {
        if (!zimrx_validate_safe_svg($tmpPath)) {
            throw new RuntimeException('Invalid or potentially unsafe SVG file.');
        }
    } else {
        $imageInfo = @getimagesize($tmpPath);
        $mime = (string)($imageInfo['mime'] ?? '');
        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];
        if (!in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('Invalid image file format.');
        }
    }

    $profile = (new \ZimRx\Services\PracticeProfileService($pdo))->activeForCurrentUser();
    $profileId = (int)($profile['id'] ?? 0);
    $doctorId = (int)($profile['doctor_id'] ?? 0);
    if ($profileId <= 0 || $doctorId <= 0) {
        throw new RuntimeException('An active practice profile is required.');
    }
    $targetDir = ZIMRX_UPLOADS_DIR . '/full-body-headers';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0750, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Unable to create full body header upload directory.');
    }

    $filename = sprintf('profile-%d-%d-%s.%s', $profileId, time(), bin2hex(random_bytes(8)), $allowedExts[$ext]);
    $targetPath = $targetDir . '/' . $filename;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('Could not save uploaded image.');
    }

    $publicPath = '/uploads/full-body-headers/' . $filename;
    zimrx_print_save_setup($pdo, $profileId, [
        'full_body_header_path' => $publicPath,
        'header_type' => 'image',
    ]);

    echo json_encode([
        'ok' => true,
        'full_body_header_path' => $publicPath,
        'message' => 'সফলভাবে ফুলবডি ইমেজ হেডার আপলোড সম্পন্ন হয়েছে।',
    ]);
} catch (RuntimeException $e) {
    error_log('[ZimRx] upload_full_body_header error: ' . $e->getMessage());
    echo json_encode(['error' => 'An error occurred while uploading full body header.']);
} catch (Throwable $e) {
    error_log('[ZimRx] upload_full_body_header error: ' . $e->getMessage());
    echo json_encode(['error' => 'An error occurred while uploading full body header.']);
}
