<?php
// Resets print setup configuration back to defaults for the active doctor.
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/print_setup_lib.php';

ini_set('display_errors', '0');

header('Content-Type: text/plain; charset=utf-8');

try {
    zimrx_print_reset_setup($pdo, current_user_doctor_id());
    echo '1';
} catch (Throwable $e) {
    http_response_code(500);
    echo $e->getMessage();
}
