<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

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

    // Additional security checks for raster images
    if ($ext !== 'svg') {
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

    $doctorId = current_user_doctor_id();
    $targetDir = ZIMRX_UPLOADS_DIR . '/full-body-headers';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Unable to create full body header upload directory.');
    }

    $filename = sprintf('doctor-%d-%d.%s', $doctorId, time(), $allowedExts[$ext]);
    $targetPath = $targetDir . '/' . $filename;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('Could not save uploaded image.');
    }

    $publicPath = '/uploads/full-body-headers/' . $filename;
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
         SET full_body_header_path = :full_body_header_path,
             header_type = 'image',
             updated_at = CURRENT_TIMESTAMP
         WHERE doctor_id = :doctor_id"
    );
    $stmt->execute([
        'full_body_header_path' => $publicPath,
        'doctor_id' => $doctorId,
    ]);

    // Also sync header_type in print_settings_json if present
    $layoutStmt = $pdo->prepare("SELECT print_settings_json FROM zimrx_prescription_print_layout_settings WHERE doctor_id = :doctor_id LIMIT 1");
    $layoutStmt->execute(['doctor_id' => $doctorId]);
    $advJson = $layoutStmt->fetchColumn();
    $advData = json_decode((string)($advJson ?: '{}'), true);
    if (!is_array($advData)) {
        $advData = [];
    }
    $advData['header_type'] = 'image';
    $pdo->prepare("UPDATE zimrx_prescription_print_layout_settings SET print_settings_json = :json WHERE doctor_id = :doctor_id")
        ->execute(['json' => json_encode($advData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'doctor_id' => $doctorId]);

    echo json_encode([
        'ok' => true,
        'full_body_header_path' => $publicPath,
        'message' => 'সফলভাবে ফুলবডি ইমেজ হেডার আপলোড সম্পন্ন হয়েছে।',
    ]);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
