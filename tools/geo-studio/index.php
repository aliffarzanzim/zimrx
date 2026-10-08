<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'zrx_icons.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'compile_address_hierarchy.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'CountryCatalog.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'GeoNamesImporter.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'WikidataImporter.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'GeoStudioApi.php';

$hierarchiesDir = __DIR__ . DIRECTORY_SEPARATOR . 'hierarchies';
$cacheDir = __DIR__ . DIRECTORY_SEPARATOR . 'cache';
$zrxCountryCatalog = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'countries.php';

if (isset($_GET['action'])) {
    $api = new \ZimRx\GeoStudio\GeoStudioApi($hierarchiesDir, $cacheDir);
    $api->handle((string)$_GET['action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geo Studio - Places &amp; Postal Hierarchy Designer</title>
    <link rel="stylesheet" href="assets/css/geo-studio.css?v=<?= filemtime(__DIR__ . '/assets/css/geo-studio.css') ?>">
    <script>
        window.ZimRxIconsMap = <?= json_encode(ZimRxIcon::getAll(), JSON_UNESCAPED_SLASHES) ?>;
    </script>
</head>
<body>

    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'appbar.php'; ?>
    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'hub.php'; ?>
    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'workspace.php'; ?>
    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'modals.php'; ?>

    <script src="assets/js/geo-studio.js?v=<?= filemtime(__DIR__ . '/assets/js/geo-studio.js') ?>"></script>
</body>
</html>
