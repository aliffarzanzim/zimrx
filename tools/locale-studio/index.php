<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'zrx_icons.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'LocaleCatalogManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'LocaleStudioApi.php';

$localesDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'locales';

if (isset($_GET['action'])) {
    $api = new \ZimRx\LocaleStudio\LocaleStudioApi($localesDir);
    $api->handle((string)$_GET['action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Locale Studio - Clinical Translations &amp; Dynamic Catalogs</title>
    <link rel="stylesheet" href="assets/css/locale-studio.css?v=<?= filemtime(__DIR__ . '/assets/css/locale-studio.css') ?>">
    <script>
        window.ZimRxIconsMap = <?= json_encode(ZimRxIcon::getAll(), JSON_UNESCAPED_SLASHES) ?>;
    </script>
</head>
<body>

    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'appbar.php'; ?>
    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'hub.php'; ?>
    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'workspace.php'; ?>
    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'modals.php'; ?>

    <div class="locale-toast-container" id="toastContainer"></div>

    <script src="assets/js/locale-studio.js?v=<?= filemtime(__DIR__ . '/assets/js/locale-studio.js') ?>"></script>
</body>
</html>
