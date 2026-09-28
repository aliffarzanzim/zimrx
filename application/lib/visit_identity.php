<?php
declare(strict_types=1);

/**
 * Fail clearly if migration 014 has not applied the visit-identity schema.
 * All column additions and renames are owned by migration 014; no DDL
 * is executed at request time.
 */
function zimrx_ensure_visit_identity_schema(PDO $pdo): void {
    if (!DbSchema::tableExists($pdo, 'zimrx_appointments') ||
        !DbSchema::columnExists($pdo, 'zimrx_appointments', 'vitals_note')) {
        throw new RuntimeException(
            'Visit-identity schema is incomplete. ' .
            'Run database migrations before serving clinical requests.'
        );
    }
}
