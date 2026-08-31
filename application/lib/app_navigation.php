<?php
declare(strict_types=1);

function zimrx_header_menu_builtin_catalog(): array {
    return [
        'builtin-prescription' => ['type' => 'link', 'translation' => 'nav_new_prescription', 'fallback' => 'New Prescription', 'href' => 'prescription.php'],
        'builtin-appointments' => ['type' => 'link', 'translation' => 'nav_appointments', 'fallback' => 'Appointments', 'href' => 'appointments.php'],
        'builtin-emr' => ['type' => 'link', 'translation' => 'nav_emr', 'fallback' => 'EMR', 'href' => 'emr.php'],
        'builtin-drug-db' => ['type' => 'link', 'translation' => 'nav_drug_db', 'fallback' => 'Drug DB', 'href' => 'drug_db.php'],
        'builtin-finance' => ['type' => 'group', 'translation' => 'nav_billings_stats', 'fallback' => 'Billings & Stats'],
        'builtin-settings' => ['type' => 'link', 'translation' => 'nav_settings', 'fallback' => 'Settings', 'href' => 'settings.php', 'required' => true],
        'builtin-help' => ['type' => 'group', 'translation' => 'nav_help', 'fallback' => 'Help'],
        'builtin-billings' => ['type' => 'link', 'translation' => 'nav_billings', 'fallback' => 'Billings', 'href' => 'billings.php'],
        'builtin-performance' => ['type' => 'link', 'translation' => 'nav_performance_dashboard', 'fallback' => 'Performance Dashboard', 'href' => 'performance_dashboard.php'],
        'builtin-support' => ['type' => 'action', 'translation' => 'btn_support_zimrx', 'fallback' => 'Support ZimRx', 'action' => 'support'],
    ];
}

function zimrx_default_header_menu_preferences(): array {
    return [
        'version' => 2,
        'items' => [
            ['id' => 'builtin-prescription', 'type' => 'link', 'label' => '', 'href' => 'prescription.php', 'visible' => true, 'width' => 'compact'],
            ['id' => 'builtin-appointments', 'type' => 'link', 'label' => '', 'href' => 'appointments.php', 'visible' => true, 'width' => 'compact'],
            ['id' => 'builtin-emr', 'type' => 'link', 'label' => '', 'href' => 'emr.php', 'visible' => true, 'width' => 'compact'],
            ['id' => 'builtin-drug-db', 'type' => 'link', 'label' => '', 'href' => 'drug_db.php', 'visible' => true, 'width' => 'compact'],
            ['id' => 'builtin-finance', 'type' => 'group', 'label' => '', 'visible' => true, 'width' => 'compact', 'children' => [
                ['id' => 'builtin-billings', 'type' => 'link', 'label' => '', 'href' => 'billings.php', 'visible' => true],
                ['id' => 'builtin-performance', 'type' => 'link', 'label' => '', 'href' => 'performance_dashboard.php', 'visible' => true],
            ]],
            ['id' => 'builtin-settings', 'type' => 'link', 'label' => '', 'href' => 'settings.php', 'visible' => true, 'width' => 'compact'],
            ['id' => 'builtin-help', 'type' => 'group', 'label' => '', 'visible' => true, 'width' => 'compact', 'children' => [
                ['id' => 'builtin-support', 'type' => 'action', 'label' => '', 'action' => 'support', 'visible' => true],
            ]],
        ],
    ];
}

function zimrx_header_menu_label(array $item, array $strings): string {
    if ($item['label'] !== '') {
        return $item['label'];
    }
    $builtin = zimrx_header_menu_builtin_catalog()[$item['id']] ?? null;
    if ($builtin === null) {
        return '';
    }
    return (string)($strings[$builtin['translation']] ?? $builtin['fallback']);
}

