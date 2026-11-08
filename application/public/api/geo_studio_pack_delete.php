<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioPackRepository.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioPackCompiler.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioApiResponse.php';

\ZimRx\Services\GeoStudioApiResponse::run('DELETE', static function (): array {
    $raw = file_get_contents('php://input', false, null, 0, 2049);
    if (!is_string($raw) || strlen($raw) > 2048) {
        throw new RuntimeException('Delete request exceeds the supported size limit.', 413);
    }
    try {
        $payload = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new InvalidArgumentException('Delete request contains invalid JSON.', 0, $error);
    }
    if (!is_array($payload) || array_is_list($payload)
        || array_diff(array_keys($payload), ['pack_id', 'revision']) !== []
        || !is_string($payload['pack_id'] ?? null) || !is_string($payload['revision'] ?? null)) {
        throw new InvalidArgumentException('Delete request has an invalid shape.');
    }
    $packs = new \ZimRx\Services\GeoStudioPackRepository();
    $backup = $packs->exportPack($payload['pack_id'], 'user');
    if (!hash_equals($payload['revision'], hash('sha256', $backup['content']))) {
        throw new RuntimeException('This pack changed on disk. Reload the pack list before deleting it.', 409);
    }
    $userPdo = DbConnections::userdata();
    $staticPdo = DbConnections::staticDb();
    $userPdo->beginTransaction();
    try {
        \ZimRx\Services\GeoStudioPackCompiler::removePack($userPdo, $staticPdo, $payload['pack_id']);
        $packs->deletePack($payload['pack_id'], $payload['revision']);
        $userPdo->commit();
    } catch (Throwable $error) {
        if ($userPdo->inTransaction()) {
            $userPdo->rollBack();
        }
        if (!is_file($packs->userPackPath($payload['pack_id']))) {
            try {
                $packs->importPack($backup['content']);
            } catch (Throwable $restoreError) {
                error_log('[ZimRx Geo Studio] Could not restore a pack after delete rollback: ' . $restoreError->getMessage());
            }
        }
        throw $error;
    }
    return ['pack_id' => $payload['pack_id']];
}, true);
