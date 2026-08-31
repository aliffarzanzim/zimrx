<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/Services/LocaleService.php';

use ZimRx\Services\LocaleService;

header('Content-Type: application/json; charset=utf-8');

function respond_help_language(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_help_language(405, ['ok' => false, 'error' => 'Method not allowed.']);
}
if (!zimrx_verify_csrf()) {
    respond_help_language(403, ['ok' => false, 'error' => 'CSRF verification failed.']);
}

$payload = json_decode((string)file_get_contents('php://input'), true);
$rawLanguage = is_array($payload) && is_string($payload['language'] ?? null)
    ? $payload['language']
    : '';
$language = strtolower(trim($rawLanguage));
$availableLanguages = LocaleService::default()->availableForCatalog('help_guidelines');
if (!array_key_exists($language, $availableLanguages)) {
    respond_help_language(400, ['ok' => false, 'error' => 'Unsupported help language.']);
}

try {
    global $pdo;
    if (!($pdo instanceof PDO) || !zimrx_db_table_exists($pdo, 'zimrx_app_config')) {
        respond_help_language(500, ['ok' => false, 'error' => 'Application settings are unavailable.']);
    }

    $pdo->beginTransaction();
    $lookup = $pdo->prepare('SELECT 1 FROM zimrx_app_config WHERE config_key = :config_key');
    $lookup->execute(['config_key' => 'help_lang']);
    if ($lookup->fetchColumn() !== false) {
        $save = $pdo->prepare(
            'UPDATE zimrx_app_config SET config_value = :config_value, updated_at = CURRENT_TIMESTAMP WHERE config_key = :config_key'
        );
    } else {
        $save = $pdo->prepare(
            'INSERT INTO zimrx_app_config (config_key, config_value, updated_at) VALUES (:config_key, :config_value, CURRENT_TIMESTAMP)'
        );
    }
    $save->execute(['config_key' => 'help_lang', 'config_value' => $language]);
    $pdo->commit();
    respond_help_language(200, ['ok' => true, 'language' => $language]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[ZimRx] Failed to save help language: ' . $error->getMessage());
    respond_help_language(500, ['ok' => false, 'error' => 'Unable to save help language.']);
}
