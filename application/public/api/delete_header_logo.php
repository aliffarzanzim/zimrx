<?php
declare(strict_types=1);

// Header logo deletion endpoint: deletes doctor-specific header logo image files.

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/print_setup_lib.php';

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

    $profile = (new \ZimRx\Services\PracticeProfileService($pdo))->activeForCurrentUser();
    $profileId = (int)($profile['id'] ?? 0);
    if ($profileId <= 0) {
        throw new RuntimeException('An active practice profile is required.');
    }
    $header = zimrx_print_load_header_settings($pdo, $profileId);
    $configuredPath = trim((string)($header['logo_path'] ?? ''));
    if (basename((string)(parse_url($configuredPath, PHP_URL_PATH) ?? '')) !== $filename ||
        !str_starts_with((string)(parse_url($configuredPath, PHP_URL_PATH) ?? ''), '/uploads/header-logos/')) {
        throw new RuntimeException('The requested logo is not assigned to this practice profile.');
    }

    zimrx_print_save_setup($pdo, $profileId, ['logo_path' => '']);
    $references = $pdo->prepare(
        'SELECT COUNT(*) FROM zimrx_doctor_profiles
         WHERE id <> :profile_id AND is_deleted = 0 AND settings_json LIKE :path'
    );
    $references->execute([
        'profile_id' => $profileId,
        'path' => '%' . $configuredPath . '%',
    ]);
    if ((int)$references->fetchColumn() === 0) {
        $root = realpath(ZIMRX_USERDATA_DIR);
        $target = realpath(ZIMRX_UPLOADS_DIR . '/header-logos/' . $filename);
        if ($root !== false && $target !== false && str_starts_with($target, $root . DIRECTORY_SEPARATOR) && is_file($target)) {
            @unlink($target);
        }
    }

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('[ZimRx] delete_header_logo error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'An error occurred while deleting the file.']);
}
