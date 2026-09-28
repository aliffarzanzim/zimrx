<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

if (!zimrx_verify_csrf()) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF verification failed.']);
    exit;
}

try {
    if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
        throw new RuntimeException('No file received.');
    }

    $file = $_FILES['file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed.');
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('Invalid upload.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
    
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Only JPG, PNG, GIF, WEBP, or PDF files are allowed.');
    }

    if ($ext === 'pdf') {
        $magic = @file_get_contents($tmpPath, false, null, 0, 5);
        if ($magic !== '%PDF-') {
            throw new RuntimeException('Invalid PDF file content.');
        }
    } else {
        $imageInfo = @getimagesize($tmpPath);
        if (!$imageInfo) {
            throw new RuntimeException('Invalid image file content.');
        }
    }

    $targetDir = ZIMRX_UPLOADS_DIR . '/reports';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0750, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Unable to create reports directory.');
    }

    $filename = sprintf(
        'report-%d-%d-%s.%s',
        current_user_id(),
        time(),
        bin2hex(random_bytes(8)),
        $ext
    );
    $targetPath = $targetDir . '/' . $filename;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('Could not save uploaded file.');
    }

    $publicPath = 'uploads/reports/' . $filename;

    echo json_encode([
        'ok' => true,
        'file_path' => $publicPath,
        'original_name' => $file['name']
    ]);
} catch (Throwable $e) {
    error_log('[ZimRx] upload_report error: ' . $e->getMessage());
    echo json_encode(['error' => 'An error occurred while uploading the report.']);
}
