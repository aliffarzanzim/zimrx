<?php
declare(strict_types=1);

$navigationGroupKey = (string)($section_navigation_group ?? '');
$navigationGroups = require ZIMRX_BASE_DIR . '/config/page_navigation.php';
$navigationGroup = $navigationGroups[$navigationGroupKey] ?? null;
$navigationItems = is_array($navigationGroup['items'] ?? null) ? $navigationGroup['items'] : [];
$activeNavigationKey = (string)($section_navigation_active ?? '');
$navigationLabel = (string)($navigationGroup['label'] ?? 'Related pages');
$navigationCounts = is_array($section_navigation_counts ?? null) ? $section_navigation_counts : [];
?>
<?php if ($navigationItems !== []): ?>
<nav class="zrx-section-nav" aria-label="<?= htmlspecialchars($navigationLabel, ENT_QUOTES, 'UTF-8') ?>">
    <div class="zrx-section-nav-list">
        <?php foreach ($navigationItems as $item): ?>
            <?php
            $key = (string)($item['key'] ?? '');
            $label = (string)($item['label'] ?? '');
            $href = (string)($item['href'] ?? '#');
            $isActive = $key !== '' && $key === $activeNavigationKey;
            $countKey = (string)($item['count'] ?? '');
            ?>
            <a class="zrx-section-nav-link<?= $isActive ? ' is-active' : '' ?>"
                href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                <?= $isActive ? 'aria-current="page"' : '' ?>>
                <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($countKey !== '' && array_key_exists($countKey, $navigationCounts)): ?>
                    <span class="zrx-section-nav-count"><?= (int)$navigationCounts[$countKey] ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</nav>
<?php endif; ?>
