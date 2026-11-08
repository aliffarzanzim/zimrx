<?php
declare(strict_types=1);

namespace GeoStudio;

use InvalidArgumentException;

final class PackLanguageRules
{
    public const MAX_LANGUAGES = 64;

    public static function normalizeLanguageList(mixed $languages): array
    {
        if (is_string($languages)) {
            $languages = preg_split('/[,\s]+/', trim($languages), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        if (!is_array($languages) || !array_is_list($languages)) {
            throw new InvalidArgumentException('Pack languages must be a list of language codes.');
        }
        $normalized = [];
        foreach ($languages as $language) {
            if (!is_string($language)) {
                throw new InvalidArgumentException('Pack languages must contain only language codes.');
            }
            $language = strtolower(trim($language));
            if (strlen($language) > 35 || !preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $language)) {
                throw new InvalidArgumentException("Invalid language code '{$language}'.");
            }
            $normalized[$language] = $language;
        }
        if (count($normalized) !== count($languages)) {
            throw new InvalidArgumentException('Pack languages must not contain duplicates.');
        }
        if ($normalized === [] || count($normalized) > self::MAX_LANGUAGES) {
            throw new InvalidArgumentException('A pack must declare between 1 and 64 languages.');
        }
        return array_values($normalized);
    }

    public static function normalizeNameMap(
        mixed $names,
        array $languages,
        bool $requireDefault = true,
        string $defaultLanguage = 'en',
        bool $requireAllLanguages = false
    ): array
    {
        if (!is_array($names) || ($names !== [] && array_is_list($names))) {
            throw new InvalidArgumentException('Names must be an object keyed by language code.');
        }
        $result = [];
        foreach ($names as $language => $name) {
            if (!is_string($language) || $language !== strtolower($language) || !in_array($language, $languages, true)
                || !is_string($name) || trim($name) === '' || preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
                throw new InvalidArgumentException('Names must use declared language codes and non-empty text values.');
            }
            $result[strtolower($language)] = trim($name);
        }
        if ($requireDefault && !isset($result[$defaultLanguage])) {
            throw new InvalidArgumentException("Every place and type needs a name in the default language '{$defaultLanguage}'.");
        }
        if ($requireAllLanguages && array_diff($languages, array_keys($result)) !== []) {
            throw new InvalidArgumentException('Root names must include a value for every declared pack language.');
        }
        return $result;
    }
}
