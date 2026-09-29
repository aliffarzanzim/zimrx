<?php
declare(strict_types=1);

// Asserts that visit-identity and intake vitals columns from migration 014 exist before handling requests.
function zimrx_ensure_visit_identity_schema(PDO $pdo): void {
    if (!DbSchema::tableExists($pdo, 'zimrx_appointments') ||
        !DbSchema::columnExists($pdo, 'zimrx_appointments', 'vitals_note')) {
        throw new RuntimeException(
            'Visit-identity schema is incomplete. ' .
            'Run database migrations before serving clinical requests.'
        );
    }
}
