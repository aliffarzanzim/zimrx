<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioPackRepository.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioPackCompiler.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioApiResponse.php';

\ZimRx\Services\GeoStudioApiResponse::run('POST', static function (): array {
    $raw = file_get_contents('php://input', false, null, 0, 2049);
    if (!is_string($raw) || strlen($raw) > 2048) {
        throw new RuntimeException('Pack selection request exceeds the supported size limit.', 413);
    }
    try {
        $payload = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new InvalidArgumentException('Pack selection request contains invalid JSON.', 0, $error);
    }
    if (!is_array($payload) || array_is_list($payload)
        || array_diff(array_keys($payload), ['pack_id', 'source', 'language']) !== []
        || !is_string($payload['pack_id'] ?? null)
        || !is_string($payload['source'] ?? null)
        || !is_string($payload['language'] ?? null)) {
        throw new InvalidArgumentException('Pack selection request has an invalid shape.');
    }
    return \ZimRx\Services\GeoStudioPackCompiler::selectPack(
        DbConnections::staticDb(),
        DbConnections::userdata(),
        new \ZimRx\Services\GeoStudioPackRepository(),
        $payload['pack_id'],
        $payload['source'],
        $payload['language']
    );
}, true);
