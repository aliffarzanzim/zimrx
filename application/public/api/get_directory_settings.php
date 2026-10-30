<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/Services/LocaleService.php';

use ZimRx\Services\LocaleService;

header('Content-Type: application/json; charset=utf-8');

try {
    $rawLanguage = $_GET['lang'] ?? 'en';
    if (!is_string($rawLanguage)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid language.']);
        exit;
    }

    $language = strtolower(trim($rawLanguage));
    $locales = LocaleService::default();
    if (!array_key_exists($language, $locales->availableForCatalog('directory_settings'))) {
        http_response_code(400);
        echo json_encode(['error' => 'Unsupported language.']);
        exit;
    }

    echo json_encode(
        $locales->catalog($language, 'directory_settings'),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
} catch (Throwable $error) {
    error_log('[ZimRx] get_directory_settings failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load directory settings.']);
}
