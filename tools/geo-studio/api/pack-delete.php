<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/RequestBootstrap.php';
require_once dirname(__DIR__) . '/lib/ApiResponse.php';
require_once dirname(__DIR__) . '/lib/PackRepository.php';

$bootstrap = \GeoStudio\RequestBootstrap::initialize();
$packs = new \GeoStudio\PackRepository($bootstrap['packsDir']);

\GeoStudio\ApiResponse::run('DELETE', static function () use ($packs): array {
    $contentType = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));
    if (trim(explode(';', $contentType, 2)[0]) !== 'application/json') {
        throw new InvalidArgumentException('Request content type must be application/json.');
    }

    $contentLength = $_SERVER['CONTENT_LENGTH'] ?? '0';
    if (!is_string($contentLength) || !ctype_digit($contentLength) || (int)$contentLength > 1024) {
        throw new InvalidArgumentException('Request size is invalid.', 413);
    }
    $raw = file_get_contents('php://input', false, null, 0, 1025);
    if (!is_string($raw) || strlen($raw) > 1024) {
        throw new InvalidArgumentException('Request size is invalid.', 413);
    }
    try {
        $payload = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
    } catch (\JsonException $error) {
        throw new InvalidArgumentException('Request body contains invalid JSON.', 0, $error);
    }
    if (!is_array($payload) || array_is_list($payload) || array_diff(array_keys($payload), ['revision']) !== []) {
        throw new InvalidArgumentException('Request body has an invalid shape.');
    }
    if (!is_string($payload['revision'] ?? null)) {
        throw new InvalidArgumentException('Pack revision is required.');
    }

    $packId = $_GET['pack_id'] ?? null;
    if (!is_string($packId) || trim($packId) === '') {
        throw new InvalidArgumentException('Pack ID is required.');
    }
    return $packs->deletePack($packId, $payload['revision']);
}, true);
