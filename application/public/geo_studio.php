<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_login();

$page_title = 'ZimRx - Geo Studio';
$body_class = 'geo-studio-page';
$extra_css = ['assets/css/pages/geo_studio.css'];
$geoStudioInitialPage = ($_GET['page'] ?? '') === 'new' ? 'new' : '';
$geoStudioInitialPackId = is_string($_GET['pack_id'] ?? null) ? trim($_GET['pack_id']) : '';
$geoStudioInitialContributor = function_exists('current_user_name') ? current_user_name() : '';
if ($geoStudioInitialPackId !== '') {
    $page_title = 'ZimRx - Geo Studio - ' . $geoStudioInitialPackId;
}

include __DIR__ . '/header.php';
?>
<main class="geo-studio-root">
    <div class="geo-app-shell geo-studio-app" id="geoStudioApp"
        data-initial-contributor="<?= htmlspecialchars($geoStudioInitialContributor, ENT_QUOTES, 'UTF-8') ?>">
        <?php include ZIMRX_BASE_DIR . '/views/geo_studio_header.php'; ?>
        <?php include ZIMRX_BASE_DIR . '/views/geo_studio_pack_library.php'; ?>
        <?php include ZIMRX_BASE_DIR . '/views/geo_studio_editor.php'; ?>
        <?php include ZIMRX_BASE_DIR . '/views/geo_studio_create_pack.php'; ?>
        <?php include ZIMRX_BASE_DIR . '/views/geo_studio_dialogs.php'; ?>
    </div>
</main>
<script type="module" src="assets/js/pages/geo_studio/app.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/geo_studio/app.js') ?>"></script>
<?php include __DIR__ . '/footer.php'; ?>
