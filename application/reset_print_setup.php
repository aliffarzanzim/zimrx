<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/print_setup_lib.php';

ini_set('display_errors', '0');

header('Content-Type: text/plain; charset=utf-8');

try {
    zimrx_bridge_reset_print_setup($pdo, current_user_doctor_id());
    echo '1';
} catch (Throwable $e) {
    http_response_code(500);
    echo $e->getMessage();
}
