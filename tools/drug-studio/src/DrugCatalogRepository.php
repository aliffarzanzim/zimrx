<?php
declare(strict_types=1);

namespace ZimRx\DrugStudio;

use PDO;
use RuntimeException;
use InvalidArgumentException;
use DbConnections;

class DrugCatalogRepository
{
    private string $dbPath;
    private string $baseAppDir;
    private string $manifestsDir;
    private ?PDO $pdo = null;

    public function __construct(string $dbPath, string $baseAppDir, string $manifestsDir)
    {
        $this->dbPath = $dbPath;
        $this->baseAppDir = rtrim($baseAppDir, DIRECTORY_SEPARATOR);
        $this->manifestsDir = rtrim($manifestsDir, DIRECTORY_SEPARATOR);

        if (!is_dir(dirname($this->dbPath))) {
            mkdir(dirname($this->dbPath), 0755, true);
        }
        if (!is_dir($this->manifestsDir)) {
            mkdir($this->manifestsDir, 0755, true);
        }

        $this->ensureCurationDatabase();
    }

    /**
     * Get active PDO connection using central DbConnections.
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = DbConnections::openSqlite($this->dbPath);
        }
        return $this->pdo;
    }

    /**
     * Return curation database path.
     */
    public function getDbPath(): string
    {
        return $this->dbPath;
    }

    /**
     * Ensures canonical curation database is populated with core catalog and BD pack.
     */
    private function ensureCurationDatabase(): void
    {
        $needsInit = !file_exists($this->dbPath) || filesize($this->dbPath) < 10240;

        if ($needsInit) {
            $shippedDb = $this->baseAppDir . DIRECTORY_SEPARATOR . 'systemdata' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'zimrx_drugs.db';
            if (file_exists($shippedDb) && filesize($shippedDb) > 100000) {
                copy($shippedDb, $this->dbPath);
            }
        }

        $pdo = $this->getPdo();

        // Check if drug_generic exists
        $hasGeneric = (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='drug_generic'")->fetchColumn();
        if (!$hasGeneric) {
            $coreSeed = $this->baseAppDir . DIRECTORY_SEPARATOR . 'systemdata' . DIRECTORY_SEPARATOR . 'seeds' . DIRECTORY_SEPARATOR . 'zimrx_drugs.sql';
            if (file_exists($coreSeed)) {
                $this->executeSqlFile($pdo, $coreSeed);
            }
        }

        // Check if drug_brand_bd exists
        $hasBrandBd = (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='drug_brand_bd'")->fetchColumn();
        if (!$hasBrandBd) {
            $bdSeed = $this->baseAppDir . DIRECTORY_SEPARATOR . 'systemdata' . DIRECTORY_SEPARATOR . 'seeds' . DIRECTORY_SEPARATOR . 'zimrx_drugs_bd.sql';
            if (file_exists($bdSeed)) {
                $this->executeSqlFile($pdo, $bdSeed);
            }
        }

        // Ensure default manifests exist
        $this->ensureDefaultManifests();
    }

    /**
     * Executes plain-text SQL statements in chunks.
     */
    private function executeSqlFile(PDO $pdo, string $filePath): void
    {
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return;
        }

        $buffer = '';
        $pdo->beginTransaction();

        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);
            if ($buffer === '') {
                if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                    continue;
                }
            }

            $buffer .= $line;

