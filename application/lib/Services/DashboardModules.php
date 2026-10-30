<?php
declare(strict_types=1);

namespace ZimRx\Services;

final class DashboardModules
{
    private array $modules = [];
    private array $nameIndex = [];

    public function __construct(string $moduleDirectory)
    {
        $moduleRoot = realpath($moduleDirectory);
        if ($moduleRoot === false) {
            throw new \RuntimeException('Dashboard module directories are unavailable.');
        }

        $paths = glob($moduleRoot . DIRECTORY_SEPARATOR . '*.json', GLOB_NOSORT);
        if ($paths === false) {
            throw new \RuntimeException('Dashboard module manifests could not be listed.');
        }
        sort($paths, SORT_STRING);

        $ids = [];
        $defaultOrders = ['left' => [], 'right' => []];
        foreach ($paths as $path) {
            $resolvedManifest = realpath($path);
            if ($resolvedManifest === false || dirname($resolvedManifest) !== $moduleRoot) {
                throw new \RuntimeException('Dashboard module manifest path is invalid.');
            }

            $contents = file_get_contents($resolvedManifest);
            if ($contents === false) {
                throw new \RuntimeException('Dashboard module manifest could not be read.');
            }

            try {
                $manifest = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new \RuntimeException('Dashboard module manifest contains invalid JSON.', 0, $exception);
            }

            $module = $this->normalizeManifest($manifest, $moduleRoot, pathinfo($resolvedManifest, PATHINFO_FILENAME));
            if (isset($ids[$module['id']])) {
                throw new \RuntimeException('Dashboard module IDs must be unique.');
            }
            $ids[$module['id']] = true;

            $names = array_merge([$module['name']], $module['aliases']);
            foreach ($names as $name) {
                if (isset($this->nameIndex[$name])) {
                    throw new \RuntimeException('Dashboard module names and aliases must be unique.');
                }
                $this->nameIndex[$name] = $module;
            }

            if ($module['default_order'] !== null) {
                $order = $module['default_order'];
                if (isset($defaultOrders[$module['panel']][$order])) {
                    throw new \RuntimeException('Dashboard module default orders must be unique per panel.');
                }
                $defaultOrders[$module['panel']][$order] = true;
            }

            $this->modules[] = $module;
        }

        if ($this->modules === []) {
            throw new \RuntimeException('No dashboard module manifests were found.');
        }
    }

    /** @return list<string> */
    public function availableNames(string $panel): array
    {
        $this->assertPanel($panel);
        $names = [];
        foreach ($this->modules as $module) {
            if ($module['panel'] === $panel) {
                $names[] = $module['name'];
            }
        }
        usort($names, static fn(string $left, string $right): int => strnatcasecmp($left, $right) ?: strcmp($left, $right));
        return $names;
    }

    /** @return list<string> */
    public function defaultLayout(string $panel): array
    {
        $this->assertPanel($panel);
        $modules = array_values(array_filter(
            $this->modules,
            static fn(array $module): bool => $module['panel'] === $panel
                && $module['default_order'] !== null
                && $module['enabled_by_default']
        ));
        usort($modules, static fn(array $left, array $right): int => $left['default_order'] <=> $right['default_order']);
        return array_column($modules, 'name');
    }

    public function supportsPanel(string $name, string $panel): bool
    {
        $this->assertPanel($panel);
        return isset($this->nameIndex[$name]) && $this->nameIndex[$name]['panel'] === $panel;
    }

    public function canonicalName(string $name): ?string
    {
        return $this->nameIndex[$name]['name'] ?? null;
    }

    public function viewPathFor(string $name): ?string
    {
        return $this->nameIndex[$name]['view_path'] ?? null;
    }

    /** @return array{available:array{left:list<string>,right:list<string>},defaults:array{left:list<string>,right:list<string>}} */
    public function clientConfig(): array
    {
        return [
            'available' => [
                'left' => array_merge([''], $this->availableNames('left')),
                'right' => array_merge([''], $this->availableNames('right')),
            ],
            'defaults' => [
                'left' => $this->defaultLayout('left'),
                'right' => $this->defaultLayout('right'),
            ],
        ];
    }

    private function normalizeManifest(mixed $manifest, string $moduleRoot, string $manifestName): array
    {
        if (!is_array($manifest) || ($manifest['schema_version'] ?? null) !== 1) {
            throw new \RuntimeException('Dashboard module manifest schema is invalid.');
        }

        $id = $manifest['id'] ?? null;
        $name = $manifest['name'] ?? null;
        $panel = $manifest['panel'] ?? null;
        $view = $manifest['view'] ?? null;
        $defaultOrder = $manifest['default_order'] ?? null;
        $enabledByDefault = $manifest['enabled_by_default'] ?? null;
        $aliases = $manifest['aliases'] ?? [];

        if (!is_string($id) || preg_match('/^[a-z][a-z0-9_]*$/', $id) !== 1
            || !is_string($name) || trim($name) === '' || mb_strlen($name, 'UTF-8') > 80
            || !is_string($panel) || !in_array($panel, ['left', 'right'], true)
            || !is_string($view) || preg_match('/^[a-z0-9_]+\.php$/', $view) !== 1
            || pathinfo($view, PATHINFO_FILENAME) !== $manifestName
            || ($defaultOrder !== null && (!is_int($defaultOrder) || $defaultOrder < 1))
            || !is_bool($enabledByDefault)
            || ($defaultOrder === null && $enabledByDefault)
            || !is_array($aliases)) {
            throw new \RuntimeException('Dashboard module manifest fields are invalid.');
        }

        $viewPath = realpath($moduleRoot . DIRECTORY_SEPARATOR . $view);
        if ($viewPath === false || dirname($viewPath) !== $moduleRoot || !is_file($viewPath)) {
            throw new \RuntimeException('Dashboard module view is outside the approved module directory.');
        }

        $normalizedAliases = [];
        foreach ($aliases as $alias) {
            if (!is_string($alias) || trim($alias) === '' || mb_strlen($alias, 'UTF-8') > 80 || $alias === $name) {
                throw new \RuntimeException('Dashboard module aliases are invalid.');
            }
            $normalizedAliases[] = $alias;
        }
        if (count($normalizedAliases) !== count(array_unique($normalizedAliases, SORT_STRING))) {
            throw new \RuntimeException('Dashboard module aliases must be unique.');
        }

        return [
            'id' => $id,
            'name' => $name,
            'panel' => $panel,
            'view_path' => $viewPath,
            'default_order' => $defaultOrder,
            'enabled_by_default' => $enabledByDefault,
            'aliases' => $normalizedAliases,
        ];
    }

    private function assertPanel(string $panel): void
    {
        if (!in_array($panel, ['left', 'right'], true)) {
            throw new \InvalidArgumentException('Dashboard panel must be left or right.');
        }
    }
}
