<?php
declare(strict_types=1);

namespace GeoStudio;

final class RequestSecurity
{
    private const SESSION_NAME = 'ZIMRX_GEOSTUDIO';
    private const SESSION_KEY = 'geo_studio_csrf_token';

    private static function isLoopbackAddress(string $remoteAddress): bool
    {
        return in_array($remoteAddress, ['127.0.0.1', '::1'], true);
    }

    private static function isLoopbackRequest(string $remoteAddress, string $hostHeader, array $server): bool
    {
        if (!self::isLoopbackAddress($remoteAddress) || $hostHeader === ''
            || preg_match('/[\x00-\x20\x7f]/', $hostHeader) === 1) {
            return false;
        }

        $hostParts = parse_url('http://' . $hostHeader);
        if (!is_array($hostParts) || !isset($hostParts['host'])) {
            return false;
        }
        foreach (['user', 'pass', 'path', 'query', 'fragment'] as $part) {
            if (isset($hostParts[$part])) return false;
        }
        if (isset($hostParts['port']) && ($hostParts['port'] < 1 || $hostParts['port'] > 65535)) {
            return false;
        }

        $host = strtolower(rtrim($hostParts['host'], '.'));
        if (!in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)) {
            return false;
        }

        foreach ([
            'HTTP_FORWARDED', 'HTTP_FORWARDED_HOST', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_HOST',
            'HTTP_X_FORWARDED_PORT', 'HTTP_X_FORWARDED_PROTO', 'HTTP_X_FORWARDED_SERVER', 'HTTP_X_REAL_IP', 'HTTP_VIA',
        ] as $header) {
            if (isset($server[$header]) && $server[$header] !== '') return false;
        }

        return true;
    }

    public static function requireLoopbackAccess(): void
    {
        self::sendSecurityHeaders();
        $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $hostHeader = $_SERVER['HTTP_HOST'] ?? '';
        if (!is_string($remoteAddress) || !is_string($hostHeader)
            || !self::isLoopbackRequest($remoteAddress, $hostHeader, $_SERVER)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Geo Studio accepts connections from this computer only.';
            exit;
        }
    }

    public static function initialize(): string
    {
        self::startSession();
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        $token = $_SESSION[self::SESSION_KEY];
        session_write_close();
        return $token;
    }

    public static function requireValidCsrfToken(): void
    {
        $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($provided) || $provided === '') {
            self::rejectRequest();
        }

        self::startSession();
        $expected = $_SESSION[self::SESSION_KEY] ?? '';
        $valid = is_string($expected) && hash_equals($expected, $provided);
        session_write_close();
        if (!$valid) self::rejectRequest();
    }

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_name(self::SESSION_NAME);
        session_set_cookie_params([
            'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Strict',
            'path' => '/',
        ]);
        if (!session_start()) {
            throw new RuntimeException('Geo Studio could not start its local security session.');
        }
    }

    private static function rejectRequest(): never
    {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Request verification failed.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function sendSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
    }
}
