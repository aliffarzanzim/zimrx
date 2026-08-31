<?php
/**
 * ZimRx Mobile Sync API
 * Provides live synchronization between desktop prescription and mobile upload page
 * per doctor, eliminating per-patient tokens.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../vendor/autoload.php';

use chillerlan\QRCode\QRCode;

header('Content-Type: application/json');

$cacheDir = ZIMRX_USERDATA_DIR . '/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
}

$doctorId = current_user_doctor_id();
$doctorName = current_user_name();
$activePatientFile = $cacheDir . '/doctor_' . $doctorId . '_patient.json';
$pendingUploadsFile = $cacheDir . '/doctor_' . $doctorId . '_uploads.json';

/**
 * Detect server LAN IP across Windows, Linux, and macOS.
 */
function zimrx_get_server_lan_ip(): string {
    if (extension_loaded('sockets')) {
        $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($sock) {
            if (@socket_connect($sock, '8.8.8.8', 53)) {
                @socket_getsockname($sock, $localIp);
                @socket_close($sock);
                if (!empty($localIp) && filter_var($localIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    if (!str_starts_with($localIp, '127.')) {
                        return $localIp;
                    }
                }
            }
            @socket_close($sock);
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

    return '127.0.0.1';
}

function zimrx_get_mobile_url(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';

    $hostParts = explode(':', $host);
    $hostname = strtolower($hostParts[0]);
    $port = isset($hostParts[1]) ? ':' . $hostParts[1] : '';

    if ($hostname === 'localhost' || $hostname === '127.0.0.1' || $hostname === '[::1]') {
        $lanIp = zimrx_get_server_lan_ip();
        if ($lanIp && $lanIp !== '127.0.0.1') {
            $host = $lanIp . $port;
        }
    }

    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
    $appPath = dirname(dirname($scriptPath));
    if ($appPath === '/' || $appPath === '\\') {
        $appPath = '';
    }

    return $protocol . '://' . $host . $appPath . '/mobile-upload.php';
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    // 1. Get QR code & Permanent mobile upload URL
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

    // 2. Desktop publishes current active patient
    if ($action === 'update_active_patient') {
        $patientName = trim($_POST['patient_name'] ?? '');
        $patientReg = trim($_POST['patient_reg'] ?? '');
        $patientAge = trim($_POST['patient_age'] ?? '');
        $patientGender = trim($_POST['patient_gender'] ?? '');
        $patientDate = trim($_POST['patient_date'] ?? date('d/m/Y'));

        $data = [
            'doctor_id' => $doctorId,
            'doctor_name' => $doctorName,
            'patient_name' => $patientName ?: 'Walk-in Patient',
            'patient_reg' => $patientReg,
            'patient_age' => $patientAge,
            'patient_gender' => $patientGender,
            'patient_date' => $patientDate,
            'updated_at' => time()
        ];

        file_put_contents($activePatientFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

        echo json_encode(['ok' => true]);
        exit();
    }

    // 3. Mobile phone fetches the current active patient of this doctor
    if ($action === 'get_active_patient') {
        if (file_exists($activePatientFile)) {
            $content = file_get_contents($activePatientFile);
            $data = json_decode($content, true) ?: [];
        } else {
            $data = [
                'doctor_id' => $doctorId,
                'doctor_name' => $doctorName,
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

    // 4. Mobile uploads a report for the doctor's active patient
    if ($action === 'upload') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new RuntimeException('Invalid request method.');
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

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'heic', 'heif'];
        if (!in_array($ext, $allowed, true)) {
            throw new RuntimeException('Only images (JPG, PNG, WEBP, GIF) and PDF files are allowed.');
        }

        $targetDir = ZIMRX_UPLOADS_DIR . '/reports';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Unable to access reports storage directory.');
        }

        $filename = sprintf('report-%d-%d-%s.%s', $doctorId, time(), bin2hex(random_bytes(3)), $ext);
        $targetPath = $targetDir . '/' . $filename;

        if (!move_uploaded_file($tmpPath, $targetPath)) {
            throw new RuntimeException('Failed to save file on server.');
        }

        $publicPath = 'uploads/reports/' . $filename;
        $reportName = trim($_POST['report_name'] ?? '');
        if (!$reportName) {
            $reportName = pathinfo($file['name'], PATHINFO_FILENAME) ?: 'Lab Report';
        }
        $reportDate = trim($_POST['report_date'] ?? date('d/m/Y'));
        if (!$reportDate) {
            $reportDate = date('d/m/Y');
        }

        $uploadRecord = [
            'id' => 'up_' . bin2hex(random_bytes(6)),
            'file_path' => $publicPath,
            'original_name' => $file['name'],
            'report_name' => $reportName,
            'date' => $reportDate,
            'uploaded_at' => time()
        ];

        // Queue upload for desktop consumption
        $existing = [];
        if (file_exists($pendingUploadsFile)) {
            $existing = json_decode(file_get_contents($pendingUploadsFile), true) ?: [];
        }
        $existing[] = $uploadRecord;
        file_put_contents($pendingUploadsFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

        echo json_encode([
            'ok' => true,
            'message' => 'Uploaded successfully.',
            'data' => $uploadRecord
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 5. Desktop checks for new uploads
    if ($action === 'check_uploads') {
        $uploads = [];
        if (file_exists($pendingUploadsFile)) {
            $content = file_get_contents($pendingUploadsFile);
            $uploads = json_decode($content, true) ?: [];
            if (!empty($uploads)) {
                // Clear the pending queue after reading
                file_put_contents($pendingUploadsFile, json_encode([], JSON_PRETTY_PRINT), LOCK_EX);
            }
        }

        echo json_encode([
            'ok' => true,
            'uploads' => $uploads
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    throw new RuntimeException('Unknown action.');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit();
}
