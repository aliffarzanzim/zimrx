<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/print_setup_lib.php';

ini_set('display_errors', '0');

header('Content-Type: text/plain; charset=utf-8');

try {
    $payload = zimrx_print_form_to_payload($_POST);
    zimrx_print_save_setup($pdo, current_user_doctor_id(), $payload);
    echo '1';
} catch (Throwable $e) {
    http_response_code(500);
    echo $e->getMessage();
}
