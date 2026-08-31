<?php
declare(strict_types=1);

namespace ZimRx\Services;

use RuntimeException;

final class LocaleService
{
    private const CODE_PATTERN = '/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})?$/';
    private const NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /** @var array<string, array> */
    private array $cache = [];

    public function __construct(
        private readonly string $localesDir,
        private readonly string $fallbackLang = 'en'
    ) {
    }

    public static function default(): self
    {
        return new self(dirname(__DIR__, 2) . '/locales');
    }

    public function available(): array
    {
        $langs = [];
        foreach (glob($this->localesDir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $code = basename($dir);
            if (preg_match(self::CODE_PATTERN, $code) === 1) {
                $langs[] = $code;
            }
        }
        sort($langs);
        return $langs;
    }

    public function availableLanguages(): array
    {
        $languages = [];
        foreach ($this->available() as $code) {
            $manifestPath = $this->localesDir . '/' . $code . '/manifest.json';
            if (is_file($manifestPath)) {
                $decoded = json_decode((string)file_get_contents($manifestPath), true);
                if (is_array($decoded)) {
                    $languages[$code] = [
                        'code' => $code,
                        'name' => (string)($decoded['name'] ?? $code),
                        'native_name' => (string)($decoded['native_name'] ?? $decoded['name'] ?? $code),
                        'direction' => (string)($decoded['direction'] ?? 'ltr'),
                        'version' => (string)($decoded['version'] ?? '1.0.0'),
                        'release_date' => (string)($decoded['release_date'] ?? ''),
                        'last_updated' => (string)($decoded['last_updated'] ?? ''),
                        'author' => (string)($decoded['author'] ?? ''),
                        'contributors' => is_array($decoded['contributors'] ?? null) ? $decoded['contributors'] : [],
                    ];
                    continue;
                }
            }
            $languages[$code] = [
                'code' => $code,
                'name' => strtoupper($code),
                'native_name' => strtoupper($code),
                'direction' => 'ltr',
                'version' => '1.0.0',
                'release_date' => '',
                'last_updated' => '',
                'author' => '',
                'contributors' => [],
            ];
        }
        return $languages;
    }

    public function availableForCatalog(string $catalogName): array
    {
        $this->assertSafe('en', $catalogName);
        $all = $this->availableLanguages();
        $filtered = [];
        foreach ($all as $code => $meta) {
            $filePath = $this->localesDir . '/' . $code . '/' . $catalogName . '.json';
            if (is_file($filePath)) {
                $filtered[$code] = $meta;
            }
        }
        return $filtered;
    }

    public function catalog(string $lang, string $name): array
    {
        $this->assertSafe($lang, $name);

        $key = $lang . '/' . $name;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $data = $this->load($lang, $name);
        if ($lang !== $this->fallbackLang) {
            $data = array_replace_recursive($this->load($this->fallbackLang, $name), $data);
        }

        return $this->cache[$key] = $data;
    }

    public function text(string $lang, string $name, string|int $id, ?string $section = null): string
    {
        return $this->field($lang, $name, $id, 'text', $section);
    }

    public function alias(string $lang, string $name, string|int $id, ?string $section = null): string
    {
        return $this->field($lang, $name, $id, 'alias', $section);
    }

    private function field(string $lang, string $name, string|int $id, string $field, ?string $section): string
    {
        $data = $this->catalog($lang, $name);
        if ($section !== null) {
            $data = $data[$section] ?? [];
        }

        $entry = $data[(string)$id] ?? null;
        return is_array($entry) && is_string($entry[$field] ?? null) ? $entry[$field] : '';
    }

    private function load(string $lang, string $name): array
    {
        $file = $this->localesDir . '/' . $lang . '/' . $name . '.json';
        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Invalid locale file: {$lang}/{$name}.json");
        }
        return $decoded;
    }

    private function assertSafe(string $lang, string $name): void
    {
        if (preg_match(self::CODE_PATTERN, $lang) !== 1 || preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new RuntimeException('Invalid locale language code or catalog name.');
        }
    }
}
