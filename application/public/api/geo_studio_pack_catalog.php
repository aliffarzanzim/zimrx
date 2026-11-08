<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/Services/AddressPackService.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioPackRepository.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioApiResponse.php';

\ZimRx\Services\GeoStudioApiResponse::run('GET', static function (): array {
    $catalog = (new \ZimRx\Services\GeoStudioPackRepository())->getPackCatalog();
    $systemIds = [];
    foreach ($catalog['packs'] as $pack) {
        if ($pack['source'] === 'system') {
            $systemIds[$pack['pack_id']] = true;
        }
    }
    foreach ($catalog['packs'] as &$pack) {
        $pack['has_system_pack'] = isset($systemIds[$pack['pack_id']]);
    }
    unset($pack);

    $activePack = \ZimRx\Services\AddressPackService::getConfiguredPack(
        DbConnections::staticDb(),
        DbConnections::userdata()
    );
    $active = is_array($activePack) ? [
        'pack_id' => $activePack['pack_id'],
        'source' => $activePack['source'],
        'language' => $activePack['display_language'],
    ] : null;
    return ['packs' => $catalog['packs'], 'issues' => $catalog['issues'], 'active' => $active];
});
