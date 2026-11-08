<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/RequestBootstrap.php';
require_once dirname(__DIR__) . '/lib/ApiResponse.php';
require_once dirname(__DIR__) . '/lib/PackRepository.php';

$bootstrap = \GeoStudio\RequestBootstrap::initialize();
$packs = new \GeoStudio\PackRepository($bootstrap['packsDir']);

\GeoStudio\ApiResponse::run('PUT', static function () use ($packs): array {
    $contentType = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));
    if (trim(explode(';', $contentType, 2)[0]) !== 'application/json') {
        throw new InvalidArgumentException('Request content type must be application/json.');
    }

    $maxRequestBytes = \GeoStudio\ResourceLimits::packBytes() + 65536;
    $contentLength = $_SERVER['CONTENT_LENGTH'] ?? '0';
    if (!is_string($contentLength) || !ctype_digit($contentLength)) {
        throw new InvalidArgumentException('Request size is invalid.');
    }
    if ((int)$contentLength > $maxRequestBytes) {
        throw new InvalidArgumentException('Request body exceeds the supported size limit.', 413);
    }
    $raw = file_get_contents('php://input', false, null, 0, $maxRequestBytes + 1) ?: '';
    if (strlen($raw) > $maxRequestBytes) {
        throw new InvalidArgumentException('Request body exceeds the supported size limit.', 413);
    }
    try {
        $payload = json_decode($raw, true, \GeoStudio\PackValidator::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
    } catch (\JsonException $error) {
        throw new InvalidArgumentException('Request body contains invalid or excessively nested JSON.', 0, $error);
    }
    if (!is_array($payload) || array_is_list($payload)
        || array_diff(array_keys($payload), ['pack_id', 'revision', 'data', 'contributor']) !== []) {
        throw new InvalidArgumentException('Request body has an invalid shape.');
    }
    if (!isset($payload['data']) || !is_array($payload['data']) || array_is_list($payload['data'])
        || !is_string($payload['revision'] ?? null)
        || !is_string($payload['contributor'] ?? '')) {
        throw new InvalidArgumentException('Request body must include valid pack data and revision.');
    }
    $packId = $_GET['pack_id'] ?? null;
    if (!is_string($packId) || trim($packId) === '') {
        throw new InvalidArgumentException('Pack ID is required.');
    }
    $packId = \GeoStudio\PackRepository::sanitizePackId($packId);
    if (!isset($payload['pack_id']) || !is_string($payload['pack_id'])
        || \GeoStudio\PackRepository::sanitizePackId($payload['pack_id']) !== $packId) {
        throw new InvalidArgumentException('Pack ID in the request body does not match the requested pack.');
    }

    return $packs->savePack($packId, $payload['data'], $payload['revision'], $payload['contributor'] ?? '');
}, true);
