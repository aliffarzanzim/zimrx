<?php
define('ZIMRX_DB_LIGHTWEIGHT', true);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/drug_catalog_lib.php';
require_once __DIR__ . '/user_drug_lib.php';

header('Content-Type: application/json');

function user_drugs_payload(): array {
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
    }
    return $_POST ?: [];
}

try {
    zimrx_user_drug_pdo();
    $action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));
    $payload = user_drugs_payload();
    $doctorId = current_user_doctor_id();

    $mutationActions = ['save', 'hide', 'restore', 'remove_override'];
    if (in_array($action, $mutationActions, true)) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Method Not Allowed. Mutations must use POST.']);
            exit;
        }

        $csrfToken = $payload['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!zimrx_verify_csrf(is_string($csrfToken) ? $csrfToken : null)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'CSRF verification failed.']);
            exit;
        }
    }

    switch ($action) {
        case 'save':
            $row = zimrx_user_drug_save($payload, $doctorId);
            echo json_encode(['ok' => true, 'drug' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'hide':
            $id = trim((string)($payload['id'] ?? $payload['brand_id'] ?? ''));
            if ($id === '') {
                throw new InvalidArgumentException('Missing drug id.');
            }
            $snapshot = isset($payload['snapshot']) && is_array($payload['snapshot']) ? $payload['snapshot'] : [];
            zimrx_user_drug_hide($id, $snapshot, $doctorId);
            echo json_encode(['ok' => true]);
            break;

        case 'restore':
            $id = trim((string)($payload['id'] ?? $payload['brand_id'] ?? ''));
            if ($id === '') {
                throw new InvalidArgumentException('Missing drug id.');
            }
            zimrx_user_drug_restore($id, $doctorId);
            echo json_encode(['ok' => true]);
            break;

        case 'hidden':
            $query = trim((string)($payload['q'] ?? $_GET['q'] ?? ''));
            echo json_encode(['ok' => true, 'rows' => zimrx_user_drug_hidden_list($query, $doctorId)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'custom':
            $query = trim((string)($payload['q'] ?? $_GET['q'] ?? ''));
            echo json_encode(['ok' => true, 'rows' => zimrx_user_drug_custom_rows($query, $doctorId)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'overrides':
            $query = trim((string)($payload['q'] ?? $_GET['q'] ?? ''));
            echo json_encode(['ok' => true, 'rows' => zimrx_user_drug_override_rows($query, $doctorId)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'remove_override':
            $id = trim((string)($payload['id'] ?? $payload['brand_id'] ?? $payload['system_brand_id'] ?? ''));
            if ($id === '') {
                throw new InvalidArgumentException('Missing drug id.');
            }
            zimrx_user_drug_remove_override($id, $doctorId);
            echo json_encode(['ok' => true]);
            break;

        case 'get':
            $id = trim((string)($payload['id'] ?? $payload['brand_id'] ?? $_GET['id'] ?? ''));
            if ($id === '') {
                throw new InvalidArgumentException('Missing drug id.');
            }
            $systemPdo = DbConnections::systemDb();
            $drug = drug_catalog_fetch_brand($systemPdo, $id);
            echo json_encode(['ok' => true, 'drug' => $drug], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
