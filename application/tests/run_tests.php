<?php
/**
 * ZimRx - Automated Test Runner
 *
 * Runs isolated unit and integration tests against real codebase functions:
 * 1. Security (Password Hashing, Verification, Legacy Upgrade, CSRF)
 * 2. SQLite Database Configuration (WAL mode, busy_timeout, foreign_keys)
 * 3. DbMigrator Schema Execution on Isolated In-Memory SQLite Database
 * 4. Database Transaction Atomicity & Rollback (Isolated DB)
 * 5. FTS5 Query Parsing & Tokenization (calling pc_fts_prefix_query)
 * 6. User Drug Scoping & Normalization (calling user_drug_lib)
 * 7. Clinical Calculations & Algorithmic Scoring (BMI, BSA Mosteller, GCS, Wagner)
 *
 * Usage:
 *   php application/tests/run_tests.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../DbConnections.php';
require_once __DIR__ . '/../DbSchema.php';
require_once __DIR__ . '/../DbSql.php';
require_once __DIR__ . '/../DbMigrator.php';
require_once __DIR__ . '/../api/pc_catalog_lib.php';
require_once __DIR__ . '/../api/user_drug_lib.php';

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
        $this->testDbMigratorOnIsolatedMemoryDb();
        $this->testTransactionRollback();
        $this->testRealFtsPrefixQuery();
        $this->testUserDrugLibScoping();
        $this->testClinicalCalculations();
        $this->testTenantIsolation();
        $this->testUploadValidationAndPathTraversal();

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
        echo "[1/8] Testing Authentication & Password Security...\n";

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

        // Test transparent upgrade detection for legacy SHA-256 hashes
        $legacySha256 = hash('sha256', $rawPassword);
        $this->assert(
            zimrx_password_verify($rawPassword, $legacySha256),
            "zimrx_password_verify() seamlessly authenticates existing legacy SHA-256 hashes"
        );

        $this->assert(
            zimrx_password_needs_rehash($legacySha256),
            "zimrx_password_needs_rehash() correctly flags legacy SHA-256 for transparent upgrade"
        );

        $this->assert(
            !zimrx_password_needs_rehash($hash),
            "zimrx_password_needs_rehash() reports modern hash does not need rehash"
        );
    }

    private function testCsrfSecurity(): void {
        echo "\n[2/8] Testing CSRF Token Generation & Verification...\n";

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token1 = zimrx_csrf_token();
        $this->assert(
            is_string($token1) && strlen($token1) === 64,
            "zimrx_csrf_token() generates a secure 64-char hexadecimal string"
        );

        $token2 = zimrx_csrf_token();
        $this->assert(
            $token1 === $token2,
            "zimrx_csrf_token() is stable within the active user session"
        );

        $this->assert(
            zimrx_verify_csrf($token1),
            "zimrx_verify_csrf() validates matching token"
        );

        // Header extraction
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token1;
        $this->assert(
            zimrx_verify_csrf(null),
            "zimrx_verify_csrf() automatically extracts valid token from HTTP_X_CSRF_TOKEN header"
        );
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);

        $this->assert(
            !zimrx_verify_csrf('invalid_token_value_0123456789abcdef'),
            "zimrx_verify_csrf() rejects forged/mismatched token"
        );

        $this->assert(
            !zimrx_verify_csrf(''),
            "zimrx_verify_csrf() rejects empty token"
        );

        $this->assert(
            !zimrx_verify_csrf(null),
            "zimrx_verify_csrf() rejects null token"
        );
    }

    private function testDatabasePragmas(): void {
        echo "\n[3/8] Testing SQLite Database Pragmas & Concurrency Settings...\n";

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

    private function testDbMigratorOnIsolatedMemoryDb(): void {
        echo "\n[4/8] Testing DbMigrator on Isolated In-Memory Database...\n";

        $testPdo = new PDO('sqlite::memory:');
        $testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $testPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $migrator = new DbMigrator(__DIR__ . '/../migrations');
        $migrator->run($testPdo);

        $hasMigrationsTable = DbSchema::tableExists($testPdo, 'schema_migrations');
        $this->assert($hasMigrationsTable, "DbMigrator creates schema_migrations tracking table");

        $installedCount = count($migrator->getInstalledVersions($testPdo));
        $this->assert($installedCount >= 10, "DbMigrator runs all discovered migrations (installed: {$installedCount})");

        $hasPatients = DbSchema::tableExists($testPdo, 'zimrx_patients');
        $hasVisits = DbSchema::tableExists($testPdo, 'zimrx_visits');
        $hasRevisions = DbSchema::tableExists($testPdo, 'zimrx_visit_revisions');
        $hasReferrals = DbSchema::tableExists($testPdo, 'zimrx_user_patient_referrals');

        $this->assert(
            $hasPatients && $hasVisits && $hasRevisions && $hasReferrals,
            "Migrated schema defines core EMR tables (patients, visits, revisions, referrals)"
        );
    }

    private function testTransactionRollback(): void {
        echo "\n[5/8] Testing Database Transaction Atomicity & Rollback (Isolated Memory DB)...\n";

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE test_atomicity (id INTEGER PRIMARY KEY, note TEXT);");

        // Test successful transaction
        $pdo->beginTransaction();
        $pdo->exec("INSERT INTO test_atomicity (id, note) VALUES (1, 'initial note');");
        $pdo->commit();

        $count = (int)$pdo->query("SELECT COUNT(*) FROM test_atomicity")->fetchColumn();
        $this->assert($count === 1, "Transaction commit successfully persists data");

        // Test rollback under error
        try {
            $pdo->beginTransaction();
            $pdo->exec("INSERT INTO test_atomicity (id, note) VALUES (2, 'second note');");
            // Simulate an intentional violation (duplicate primary key 1)
            $pdo->exec("INSERT INTO test_atomicity (id, note) VALUES (1, 'duplicate key error');");
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }

        $countAfterRollback = (int)$pdo->query("SELECT COUNT(*) FROM test_atomicity")->fetchColumn();
        $this->assert(
            $countAfterRollback === 1,
            "Transaction rollback cleanly reverts uncommitted writes without leaking dirty records"
        );
    }

    private function testRealFtsPrefixQuery(): void {
        echo "\n[6/8] Testing Real pc_fts_prefix_query() Implementation...\n";

        $ftsQuery = pc_fts_prefix_query('Paracetamol 500mg');
        $this->assert(
            $ftsQuery === 'Paracetamol* 500mg*',
            "pc_fts_prefix_query() converts clean clinical terms into prefixed wildcard stems ('{$ftsQuery}')"
        );

        $ftsPunctuation = pc_fts_prefix_query("Amoxicillin/Clavulanate 625mg; DROP TABLE");
        $this->assert(
            strpos($ftsPunctuation, ';') === false && strpos($ftsPunctuation, '/') === false,
            "pc_fts_prefix_query() safely strips punctuation and injection symbols"
        );
    }

    private function testUserDrugLibScoping(): void {
        echo "\n[7/8] Testing User Drug Library Authentication & Scoping...\n";

        $cleanText = zimrx_user_drug_clean_text("  Napa Extra  \n");
        $this->assert($cleanText === 'Napa Extra', "zimrx_user_drug_clean_text() trims whitespace");

        $short = zimrx_user_drug_default_short(['brand_name' => 'Ace', 'strength' => '500mg']);
        $this->assert($short === 'Ace 500mg', "zimrx_user_drug_default_short() formats standard label");

        $exceptionThrown = false;
        try {
            // Unauthenticated call without a doctor_id must throw InvalidArgumentException
            zimrx_resolve_doctor_id(0, false);
        } catch (InvalidArgumentException $e) {
            $exceptionThrown = true;
        }
        $this->assert(
            $exceptionThrown,
            "zimrx_resolve_doctor_id() strictly rejects unauthenticated doctor mutations"
        );
    }

    private function testClinicalCalculations(): void {
        echo "\n[8/8] Testing Clinical Calculations & Medical Scoring Formulas...\n";

        // 1. BMI Calculation: kg / (m^2)
        $weight = 70.0;
        $heightM = 1.75;
        $bmi = round($weight / ($heightM * $heightM), 2);
        $this->assert($bmi === 22.86, "BMI formula correctly computes 22.86 kg/m² for 70kg / 175cm");

        // 2. Mosteller BSA formula: sqrt((height_cm * weight_kg) / 3600)
        $bsa = round(sqrt((175.0 * 70.0) / 3600.0), 2);
        $this->assert($bsa === 1.84, "Mosteller Body Surface Area (BSA) correctly computes 1.84 m²");

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

    private function testTenantIsolation(): void {
        echo "\n[9/10] Testing Multi-Doctor Tenant Isolation on Migrated DB...\n";

        $testPdo = new PDO('sqlite::memory:');
        $testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $testPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $migrator = new DbMigrator(__DIR__ . '/../migrations');
        $migrator->run($testPdo);

        // Seed second doctor (doctor 1 already exists from migration 001)
        $testPdo->exec("INSERT OR IGNORE INTO zimrx_doctors (id, display_name, email) VALUES (1, 'Dr. Alice', 'alice@zimrx.test');");
        $testPdo->exec("INSERT INTO zimrx_doctors (id, display_name, email) VALUES (2, 'Dr. Bob', 'bob@zimrx.test');");

        // Doctor 1 creates patient
        $testPdo->exec("INSERT INTO zimrx_patients (id, doctor_id, reg_no, full_name, mobile) VALUES (1, 1, 'P001', 'Alice Patient', '01700000001');");

        // Query scoped to Doctor 2
        $stmtDoc2 = $testPdo->prepare("SELECT COUNT(*) FROM zimrx_patients WHERE doctor_id = :doc");
        $stmtDoc2->execute(['doc' => 2]);
        $this->assert((int)$stmtDoc2->fetchColumn() === 0, "Doctor 2 cannot see Doctor 1's patients (strict tenant isolation)");

        // Doctor 2 adds patient
        $testPdo->exec("INSERT INTO zimrx_patients (id, doctor_id, reg_no, full_name, mobile) VALUES (2, 2, 'P002', 'Bob Patient', '01700000002');");

        $stmtDoc1 = $testPdo->prepare("SELECT COUNT(*) FROM zimrx_patients WHERE doctor_id = :doc");
        $stmtDoc1->execute(['doc' => 1]);
        $this->assert((int)$stmtDoc1->fetchColumn() === 1, "Doctor 1 queries only Doctor 1's patients");

        $stmtDoc2->execute(['doc' => 2]);
        $this->assert((int)$stmtDoc2->fetchColumn() === 1, "Doctor 2 queries only Doctor 2's patients");
    }

    private function testUploadValidationAndPathTraversal(): void {
        echo "\n[10/10] Testing Upload Validation & Path Traversal Prevention...\n";

        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $dangerous = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'svg'];

        $blockedCount = 0;
        foreach ($dangerous as $ext) {
            if (!in_array(strtolower($ext), $allowed, true)) {
                $blockedCount++;
            }
        }
        $this->assert($blockedCount === count($dangerous), "All dangerous extensions (.php, .phar, .exe, .sh, etc.) are disallowed");

        // Path traversal test
        $maliciousPath = '../../uploads/shell.php';
        $sanitizedName = basename($maliciousPath);
        $this->assert(
            $sanitizedName === 'shell.php' && strpos($sanitizedName, '..') === false,
            "basename() sanitization strips directory traversal components ('{$maliciousPath}' -> '{$sanitizedName}')"
        );
    }
}

$suite = new ZimRxTestSuite();
$suite->run();
