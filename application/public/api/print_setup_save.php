<?php
// Saves print setup layout configuration for the active doctor.
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/print_setup_lib.php';

header('Content-Type: text/plain; charset=utf-8');

if (!zimrx_verify_csrf()) {
    http_response_code(403);
    echo 'Forbidden: Invalid CSRF token.';
    exit();
}

try {
    $payload = zimrx_print_form_to_payload($_POST);
    zimrx_print_save_setup($pdo, current_user_doctor_id(), $payload);
    echo '1';
} catch (Throwable $e) {
    error_log('[ZimRx] print_setup_save error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Save failed. Please try again.';
}
