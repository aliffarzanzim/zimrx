<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/RequestBootstrap.php';
require_once dirname(__DIR__) . '/lib/ApiResponse.php';
require_once dirname(__DIR__) . '/lib/IconRegistry.php';

\GeoStudio\RequestBootstrap::initialize();

\GeoStudio\ApiResponse::run('GET', static fn(): array => [
    'success' => true,
    'icons' => \GeoStudio\IconRegistry::getAll(),
]);
