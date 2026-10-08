<?php
declare(strict_types=1);

// Help guidelines catalog endpoint: delivers locale-aware workflow guidelines and UI strings.

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/Services/LocaleService.php';

use ZimRx\Services\LocaleService;

header('Content-Type: application/json; charset=utf-8');

try {
    $lang = strtolower(trim((string)($_GET['lang'] ?? 'en')));
    if ($lang === '') {
        $lang = 'en';
    }
    $localeService = LocaleService::default();
    $catalog = $localeService->catalog($lang, 'help_guidelines');
    echo json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('[ZimRx] get_help_guidelines error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Unable to load guidelines.']);
}
