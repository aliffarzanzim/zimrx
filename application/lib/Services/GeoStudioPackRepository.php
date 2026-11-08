<?php
declare(strict_types=1);

namespace ZimRx\Services;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/AddressPackImporter.php';

final class GeoStudioPackRepository
{
    private string $userPacksDirectory;
    private string $systemSeedsDirectory;

    public function __construct(?string $userPacksDirectory = null, ?string $systemSeedsDirectory = null)
    {
        $baseDirectory = defined('ZIMRX_BASE_DIR') ? ZIMRX_BASE_DIR : dirname(__DIR__, 2);
        $userdataDirectory = defined('ZIMRX_USERDATA_DIR')
            ? ZIMRX_USERDATA_DIR
            : $baseDirectory . DIRECTORY_SEPARATOR . 'userdata';
        $this->userPacksDirectory = $userPacksDirectory
            ?? $userdataDirectory . DIRECTORY_SEPARATOR . 'address-packs';
        $this->systemSeedsDirectory = $systemSeedsDirectory
            ?? $baseDirectory . DIRECTORY_SEPARATOR . 'systemdata' . DIRECTORY_SEPARATOR . 'seeds';
        $this->ensureUserPacksDirectory();
    }

    public static function sanitizePackId(string $packId): string
    {
        $clean = strtoupper(trim($packId));
        if (preg_match('/^[A-Z0-9_-]{2,32}$/D', $clean) !== 1) {
            throw new InvalidArgumentException('Pack ID must contain 2 to 32 letters, numbers, hyphens, or underscores.');
        }
        return $clean;
    }

    public function userPackPath(string $packId): string
    {
        $clean = self::sanitizePackId($packId);
        return $this->userPacksDirectory . DIRECTORY_SEPARATOR
            . 'zimrx_address_pack_' . strtolower($clean) . '.json';
    }

    public function systemPackPath(string $packId): string
    {
        $clean = self::sanitizePackId($packId);
        return $this->systemSeedsDirectory . DIRECTORY_SEPARATOR
            . 'zimrx_address_pack_' . strtolower($clean) . '.json';
    }

    public function hasSystemPack(string $packId): bool
    {
        return is_file($this->systemPackPath($packId));
    }

    /** @return array{packs:list<array<string,mixed>>,issues:list<array{file:string,reason:string}>} */
    public function getPackCatalog(): array
    {
        $packs = [];
        $issues = [];
        foreach (AddressPackImporter::catalog($this->systemSeedsDirectory) as $entry) {
            $packs[] = $this->catalogRecord($entry['path'], $entry['pack_id'], 'system');
        }

        $userPaths = glob($this->userPacksDirectory . DIRECTORY_SEPARATOR . 'zimrx_address_pack_*.json') ?: [];
        sort($userPaths, SORT_STRING);
        foreach ($userPaths as $path) {
            $filename = basename($path);
            if (preg_match('/^zimrx_address_pack_([a-z0-9_-]{2,32})\.json$/D', $filename, $matches) !== 1) {
                $issues[] = ['file' => $filename, 'reason' => 'Filename does not match the address pack naming format.'];
                continue;
            }
            try {
                $packId = self::sanitizePackId($matches[1]);
                $packs[] = $this->catalogRecord($path, $packId, 'user');
            } catch (Throwable $error) {
                $issues[] = ['file' => $filename, 'reason' => $error->getMessage()];
            }
        }

        usort($packs, static function (array $left, array $right): int {
            $nameOrder = strcasecmp((string)$left['name'], (string)$right['name']);
            if ($nameOrder !== 0) {
                return $nameOrder;
            }
            if ($left['pack_id'] === $right['pack_id']) {
                return strcmp((string)$left['source'], (string)$right['source']);
            }
            return strcmp((string)$left['pack_id'], (string)$right['pack_id']);
        });

        return ['packs' => $packs, 'issues' => $issues];
    }

    /** @return array{data:array<string,mixed>,revision:string,source:string} */
    public function loadPackSnapshot(string $packId, string $source): array
    {
        $clean = self::sanitizePackId($packId);
        $path = $this->pathForSource($clean, $source);
        $loaded = $this->readPack($path, $clean);
        return [
            'data' => $loaded['data'],
            'revision' => hash('sha256', $loaded['raw']),
            'source' => $source,
        ];
    }

    /** @return array{filename:string,content:string} */
    public function exportPack(string $packId, string $source): array
    {
        $clean = self::sanitizePackId($packId);
        $path = $this->pathForSource($clean, $source);
        $loaded = $this->readPack($path, $clean);
        return [
            'filename' => 'zimrx_address_pack_' . strtolower($clean) . '.json',
            'content' => $loaded['raw'],
        ];
    }

