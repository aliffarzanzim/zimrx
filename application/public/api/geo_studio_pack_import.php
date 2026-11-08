<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioPackRepository.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioPackCompiler.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioApiResponse.php';

\ZimRx\Services\GeoStudioApiResponse::run('POST', static function (): array {
    $file = $_FILES['pack_json'] ?? null;
    if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['size'])
        || (int)$file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name'])) {
        throw new InvalidArgumentException('Choose a valid address pack JSON file.');
    }
    if ((int)$file['size'] < 1 || (int)$file['size'] > \ZimRx\Services\AddressPackImporter::MAX_PACK_BYTES) {
        throw new RuntimeException('Address pack JSON exceeds the supported 4 MB limit.', 413);
    }
    $raw = file_get_contents((string)$file['tmp_name']);
    if (!is_string($raw) || strlen($raw) !== (int)$file['size']) {
        throw new RuntimeException('Geo Studio could not read the uploaded JSON file.');
    }
    $overwriteRevision = $_POST['overwrite_revision'] ?? null;
    if ($overwriteRevision !== null && !is_string($overwriteRevision)) {
        throw new InvalidArgumentException('Replacement revision is invalid.');
    }
    $repository = new \ZimRx\Services\GeoStudioPackRepository();
    $result = $repository->importPack($raw, $overwriteRevision);
    $result['active_pack_refreshed'] = \ZimRx\Services\GeoStudioPackCompiler::refreshIfActiveUserPack(
        DbConnections::staticDb(),
        DbConnections::userdata(),
        $repository,
        $result['pack_id']
    );
    return $result;
}, true);
