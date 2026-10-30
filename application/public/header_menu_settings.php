<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once ZIMRX_BASE_DIR . '/lib/app_name_logo.php';
require_once ZIMRX_BASE_DIR . '/lib/app_navigation.php';
require_login();

if (!is_admin_user() && (current_user_role() !== 'doctor' || zimrx_is_multi_doctor($pdo))) {
    http_response_code(403);
    exit('You do not have permission to change header settings.');
}

$appNameLogo = zimrx_app_name_logo_settings($pdo);
$menuPreferences = zimrx_load_header_menu_preferences($pdo);
$errorMessage = '';
$newFiles = [];
$oldFiles = [];

$removeUploadedLogo = static function (string $publicPath): void {
    if (!str_starts_with($publicPath, '/uploads/app-logos/')) {
        return;
    }
    $filename = basename($publicPath);
    if (!preg_match('/^[a-z0-9-]+\.(?:jpg|png|webp)$/i', $filename)) {
        return;
    }
    $directory = ZIMRX_UPLOADS_DIR . '/app-logos';
    $target = $directory . '/' . $filename;
    $realDirectory = realpath($directory);
    $realTarget = realpath($target);
    if ($realDirectory !== false && $realTarget !== false && dirname($realTarget) === $realDirectory && is_file($realTarget)) {
        unlink($realTarget);
    }
};

$storeLogo = static function (string $field, string $slot) use (&$newFiles): ?string {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }
    $file = $_FILES[$field];
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($uploadError !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The ' . $slot . ' logo upload failed.');
    }

    $temporaryPath = (string)($file['tmp_name'] ?? '');
    $fileSize = (int)($file['size'] ?? 0);
    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath) || $fileSize < 1 || $fileSize > 3 * 1024 * 1024) {
        throw new RuntimeException('Choose a valid image no larger than 3 MB.');
    }

    $imageInfo = @getimagesize($temporaryPath);
    $mime = (string)($imageInfo['mime'] ?? '');
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $width = (int)($imageInfo[0] ?? 0);
    $height = (int)($imageInfo[1] ?? 0);
    if (!isset($extensions[$mime]) || $width < 1 || $height < 1 || $width > 2400 || $height > 1600 || ($width * $height) > 3000000) {
        throw new RuntimeException('Use a JPG, PNG, or WEBP logo up to 2400 by 1600 pixels.');
    }

    $directory = ZIMRX_UPLOADS_DIR . '/app-logos';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the application logo directory.');
    }

    $filename = $slot . '-' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
    $target = $directory . '/' . $filename;
    if (!move_uploaded_file($temporaryPath, $target)) {
        throw new RuntimeException('Unable to save the uploaded logo.');
    }
    $newFiles[] = $target;
    return '/uploads/app-logos/' . $filename;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appNameLogo['display_name'] = trim((string)($_POST['display_name'] ?? ''));
    $appNameLogo['browser_tab_title'] = trim((string)($_POST['browser_tab_title'] ?? ''));
    $appNameLogo['logo_layout'] = (string)($_POST['logo_layout'] ?? 'icon_name');

    try {
        if (!zimrx_verify_csrf()) {
            throw new RuntimeException('The form expired. Refresh this page and try again.');
        }
        $menuPreferences = zimrx_parse_header_menu_submission($_POST);
        foreach (['display_name' => 80, 'browser_tab_title' => 100] as $field => $maxLength) {
            if (!preg_match('/\A.{1,' . $maxLength . '}\z/us', $appNameLogo[$field])) {
                throw new RuntimeException('Display name and browser tab title must be non-empty and within the allowed length.');
            }
        }
        if (!in_array($appNameLogo['logo_layout'], ['icon_name', 'name', 'logo_name', 'logo'], true)) {
            throw new RuntimeException('Choose a valid navigation logo layout.');
        }

        $appLogo = $storeLogo('app_logo_file', 'app');
        $removeAppLogo = isset($_POST['remove_app_logo']);
        if ($appLogo !== null && $removeAppLogo) {
            throw new RuntimeException('Choose either a new logo or remove the current logo, not both.');
        }

        $oldFiles = [$appNameLogo['app_logo']];

        if ($removeAppLogo) {
            $appNameLogo['app_logo'] = '';
        } elseif ($appLogo !== null) {
            $appNameLogo['app_logo'] = $appLogo;
        }

        $values = [
            'app_display_name' => $appNameLogo['display_name'],
            'app_browser_tab_title' => $appNameLogo['browser_tab_title'],
            'app_logo_layout' => $appNameLogo['logo_layout'],
            'app_logo' => $appNameLogo['app_logo'],
            'header_menu_preferences' => json_encode($menuPreferences, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ];

        $pdo->beginTransaction();
        zimrx_save_app_config($pdo, $values);
        $pdo->commit();
        foreach ($oldFiles as $oldFile) {
            if ($oldFile !== '' && $oldFile !== $appNameLogo['app_logo']) {
                $removeUploadedLogo($oldFile);
            }
        }
        header('Location: header_menu_settings.php?saved=1');
        exit;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($newFiles as $newFile) {
            if (is_file($newFile)) {
                unlink($newFile);
            }
        }
        $isExpectedSaveError = $error instanceof RuntimeException || $error instanceof InvalidArgumentException;
        $errorMessage = $isExpectedSaveError
            ? $error->getMessage()
            : 'Unable to save settings. Please try again.';
        if (!$isExpectedSaveError) {
            error_log('[ZimRx] Header and menu settings save failed: ' . $error->getMessage());
        }
    }
}