            if (str_ends_with($trimmed, ';')) {
                $statement = trim($buffer);
                $buffer = '';

                $upper = strtoupper($statement);
                if (str_starts_with($upper, 'BEGIN') || str_starts_with($upper, 'COMMIT')) {
                    continue;
                }

                try {
                    $pdo->exec($statement);
                } catch (\Throwable) {
                    // Ignore defensive pragma errors
                }
            }
        }

        fclose($handle);
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    }

    private function ensureDefaultManifests(): void
    {
        $coreManifestPath = $this->manifestsDir . DIRECTORY_SEPARATOR . 'core.manifest.json';
        if (!file_exists($coreManifestPath)) {
            $manifest = [
                'scope' => 'core',
                'name' => 'ZimRx Universal Clinical Drug Core',
                'version' => '1.0.0',
                'author' => 'Alif Farzan Zim',
                'contributors' => ['Alif Farzan Zim'],
                'license' => 'GPL-3.0-or-later',
                'attribution' => 'ZimRx Clinical Knowledge Base',
                'release_notes' => 'Canonical generic clinical catalog, WHO ATC classification, dosing, and safety rules.',
                'release_date' => date('Y-m-d'),
                'last_updated' => date('Y-m-d'),
            ];
            file_put_contents($coreManifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $bdManifestPath = $this->manifestsDir . DIRECTORY_SEPARATOR . 'bd.manifest.json';
        if (!file_exists($bdManifestPath)) {
            $manifest = [
                'scope' => 'bd',
                'name' => 'Bangladesh Commercial Drug Formulary',
                'version' => '1.0.0',
                'author' => 'Alif Farzan Zim',
                'contributors' => ['Alif Farzan Zim'],
                'license' => 'Proprietary Clinical / Open Medical Reference',
                'attribution' => 'Directorate General of Drug Administration (DGDA) Bangladesh Reference',
                'release_notes' => 'Commercial trade brand names, dosage variants, strengths, and licensed manufacturers for Bangladesh.',
                'release_date' => date('Y-m-d'),
                'last_updated' => date('Y-m-d'),
            ];
            file_put_contents($bdManifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Get aggregate statistics across catalog.
     *
     * @return array<string, int>
     */
    public function getStudioStats(): array
    {
        $pdo = $this->getPdo();

        $totalGenerics = (int)$pdo->query("SELECT COUNT(*) FROM drug_generic")->fetchColumn();
        $totalDosageForms = (int)$pdo->query("SELECT COUNT(*) FROM drug_dosage_form")->fetchColumn();
        $totalClasses = (int)$pdo->query("SELECT COUNT(*) FROM drug_therapeutic_class")->fetchColumn();
        $totalIndications = (int)$pdo->query("SELECT COUNT(*) FROM drug_indication")->fetchColumn();

        // Count brands across all country tables
        $tables = $this->listCountryTables();
        $totalBrands = 0;
        foreach ($tables as $t) {
            $totalBrands += (int)$pdo->query("SELECT COUNT(*) FROM \"{$t['brand_table']}\"")->fetchColumn();
        }

        return [
            'total_generics' => $totalGenerics,
            'total_brands' => $totalBrands,
            'total_dosage_forms' => $totalDosageForms,
            'total_classes' => $totalClasses,
            'total_indications' => $totalIndications,
            'total_country_packs' => count($tables),
        ];
    }

    // -------------------------------------------------------------------------
    // GENERICS CRUD
    // -------------------------------------------------------------------------

    /**
     * Search generics by name, us name, or WHO ATC code.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchGenerics(string $query, int $limit = 40): array
    {
        $pdo = $this->getPdo();
        $query = trim($query);

        if ($query === '') {
            $stmt = $pdo->prepare("
                SELECT generic_id, generic_name, us_generic_name, who_atc_class, pregnancy_category, is_antibiotic, is_high_alert_medicine
                FROM drug_generic
                ORDER BY generic_name ASC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $term = '%' . $query . '%';
        $stmt = $pdo->prepare("
            SELECT generic_id, generic_name, us_generic_name, who_atc_class, pregnancy_category, is_antibiotic, is_high_alert_medicine
            FROM drug_generic
            WHERE generic_name LIKE :t1 OR us_generic_name LIKE :t2 OR who_atc_class LIKE :t3
            ORDER BY 
                CASE WHEN generic_name LIKE :starts THEN 1 ELSE 2 END,
                generic_name ASC
            LIMIT :lim
        ");
        $stmt->bindValue(':t1', $term);
        $stmt->bindValue(':t2', $term);
        $stmt->bindValue(':t3', $term);
        $stmt->bindValue(':starts', $query . '%');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get complete generic record.
     *
     * @return array<string, mixed>|null
     */
    public function getGeneric(int $id): ?array
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT * FROM drug_generic WHERE generic_id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $idStr = (string)$id;
        $classesStmt = $pdo->query("SELECT class_id, class_name, generic_ids FROM drug_therapeutic_class WHERE generic_ids != ''");
        $linkedClasses = [];
        while ($c = $classesStmt->fetch(PDO::FETCH_ASSOC)) {
            $parts = array_map('trim', explode(',', (string)($c['generic_ids'] ?? '')));
            if (in_array($idStr, $parts, true)) {
                $linkedClasses[] = ['class_id' => (int)$c['class_id'], 'class_name' => $c['class_name']];
            }
        }
        $row['linked_classes'] = $linkedClasses;

        $indsStmt = $pdo->query("SELECT indication_id, indication_name, generic_ids FROM drug_indication WHERE generic_ids != ''");
        $linkedInds = [];
        while ($ind = $indsStmt->fetch(PDO::FETCH_ASSOC)) {
            $parts = array_map('trim', explode(',', (string)($ind['generic_ids'] ?? '')));
            if (in_array($idStr, $parts, true)) {
                $linkedInds[] = ['indication_id' => (int)$ind['indication_id'], 'indication_name' => $ind['indication_name']];
            }
        }
        $row['linked_indications'] = $linkedInds;

        return $row;
    }

    /**
     * Add new generic record with collision-safe ID.
     *
     * @param array<string, mixed> $data
     */
    public function insertGeneric(array $data): int
    {
        $pdo = $this->getPdo();
        $pdo->beginTransaction();

        try {
            $maxId = (int)$pdo->query("SELECT COALESCE(MAX(generic_id), 0) FROM drug_generic")->fetchColumn();
            $newId = $maxId + 1;

            $allowedColumns = [
                'generic_name', 'us_generic_name', 'who_atc_class',
                'is_antibiotic', 'is_high_alert_medicine', 'is_safe_in_pregnancy', 'is_safe_in_lactation',
                'require_renal_adjustments', 'is_safe_in_hepatic_impairment', 'is_safe_in_paediatric', 'requires_tapering',
                'immediate_warning', 'precaution', 'indication', 'contra_indication', 'side_effect',
                'pregnancy_category', 'pregnancy_modern_category', 'pregnancy_category_and_lactation_note', 'pregnancy_trimester_safety',
                'mode_of_action_summary', 'mode_of_action_flow', 'interaction',
                'adult_dose', 'child_dose', 'paediatric_calc_parameter', 'renal_dose', 'administration',
                'overdose_effect', 'overdose_treatment', 'storage', 'counselling_pearl', 'pubmed_query_base'
            ];

            $cols = ['generic_id'];
            $vals = [':generic_id'];
            $params = [':generic_id' => $newId];

            foreach ($allowedColumns as $col) {
                if (array_key_exists($col, $data)) {
                    $cols[] = $col;
                    $vals[] = ':' . $col;
                    $params[':' . $col] = $data[$col];
                }
            }

            $sql = "INSERT INTO drug_generic (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $pdo->commit();
            return $newId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Update specific fields of a generic without mutating untouched sections.
     *
     * @param array<string, mixed> $fields
     */
    public function updateGenericSection(int $id, array $fields): void
    {
        $pdo = $this->getPdo();
        $pdo->beginTransaction();

        try {
            $allowedColumns = [
                'generic_name', 'us_generic_name', 'who_atc_class',
                'is_antibiotic', 'is_high_alert_medicine', 'is_safe_in_pregnancy', 'is_safe_in_lactation',
                'require_renal_adjustments', 'is_safe_in_hepatic_impairment', 'is_safe_in_paediatric', 'requires_tapering',
                'immediate_warning', 'precaution', 'indication', 'contra_indication', 'side_effect',
                'pregnancy_category', 'pregnancy_modern_category', 'pregnancy_category_and_lactation_note', 'pregnancy_trimester_safety',
                'mode_of_action_summary', 'mode_of_action_flow', 'interaction',
                'adult_dose', 'child_dose', 'paediatric_calc_parameter', 'renal_dose', 'administration',
                'overdose_effect', 'overdose_treatment', 'storage', 'counselling_pearl', 'pubmed_query_base'
            ];

            $setClauses = [];
            $params = [':id' => $id];

            foreach ($allowedColumns as $col) {
                if (array_key_exists($col, $fields)) {
                    $setClauses[] = "{$col} = :{$col}";
                    $params[':' . $col] = $fields[$col];
                }
            }

            if (empty($setClauses)) {
                $pdo->rollBack();
                return;
            }

            $sql = "UPDATE drug_generic SET " . implode(', ', $setClauses) . " WHERE generic_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // THERAPEUTIC CLASSIFICATIONS
    // -------------------------------------------------------------------------

    public function searchClasses(string $query, int $limit = 40): array
    {
        $pdo = $this->getPdo();
        $query = trim($query);

        if ($query === '') {
            $stmt = $pdo->prepare("SELECT class_id, class_name, category_name, subcategory_name, generic_ids FROM drug_therapeutic_class ORDER BY class_name ASC LIMIT :lim");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $term = '%' . $query . '%';
        $stmt = $pdo->prepare("
            SELECT class_id, class_name, category_name, subcategory_name, generic_ids 
            FROM drug_therapeutic_class
            WHERE class_name LIKE :t1 OR category_name LIKE :t2 OR subcategory_name LIKE :t3
            ORDER BY class_name ASC
            LIMIT :lim
        ");
        $stmt->bindValue(':t1', $term);
        $stmt->bindValue(':t2', $term);
        $stmt->bindValue(':t3', $term);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClass(int $id): ?array
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT * FROM drug_therapeutic_class WHERE class_id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['linked_generics'] = $this->resolveGenericNames((string)($row['generic_ids'] ?? ''));
        return $row;
    }

    public function insertClass(array $data): int
    {
        $pdo = $this->getPdo();
        $pdo->beginTransaction();

        try {
            $maxId = (int)$pdo->query("SELECT COALESCE(MAX(class_id), 0) FROM drug_therapeutic_class")->fetchColumn();
            $newId = $maxId + 1;

            $stmt = $pdo->prepare("
                INSERT INTO drug_therapeutic_class (class_id, class_name, category_name, subcategory_name, generic_ids)
                VALUES (:class_id, :class_name, :category_name, :subcategory_name, :generic_ids)
            ");
            $stmt->execute([
                ':class_id' => $newId,
                ':class_name' => trim((string)($data['class_name'] ?? '')),
                ':category_name' => trim((string)($data['category_name'] ?? '')),
                ':subcategory_name' => trim((string)($data['subcategory_name'] ?? '')),
                ':generic_ids' => trim((string)($data['generic_ids'] ?? '')),
            ]);

            $pdo->commit();
            return $newId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function updateClass(int $id, array $data): void
    {
        $pdo = $this->getPdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                UPDATE drug_therapeutic_class
                SET class_name = :class_name,
                    category_name = :category_name,
                    subcategory_name = :subcategory_name,
                    generic_ids = :generic_ids
                WHERE class_id = :id
            ");
            $stmt->execute([
                ':id' => $id,
                ':class_name' => trim((string)($data['class_name'] ?? '')),
                ':category_name' => trim((string)($data['category_name'] ?? '')),
                ':subcategory_name' => trim((string)($data['subcategory_name'] ?? '')),
                ':generic_ids' => trim((string)($data['generic_ids'] ?? '')),
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // INDICATIONS
    // -------------------------------------------------------------------------

    public function searchIndications(string $query, int $limit = 40): array
    {
        $pdo = $this->getPdo();
        $query = trim($query);

        if ($query === '') {
            $stmt = $pdo->prepare("SELECT indication_id, indication_name, generic_ids FROM drug_indication ORDER BY indication_name ASC LIMIT :lim");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $term = '%' . $query . '%';
        $stmt = $pdo->prepare("
            SELECT indication_id, indication_name, generic_ids 
            FROM drug_indication
            WHERE indication_name LIKE :t1
            ORDER BY indication_name ASC
            LIMIT :lim
        ");
        $stmt->bindValue(':t1', $term);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getIndication(int $id): ?array
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT * FROM drug_indication WHERE indication_id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['linked_generics'] = $this->resolveGenericNames((string)($row['generic_ids'] ?? ''));
        return $row;
    }

    public function insertIndication(array $data): int
    {
        $pdo = $this->getPdo();
        $pdo->beginTransaction();

        try {
            $maxId = (int)$pdo->query("SELECT COALESCE(MAX(indication_id), 0) FROM drug_indication")->fetchColumn();
            $newId = $maxId + 1;

            $stmt = $pdo->prepare("
                INSERT INTO drug_indication (indication_id, indication_name, generic_ids)
                VALUES (:indication_id, :indication_name, :generic_ids)
            ");
            $stmt->execute([
                ':indication_id' => $newId,
                ':indication_name' => trim((string)($data['indication_name'] ?? '')),
                ':generic_ids' => trim((string)($data['generic_ids'] ?? '')),
            ]);

            $pdo->commit();
            return $newId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function updateIndication(int $id, array $data): void
    {
        $pdo = $this->getPdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                UPDATE drug_indication
                SET indication_name = :indication_name,
                    generic_ids = :generic_ids
                WHERE indication_id = :id
            ");
            $stmt->execute([
                ':id' => $id,
                ':indication_name' => trim((string)($data['indication_name'] ?? '')),
                ':generic_ids' => trim((string)($data['generic_ids'] ?? '')),
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function linkGenericToClass(int $classId, int $genericId): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT generic_ids FROM drug_therapeutic_class WHERE class_id = :id");
        $stmt->execute([':id' => $classId]);
        $raw = (string)$stmt->fetchColumn();
        $parts = array_filter(array_map('trim', explode(',', $raw)), 'strlen');
        $genStr = (string)$genericId;
        if (!in_array($genStr, $parts, true)) {
            $parts[] = $genStr;
            $newRaw = implode(',', $parts);
            $upd = $pdo->prepare("UPDATE drug_therapeutic_class SET generic_ids = :g WHERE class_id = :id");
            $upd->execute([':g' => $newRaw, ':id' => $classId]);
        }
    }

    public function unlinkGenericFromClass(int $classId, int $genericId): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT generic_ids FROM drug_therapeutic_class WHERE class_id = :id");
        $stmt->execute([':id' => $classId]);
        $raw = (string)$stmt->fetchColumn();
        $parts = array_filter(array_map('trim', explode(',', $raw)), 'strlen');
        $genStr = (string)$genericId;
        $filtered = array_values(array_filter($parts, fn($id) => $id !== $genStr));
        $newRaw = implode(',', $filtered);
        $upd = $pdo->prepare("UPDATE drug_therapeutic_class SET generic_ids = :g WHERE class_id = :id");
        $upd->execute([':g' => $newRaw, ':id' => $classId]);
    }

    public function linkGenericToIndication(int $indicationId, int $genericId): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT generic_ids FROM drug_indication WHERE indication_id = :id");
        $stmt->execute([':id' => $indicationId]);
        $raw = (string)$stmt->fetchColumn();
        $parts = array_filter(array_map('trim', explode(',', $raw)), 'strlen');
        $genStr = (string)$genericId;
        if (!in_array($genStr, $parts, true)) {
            $parts[] = $genStr;
            $newRaw = implode(',', $parts);
            $upd = $pdo->prepare("UPDATE drug_indication SET generic_ids = :g WHERE indication_id = :id");
            $upd->execute([':g' => $newRaw, ':id' => $indicationId]);
        }
    }

    public function unlinkGenericFromIndication(int $indicationId, int $genericId): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT generic_ids FROM drug_indication WHERE indication_id = :id");
        $stmt->execute([':id' => $indicationId]);
        $raw = (string)$stmt->fetchColumn();
        $parts = array_filter(array_map('trim', explode(',', $raw)), 'strlen');
        $genStr = (string)$genericId;
        $filtered = array_values(array_filter($parts, fn($id) => $id !== $genStr));
        $newRaw = implode(',', $filtered);
        $upd = $pdo->prepare("UPDATE drug_indication SET generic_ids = :g WHERE indication_id = :id");
        $upd->execute([':g' => $newRaw, ':id' => $indicationId]);
    }

    /**
     * Resolves generic names for comma-separated generic IDs.
     *
     * @return array<int, array{generic_id: int, generic_name: string}>
     */
    private function resolveGenericNames(string $rawIds): array
    {
        if (trim($rawIds) === '') {
            return [];
        }

        $ids = array_filter(array_map('trim', explode(',', $rawIds)), 'strlen');
        if (empty($ids)) {
            return [];
        }

        $pdo = $this->getPdo();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT generic_id, generic_name FROM drug_generic WHERE generic_id IN ({$placeholders}) ORDER BY generic_name ASC");
        $stmt->execute(array_values($ids));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -------------------------------------------------------------------------
    // COUNTRY FORMULARIES
    // -------------------------------------------------------------------------

    /**
     * Discovers all country brand and manufacturer tables.
     *
     * @return array<int, array{code: string, brand_table: string, mfg_table: string}>
     */
    public function listCountryTables(): array
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'drug_brand_%' ORDER BY name ASC");
        $tables = [];

        while ($tbl = $stmt->fetchColumn()) {
            $code = strtoupper(substr($tbl, 11)); // 'drug_brand_bd' -> 'BD'
            if (preg_match('/^[A-Z]{2}$/', $code)) {
                $tables[] = [
                    'code' => $code,
                    'brand_table' => $tbl,
                    'mfg_table' => 'drug_manufacturer_' . strtolower($code),
                ];
            }
        }

        return $tables;
    }

    /**
     * Get details and metadata for all discovered country packs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listCountryPacks(): array
    {
        $tables = $this->listCountryTables();
        $pdo = $this->getPdo();
        $packs = [];

        foreach ($tables as $t) {
            $code = $t['code'];
            $brandCount = (int)$pdo->query("SELECT COUNT(*) FROM \"{$t['brand_table']}\"")->fetchColumn();
            
            $hasMfg = (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='{$t['mfg_table']}'")->fetchColumn();
            $mfgCount = $hasMfg ? (int)$pdo->query("SELECT COUNT(*) FROM \"{$t['mfg_table']}\"")->fetchColumn() : 0;

            $manifestFile = $this->manifestsDir . DIRECTORY_SEPARATOR . strtolower($code) . '.manifest.json';
            $manifest = [];
            if (file_exists($manifestFile)) {
                $manifest = json_decode((string)file_get_contents($manifestFile), true) ?: [];
            }

            $packs[] = [
                'code' => $code,
                'name' => $manifest['name'] ?? ($code === 'BD' ? 'Bangladesh Commercial Formulary' : "Country Pack {$code}"),
                'version' => $manifest['version'] ?? '1.0.0',
                'author' => $manifest['author'] ?? 'Alif Farzan Zim',
                'last_updated' => $manifest['last_updated'] ?? date('Y-m-d'),
                'brand_count' => $brandCount,
                'manufacturer_count' => $mfgCount,
                'license' => $manifest['license'] ?? 'Open Medical Reference',
            ];
        }

        return $packs;
    }

    /**
     * Add a new country formulary pack with strict schema.
     */
    public function addCountryPack(string $countryCode, string $countryName, array $manifestData = []): void
    {
        $code = DrugCatalogValidator::validateCountryCode($countryCode);
        $lower = strtolower($code);
        $pdo = $this->getPdo();

        $pdo->beginTransaction();
        try {
            $mfgTable = "drug_manufacturer_{$lower}";
            $brandTable = "drug_brand_{$lower}";

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS \"{$mfgTable}\" (
                    id TEXT PRIMARY KEY,
                    manufacturer_name TEXT,
                    manufacturer_name_short TEXT
                );
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS \"{$brandTable}\" (
                    brand_id TEXT PRIMARY KEY,
                    generic_id TEXT,
                    manufacturer_id TEXT,
                    brand_name TEXT,
                    form TEXT,
                    form_variant TEXT,
                    strength TEXT,
                    price TEXT,
                    packsize TEXT
                );
            ");

            $manifestPath = $this->manifestsDir . DIRECTORY_SEPARATOR . "{$lower}.manifest.json";
            $manifest = [
                'scope' => $lower,
                'name' => $countryName ?: "{$code} Commercial Drug Formulary",
                'version' => $manifestData['version'] ?? '1.0.0',
                'author' => $manifestData['author'] ?? 'Alif Farzan Zim',
                'contributors' => $manifestData['contributors'] ?? ['Alif Farzan Zim'],
                'license' => $manifestData['license'] ?? 'Proprietary Clinical / Open Medical Reference',
                'attribution' => $manifestData['attribution'] ?? "National drug catalog for {$code}",
                'release_notes' => $manifestData['release_notes'] ?? "Initial commercial brand and manufacturer registry for {$code}.",
                'release_date' => date('Y-m-d'),
                'last_updated' => date('Y-m-d'),
            ];
            file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function searchBrands(string $countryCode, string $query, int $limit = 40): array
    {
        $code = DrugCatalogValidator::validateCountryCode($countryCode);
        $lower = strtolower($code);
        $brandTable = "drug_brand_{$lower}";
        $mfgTable = "drug_manufacturer_{$lower}";
        $pdo = $this->getPdo();
        $query = trim($query);

        if ($query === '') {
            $sql = "
                SELECT b.brand_id, b.generic_id, b.manufacturer_id, b.brand_name, b.form, b.form_variant, b.strength, b.price, b.packsize,
                       g.generic_name, m.manufacturer_name, m.manufacturer_name_short
                FROM \"{$brandTable}\" b
                LEFT JOIN drug_generic g ON CAST(b.generic_id AS INTEGER) = g.generic_id
                LEFT JOIN \"{$mfgTable}\" m ON b.manufacturer_id = m.id
                ORDER BY b.brand_name ASC
                LIMIT :lim
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $term = '%' . $query . '%';
        $sql = "
            SELECT b.brand_id, b.generic_id, b.manufacturer_id, b.brand_name, b.form, b.form_variant, b.strength, b.price, b.packsize,
                   g.generic_name, m.manufacturer_name, m.manufacturer_name_short
            FROM \"{$brandTable}\" b
            LEFT JOIN drug_generic g ON CAST(b.generic_id AS INTEGER) = g.generic_id
            LEFT JOIN \"{$mfgTable}\" m ON b.manufacturer_id = m.id
            WHERE b.brand_name LIKE :t1 OR g.generic_name LIKE :t2 OR m.manufacturer_name LIKE :t3
            ORDER BY 
                CASE WHEN b.brand_name LIKE :starts THEN 1 ELSE 2 END,
                b.brand_name ASC
            LIMIT :lim
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':t1', $term);
        $stmt->bindValue(':t2', $term);
        $stmt->bindValue(':t3', $term);
        $stmt->bindValue(':starts', $query . '%');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBrand(string $countryCode, string $brandId): ?array
    {
        $code = DrugCatalogValidator::validateCountryCode($countryCode);
        $lower = strtolower($code);
        $brandTable = "drug_brand_{$lower}";
        $mfgTable = "drug_manufacturer_{$lower}";
        $pdo = $this->getPdo();

        $sql = "
            SELECT b.*, g.generic_name, g.pregnancy_category, g.who_atc_class, m.manufacturer_name, m.manufacturer_name_short
            FROM \"{$brandTable}\" b
            LEFT JOIN drug_generic g ON CAST(b.generic_id AS INTEGER) = g.generic_id
            LEFT JOIN \"{$mfgTable}\" m ON b.manufacturer_id = m.id
            WHERE b.brand_id = :id
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $brandId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insertBrand(string $countryCode, array $data): string
    {
        $code = DrugCatalogValidator::validateCountryCode($countryCode);
        $lower = strtolower($code);
        $brandTable = "drug_brand_{$lower}";
        $pdo = $this->getPdo();

        // Enforce generic exists in drug_generic
        $genId = (int)$data['generic_id'];
        $genExists = (bool)$pdo->query("SELECT 1 FROM drug_generic WHERE generic_id = {$genId}")->fetchColumn();
        if (!$genExists) {
            throw new InvalidArgumentException("Selected generic ID ({$genId}) does not exist in the core drug catalog.");
        }

        $pdo->beginTransaction();
        try {
            $maxId = (int)$pdo->query("SELECT COALESCE(MAX(CAST(brand_id AS INTEGER)), 0) FROM \"{$brandTable}\"")->fetchColumn();
            $newId = (string)($maxId + 1);

            $stmt = $pdo->prepare("
                INSERT INTO \"{$brandTable}\" (brand_id, generic_id, manufacturer_id, brand_name, form, form_variant, strength, price, packsize)
                VALUES (:brand_id, :generic_id, :manufacturer_id, :brand_name, :form, :form_variant, :strength, :price, :packsize)
            ");
            $stmt->execute([
                ':brand_id' => $newId,
                ':generic_id' => (string)$genId,
                ':manufacturer_id' => trim((string)($data['manufacturer_id'] ?? '')),
                ':brand_name' => trim((string)($data['brand_name'] ?? '')),
                ':form' => trim((string)($data['form'] ?? '')),
                ':form_variant' => trim((string)($data['form_variant'] ?? '')),
                ':strength' => trim((string)($data['strength'] ?? '')),
                ':price' => trim((string)($data['price'] ?? '')),
                ':packsize' => trim((string)($data['packsize'] ?? '')),
            ]);

            $pdo->commit();
            return $newId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function updateBrandSection(string $countryCode, string $brandId, array $fields): void
    {
        $code = DrugCatalogValidator::validateCountryCode($countryCode);
        $lower = strtolower($code);
        $brandTable = "drug_brand_{$lower}";
        $pdo = $this->getPdo();

        if (array_key_exists('generic_id', $fields)) {
            $genId = (int)$fields['generic_id'];
            $genExists = (bool)$pdo->query("SELECT 1 FROM drug_generic WHERE generic_id = {$genId}")->fetchColumn();
            if (!$genExists) {
                throw new InvalidArgumentException("Selected generic ID ({$genId}) does not exist in the core drug catalog.");
            }
        }

        $allowedColumns = ['generic_id', 'manufacturer_id', 'brand_name', 'form', 'form_variant', 'strength', 'price', 'packsize'];
        $setClauses = [];
        $params = [':id' => $brandId];

        foreach ($allowedColumns as $col) {
            if (array_key_exists($col, $fields)) {
                $setClauses[] = "{$col} = :{$col}";
                $params[':' . $col] = trim((string)$fields[$col]);
            }
        }

        if (empty($setClauses)) {
            return;
        }

        $pdo->beginTransaction();
        try {
            $sql = "UPDATE \"{$brandTable}\" SET " . implode(', ', $setClauses) . " WHERE brand_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function searchManufacturers(string $countryCode, string $query, int $limit = 40): array
    {
        $code = DrugCatalogValidator::validateCountryCode($countryCode);
        $lower = strtolower($code);
        $mfgTable = "drug_manufacturer_{$lower}";
        $pdo = $this->getPdo();
        $query = trim($query);

        if ($query === '') {
            $stmt = $pdo->prepare("SELECT id, manufacturer_name, manufacturer_name_short FROM \"{$mfgTable}\" ORDER BY manufacturer_name ASC LIMIT :lim");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $term = '%' . $query . '%';
        $stmt = $pdo->prepare("
            SELECT id, manufacturer_name, manufacturer_name_short
            FROM \"{$mfgTable}\"
            WHERE manufacturer_name LIKE :t1 OR manufacturer_name_short LIKE :t2
            ORDER BY manufacturer_name ASC
            LIMIT :lim
        ");
        $stmt->bindValue(':t1', $term);
        $stmt->bindValue(':t2', $term);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertManufacturer(string $countryCode, array $data): string
    {
        $code = DrugCatalogValidator::validateCountryCode($countryCode);
        $lower = strtolower($code);
        $mfgTable = "drug_manufacturer_{$lower}";
        $pdo = $this->getPdo();

        $pdo->beginTransaction();
        try {
            $maxId = (int)$pdo->query("SELECT COALESCE(MAX(CAST(id AS INTEGER)), 0) FROM \"{$mfgTable}\"")->fetchColumn();
            $newId = (string)($maxId + 1);

            $stmt = $pdo->prepare("
                INSERT INTO \"{$mfgTable}\" (id, manufacturer_name, manufacturer_name_short)
                VALUES (:id, :manufacturer_name, :manufacturer_name_short)
            ");
            $stmt->execute([
                ':id' => $newId,
                ':manufacturer_name' => trim((string)($data['manufacturer_name'] ?? '')),
                ':manufacturer_name_short' => trim((string)($data['manufacturer_name_short'] ?? '')),
            ]);

            $pdo->commit();
            return $newId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function getManufacturer(string $countryCode, int $id): ?array
    {
        $code = DrugCatalogValidator::validateCountryCode($countryCode);
        $lower = strtolower($code);
        $mfgTable = "drug_manufacturer_{$lower}";
        $brandTable = "drug_brand_{$lower}";
        $pdo = $this->getPdo();

        $stmt = $pdo->prepare("SELECT * FROM \"{$mfgTable}\" WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM \"{$brandTable}\" WHERE manufacturer_id = :id");
        $cntStmt->execute([':id' => $id]);
        $row['total_brands'] = (int)$cntStmt->fetchColumn();

        return $row;
    }

    public function updateManufacturer(string $countryCode, int $id, array $fields): void
    {
        $code = DrugCatalogValidator::validateCountryCode($countryCode);
        $lower = strtolower($code);
        $mfgTable = "drug_manufacturer_{$lower}";
        $pdo = $this->getPdo();

        $stmt = $pdo->prepare("UPDATE \"{$mfgTable}\" SET manufacturer_name = :name WHERE id = :id");
        $stmt->execute([
            ':name' => trim((string)($fields['manufacturer_name'] ?? '')),
            ':id' => $id,
        ]);
    }

    // -------------------------------------------------------------------------
    // DOSAGE FORMS CRUD
    // -------------------------------------------------------------------------

    public function searchDosageForms(string $query, int $limit = 40): array
    {
        $pdo = $this->getPdo();
        $query = trim($query);

        if ($query === '') {
            $stmt = $pdo->prepare("SELECT form_id, form, form_variant, icon, prefix_short, prefix_full, form_order FROM drug_dosage_form ORDER BY form_order ASC, form ASC LIMIT :lim");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $term = '%' . $query . '%';
        $stmt = $pdo->prepare("
            SELECT form_id, form, form_variant, icon, prefix_short, prefix_full, form_order
            FROM drug_dosage_form
            WHERE form LIKE :t1 OR form_variant LIKE :t2 OR prefix_short LIKE :t3
            ORDER BY form_order ASC, form ASC
            LIMIT :lim
        ");
        $stmt->bindValue(':t1', $term);
        $stmt->bindValue(':t2', $term);
        $stmt->bindValue(':t3', $term);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDosageForm(int $id): ?array
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT * FROM drug_dosage_form WHERE form_id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['form_name'] = $row['form'];
        $row['short_prefix'] = $row['prefix_short'];
        $row['full_prefix'] = $row['prefix_full'];
        $row['display_order'] = (int)($row['form_order'] ?? 0);
        $row['append_strength'] = (int)($row['add_strength_brand'] ?? 1);
        return $row;
    }

    public function insertDosageForm(array $data): int
    {
        $pdo = $this->getPdo();
        $formName = trim((string)($data['form'] ?? $data['form_name'] ?? ''));

        $exists = (bool)$pdo->query("SELECT 1 FROM drug_dosage_form WHERE LOWER(form) = LOWER(" . $pdo->quote($formName) . ")")->fetchColumn();
        if ($exists) {
            throw new InvalidArgumentException("Dosage form '{$formName}' already exists.");
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO drug_dosage_form (
                    form, form_variant, icon, prefix_short, prefix_full, suffix, suffix_generic,
                    add_strength_brand, add_strength_generic, prescribe_short, prescribe_full, generic_subtitle, form_order
                ) VALUES (
                    :form, :form_variant, :icon, :prefix_short, :prefix_full, :suffix, :suffix_generic,
                    :add_strength_brand, :add_strength_generic, :prescribe_short, :prescribe_full, :generic_subtitle, :form_order
                )
            ");
            $stmt->execute([
                ':form' => $formName,
                ':form_variant' => trim((string)($data['form_variant'] ?? '')),
                ':icon' => trim((string)($data['icon'] ?? 'fallback.svg')),
                ':prefix_short' => trim((string)($data['prefix_short'] ?? $data['short_prefix'] ?? '')),
                ':prefix_full' => trim((string)($data['prefix_full'] ?? $data['full_prefix'] ?? '')),
                ':suffix' => trim((string)($data['suffix'] ?? '')),
                ':suffix_generic' => trim((string)($data['suffix_generic'] ?? '')),
                ':add_strength_brand' => !empty($data['add_strength_brand'] ?? $data['append_strength'] ?? 1) ? 1 : 0,
                ':add_strength_generic' => !empty($data['add_strength_generic'] ?? 1) ? 1 : 0,
                ':prescribe_short' => trim((string)($data['prescribe_short'] ?? '')),
                ':prescribe_full' => trim((string)($data['prescribe_full'] ?? '')),
                ':generic_subtitle' => trim((string)($data['generic_subtitle'] ?? '')),
                ':form_order' => (int)($data['form_order'] ?? $data['display_order'] ?? 999),
            ]);

            $newId = (int)$pdo->lastInsertId();
            $pdo->commit();
            return $newId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function updateDosageFormSection(int $id, array $fields): void
    {
        $pdo = $this->getPdo();

        if (isset($fields['form_name']) && !isset($fields['form'])) {
            $fields['form'] = $fields['form_name'];
        }
        if (isset($fields['short_prefix']) && !isset($fields['prefix_short'])) {
            $fields['prefix_short'] = $fields['short_prefix'];
        }
        if (isset($fields['full_prefix']) && !isset($fields['prefix_full'])) {
            $fields['prefix_full'] = $fields['full_prefix'];
        }
        if (isset($fields['display_order']) && !isset($fields['form_order'])) {
            $fields['form_order'] = $fields['display_order'];
        }
        if (isset($fields['append_strength']) && !isset($fields['add_strength_brand'])) {
            $fields['add_strength_brand'] = $fields['append_strength'];
        }

        if (array_key_exists('form', $fields)) {
            $formName = trim((string)$fields['form']);
            $dupStmt = $pdo->prepare("SELECT 1 FROM drug_dosage_form WHERE LOWER(form) = LOWER(:f) AND form_id != :id");
            $dupStmt->execute([':f' => $formName, ':id' => $id]);
            if ($dupStmt->fetchColumn()) {
                throw new InvalidArgumentException("Dosage form '{$formName}' is already in use by another record.");
            }
        }

        $allowedColumns = [
            'form', 'form_variant', 'icon', 'prefix_short', 'prefix_full', 'suffix', 'suffix_generic',
            'add_strength_brand', 'add_strength_generic', 'prescribe_short', 'prescribe_full', 'generic_subtitle', 'form_order'
        ];

        $setClauses = [];
        $params = [':id' => $id];

        foreach ($allowedColumns as $col) {
            if (array_key_exists($col, $fields)) {
                $setClauses[] = "{$col} = :{$col}";
                $val = $fields[$col];
                if (in_array($col, ['add_strength_brand', 'add_strength_generic'], true)) {
                    $val = !empty($val) ? 1 : 0;
                } elseif ($col === 'form_order') {
                    $val = (int)$val;
                } else {
                    $val = trim((string)$val);
                }
                $params[':' . $col] = $val;
            }
        }

        if (empty($setClauses)) {
            return;
        }

        $pdo->beginTransaction();
        try {
            $sql = "UPDATE drug_dosage_form SET " . implode(', ', $setClauses) . " WHERE form_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function deleteGeneric(int $id): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("DELETE FROM drug_generic WHERE generic_id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function deleteClass(int $id): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("DELETE FROM drug_therapeutic_class WHERE class_id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function deleteIndication(int $id): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("DELETE FROM drug_indication WHERE indication_id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function deleteBrand(string $country, string $brandId): void
    {
        $country = strtolower(trim($country));
        $table = "drug_brand_{$country}";
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE brand_id = :id");
        $stmt->execute([':id' => $brandId]);
    }

    public function deleteManufacturer(string $country, int $mfgId): void
    {
        $country = strtolower(trim($country));
        $table = "drug_manufacturer_{$country}";
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE manufacturer_id = :id");
        $stmt->execute([':id' => $mfgId]);
    }

    public function deleteDosageForm(int $id): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("DELETE FROM drug_dosage_form WHERE form_id = :id");
        $stmt->execute([':id' => $id]);
    }
}

