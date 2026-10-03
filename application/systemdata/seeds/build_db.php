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

function buildDatabaseFromSql(string $sqlFile, string $dbFile, string $testTable, int $minRows, bool $force = false): bool
{
    $name = basename($dbFile);
    
    if (!$force && file_exists($dbFile) && filesize($dbFile) > 1024 * 1024) {
        try {
            $testPdo = new PDO('sqlite:' . $dbFile, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $count = (int)$testPdo->query("SELECT count(*) FROM {$testTable}")->fetchColumn();
            if ($count >= $minRows) {
                buildDbLog("[SKIP] {$name} already exists and is healthy ({$count} rows in {$testTable}).");
                return true;
            }
        } catch (Throwable $e) {
            buildDbLog("[INFO] Existing {$name} is incomplete or corrupt ({$e->getMessage()}). Rebuilding...");
        }
    }

    if (!file_exists($sqlFile)) {
        buildDbLog("[ERROR] SQL seed file not found: {$sqlFile}", true);
        return false;
    }

    buildDbLog("[BUILD] Compiling {$name} from " . basename($sqlFile) . " (" . round(filesize($sqlFile) / (1024 * 1024), 1) . " MB)...");
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

        $handle = fopen($sqlFile, 'rb');
        if (!$handle) {
            throw new RuntimeException("Cannot open {$sqlFile} for reading.");
        }

        $buffer = '';
        $inTransaction = false;
        $stmtCount = 0;

        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);

            // Skip pure comments or blank lines if buffer is empty
            if ($buffer === '') {
                if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                    // Check for single line comments
                    if (str_ends_with($trimmed, '*/') || str_starts_with($trimmed, '--')) {
                        continue;
                    }
                }
            }

            $buffer .= $line;

            // Check if statement ends with semicolon
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
                    // Ignore defensive pragma warnings
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
    $allSuccess = true;
    foreach ($databases as $key => $config) {
        $ok = buildDatabaseFromSql(
            $config['sql'],
            $config['db'],
            $config['test_table'],
            $config['min_rows'],
            $force
        );
        if (!$ok) {
            $allSuccess = false;
        }
    }

    exit($allSuccess ? 0 : 1);
}

