<?php
/**
 * ZimRx Standalone Database Builder
 * Compiles master reference databases directly from plain-text SQL seeds using native PDO SQLite.
 * Zero external dependencies: requires NO sqlite3 CLI, NO Python, and NO binary blobs.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('memory_limit', '512M');
set_time_limit(600);

$baseDir = dirname(__DIR__, 2); // ZimRx/application
$seedsDir = $baseDir . DIRECTORY_SEPARATOR . 'systemdata' . DIRECTORY_SEPARATOR . 'seeds';
$dbDir = $baseDir . DIRECTORY_SEPARATOR . 'systemdata' . DIRECTORY_SEPARATOR . 'database';

if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}

$databases = [
    'zimrx_drugs' => [
        'sql' => $seedsDir . DIRECTORY_SEPARATOR . 'zimrx_drugs.sql',
        'db'  => $dbDir . DIRECTORY_SEPARATOR . 'zimrx_drugs.db',
        'test_table' => 'drug_generic',
        'min_rows' => 2000,
    ],
    'zimrx_static' => [
        'sql' => $seedsDir . DIRECTORY_SEPARATOR . 'zimrx_static.sql',
        'db'  => $dbDir . DIRECTORY_SEPARATOR . 'zimrx_static.db',
        'test_table' => 'zimrx_static_dx',
        'min_rows' => 1000,
    ],
];

function buildDbLog(string $msg, bool $isError = false): void
{
    if (php_sapi_name() === 'cli') {
        if ($isError && defined('STDERR')) {
            fwrite(STDERR, $msg . "\n");
        } else {
            echo $msg . "\n";
        }
    } else {
        error_log('[ZimRx Database Builder] ' . $msg);
    }
}

/**
 * Execute statements from a plain-text SQL file onto an open PDO instance.
 */
function executeSqlStream(PDO $pdo, string $sqlFile, ?int &$stmtCount = 0): void
{
    $handle = fopen($sqlFile, 'rb');
    if (!$handle) {
        throw new RuntimeException("Cannot open {$sqlFile} for reading.");
    }

    $buffer = '';
    $inTransaction = false;

    while (($line = fgets($handle)) !== false) {
        $trimmed = trim($line);

        if ($buffer === '') {
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                if (str_ends_with($trimmed, '*/') || str_starts_with($trimmed, '--')) {
                    continue;
                }
            }
        }

        $buffer .= $line;

        if (str_ends_with($trimmed, ';')) {
            $statement = trim($buffer);
            $buffer = '';

            if ($statement === '') {
                continue;
            }

            $upper = strtoupper($statement);
            if (str_starts_with($upper, 'BEGIN')) {
                $inTransaction = true;
            } elseif (str_starts_with($upper, 'COMMIT')) {
                $inTransaction = false;
            }

            try {
                $pdo->exec($statement);
                $stmtCount++;
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'SQLITE_DBCONFIG_DEFENSIVE')) {
                    continue;
                }
                throw $e;
            }
        }
    }

    fclose($handle);

    if ($inTransaction) {
        $pdo->exec('COMMIT;');
    }
}

/**
 * Compiles the dynamic prescribe catalog (drug_prescribe_bd / drug_prescribe) and FTS5 search index.
 */
