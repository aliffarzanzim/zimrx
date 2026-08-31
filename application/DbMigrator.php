<?php
/**
 * DbMigrator — Versioned schema migration runner
 *
 * Replaces PRAGMA user_version + scattered inline DDL with a proper
 * numbered migration system that works on any supported PDO driver.
 *
 * ## How It Works
 *
 * 1. Ensures a `schema_migrations` table exists in the database.
 * 2. Scans `application/migrations/` for files named NNN_*.php.
 * 3. Skips any migration already recorded in `schema_migrations`.
 * 4. Runs pending migrations in numeric order, each wrapped in a transaction.
 * 5. Records the migration version on success.
 *
 * ## Migration File Convention
 *
 *   File:  application/migrations/001_initial_userdata_tables.php
 *   Class: Migration001InitialUserdataTables
 *   Method: public function up(PDO $pdo): void { ... }
 *
 * Class names are derived automatically from the filename.
 *
 * ## Usage (in db.php boot)
 *
 *   (new DbMigrator())->run($pdo);
 *
 * Requires:
 *   - application/DbConnections.php  (for driver awareness)
 *   - application/DbSql.php          (dialect helpers used inside migrations)
 *   - application/DbSchema.php       (introspection used inside migrations)
 */
class DbMigrator {

    /** @var string Directory containing migration files */
    private string $migrationsDir;

    public function __construct(?string $migrationsDir = null) {
        $this->migrationsDir = $migrationsDir ?? __DIR__ . '/migrations';
    }

    // ----------------------------------------------------------------
    // Public API
    // ----------------------------------------------------------------

    /**
     * Run all pending migrations against $pdo.
     * Safe to call on every boot — already-applied migrations are skipped.
     */
    public function run(PDO $pdo): void {
        $this->ensureMigrationsTable($pdo);
        $installed = $this->getInstalledVersions($pdo);
        $pending   = $this->discoverMigrations($installed);

        foreach ($pending as $version => $file) {
            require_once $file;
            $class     = $this->fileToClassName($file);
            $migration = new $class();

            $pdo->beginTransaction();
            try {
                $migration->up($pdo);
                $this->markInstalled($pdo, $version);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw new RuntimeException(
                    "Migration $version failed: " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }
    }

    /**
     * Return all installed migration versions.
     * @return string[]
     */
    public function getInstalledVersions(PDO $pdo): array {
        $this->ensureMigrationsTable($pdo);
        $stmt = $pdo->query("SELECT version FROM schema_migrations ORDER BY version ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * Return all available migration files (version => filepath), excluding installed ones.
     * @param string[] $installed Already-installed versions to skip.
     * @return array<string, string>
     */
    public function discoverMigrations(array $installed = []): array {
        if (!is_dir($this->migrationsDir)) {
            return [];
        }

        $files    = glob($this->migrationsDir . '/[0-9][0-9][0-9]_*.php') ?: [];
        $pending  = [];

        foreach ($files as $file) {
            $version = $this->fileToVersion($file);
            if (!in_array($version, $installed, true)) {
                $pending[$version] = $file;
            }
        }

        ksort($pending);
        return $pending;
    }

    // ----------------------------------------------------------------
    // Internal helpers
    // ----------------------------------------------------------------

    /**
     * Create the schema_migrations table if it does not already exist.
     * Uses a cross-driver compatible CREATE TABLE IF NOT EXISTS.
     */
    private function ensureMigrationsTable(PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(255) NOT NULL,
                applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (version)
            )"
        );
    }

    /**
     * Record a migration version as applied.
     */
    private function markInstalled(PDO $pdo, string $version): void {
        $stmt = $pdo->prepare(
            "INSERT INTO schema_migrations (version, applied_at)
             VALUES (:version, CURRENT_TIMESTAMP)"
        );
        $stmt->execute(['version' => $version]);
    }

    /**
     * Derive the numeric version string from a migration filename.
     * e.g. "001_initial_userdata_tables.php" → "001"
     */
    private function fileToVersion(string $file): string {
        $base = basename($file, '.php');
        preg_match('/^(\d+)/', $base, $m);
        return $m[1] ?? $base;
    }

    /**
     * Derive the PHP class name from a migration filename.
     *
     * Convention: NNN_some_snake_case_name.php → MigrationNNNSomeSnakeCaseName
     *
     * e.g. "001_initial_userdata_tables.php" → Migration001InitialUserdataTables
     */
    private function fileToClassName(string $file): string {
        $base  = basename($file, '.php');                      // 001_initial_userdata_tables
        $parts = explode('_', $base);                          // ['001','initial','userdata','tables']
        $class = 'Migration' . implode('', array_map('ucfirst', $parts));
        return $class;
    }
}
