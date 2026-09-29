# ZimRx `lib/` - Service Library

## Design Rationale

ZimRx is a **zero-dependency, plug-and-play EMR** designed to run on shared cPanel hosting without a framework requirement. `lib/` contains the application's business-logic layer as procedural PHP functions.

### Why procedural, not OOP classes?

All functions use the `zimrx_` prefix as a de-facto namespace - the same convention used by WordPress (`wp_`), Drupal (`drupal_`), and other mature open-source PHP projects that must run on any host without autoloaders or framework bootstrapping.

- Every function is individually `require_once`-able from any entry point.
- No autoloader configuration is needed, so copying `application/` to `public_html` works with zero setup.
- All files declare `strict_types=1` and use explicit `PDO` typing.

### Architectural separation

Procedural functions in `lib/*.php` handle discrete, stateless domain calculations and template rendering. Services in `lib/Services/*.php` handle stateful workflows, transactional boundaries (like billing and queue scheduling), and data sync.

## File Map

| File | Domain | Key prefix |
|------|--------|------------|
| `admin_lib.php` | Doctor/assistant account management | `zimrx_admin_*` |
| `drug_catalog_lib.php` | System drug catalog queries (PDO) | `drug_catalog_*` |
| `emr_identity_lib.php` | Visit/registration ID generation | `zimrx_get_emr_*`, `zimrx_digits_*` |
| `medical_history_lib.php` | Medical history templates | `zimrx_mh_*` |
| `particulars_audit_lib.php` | Patient demographic lookups | `zimrx_pa_*` |
| `pc_catalog_lib.php` | Presenting Complaint catalog (FTS) | `pc_*` |
| `physical_examination_lib.php` | Physical examination templates | `zimrx_pe_*` |
| `print_setup_lib.php` | Print layout, header/footer, preview | `zimrx_print_*`, `preview_escape` |
| `rx_regimen_lib.php` | Prescription regimen CRUD | `rx_*` |
| `rx_template_lib.php` | Dose/duration/instruction templates | `zimrx_rx_template_*` |
| `user_drug_lib.php` | Doctor-specific user drug customisation | `zimrx_user_drug_*`, `zimrx_resolve_doctor_id` |
| `visit_identity.php` | Visit schema integrity assertions | `zimrx_ensure_visit_identity_schema` |

## Services/ (new OOP layer)

See `lib/Services/README.md` for the namespaced service class layer used in new feature development.
