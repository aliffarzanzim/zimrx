# ZimRx `lib/` Architecture & Developer Guide

`lib/` contains the core business logic, database abstraction, security infrastructure, and service layers for ZimRx.

---

## 1. Architectural Design & Philosophy

ZimRx is built as an offline-first, local-first Electronic Medical Record (EMR) and digital prescription system. It is designed to run in environments ranging from a solo doctor's laptop running FrankenPHP to shared cPanel hosting in low-resource clinic networks.

### Why Micro-Endpoints Over Bulky MVC Frameworks?
- **Zero Framework Overhead:** No heavy dependency tree (Laravel, Symfony) that can break during offline updates or require shell access (Composer / Artisan) in restrictive shared environments.
- **Direct Entry Points:** Each public script in `public/` acts as an independent controller wired through `init.php`. This allows isolated debugging, granular security scoping, and direct HTTP execution.
- **Portability:** Moving `application/` to `public_html` or packaging it into a standalone portable folder works out of the box with zero build step.

---

## 2. Database Architecture (`lib/db/`)

All database interactions across the entire codebase flow through a centralized PDO singleton manager. Raw `new PDO()` calls are strictly prohibited.

### Centralized Connection Manager (`DbConnections`)
`DbConnections` provides singleton access to the three segregated database domains:

```php
// Patient records, visits, appointments, settings, and billing
$pdoUser = DbConnections::userdata();

// Static reference data: clinical frequencies, doses, instructions
$pdoStatic = DbConnections::staticDb();

// Master drug reference catalog and interactions
$pdoSystem = DbConnections::systemDb();
```

#### Why Direct `new PDO()` is Prohibited:
1. **Connection Pooling & Concurrency:** Guarantees a single open handle per database per request, configuring SQLite `busy_timeout` (5000ms) and WAL (Write-Ahead Logging) mode consistently.
2. **Pluggable Database Drivers:** Centralizes database configuration. Switching from SQLite to MariaDB, MySQL, or PostgreSQL requires updating `DbConnections::configure()` without touching application query logic.
3. **Audit & Safety:** Enforces `PDO::ERRMODE_EXCEPTION` and `PDO::ATTR_DEFAULT_FETCH_MODE = PDO::FETCH_ASSOC` uniformly across all endpoints.

### Schema Migrations (`DbMigrator`)
- Database migrations live in `migrations/` as numbered SQL files (`001_initial_schema.sql`, `002_add_appointments.sql`, etc.).
- On application bootstrap, `DbMigrator::run($pdo)` checks the `zimrx_migrations` table and applies unapplied migrations inside an atomic transaction.
- When performing CLI batch operations or test setups, define `ZIMRX_DB_LIGHTWEIGHT` to skip automatic runtime migration checks.

---

## 3. Security & Authentication (`lib/auth.php`)

ZimRx manages confidential patient health information and clinical histories. The security layer enforces strict boundary protections:

### Network Encryption Enforcement (LAN vs Localhost)
- **Local Loopback (`127.0.0.1`, `::1`):** Unencrypted HTTP is permitted for local desktop development or single-machine clinic usage.
- **Network / LAN Connections:** Non-loopback requests strictly require HTTPS (TLS). Plain HTTP access over the network is rejected with HTTP 403 to prevent unencrypted clinical data transmission across clinic Wi-Fi.

### Session & Cookie Security
- Sessions use `SameSite=Lax`, `httponly=true`, and dynamic `secure` flags (active when HTTPS is detected).
- Multi-role authorization helpers (`current_user_role()`, `current_user_doctor_id()`, `is_admin_user()`) strictly validate authenticated session state.
- Unauthenticated requests default `current_user_doctor_id()` to `0`, preventing accidental tenant fallback.

### CSRF Protection
All mutating operations (POST, PUT, DELETE) must pass `zimrx_verify_csrf()` validation. CSRF tokens are cryptographically generated per session and verified via constant-time string comparison (`hash_equals`).

---

## 4. Object-Oriented Services Layer (`lib/Services/`)

Complex multi-table operations, transactional business rules, and synchronization workflows are organized into PSR-4 service classes under the `ZimRx\Services` namespace.

| Service | Primary Responsibility |
|---|---|
| `BillingService` | Invoicing, payment collection ledger, and appointment fee reconciliation. |
| `AppointmentService` | Appointment scheduling, queue management, status workflows, and doctor isolation. |
| `SyncJournalService` | Append-only delta sync journal logging with RFC 4122 v4 UUIDs for distributed offline replication. |
| `DrugInteractionService` | Master drug interaction verification and severity scoring. |
| `PrintLayoutService` | Prescription layout geometry, margin calculations, and letterhead positioning. |

### Usage Example
```php
use ZimRx\Services\BillingService;

$billingService = new BillingService(DbConnections::userdata());
$receiptNumber = $billingService->recordPayment([
    'doctor_id'   => $doctorId,
    'patient_id'  => $patientId,
    'gross_fee'   => 1000.00,
    'discount'    => 100.00,
    'paid_amount' => 900.00,
]);
```

---

## 5. Procedural Domain Libraries (`lib/*.php`)

Core calculation helpers, clinical templates, and catalog lookups are implemented as procedural functions with explicit prefix naming:

| Library File | Prefix Convention | Primary Responsibility |
|---|---|---|
| `admin_lib.php` | `zimrx_admin_*` | Doctor and assistant account provisioning and password hashing. |
| `drug_catalog_lib.php` | `drug_catalog_*` | Full-text search and catalog queries across master pharmaceutical database. |
| `emr_identity_lib.php` | `zimrx_get_emr_*` | Monotonic visit sequence generation, formatted registration IDs, and patient context verification. |
| `medical_history_lib.php` | `zimrx_mh_*` | Past medical history questionnaires and categorical templates. |
| `particulars_audit_lib.php` | `zimrx_pa_*` | Patient demographic audit lookups, phone formatting, and duplicate checks. |
| `pc_catalog_lib.php` | `pc_*` | Presenting complaints classification, frequency ordering, and autocomplete lookups. |
| `physical_examination_lib.php` | `zimrx_pe_*` | Organ systems examination parameters and clinical findings options. |
| `print_setup_lib.php` | `zimrx_print_*` | Printable HTML prescription layout builder and CSS canvas configuration. |
| `rx_regimen_lib.php` | `rx_*` | Prescription regimen persistence, dose formatting, and duration normalization. |
| `rx_template_lib.php` | `zimrx_rx_template_*` | Doctor-specific prescription presets and advice templates. |
| `user_drug_lib.php` | `zimrx_user_drug_*` | Doctor custom drug additions and localized generic brand overrides. |

### Coding Standards in `lib/`:
1. Every file starts with `declare(strict_types=1);`.
2. Functions accept `PDO` explicitly as a parameter rather than relying on global scope.
3. Prepared statements with named parameters are required for all SQL queries.
4. Standard hyphen `-` or colon `:` punctuation is used in all docs and comments (no em dashes).

---

## 6. Extension Guidelines

When adding new functionality to `lib/`:

- **Use `lib/Services/`** if the feature involves multi-step transactions, coordinates multiple tables, manages queues/sync state, or benefits from object-oriented encapsulation and dependency injection.
- **Use `lib/*.php`** if the feature provides pure clinical calculations, format transformations, template snippet rendering, or lightweight catalog queries.
- **Never bypass `DbConnections`** to connect to database files directly.
