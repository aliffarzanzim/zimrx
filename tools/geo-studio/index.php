<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/RequestBootstrap.php';
require_once __DIR__ . '/lib/IconRegistry.php';

$geoStudioBootstrap = \GeoStudio\RequestBootstrap::initialize();

$geoStudioInitialPage = (string)($_GET['page'] ?? '');
$geoStudioInitialTitle = $geoStudioInitialPage === 'new'
    ? 'Geo Studio - New Pack'
    : 'Geo Studio - Home';
if (trim((string)($_GET['pack_id'] ?? '')) !== '') {
    $geoStudioInitialTitle = 'Geo Studio - ' . trim((string)$_GET['pack_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($geoStudioBootstrap['csrfToken'], ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($geoStudioInitialTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/assets/css/app.css') ?>">
</head>
<body>
    <div class="geo-app-shell" id="geoStudioApp">
        <?php require __DIR__ . '/views/application-header.php'; ?>
        <?php require __DIR__ . '/views/pack-library.php'; ?>
        <?php require __DIR__ . '/views/pack-editor.php'; ?>
        <?php require __DIR__ . '/views/create-pack.php'; ?>
        <?php require __DIR__ . '/views/dialogs.php'; ?>
    </div>
    <?php require __DIR__ . '/views/contributor-setup.php'; ?>
    <script type="module" src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
</body>
</html>
