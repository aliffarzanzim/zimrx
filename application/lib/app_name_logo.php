<?php
declare(strict_types=1);

function zimrx_app_name_logo_settings(PDO $pdo): array {
    $defaults = [
        'display_name' => 'ZimRx',
        'browser_tab_title' => 'ZimRx EMR System',
        'logo_layout' => 'icon_name',
        'app_logo' => '',
    ];

    if (!function_exists('zimrx_db_table_exists') || !zimrx_db_table_exists($pdo, 'zimrx_app_config')) {
        return $defaults;
    }

    $map = [
        'app_display_name' => 'display_name',
        'app_browser_tab_title' => 'browser_tab_title',
        'app_logo_layout' => 'logo_layout',
        'app_logo' => 'app_logo',
    ];
    try {
        $stmt = $pdo->query(
            "SELECT config_key, config_value FROM zimrx_app_config
             WHERE config_key IN ('app_display_name', 'app_browser_tab_title', 'app_logo_layout', 'app_logo')"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $key => $value) {
            if (isset($map[$key])) {
                $defaults[$map[$key]] = trim((string)$value);
            }
        }
    } catch (Throwable $error) {
        error_log('[ZimRx] Unable to load application name and logo settings: ' . $error->getMessage());
        return $defaults;
    }

    if (!in_array($defaults['logo_layout'], ['icon_name', 'name', 'logo_name', 'logo'], true)) {
        $defaults['logo_layout'] = 'icon_name';
    }
    if (!preg_match('~^/uploads/app-logos/[a-z0-9-]+\.(?:jpg|png|webp)$~i', $defaults['app_logo'])) {
        $defaults['app_logo'] = '';
    }
    if ($defaults['display_name'] === '') {
        $defaults['display_name'] = 'ZimRx';
    }
    if ($defaults['browser_tab_title'] === '') {
        $defaults['browser_tab_title'] = 'ZimRx EMR System';
    }

    return $defaults;
}

function zimrx_format_page_title(string $pageTitle, string $browserTabTitle): string {
    $pageTitle = preg_replace('/\bZimRx\b/i', '', $pageTitle) ?? $pageTitle;
    $pageTitle = trim($pageTitle, " \t\n\r\0\x0B-|:");
    return $pageTitle === '' ? $browserTabTitle : $pageTitle . ' | ' . $browserTabTitle;
}

function zimrx_save_app_config(PDO $pdo, array $values): void {
    $lookup = $pdo->prepare('SELECT 1 FROM zimrx_app_config WHERE config_key = :config_key');
    $update = $pdo->prepare(
        'UPDATE zimrx_app_config SET config_value = :config_value, updated_at = CURRENT_TIMESTAMP WHERE config_key = :config_key'
    );
    $insert = $pdo->prepare(
        'INSERT INTO zimrx_app_config (config_key, config_value, updated_at) VALUES (:config_key, :config_value, CURRENT_TIMESTAMP)'
    );
    foreach ($values as $key => $value) {
        $lookup->execute(['config_key' => $key]);
        $statement = $lookup->fetchColumn() === false ? $insert : $update;
        $statement->execute(['config_key' => $key, 'config_value' => $value]);
    }
}
