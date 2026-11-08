<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/Services/GeoStudioPackRepository.php';

$packId = $_GET['pack_id'] ?? null;
$source = $_GET['source'] ?? null;
if (!is_string($packId) || !is_string($source)) {
    http_response_code(400);
    exit('Pack ID and source are required.');
}
try {
    $export = (new \ZimRx\Services\GeoStudioPackRepository())->exportPack($packId, $source);
} catch (Throwable $error) {
    http_response_code($error->getCode() === 404 ? 404 : 422);
    exit('The selected address pack could not be exported.');
}

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $export['filename'] . '"');
header('Content-Length: ' . strlen($export['content']));
header('X-Content-Type-Options: nosniff');
echo $export['content'];