$page_title = 'ZimRx - Header & Menu Settings';
$extra_css = ['assets/css/pages/header_menu_settings.css'];
include __DIR__ . '/header.php';
?>

<main class="zrx-header-menu-settings zrx-page-container">
    <header class="zrx-settings-index-header zrx-settings-page-hero">
        <div class="zrx-settings-page-hero-copy">
            <a class="zrx-settings-page-back" href="settings.php">Settings</a>
            <h1>Header &amp; Menu Settings</h1>
            <p>Set the display name and logo, arrange header links, and group related pages.</p>
        </div>
    </header>

    <?php if (isset($_GET['saved'])): ?>
        <p class="zrx-header-menu-feedback zrx-header-menu-success" role="status">Settings saved.</p>
    <?php endif; ?>
    <?php if ($errorMessage !== ''): ?>
        <p class="zrx-header-menu-feedback zrx-header-menu-error" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form class="zrx-header-menu-form" method="post" enctype="multipart/form-data">
        <?= zimrx_csrf_field() ?>
        <section class="zrx-header-menu-panel" aria-labelledby="name-logo-title">
            <h2 id="name-logo-title">Name &amp; Logo</h2>
            <div class="zrx-header-menu-fields">
                <label>
                    <span>Display name</span>
                    <input class="zrx-input" type="text" name="display_name" maxlength="80" value="<?= htmlspecialchars($appNameLogo['display_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                    <small>Used in the navigation and sign-in heading.</small>
                </label>
                <label>
                    <span>Browser tab title</span>
                    <input class="zrx-input" type="text" name="browser_tab_title" maxlength="100" value="<?= htmlspecialchars($appNameLogo['browser_tab_title'], ENT_QUOTES, 'UTF-8') ?>" required>
                    <small>Shown in the browser tab after the current page name.</small>
                </label>
                <label>
                    <span>Navigation Logo Layout</span>
                    <select class="zrx-input" name="logo_layout">
                        <option value="icon_name" <?= $appNameLogo['logo_layout'] === 'icon_name' ? 'selected' : '' ?>>Icon and name</option>
                        <option value="name" <?= $appNameLogo['logo_layout'] === 'name' ? 'selected' : '' ?>>Name only</option>
                        <option value="logo_name" <?= $appNameLogo['logo_layout'] === 'logo_name' ? 'selected' : '' ?>>Logo and name</option>
                        <option value="logo" <?= $appNameLogo['logo_layout'] === 'logo' ? 'selected' : '' ?>>Logo only</option>
                    </select>
                </label>
                <div class="zrx-header-menu-logo-field">
                    <label for="app-logo-file">Application logo</label>
                    <div class="zrx-header-menu-logo-preview">
                        <?php if ($appNameLogo['app_logo'] !== ''): ?>
                            <img src="<?= htmlspecialchars($appNameLogo['app_logo'], ENT_QUOTES, 'UTF-8') ?>" alt="Current application logo">
                        <?php else: ?>
                            <span>No logo uploaded</span>
                        <?php endif; ?>
                    </div>
                    <small>Used in the navigation header and sign-in page. PNG, JPG, or WEBP up to 3 MB.</small>
                    <input id="app-logo-file" type="file" name="app_logo_file" accept="image/png,image/jpeg,image/webp">
                    <?php if ($appNameLogo['app_logo'] !== ''): ?>
                        <label class="zrx-header-menu-remove"><input type="checkbox" name="remove_app_logo" value="1"> Remove current logo</label>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="zrx-header-menu-panel" aria-labelledby="header-menu-title">
            <h2 id="header-menu-title">Navigation menu</h2>
            <p class="zrx-header-menu-description">Arrange links, group related pages in dropdowns, and choose what appears in the header. Drag items to reorder or move them into a dropdown. Settings stays available in the menu.</p>
            <div class="zrx-menu-builder" data-menu-items>
                <aside class="zrx-menu-add-panel" aria-labelledby="menu-add-title">
                    <h3 id="menu-add-title">Add menu items</h3>
                    <div class="zrx-menu-add-options" data-menu-add-options></div>
                    <button class="btn btn-outline-primary" type="button" data-menu-add-selected>Add to menu</button>
                    <fieldset class="zrx-menu-custom-link">
                        <legend>Custom link</legend>
                        <label>Label<input class="zrx-input" type="text" maxlength="40" data-custom-label></label>
                        <label>Destination<input class="zrx-input" type="text" maxlength="500" placeholder="page.php or https://example.com" data-custom-href></label>
                        <button class="btn btn-outline" type="button" data-menu-add-custom>Add link</button>
                    </fieldset>
                    <button class="btn btn-outline" type="button" data-menu-add-group>Add dropdown</button>
                </aside>
                <section class="zrx-menu-structure" aria-labelledby="menu-structure-title">
                    <div class="zrx-menu-structure-heading">
                        <div><h3 id="menu-structure-title">Menu structure</h3><p>Drag an item into a dropdown to nest it. Use the arrow to edit item details.</p></div>
                    </div>
                    <div class="zrx-menu-sort-list" data-sortable="root" data-zrx-reorderable></div>
                    <p class="zrx-menu-empty" data-menu-empty hidden>No items in this menu yet. Add a page or custom link.</p>
                </section>
            </div>
            <input type="hidden" name="header_menu_json" data-menu-json>
            <script type="application/json" data-menu-initial><?= json_encode(['items' => $menuPreferences['items'], 'catalog' => array_map(static fn(string $id, array $item): array => ['id' => $id, 'type' => $item['type'], 'label' => (string)($zrx_hdr_strings[$item['translation']] ?? $item['fallback']), 'href' => $item['href'] ?? null], array_keys(zimrx_header_menu_builtin_catalog()), array_values(zimrx_header_menu_builtin_catalog()))], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?></script>
            <script src="assets/js/layout/boot.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/boot.js') ?>" defer></script>
            <p class="zrx-menu-builder-error" data-menu-error role="alert" hidden></p>
            <script src="assets/js/pages/header_menu_builder.js?v=<?= file_exists(__DIR__ . '/assets/js/pages/header_menu_builder.js') ? filemtime(__DIR__ . '/assets/js/pages/header_menu_builder.js') : '1' ?>" defer></script>
        </section>

        <div class="zrx-header-menu-actions">
            <button class="btn btn-primary" type="submit">Save Changes</button>
        </div>
    </form>
</main>

<?php include __DIR__ . '/footer.php'; ?>
