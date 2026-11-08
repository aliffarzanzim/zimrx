<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/RequestBootstrap.php';
require_once dirname(__DIR__) . '/lib/ApiResponse.php';
require_once dirname(__DIR__) . '/lib/PackRepository.php';

$bootstrap = \GeoStudio\RequestBootstrap::initialize();
$packs = new \GeoStudio\PackRepository($bootstrap['packsDir']);

\GeoStudio\ApiResponse::run('POST', static function () use ($packs): array {
    $maxRequestBytes = \GeoStudio\ResourceLimits::packBytes() + 65536;
    $contentLength = $_SERVER['CONTENT_LENGTH'] ?? '0';
    if (!is_string($contentLength) || !ctype_digit($contentLength)) {
        throw new InvalidArgumentException('Request size is invalid.');
    }
    if ((int)$contentLength > $maxRequestBytes) {
        throw new InvalidArgumentException('Request body exceeds the supported size limit.', 413);
    }

    $params = $_POST;
    $allowedFields = [
        'pack_id', 'pack_type', 'code_label', 'root_names', 'default_lang', 'languages',
        'contributor', 'license', 'notes', 'depth_levels', 'overwrite_confirmed', 'overwrite_revision',
    ];
    foreach (['pack_id', 'pack_type', 'root_names', 'default_lang', 'languages', 'depth_levels'] as $requiredField) {
        if (!array_key_exists($requiredField, $params)) {
            throw new InvalidArgumentException("Blank pack request is missing '{$requiredField}'.");
        }
    }
    if (array_diff(array_keys($params), $allowedFields) !== []) {
        throw new InvalidArgumentException('Blank pack request contains unsupported fields.');
    }
    foreach ($params as $value) {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Blank pack request fields must be strings.');
        }
    }
    if (isset($params['overwrite_confirmed']) && !in_array($params['overwrite_confirmed'], ['0', '1'], true)) {
        throw new InvalidArgumentException('Overwrite confirmation must be 0 or 1.');
    }
    if (($params['overwrite_confirmed'] ?? '0') === '1' && !isset($params['overwrite_revision'])) {
        throw new InvalidArgumentException('Overwrite confirmation is missing the pack revision.');
    }
    foreach (['depth_levels', 'root_names'] as $field) {
        try {
            $params[$field] = json_decode($params[$field], true, \GeoStudio\PackValidator::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new InvalidArgumentException(ucfirst(str_replace('_', ' ', $field)) . ' contains invalid JSON.', 0, $error);
        }
    }
    if (!is_array($params['root_names']) || array_is_list($params['root_names'])
        || !is_array($params['depth_levels']) || !array_is_list($params['depth_levels'])) {
        throw new InvalidArgumentException('Pack root names and hierarchy levels have an invalid shape.');
    }

    return $packs->createBlankPack($params);
}, true);
