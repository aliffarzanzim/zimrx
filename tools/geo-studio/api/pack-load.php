<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/RequestBootstrap.php';
require_once dirname(__DIR__) . '/lib/ApiResponse.php';
require_once dirname(__DIR__) . '/lib/PackRepository.php';

$bootstrap = \GeoStudio\RequestBootstrap::initialize();
$packs = new \GeoStudio\PackRepository($bootstrap['packsDir']);

\GeoStudio\ApiResponse::run('GET', static function () use ($packs): array {
    $packId = $_GET['pack_id'] ?? null;
    if (!is_string($packId) || trim($packId) === '') {
        throw new InvalidArgumentException('Pack ID is required.');
    }
    $packId = \GeoStudio\PackRepository::sanitizePackId($packId);
    return ['success' => true, ...$packs->loadPackSnapshot($packId)];
});
