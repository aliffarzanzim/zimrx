<?php
declare(strict_types=1);

// Runs pending migration scripts on startup and tracks applied versions.
class DbMigrator {

    private string $migrationsDir;

    public function __construct(?string $migrationsDir = null) {
        $this->migrationsDir = $migrationsDir ?? dirname(__DIR__) . '/migrations';
    }

    public function run(PDO $pdo): void {
        $this->ensureMigrationsTable($pdo);
        $installed = $this->getInstalledVersions($pdo);
        $pending   = $this->discoverMigrations($installed);

        foreach ($pending as $version => $file) {
            require_once $file;
            $class     = $this->fileToClassName($file);
            $migration = new $class();

            try {
                // SQLite: lock immediately so concurrent web requests don't both run the migration
                $pdo->exec('BEGIN IMMEDIATE');
                $migration->up($pdo);
                $this->markInstalled($pdo, $version);
                $pdo->exec('COMMIT');
            } catch (Throwable $e) {
                try {
                    $pdo->exec('ROLLBACK');
                } catch (Throwable $rbEx) {
                }
                throw new RuntimeException(
                    "Migration $version failed: " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }
    }

    public function getInstalledVersions(PDO $pdo): array {
        $this->ensureMigrationsTable($pdo);
        $stmt = $pdo->query("SELECT version FROM schema_migrations ORDER BY version ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

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

    private function ensureMigrationsTable(PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(255) NOT NULL,
                applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (version)
            )"
        );
    }

    private function markInstalled(PDO $pdo, string $version): void {
        $stmt = $pdo->prepare(
            "INSERT INTO schema_migrations (version, applied_at)
             VALUES (:version, CURRENT_TIMESTAMP)"
        );
        $stmt->execute(['version' => $version]);
    }

    private function fileToVersion(string $file): string {
        $base = basename($file, '.php');
        preg_match('/^(\d+)/', $base, $m);
        return $m[1] ?? $base;
    }

    // Convention: 001_initial_tables.php -> Migration001InitialTables
    private function fileToClassName(string $file): string {
        $base  = basename($file, '.php');
        $parts = explode('_', $base);
        return 'Migration' . implode('', array_map('ucfirst', $parts));
    }
}


