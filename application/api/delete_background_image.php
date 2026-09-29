<?php
declare(strict_types=1);

// Background image deletion endpoint: deletes doctor-specific watermark/background images.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
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
    $filename = basename(trim((string)($_POST['filename'] ?? '')));
    if ($filename === '') throw new RuntimeException('No filename provided.');

    // Only allow filenames that belong to the current doctor
    $doctorId = current_user_doctor_id();
    if ($doctorId <= 0) {
        throw new RuntimeException('A valid doctor account is required.');
    }
    $prefix   = 'doctor-' . $doctorId . '-';
    if (!str_starts_with($filename, $prefix)) {
        throw new RuntimeException('Not authorized to delete this file.');
    }

    $targetPath = ZIMRX_UPLOADS_DIR . '/background-images/' . $filename;
    if (!file_exists($targetPath)) throw new RuntimeException('File not found.');

    if (!unlink($targetPath)) throw new RuntimeException('Could not delete file.');

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('[ZimRx] delete_background_image error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'An error occurred while deleting the file.']);
}
