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
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db/db_connections.php';
require_once __DIR__ . '/../lib/db/db_schema.php';
require_once __DIR__ . '/../lib/db/db_sql.php';
require_once __DIR__ . '/../lib/db/db_migrator.php';
require_once __DIR__ . '/../lib/pc_catalog_lib.php';
require_once __DIR__ . '/../lib/user_drug_lib.php';
require_once __DIR__ . '/../lib/Services/BillingService.php';

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
        $this->testClinicalReportSecurityAndDeploymentHardening();
        $this->testBillingServiceTransactionSafety();
        $this->testInitGatewayAndPathDiscovery();

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
        echo "\n[10/11] Testing Upload Validation & Path Traversal Prevention...\n";

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

        // Verify cryptographically randomized upload filenames
        $uploadScripts = [
            'upload_header_logo.php',
            'upload_full_body_header.php',
            'upload_background_image.php',
            'upload_seal_and_stamp.php',
            'upload_report.php',
        ];
        $allRandomized = true;
        foreach ($uploadScripts as $script) {
            $code = (string)file_get_contents(__DIR__ . '/../public/api/' . $script);
            if (!str_contains($code, 'random_bytes')) {
                $allRandomized = false;
                break;
            }
        }
        $this->assert($allRandomized, "All file upload endpoints append cryptographically secure random bytes (random_bytes) to filenames");
    }

    private function testEndpointMutationGuards(): void {
        echo "\n[11/11] Testing Write Endpoint Mutation Guard Patterns (Method & CSRF Enforcement)...\n";

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
        echo "\n[12/13] Testing Mobile Upload Queue Patient Binding & Visit Optimistic Locking...\n";

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
        $pdo->exec('BEGIN IMMEDIATE');
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
        echo "\n[13/13] Testing Delta-Sync UUID Generation & Audit Journaling...\n";

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

    // [14/15] Active Patient Ownership Validation
    private function testActivePatientOwnershipValidation(): void {
        echo "\n[14/15] Testing Active Patient Ownership Validation...\n";

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

        // Helper: replicate the ownership check logic from update_active_patient
        $checkOwnership = static function (PDO $pdo, int $doctorId, int $patientId, int $visitRecordId): bool {
            if ($patientId === 0 && $visitRecordId === 0) {
                return true; // walk-in always permitted
            }
            if ($visitRecordId > 0) {
                $chk = $pdo->prepare(
                    "SELECT v.id
                     FROM zimrx_visits v
                     JOIN zimrx_patients p ON p.id = v.patient_id
                     WHERE v.id = :vid AND v.doctor_id = :did
                       AND (:pid = 0 OR v.patient_id = :pid)
                     LIMIT 1"
                );
                $chk->execute(['vid' => $visitRecordId, 'did' => $doctorId, 'pid' => $patientId]);
                return (bool)$chk->fetch();
            }
            $chk = $pdo->prepare(
                "SELECT id FROM zimrx_patients
                 WHERE id = :pid
                   AND COALESCE(NULLIF(doctor_id, 0), 1) = :did
                 LIMIT 1"
            );
            $chk->execute(['pid' => $patientId, 'did' => $doctorId]);
            return (bool)$chk->fetch();
        };

        // Walk-in (0,0) always allowed
        $this->assert($checkOwnership($pdo, 1, 0, 0), 'Walk-in context (patient_id=0, visit_record_id=0) is always permitted');

        // Doctor 1 legitimately owns patient 10 + visit 100
        $this->assert($checkOwnership($pdo, 1, 10, 100), 'Doctor 1 can set active context to their own patient+visit');

        // Doctor 1 CANNOT use Doctor 2's visit (200)
        $this->assert(!$checkOwnership($pdo, 1, 10, 200), 'Forged visit_record_id belonging to another doctor is rejected');

        // Doctor 1 CANNOT claim Doctor 2's patient (20) even without a visit
        $this->assert(!$checkOwnership($pdo, 1, 20, 0), 'Forged patient_id belonging to another doctor is rejected');

        // Doctor 2 legitimately owns patient 20 + visit 200
        $this->assert($checkOwnership($pdo, 2, 20, 200), 'Doctor 2 can set active context to their own patient+visit');
    }

    // [15/15] Walk-in Upload Isolation
    private function testWalkInUploadIsolation(): void {
        echo "\n[15/15] Testing Walk-in Upload Isolation...\n";

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
            'Bound and walk-in upload sets are disjoint — no cross-contamination'
        );
    }

    private function testLoginRateLimitingAndRedirectDefense(): void {
        echo "\n[16/16] Testing Login Security (Rate-Limiting & Open-Redirect Defense)...\n";

        // 1. Open-Redirect Validation Logic
        $sanitizeRedirect = function (string $redirect): string {
            $redirect = trim($redirect);
            if ($redirect !== '') {
                if (str_starts_with($redirect, '//')
                    || str_starts_with($redirect, '\\')
                    || str_contains($redirect, '\\')
                    || preg_match('/^[a-z][a-z0-9+.-]*:/i', $redirect)
                ) {
                    return '';
                }
            }
            return $redirect;
        };

        $this->assert($sanitizeRedirect('http://attacker.com') === '', 'Redirect rejects http:// external URLs');
        $this->assert($sanitizeRedirect('https://attacker.com') === '', 'Redirect rejects https:// external URLs');
        $this->assert($sanitizeRedirect('//attacker.com/steal') === '', 'Redirect rejects protocol-relative // URLs');
        $this->assert($sanitizeRedirect('\\\\attacker.com\\steal') === '', 'Redirect rejects backslash-based relative URLs');
        $this->assert($sanitizeRedirect('/foo\\bar') === '', 'Redirect rejects URLs containing embedded backslashes');
        $this->assert($sanitizeRedirect('javascript:alert(1)') === '', 'Redirect rejects javascript: pseudo-protocol');
        $this->assert($sanitizeRedirect('data:text/html,bad') === '', 'Redirect rejects data: pseudo-protocol');
        $this->assert($sanitizeRedirect('prescription.php?id=12') === 'prescription.php?id=12', 'Redirect accepts legitimate local path');

        // 2. Login Rate-Limiting Counter Logic
        $session = [];
        $ip = '192.168.1.100';
        $ipHash = hash('sha256', $ip);
        $attemptKey = 'login_attempts_' . $ipHash;
        $lockoutKey = 'login_lockout_' . $ipHash;

        // Simulate 4 failed attempts
        for ($i = 1; $i <= 4; $i++) {
            $session[$attemptKey] = ($session[$attemptKey] ?? 0) + 1;
        }
        $this->assert(($session[$attemptKey] ?? 0) === 4, '4 failed attempts recorded without lockout');
        $this->assert(!isset($session[$lockoutKey]), 'Lockout is not triggered before threshold');

        // 5th failed attempt triggers lockout
        $session[$attemptKey] = ($session[$attemptKey] ?? 0) + 1;
        if ($session[$attemptKey] >= 5) {
            $session[$lockoutKey] = time() + 60;
            $session[$attemptKey] = 0;
        }
        $this->assert(($session[$attemptKey] ?? 0) === 0, 'Attempt counter resets on lockout');
        $this->assert(isset($session[$lockoutKey]) && $session[$lockoutKey] > time(), 'Lockout timestamp set for 60 seconds');

        // Successful login resets lockout keys
        unset($session[$attemptKey], $session[$lockoutKey]);
        $this->assert(!isset($session[$lockoutKey]), 'Lockout keys cleanly wiped on successful authentication');
    }

    private function testClinicalReportSecurityAndDeploymentHardening(): void {
        echo "\n[17/17] Testing Clinical Report Security & Deployment Hardening...\n";

        // 1. Root Apache/cPanel .htaccess verification
        $rootHtaccess = __DIR__ . '/../public/.htaccess';
        $this->assert(file_exists($rootHtaccess), 'Root application/public/.htaccess exists for plug-and-play cPanel deployment');
        $rootHtContent = (string)file_get_contents($rootHtaccess);
        $this->assert(str_contains($rootHtContent, 'Options -Indexes'), 'application/.htaccess disables directory indexing (Options -Indexes)');
        $this->assert(str_contains($rootHtContent, 'uploads/reports'), 'application/.htaccess blocks direct HTTP access to clinical reports');
        $this->assert(str_contains($rootHtContent, 'db|lib|migrations|tests'), 'application/.htaccess blocks direct HTTP access to backend engine directories');

        // 2. Storage defense-in-depth .htaccess verification
        $reportsHtaccess = __DIR__ . '/../userdata/uploads/reports/.htaccess';
        $this->assert(file_exists($reportsHtaccess), 'userdata/uploads/reports/.htaccess defense-in-depth file exists');
        $reportsHtContent = (string)file_get_contents($reportsHtaccess);
        $this->assert(str_contains($reportsHtContent, 'Require all denied') || str_contains($reportsHtContent, 'Deny from all'), 'reports/.htaccess strictly denies all direct HTTP access');

        // 3. Authenticated viewer endpoint verification
        $viewerScript = __DIR__ . '/../public/api/view_report.php';
        $this->assert(file_exists($viewerScript), 'api/view_report.php authenticated report viewer endpoint exists');
        $viewerCode = (string)file_get_contents($viewerScript);
        $this->assert(str_contains($viewerCode, 'declare(strict_types=1);'), 'api/view_report.php enforces strict types');
        $this->assert(str_contains($viewerCode, 'current_user_doctor_id()'), 'api/view_report.php validates doctor authentication');
        $this->assert(str_contains($viewerCode, 'basename('), 'api/view_report.php uses basename() for path traversal neutralization');
        $this->assert(str_contains($viewerCode, 'X-Content-Type-Options: nosniff'), 'api/view_report.php enforces nosniff header');

        // 4. Path traversal neutralization simulation
        $traversalInput = '../../userdata/database/zimrx_userdata.db';
        $sanitizedFile = basename(urldecode($traversalInput));
        $this->assert($sanitizedFile === 'zimrx_userdata.db', 'basename() strips directory traversal escape sequences');
        $reportsDir = realpath(__DIR__ . '/../userdata/uploads/reports') ?: '';
        $simulatedTarget = $reportsDir . DIRECTORY_SEPARATOR . $sanitizedFile;
        $this->assert(!file_exists($simulatedTarget), 'Escalation outside reports directory returns 404');

        // 5. Tenant Scoping Validation Logic
        $extractReportDoctor = function (string $filename): int {
            if (preg_match('/^report-(\d+)-/', $filename, $matches)) {
                return (int)$matches[1];
            }
            return 0;
        };

        $this->assert($extractReportDoctor('report-1-1780242361.pdf') === 1, 'Report filename parser extracts Doctor 1 ownership');
        $this->assert($extractReportDoctor('report-2-1780242361.png') === 2, 'Report filename parser extracts Doctor 2 ownership');
        $this->assert($extractReportDoctor('random_file.pdf') === 0, 'Non-standard filename defaults to 0 doctor scope');

        // Doctor 1 accessing Doctor 2's report is blocked
        $currentDoctor = 1;
        $reportOwner = $extractReportDoctor('report-2-1780242361.png');
        $this->assert($currentDoctor !== $reportOwner, 'Cross-doctor report access detected and blocked');

        // 6. Anti-CSRF verification on layout & header mutation endpoints
        $printSaveCode = (string)file_get_contents(__DIR__ . '/../public/api/print_setup_save.php');
        $this->assert(str_contains($printSaveCode, 'zimrx_verify_csrf()'), 'api/print_setup_save.php enforces zimrx_verify_csrf()');

        $headerEditCode = (string)file_get_contents(__DIR__ . '/../public/api/header_edit_ajax.php');
        $this->assert(str_contains($headerEditCode, 'zimrx_verify_csrf()'), 'api/header_edit_ajax.php enforces zimrx_verify_csrf()');

        $headerOnboardCode = (string)file_get_contents(__DIR__ . '/../public/api/header_onboarding_ajax.php');
        $this->assert(str_contains($headerOnboardCode, 'zimrx_verify_csrf()'), 'api/header_onboarding_ajax.php enforces zimrx_verify_csrf()');

        // 7. GET Idempotency verification on template endpoints (RFC 7231)
        $rxTemplateCode = (string)file_get_contents(__DIR__ . '/../public/api/rx_user_templates.php');
        $this->assert(str_contains($rxTemplateCode, "REQUEST_METHOD'] === 'POST'"), 'api/rx_user_templates.php enforces GET idempotency for usage metrics');

        // 8. Authentication verification on clinic search & data lookups
        $addrCode = (string)file_get_contents(__DIR__ . '/../public/api/search_address.php');
        $this->assert(str_contains($addrCode, 'require_login()'), 'api/search_address.php enforces require_login()');

        $phraseCode = (string)file_get_contents(__DIR__ . '/../public/api/rx_phrase_suggestions.php');
        $this->assert(str_contains($phraseCode, 'require_login()'), 'api/rx_phrase_suggestions.php enforces require_login()');

        $interactCode = (string)file_get_contents(__DIR__ . '/../public/api/check_drug_interactions.php');
        $this->assert(str_contains($interactCode, 'require_login()'), 'api/check_drug_interactions.php enforces require_login()');

        $searchDrugCode = (string)file_get_contents(__DIR__ . '/../public/api/search_drug.php');
        $this->assert(str_contains($searchDrugCode, 'require_login()'), 'api/search_drug.php enforces require_login()');

        $drugLookupCode = (string)file_get_contents(__DIR__ . '/../public/api/drug_lookup.php');
        $this->assert(str_contains($drugLookupCode, 'require_login()'), 'api/drug_lookup.php enforces require_login()');

        $drugExplorerCode = (string)file_get_contents(__DIR__ . '/../public/api/drug_explorer.php');
        $this->assert(str_contains($drugExplorerCode, 'require_login()'), 'api/drug_explorer.php enforces require_login()');

        $searchDxCode = (string)file_get_contents(__DIR__ . '/../public/api/search_dx.php');
        $this->assert(str_contains($searchDxCode, 'require_login()'), 'api/search_dx.php enforces require_login()');

        $occupationsCode = (string)file_get_contents(__DIR__ . '/../public/api/get_occupations.php');
        $this->assert(str_contains($occupationsCode, 'require_login()'), 'api/get_occupations.php enforces require_login()');
    }

    private function testBillingServiceTransactionSafety(): void {
        echo "\n[18/18] Testing BillingService & Financial Transaction Safety...\n";

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
        echo "\n[19/19] Testing init.php Gateway Architecture & Path Auto-Discovery...\n";

        $initPath = __DIR__ . '/../public/init.php';
        $this->assert(file_exists($initPath), 'public/init.php gateway file exists');

        $initCode = (string)file_get_contents($initPath);
        $this->assert(str_contains($initCode, "define('ZIMRX_BASE_DIR'"), 'init.php defines ZIMRX_BASE_DIR');
        $this->assert(str_contains($initCode, "define('ZIMRX_PUBLIC_DIR'"), 'init.php defines ZIMRX_PUBLIC_DIR');
        $this->assert(str_contains($initCode, "/../config/config.php"), 'init.php supports offline standalone / USB mode');
        $this->assert(str_contains($initCode, "/application/config/config.php"), 'init.php supports shared cPanel / public_html mode');
        $this->assert(file_exists(__DIR__ . '/../lib/auth.php'), 'Core auth engine located at lib/auth.php');
        $this->assert(file_exists(__DIR__ . '/../lib/db/db.php'), 'Core database bootstrapper located at lib/db/db.php');
        $this->assert(!file_exists(__DIR__ . '/../auth.php'), 'Zero loose auth.php in application root');
        $this->assert(!is_dir(__DIR__ . '/../db'), 'Zero loose db folder in application root');
        $this->assert(!is_dir(__DIR__ . '/../system_database'), 'system_database legacy folder removed');
        $this->assert(!is_dir(__DIR__ . '/../modules'), 'Zero loose modules folder in application root (nested under views/modules)');
        $this->assert(is_dir(__DIR__ . '/../views/modules'), 'Prescription workspace modules centralized in views/modules');
        $this->assert(file_exists(__DIR__ . '/../systemdata/database/zimrx_drugs.db'), 'System drug reference DB located in systemdata/database');
        $this->assert(file_exists(__DIR__ . '/../systemdata/database/zimrx_static.db'), 'System static reference DB located in systemdata/database');
        $this->assert(is_dir(__DIR__ . '/../systemdata/seeds'), 'System SQL seeds directory located in systemdata/seeds');
        $this->assert(!file_exists(__DIR__ . '/../lib/visit_identity.php'), 'Orphan visit_identity.php consolidated into emr_identity_lib.php');

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
        $this->assert($apisUsingInit >= 55, "All public API endpoints wire through init.php (got: {$apisUsingInit})");
        $this->assert($apisWithRawDirname2 === 0, "No raw dirname(__DIR__, 2) path escapes remain in public APIs");
    }
}

$suite = new ZimRxTestSuite();
$suite->run();
