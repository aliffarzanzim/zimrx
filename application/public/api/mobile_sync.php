<?php
declare(strict_types=1);

// Mobile sync API: mirrors active desktop patient context and receives mobile photo uploads.


require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/vendor/autoload.php';

use chillerlan\QRCode\QRCode;

header('Content-Type: application/json');

$cacheDir = ZIMRX_USERDATA_DIR . '/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0750, true);
}

$doctorId = current_user_doctor_id();
$doctorName = current_user_name();
$activePatientFile = $cacheDir . '/doctor_' . $doctorId . '_patient.json';

// Verify mobile active context table exists before processing requests
function zimrx_assert_mobile_context_table(PDO $pdo): void {
    if (!DbSchema::tableExists($pdo, 'zimrx_mobile_active_context')) {
        throw new RuntimeException(
            'zimrx_mobile_active_context table is missing. ' .
            'Run database migrations before using the mobile sync API.'
        );
    }
}

// Detect server LAN IP across Windows, Linux, and macOS (strictly offline with zero remote telemetry)
function zimrx_get_server_lan_ip(): string {
    // 1. Inspect server-bound address if bound to a local LAN interface
    $serverAddr = $_SERVER['SERVER_ADDR'] ?? '';
    if (!empty($serverAddr) && filter_var($serverAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        if (!str_starts_with($serverAddr, '127.') && !str_starts_with($serverAddr, '169.254.')) {
            return $serverAddr;
        }
    }

    $hostname = @gethostname();
    if ($hostname) {
        $ips = @gethostbynamel($hostname);
        if ($ips) {
            foreach ($ips as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !str_starts_with($ip, '127.') && !str_starts_with($ip, '169.254.')) {
                    return $ip;
                }
            }
        }
        $ip = @gethostbyname($hostname);
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !str_starts_with($ip, '127.') && !str_starts_with($ip, '169.254.')) {
            return $ip;
        }
    }

    // Best-effort LAN adapter discovery via static OS commands
    if (function_exists('shell_exec')) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = @shell_exec('ipconfig 2>nul');
            if ($output && preg_match_all('/IPv4 Address[.\s]+:\s*([0-9.]+)/i', $output, $matches)) {
                foreach ($matches[1] as $ip) {
                    if (!str_starts_with($ip, '127.') && !str_starts_with($ip, '169.254.')) {
                        return trim($ip);
                    }
                }
            }
        } else {
            $output = @shell_exec("hostname -I 2>/dev/null || ip route get 1 2>/dev/null | awk '{print $7;exit}' || ifconfig 2>/dev/null");
            if ($output && preg_match_all('/\b(192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+|172\.(?:1[6-9]|2\d|3[01])\.\d+\.\d+)\b/', $output, $matches)) {
                foreach ($matches[0] as $ip) {
                    return trim($ip);
                }
            }
        }
    }

    return '127.0.0.1';
}

