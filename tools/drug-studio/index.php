<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$baseAppDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application';

require_once $baseAppDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'zrx_icons.php';
require_once $baseAppDir . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'db_connections.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'DrugCatalogValidator.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'DrugCatalogRepository.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'DrugCatalogExporter.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'DrugStudioApi.php';

$curationDb = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'zimrx_drugs_curation.db';
$manifestsDir = __DIR__ . DIRECTORY_SEPARATOR . 'manifests';
$seedsDir = $baseAppDir . DIRECTORY_SEPARATOR . 'systemdata' . DIRECTORY_SEPARATOR . 'seeds';

$repository = new \ZimRx\DrugStudio\DrugCatalogRepository($curationDb, $baseAppDir, $manifestsDir);
$exporter = new \ZimRx\DrugStudio\DrugCatalogExporter($repository, $seedsDir, $manifestsDir);

if (isset($_GET['action'])) {
    $api = new \ZimRx\DrugStudio\DrugStudioApi($repository, $exporter);
    $api->handle((string)$_GET['action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drug Studio - Clinical Drug Catalog &amp; Formularies Designer</title>
    <link rel="stylesheet" href="assets/css/drug-studio.css?v=<?= filemtime(__DIR__ . '/assets/css/drug-studio.css') ?>">
    <script>
        window.ZimRxIconsMap = <?= json_encode(ZimRxIcon::getAll(), JSON_UNESCAPED_SLASHES) ?>;
    </script>
</head>
<body>

    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'appbar.php'; ?>
    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'hub.php'; ?>
    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'workspace.php'; ?>
    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'modals.php'; ?>

    <script src="assets/js/drug-studio.js?v=<?= filemtime(__DIR__ . '/assets/js/drug-studio.js') ?>"></script>
</body>
</html>
