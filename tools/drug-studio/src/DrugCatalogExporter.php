<?php
declare(strict_types=1);

namespace ZimRx\DrugStudio;

use PDO;
use RuntimeException;
use InvalidArgumentException;

class DrugCatalogExporter
{
    private DrugCatalogRepository $repository;
    private string $seedsDir;
    private string $manifestsDir;

    public function __construct(DrugCatalogRepository $repository, string $seedsDir, string $manifestsDir)
    {
        $this->repository = $repository;
        $this->seedsDir = rtrim($seedsDir, DIRECTORY_SEPARATOR);
        $this->manifestsDir = rtrim($manifestsDir, DIRECTORY_SEPARATOR);

        if (!is_dir($this->seedsDir)) {
            mkdir($this->seedsDir, 0755, true);
        }
        if (!is_dir($this->manifestsDir)) {
            mkdir($this->manifestsDir, 0755, true);
        }
    }

    /**
     * Export deterministic SQL and update manifest for core or country pack.
     *
     * @return array{success: bool, sql_file: string, manifest_file: string, sha256: string, size_bytes: int, table_counts: array<string, int>}
     */
    public function export(string $scope, array $manifestOverrides = []): array
    {
        $cleanScope = strtolower(trim($scope));
        if ($cleanScope === 'core') {
            return $this->exportCoreCatalog($manifestOverrides);
        }

        $code = DrugCatalogValidator::validateCountryCode($cleanScope);
        return $this->exportCountryPack($code, $manifestOverrides);
    }

