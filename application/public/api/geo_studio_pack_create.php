<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioPackRepository.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioPackCompiler.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioApiResponse.php';

\ZimRx\Services\GeoStudioApiResponse::run('POST', static function (): array {
    $limit = \ZimRx\Services\AddressPackImporter::MAX_PACK_BYTES + 65536;
    $length = $_SERVER['CONTENT_LENGTH'] ?? '0';
    if (!is_string($length) || !ctype_digit($length) || (int)$length > $limit) {
        throw new RuntimeException('Blank pack request exceeds the supported size limit.', 413);
    }
    $allowed = [
        'pack_id', 'pack_type', 'code_label', 'root_names', 'default_lang', 'languages',
        'contributor', 'license', 'notes', 'depth_levels', 'overwrite_confirmed', 'overwrite_revision',
    ];
    if (array_diff(array_keys($_POST), $allowed) !== []) {
        throw new InvalidArgumentException('Blank pack request contains unsupported fields.');
    }
    foreach (['pack_id', 'pack_type', 'root_names', 'default_lang', 'languages', 'depth_levels'] as $field) {
        if (!is_string($_POST[$field] ?? null)) {
            throw new InvalidArgumentException("Blank pack request is missing '{$field}'.");
        }
    }
    $params = $_POST;
    foreach (['root_names', 'languages', 'depth_levels'] as $field) {
        try {
            $params[$field] = json_decode($params[$field], true, \ZimRx\Services\AddressPackImporter::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException(ucfirst(str_replace('_', ' ', $field)) . ' contains invalid JSON.', 0, $error);
        }
    }
    if (!is_array($params['root_names']) || array_is_list($params['root_names'])
        || !is_array($params['languages']) || !array_is_list($params['languages'])
        || !is_array($params['depth_levels']) || !array_is_list($params['depth_levels'])) {
        throw new InvalidArgumentException('Blank pack names, languages, or hierarchy levels have an invalid shape.');
    }
    if (isset($params['overwrite_confirmed']) && !in_array($params['overwrite_confirmed'], ['0', '1'], true)) {
        throw new InvalidArgumentException('Replacement confirmation must be 0 or 1.');
    }
    $revision = ($params['overwrite_confirmed'] ?? '0') === '1'
        ? (string)($params['overwrite_revision'] ?? '')
        : null;
    $repository = new \ZimRx\Services\GeoStudioPackRepository();
    $result = $repository->createBlankPack($params, $revision);
    $result['active_pack_refreshed'] = \ZimRx\Services\GeoStudioPackCompiler::refreshIfActiveUserPack(
        DbConnections::staticDb(),
        DbConnections::userdata(),
        $repository,
        $result['pack_id']
    );
    return $result;
}, true);
