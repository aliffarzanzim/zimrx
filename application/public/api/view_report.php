<?php
declare(strict_types=1);

// Authenticated clinical report viewer and streaming service with strict tenant isolation and path traversal guards.

require_once dirname(__DIR__) . '/init.php';

$doctorId = current_user_doctor_id();
if ($doctorId <= 0) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Authentication required to access patient clinical reports.']);
    exit();
}

$rawFile = (string)($_GET['file'] ?? '');
if ($rawFile === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Missing file parameter.']);
    exit();
}

// Sanitize filename against directory traversal
$fileName = basename(urldecode($rawFile));

$configuredReportsDir = defined('ZIMRX_UPLOADS_DIR')
    ? (ZIMRX_UPLOADS_DIR . '/reports')
    : (ZIMRX_BASE_DIR . '/userdata/uploads/reports');
if (!is_dir($configuredReportsDir)) {
    @mkdir($configuredReportsDir, 0750, true);
}
$reportsDir = realpath($configuredReportsDir);
if (!$reportsDir) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Clinical reports directory is not configured.']);
    exit();
}

$targetPath = $reportsDir . DIRECTORY_SEPARATOR . $fileName;
$realFilePath = realpath($targetPath);

if (!$realFilePath || !file_exists($realFilePath) || !str_starts_with($realFilePath, $reportsDir)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Report file not found.']);
    exit();
}

// Verify doctor ownership or assistant permission
if (preg_match('/^report-(\d+)-/', $fileName, $matches)) {
    $ownerDoctorId = (int)$matches[1];
    if ($ownerDoctorId !== $doctorId) {
        $hasPermission = false;
        try {
            $pdo = db();
            $stmt = $pdo->prepare(
                "SELECT 1 FROM zimrx_doctor_assistants
                 WHERE doctor_id = :owner_doctor AND assistant_id = :user_id LIMIT 1"
            );
            $stmt->execute(['owner_doctor' => $ownerDoctorId, 'user_id' => current_user_id()]);
            if ($stmt->fetchColumn()) {
                $hasPermission = true;
            }
        } catch (Throwable) {
            $hasPermission = false;
        }

        if (!$hasPermission) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Access denied: clinical report belongs to another practitioner.']);
            exit();
        }
    }
}

// Determine MIME type and stream inline
$ext = strtolower(pathinfo($realFilePath, PATHINFO_EXTENSION));
$mimeTypes = [
    'pdf'  => 'application/pdf',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
];
$contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $contentType);
header('Content-Length: ' . (string)filesize($realFilePath));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . rawurlencode($fileName) . '"');
header('Cache-Control: private, no-transform, max-age=3600');

readfile($realFilePath);
exit();
