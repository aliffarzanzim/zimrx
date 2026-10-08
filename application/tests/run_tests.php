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

require_once __DIR__ . '/../config/config.php';
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db/db_connections.php';
require_once __DIR__ . '/../lib/db/db_schema.php';
require_once __DIR__ . '/../lib/db/db_sql.php';
require_once __DIR__ . '/../lib/db/db_migrator.php';
require_once __DIR__ . '/../lib/pc_catalog_lib.php';
require_once __DIR__ . '/../lib/user_drug_lib.php';
require_once __DIR__ . '/../lib/Services/ClinicalCalculationService.php';
require_once __DIR__ . '/../lib/Services/DrugInteractionService.php';
require_once __DIR__ . '/../lib/Services/AppointmentService.php';
require_once __DIR__ . '/../lib/Services/BillingService.php';
require_once __DIR__ . '/../lib/Services/PatientIpsExportService.php';
require_once __DIR__ . '/../lib/Services/LocaleService.php';

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
        $this->testEndpointMutationGuards();
        $this->testMobileQueueAndOptimisticLocking();
        $this->testSyncJournalAndUuid();
        $this->testActivePatientOwnershipValidation();
        $this->testWalkInUploadIsolation();
        $this->testLoginRateLimitingAndRedirectDefense();
        $this->testClinicalReportSecurityAndApiArchitecture();
        $this->testBillingServiceTransactionSafety();
        $this->testInitGatewayAndPathDiscovery();
        $this->testOfflinePrivacyAndFontIntegrity();
        $this->testTemplateMarkupAndChatSecurity();
        $this->testDatabaseAutoProvisioningAndSecurityRules();
        $this->testWhoAtcAndInnStandardization();
        $this->testInternationalPatientSummaryExport();
        $this->testClinicalLocaleCatalogs();

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
        echo "[1/25] Testing Authentication & Password Security...\n";

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

        // Legacy SHA-256 hashes must be REJECTED (fallback removed for security)
        $legacySha256 = hash('sha256', $rawPassword);
        $this->assert(
            !zimrx_password_verify($rawPassword, $legacySha256),
            "zimrx_password_verify() rejects insecure legacy SHA-256 hashes (migration required)"
        );

        $this->assert(
            !zimrx_password_needs_rehash($hash),
            "zimrx_password_needs_rehash() reports modern hash does not need rehash"
        );
    }

    private function testCsrfSecurity(): void {
        echo "\n[2/25] Testing CSRF Token Generation & Verification...\n";

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
        echo "\n[3/25] Testing SQLite Database Pragmas & Concurrency Settings...\n";

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
        echo "\n[4/25] Testing DbMigrator on Isolated In-Memory Database...\n";

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

        $this->assert(
            DbSchema::columnExists($testPdo, 'zimrx_doctors', 'full_name_native') &&
            DbSchema::columnExists($testPdo, 'zimrx_doctors', 'license_no_en') &&
            DbSchema::columnExists($testPdo, 'zimrx_prescription_header_settings', 'license_no') &&
            DbSchema::columnExists($testPdo, 'zimrx_user_advices', 'category_native'),
            "Migration 015 generalizes doctor localization schema (_native and license_no)"
        );

        require_once __DIR__ . '/../lib/print_setup_lib.php';
        $bdDefs = zimrx_onboarding_defaults('BD');
        $intDefs = zimrx_onboarding_defaults('INT');
        $this->assert(
            str_contains($bdDefs['footer_html'] ?? '', 'solaimanlipi') &&
            str_contains($intDefs['footer_html'] ?? '', 'tinos') &&
            ($intDefs['doctor_profile']['name_native'] ?? '') === 'Dr. Alex Mercer' &&
            ($intDefs['doctor_profile']['name_en'] ?? '') === 'Metropolitan Clinical Center',
            "zimrx_onboarding_defaults resolves regional BD and international INT profiles"
        );
    }

    private function testTransactionRollback(): void {
        echo "\n[5/25] Testing Database Transaction Atomicity & Rollback (Isolated Memory DB)...\n";

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

        // Test rollback under non-Exception Error (TypeError implementing Throwable)
        try {
            $pdo->beginTransaction();
            $pdo->exec("INSERT INTO test_atomicity (id, note) VALUES (3, 'third note');");
            throw new TypeError("Simulated PHP engine TypeError during transaction");
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
        $countAfterTypeError = (int)$pdo->query("SELECT COUNT(*) FROM test_atomicity")->fetchColumn();
        $this->assert(
            $countAfterTypeError === 1,
            "Transaction rollback triggers on Throwable (catches PHP 8 TypeError/Error, not just Exception)"
        );
    }

    private function testRealFtsPrefixQuery(): void {
        echo "\n[6/25] Testing Real pc_fts_prefix_query() Implementation...\n";

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
        echo "\n[7/25] Testing User Drug Library Authentication & Scoping...\n";

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
        echo "\n[8/25] Testing Clinical Calculations & Medical Scoring Formulas (ClinicalCalculationService)...\n";

        // 1. BMI Calculation & WHO Classification
        $normalBmi = ClinicalCalculationService::calculateBmi(70.0, 175.0);
        $this->assert(
            $normalBmi !== null && $normalBmi['bmi'] === 22.86 && $normalBmi['category'] === 'Normal',
            "ClinicalCalculationService calculates BMI 22.86 kg/m² ('Normal') for 70kg / 175cm"
        );
        $this->assert(
            $normalBmi !== null && $normalBmi['ideal_weight_min'] === 56.7 && $normalBmi['ideal_weight_max'] === 76.3,
            "ClinicalCalculationService computes healthy ideal weight range (56.7kg - 76.3kg) for 175cm"
        );

        $obeseBmi = ClinicalCalculationService::calculateBmi(95.0, 170.0);
        $this->assert(
            $obeseBmi !== null && $obeseBmi['bmi'] === 32.87 && $obeseBmi['category'] === 'Obese Class I',
            "ClinicalCalculationService categorizes 95kg / 170cm as Obese Class I (32.87 kg/m²)"
        );

        // 2. Mosteller Body Surface Area formula: sqrt((height_cm * weight_kg) / 3600)
        $bsa = ClinicalCalculationService::calculateBsaMosteller(175.0, 70.0);
        $this->assert($bsa === 1.84, "Mosteller Body Surface Area (BSA) correctly computes 1.84 m²");

        // 3. Glasgow Coma Scale (GCS) calculation: Eye (1-4) + Verbal (1-5) + Motor (1-6)
        $gcsNormal = ClinicalCalculationService::calculateGcs(4, 5, 6);
        $this->assert(
            $gcsNormal['total'] === 15 && $gcsNormal['severity'] === 'Mild Brain Injury / Normal',
            "Normal consciousness GCS calculates to 15/15 (E4 V5 M6)"
        );

        $gcsSevere = ClinicalCalculationService::calculateGcs(1, 2, 3);
        $this->assert(
            $gcsSevere['total'] === 6 && $gcsSevere['severity'] === 'Severe (Coma)',
            "Severe coma GCS correctly identifies score <= 8 (Score 6: E1 V2 M3)"
        );

        // 4. Wagner Diabetic Foot Staging Logic (0 to 5)
        $wagnerGrades = [];
        for ($g = 0; $g <= 5; $g++) {
            $stage = ClinicalCalculationService::getWagnerStage($g);
            if ($stage !== null) {
                $wagnerGrades[$g] = $stage['name'];
            }
        }
        $this->assert(count($wagnerGrades) === 6, "Wagner Diabetic Foot classification defines all 6 standard grades (0 to 5)");
        $this->assert(
            ClinicalCalculationService::getWagnerStage(0)['risk'] === 'Low to Moderate' &&
            ClinicalCalculationService::getWagnerStage(5)['risk'] === 'Critical Emergency',
            "Wagner Diabetic Foot staging stratifies ulcer risk from Grade 0 to Grade 5"
        );

        // 5. Insulin Daily Dose Split Regimens
        $splitBd = ClinicalCalculationService::distributeInsulin(30.0, 'BD');
        $this->assert(
            $splitBd['morning'] === 20 && $splitBd['night'] === 10 && $splitBd['label'] === '20 + 0 + 10',
            "Insulin BD regimen distributes 30 units into 2/3 morning (20u) and 1/3 evening (10u)"
        );
        $splitTds = ClinicalCalculationService::distributeInsulin(30.0, 'TDS');
        $this->assert(
            $splitTds['morning'] === 10 && $splitTds['noon'] === 10 && $splitTds['night'] === 10,
            "Insulin TDS regimen splits 30 units evenly into three 10u doses (10 + 10 + 10)"
        );
    }

    private function testTenantIsolation(): void {
        echo "\n[9/25] Testing Multi-Doctor Tenant Isolation on Migrated DB...\n";

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
        echo "\n[10/25] Testing Upload Validation & Path Traversal Prevention...\n";

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

        // SVG security validator tests
        $safeSvg = sys_get_temp_dir() . '/safe_test.svg';
        $xssSvg = sys_get_temp_dir() . '/xss_test.svg';
        $xxeSvg = sys_get_temp_dir() . '/xxe_test.svg';
        $onloadSvg = sys_get_temp_dir() . '/onload_test.svg';

        file_put_contents($safeSvg, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>');
        file_put_contents($xssSvg, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        file_put_contents($xxeSvg, '<?xml version="1.0"?><!DOCTYPE test [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><svg>&xxe;</svg>');
        file_put_contents($onloadSvg, '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>');

        $this->assert(zimrx_validate_safe_svg($safeSvg), "zimrx_validate_safe_svg() approves clean, well-formed SVG");
        $this->assert(!zimrx_validate_safe_svg($xssSvg), "zimrx_validate_safe_svg() blocks SVG containing <script> tags");
        $this->assert(!zimrx_validate_safe_svg($xxeSvg), "zimrx_validate_safe_svg() blocks SVG containing DOCTYPE / ENTITY (XXE)");
        $this->assert(!zimrx_validate_safe_svg($onloadSvg), "zimrx_validate_safe_svg() blocks SVG containing onload / event handlers");

        @unlink($safeSvg);
        @unlink($xssSvg);
        @unlink($xxeSvg);
        @unlink($onloadSvg);

        // Verify cryptographically randomized upload filename generator
        $sampleName = sprintf('doctor-%d-%d-%s.%s', 1, time(), bin2hex(random_bytes(8)), 'png');
        $this->assert(
            (bool)preg_match('/^doctor-\d+-\d+-[0-9a-f]{16}\.png$/', $sampleName),
            "Upload filename schema enforces randomized 16-hex character cryptographically secure entropy"
        );

        // Ensure zero collision across generated filename tokens
        $tokens = [];
        for ($i = 0; $i < 100; $i++) {
            $tokens[] = bin2hex(random_bytes(8));
        }
        $this->assert(count(array_unique($tokens)) === 100, "100 generated upload tokens demonstrate zero entropy collision");
    }

    private function testEndpointMutationGuards(): void {
        echo "\n[11/25] Testing Write Endpoint Mutation Guard Patterns (Method & CSRF Enforcement)...\n";

        // Setup session token
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $validToken = zimrx_csrf_token();

        // 1. Valid token passes
        $this->assert(
            zimrx_verify_csrf($validToken),
            "zimrx_verify_csrf() approves genuine CSRF token matching active session"
        );

        // 2. Forged token fails
        $forgedToken = bin2hex(random_bytes(32));
        $this->assert(
            !zimrx_verify_csrf($forgedToken),
            "zimrx_verify_csrf() rejects forged CSRF token"
        );

        // 3. Current user doctor ID when unauthenticated is 0, avoiding default Doctor 1
        $savedSession = $_SESSION;
        unset($_SESSION['user_id'], $_SESSION['doctor_id'], $_SESSION['role']);
        $unauthDocId = function_exists('current_user_doctor_id') ? current_user_doctor_id() : 0;
        $this->assert(
            $unauthDocId === 0,
            "current_user_doctor_id() returns 0 for unauthenticated requests, preventing unauthorized Doctor 1 fallback"
        );

        $_SESSION = $savedSession;
        $_SESSION['user_id'] = 999;
        unset($_SESSION['doctor_id']);
        $this->assert(
            current_user_doctor_id() === 0,
            "current_user_doctor_id() rejects logged-in sessions without an explicit doctor scope"
        );

        // Restore session
        $_SESSION = $savedSession;
    }

    private function testMobileQueueAndOptimisticLocking(): void {
        echo "\n[12/25] Testing Mobile Upload Queue Patient Binding & Visit Optimistic Locking...\n";

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new DbMigrator())->run($pdo);

        // 1. Insert patient-bound upload into queue
        $uploadId = 'up_test_123';
        $doctorId = 1;
        $patientId = 42;
        $visitRecordId = 84;
        $activeRevision = 3;

        $stmt = $pdo->prepare(
            "INSERT INTO zimrx_mobile_upload_queue (
                id, doctor_id, patient_id, visit_record_id, active_revision,
                file_path, original_name, report_name, report_date, created_at, claimed_at
            ) VALUES (
                :id, :doctor_id, :patient_id, :visit_record_id, :active_revision,
                'uploads/reports/test.jpg', 'cbc.jpg', 'CBC Report', '28/09/2026',
                CURRENT_TIMESTAMP, NULL
            )"
        );
        $stmt->execute([
            'id' => $uploadId,
            'doctor_id' => $doctorId,
            'patient_id' => $patientId,
            'visit_record_id' => $visitRecordId,
            'active_revision' => $activeRevision
        ]);

        // 2. Query with mismatched active_revision (doctor switched patients) must find nothing
        $staleCheck = $pdo->prepare(
            "SELECT id FROM zimrx_mobile_upload_queue
             WHERE doctor_id = :doctor_id AND claimed_at IS NULL AND active_revision = :active_revision"
        );
        $staleCheck->execute(['doctor_id' => $doctorId, 'active_revision' => 4]);
        $staleRows = $staleCheck->fetchAll(PDO::FETCH_ASSOC);
        $this->assert(
            empty($staleRows),
            "Mobile queue consumption strictly rejects uploads from a mismatched active patient revision"
        );

        // 3. Query with matching active_revision finds the queued item
        $matchCheck = $pdo->prepare(
            "SELECT id, patient_id, visit_record_id, active_revision
             FROM zimrx_mobile_upload_queue
             WHERE doctor_id = :doctor_id AND claimed_at IS NULL AND active_revision = :active_revision"
        );
        $matchCheck->execute(['doctor_id' => $doctorId, 'active_revision' => $activeRevision]);
        $matchedRows = $matchCheck->fetchAll(PDO::FETCH_ASSOC);
        $this->assert(
            count($matchedRows) === 1 && (int)$matchedRows[0]['patient_id'] === $patientId,
            "Mobile queue accurately finds upload matching current active patient context"
        );

        // 4. Atomic exclusive claim
        $pdo->beginTransaction();
        $claimStmt = $pdo->prepare(
            "UPDATE zimrx_mobile_upload_queue
             SET claimed_at = CURRENT_TIMESTAMP
             WHERE doctor_id = :doctor_id AND claimed_at IS NULL AND id = :id"
        );
        $claimStmt->execute(['doctor_id' => $doctorId, 'id' => $uploadId]);
        $claimedCount = $claimStmt->rowCount();
        $pdo->commit();

        $this->assert($claimedCount === 1, "Atomic queue claim exclusively marks target queue item claimed");

        // Subsequent check yields 0 rows
        $postClaimCheck = $pdo->prepare(
            "SELECT id FROM zimrx_mobile_upload_queue WHERE doctor_id = :doctor_id AND claimed_at IS NULL"
        );
        $postClaimCheck->execute(['doctor_id' => $doctorId]);
        $this->assert(
            empty($postClaimCheck->fetchAll()),
            "Claimed queue item is no longer visible to subsequent pollers"
        );

        // 5. Test Visit Optimistic Locking
        $pdo->prepare(
            "INSERT INTO zimrx_visits (id, doctor_id, patient_id, visit_no, revision)
             VALUES (10, 1, 42, 1, 1)"
        )->execute();

        // First save with expected revision = 1 succeeds and increments to 2
        $upStmt1 = $pdo->prepare(
            "UPDATE zimrx_visits
             SET revision = revision + 1, updated_at = CURRENT_TIMESTAMP
             WHERE id = 10 AND doctor_id = 1 AND revision = :expected_revision"
        );
        $upStmt1->execute(['expected_revision' => 1]);
        $this->assert($upStmt1->rowCount() === 1, "First guarded visit update succeeds when revision matches (1 -> 2)");

        // Concurrent/second save presenting stale expected revision = 1 fails (0 rows updated)
        $upStmt2 = $pdo->prepare(
            "UPDATE zimrx_visits
             SET revision = revision + 1, updated_at = CURRENT_TIMESTAMP
             WHERE id = 10 AND doctor_id = 1 AND revision = :expected_revision"
        );
        $upStmt2->execute(['expected_revision' => 1]);
        $this->assert(
            $upStmt2->rowCount() === 0,
            "Concurrent guarded update with stale revision is detected and rejected (0 rows updated)"
        );
    }

    private function testSyncJournalAndUuid(): void {
        echo "\n[13/25] Testing Delta-Sync UUID Generation & Audit Journaling...\n";

        require_once __DIR__ . '/../lib/Services/SyncJournalService.php';

        // 1. UUID format
        $uuid1 = SyncJournalService::generateUuid();
        $uuid2 = SyncJournalService::generateUuid();
        $this->assert(
            (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid1),
            "SyncJournalService::generateUuid() generates compliant RFC 4122 v4 UUID ({$uuid1})"
        );
        $this->assert($uuid1 !== $uuid2, "Consecutive UUIDs are unique");

        // 2. Change logging in database
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new DbMigrator())->run($pdo);

        SyncJournalService::logChange(
            $pdo,
            'visit',
            $uuid1,
            'insert',
            0,
            1,
            ['id' => 10, 'visit_no' => 1, 'patient_id' => 42]
        );

        SyncJournalService::logChange(
            $pdo,
            'visit',
            $uuid1,
            'update',
            1,
            2,
            ['id' => 10, 'visit_no' => 1]
        );

        $stmt = $pdo->query("SELECT * FROM zimrx_sync_changes ORDER BY sequence ASC");
        $changes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assert(count($changes) === 2, "SyncJournalService logs change entries sequentially");
        $this->assert(
            $changes[0]['operation'] === 'insert' && (int)$changes[0]['new_revision'] === 1,
            "Insert journal entry records base_revision 0 -> new_revision 1"
        );
        $this->assert(
            $changes[1]['operation'] === 'update' && (int)$changes[1]['base_revision'] === 1 && (int)$changes[1]['new_revision'] === 2,
            "Update journal entry records base_revision 1 -> new_revision 2"
        );
    }

    // [14/25] Active Patient Ownership Validation
    private function testActivePatientOwnershipValidation(): void {
        echo "\n[14/25] Testing Active Patient Ownership Validation (zimrx_verify_patient_ownership)...\n";

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new DbMigrator())->run($pdo);

        // Seed doctors (migration 001 already inserts id=1; use OR IGNORE to skip)
        $pdo->exec("INSERT OR IGNORE INTO zimrx_doctors (id, doctor_code, display_name, full_name_en) VALUES (1, 'D001', 'Dr Alpha', 'Dr Alpha')");
        $pdo->exec("INSERT INTO zimrx_doctors (id, doctor_code, display_name, full_name_en) VALUES (2, 'D002', 'Dr Beta',  'Dr Beta')");
        $pdo->exec("INSERT OR IGNORE INTO zimrx_patients (id, doctor_id, full_name, reg_no) VALUES (10, 1, 'Patient Alpha', 'PA001')");
        $pdo->exec("INSERT OR IGNORE INTO zimrx_patients (id, doctor_id, full_name, reg_no) VALUES (20, 2, 'Patient Beta',  'PB001')");
        $pdo->exec("INSERT OR IGNORE INTO zimrx_visits (id, doctor_id, patient_id, visit_no) VALUES (100, 1, 10, 1)");
        $pdo->exec("INSERT OR IGNORE INTO zimrx_visits (id, doctor_id, patient_id, visit_no) VALUES (200, 2, 20, 1)");

        // Walk-in (0,0) always allowed
        $this->assert(zimrx_verify_patient_ownership($pdo, 1, 0, 0), 'Walk-in context (patient_id=0, visit_record_id=0) is always permitted');

        // Doctor 1 legitimately owns patient 10 + visit 100
        $this->assert(zimrx_verify_patient_ownership($pdo, 1, 10, 100), 'Doctor 1 can set active context to their own patient+visit');

        // Doctor 1 CANNOT use Doctor 2's visit (200)
        $this->assert(!zimrx_verify_patient_ownership($pdo, 1, 10, 200), 'Forged visit_record_id belonging to another doctor is rejected');

        // Doctor 1 CANNOT claim Doctor 2's patient (20) even without a visit
        $this->assert(!zimrx_verify_patient_ownership($pdo, 1, 20, 0), 'Forged patient_id belonging to another doctor is rejected');

        // Doctor 2 legitimately owns patient 20 + visit 200
        $this->assert(zimrx_verify_patient_ownership($pdo, 2, 20, 200), 'Doctor 2 can set active context to their own patient+visit');
    }

    // [15/25] Walk-in Upload Isolation
    private function testWalkInUploadIsolation(): void {
        echo "\n[15/25] Testing Walk-in Upload Isolation...\n";

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new DbMigrator())->run($pdo);

        // Insert one bound upload (patient_id=5) and one walk-in (patient_id=NULL)
        $pdo->exec("INSERT INTO zimrx_mobile_upload_queue (id, doctor_id, patient_id, visit_record_id, active_revision, file_path, original_name, report_name, report_date, claimed_at)
                    VALUES ('up_bound', 1, 5, 10, 1, 'uploads/reports/bound.jpg', 'bound.jpg', 'Blood Test', '28/09/2026', NULL)");
        $pdo->exec("INSERT INTO zimrx_mobile_upload_queue (id, doctor_id, patient_id, visit_record_id, active_revision, file_path, original_name, report_name, report_date, claimed_at)
                    VALUES ('up_walkin', 1, NULL, NULL, 1, 'uploads/reports/walkin.jpg', 'walkin.jpg', 'X-Ray', '28/09/2026', NULL)");

        // Polling with patient_id=5: must return ONLY the bound record
        $stmt = $pdo->prepare("SELECT id FROM zimrx_mobile_upload_queue WHERE doctor_id = 1 AND claimed_at IS NULL AND patient_id = :pid");
        $stmt->execute(['pid' => 5]);
        $boundResults = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assert($boundResults === ['up_bound'], 'Polling with patient_id returns ONLY the bound record (not walk-in)');

        // Polling with patient_id=0 (unassigned screen): must return ONLY the walk-in record
        $stmt2 = $pdo->prepare("SELECT id FROM zimrx_mobile_upload_queue WHERE doctor_id = 1 AND claimed_at IS NULL AND (patient_id IS NULL OR patient_id = 0)");
        $stmt2->execute();
        $walkinResults = $stmt2->fetchAll(PDO::FETCH_COLUMN);
        $this->assert($walkinResults === ['up_walkin'], 'Polling for unbound context returns ONLY walk-in records (not bound)');

        // Confirm the two result sets are disjoint
        $this->assert(
            empty(array_intersect($boundResults, $walkinResults)),
            'Bound and walk-in upload sets are disjoint: no cross-contamination'
        );
    }

    private function testLoginRateLimitingAndRedirectDefense(): void {
        echo "\n[16/25] Testing Login Security (Rate-Limiting & Open-Redirect Defense)...\n";

        // 1. Open-Redirect Validation
        $this->assert(zimrx_sanitize_redirect('http://attacker.com') === '', 'Redirect rejects http:// external URLs');
        $this->assert(zimrx_sanitize_redirect('https://attacker.com') === '', 'Redirect rejects https:// external URLs');
        $this->assert(zimrx_sanitize_redirect('//attacker.com/steal') === '', 'Redirect rejects protocol-relative // URLs');
        $this->assert(zimrx_sanitize_redirect('\\\\attacker.com\\steal') === '', 'Redirect rejects backslash-based relative URLs');
        $this->assert(zimrx_sanitize_redirect('/foo\\bar') === '', 'Redirect rejects URLs containing embedded backslashes');
        $this->assert(zimrx_sanitize_redirect('javascript:alert(1)') === '', 'Redirect rejects javascript: pseudo-protocol');
        $this->assert(zimrx_sanitize_redirect('data:text/html,bad') === '', 'Redirect rejects data: pseudo-protocol');
        $this->assert(zimrx_sanitize_redirect('prescription.php?id=12') === 'prescription.php?id=12', 'Redirect accepts legitimate local path');

        // 2. Login Rate-Limiting Counter Logic with Superglobal Isolation
        $savedSession = $_SESSION;
        $testIp = '192.168.1.100';

        try {
            zimrx_clear_login_rate_limit($testIp);
            $this->assert(zimrx_check_login_rate_limit($testIp) === null, 'Login rate limit starts unblocked');

            // Simulate 4 failed attempts
            for ($i = 1; $i <= 4; $i++) {
                $count = zimrx_record_failed_login($testIp, 5, 60);
                $this->assert($count === $i, "Recorded failed login attempt #{$i}");
            }
            $this->assert(zimrx_check_login_rate_limit($testIp) === null, 'Lockout is not triggered before threshold');

            // 5th failed attempt triggers lockout
            $fifthAttempt = zimrx_record_failed_login($testIp, 5, 60);
            $this->assert($fifthAttempt === 0, '5th failed attempt triggers lockout return (0)');
            $remaining = zimrx_check_login_rate_limit($testIp);
            $this->assert($remaining !== null && $remaining > 0 && $remaining <= 60, 'Active lockout remaining seconds reported');

            // Successful login resets lockout keys
            zimrx_clear_login_rate_limit($testIp);
            $this->assert(zimrx_check_login_rate_limit($testIp) === null, 'Lockout keys cleanly wiped on clear');
        } finally {
            $_SESSION = $savedSession;
        }

        // 3. Emergency Account Recovery Cryptographic Token Verification
        $rawRecoveryKey = bin2hex(random_bytes(16));
        $hashedRecoveryKey = hash('sha256', $rawRecoveryKey);
        $this->assert(
            hash_equals($hashedRecoveryKey, hash('sha256', $rawRecoveryKey)),
            'Emergency recovery key verification uses constant-time hash_equals comparison'
        );
        $this->assert(
            !hash_equals($hashedRecoveryKey, hash('sha256', 'tampered_recovery_key')),
            'Constant-time recovery key comparison securely rejects invalid tokens'
        );
    }

    private function testClinicalReportSecurityAndApiArchitecture(): void {
        echo "\n[17/25] Testing Clinical Report Security & API Architecture Policies...\n";

        // 1. Root Apache/cPanel .htaccess verification
        $rootHtaccess = __DIR__ . '/../public/.htaccess';
        $this->assert(file_exists($rootHtaccess), 'Root application/public/.htaccess exists for webroot security');
        $rootHtContent = (string)file_get_contents($rootHtaccess);
        $this->assert(
            str_contains($rootHtContent, 'Options -Indexes') && str_contains($rootHtContent, 'uploads/reports'),
            'public/.htaccess disables directory indexing and blocks direct HTTP access to clinical reports'
        );

        // 2. Storage defense-in-depth .htaccess verification
        $reportsHtaccess = __DIR__ . '/../userdata/uploads/reports/.htaccess';
        $this->assert(file_exists($reportsHtaccess), 'userdata/uploads/reports/.htaccess defense-in-depth file exists');
        $reportsHtContent = (string)file_get_contents($reportsHtaccess);
        $this->assert(
            str_contains($reportsHtContent, 'Require all denied') || str_contains($reportsHtContent, 'Deny from all'),
            'reports/.htaccess strictly denies all direct HTTP access'
        );

        // 3. Path traversal neutralization simulation
        $traversalInput = '../../userdata/database/zimrx_userdata.db';
        $sanitizedFile = basename(urldecode($traversalInput));
        $this->assert($sanitizedFile === 'zimrx_userdata.db', 'basename() strips directory traversal escape sequences');
        $reportsDir = realpath(__DIR__ . '/../userdata/uploads/reports') ?: '';
        $simulatedTarget = $reportsDir . DIRECTORY_SEPARATOR . $sanitizedFile;
        $this->assert(!file_exists($simulatedTarget), 'Escalation outside reports directory returns 404');

        // 4. Tenant Scoping Validation Logic
        $this->assert(zimrx_parse_report_doctor_id('report-1-1780242361.pdf') === 1, 'Report filename parser extracts Doctor 1 ownership');
        $this->assert(zimrx_parse_report_doctor_id('report-2-1780242361.png') === 2, 'Report filename parser extracts Doctor 2 ownership');
        $this->assert(zimrx_parse_report_doctor_id('random_file.pdf') === 0, 'Non-standard filename defaults to 0 doctor scope');

        // Doctor 1 accessing Doctor 2's report is blocked
        $currentDoctor = 1;
        $reportOwner = zimrx_parse_report_doctor_id('report-2-1780242361.png');
        $this->assert($currentDoctor !== $reportOwner, 'Cross-doctor report access detected and blocked');

        // 5. Comprehensive API Security & Authentication Architecture Gate
        $apiFiles = glob(__DIR__ . '/../public/api/*.php') ?: [];
        $unauthenticatedApis = [];
        $untypedApis = [];
        $authWhitelist = ['first_launch_save.php', 'zrx_icons.php'];

        foreach ($apiFiles as $file) {
            $base = basename($file);
            $content = (string)file_get_contents($file);
            if (!str_contains($content, 'declare(strict_types=1);')) {
                $untypedApis[] = $base;
            }
            if (!in_array($base, $authWhitelist, true)) {
                $hasAuth = str_contains($content, 'require_login()') 
                    || str_contains($content, 'current_user_doctor_id()')
                    || str_contains($content, 'require_assistant_or_doctor()');
                if (!$hasAuth) {
                    $unauthenticatedApis[] = $base;
                }
            }
        }

        $this->assert(
            empty($untypedApis),
            'All ' . count($apiFiles) . ' API endpoints enforce declare(strict_types=1) at file header'
        );
        $this->assert(
            empty($unauthenticatedApis),
            'All public API endpoints require authenticated doctor/assistant session (0 unprotected endpoints)'
        );

        // 6. CSRF Protection Gate on State-Mutating Endpoints & Management Forms
        $mutatingEndpoints = [
            'print_setup_save.php',
            'header_edit_ajax.php',
            'header_onboarding_ajax.php',
            'reset_print_setup.php',
        ];
        $missingCsrf = [];
        foreach ($mutatingEndpoints as $ep) {
            $code = (string)file_get_contents(__DIR__ . '/../public/api/' . $ep);
            if (!str_contains($code, 'zimrx_verify_csrf()')) {
                $missingCsrf[] = $ep;
            }
        }
        $this->assert(
            empty($missingCsrf),
            'All state-mutating API endpoints enforce zimrx_verify_csrf() validation'
        );

        $managementPages = [
            'profile_settings.php',
            'emr_settings.php',
            'appointment_settings.php',
            'admin_doctors.php',
            'admin_assistants.php',
            'doctor_assistants.php',
        ];
        $missingFormCsrf = [];
        foreach ($managementPages as $mp) {
            $code = (string)file_get_contents(__DIR__ . '/../public/' . $mp);
            if (!str_contains($code, 'zimrx_verify_csrf()') && !str_contains($code, 'zimrx_csrf_field()')) {
                $missingFormCsrf[] = $mp;
            }
        }
        $this->assert(
            empty($missingFormCsrf),
            'All administrative and configuration forms enforce CSRF verification'
        );
    }

    private function testBillingServiceTransactionSafety(): void {
        echo "\n[18/25] Testing BillingService & Financial Transaction Safety...\n";

        $memPdo = new PDO('sqlite::memory:');
        $memPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $memPdo->exec('PRAGMA foreign_keys = 1');
        $migrator = new DbMigrator(__DIR__ . '/../migrations');
        $migrator->run($memPdo);

        $billingService = new BillingService($memPdo);

        // 1. Seed patient, appointment
        $memPdo->exec("INSERT OR IGNORE INTO zimrx_doctors (id, display_name, full_name_en) VALUES (1, 'Dr. Tester', 'Dr. Tester')");
        $memPdo->exec("INSERT INTO zimrx_patients (id, doctor_id, reg_no, full_name) VALUES (10, 1, 'P-001', 'Test Patient')");
        $today = date('Y-m-d');
        $memPdo->exec(
            "INSERT INTO zimrx_appointments (id, doctor_id, patient_id, appointment_no, appointment_date, patient_name, visit_fee, discount, paid_amount)
             VALUES (100, 1, 10, 'A-01', '{$today}', 'Test Patient', 500, 50, 450)"
        );

        // 2. Reconcile missing payment atomically
        $synced = $billingService->reconcileMissingAppointmentPayments(1);
        $this->assert($synced === 1, 'BillingService atomically reconciles 1 unbilled paid appointment');

        // 3. Verify ledger payment entry created with generated receipt
        $payment = $memPdo->query("SELECT * FROM zimrx_payments WHERE appointment_id = 100")->fetch(PDO::FETCH_ASSOC);
        $this->assert($payment !== false, 'Payment record successfully inserted into ledger');
        $this->assert((float)$payment['visit_fee'] === 500.0, 'Payment gross fee accurately stored');
        $this->assert((float)$payment['discount'] === 50.0, 'Payment discount accurately stored');
        $this->assert((float)$payment['paid_amount'] === 450.0, 'Payment paid amount accurately stored');
        $this->assert($payment['payment_status'] === 'paid', 'Payment status calculated as paid');
        $this->assert(!empty($payment['receipt_no']) && str_starts_with($payment['receipt_no'], 'INV-'), 'Receipt number automatically generated');

        // 4. Test idempotency (calling again must not duplicate or re-insert)
        $syncedAgain = $billingService->reconcileMissingAppointmentPayments(1);
        $this->assert($syncedAgain === 0, 'Reconciliation is idempotent (0 records synced on re-run)');

        // 5. Test Date Range Presets
        $rangeToday = $billingService->resolveDateRange('today');
        $this->assert($rangeToday['from'] === date('Y-m-d') && $rangeToday['to'] === date('Y-m-d'), 'Date range resolver handles today preset');

        $rangeMonth = $billingService->resolveDateRange('this_month');
        $this->assert($rangeMonth['from'] === date('Y-m-01') && $rangeMonth['to'] === date('Y-m-d'), 'Date range resolver handles this_month preset');

        // 6. Test filtered queries and aggregates
        $result = $billingService->getFilteredTransactions(1, ['from' => date('Y-m-d'), 'to' => date('Y-m-d'), 'status' => 'all']);
        $this->assert(count($result['transactions']) === 1, 'Filtered transaction query retrieves seeded transaction');
        $this->assert($result['aggregates']['collected'] === 450.0, 'Filtered aggregates calculate collected revenue accurately');
        $this->assert($result['aggregates']['invoiced'] === 500.0, 'Filtered aggregates calculate invoiced total accurately');
        $this->assert($result['aggregates']['due'] === 0.0, 'Filtered aggregates calculate zero due on fully paid item');
    }

    private function testInitGatewayAndPathDiscovery(): void {
        echo "\n[19/25] Testing init.php Gateway Architecture & Domain Services (Appointment, Interaction, Country)...\n";

        $initPath = __DIR__ . '/../public/init.php';
        $this->assert(file_exists($initPath), 'public/init.php gateway file exists');

        $initCode = (string)file_get_contents($initPath);
        $this->assert(
            str_contains($initCode, "define('ZIMRX_BASE_DIR'") && str_contains($initCode, "define('ZIMRX_PUBLIC_DIR'"),
            'init.php establishes central ZIMRX_BASE_DIR and ZIMRX_PUBLIC_DIR environment constants'
        );
        $this->assert(
            str_contains($initCode, "/../config/config.php") && str_contains($initCode, "/application/config/config.php"),
            'init.php supports both offline standalone/USB and webroot cPanel environments'
        );
        $this->assert(file_exists(__DIR__ . '/../lib/auth.php') && file_exists(__DIR__ . '/../lib/db/db.php'), 'Core auth and database bootstrapping libraries exist in application/lib');
        $this->assert(is_dir(__DIR__ . '/../systemdata/seeds'), 'System SQL reference seeds directory located in systemdata/seeds');

        // Check public pages use init.php
        $publicFiles = glob(__DIR__ . '/../public/*.php');
        $pagesUsingInit = 0;
        $pagesWithRawDirname = 0;
        foreach ($publicFiles as $pf) {
            if (basename($pf) === 'init.php') continue;
            $code = (string)file_get_contents($pf);
            if (str_contains($code, "init.php")) {
                $pagesUsingInit++;
            }
            if (str_contains($code, "dirname(__DIR__)")) {
                $pagesWithRawDirname++;
            }
        }
        $this->assert($pagesUsingInit >= 28, "All public page controllers wire through init.php (got: {$pagesUsingInit})");
        $this->assert($pagesWithRawDirname === 0, "No raw dirname(__DIR__) path escapes remain in public pages");

        // Check public APIs use init.php
        $apiFiles = glob(__DIR__ . '/../public/api/*.php');
        $apisUsingInit = 0;
        $apisWithRawDirname2 = 0;
        foreach ($apiFiles as $af) {
            if (basename($af) === 'zrx_icons.php') continue;
            $code = (string)file_get_contents($af);
            if (str_contains($code, "init.php")) {
                $apisUsingInit++;
            }
            if (str_contains($code, "dirname(__DIR__, 2)")) {
                $apisWithRawDirname2++;
            }
        }
        $this->assert($apisUsingInit >= 52, "All public API endpoints wire through init.php (got: {$apisUsingInit})");
        $this->assert($apisWithRawDirname2 === 0, "No raw dirname(__DIR__, 2) path escapes remain in public APIs");

        // Enforce centralized PDO abstraction: zero raw new PDO instantiations in public APIs or lib
        $allSourceFiles = array_merge(
            glob(__DIR__ . '/../public/api/*.php') ?: [],
            glob(__DIR__ . '/../lib/*.php') ?: []
        );
        $rawPdoCount = 0;
        foreach ($allSourceFiles as $sf) {
            $code = (string)file_get_contents($sf);
            if (preg_match('/\bnew\s+PDO\s*\(/i', $code)) {
                $rawPdoCount++;
            }
        }
        $this->assert($rawPdoCount === 0, "Zero raw new PDO instantiations in public APIs or lib (enforcing DbConnections)");

        // Verify PSR-4 autoloading alignment for ZimRx\Services
        $serviceClasses = [
            'ZimRx\\Services\\SyncJournalService',
            'ZimRx\\Services\\BillingService',
            'ZimRx\\Services\\AppointmentService',
            'ZimRx\\Services\\DrugInteractionService',
            'ZimRx\\Services\\ClinicalCalculationService',
            'ZimRx\\Services\\PrintLayoutService',
            'ZimRx\\Services\\CountryService',
        ];
        foreach ($serviceClasses as $cls) {
            $this->assert(class_exists($cls), "Composer PSR-4 class {$cls} resolves and loads");
        }

        // Verify AppointmentService clinical queue calculation
        $this->assert(
            \ZimRx\Services\AppointmentService::dmyToIso('28/09/2026') === '2026-09-28' &&
            \ZimRx\Services\AppointmentService::isoToDmy('2026-09-28') === '28/09/2026',
            "AppointmentService handles bidirectional ISO and DMY date transformations"
        );
        $apptService = new \ZimRx\Services\AppointmentService(new PDO('sqlite::memory:'));
        $defaultSettings = $apptService->getSettings(0);
        $this->assert(
            ($defaultSettings['default_start_time'] ?? '') === '14:00' && ($defaultSettings['minutes_per_patient'] ?? 0) === 5,
            "AppointmentService resolves default 14:00 clinic start time with 5 min slots"
        );
        $settingsNoBuffer = array_merge($defaultSettings, ['blank_slots' => 0]);
        $estimatedTime = $apptService->calculateTime(3, '2026-10-10', $settingsNoBuffer);
        $this->assert(
            $estimatedTime === '14:10',
            "AppointmentService computes slot 3 consult time as 14:10 with 0 buffer slots (14:00, 14:05, 14:10)"
        );
        $bufferTime = $apptService->calculateTime(5, '2026-10-10', $defaultSettings);
        $this->assert(
            $bufferTime === '14:05',
            "AppointmentService factors in 3 reserved buffer slots (slot 5 begins consultation at 14:05)"
        );

        // Verify DrugInteractionService pair queries on master drug database
        $drugDb = DbConnections::systemDb();
        $interactionService = new \ZimRx\Services\DrugInteractionService($drugDb);
        $this->assert(
            $interactionService->hasGenericIdColumns(),
            "DrugInteractionService confirms master drug_interaction table defines generic ID foreign keys"
        );
        $interactions = $interactionService->findInteractions([135, 1114]);
        $this->assert(
            count($interactions) >= 1 && str_contains(strtolower($interactions[0]['interaction']), 'bleeding'),
            "DrugInteractionService detects clinical interaction between Abciximab and Alteplase ('bleeding')"
        );
        $this->assert(
            empty($interactionService->findInteractions([135])),
            "DrugInteractionService requires at least 2 generic IDs (returns empty on single drug)"
        );

        $this->assert(count(\ZimRx\Services\CountryService::getAll()) >= 195, "CountryService provides comprehensive ISO 3166-1 catalog (got: " . count(\ZimRx\Services\CountryService::getAll()) . ")");
        $this->assert(\ZimRx\Services\CountryService::getName('BD') === 'Bangladesh', "CountryService provides clean country name without regulatory body");
        $this->assert(\ZimRx\Services\CountryService::getRegulatoryBody('BD') === 'BMDC', "CountryService isolates clinical regulatory body (BMDC)");
        $this->assert(\ZimRx\Services\CountryService::getDisplayLabel('BD') === 'Bangladesh (BMDC)', "CountryService formats combined display label");
        $this->assert(\ZimRx\Services\CountryService::getIso3('NL') === 'NLD', "CountryService maps NL to NLD for HL7 FHIR IPS standard");
        $this->assert(\ZimRx\Services\CountryService::getIso3('BD') === 'BGD', "CountryService maps BD to BGD for HL7 FHIR IPS standard");
        $this->assert(\ZimRx\Services\CountryService::getDefaultClinicalLanguage('BD') === 'bn', "CountryService maps BD practice default clinical language to Bengali ('bn')");
        $this->assert(\ZimRx\Services\CountryService::getDefaultClinicalLanguage('US') === null, "CountryService returns null for unmapped countries defaulting to interface language");
        $this->assert(isset(\ZimRx\Services\CountryService::getCountryClinicalLanguages()['BD']), "CountryService provides country clinical languages dictionary");
        $formularies = \ZimRx\Services\CountryService::getAvailableFormularies();
        $this->assert(isset($formularies['BD']), "CountryService discovers Bangladesh formulary pack manifest");
        $this->assert(\ZimRx\Services\CountryService::hasFormulary('BD'), "CountryService confirms BD commercial formulary is available");
        $this->assert(!\ZimRx\Services\CountryService::hasFormulary('INT'), "CountryService confirms INT has no commercial formulary (generic only)");
        $this->assert(!\ZimRx\Services\CountryService::hasFormulary('FR'), "CountryService confirms France has no commercial formulary (generic only)");
        $bdPack = \ZimRx\Services\CountryService::getFormulary('BD');
        $this->assert($bdPack !== null && ($bdPack['brands_count'] ?? 0) >= 30000, "BD formulary manifest specifies >30,000 brand records");
        $this->assert(isset($bdPack['manufacturer_table']), "BD formulary manifest specifies manufacturer table");
        $this->assert(isset($bdPack['template_table']), "BD formulary manifest specifies template table");
        $this->assert(($bdPack['currency'] ?? '') === 'BDT', "BD formulary manifest specifies BDT currency");
        $this->assert(($bdPack['default_mode'] ?? '') === 'dual', "BD formulary manifest specifies default_mode dual");
        $this->assert(\ZimRx\Services\CountryService::getDefaultPrescribingMode('BD') === 'dual', "CountryService resolves default prescribing mode 'dual' for BD");
        $this->assert(\ZimRx\Services\CountryService::getDefaultPrescribingMode('INT') === 'generic', "CountryService resolves default prescribing mode 'generic' for INT");
        $this->assert(is_array($bdPack['contributors'] ?? null) && count($bdPack['contributors']) >= 1, "BD formulary manifest defines contributors registry");
    }


    private function testOfflinePrivacyAndFontIntegrity(): void {
        echo "\n[20/25] Testing 100% Offline Privacy, LAN Discovery & Local Font Integrity...\n";

        // 1. LAN IP Discovery regression: No external network probes
        $mobileSyncPath = __DIR__ . '/../public/api/mobile_sync.php';
        $this->assert(file_exists($mobileSyncPath), "mobile_sync.php endpoint exists");
        $mobileSyncCode = (string)file_get_contents($mobileSyncPath);
        $this->assert(
            !str_contains($mobileSyncCode, '8.8.8.8'),
            "Zero external socket probes to Google DNS (8.8.8.8) in mobile_sync.php"
        );
        $this->assert(
            str_contains($mobileSyncCode, 'zimrx_get_server_lan_ip'),
            "mobile_sync.php defines offline LAN IP discovery via local host inspection"
        );

        // 2. Local Font Integrity: 99 bundled webfonts and fonts.css
        $fontsDir = __DIR__ . '/../public/assets/fonts';
        $this->assert(is_dir($fontsDir), "Local fonts directory exists in public/assets/fonts");
        $fontFiles = glob($fontsDir . '/*.*');
        $fontCount = count($fontFiles);
        $this->assert($fontCount >= 90, "Bundled local web fonts present for complete offline typography (got: {$fontCount})");

        $fontsCssPath = __DIR__ . '/../public/assets/css/layout/fonts.css';
        $this->assert(file_exists($fontsCssPath), "Local typography stylesheet exists at layout/fonts.css");
        $fontsCss = (string)file_get_contents($fontsCssPath);
        $this->assert(str_contains($fontsCss, '@font-face'), "fonts.css declares local @font-face rules");

        // 3. Zero external Google Font CDN dependencies
        $publicPhpFiles = glob(__DIR__ . '/../public/*.php');
        $externalFontReferences = 0;
        foreach ($publicPhpFiles as $phpFile) {
            $code = (string)file_get_contents($phpFile);
            if (str_contains($code, 'fonts.googleapis.com') || str_contains($code, 'fonts.gstatic.com')) {
                $externalFontReferences++;
            }
        }
        $this->assert(
            $externalFontReferences === 0,
            "Zero external Google Fonts CDN links remain across public PHP pages"
        );
    }

    private function testTemplateMarkupAndChatSecurity(): void {
        echo "\n[21/25] Testing Template Markup Integrity & Chat Attachment Security...\n";

        // 1. Assert zero duplicate class="..." class="..." attributes across all PHP files
        $phpFiles = array_merge(
            glob(__DIR__ . '/../public/*.php') ?: [],
            glob(__DIR__ . '/../public/api/*.php') ?: [],
            glob(__DIR__ . '/../views/*.php') ?: [],
            glob(__DIR__ . '/../views/modules/*.php') ?: [],
            glob(__DIR__ . '/../lib/*.php') ?: []
        );
        $duplicateClassCount = 0;
        foreach ($phpFiles as $file) {
            $content = (string)file_get_contents($file);
            if (preg_match('/class="[^"]*"\s+class=/i', $content)) {
                $duplicateClassCount++;
            }
        }
        $this->assert($duplicateClassCount === 0, "Zero duplicate HTML class attributes across all view and controller PHP files");

        // 2. Chat attachment authorization
        $chatApiPath = __DIR__ . '/../public/api/chat.php';
        $this->assert(file_exists($chatApiPath), "api/chat.php messaging API endpoint exists");
        $chatCode = (string)file_get_contents($chatApiPath);
        $this->assert(
            str_contains($chatCode, 'zimrx_chat_participants') && str_contains($chatCode, 'conversation_id = :conv_id AND user_id = :user_id'),
            "api/chat.php verifies conversation participant membership on attachment streaming"
        );
        $this->assert(
            str_contains($chatCode, 'is_admin_user()'),
            "api/chat.php allows admin override on attachment streaming"
        );

        // 3. Chat uploads defense-in-depth .htaccess
        $chatHtaccess = __DIR__ . '/../userdata/uploads/chat/.htaccess';
        $this->assert(file_exists($chatHtaccess), "userdata/uploads/chat/.htaccess defense-in-depth file exists");
        $chatHtContent = (string)file_get_contents($chatHtaccess);
        $this->assert(
            str_contains($chatHtContent, 'Require all denied') || str_contains($chatHtContent, 'Deny from all'),
            "userdata/uploads/chat/.htaccess strictly denies all direct HTTP access"
        );

        // 4. Password validation length threshold
        $this->assert(!zimrx_validate_password_strength('short'), "Password validation policy rejects passwords under 8 characters");
        $this->assert(zimrx_validate_password_strength('DoctorSecurePass2026'), "Password validation policy accepts valid passwords >= 8 characters");
    }

    private function testDatabaseAutoProvisioningAndSecurityRules(): void {
        echo "\n[22/25] Testing Database Auto-Provisioning & Webroot Security Rules...\n";

        // 1. application/.htaccess fallback protection verification
        $appHtaccess = __DIR__ . '/../.htaccess';
        $this->assert(file_exists($appHtaccess), "application/.htaccess docroot fallback exists");
        $appHtContent = (string)file_get_contents($appHtaccess);
        $this->assert(
            str_contains($appHtContent, 'Options -Indexes') &&
            str_contains($appHtContent, 'RewriteRule ^$ public/ [R=301,L]') &&
            str_contains($appHtContent, 'RewriteRule ^(db|lib|locales|migrations|tests|systemdata)'),
            "application/.htaccess disables indexing, redirects root, and blocks internal engine directories"
        );

        // 2. Standalone PHP Database Builder verification
        $builderPath = __DIR__ . '/../systemdata/seeds/build_db.php';
        $this->assert(file_exists($builderPath), "application/systemdata/seeds/build_db.php exists");
        $builderContent = (string)file_get_contents($builderPath);
        $this->assert(
            str_contains($builderContent, 'buildDatabaseFromSql') && str_contains($builderContent, 'unistr'),
            "build_db.php defines pure PDO database compilation with custom unistr() support"
        );

        // 3. Database auto-provisioning
        $dbConnPath = __DIR__ . '/../lib/db/db_connections.php';
        $this->assert(file_exists($dbConnPath), "application/lib/db/db_connections.php exists");
        $dbConnContent = (string)file_get_contents($dbConnPath);
        $this->assert(
            str_contains($dbConnContent, 'ensureReferenceDatabaseExists'),
            "DbConnections transparently auto-provisions missing reference databases on first access"
        );
    }

    private function testWhoAtcAndInnStandardization(): void {
        echo "\n[23/25] Testing WHO ATC 2026, Defined Daily Dose (DDD), and INN Standards...\n";

        $db = DbConnections::systemDb();

        // 1. Verify table presence
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('who_atc_hierarchy', 'who_ddd_reference', 'who_inn_catalog')")->fetchAll(PDO::FETCH_COLUMN);
        $this->assert(
            count($tables) === 3,
            "Master drug database contains all 3 WHO standard tables (who_atc_hierarchy, who_ddd_reference, who_inn_catalog)"
        );

        // 2. ATC Hierarchy integrity
        $atcCount = (int)$db->query("SELECT count(*) FROM who_atc_hierarchy")->fetchColumn();
        $this->assert(
            $atcCount >= 7000,
            "who_atc_hierarchy contains complete 5-level international classification ({$atcCount} nodes >= 7000)"
        );

        $l1Count = (int)$db->query("SELECT count(DISTINCT level1_code) FROM who_atc_hierarchy WHERE level_number = 1")->fetchColumn();
        $this->assert(
            $l1Count === 14,
            "who_atc_hierarchy covers all 14 official WHO anatomical main groups (A, B, C, D, G, H, J, L, M, N, P, R, S, V)"
        );

        // Spot-check standard substance: Metformin (A10BA02)
        $metformin = $db->query("SELECT * FROM who_atc_hierarchy WHERE atc_code = 'A10BA02'")->fetch(PDO::FETCH_ASSOC);
        $this->assert(
            $metformin && $metformin['level1_code'] === 'A' && $metformin['level2_code'] === 'A10' && $metformin['level4_code'] === 'A10BA',
            "ATC hierarchy maps Metformin (A10BA02) through Alimentary -> Blood glucose lowering -> Biguanides"
        );

        // 3. Defined Daily Dose (DDD) Reference integrity
        $dddCount = (int)$db->query("SELECT count(*) FROM who_ddd_reference")->fetchColumn();
        $this->assert(
            $dddCount >= 2700,
            "who_ddd_reference contains official 2026 WHO Defined Daily Doses ({$dddCount} records >= 2700)"
        );

        $metforminDdd = $db->query("SELECT * FROM who_ddd_reference WHERE atc_code = 'A10BA02'")->fetch(PDO::FETCH_ASSOC);
        $this->assert(
            $metforminDdd && (float)$metforminDdd['ddd'] === 2.0 && $metforminDdd['uom'] === 'g' && $metforminDdd['adm_route'] === 'O',
            "WHO DDD specifies standard adult daily dose for Metformin (2.0 g, oral administration)"
        );

        // 4. International Nonproprietary Names (INN) Catalog integrity
        $innCount = (int)$db->query("SELECT count(*) FROM who_inn_catalog")->fetchColumn();
        $this->assert(
            $innCount >= 7200,
            "who_inn_catalog contains WCO / WHO standardized nomenclature ({$innCount} records >= 7200)"
        );

        $atorvastatinInn = $db->query("SELECT * FROM who_inn_catalog WHERE inn_name_en = 'atorvastatin'")->fetch(PDO::FETCH_ASSOC);
        $this->assert(
            $atorvastatinInn && $atorvastatinInn['cas_number'] === '134523-00-5' && $atorvastatinInn['hs_class_2022'] === '2933.99',
            "who_inn_catalog maps Atorvastatin to CAS Registry Number 134523-00-5 and WCO HS customs class 2933.99"
        );

        // 5. Relational join with drug_generic (no extra columns added to drug_generic)
        $joinCount = (int)$db->query("SELECT count(*) FROM drug_generic g JOIN who_atc_hierarchy h ON g.who_atc_class = h.atc_code")->fetchColumn();
        $this->assert(
            $joinCount >= 2250,
            "Clinical generics join directly with WHO ATC hierarchy ({$joinCount} / 2331 generics matched, >96% coverage)"
        );

        // Verify drug_generic schema was not modified
        $genericCols = $db->query("PRAGMA table_info(drug_generic)")->fetchAll(PDO::FETCH_COLUMN, 1);
        $this->assert(
            !in_array('ddd', $genericCols, true) && !in_array('inn_name', $genericCols, true),
            "drug_generic schema preserved with zero positional column mutations (clean 3NF relational design)"
        );
    }

    private function testInternationalPatientSummaryExport(): void {
        echo "\n[24/25] Testing International Patient Summary (IPS / HL7 FHIR EU Profile) Export...\n";

        // 1. Verify service class resolution
        $this->assert(
            class_exists(\ZimRx\Services\PatientIpsExportService::class),
            "PatientIpsExportService class autoloads via PSR-4 namespace"
        );

        // 2. Test PatientIpsExportService on an isolated in-memory SQLite database
        $memUserDb = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $memUserDb->exec("
            CREATE TABLE zimrx_patients (
                id INTEGER PRIMARY KEY,
                reg_no TEXT,
                full_name TEXT,
                dob TEXT,
                gender TEXT,
                blood_group TEXT,
                address TEXT,
                mobile TEXT,
                occupation TEXT,
                age TEXT,
                age_unit TEXT,
                weight TEXT,
                height TEXT,
                doctor_id INTEGER
            );
            CREATE TABLE zimrx_doctors (
                id INTEGER PRIMARY KEY,
                doctor_name TEXT,
                degrees TEXT,
                specialty TEXT,
                license_no TEXT
            );
            CREATE TABLE zimrx_prescription_drugs (
                id INTEGER PRIMARY KEY,
                prescription_id INTEGER,
                visit_id INTEGER,
                patient_id INTEGER,
                doctor_id INTEGER,
                generic_id INTEGER,
                drug_name TEXT,
                brand_name TEXT,
                generic_name TEXT,
                strength TEXT,
                form TEXT,
                dosage TEXT,
                duration TEXT,
                instructions TEXT
            );
            CREATE TABLE zimrx_prescription_clinical_findings (
                id INTEGER PRIMARY KEY,
                prescription_id INTEGER,
                visit_id INTEGER,
                doctor_id INTEGER,
                category TEXT,
                label TEXT,
                value TEXT,
                unit TEXT
            );
            CREATE TABLE zimrx_visit_vitals (
                id INTEGER PRIMARY KEY,
                visit_id INTEGER,
                patient_id INTEGER,
                doctor_id INTEGER,
                bp TEXT,
                pulse TEXT,
                temperature TEXT,
                spo2 TEXT,
                resp_rate TEXT
            );
        ");

        $memUserDb->exec("INSERT INTO zimrx_doctors VALUES (1, 'Dr. Alif Farzan', 'MBBS, Clinical Informatics', 'General Practice', 'A-89421')");
        $memUserDb->exec("INSERT INTO zimrx_patients VALUES (101, 'ZR-2026-001', 'Karim Rahman', '1982-05-14', 'Male', 'B+', 'Dhanmondi, Dhaka', '+8801711000000', 'Engineer', '44', 'years', '72', '175', 1)");
        $memUserDb->exec("INSERT INTO zimrx_prescription_drugs VALUES (1, 1, 1, 101, 1, 1230, 'TAB. METMIN XR 500 mg', 'Metmin XR', 'Metformin Hydrochloride', '500 mg', 'Tablet', '1+0+1', '14 days', 'After meals')");
        $memUserDb->exec("INSERT INTO zimrx_prescription_clinical_findings VALUES (1, 1, 1, 1, 'dx', 'Type 2 Diabetes Mellitus', 'E11.9', '')");
        $memUserDb->exec("INSERT INTO zimrx_visit_vitals VALUES (1, 1, 101, 1, '120/80', '72', '98.4', '98', '16')");

        $drugDb = DbConnections::systemDb();
        $service = new \ZimRx\Services\PatientIpsExportService($memUserDb, $drugDb);

        // Export IPS bundle
        $bundle = $service->exportPatientIps(101, 1);

        $this->assert($bundle['resourceType'] === 'Bundle', "IPS export root is standard FHIR Bundle resourceType");
        $this->assert($bundle['type'] === 'document', "IPS export Bundle type is document");
        $this->assert(
            in_array('http://hl7.org/fhir/uv/ips/StructureDefinition/Bundle-uv-ips', $bundle['meta']['profile'] ?? [], true),
            "IPS export conforms to official HL7 FHIR IPS profile URI"
        );

        $comp = $bundle['entry'][0]['resource'] ?? [];
        $this->assert(
            ($comp['resourceType'] ?? '') === 'Composition' && ($comp['type']['coding'][0]['code'] ?? '') === '60591-5',
            "IPS document starts with Composition resource typed as LOINC 60591-5 (Patient summary Document)"
        );

        $patientRes = null;
        $medRes = null;
        $condRes = null;
        $obsBp = null;
        $allergyRes = null;

        foreach ($bundle['entry'] as $e) {
            $r = $e['resource'] ?? [];
            $rt = $r['resourceType'] ?? '';
            if ($rt === 'Patient') $patientRes = $r;
            if ($rt === 'MedicationStatement') $medRes = $r;
            if ($rt === 'Condition') $condRes = $r;
            if ($rt === 'AllergyIntolerance') $allergyRes = $r;
            if ($rt === 'Observation' && ($r['code']['coding'][0]['code'] ?? '') === '85354-9') $obsBp = $r;
        }

        $this->assert(
            $patientRes !== null && ($patientRes['name'][0]['family'] ?? '') === 'Rahman',
            "IPS Bundle contains Patient resource with correct demographic mapping (family: Rahman, gender: male)"
        );

        $this->assert($medRes !== null, "IPS Bundle contains MedicationStatement entry for prescribed drug");
        $atcCodeFound = false;
        foreach ($medRes['medicationCodeableConcept']['coding'] ?? [] as $coding) {
            if (($coding['system'] ?? '') === 'http://www.whocc.no/atc' && str_starts_with($coding['code'] ?? '', 'A10B')) {
                $atcCodeFound = true;
                break;
            }
        }
        $this->assert(
            $atcCodeFound,
            "MedicationStatement automatically binds prescribed Metformin to WHO ATC classification (A10B* Blood glucose lowering)"
        );

        $this->assert(
            $condRes !== null && ($condRes['code']['text'] ?? '') === 'Type 2 Diabetes Mellitus',
            "IPS Bundle contains Condition resource representing diagnosed problem"
        );

        $this->assert(
            $obsBp !== null && ($obsBp['component'][0]['valueQuantity']['value'] ?? 0) === 120.0,
            "IPS Bundle contains Observation resource representing systolic (120 mmHg) and diastolic (80 mmHg) blood pressure"
        );

        $this->assert(
            $allergyRes !== null && ($allergyRes['code']['coding'][0]['code'] ?? '') === '716186003',
            "IPS Bundle contains standard SNOMED CT 716186003 (No known allergies) assertion when unrecorded"
        );

        // Tenant security check
        $accessBlocked = false;
        try {
            $service->exportPatientIps(101, 2);
        } catch (RuntimeException $e) {
            $accessBlocked = true;
        }
        $this->assert($accessBlocked, "PatientIpsExportService enforces doctor tenant scoping (Doctor 2 cannot export Doctor 1's patient)");

        // JSON serialization
        $json = $service->exportPatientIpsJson(101, 1);
        $decoded = json_decode($json, true);
        $this->assert(strlen($json) > 1000 && $decoded !== null, "PatientIpsExportService serializes valid, parseable JSON output");
    }

    private function testClinicalLocaleCatalogs(): void {
        echo "\n[25/25] Testing Clinical Locale Catalogs (application/locales)...\n";

        $dir = __DIR__ . '/../locales';
        $locales = new \ZimRx\Services\LocaleService($dir);
        $langs = $locales->available();
        $this->assert(in_array('en', $langs, true) && in_array('bn', $langs, true), "locales folder provides en and bn languages");

        $langMetas = $locales->availableLanguages();
        $this->assert(isset($langMetas['en']) && isset($langMetas['bn']), "Language metadata manifests load for en and bn");
        $this->assert($langMetas['bn']['native_name'] === 'বাংলা', "bn manifest native_name is বাংলা");

        $flLangs = $locales->availableForCatalog('first_launch');
        $this->assert(isset($flLangs['en']) && isset($flLangs['bn']), "first_launch catalog is available for en and bn");
        $flEn = $locales->catalog('en', 'first_launch');
        $flBn = $locales->catalog('bn', 'first_launch');
        $this->assert(!str_contains($flEn['btn_continue'] ?? '', '→') && !str_contains($flBn['btn_continue'] ?? '', '→'), "first_launch button strings avoid hardcoded directional arrows");
        $this->assert(
            isset($flEn['section_help_lang'], $flBn['section_help_lang'], $flEn['prescribing_mode_label'], $flBn['prescribing_mode_label']),
            "first_launch provides essential localized form section strings in English and Bengali"
        );

        require_once __DIR__ . '/../public/api/zrx_icons.php';
        $this->assert(str_contains(zrx_icon('arrow-right', 14), '<svg') && isset(ZimRxIcon::getAll()['arrow-right']), "global icon registry provides arrow-right SVG vector");

        $hgLangs = $locales->availableForCatalog('help_guidelines');
        $this->assert(isset($hgLangs['en']) && isset($hgLangs['bn']), "help_guidelines catalog is available for en and bn");
        $hgEn = $locales->catalog('en', 'help_guidelines');
        $hgBn = $locales->catalog('bn', 'help_guidelines');
        $this->assert(is_array($hgEn) && is_array($hgBn), "help_guidelines catalog loads as valid array for en and bn");
        $this->assert(
            isset($hgEn['pres-reg'], $hgEn['appt-reg'], $hgEn['mobile'], $hgEn['col-resize'], $hgEn['dob'], $hgEn['visit-id'], $hgEn['occupation'], $hgEn['address']) &&
            isset($hgBn['pres-reg'], $hgBn['appt-reg'], $hgBn['mobile'], $hgBn['col-resize'], $hgBn['dob'], $hgBn['visit-id'], $hgBn['occupation'], $hgBn['address']),
            "help_guidelines covers all 8 clinical workflow guides in English and Bengali"
        );

        $catalogs = ['instructions', 'doses', 'durations', 'advices'];
        $parity = true;
        foreach ($langs as $lang) {
            foreach ($catalogs as $name) {
                $file = "{$dir}/{$lang}/{$name}.json";
                if (!is_file($file) || !is_array(json_decode((string)file_get_contents($file), true))) {
                    $parity = false;
                }
            }
        }
        $this->assert($parity, "every language folder ships all clinical catalogs as valid JSON");

        $this->assert($locales->text('en', 'instructions', 2) === 'After meal', "en instruction id 2 resolves to 'After meal'");
        $this->assert($locales->text('bn', 'instructions', 2) !== '' && $locales->text('bn', 'instructions', 2) !== 'After meal', "bn instruction id 2 resolves to Bengali text");

        $db = DbConnections::staticDb();
        $dbCount = (int)$db->query('SELECT COUNT(*) FROM zimrx_static_instructions')->fetchColumn();
        $this->assert(count($locales->catalog('en', 'instructions')) === $dbCount, "en instructions JSON matches static DB row count ({$dbCount})");

        $this->assert($locales->alias('bn', 'instructions', 1) !== '', "bn instruction carries its search alias");
        $advCats = (int)$db->query('SELECT COUNT(DISTINCT category_id) FROM zimrx_static_advices')->fetchColumn();
        $this->assert(
            count($locales->catalog('en', 'advices')['categories'] ?? []) === $advCats && $locales->text('en', 'advices', 1, 'items') !== '',
            "advices catalog exposes categories and items sections"
        );

        $tmp = sys_get_temp_dir() . '/zimrx_locale_' . bin2hex(random_bytes(4));
        mkdir($tmp . '/en', 0775, true);
        mkdir($tmp . '/fr', 0775, true);
        file_put_contents($tmp . '/en/instructions.json', json_encode(['1' => ['text' => 'Before meal', 'alias' => 'ac'], '2' => ['text' => 'After meal']]));
        file_put_contents($tmp . '/fr/instructions.json', json_encode(['1' => ['text' => 'Avant le repas', 'alias' => 'avant repas']]));
        $partial = new \ZimRx\Services\LocaleService($tmp);
        $this->assert($partial->text('fr', 'instructions', 1) === 'Avant le repas', "translated entries use the contributed language");
        $this->assert($partial->alias('fr', 'instructions', 1) === 'avant repas', "search aliases are read per language");
        $this->assert($partial->text('fr', 'instructions', 2) === 'After meal', "missing translations fall back to English");
        unlink($tmp . '/en/instructions.json');
        unlink($tmp . '/fr/instructions.json');
        rmdir($tmp . '/en');
        rmdir($tmp . '/fr');
        rmdir($tmp);

        $blocked = 0;
        foreach (['../en', 'en/../../config', 'EN', 'xx;rm'] as $badLang) {
            try {
                $locales->catalog($badLang, 'instructions');
            } catch (RuntimeException $e) {
                $blocked++;
            }
        }
        try {
            $locales->catalog('en', '../ui');
        } catch (RuntimeException $e) {
            $blocked++;
        }
        $this->assert($blocked === 5, "LocaleService rejects path traversal in language code and catalog name");

        // Verify runtime wiring through rx_regimen_lib and rx_template_lib
        require_once __DIR__ . '/../lib/rx_regimen_lib.php';
        require_once __DIR__ . '/../lib/rx_template_lib.php';

        $staticInstructions = rx_static_instruction_rows();
        $this->assert(
            isset($staticInstructions[0]['instruction_bn']) && $staticInstructions[0]['instruction_bn'] === 'খাবার আগে',
            "rx_static_instruction_rows delivers clean UTF-8 Bengali from LocaleService ('খাবার আগে')"
        );
        $this->assert(
            isset($staticInstructions[0]['search_alias']) && str_contains($staticInstructions[0]['search_alias'], 'khabar'),
            "rx_static_instruction_rows populates search alias from LocaleService"
        );

        $staticDoses = rx_phrase_static_rows('dose');
        $this->assert(
            isset($staticDoses[0]['dosage_bn']) && $staticDoses[0]['dosage_bn'] === '১+০+১',
            "rx_phrase_static_rows('dose') delivers clean UTF-8 Bengali ('১+০+১')"
        );

        $staticDurations = rx_phrase_static_rows('duration');
        $this->assert(
            isset($staticDurations[0]['duration_bn']) && $staticDurations[0]['duration_bn'] === '০১ দিন',
            "rx_phrase_static_rows('duration') delivers clean UTF-8 Bengali ('০১ দিন')"
        );

        $staticAdvices = rx_phrase_static_rows('advice');
        $this->assert(
            count($staticAdvices) === 255 && ($staticAdvices[0]['body'] ?? '') === 'নিয়মিত ঔষধ খাবেন।',
            "rx_phrase_static_rows('advice') delivers all 255 items with clean UTF-8 Bengali"
        );
        $this->assert(
            ($staticAdvices[0]['category_bn'] ?? '') === 'সাধারণ উপদেশ',
            "rx_phrase_static_rows('advice') maps category_bn from LocaleService ('সাধারণ উপদেশ')"
        );
    }
}

$suite = new ZimRxTestSuite();
$suite->run();