function zimrx_validate_header_menu_href(string $href): string {
    $href = trim($href);
    if ($href === '' || strlen($href) > 500 || preg_match('/[\x00-\x20\\\\]/', $href) === 1) {
        throw new InvalidArgumentException('Enter a valid menu link.');
    }
    if (str_starts_with($href, '//')) {
        throw new InvalidArgumentException('Protocol-relative menu links are not allowed.');
    }

    if (str_starts_with(strtolower($href), 'https://')) {
        $parts = parse_url($href);
        if (!is_array($parts) || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || filter_var($href, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Enter a valid HTTPS link.');
        }
        return $href;
    }

    if (preg_match('/\A(?:[A-Za-z0-9_-]+\/)*[A-Za-z0-9_.-]+(?:\.php)?(?:#[A-Za-z0-9_.-]+)?\z/', $href) !== 1) {
        throw new InvalidArgumentException('Use an application page path or an HTTPS link.');
    }
    return $href;
}

function zimrx_normalize_header_menu_preferences(array $input): array {
    if (($input['version'] ?? null) !== 2 || !isset($input['items']) || !is_array($input['items'])) {
        return zimrx_default_header_menu_preferences();
    }
    try {
        $items = zimrx_validate_header_menu_items($input['items']);
        return ['version' => 2, 'items' => $items];
    } catch (InvalidArgumentException $error) {
        return zimrx_default_header_menu_preferences();
    }
}

function zimrx_parse_header_menu_submission(array $post): array {
    $json = $post['header_menu_json'] ?? null;
    if (!is_string($json) || strlen($json) > 50000) {
        throw new InvalidArgumentException('The menu configuration is missing or too large.');
    }
    try {
        $input = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new InvalidArgumentException('The menu configuration could not be read.');
    }
    if (!is_array($input)) {
        throw new InvalidArgumentException('The menu configuration is invalid.');
    }
    $items = zimrx_validate_header_menu_items($input);
    foreach ($items as $item) {
        if ($item['id'] === 'builtin-settings' && !$item['visible']) {
            throw new InvalidArgumentException('Settings must remain visible.');
        }
    }
    if (!array_filter($items, static fn(array $item): bool => $item['id'] === 'builtin-settings')) {
        throw new InvalidArgumentException('The Settings menu item must remain in the header.');
    }
    return ['version' => 2, 'items' => $items];
}

function zimrx_validate_header_menu_items(array $items): array {
    if (count($items) < 1 || count($items) > 30) {
        throw new InvalidArgumentException('A menu must contain between 1 and 30 items.');
    }
    $catalog = zimrx_header_menu_builtin_catalog();
    $seen = [];
    $clean = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('A menu item is malformed.');
        }
        $id = $item['id'] ?? null;
        $type = $item['type'] ?? null;
        $label = $item['label'] ?? null;
        $visible = $item['visible'] ?? null;
        $width = $item['width'] ?? 'compact';
        if (!is_string($id) || isset($seen[$id])) {
            throw new InvalidArgumentException('Menu item identifiers must be unique.');
        }
        if (!is_string($label) || preg_match('/\A.{0,40}\z/us', $label) !== 1 || preg_match('/\p{Cc}/u', $label) === 1) {
            throw new InvalidArgumentException('Menu labels must be no longer than 40 characters.');
        }
        if (!is_bool($visible) || !in_array($width, ['compact', 'standard', 'wide'], true)) {
            throw new InvalidArgumentException('Choose valid visibility and width settings for each menu item.');
        }

        $builtin = $catalog[$id] ?? null;
        $isCustom = preg_match('/\Acustom-[a-f0-9-]{36}\z/', $id) === 1;
        if ($builtin === null && !$isCustom) {
            throw new InvalidArgumentException('The menu contains an invalid item identifier.');
        }
        if ($isCustom && trim($label) === '') {
            throw new InvalidArgumentException('Custom menu items need a label.');
        }
        $expectedType = $builtin['type'] ?? null;
        if ($builtin !== null && $type !== $expectedType) {
            throw new InvalidArgumentException('A built-in menu item has an invalid type.');
        }
        if ($builtin === null && !in_array($type, ['link', 'group'], true)) {
            throw new InvalidArgumentException('Custom menu items must be links or dropdown groups.');
        }
        if ($type === 'action') {
            throw new InvalidArgumentException('Application actions can only appear inside dropdown groups.');
        }
        if ($id === 'builtin-settings') {
            $visible = true;
        }
        $seen[$id] = true;
        $entry = ['id' => $id, 'type' => $type, 'label' => trim($label), 'visible' => $visible, 'width' => $width];

        if ($type === 'link') {
            $rawHref = $builtin['href'] ?? ($item['href'] ?? null);
            if (!is_string($rawHref)) {
                throw new InvalidArgumentException('Each menu link needs a destination.');
            }
            $entry['href'] = $builtin !== null ? $rawHref : zimrx_validate_header_menu_href($rawHref);
        } elseif ($type === 'group') {
            $children = $item['children'] ?? null;
            if (!is_array($children) || count($children) < 1 || count($children) > 20) {
                throw new InvalidArgumentException('Each dropdown group must have between 1 and 20 items.');
            }
            $entry['children'] = [];
            foreach ($children as $child) {
                if (!is_array($child)) {
                    throw new InvalidArgumentException('A dropdown item is malformed.');
                }
                $childId = $child['id'] ?? null;
                $childBuiltin = is_string($childId) ? ($catalog[$childId] ?? null) : null;
                $childCustom = is_string($childId) && preg_match('/\Acustom-[a-f0-9-]{36}\z/', $childId) === 1;
                if (!is_string($childId) || isset($seen[$childId]) || ($childBuiltin === null && !$childCustom) || ($childBuiltin !== null && in_array($childBuiltin['type'], ['group'], true)) || $childId === 'builtin-settings') {
                    throw new InvalidArgumentException('A dropdown item identifier is invalid or duplicated.');
                }
                $childType = $childBuiltin['type'] ?? 'link';
                if (!in_array($childType, ['link', 'action'], true) || (isset($child['type']) && $child['type'] !== $childType)) {
                    throw new InvalidArgumentException('Dropdown items must be links or supported actions.');
                }
                $childLabel = $child['label'] ?? null;
                $childVisible = $child['visible'] ?? null;
                if (!is_string($childLabel) || preg_match('/\A.{0,40}\z/us', $childLabel) !== 1 || preg_match('/\p{Cc}/u', $childLabel) === 1 || !is_bool($childVisible) || ($childCustom && trim($childLabel) === '')) {
                    throw new InvalidArgumentException('Dropdown labels or visibility settings are invalid.');
                }
                $seen[$childId] = true;
                $childEntry = ['id' => $childId, 'type' => $childType, 'label' => trim($childLabel), 'visible' => $childVisible];
                if ($childType === 'link') {
                    $childHref = $childBuiltin['href'] ?? ($child['href'] ?? null);
                    if (!is_string($childHref)) {
                        throw new InvalidArgumentException('Each dropdown link needs a destination.');
                    }
                    $childEntry['href'] = $childBuiltin !== null ? $childHref : zimrx_validate_header_menu_href($childHref);
                } else {
                    $childEntry['action'] = $childBuiltin['action'];
                }
                $entry['children'][] = $childEntry;
            }
        }
        $clean[] = $entry;
    }
    return $clean;
}

function zimrx_load_header_menu_preferences(PDO $pdo): array {
    try {
        $statement = $pdo->prepare('SELECT config_value FROM zimrx_app_config WHERE config_key = :config_key LIMIT 1');
        $statement->execute(['config_key' => 'header_menu_preferences']);
        $stored = $statement->fetchColumn();
        if (!is_string($stored) || $stored === '') {
            return zimrx_default_header_menu_preferences();
        }
        $decoded = json_decode($stored, true, 16, JSON_THROW_ON_ERROR);
        return is_array($decoded)
            ? zimrx_normalize_header_menu_preferences($decoded)
            : zimrx_default_header_menu_preferences();
    } catch (Throwable $error) {
        error_log('[ZimRx] Unable to load header menu preferences: ' . $error->getMessage());
        return zimrx_default_header_menu_preferences();
    }
}
