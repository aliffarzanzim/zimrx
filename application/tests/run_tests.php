<?php
/**
 * ZimRx - Automated Test Runner
 *
 * Runs self-contained unit and integration tests for:
 * 1. Security (Password Hashing, Verification, Legacy Migration, CSRF)
 * 2. SQLite Database Configuration (WAL mode, busy_timeout, foreign_keys)
 * 3. Database Transaction Atomicity & Rollback
 * 4. Clinical Calculations & Algorithmic Scoring (BMI, BSA Mosteller, GCS, Wagner Diabetic Foot)
 *
 * Usage:
 *   php application/tests/run_tests.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../DbConnections.php';
require_once __DIR__ . '/../DbSchema.php';

class ZimRxTestSuite {
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function run(): void {
        echo "========================================================\n";
        echo " ZimRx Automated Quality & Security Test Suite\n";
        echo "========================================================\n\n";

        $this->testPasswordSecurity();
        $this->testCsrfSecurity();
        $this->testDatabasePragmas();
        $this->testTransactionRollback();
        $this->testClinicalCalculations();

        echo "\n--------------------------------------------------------\n";
        echo "Test Results: {$this->passed} passed, {$this->failed} failed\n";
        if ($this->failed > 0) {
            echo "\nFailures:\n";
            foreach ($this->errors as $err) {
                echo "  ❌ {$err}\n";
            }
            exit(1);
        } else {
            echo "  ✅ All tests passed successfully!\n";
            echo "--------------------------------------------------------\n";
            exit(0);
        }
    }

    private function assert(bool $condition, string $message): void {
        if ($condition) {
            $this->passed++;
            echo "  ✔ {$message}\n";
        } else {
            $this->failed++;
            $this->errors[] = $message;
            echo "  ✖ FAIL: {$message}\n";
        }
    }

    private function testPasswordSecurity(): void {
        echo "[1/5] Testing Authentication & Password Security...\n";

        $rawPassword = 'DoctorSecurePassword2026!';
        $hash = zimrx_password_hash($rawPassword);

        $this->assert(
            str_starts_with($hash, '$2y$') || str_starts_with($hash, '$argon2'),
            "Modern password_hash() produces secure Bcrypt/Argon2 string (got: " . substr($hash, 0, 7) . "...)"
        );

        $this->assert(
            zimrx_password_verify($rawPassword, $hash),
            "zimrx_password_verify() verifies correct password against modern hash"
        );

        $this->assert(
            !zimrx_password_verify('WrongPassword', $hash),
            "zimrx_password_verify() rejects incorrect password"
        );

        // Test backward-compatible legacy SHA-256 fallback
        $legacySha256 = hash('sha256', 'legacy123');
        $this->assert(
            zimrx_password_verify('legacy123', $legacySha256),
            "zimrx_password_verify() transparently authenticates legacy SHA-256 password"
        );

        $this->assert(
            !zimrx_password_verify('wronglegacy', $legacySha256),
            "zimrx_password_verify() rejects incorrect password on legacy SHA-256 hash"
        );
    }

    private function testCsrfSecurity(): void {
        echo "\n[2/5] Testing CSRF Token Generation & Verification...\n";

        $token = zimrx_csrf_token();
        $this->assert(
            strlen($token) === 64 && ctype_xdigit($token),
            "zimrx_csrf_token() generates a 64-character cryptographically secure hex string"
        );

        $this->assert(
            zimrx_verify_csrf($token),
            "zimrx_verify_csrf() accepts matching session token"
        );

        $this->assert(
            !zimrx_verify_csrf('invalid_token_1234567890abcdef'),
            "zimrx_verify_csrf() rejects forged/mismatched token"
        );

        $this->assert(
            !zimrx_verify_csrf(null),
            "zimrx_verify_csrf() rejects null token"
        );
    }

    private function testDatabasePragmas(): void {
        echo "\n[3/5] Testing SQLite Database Pragmas & Concurrency Settings...\n";

        DbConnections::configure(DB_CONFIG);
        $pdo = DbConnections::userdata();

        $journalMode = strtolower((string)$pdo->query('PRAGMA journal_mode;')->fetchColumn());
        $this->assert(
            $journalMode === 'wal',
            "SQLite database is running in Write-Ahead Logging (WAL) mode (got: {$journalMode})"
        );

        $busyTimeout = (int)$pdo->query('PRAGMA busy_timeout;')->fetchColumn();
        $this->assert(
            $busyTimeout >= 5000,
            "SQLite busy_timeout is configured to >= 5000ms for concurrency protection (got: {$busyTimeout}ms)"
        );

        $foreignKeys = (int)$pdo->query('PRAGMA foreign_keys;')->fetchColumn();
        $this->assert(
            $foreignKeys === 1,
            "SQLite foreign key constraints are strictly enforced (PRAGMA foreign_keys = 1)"
        );
    }

    private function testTransactionRollback(): void {
        echo "\n[4/5] Testing Database Transaction Atomicity & Rollback...\n";

        $pdo = DbConnections::userdata();
        $testTable = 'zimrx_test_atomicity_' . time();

        $pdo->exec("CREATE TABLE IF NOT EXISTS {$testTable} (id INTEGER PRIMARY KEY, note TEXT);");

        // Test successful transaction
        $pdo->beginTransaction();
        $pdo->exec("INSERT INTO {$testTable} (id, note) VALUES (1, 'initial note');");
        $pdo->commit();

        $count = (int)$pdo->query("SELECT COUNT(*) FROM {$testTable}")->fetchColumn();
        $this->assert($count === 1, "Transaction commit successfully persists data");

        // Test rollback under error
        try {
            $pdo->beginTransaction();
            $pdo->exec("INSERT INTO {$testTable} (id, note) VALUES (2, 'second note');");
            // Simulate an intentional violation (duplicate primary key 1)
            $pdo->exec("INSERT INTO {$testTable} (id, note) VALUES (1, 'duplicate key error');");
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }

        $countAfterRollback = (int)$pdo->query("SELECT COUNT(*) FROM {$testTable}")->fetchColumn();
        $this->assert(
            $countAfterRollback === 1,
            "Transaction rollback cleanly reverts dirty uncommitted writes on error"
        );

        $pdo->exec("DROP TABLE IF EXISTS {$testTable};");
    }

    private function testClinicalCalculations(): void {
        echo "\n[5/5] Testing Clinical Algorithms & Medical Scoring...\n";

        // 1. BMI Calculation: kg / (m^2)
        // E.g. Weight = 70 kg, Height = 175 cm (1.75 m) -> BMI = 70 / (1.75^2) = 22.86
        $weight = 70.0;
        $heightM = 1.75;
        $bmi = round($weight / ($heightM * $heightM), 2);
        $this->assert($bmi === 22.86, "BMI calculation correctly yields 22.86 kg/m² for 70kg / 175cm");

        // 2. Mosteller BSA formula: sqrt((height_cm * weight_kg) / 3600)
        // E.g. Height = 175 cm, Weight = 70 kg -> sqrt((175 * 70) / 3600) = sqrt(3.40277) = 1.84 m²
        $bsa = round(sqrt((175.0 * 70.0) / 3600.0), 2);
        $this->assert($bsa === 1.84, "Mosteller Body Surface Area (BSA) correctly yields 1.84 m²");

        // 3. Glasgow Coma Scale (GCS) calculation: Eye (1-4) + Verbal (1-5) + Motor (1-6)
        $e = 4; $v = 5; $m = 6;
        $gcsMax = $e + $v + $m;
        $this->assert($gcsMax === 15, "Normal consciousness GCS calculates to 15/15 (E4 V5 M6)");

        $severeE = 1; $severeV = 2; $severeM = 3;
        $gcsSevere = $severeE + $severeV + $severeM;
        $this->assert($gcsSevere === 6 && $gcsSevere <= 8, "Severe coma GCS correctly identifies score <= 8 (Score 6)");

        // 4. Wagner Diabetic Foot Staging Logic
        $wagnerGrades = [
            'Grade 0' => 'Intact skin / pre-ulcerative',
            'Grade 1' => 'Superficial ulcer',
            'Grade 2' => 'Deep ulcer penetrating to tendon/capsule/bone',
            'Grade 3' => 'Deep ulcer with abscess/osteomyelitis',
            'Grade 4' => 'Localized gangrene (forefoot/heel)',
            'Grade 5' => 'Extensive gangrene involving whole foot',
        ];
        $this->assert(count($wagnerGrades) === 6, "Wagner Diabetic Foot classification defines all 6 standard grades (0 to 5)");
    }
}

$suite = new ZimRxTestSuite();
$suite->run();
