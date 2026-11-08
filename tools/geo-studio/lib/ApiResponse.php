<?php
declare(strict_types=1);

namespace GeoStudio;

use InvalidArgumentException;
use Throwable;

require_once __DIR__ . '/RequestSecurity.php';

final class ApiResponse
{
    public static function run(string $method, callable $operation, bool $requiresCsrf = false): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $method = strtoupper($method);
        $requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? ''));
        if ($requestMethod !== $method) {
            header('Allow: ' . $method);
            http_response_code(405);
            self::emit(['success' => false, 'error' => 'Method not allowed.']);
        }

        if ($requiresCsrf) {
            RequestSecurity::requireValidCsrfToken();
        }

        try {
            $payload = $operation();
            if (!is_array($payload)) {
                throw new \RuntimeException('API operations must return a response object.');
            }
            self::emit($payload);
        } catch (Throwable $error) {
            $isInputError = $error instanceof InvalidArgumentException;
            $isConflict = is_a($error, 'GeoStudio\\PackWriteConflict');
            http_response_code($isConflict ? 409 : ($isInputError ? ($error->getCode() === 413 ? 413 : 400) : 500));
            if (!$isInputError && !$isConflict) {
                error_log('[Geo Studio] ' . $error);
            }
            self::emit([
                'success' => false,
                'error' => $isInputError || $isConflict ? $error->getMessage() : 'Geo Studio could not complete the request.',
            ]);
        }
    }

    private static function emit(array $payload): never
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($json)) {
            http_response_code(500);
            $json = '{"success":false,"error":"Geo Studio could not encode the response."}';
        }
        echo $json;
        exit;
    }
}
