<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();

header('Content-Type: application/json');

try {
    $filename = basename(trim((string)($_POST['filename'] ?? '')));
    if ($filename === '') throw new RuntimeException('No filename provided.');

    // Only allow filenames that belong to the current doctor
    $doctorId = current_user_doctor_id();
    $prefix   = 'doctor-' . $doctorId . '-';
    if (!str_starts_with($filename, $prefix)) {
        throw new RuntimeException('Not authorized to delete this file.');
    }

    $targetPath = ZIMRX_UPLOADS_DIR . '/background-images/' . $filename;
    if (!file_exists($targetPath)) throw new RuntimeException('File not found.');

    if (!unlink($targetPath)) throw new RuntimeException('Could not delete file.');

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
