<?php
define('ZIMRX_DB_LIGHTWEIGHT', true);
require_once __DIR__ . '/../db.php';
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
    return $_POST ?: $_GET;
}

try {
    zimrx_user_drug_pdo();
    $action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));
    $payload = user_drugs_payload();

    switch ($action) {
        case 'save':
            $row = zimrx_user_drug_save($payload);
            echo json_encode(['ok' => true, 'drug' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'hide':
            $id = trim((string)($payload['id'] ?? $payload['brand_id'] ?? ''));
            if ($id === '') {
                throw new InvalidArgumentException('Missing drug id.');
            }
            $snapshot = isset($payload['snapshot']) && is_array($payload['snapshot']) ? $payload['snapshot'] : [];
            zimrx_user_drug_hide($id, $snapshot);
            echo json_encode(['ok' => true]);
            break;

        case 'restore':
            $id = trim((string)($payload['id'] ?? $payload['brand_id'] ?? ''));
            if ($id === '') {
                throw new InvalidArgumentException('Missing drug id.');
            }
            zimrx_user_drug_restore($id);
            echo json_encode(['ok' => true]);
            break;

        case 'hidden':
            $query = trim((string)($payload['q'] ?? ''));
            echo json_encode(['ok' => true, 'rows' => zimrx_user_drug_hidden_list($query)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'custom':
            $query = trim((string)($payload['q'] ?? ''));
            echo json_encode(['ok' => true, 'rows' => zimrx_user_drug_custom_rows($query)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'overrides':
            $query = trim((string)($payload['q'] ?? ''));
            echo json_encode(['ok' => true, 'rows' => zimrx_user_drug_override_rows($query)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'remove_override':
            $id = trim((string)($payload['id'] ?? $payload['brand_id'] ?? $payload['system_brand_id'] ?? ''));
            if ($id === '') {
                throw new InvalidArgumentException('Missing drug id.');
            }
            zimrx_user_drug_remove_override($id);
            echo json_encode(['ok' => true]);
            break;

        case 'get':
            $id = trim((string)($payload['id'] ?? $payload['brand_id'] ?? ''));
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