    /** @return array{success:true,pack_id:string,source:string,revision:string,last_updated:string} */
    public function savePack(string $packId, string $source, array $data, string $expectedRevision, string $contributor = ''): array
    {
        $clean = self::sanitizePackId($packId);
        if (!in_array($source, ['system', 'user'], true)) {
            throw new InvalidArgumentException('Pack source is invalid.');
        }
        $manifest = $data['manifest'] ?? null;
        if (!is_array($manifest) || !is_string($manifest['pack_id'] ?? null)
            || self::sanitizePackId($manifest['pack_id']) !== $clean) {
            throw new InvalidArgumentException('Pack manifest ID does not match the selected pack.');
        }
        AddressPackImporter::validatePackData($data, $clean);

        $contributor = trim($contributor);
        $today = date('Y-m-d');
        $data['manifest']['last_updated'] = $today;
        if (trim((string)$data['manifest']['author']) === '' && $contributor !== '') {
            $data['manifest']['author'] = $contributor;
        }
        if ($contributor !== '' && !in_array($contributor, $data['manifest']['contributors'], true)) {
            $data['manifest']['contributors'][] = $contributor;
        }
        $data['manifest']['contributors'] = array_values(array_unique($data['manifest']['contributors']));
        $encoded = $this->encodePack($data);
        $targetPath = $this->userPackPath($clean);

        $this->withWriteLock(function () use ($clean, $source, $expectedRevision, $encoded, $targetPath): void {
            $sourcePath = $this->pathForSource($clean, $source);
            $currentSourceRevision = is_file($sourcePath) ? hash_file('sha256', $sourcePath) : false;
            if (!is_string($currentSourceRevision) || !$this->matchesRevision($expectedRevision, $currentSourceRevision)) {
                throw new RuntimeException('This pack changed on disk. Reload it before saving.', 409);
            }
            if ($source === 'system' && file_exists($targetPath)) {
                throw new RuntimeException('A modified copy already exists. Open that copy to continue editing.', 409);
            }
            $this->writeAtomically($targetPath, $encoded);
        });

        return [
            'success' => true,
            'pack_id' => $clean,
            'source' => 'user',
            'revision' => hash('sha256', $encoded),
            'last_updated' => $today,
        ];
    }

