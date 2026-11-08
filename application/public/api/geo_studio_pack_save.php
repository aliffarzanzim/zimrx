<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioPackRepository.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioPackCompiler.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioApiResponse.php';

\ZimRx\Services\GeoStudioApiResponse::run('PUT', static function (): array {
    $contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]));
    if ($contentType !== 'application/json') {
        throw new InvalidArgumentException('Request content type must be application/json.');
    }
    $limit = \ZimRx\Services\AddressPackImporter::MAX_PACK_BYTES + 65536;
    $length = $_SERVER['CONTENT_LENGTH'] ?? '0';
    if (!is_string($length) || !ctype_digit($length) || (int)$length > $limit) {
        throw new RuntimeException('Pack save request exceeds the supported size limit.', 413);
    }
    $raw = file_get_contents('php://input', false, null, 0, $limit + 1);
    if (!is_string($raw) || strlen($raw) > $limit) {
        throw new RuntimeException('Pack save request exceeds the supported size limit.', 413);
    }
    try {
        $payload = json_decode($raw, true, \ZimRx\Services\AddressPackImporter::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new InvalidArgumentException('Pack save request contains invalid JSON.', 0, $error);
    }
    if (!is_array($payload) || array_is_list($payload)
        || array_diff(array_keys($payload), ['pack_id', 'source', 'revision', 'data', 'contributor']) !== []
        || !is_string($payload['pack_id'] ?? null)
        || !is_string($payload['source'] ?? null)
        || !is_string($payload['revision'] ?? null)
        || !is_array($payload['data'] ?? null)
        || !is_string($payload['contributor'] ?? '')) {
        throw new InvalidArgumentException('Pack save request has an invalid shape.');
    }
    $repository = new \ZimRx\Services\GeoStudioPackRepository();
    $result = $repository->savePack(
        $payload['pack_id'],
        $payload['source'],
        $payload['data'],
        $payload['revision'],
        $payload['contributor']
    );
    $result['active_pack_refreshed'] = \ZimRx\Services\GeoStudioPackCompiler::refreshIfActiveUserPack(
        DbConnections::staticDb(),
        DbConnections::userdata(),
        $repository,
        $result['pack_id']
    );
    return $result;
}, true);
