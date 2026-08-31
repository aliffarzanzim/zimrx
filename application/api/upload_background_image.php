<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

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

    // Additional checks for raster images
    if ($ext !== 'svg') {
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
    if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Unable to create background-images upload directory.');
    }

    $filename = sprintf('doctor-%d-%d.%s', $doctorId, time(), $allowedExts[$ext]);
    $targetPath = $targetDir . '/' . $filename;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('Could not save uploaded image.');
    }

    $publicPath = '/uploads/background-images/' . $filename;

    echo json_encode([
        'ok' => true,
        'url' => $publicPath,
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
