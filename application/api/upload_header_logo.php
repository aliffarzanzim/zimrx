<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

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

    $doctorId = current_user_doctor_id();
    $targetDir = ZIMRX_UPLOADS_DIR . '/header-logos';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Unable to create header logo directory.');
    }

    $filename = sprintf('doctor-%d-%d.%s', $doctorId, time(), $allowed[$mime]);
    $targetPath = $targetDir . '/' . $filename;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('Could not save uploaded logo.');
    }

    $publicPath = '/uploads/header-logos/' . $filename;
    $chkStmt = $pdo->prepare("SELECT COUNT(*) FROM zimrx_prescription_header_settings WHERE doctor_id = :doctor_id");
    $chkStmt->execute(['doctor_id' => $doctorId]);
    $exists = (int)$chkStmt->fetchColumn() > 0;

    if (!$exists) {
        try {
            $pdo->prepare(
                "INSERT INTO zimrx_prescription_header_settings (doctor_id, doctor_name) VALUES (:doctor_id, :doctor_name)"
            )->execute(['doctor_id' => $doctorId, 'doctor_name' => current_user_name()]);
        } catch (PDOException $e) {
            // Ignore if inserted concurrently
        }
    }

    $stmt = $pdo->prepare(
        "UPDATE zimrx_prescription_header_settings
         SET logo_path = :logo_path,
             updated_at = CURRENT_TIMESTAMP
         WHERE doctor_id = :doctor_id"
    );
    $stmt->execute([
        'logo_path' => $publicPath,
        'doctor_id' => $doctorId,
    ]);

    echo json_encode([
        'ok' => true,
        'logo_path' => $publicPath,
    ]);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
