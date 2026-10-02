<?php
// Upload and sanitize prescription pad background images (SVG, PNG, JPEG).
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_login();

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
    if (empty($_FILES['bg_image']) || !is_array($_FILES['bg_image'])) {
        throw new RuntimeException('No file received.');
    }

    $file = $_FILES['bg_image'];
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
        throw new RuntimeException('Only SVG, PNG, or JPG background images are allowed.');
    }

    // Validate image MIME type and sanitize SVG markup
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
    $targetDir = ZIMRX_UPLOADS_DIR . '/background-images';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0750, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Unable to create background-images upload directory.');
    }

    $filename = sprintf('doctor-%d-%d-%s.%s', $doctorId, time(), bin2hex(random_bytes(8)), $allowedExts[$ext]);
    $targetPath = $targetDir . '/' . $filename;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('Could not save uploaded image.');
    }

    $publicPath = '/uploads/background-images/' . $filename;

    echo json_encode([
        'ok' => true,
        'url' => $publicPath,
    ]);
} catch (RuntimeException $e) {
    error_log('[ZimRx] upload_background_image error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'An error occurred while uploading background image.']);
} catch (Throwable $e) {
    error_log('[ZimRx] upload_background_image error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'An error occurred while uploading background image.']);
}
