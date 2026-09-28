<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';

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
    if (empty($_FILES['stamp_image']) || !is_array($_FILES['stamp_image'])) {
        throw new RuntimeException('No file received.');
    }

    $file = $_FILES['stamp_image'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed.');
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
    ];

    if (!isset($allowedExts[$ext])) {
        throw new RuntimeException('Only SVG, PNG, or JPG seal/stamp images are allowed.');
    }

    // Additional security checks
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
        ];
        if (!in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('Invalid image file format.');
        }
    }

    $doctorId = current_user_doctor_id();
    $targetDir = ZIMRX_UPLOADS_DIR . '/seal-and-stamps';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0750, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Unable to create seal-and-stamps upload directory.');
    }

    $filename = sprintf('doctor-%d-%d.%s', $doctorId, time(), $allowedExts[$ext]);
    $targetPath = $targetDir . '/' . $filename;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('Could not save uploaded image.');
    }

    $publicPath = '/uploads/seal-and-stamps/' . $filename;

    echo json_encode([
        'ok' => true,
        'url' => $publicPath,
    ]);
} catch (RuntimeException $e) {
    error_log('[ZimRx] upload_seal_and_stamp error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'An error occurred while uploading seal/stamp.']);
} catch (Throwable $e) {
    error_log('[ZimRx] upload_seal_and_stamp error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'An error occurred while uploading seal/stamp.']);
}
