<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/RequestBootstrap.php';
require_once dirname(__DIR__) . '/lib/ApiResponse.php';
require_once dirname(__DIR__) . '/lib/PackRepository.php';

$bootstrap = \GeoStudio\RequestBootstrap::initialize();
$packs = new \GeoStudio\PackRepository($bootstrap['packsDir']);

\GeoStudio\ApiResponse::run('GET', static function () use ($packs): array {
    return ['success' => true, ...$packs->getPackCatalog()];
});