function compilePrescribeCatalog(PDO $pdo): void
{
    $hasBrandBd = (bool)$pdo->query(
        "SELECT 1 FROM sqlite_master WHERE type='table' AND name='drug_brand_bd'"
    )->fetchColumn();

    // 1. Load dosage form rules
    $rulesByStd = [];
    $rulesByForm = [];
    $hasFormTable = (bool)$pdo->query(
        "SELECT 1 FROM sqlite_master WHERE type IN ('table', 'view') AND name IN ('drug_dosage_form', 'drug_forms')"
    )->fetchColumn();

    if ($hasFormTable) {
        $formTable = $pdo->query("SELECT 1 FROM sqlite_master WHERE type IN ('table', 'view') AND name='drug_dosage_form'")->fetchColumn()
            ? 'drug_dosage_form' : 'drug_forms';
        $formStmt = $pdo->query("SELECT form, form_variant, suffix, suffix_generic, add_strength_brand, add_strength_generic, prescribe_full, prescribe_short, generic_subtitle FROM {$formTable}");
        while ($r = $formStmt->fetch(PDO::FETCH_ASSOC)) {
            $stdForm = trim((string)$r['form']);
            $formVar = trim((string)$r['form_variant']);
            $rule = [
                'std_form' => $stdForm,
                'suffix' => trim((string)($r['suffix'] ?? '')),
                'suffix_generic' => trim((string)($r['suffix_generic'] ?? '')),
                'add_strength_brand' => (bool)$r['add_strength_brand'],
                'add_strength_generic' => (bool)$r['add_strength_generic'],
                'pres_full' => trim((string)$r['prescribe_full']),
                'pres_short' => trim((string)$r['prescribe_short']),
                'pres_gen' => trim((string)$r['generic_subtitle']),
            ];
            $stdKey = strtolower($stdForm);
            $rulesByStd[$stdKey] = $rule;
            $rulesByForm[$stdKey] = $rule;
            if ($formVar !== '') {
                foreach (explode(',', $formVar) as $p) {
                    $pk = strtolower(trim($p));
                    if ($pk !== '') {
                        $rulesByForm[$pk] = $rule;
                    }
                }
            }
        }
    }

    // 2. Load drug therapeutic class map
    $drugClasses = [];
    $hasClassTable = (bool)$pdo->query(
        "SELECT 1 FROM sqlite_master WHERE type='table' AND name='drug_therapeutic_class'"
    )->fetchColumn();
    if ($hasClassTable) {
        $clsStmt = $pdo->query("SELECT class_name, generic_ids FROM drug_therapeutic_class ORDER BY CAST(class_id AS INTEGER), class_name");
        $classMap = [];
        while ($c = $clsStmt->fetch(PDO::FETCH_ASSOC)) {
            $cName = trim((string)$c['class_name']);
            foreach (explode(',', (string)$c['generic_ids']) as $gid) {
                $gid = trim($gid);
                if ($gid !== '') {
                    $classMap[$gid][] = $cName;
                }
            }
        }
        foreach ($classMap as $gid => $names) {
            $drugClasses[$gid] = implode(', ', array_unique($names));
        }
    }

    $formOrders = ['TAB.' => 1, 'CAP.' => 2, 'SYP.' => 3, 'SUSP.' => 4, 'NEB.' => 5, 'INJ.' => 6];

    $fallbackMfgShort = function (string $name): string {
        $name = trim($name);
        if ($name === '') return '';
        $name = preg_replace('/\([^)]*\)/', '', $name);
        $name = preg_replace('/\b(pharmaceuticals?|pharma|laboratories|labs?|limited|ltd|plc|company|co|bangladesh|bd|healthcare|specialized|specialities|industries|industry)\b\.?/i', '', $name);
        $name = preg_replace('/[,.]+/', ' ', $name);
        $name = trim((string)$name, " -");
        $parts = explode(' ', (string)$name);
        return $parts[0] ?? (string)$name;
    };

    $resolveFormOrder = function (string $presBrandShort) use ($formOrders): int {
        $upper = strtoupper(trim($presBrandShort));
        foreach ($formOrders as $prefix => $order) {
            if ($upper === $prefix || str_starts_with($upper, $prefix . ' ')) {
                return $order;
            }
        }
        return 999;
    };

    $prepareBrandName = function (string $brandName, string $strength): string {
        $brand = trim($brandName);
        if ($brand === '') return $brand;
        $brand = preg_replace('/\b(ER|XR|SR|CR|MR|DR|EC)(\d+(?:\.\d+)?)\b/i', '$1 $2', $brand);
        $brand = preg_replace('/(?<=\D)-(?=\d)/', ' ', (string)$brand);
        $brand = trim((string)preg_replace('/\s+/', ' ', (string)$brand));

        if (preg_match('/^(?P<prefix>.*?)(?P<sep>[\s-]+)(?P<dose>\d+(?:\.\d+)?(?:\s*(?:mg|mcg|g|ml|iu|units?|%|anti-xaiu|xaiu))?(?:\s*\/\s*\d+(?:\.\d+)?(?:\s*(?:mg|mcg|g|ml|iu|units?|%|anti-xaiu|xaiu))?)*)$/i', $brand, $m)) {
            $prefix = trim($m['prefix']);
            $dose = $m['dose'];
            if ($prefix !== '') {
                $hasUnit = preg_match('/(mg|mcg|g|ml|iu|units?|%|anti-xaiu|xaiu)/i', $dose);
                $hasSlash = str_contains($dose, '/');
                preg_match_all('/\d+(?:\.\d+)?/', $dose, $dm);
                preg_match_all('/\d+(?:\.\d+)?/', $strength, $sm);
                $dNums = array_map(fn($v) => str_contains($v, '.') ? rtrim(rtrim($v, '0'), '.') : $v, $dm[0] ?? []);
                $sNums = array_map(fn($v) => str_contains($v, '.') ? rtrim(rtrim($v, '0'), '.') : $v, $sm[0] ?? []);
                $overlaps = count(array_intersect($dNums, $sNums)) > 0;
                if ($hasUnit || $hasSlash || $overlaps) {
                    return $prefix;
                }
            }
        }
        return $brand;
    };

    $applyTemplate = function (
        string $template,
        array $rule,
        string $brandName,
        string $form,
        string $genericName,
        string $strength,
        bool $isBrand
    ) use ($prepareBrandName): string {
        $text = trim($template);
        $text = preg_replace('/\s*,\s*but\b.*$/i', '', $text);
        $text = preg_replace('/\s+but\s+dont\b.*$/i', '', (string)$text);

        $haystack = $brandName . ' ' . $form;
        $useOdt = (bool)preg_match('/(?<![A-Za-z0-9])(ODT|OD)(?![A-Za-z])/i', $haystack);
        $replacement = $useOdt ? '(ODT)' : '(Dispersible)';
        $genericReplacement = $useOdt ? 'ODT tablet' : 'dispersible tablet';
        $text = preg_replace('/\(Dispersible\)\s*\/\s*\(ODT\)/i', $replacement, (string)$text);
        $text = preg_replace('/dispersible\s+tablet\s*\/\s*ODT\s+tablet/i', $genericReplacement, (string)$text);

        if ($isBrand) {
            $suffix = trim((string)($rule['suffix'] ?? ''));
            if ($suffix !== '') {
                preg_match_all('/[A-Za-z0-9]+/', $suffix, $matches);
                $tokens = $matches[0] ?? [];
                if (in_array('ER', $tokens, true) && !in_array('XR', $tokens, true)) {
                    $tokens[] = 'XR';
                }
                if (in_array('XR', $tokens, true) && !in_array('ER', $tokens, true)) {
                    $tokens[] = 'ER';
                }
                foreach ($tokens as $tok) {
                    if (preg_match('/(?<![A-Za-z0-9])' . preg_quote($tok, '/') . '(?![A-Za-z])/i', $brandName)) {
                        $text = preg_replace('/\s*' . preg_quote($suffix, '/') . '/i', '', (string)$text);
                        $text = preg_replace('/\s*\(' . preg_quote($tok, '/') . '\)/i', '', (string)$text);
                    }
                }
            }
        }

        $brandPrepared = strtoupper($prepareBrandName($brandName, $strength));
        $genPrepared = trim($genericName);
        $strPrepared = trim($strength);

        if ($isBrand && !($rule['add_strength_brand'] ?? true)) {
            $strPrepared = '';
        }
        if (!$isBrand && !($rule['add_strength_generic'] ?? true)) {
            $strPrepared = '';
        }

        $namePrepared = $isBrand ? $brandPrepared : $genPrepared;

        $text = str_replace('[NAME]', $namePrepared, (string)$text);
        $text = str_replace('{NAME}', $namePrepared, (string)$text);
        $text = str_replace('[BRAND_NAME]', $namePrepared, (string)$text);
        $text = str_replace('{BRAND_NAME}', $namePrepared, (string)$text);
        $text = str_replace('[Generic_name]', $genPrepared, (string)$text);
        $text = str_replace('{Generic_name}', $genPrepared, (string)$text);
        $text = preg_replace('/\bstrength\b/i', $strPrepared, (string)$text);

        $text = preg_replace('/\s+/', ' ', (string)$text);
        $text = preg_replace('/\s+([,;)])/', '$1', (string)$text);
        $text = preg_replace('/([(])\s+/', '$1', (string)$text);
        $text = preg_replace('/\s*\/\s*/', '/', (string)$text);
        $text = trim((string)$text);

        if (!$isBrand) {
            $text = ucfirst($text);
        }
        return $text;
    };

    if ($hasBrandBd) {
        // Compile Bangladesh brand catalog: drug_prescribe_bd
        buildDbLog("[BUILD] Compiling drug_prescribe_bd from commercial catalog...");

        $mfgNames = [];
        $mfgShorts = [];
        $mfgOrder = [
            "680" => 1, "679" => 2, "331" => 3, "98" => 4, "523" => 5, "530" => 6,
            "221" => 7, "598" => 8, "12" => 9, "11" => 11, "52" => 12, "201" => 13,
            "693" => 14, "264" => 15, "529" => 16, "528" => 17, "512" => 18, "513" => 19,
            "311" => 20, "625" => 21, "626" => 22, "570" => 23, "493" => 24, "329" => 25,
            "515" => 26, "719" => 27, "687" => 28, "282" => 29, "582" => 30, "533" => 31,
            "354" => 32, "84" => 33, "667" => 34, "374" => 36, "479" => 37, "672" => 38,
            "587" => 39, "402" => 40, "29" => 41, "47" => 42, "557" => 43, "664" => 44,
            "211" => 45, "750" => 46, "740" => 47, "498" => 48,
        ];

        $hasMfgTable = (bool)$pdo->query(
            "SELECT 1 FROM sqlite_master WHERE type IN ('table', 'view') AND name IN ('drug_manufacturer_bd', 'drug_manufacturer')"
        )->fetchColumn();
        if ($hasMfgTable) {
            $mfgTable = $pdo->query("SELECT 1 FROM sqlite_master WHERE type IN ('table', 'view') AND name='drug_manufacturer_bd'")->fetchColumn()
                ? 'drug_manufacturer_bd' : 'drug_manufacturer';
            $mfgStmt = $pdo->query("SELECT id, manufacturer_name, manufacturer_name_short FROM {$mfgTable}");
            while ($m = $mfgStmt->fetch(PDO::FETCH_ASSOC)) {
                $mid = trim((string)$m['id']);
                $mfgNames[$mid] = trim((string)$m['manufacturer_name']);
                $mfgShorts[$mid] = trim((string)$m['manufacturer_name_short']);
            }
        }

        $pdo->exec("
            DROP TABLE IF EXISTS drug_prescribe_bd;
            CREATE TABLE drug_prescribe_bd (
                brand_id TEXT,
                generic_id TEXT,
                manufacturer_id TEXT,
                manufacturer_name TEXT,
                manufacturer_name_short TEXT,
                drug_class TEXT,
                brand_name TEXT,
                form TEXT,
                form_variant TEXT,
                generic_name TEXT,
                us_generic_name TEXT,
                who_atc_class TEXT,
                is_antibiotic TEXT,
                is_high_alert_medicine TEXT,
                is_safe_in_pregnancy TEXT,
                is_safe_in_lactation TEXT,
                require_renal_adjustments TEXT,
                is_safe_in_hepatic_impairment TEXT,
                is_safe_in_paediatric TEXT,
                requires_tapering TEXT,
                immediate_warning TEXT,
                pregnancy_category TEXT,
                strength TEXT,
                price TEXT,
                packsize TEXT,
                prescribe_brand_short TEXT,
                prescribe_brand_full TEXT,
                prescribe_generic_short TEXT,
                prescribe_generic_full TEXT,
                labelled_generic_short TEXT,
                labelled_generic_full TEXT,
                manufacturer_preference INTEGER,
                form_order INTEGER
            );
        ");

        $insSql = "INSERT INTO drug_prescribe_bd VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $insStmt = $pdo->prepare($insSql);

        $query = "
            SELECT 
                b.brand_id, b.generic_id, b.manufacturer_id, b.brand_name, b.form, b.form_variant, b.strength, b.price, b.packsize,
                g.generic_name, g.us_generic_name, g.who_atc_class, g.is_antibiotic, g.is_high_alert_medicine,
                g.is_safe_in_pregnancy, g.is_safe_in_lactation, g.require_renal_adjustments, g.is_safe_in_hepatic_impairment,
                g.is_safe_in_paediatric, g.requires_tapering, g.immediate_warning, g.pregnancy_category
            FROM drug_brand_bd b
            LEFT JOIN drug_generic g ON CAST(b.generic_id AS INTEGER) = g.generic_id
            ORDER BY CAST(b.brand_id AS INTEGER), b.brand_id
        ";
        $srcStmt = $pdo->query($query);
        $count = 0;
        $pdo->beginTransaction();

        while ($row = $srcStmt->fetch(PDO::FETCH_ASSOC)) {
            $brandId = trim((string)$row['brand_id']);
            $genId = trim((string)$row['generic_id']);
            $mfgId = trim((string)$row['manufacturer_id']);
            $brandName = trim((string)$row['brand_name']);
            $form = trim((string)$row['form']);
            $formVar = trim((string)$row['form_variant']);
            $strength = trim((string)$row['strength']);
            $genName = trim((string)$row['generic_name']);

            $mfgName = $mfgNames[$mfgId] ?? '';
            $mfgShort = $mfgShorts[$mfgId] ?? $fallbackMfgShort($mfgName);
            $drugCls = $drugClasses[$genId] ?? '';

            $ruleKey = strtolower($form);
            $varKey = strtolower($formVar);
            $rule = $rulesByStd[$ruleKey] ?? ($rulesByForm[$ruleKey] ?? ($rulesByForm[$varKey] ?? null));
            if (!$rule) {
                $rule = [
                    'std_form' => $form ?: $formVar,
                    'suffix' => '',
                    'suffix_generic' => strtolower($form ?: $formVar),
                    'add_strength_brand' => true,
                    'add_strength_generic' => true,
                    'pres_full' => '[NAME] strength',
                    'pres_short' => '[NAME] strength',
                    'pres_gen' => trim("[Generic_name] strength " . strtolower($form ?: $formVar)),
                ];
            }

            $presBrandShort = $applyTemplate($rule['pres_short'], $rule, $brandName, $formVar ?: $form, $genName, $strength, true);
            $presBrandFull = $applyTemplate($rule['pres_full'], $rule, $brandName, $formVar ?: $form, $genName, $strength, true);
            $presGeneric = $applyTemplate($rule['pres_gen'], $rule, $brandName, $formVar ?: $form, $genName, $strength, false);
            $lblGenShort = $applyTemplate($rule['pres_short'], $rule, $genName, $formVar ?: $form, $genName, $strength, true);
            $lblGenFull = $applyTemplate($rule['pres_full'], $rule, $genName, $formVar ?: $form, $genName, $strength, true);

            $mfgPref = $mfgOrder[$mfgId] ?? 9999;
            $fOrder = $resolveFormOrder($presBrandShort);

            $insStmt->execute([
                $brandId,
                $genId,
                $mfgId,
                $mfgName,
                $mfgShort,
                $drugCls,
                $brandName,
                $form,
                $formVar,
                $genName,
                $row['us_generic_name'] ?? '',
                $row['who_atc_class'] ?? '',
                $row['is_antibiotic'] ?? '',
                $row['is_high_alert_medicine'] ?? '',
                $row['is_safe_in_pregnancy'] ?? '',
                $row['is_safe_in_lactation'] ?? '',
                $row['require_renal_adjustments'] ?? '',
                $row['is_safe_in_hepatic_impairment'] ?? '',
                $row['is_safe_in_paediatric'] ?? '',
                $row['requires_tapering'] ?? '',
                $row['immediate_warning'] ?? '',
                $row['pregnancy_category'] ?? '',
                $strength,
                trim((string)$row['price']),
                trim((string)$row['packsize']),
                $presBrandShort,
                $presBrandFull,
                $presGeneric,
                $presGeneric,
                $lblGenShort,
                $lblGenFull,
                $mfgPref,
                $fOrder,
            ]);

            $count++;
            if ($count % 5000 === 0) {
                $pdo->commit();
                $pdo->beginTransaction();
            }
        }
        $pdo->commit();

        // Indexes
        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_brand_id ON drug_prescribe_bd(brand_id);
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_generic_id ON drug_prescribe_bd(generic_id);
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_brand_name ON drug_prescribe_bd(brand_name);
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_prescribe_brand_short ON drug_prescribe_bd(prescribe_brand_short);
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_prescribe_brand_full ON drug_prescribe_bd(prescribe_brand_full);
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_generic_name ON drug_prescribe_bd(generic_name);
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_us_generic_name ON drug_prescribe_bd(us_generic_name);
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_who_atc_class ON drug_prescribe_bd(who_atc_class);
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_pregnancy_category ON drug_prescribe_bd(pregnancy_category);
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_manufacturer_name ON drug_prescribe_bd(manufacturer_name);
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_manufacturer_name_short ON drug_prescribe_bd(manufacturer_name_short);
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_drug_class ON drug_prescribe_bd(drug_class);
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_brand_name_nocase ON drug_prescribe_bd(brand_name COLLATE NOCASE);
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_prescribe_brand_short_nocase ON drug_prescribe_bd(prescribe_brand_short COLLATE NOCASE);
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_prescribe_brand_full_nocase ON drug_prescribe_bd(prescribe_brand_full COLLATE NOCASE);
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_generic_name_nocase ON drug_prescribe_bd(generic_name COLLATE NOCASE);
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_default_order_expr ON drug_prescribe_bd(
                CAST(manufacturer_preference AS INTEGER),
                CAST(form_order AS INTEGER),
                prescribe_brand_short
            );
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_generic_id_order_expr ON drug_prescribe_bd(
                CAST(generic_id AS TEXT),
                CAST(form_order AS INTEGER),
                CAST(manufacturer_preference AS INTEGER),
                brand_name
            );
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_generic_form_order_expr ON drug_prescribe_bd(
                CAST(generic_id AS TEXT),
                form,
                CAST(manufacturer_preference AS INTEGER),
                prescribe_brand_short
            );
        ");

        // Compatibility views
        $pdo->exec("CREATE VIEW IF NOT EXISTS drug_brand AS SELECT * FROM drug_brand_bd;");
        $pdo->exec("CREATE VIEW IF NOT EXISTS drug_manufacturer AS SELECT * FROM drug_manufacturer_bd;");
        $pdo->exec("CREATE VIEW IF NOT EXISTS drug_prescribe AS SELECT * FROM drug_prescribe_bd;");

        // FTS virtual table
        $pdo->exec("
            DROP TABLE IF EXISTS fts_drug_prescribe;
            CREATE VIRTUAL TABLE fts_drug_prescribe USING fts5(
                brand_name,
                generic_name,
                prescribe_brand_short,
                prescribe_brand_full,
                manufacturer_name,
                content='drug_prescribe_bd',
                content_rowid='brand_id',
                tokenize='unicode61'
            );
            INSERT INTO fts_drug_prescribe(fts_drug_prescribe) VALUES('rebuild');
        ");
        buildDbLog("[SUCCESS] Compiled drug_prescribe_bd ({$count} rows) with FTS5 virtual search index.");
    } else {
        // Generic/International mode from drug_generic_product
        buildDbLog("[BUILD] Compiling generic clinical products prescribe catalog...");
        $pdo->exec("
            DROP TABLE IF EXISTS drug_prescribe_generic;
            CREATE TABLE drug_prescribe_generic (
                product_id INTEGER PRIMARY KEY,
                generic_id TEXT,
                drug_class TEXT,
                form TEXT,
                generic_name TEXT,
                us_generic_name TEXT,
                who_atc_class TEXT,
                is_antibiotic TEXT,
                is_high_alert_medicine TEXT,
                is_safe_in_pregnancy TEXT,
                is_safe_in_lactation TEXT,
                require_renal_adjustments TEXT,
                is_safe_in_hepatic_impairment TEXT,
                is_safe_in_paediatric TEXT,
                requires_tapering TEXT,
                immediate_warning TEXT,
                pregnancy_category TEXT,
                strength TEXT,
                is_who_eml INTEGER,
                who_eml_category TEXT,
                prescribe_generic_short TEXT,
                prescribe_generic_full TEXT,
                form_order INTEGER
            );
        ");

        $insSql = "INSERT INTO drug_prescribe_generic VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $insStmt = $pdo->prepare($insSql);

        $query = "
            SELECT 
                p.product_id, p.generic_id, p.form, p.strength, p.is_who_eml, p.who_eml_category,
                g.generic_name, g.us_generic_name, g.who_atc_class, g.is_antibiotic, g.is_high_alert_medicine,
                g.is_safe_in_pregnancy, g.is_safe_in_lactation, g.require_renal_adjustments, g.is_safe_in_hepatic_impairment,
                g.is_safe_in_paediatric, g.requires_tapering, g.immediate_warning, g.pregnancy_category
            FROM drug_generic_product p
            LEFT JOIN drug_generic g ON CAST(p.generic_id AS INTEGER) = g.generic_id
            ORDER BY p.product_id
        ";
        $srcStmt = $pdo->query($query);
        $count = 0;
        $pdo->beginTransaction();

        while ($row = $srcStmt->fetch(PDO::FETCH_ASSOC)) {
            $prodId = (int)$row['product_id'];
            $genId = trim((string)$row['generic_id']);
            $form = trim((string)$row['form']);
            $strength = trim((string)$row['strength']);
            $genName = trim((string)$row['generic_name']);
            $drugCls = $drugClasses[$genId] ?? '';

            $ruleKey = strtolower($form);
            $rule = $rulesByStd[$ruleKey] ?? ($rulesByForm[$ruleKey] ?? null);
            if (!$rule) {
                $rule = [
                    'std_form' => $form,
                    'add_strength_brand' => true,
                    'add_strength_generic' => true,
                    'pres_full' => '[BRAND_NAME] strength',
                    'pres_short' => '[BRAND_NAME] strength',
                    'pres_gen' => trim("[Generic_name] strength " . strtolower($form)),
                ];
            }

            $presGeneric = $applyTemplate($rule['pres_gen'], $rule, $genName, $form, $genName, $strength, false);
            $fOrder = $resolveFormOrder($presGeneric);

            $insStmt->execute([
                $prodId,
                $genId,
                $drugCls,
                $form,
                $genName,
                $row['us_generic_name'] ?? '',
                $row['who_atc_class'] ?? '',
                $row['is_antibiotic'] ?? '',
                $row['is_high_alert_medicine'] ?? '',
                $row['is_safe_in_pregnancy'] ?? '',
                $row['is_safe_in_lactation'] ?? '',
                $row['require_renal_adjustments'] ?? '',
                $row['is_safe_in_hepatic_impairment'] ?? '',
                $row['is_safe_in_paediatric'] ?? '',
                $row['requires_tapering'] ?? '',
                $row['immediate_warning'] ?? '',
                $row['pregnancy_category'] ?? '',
                $strength,
                (int)($row['is_who_eml'] ?? 0),
                $row['who_eml_category'] ?? '',
                $presGeneric,
                $presGeneric,
                $fOrder,
            ]);

            $count++;
        }
        $pdo->commit();

        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_gen_id ON drug_prescribe_generic(generic_id);
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_gen_name ON drug_prescribe_generic(generic_name);
            CREATE INDEX IF NOT EXISTS idx_drug_prescribe_gen_pres ON drug_prescribe_generic(prescribe_generic_short);
            CREATE VIEW IF NOT EXISTS drug_prescribe AS SELECT 
                product_id AS brand_id, generic_id, '' AS manufacturer_id, '' AS manufacturer_name,
                '' AS manufacturer_name_short, drug_class, generic_name AS brand_name, form, form AS form_variant,
                generic_name, us_generic_name, who_atc_class, is_antibiotic, is_high_alert_medicine,
                is_safe_in_pregnancy, is_safe_in_lactation, require_renal_adjustments, is_safe_in_hepatic_impairment,
                is_safe_in_paediatric, requires_tapering, immediate_warning, pregnancy_category,
                strength, '' AS price, '' AS packsize, prescribe_generic_short AS prescribe_brand_short,
                prescribe_generic_full AS prescribe_brand_full, prescribe_generic_short, prescribe_generic_full,
                prescribe_generic_short AS labelled_generic_short, prescribe_generic_full AS labelled_generic_full,
                9999 AS manufacturer_preference, form_order
            FROM drug_prescribe_generic;
            DROP TABLE IF EXISTS fts_drug_prescribe;
            CREATE VIRTUAL TABLE fts_drug_prescribe USING fts5(
                brand_name,
                generic_name,
                prescribe_brand_short,
                prescribe_brand_full,
                manufacturer_name,
                content='drug_prescribe',
                content_rowid='brand_id',
                tokenize='unicode61'
            );
            INSERT INTO fts_drug_prescribe(fts_drug_prescribe) VALUES('rebuild');
        ");
        buildDbLog("[SUCCESS] Compiled drug_prescribe_generic ({$count} products) with FTS5 search index.");
    }
}

function buildDatabaseFromSql(
    string $sqlFile,
    string $dbFile,
    string $testTable,
    int $minRows,
    bool $force = false,
    ?string $countryCode = null
): bool {
    $name = basename($dbFile);

    // If countryCode is null, try reading from userdata database app_config
    if ($countryCode === null) {
        $baseDir = dirname($dbFile, 3); // .../application
        $userDb = $baseDir . DIRECTORY_SEPARATOR . 'userdata' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'zimrx_userdata.db';
        if (file_exists($userDb)) {
            try {
                $uPdo = new PDO('sqlite:' . $userDb, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);
                $c = $uPdo->query("SELECT config_value FROM zimrx_app_config WHERE config_key = 'practice_country' LIMIT 1")->fetchColumn();
                if (!empty($c)) {
                    $countryCode = strtoupper(trim((string)$c));
                }
            } catch (Throwable) {
                // Ignore
            }
        }
    }

    if ($countryCode === null) {
        // CLI or unconfigured default: use 'BD' for backwards compatibility and test suites
        $countryCode = (php_sapi_name() === 'cli') ? 'BD' : 'INT';
    } else {
        $countryCode = strtoupper(trim($countryCode));
    }
    
    if (!$force && file_exists($dbFile) && filesize($dbFile) > 1024 * 1024) {
        try {
            $testPdo = new PDO('sqlite:' . $dbFile, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $count = (int)$testPdo->query("SELECT count(*) FROM {$testTable}")->fetchColumn();
            if ($count >= $minRows) {
                // For drug db, verify drug_prescribe exists and country pack matches
                if ($name === 'zimrx_drugs.db') {
                    $hasPrescribe = (bool)$testPdo->query("SELECT 1 FROM sqlite_master WHERE type IN ('table', 'view') AND name='drug_prescribe'")->fetchColumn();
                    $hasBrandBd = (bool)$testPdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='drug_brand_bd'")->fetchColumn();
                    $isBd = ($countryCode === 'BD');

                    if ($hasPrescribe && ($hasBrandBd === $isBd)) {
                        buildDbLog("[SKIP] {$name} already exists and matches {$countryCode} catalog ({$count} rows in {$testTable}).");
                        return true;
                    }
                    buildDbLog("[INFO] Existing {$name} does not match country {$countryCode} (hasBrandBd=" . ($hasBrandBd ? '1' : '0') . "). Rebuilding...");
                } elseif (str_starts_with($name, 'zimrx_static')) {
                    $hasPlacesHierarchy = (bool)$testPdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='zimrx_address_hierarchy'")->fetchColumn();
                    $hasPlacesBd = false;
                    if ($hasPlacesHierarchy) {
                        $hasPlacesBd = (bool)$testPdo->query("SELECT 1 FROM zimrx_address_hierarchy WHERE country = 'BD' LIMIT 1")->fetchColumn();
                    }
                    $isBd = ($countryCode === 'BD');

                    if ($hasPlacesBd === $isBd) {
                        buildDbLog("[SKIP] {$name} already exists and matches {$countryCode} places pack ({$count} rows in {$testTable}).");
                        return true;
                    }
                    buildDbLog("[INFO] Existing {$name} places pack does not match country {$countryCode}. Rebuilding...");
                } else {
                    buildDbLog("[SKIP] {$name} already exists and is healthy ({$count} rows in {$testTable}).");
                    return true;
                }
            }
        } catch (Throwable $e) {
            buildDbLog("[INFO] Existing {$name} is incomplete or corrupt ({$e->getMessage()}). Rebuilding...");
        } finally {
            unset($testPdo);
        }
    }

    if (!file_exists($sqlFile)) {
        buildDbLog("[ERROR] SQL seed file not found: {$sqlFile}", true);
        return false;
    }

    buildDbLog("[BUILD] Compiling {$name} from " . basename($sqlFile) . " (" . round(filesize($sqlFile) / (1024 * 1024), 1) . " MB) for country {$countryCode}...");
    $startTime = microtime(true);

    $tempDb = $dbFile . '.tmp.' . uniqid();
    if (file_exists($tempDb)) {
        @unlink($tempDb);
    }

    try {
        $pdo = new PDO('sqlite:' . $tempDb, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Register custom SQLite functions for unistr()
        $registerFunc = function(PDO $p, string $fnName, callable $callback) {
            if (method_exists($p, 'createFunction')) {
                $p->createFunction($fnName, $callback);
            } elseif (method_exists($p, 'sqliteCreateFunction')) {
                @$p->sqliteCreateFunction($fnName, $callback);
            }
        };

        $registerFunc($pdo, 'unistr', function (?string $str): string {
            if ($str === null || $str === '') {
                return (string)$str;
            }
            return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($matches) {
                return mb_chr(hexdec($matches[1]), 'UTF-8');
            }, $str);
        });

        // Fast in-memory SQLite build tuning
        $pdo->exec('PRAGMA foreign_keys = OFF;');
        $pdo->exec('PRAGMA synchronous = OFF;');
        $pdo->exec('PRAGMA journal_mode = MEMORY;');
        $pdo->exec('PRAGMA temp_store = MEMORY;');
        $pdo->exec('PRAGMA cache_size = -64000;');

        $stmtCount = 0;
        executeSqlStream($pdo, $sqlFile, $stmtCount);

        // If compiling zimrx_drugs.db, check for regional country pack and compile prescribing catalog
        if ($name === 'zimrx_drugs.db') {
            $seedsDir = dirname($sqlFile);
            if ($countryCode === 'BD') {
                $bdSeed = $seedsDir . DIRECTORY_SEPARATOR . 'zimrx_drugs_bd.sql';
                if (file_exists($bdSeed)) {
                    buildDbLog("[BUILD] Found Bangladesh Country Pack: " . basename($bdSeed) . " (" . round(filesize($bdSeed) / (1024 * 1024), 1) . " MB). Importing...");
                    executeSqlStream($pdo, $bdSeed, $stmtCount);
                }
            } else {
                $countryLower = strtolower($countryCode);
                $regSeed = $seedsDir . DIRECTORY_SEPARATOR . "zimrx_drugs_{$countryLower}.sql";
                if ($countryLower !== '' && file_exists($regSeed)) {
                    buildDbLog("[BUILD] Found {$countryCode} Country Pack: " . basename($regSeed) . " (" . round(filesize($regSeed) / (1024 * 1024), 1) . " MB). Importing...");
                    executeSqlStream($pdo, $regSeed, $stmtCount);
                } else {
                    buildDbLog("[BUILD] Prescribing catalog for {$countryCode}: using international WHO essential generic catalog.");
                }
            }

            compilePrescribeCatalog($pdo);
        }

        // If compiling zimrx_static.db, check for regional address hierarchy packs matching selected country
        if (str_starts_with($name, 'zimrx_static')) {
            $seedsDir = dirname($sqlFile);
            if ($countryCode === 'BD') {
                $bdPlaceSeed = $seedsDir . DIRECTORY_SEPARATOR . 'zimrx_address_hierarchy_bd.sql';
                if (file_exists($bdPlaceSeed)) {
                    buildDbLog("[BUILD] Found Bangladesh Address Hierarchy Pack: " . basename($bdPlaceSeed) . " (" . round(filesize($bdPlaceSeed) / 1024, 1) . " KB). Importing...");
                    executeSqlStream($pdo, $bdPlaceSeed, $stmtCount);
                }
            } else {
                $countryLower = strtolower($countryCode);
                $regPlaceSeed = $seedsDir . DIRECTORY_SEPARATOR . "zimrx_address_hierarchy_{$countryLower}.sql";
                if ($countryLower !== '' && file_exists($regPlaceSeed)) {
                    buildDbLog("[BUILD] Found {$countryCode} Address Hierarchy Pack: " . basename($regPlaceSeed) . " (" . round(filesize($regPlaceSeed) / 1024, 1) . " KB). Importing...");
                    executeSqlStream($pdo, $regPlaceSeed, $stmtCount);
                }
            }
        }

        // Optimize database for read-heavy production usage
        $pdo->exec('PRAGMA optimize;');
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA synchronous = NORMAL;');
        
        // Disconnect PDO so file is unlocked on Windows
        unset($pdo);

        // Atomic swap
        if (file_exists($dbFile)) {
            @unlink($dbFile);
        }
        if (!rename($tempDb, $dbFile)) {
            copy($tempDb, $dbFile);
            @unlink($tempDb);
        }

        $elapsed = round(microtime(true) - $startTime, 2);
        $finalSize = round(filesize($dbFile) / (1024 * 1024), 1);
        buildDbLog("[SUCCESS] {$name} created successfully ({$stmtCount} statements, {$finalSize} MB in {$elapsed}s).");
        return true;
    } catch (Throwable $e) {
        if (file_exists($tempDb)) {
            @unlink($tempDb);
        }
        buildDbLog("[FAIL] Failed compiling {$name}: " . $e->getMessage(), true);
        return false;
    }
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $force = in_array('--force', $argv ?? [], true);
    $countryArg = null;
    foreach ($argv ?? [] as $arg) {
        if (str_starts_with($arg, '--country=')) {
            $countryArg = strtoupper(trim(substr($arg, 10)));
        }
    }
    $allSuccess = true;
    foreach ($databases as $key => $config) {
        $ok = buildDatabaseFromSql(
            $config['sql'],
            $config['db'],
            $config['test_table'],
            $config['min_rows'],
            $force,
            $countryArg
        );
        if (!$ok) {
            $allSuccess = false;
        }
    }

    exit($allSuccess ? 0 : 1);
}
