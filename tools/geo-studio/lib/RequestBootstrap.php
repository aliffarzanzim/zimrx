<?php
declare(strict_types=1);

namespace GeoStudio;

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/RequestSecurity.php';
require_once __DIR__ . '/WorkspacePaths.php';

final class RequestBootstrap
{
    public static function initialize(): array
    {
        RequestSecurity::requireLoopbackAccess();

        $packsDir = WorkspacePaths::seedDirectory();
        $csrfToken = RequestSecurity::initialize();
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; style-src-attr 'unsafe-inline'; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
        header('Cache-Control: no-store');

        return [
            'packsDir' => $packsDir,
            'csrfToken' => $csrfToken,
        ];
    }
}