function zimrx_get_mobile_url(): string {
    if (defined('ZIMRX_PUBLIC_ORIGIN') && !empty(ZIMRX_PUBLIC_ORIGIN)) {
        $origin = rtrim((string)ZIMRX_PUBLIC_ORIGIN, '/');
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
        $appPath = dirname(dirname($scriptPath));
        if ($appPath === '/' || $appPath === '\\') {
            $appPath = '';
        }
        return $origin . $appPath . '/mobile_upload.php';
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

    $port = '';
    if (isset($_SERVER['SERVER_PORT']) && !in_array((int)$_SERVER['SERVER_PORT'], [80, 443], true)) {
        $port = ':' . (int)$_SERVER['SERVER_PORT'];
    }

    // Validate server LAN IP; never trust unvalidated client HTTP_HOST header
    $lanIp = zimrx_get_server_lan_ip();
    $host = ($lanIp && $lanIp !== '127.0.0.1') ? $lanIp : '127.0.0.1';

    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
    $appPath = dirname(dirname($scriptPath));
    if ($appPath === '/' || $appPath === '\\') {
        $appPath = '';
    }

    return $protocol . '://' . $host . $port . $appPath . '/mobile_upload.php';
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    // QR code and mobile upload URL
    if ($action === 'get_qr') {
        $url = zimrx_get_mobile_url();
        $qrCode = new QRCode();
        $qrImage = $qrCode->render($url);

        echo json_encode([
            'ok' => true,
            'upload_url' => $url,
            'qr_image' => $qrImage,
            'doctor_name' => $doctorName
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Publish active patient context from desktop
    if ($action === 'update_active_patient') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new RuntimeException('Invalid request method.');
        }
        if (!zimrx_verify_csrf()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'CSRF verification failed.']);
            exit();
        }
        $patientName = trim($_POST['patient_name'] ?? '');
        $patientReg = trim($_POST['patient_reg'] ?? '');
        $patientAge = trim($_POST['patient_age'] ?? '');
        $patientGender = trim($_POST['patient_gender'] ?? '');
        $patientDate = trim($_POST['patient_date'] ?? date('d/m/Y'));
        $patientId     = (int)($_POST['patient_id']      ?? 0);
        $visitRecordId = (int)($_POST['visit_record_id'] ?? 0);

        // Ownership validation
        // Prove supplied IDs belong to the signed-in doctor before persisting them.
        // Walk-in mode (both IDs = 0) is always permitted.
        if ($patientId > 0 || $visitRecordId > 0) {
            $pdo = db();
            if (!zimrx_verify_patient_ownership($pdo, $doctorId, $patientId, $visitRecordId)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Visit or patient does not belong to your account.']);
                exit();
            }
        }

        $pdo = db();
        zimrx_assert_mobile_context_table($pdo);

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT patient_id, visit_record_id, active_revision, patient_name, patient_reg, patient_age, patient_gender, patient_date
                 FROM zimrx_mobile_active_context
                 WHERE doctor_id = :doctor_id'
            );
            $stmt->execute(['doctor_id' => $doctorId]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $resolvedPatientName = $patientName ?: 'Walk-in Patient';
            $changed = (int)($current['patient_id'] ?? 0) !== $patientId
                || (int)($current['visit_record_id'] ?? 0) !== $visitRecordId
                || (string)($current['patient_reg'] ?? '') !== $patientReg
                || (string)($current['patient_name'] ?? '') !== $resolvedPatientName;

            $currentRevision = (int)($current['active_revision'] ?? 0);
            $activeRevision = $currentRevision === 0 ? 1 : ($currentRevision + ($changed ? 1 : 0));

            $upsert = $pdo->prepare(
                'INSERT INTO zimrx_mobile_active_context
                    (doctor_id, patient_id, visit_record_id, active_revision, patient_name, patient_reg, patient_age, patient_gender, patient_date, updated_at)
                 VALUES (:doctor_id, :patient_id, :visit_record_id, :revision, :pname, :preg, :page, :pgender, :pdate, CURRENT_TIMESTAMP)
                 ON CONFLICT(doctor_id) DO UPDATE SET
                    patient_id = excluded.patient_id,
                    visit_record_id = excluded.visit_record_id,
                    active_revision = excluded.active_revision,
                    patient_name = excluded.patient_name,
                    patient_reg = excluded.patient_reg,
                    patient_age = excluded.patient_age,
                    patient_gender = excluded.patient_gender,
                    patient_date = excluded.patient_date,
                    updated_at = CURRENT_TIMESTAMP'
            );
            $upsert->execute([
                'doctor_id' => $doctorId,
                'patient_id' => $patientId ?: null,
                'visit_record_id' => $visitRecordId ?: null,
                'revision' => max(1, $activeRevision),
                'pname' => $resolvedPatientName,
                'preg' => $patientReg,
                'page' => $patientAge,
                'pgender' => $patientGender,
                'pdate' => $patientDate,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $data = [
            'doctor_id' => $doctorId,
            'doctor_name' => $doctorName,
            'patient_id' => $patientId,
            'visit_record_id' => $visitRecordId,
            'active_revision' => $activeRevision,
            'patient_name' => $resolvedPatientName,
            'patient_reg' => $patientReg,
            'patient_age' => $patientAge,
            'patient_gender' => $patientGender,
            'patient_date' => $patientDate,
            'updated_at' => time()
        ];

        file_put_contents($activePatientFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

        echo json_encode([
            'ok' => true,
            'active_revision' => $activeRevision,
            'patient_id' => $patientId,
            'visit_record_id' => $visitRecordId
        ]);
        exit();
    }

    // Fetch active patient context on mobile device
    if ($action === 'get_active_patient') {
        $pdo = db();
        zimrx_assert_mobile_context_table($pdo);
        $stmt = $pdo->prepare('SELECT * FROM zimrx_mobile_active_context WHERE doctor_id = :doctor_id LIMIT 1');
        $stmt->execute(['doctor_id' => $doctorId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $data = [
                'doctor_id' => $doctorId,
                'doctor_name' => $doctorName,
                'patient_id' => (int)($row['patient_id'] ?? 0),
                'visit_record_id' => (int)($row['visit_record_id'] ?? 0),
                'active_revision' => (int)($row['active_revision'] ?? 1),
                'patient_name' => (string)($row['patient_name'] ?? 'Walk-in Patient'),
                'patient_reg' => (string)($row['patient_reg'] ?? ''),
                'patient_age' => (string)($row['patient_age'] ?? ''),
                'patient_gender' => (string)($row['patient_gender'] ?? ''),
                'patient_date' => (string)($row['patient_date'] ?? date('d/m/Y')),
                'updated_at' => time()
            ];
        } elseif (file_exists($activePatientFile)) {
            $content = file_get_contents($activePatientFile);
            $data = json_decode($content, true) ?: [];
        } else {
            $data = [
                'doctor_id' => $doctorId,
                'doctor_name' => $doctorName,
                'patient_id' => 0,
                'visit_record_id' => 0,
                'active_revision' => 1,
                'patient_name' => 'Walk-in Patient',
                'patient_reg' => '',
                'patient_age' => '',
                'patient_gender' => '',
                'patient_date' => date('d/m/Y'),
                'updated_at' => time()
            ];
        }

        echo json_encode([
            'ok' => true,
            'patient' => $data
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Upload report photo from mobile device
    if ($action === 'upload') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Method Not Allowed. POST required.']);
            exit();
        }

        if (!zimrx_verify_csrf()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'CSRF verification failed.']);
            exit();
        }

        if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
            throw new RuntimeException('No file uploaded.');
        }

        $file = $_FILES['file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload error: ' . ($file['error'] ?? 'unknown'));
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Invalid uploaded file.');
        }

        $fileSize = (int)($file['size'] ?? 0);
        if ($fileSize <= 0 || $fileSize > 15 * 1024 * 1024) {
            throw new RuntimeException('File size must be between 1 byte and 15MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];
        if (!isset($allowedMimes[$mime])) {
            throw new RuntimeException('Invalid file type. Only JPEG, PNG, WEBP, and PDF documents are allowed.');
        }
        $ext = $allowedMimes[$mime];

        $targetDir = ZIMRX_UPLOADS_DIR . '/reports';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0750, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Unable to access reports storage directory.');
        }

        $reportName = trim((string)($_POST['report_name'] ?? ''));
        if (!$reportName) {
            $reportName = pathinfo((string)$file['name'], PATHINFO_FILENAME) ?: 'Lab Report';
        }
        $reportDate = trim((string)($_POST['report_date'] ?? date('d/m/Y')));
        if (!$reportDate) {
            $reportDate = date('d/m/Y');
        }

        // Validate active patient state before moving file
        // If the phone's context is stale we must reject here; once the file is on
        // disk an early-exit would leave an orphaned file with no queue record.
        $pdo = db();
        zimrx_assert_mobile_context_table($pdo);
        $stmtCtx = $pdo->prepare('SELECT patient_id, visit_record_id, active_revision FROM zimrx_mobile_active_context WHERE doctor_id = :doctor_id LIMIT 1');
        $stmtCtx->execute(['doctor_id' => $doctorId]);
        $activeRow = $stmtCtx->fetch(PDO::FETCH_ASSOC);

        if ($activeRow) {
            $serverPatientId      = (int)($activeRow['patient_id'] ?? 0);
            $serverVisitRecordId  = (int)($activeRow['visit_record_id'] ?? 0);
            $serverActiveRevision = (int)($activeRow['active_revision'] ?? 1);
        } else {
            $activeState = [];
            if (file_exists($activePatientFile)) {
                $activeState = json_decode((string)file_get_contents($activePatientFile), true) ?: [];
            }
            $serverPatientId      = isset($activeState['patient_id'])      ? (int)$activeState['patient_id']      : 0;
            $serverVisitRecordId  = isset($activeState['visit_record_id']) ? (int)$activeState['visit_record_id'] : 0;
            $serverActiveRevision = (int)($activeState['active_revision'] ?? 1);
        }

        $clientActiveRevision = (int)($_POST['active_revision'] ?? 0);
        if ($clientActiveRevision > 0 && $clientActiveRevision !== $serverActiveRevision) {
            http_response_code(409);
            echo json_encode([
                'ok'    => false,
                'error' => 'Patient context changed on desktop while uploading. Please re-check the patient on your mobile screen.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Save uploaded file
        $filename         = sprintf('report-%d-%d-%s.%s', $doctorId, time(), bin2hex(random_bytes(4)), $ext);
        $targetPath       = $targetDir . '/' . $filename;
        $authenticatedUrl = 'api/view_report.php?file=' . rawurlencode($filename);

        if (!move_uploaded_file($tmpPath, $targetPath)) {
            throw new RuntimeException('Failed to save file on server.');
        }

        // Insert queue record
        try {
            $uploadId = 'up_' . bin2hex(random_bytes(8));
            $pdo = db();
            $stmt = $pdo->prepare(
                "INSERT INTO zimrx_mobile_upload_queue (
                    id, doctor_id, patient_id, visit_record_id, active_revision,
                    file_path, original_name, report_name, report_date,
                    created_at, claimed_at
                ) VALUES (
                    :id, :doctor_id, :patient_id, :visit_record_id, :active_revision,
                    :file_path, :original_name, :report_name, :report_date,
                    CURRENT_TIMESTAMP, NULL
                )"
            );
            $stmt->execute([
                'id'             => $uploadId,
                'doctor_id'      => $doctorId,
                'patient_id'     => $serverPatientId     ?: null,
                'visit_record_id'=> $serverVisitRecordId ?: null,
                'active_revision'=> $serverActiveRevision,
                'file_path'      => $authenticatedUrl,
                'original_name'  => basename((string)$file['name']),
                'report_name'    => $reportName,
                'report_date'    => $reportDate,
            ]);
        } catch (Throwable $dbEx) {
            @unlink($targetPath);
            throw $dbEx;
        }

        $uploadRecord = [
            'id'             => $uploadId,
            'patient_id'     => $serverPatientId,
            'visit_record_id'=> $serverVisitRecordId,
            'active_revision'=> $serverActiveRevision,
            'file_path'      => $authenticatedUrl,
            'original_name'  => basename((string)$file['name']),
            'report_name'    => $reportName,
            'date'           => $reportDate,
            'uploaded_at'    => time()
        ];

        echo json_encode([
            'ok'      => true,
            'message' => 'Uploaded successfully.',
            'data'    => $uploadRecord
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Poll for new mobile uploads with transactional claim
    if ($action === 'check_uploads') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Method Not Allowed. POST required.']);
            exit();
        }

        if (!zimrx_verify_csrf()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'CSRF verification failed.']);
            exit();
        }

        $activeRevision = (int)($_POST['active_revision'] ?? 0);
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $visitRecordId = (int)($_POST['visit_record_id'] ?? 0);

        $pdo = db();
        $pdo->beginTransaction();

        $whereClauses = ['doctor_id = :doctor_id', 'claimed_at IS NULL'];
        $params = ['doctor_id' => $doctorId];

        if ($activeRevision > 0) {
            $whereClauses[] = 'active_revision = :active_revision';
            $params['active_revision'] = $activeRevision;
        }
        if ($patientId > 0) {
            // Exclude walk-in (NULL/0) uploads from the normal polling path.
            // Unbound reports must be reviewed via the dedicated Unassigned Reports screen.
            $whereClauses[] = 'patient_id = :patient_id';
            $params['patient_id'] = $patientId;
        } else {
            // No patient context supplied - return only unbound (walk-in) uploads
            $whereClauses[] = '(patient_id IS NULL OR patient_id = 0)';
        }

        $sql = 'SELECT id, file_path, original_name, report_name, report_date AS date,
                       patient_id, visit_record_id, active_revision
                FROM zimrx_mobile_upload_queue
                WHERE ' . implode(' AND ', $whereClauses) . '
                ORDER BY created_at ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($uploads)) {
            $ids = array_column($uploads, 'id');
            $marks = implode(',', array_fill(0, count($ids), '?'));

            $claim = $pdo->prepare(
                "UPDATE zimrx_mobile_upload_queue
                 SET claimed_at = CURRENT_TIMESTAMP
                 WHERE doctor_id = ?
                   AND claimed_at IS NULL
                   AND id IN ($marks)"
            );
            $claim->execute([$doctorId, ...$ids]);

            if ($claim->rowCount() !== count($ids)) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw new RuntimeException('Upload queue changed; retry.');
            }
        }

        $pdo->commit();

        echo json_encode([
            'ok' => true,
            'uploads' => $uploads
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    throw new RuntimeException('Unknown action.');
} catch (Throwable $e) {
    error_log('[ZimRx] mobile_sync error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'An internal error occurred. Please try again.']);
    exit();
}
