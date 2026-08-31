<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();

header('Content-Type: application/json');

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

    $targetDir = ZIMRX_UPLOADS_DIR . '/reports';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Unable to create reports directory.');
    }

    $filename = sprintf('report-%d-%d.%s', current_user_id(), time(), $ext);
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
    echo json_encode(['error' => $e->getMessage()]);
}