    /** @return array{success:true,pack_id:string,revision:string,source:string} */
    public function importPack(string $raw, ?string $overwriteRevision = null): array
    {
        if (strlen($raw) < 1 || strlen($raw) > AddressPackImporter::MAX_PACK_BYTES) {
            throw new InvalidArgumentException('JSON file must be between 1 byte and 4 MB.');
        }
        try {
            $data = json_decode($raw, true, AddressPackImporter::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('The selected file is not valid address pack JSON.', 0, $error);
        }
        if (!is_array($data) || array_is_list($data) || !is_array($data['manifest'] ?? null)
            || !is_string($data['manifest']['pack_id'] ?? null)) {
            throw new InvalidArgumentException('JSON must contain a valid address pack manifest.');
        }
        $packId = self::sanitizePackId($data['manifest']['pack_id']);
        AddressPackImporter::validatePackData($data, $packId);
        $encoded = $this->encodePack($data);
        $path = $this->userPackPath($packId);

        $this->withWriteLock(function () use ($path, $overwriteRevision, $encoded): void {
            $exists = file_exists($path);
            if ($exists) {
                $currentRevision = hash_file('sha256', $path);
                if (!is_string($overwriteRevision) || !is_string($currentRevision)
                    || !$this->matchesRevision($overwriteRevision, $currentRevision)) {
                    throw new RuntimeException('This pack already exists or changed. Confirm replacement from the current pack list.', 409);
                }
            } elseif ($overwriteRevision !== null && $overwriteRevision !== '') {
                throw new RuntimeException('The pack changed after replacement was confirmed. Reload the pack list.', 409);
            }
            $this->writeAtomically($path, $encoded);
        });

        return [
            'success' => true,
            'pack_id' => $packId,
            'source' => 'user',
            'revision' => hash('sha256', $encoded),
        ];
    }

    /** @return array{success:true,pack_id:string,revision:string,source:string} */
    public function createBlankPack(array $params, ?string $overwriteRevision = null): array
    {
        $packId = self::sanitizePackId((string)($params['pack_id'] ?? ''));
        if ($this->hasSystemPack($packId)) {
            throw new RuntimeException('Open the system pack to create a modified copy with this ID.', 409);
        }

        $packType = $params['pack_type'] ?? null;
        $defaultLanguage = $params['default_lang'] ?? null;
        $languages = $params['languages'] ?? null;
        $rootNames = $params['root_names'] ?? null;
        $levels = $params['depth_levels'] ?? null;
        if (!is_string($packType) || !in_array($packType, ['national', 'custom'], true)
            || !is_string($defaultLanguage) || !is_array($languages) || !array_is_list($languages)
            || !is_array($rootNames) || array_is_list($rootNames)
            || !is_array($levels) || !array_is_list($levels) || $levels === []) {
            throw new InvalidArgumentException('Blank pack details have an invalid shape.');
        }
        $contributor = trim((string)($params['contributor'] ?? ''));
        $today = date('Y-m-d');
        $data = [
            'manifest' => [
                'pack_id' => $packId,
                'pack_type' => $packType,
                'code_label' => trim((string)($params['code_label'] ?? '')) ?: ($packType === 'custom' ? 'Block Code' : 'Postal Code'),
                'root_names' => $rootNames,
                'default_lang' => strtolower(trim($defaultLanguage)),
                'languages' => $languages,
                'version' => '1.0.0',
                'release_date' => $today,
                'last_updated' => $today,
                'author' => $contributor,
                'contributors' => $contributor === '' ? [] : [$contributor],
                'license' => trim((string)($params['license'] ?? 'CC-BY-4.0')),
                'sources' => [],
                'notes' => trim((string)($params['notes'] ?? '')),
            ],
            'hierarchy' => ['levels' => $levels],
            'places' => [],
        ];
        AddressPackImporter::validatePackData($data, $packId);
        $encoded = $this->encodePack($data);
        $path = $this->userPackPath($packId);
        $this->writeUserPack($path, $encoded, $overwriteRevision);

        return [
            'success' => true,
            'pack_id' => $packId,
            'source' => 'user',
            'revision' => hash('sha256', $encoded),
        ];
    }

    /** @return array{success:true,pack_id:string} */
    public function deletePack(string $packId, string $expectedRevision): array
    {
        $clean = self::sanitizePackId($packId);
        $path = $this->userPackPath($clean);
        $this->withWriteLock(function () use ($path, $expectedRevision): void {
            if (!is_file($path) || is_link($path)) {
                throw new RuntimeException('Modified pack was not found.', 404);
            }
            $currentRevision = hash_file('sha256', $path);
            if (!is_string($currentRevision) || !$this->matchesRevision($expectedRevision, $currentRevision)) {
                throw new RuntimeException('This pack changed on disk. Reload the pack list before deleting it.', 409);
            }
            if (!unlink($path)) {
                throw new RuntimeException('Geo Studio could not delete the pack JSON file.');
            }
        });
        return ['success' => true, 'pack_id' => $clean];
    }

    private function catalogRecord(string $path, string $packId, string $source): array
    {
        $loaded = $this->readPack($path, $packId);
        $manifest = $loaded['data']['manifest'];
        $counts = ['total' => 0, 'codes' => 0, 'types' => []];
        $this->countPlaces($loaded['data']['places'], $counts);
        $defaultLanguage = $manifest['default_lang'];
        $typeLabels = [];
        foreach ($loaded['data']['hierarchy']['levels'] as $level) {
            foreach ($level['types'] as $type) {
                $typeLabels[$type['key']] = $type['names'][$defaultLanguage] ?? $type['key'];
            }
        }
        arsort($counts['types'], SORT_NUMERIC);
        $topTypes = array_slice($counts['types'], 0, 4, true);

        return [
            'pack_id' => $packId,
            'source' => $source,
            'name' => $manifest['root_names'][$defaultLanguage] ?? $packId,
            'pack_type' => $manifest['pack_type'],
            'default_language' => $defaultLanguage,
            'languages' => $manifest['languages'],
            'code_label' => $manifest['code_label'],
            'version' => $manifest['version'],
            'release_date' => $manifest['release_date'],
            'last_updated' => $manifest['last_updated'],
            'author' => $manifest['author'],
            'contributors' => $manifest['contributors'],
            'license' => $manifest['license'],
            'total_places' => $counts['total'],
            'code_count' => $counts['codes'],
            'top_types' => $topTypes,
            'type_labels' => $typeLabels,
            'filesize' => strlen($loaded['raw']),
            'revision' => hash('sha256', $loaded['raw']),
        ];
    }

    private function countPlaces(array $places, array &$counts): void
    {
        foreach ($places as $place) {
            $counts['total']++;
            if (trim((string)($place['code'] ?? '')) !== '') {
                $counts['codes']++;
            }
            $types = is_array($place['type']) ? $place['type'] : [$place['type']];
            foreach ($types as $type) {
                $key = trim((string)$type);
                $counts['types'][$key] = ($counts['types'][$key] ?? 0) + 1;
            }
            $this->countPlaces($place['places'], $counts);
        }
    }

    private function readPack(string $path, string $expectedPackId): array
    {
        if (is_link($path) || !is_file($path)) {
            throw new RuntimeException('Address pack file was not found.', 404);
        }
        $size = filesize($path);
        if ($size === false || $size < 1 || $size > AddressPackImporter::MAX_PACK_BYTES) {
            throw new RuntimeException('Address pack JSON exceeds the supported 4 MB limit.');
        }
        $raw = file_get_contents($path, false, null, 0, AddressPackImporter::MAX_PACK_BYTES + 1);
        if (!is_string($raw) || strlen($raw) > AddressPackImporter::MAX_PACK_BYTES) {
            throw new RuntimeException('Address pack JSON could not be read.');
        }
        try {
            $data = json_decode($raw, true, AddressPackImporter::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Address pack JSON is invalid.', 0, $error);
        }
        if (!is_array($data) || array_is_list($data)) {
            throw new RuntimeException('Address pack JSON must contain an object.');
        }
        AddressPackImporter::validatePackData($data, $expectedPackId);
        return ['raw' => $raw, 'data' => $data];
    }

    private function pathForSource(string $packId, string $source): string
    {
        return match ($source) {
            'system' => $this->systemPackPath($packId),
            'user' => $this->userPackPath($packId),
            default => throw new InvalidArgumentException('Pack source is invalid.'),
        };
    }

    private function writeUserPack(string $path, string $encoded, ?string $overwriteRevision): void
    {
        $this->withWriteLock(function () use ($path, $encoded, $overwriteRevision): void {
            $exists = file_exists($path);
            if ($exists) {
                $revision = hash_file('sha256', $path);
                if (!is_string($overwriteRevision) || !is_string($revision)
                    || !$this->matchesRevision($overwriteRevision, $revision)) {
                    throw new RuntimeException('A pack with this ID already exists. Confirm replacement from the current pack list.', 409);
                }
            } elseif ($overwriteRevision !== null && $overwriteRevision !== '') {
                throw new RuntimeException('The pack changed after replacement was confirmed. Reload the pack list.', 409);
            }
            $this->writeAtomically($path, $encoded);
        });
    }

    private function withWriteLock(callable $operation): mixed
    {
        $lockPath = $this->userPacksDirectory . DIRECTORY_SEPARATOR . '.geo_studio.lock';
        $lock = fopen($lockPath, 'c+b');
        if ($lock === false) {
            throw new RuntimeException('Geo Studio could not open the pack write lock.');
        }
        @chmod($lockPath, 0600);
        try {
            $deadline = microtime(true) + 5.0;
            while (!flock($lock, LOCK_EX | LOCK_NB)) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Another Geo Studio operation is still saving. Try again.', 409);
                }
                usleep(50000);
            }
            return $operation();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function writeAtomically(string $path, string $content): void
    {
        if (is_link($path)) {
            throw new RuntimeException('Geo Studio cannot replace a symbolic link.');
        }
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $stream = fopen($temporary, 'xb');
        if ($stream === false) {
            throw new RuntimeException('Geo Studio could not create a temporary pack file.');
        }
        @chmod($temporary, 0600);
        try {
            $length = strlen($content);
            for ($offset = 0; $offset < $length; $offset += $written) {
                $written = fwrite($stream, substr($content, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Geo Studio could not write the pack JSON.');
                }
            }
            if (!fflush($stream)) {
                throw new RuntimeException('Geo Studio could not flush the pack JSON.');
            }
            if (function_exists('fsync') && !fsync($stream)) {
                throw new RuntimeException('Geo Studio could not sync the pack JSON to disk.');
            }
            fclose($stream);
            $stream = null;
            if (!rename($temporary, $path)) {
                throw new RuntimeException('Geo Studio could not replace the pack JSON.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function encodePack(array $data): string
    {
        try {
            $encoded = json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $error) {
            throw new RuntimeException('Geo Studio could not encode this address pack.', 0, $error);
        }
        if (strlen($encoded) > AddressPackImporter::MAX_PACK_BYTES) {
            throw new InvalidArgumentException('The address pack exceeds the supported 4 MB limit.');
        }
        return $encoded . "\n";
    }

    private function matchesRevision(string $expected, string $current): bool
    {
        return preg_match('/^[a-f0-9]{64}$/D', $expected) === 1 && hash_equals($current, $expected);
    }

    private function ensureUserPacksDirectory(): void
    {
        if (is_link($this->userPacksDirectory)) {
            throw new RuntimeException('Geo Studio address pack storage cannot be a symbolic link.');
        }
        if (!is_dir($this->userPacksDirectory)
            && !mkdir($this->userPacksDirectory, 0700, true)
            && !is_dir($this->userPacksDirectory)) {
            throw new RuntimeException('Geo Studio could not create the user address pack directory.');
        }
        $realDirectory = realpath($this->userPacksDirectory);
        if ($realDirectory === false || !is_writable($realDirectory)) {
            throw new RuntimeException('Geo Studio user address pack directory is not writable.');
        }
    }
}
