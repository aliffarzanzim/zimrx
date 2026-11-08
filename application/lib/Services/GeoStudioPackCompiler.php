<?php
declare(strict_types=1);

namespace ZimRx\Services;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/AddressPackImporter.php';
require_once __DIR__ . '/GeoStudioPackRepository.php';

final class GeoStudioPackCompiler
{
    public static function refreshIfActiveUserPack(
        PDO $staticPdo,
        PDO $userPdo,
        GeoStudioPackRepository $packs,
        string $packId
    ): ?bool {
        $cleanPackId = GeoStudioPackRepository::sanitizePackId($packId);
        if (self::configValue($userPdo, 'address_pack_id') !== $cleanPackId
            || self::configValue($userPdo, 'address_pack_source') !== 'user') {
            return null;
        }
        try {
            self::selectPack(
                $staticPdo,
                $userPdo,
                $packs,
                $cleanPackId,
                'user',
                self::configValue($userPdo, 'address_language')
            );
            return true;
        } catch (Throwable $error) {
            error_log('[ZimRx Geo Studio] Active address pack could not be refreshed: ' . $error->getMessage());
            return false;
        }
    }

    public static function selectPack(
        PDO $staticPdo,
        PDO $userPdo,
        GeoStudioPackRepository $packs,
        string $packId,
        string $source,
        string $language
    ): array {
        $cleanPackId = GeoStudioPackRepository::sanitizePackId($packId);
        if (!in_array($source, ['system', 'user'], true)) {
            throw new InvalidArgumentException('Address pack source is invalid.');
        }
        if ($source === 'user') {
            $entry = AddressPackImporter::catalogEntry($packs->userPackPath($cleanPackId), $cleanPackId);
            $metadata = [
                'default_language' => $entry['manifest']['default_lang'],
                'languages_json' => json_encode($entry['manifest']['languages'], JSON_THROW_ON_ERROR),
            ];
        } else {
            $metadata = self::findPack($staticPdo, $userPdo, $cleanPackId, $source);
        }
        if (!is_array($metadata)) {
            throw new RuntimeException('The selected address pack is unavailable.', 404);
        }
        $supportedLanguages = json_decode((string)$metadata['languages_json'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($supportedLanguages) || !array_is_list($supportedLanguages)) {
            throw new RuntimeException('Address pack language metadata is invalid.');
        }
        $selectedLanguage = in_array($language, $supportedLanguages, true)
            ? $language
            : (string)$metadata['default_language'];

        $ownsTransaction = !$userPdo->inTransaction();
        if ($ownsTransaction) {
            $userPdo->beginTransaction();
        }
        try {
            if ($source === 'user') {
                AddressPackImporter::import($userPdo, [$entry], 'user');
            }
            self::setConfig($userPdo, 'address_pack_id', $cleanPackId);
            self::setConfig($userPdo, 'address_pack_source', $source);
            self::setConfig($userPdo, 'address_language', $selectedLanguage);
            if ($ownsTransaction) {
                $userPdo->commit();
            }
        } catch (Throwable $error) {
            if ($ownsTransaction && $userPdo->inTransaction()) {
                $userPdo->rollBack();
            }
            throw $error;
        }

        return [
            'pack_id' => $cleanPackId,
            'source' => $source,
            'language' => $selectedLanguage,
            'default_language' => (string)$metadata['default_language'],
            'languages' => $supportedLanguages,
        ];
    }

    public static function removePack(PDO $userPdo, PDO $staticPdo, string $packId): void
    {
        $cleanPackId = GeoStudioPackRepository::sanitizePackId($packId);
        $ownsTransaction = !$userPdo->inTransaction();
        if ($ownsTransaction) {
            $userPdo->beginTransaction();
        }
        try {
            $delete = $userPdo->prepare('DELETE FROM zimrx_user_address_packs WHERE pack_id = :pack_id');
            $delete->execute(['pack_id' => $cleanPackId]);
            $active = self::configValue($userPdo, 'address_pack_id');
            $source = self::configValue($userPdo, 'address_pack_source');
            if ($active === $cleanPackId && $source === 'user') {
                $systemPack = self::findPack($staticPdo, $userPdo, $cleanPackId, 'system');
                if (is_array($systemPack)) {
                    $languages = json_decode((string)$systemPack['languages_json'], true, 512, JSON_THROW_ON_ERROR);
                    $language = self::configValue($userPdo, 'address_language');
                    self::setConfig($userPdo, 'address_pack_source', 'system');
                    if (!is_array($languages) || !in_array($language, $languages, true)) {
                        self::setConfig($userPdo, 'address_language', (string)$systemPack['default_language']);
                    }
                } else {
                    self::setConfig($userPdo, 'address_pack_id', '');
                    self::setConfig($userPdo, 'address_pack_source', '');
                    self::setConfig($userPdo, 'address_language', '');
                }
            }
            if ($ownsTransaction) {
                $userPdo->commit();
            }
        } catch (Throwable $error) {
            if ($ownsTransaction && $userPdo->inTransaction()) {
                $userPdo->rollBack();
            }
            throw $error;
        }
    }

    private static function findPack(PDO $staticPdo, PDO $userPdo, string $packId, string $source): ?array
    {
        $pdo = $source === 'user' ? $userPdo : $staticPdo;
        $table = $source === 'user' ? 'zimrx_user_address_packs' : 'zimrx_address_packs';
        $stmt = $pdo->prepare(
            "SELECT pack_id, default_language, languages_json FROM {$table} WHERE pack_id = :pack_id LIMIT 1"
        );
        $stmt->execute(['pack_id' => $packId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private static function configValue(PDO $pdo, string $key): string
    {
        $stmt = $pdo->prepare('SELECT config_value FROM zimrx_app_config WHERE config_key = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        return trim((string)($stmt->fetchColumn() ?: ''));
    }

    private static function setConfig(PDO $pdo, string $key, string $value): void
    {
        $sql = 'INSERT INTO zimrx_app_config (config_key, config_value, updated_at) '
            . 'VALUES (:key, :value, CURRENT_TIMESTAMP) '
            . DbSql::upsert('config_key', ['config_value', 'updated_at'], ['updated_at' => DbSql::now()]);
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['key' => $key, 'value' => $value]);
    }
}
