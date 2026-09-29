<?php
declare(strict_types=1);

// Physical examination template and layout configuration endpoint (fetch, update, and reset defaults).

require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/physical_examination_lib.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $doctorId = current_user_doctor_id();
    if ($doctorId <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'GET') {
        echo json_encode([
            'success' => true,
            'data' => physical_exam_get_doctor_config($doctorId)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST') {
        if (!zimrx_verify_csrf()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'CSRF verification failed.']);
            exit;
        }
        $input = file_get_contents('php://input');
        $payload = json_decode($input, true);

        if (!is_array($payload)) {
            echo json_encode(['success' => false, 'error' => 'Invalid JSON payload.']);
            exit;
        }

        $action = trim((string)($payload['action'] ?? 'save_config'));

        if ($action === 'reset_default') {
            $updatedConfig = physical_exam_reset_doctor_config($doctorId);
            echo json_encode([
                'success' => true,
                'message' => 'Physical examination settings reset to factory defaults.',
                'data' => $updatedConfig
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'save_config') {
            $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
            $updatedConfig = physical_exam_save_doctor_config($doctorId, $items);
            echo json_encode([
                'success' => true,
                'message' => 'Physical examination settings saved successfully.',
                'data' => $updatedConfig
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
} catch (Throwable $e) {
    error_log('[ZimRx] physical_examination_settings error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An internal error occurred. Please try again.'
    ]);
}
