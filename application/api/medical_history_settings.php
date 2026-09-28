<?php
/**
 * ZimRx Medical History Settings API Endpoint
 * Handles GET (fetch configuration) and POST (save_config, reset_default).
 */

require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/medical_history_lib.php';

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
            'data' => med_history_get_doctor_config($doctorId)
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
            $updatedConfig = med_history_reset_to_default($doctorId);
            echo json_encode([
                'success' => true,
                'message' => 'Medical history settings reset to default.',
                'data' => $updatedConfig
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'save_config') {
            $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
            $updatedConfig = med_history_save_config($doctorId, $items);
            echo json_encode([
                'success' => true,
                'message' => 'Medical history settings saved successfully.',
                'data' => $updatedConfig
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
} catch (Throwable $e) {
    error_log('[ZimRx] medical_history_settings error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An internal error occurred. Please try again.'
    ]);
}
