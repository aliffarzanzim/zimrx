<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioPackRepository.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioApiResponse.php';

\ZimRx\Services\GeoStudioApiResponse::run('GET', static function (): array {
    $packId = $_GET['pack_id'] ?? null;
    $source = $_GET['source'] ?? null;
    if (!is_string($packId) || trim($packId) === '' || !is_string($source)) {
        throw new InvalidArgumentException('Pack ID and source are required.');
    }
    return (new \ZimRx\Services\GeoStudioPackRepository())->loadPackSnapshot($packId, $source);
});