    /**
     * Export deterministic core seed (zimrx_drugs.sql).
     */
    private function exportCoreCatalog(array $manifestOverrides): array
    {
        $pdo = $this->repository->getPdo();
        $targetFile = $this->seedsDir . DIRECTORY_SEPARATOR . 'zimrx_drugs.sql';
        $tempFile = $targetFile . '.tmp';

        $handle = fopen($tempFile, 'wb');
        if (!$handle) {
            throw new RuntimeException("Cannot open temp file for writing: {$tempFile}");
        }

        $tableCounts = [];

        try {
            fwrite($handle, "/* ==============================================================================\n");
            fwrite($handle, " * ZimRx Master Reference Database Seed: Universal Clinical Core Catalog\n");
            fwrite($handle, " * Generated deterministically by ZimRx Drug Studio\n");
            fwrite($handle, " * Date: " . date('Y-m-d H:i:s') . "\n");
            fwrite($handle, " * ============================================================================== */\n\n");
            fwrite($handle, "PRAGMA foreign_keys=OFF;\n");
            fwrite($handle, "BEGIN TRANSACTION;\n\n");

            // Define core tables in exact canonical seed order
            $coreTables = [
                'zimrx_drug_db_version' => '1',
                'zimrx_drug_patches' => 'patch_id',
                'drug_dosage_form' => 'form_id',
                'drug_generic' => 'generic_id',
                'drug_generic_product' => 'product_id',
                'drug_pregnancy_category' => 'id',
                'drug_therapeutic_class' => 'class_id',
                'drug_indication' => 'indication_id',
                'drug_interaction' => 'id',
                'drug_template' => 'id',
                'who_atc_hierarchy' => 'atc_code',
                'who_ddd_reference' => 'id',
                'who_inn_catalog' => 'inn_name_en',
            ];

            foreach ($coreTables as $table => $orderCol) {
                $hasTable = (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='{$table}'")->fetchColumn();
                if (!$hasTable) {
                    continue;
                }

                $createSql = $this->getTableSchema($pdo, $table);
                if ($createSql !== '') {
                    fwrite($handle, $createSql . ";\n");
                }

                $count = $this->dumpTableRows($pdo, $handle, $table, $orderCol);
                $tableCounts[$table] = $count;
                fwrite($handle, "\n");
            }

            fwrite($handle, "COMMIT;\n");
            fclose($handle);

            // Compute hash and size
            $sha256 = hash_file('sha256', $tempFile);
            $sizeBytes = filesize($tempFile);

            // Atomic rename
            if (file_exists($targetFile)) {
                @unlink($targetFile);
            }
            if (!rename($tempFile, $targetFile)) {
                copy($tempFile, $targetFile);
                @unlink($tempFile);
            }

            // Update companion manifest
            $manifestFile = $this->manifestsDir . DIRECTORY_SEPARATOR . 'core.manifest.json';
            $manifest = $this->loadManifest('core');
            $manifest = array_merge($manifest, $manifestOverrides);
            $manifest['scope'] = 'core';
            $manifest['dump_filename'] = basename($targetFile);
            $manifest['sha256'] = $sha256;
            $manifest['file_size_bytes'] = $sizeBytes;
            $manifest['table_counts'] = $tableCounts;
            $manifest['last_updated'] = date('Y-m-d H:i:s');
            file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return [
                'success' => true,
                'sql_file' => $targetFile,
                'manifest_file' => $manifestFile,
                'sha256' => $sha256,
                'size_bytes' => $sizeBytes,
                'table_counts' => $tableCounts,
            ];
        } catch (\Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
            throw $e;
        }
    }

    /**
     * Export deterministic country pack seed (zimrx_drugs_{country}.sql).
     */
    private function exportCountryPack(string $code, array $manifestOverrides): array
    {
        $pdo = $this->repository->getPdo();
        $lower = strtolower($code);
        $targetFile = $this->seedsDir . DIRECTORY_SEPARATOR . "zimrx_drugs_{$lower}.sql";
        $tempFile = $targetFile . '.tmp';

        $handle = fopen($tempFile, 'wb');
        if (!$handle) {
            throw new RuntimeException("Cannot open temp file for writing: {$tempFile}");
        }

        $tableCounts = [];

        try {
            fwrite($handle, "/* ==============================================================================\n");
            fwrite($handle, " * ZimRx Master Reference Database Seed: {$code} Country Pack\n");
            fwrite($handle, " * Commercial Brands, Manufacturers & Phrasing\n");
            fwrite($handle, " * Generated deterministically by ZimRx Drug Studio\n");
            fwrite($handle, " * Date: " . date('Y-m-d H:i:s') . "\n");
            fwrite($handle, " * ============================================================================== */\n\n");
            fwrite($handle, "PRAGMA foreign_keys=OFF;\n");
            fwrite($handle, "BEGIN TRANSACTION;\n\n");

            $mfgTable = "drug_manufacturer_{$lower}";
            $brandTable = "drug_brand_{$lower}";

            $hasMfg = (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='{$mfgTable}'")->fetchColumn();
            if ($hasMfg) {
                $createSql = $this->getTableSchema($pdo, $mfgTable);
                fwrite($handle, $createSql . ";\n");
                $mfgCount = $this->dumpTableRows($pdo, $handle, $mfgTable, "CAST(id AS INTEGER), id");
                $tableCounts[$mfgTable] = $mfgCount;
                fwrite($handle, "\n");
            }

            $hasBrand = (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='{$brandTable}'")->fetchColumn();
            if ($hasBrand) {
                $createSql = $this->getTableSchema($pdo, $brandTable);
                fwrite($handle, $createSql . ";\n");
                $brandCount = $this->dumpTableRows($pdo, $handle, $brandTable, "CAST(brand_id AS INTEGER), brand_id");
                $tableCounts[$brandTable] = $brandCount;
                fwrite($handle, "\n");
            }

            fwrite($handle, "COMMIT;\n");
            fclose($handle);

            $sha256 = hash_file('sha256', $tempFile);
            $sizeBytes = filesize($tempFile);

            if (file_exists($targetFile)) {
                @unlink($targetFile);
            }
            if (!rename($tempFile, $targetFile)) {
                copy($tempFile, $targetFile);
                @unlink($tempFile);
            }

            $manifestFile = $this->manifestsDir . DIRECTORY_SEPARATOR . "{$lower}.manifest.json";
            $manifest = $this->loadManifest($lower);
            $manifest = array_merge($manifest, $manifestOverrides);
            $manifest['scope'] = $lower;
            $manifest['country_code'] = $code;
            $manifest['dump_filename'] = basename($targetFile);
            $manifest['sha256'] = $sha256;
            $manifest['file_size_bytes'] = $sizeBytes;
            $manifest['table_counts'] = $tableCounts;
            $manifest['last_updated'] = date('Y-m-d H:i:s');
            file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return [
                'success' => true,
                'sql_file' => $targetFile,
                'manifest_file' => $manifestFile,
                'sha256' => $sha256,
                'size_bytes' => $sizeBytes,
                'table_counts' => $tableCounts,
            ];
        } catch (\Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
            throw $e;
        }
    }

    private function getTableSchema(PDO $pdo, string $tableName): string
    {
        $stmt = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name=:t LIMIT 1");
        $stmt->execute([':t' => $tableName]);
        return trim((string)$stmt->fetchColumn());
    }

    /**
     * Dumps table rows deterministically with explicit column names.
     */
    private function dumpTableRows(PDO $pdo, $handle, string $tableName, string $orderBy): int
    {
        $colStmt = $pdo->query("PRAGMA table_info(\"{$tableName}\")");
        $columns = [];
        while ($col = $colStmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $col['name'];
        }

        if (empty($columns)) {
            return 0;
        }

        $colList = implode(', ', array_map(fn($c) => "\"{$c}\"", $columns));
        $query = "SELECT {$colList} FROM \"{$tableName}\" ORDER BY {$orderBy}";
        $stmt = $pdo->query($query);
        $count = 0;

        $colHeader = 'INSERT INTO "' . $tableName . '" (' . $colList . ') VALUES(';

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $vals = [];
            foreach ($row as $val) {
                if ($val === null) {
                    $vals[] = 'NULL';
                } elseif (is_int($val) || is_float($val)) {
                    $vals[] = (string)$val;
                } else {
                    $escaped = str_replace("'", "''", (string)$val);
                    $vals[] = "'" . $escaped . "'";
                }
            }

            fwrite($handle, $colHeader . implode(', ', $vals) . ");\n");
            $count++;
        }

        return $count;
    }

    /**
     * Load manifest for given scope.
     *
     * @return array<string, mixed>
     */
    public function loadManifest(string $scope): array
    {
        $lower = strtolower(trim($scope));
        $manifestPath = $this->manifestsDir . DIRECTORY_SEPARATOR . "{$lower}.manifest.json";

        if (file_exists($manifestPath)) {
            $data = json_decode((string)file_get_contents($manifestPath), true);
            if (is_array($data)) {
                return $data;
            }
        }

        return [
            'scope' => $lower,
            'name' => $lower === 'core' ? 'ZimRx Universal Clinical Drug Core' : "Country Pack {$scope}",
            'version' => '1.0.0',
            'author' => 'Alif Farzan Zim',
            'contributors' => ['Alif Farzan Zim'],
            'license' => 'GPL-3.0-or-later',
            'attribution' => 'ZimRx Clinical Knowledge Base',
            'release_notes' => 'Catalog update.',
            'release_date' => date('Y-m-d'),
            'last_updated' => date('Y-m-d'),
        ];
    }

    /**
     * Save manifest for given scope.
     */
    public function saveManifest(string $scope, array $data): void
    {
        $lower = strtolower(trim($scope));
        $manifestPath = $this->manifestsDir . DIRECTORY_SEPARATOR . "{$lower}.manifest.json";
        $current = $this->loadManifest($lower);

        $merged = array_merge($current, $data);
        $merged['last_updated'] = date('Y-m-d H:i:s');

        file_put_contents($manifestPath, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
