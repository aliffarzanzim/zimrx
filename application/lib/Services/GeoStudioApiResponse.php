<?php
declare(strict_types=1);

namespace ZimRx\Services;

use InvalidArgumentException;
use PDOException;
use RuntimeException;
use Throwable;

final class GeoStudioApiResponse
{
    public static function run(string $method, callable $operation, bool $requiresCsrf = false): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== strtoupper($method)) {
            header('Allow: ' . strtoupper($method));
            self::send(405, ['success' => false, 'error' => 'Method not allowed.']);
            return;
        }
        if ($requiresCsrf && !zimrx_verify_csrf()) {
            self::send(403, ['success' => false, 'error' => 'CSRF verification failed.']);
            return;
        }

        try {
            $result = $operation();
            if (!is_array($result)) {
                throw new RuntimeException('Geo Studio returned an invalid result.');
            }
            self::send(200, ['success' => true, ...$result]);
        } catch (PDOException $error) {
            error_log('[ZimRx Geo Studio] Database operation failed: ' . $error->getMessage());
            self::send(500, ['success' => false, 'error' => 'The address pack database operation failed.']);
        } catch (InvalidArgumentException $error) {
            self::send(422, ['success' => false, 'error' => $error->getMessage()]);
        } catch (RuntimeException $error) {
            $status = in_array($error->getCode(), [404, 409, 413], true) ? $error->getCode() : 422;
            if ($status === 422 && $error->getCode() !== 0) {
                error_log('[ZimRx Geo Studio] Request failed: ' . $error->getMessage());
                self::send(500, ['success' => false, 'error' => 'Geo Studio could not complete the request.']);
                return;
            }
            self::send($status, ['success' => false, 'error' => $error->getMessage()]);
        } catch (Throwable $error) {
            error_log('[ZimRx Geo Studio] Unexpected error: ' . $error->getMessage());
            self::send(500, ['success' => false, 'error' => 'Geo Studio could not complete the request.']);
        }
    }

    private static function send(int $status, array $payload): void
    {
        http_response_code($status);
        try {
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (Throwable $error) {
            error_log('[ZimRx Geo Studio] Response encoding failed: ' . $error->getMessage());
            http_response_code(500);
            echo '{"success":false,"error":"Geo Studio could not encode the response."}';
        }
    }
}
