<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/print_setup_lib.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $payload = [];

    if (isset($_POST['left_block_html'])) {
        $payload['left_block_html'] = (string)$_POST['left_block_html'];
    } else {
        for ($i = 1; $i <= 10; $i++) {
            $payload['left_line_' . $i] = trim((string)($_POST['left_line_' . $i] ?? $_POST['name' . $i] ?? ''));
        }
        $leftHtml = [];
        for ($i = 1; $i <= 10; $i++) {
            $leftValue = $payload['left_line_' . $i];
            if ($leftValue !== '') {
                $leftHtml[] = '<p class="zrx-header-left-line-' . $i . '">' . htmlspecialchars($leftValue, ENT_QUOTES, 'UTF-8') . '</p>';
            }
        }
        $payload['left_block_html'] = implode('', $leftHtml);
    }

    if (isset($_POST['right_block_html'])) {
        $payload['right_block_html'] = (string)$_POST['right_block_html'];
    } else {
        for ($i = 1; $i <= 10; $i++) {
            $payload['right_line_' . $i] = trim((string)($_POST['right_line_' . $i] ?? $_POST['info' . $i] ?? ''));
        }
        $rightHtml = [];
        for ($i = 1; $i <= 10; $i++) {
            $rightValue = $payload['right_line_' . $i];
            if ($rightValue !== '') {
                $rightHtml[] = '<p class="zrx-header-right-line-' . $i . '">' . htmlspecialchars($rightValue, ENT_QUOTES, 'UTF-8') . '</p>';
            }
        }
        $payload['right_block_html'] = implode('', $rightHtml);
    }
    $payload['display_logo'] = trim((string)($_POST['display_logo'] ?? 'yes'));
    $payload['bg_color'] = trim((string)($_POST['bgcolor'] ?? 'FFFFFF'));
    $payload['footer_html'] = (string)($_POST['footer_html'] ?? $_POST['footer_text'] ?? '');

    if (isset($_POST['header_type'])) {
        $payload['header_type'] = trim((string)$_POST['header_type']);
    }
    if (isset($_POST['full_body_header_path'])) {
        $payload['full_body_header_path'] = trim((string)$_POST['full_body_header_path']);
    }
    if (isset($_POST['bg_image_path'])) {
        $payload['bg_image_path'] = trim((string)$_POST['bg_image_path']);
    }
    if (isset($_POST['bg_image_opacity'])) {
        $payload['bg_image_opacity'] = (float)$_POST['bg_image_opacity'];
    }
    if (isset($_POST['bg_image_scale'])) {
        $payload['bg_image_scale'] = (float)$_POST['bg_image_scale'];
    }
    if (isset($_POST['bg_image_angle'])) {
        $payload['bg_image_angle'] = (float)$_POST['bg_image_angle'];
    }
    if (isset($_POST['bg_image_offset_x'])) {
        $payload['bg_image_offset_x'] = (float)$_POST['bg_image_offset_x'];
    }
    if (isset($_POST['bg_image_offset_y'])) {
        $payload['bg_image_offset_y'] = (float)$_POST['bg_image_offset_y'];
    }

    if (isset($_POST['logo_path'])) {
        $payload['logo_path'] = trim((string)$_POST['logo_path']);
    }

    if (isset($_POST['stamp_path'])) {
        $payload['stamp_path'] = trim((string)$_POST['stamp_path']);
    }
    if (isset($_POST['stamp_opacity'])) {
        $payload['stamp_opacity'] = (float)$_POST['stamp_opacity'];
    }
    if (isset($_POST['stamp_scale'])) {
        $payload['stamp_scale'] = (float)$_POST['stamp_scale'];
    }
    if (isset($_POST['stamp_angle'])) {
        $payload['stamp_angle'] = (float)$_POST['stamp_angle'];
    }
    if (isset($_POST['stamp_offset_x'])) {
        $payload['stamp_offset_x'] = (float)$_POST['stamp_offset_x'];
    }
    if (isset($_POST['stamp_offset_y'])) {
        $payload['stamp_offset_y'] = (float)$_POST['stamp_offset_y'];
    }
    if (isset($_POST['stamp_color'])) {
        $payload['stamp_color'] = trim((string)$_POST['stamp_color']);
    }
    if (isset($_POST['stamp_color_enable'])) {
        $payload['stamp_color_enable'] = trim((string)$_POST['stamp_color_enable']);
    }

    zimrx_bridge_save_print_setup($pdo, current_user_doctor_id(), $payload);
    echo '1';
} catch (Throwable $e) {
    http_response_code(500);
    echo $e->getMessage();
}
