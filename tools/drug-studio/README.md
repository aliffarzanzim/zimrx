# ZimRx Drug Database: Curation, Seeding & Delta Update Architecture

## Challenges

ZimRx is built as a privacy-first, offline-capable Electronic Medical Record (EMR) engine designed to run seamlessly in low-resource and bandwidth-constrained settings (e.g. rural 2G/3G mobile networks, offline USB deployments).

A core challenge in offline clinical software is maintaining an up-to-date pharmaceutical catalog without forcing clinics to re-download 30-90 MB databases over slow connections. This document outlines the end-to-end architecture for:
1. **Decoupled Database Architecture**: Universal Core vs Country Packs.
2. **Drug Studio**: Standalone visual curation interface for clinical contributors managing all drug data (generics, indications, interactions, dosage forms, WHO ATC/INN mapping, and country brand formularies).
3. **Direct SQL Contribution Pipeline**: Conflict resolution and Git collaboration model.
4. **Automated SQLite Differential Generator**: Producing compact 15-30 KB SQL delta patches.
5. **Libsodium (Ed25519) Cryptographic Verification**: Tamper-proof patch verification and transactional application.

---

## 2. Decoupled Database & Seed Architecture

The master drug database (`zimrx_drugs.db`) is split into two modular layers:

### A. Universal Core (`zimrx_drugs.sql`)
- **Nature**: Country-agnostic, brand-free clinical knowledge base.
- **Tables**:
  - `drug_generic`: 2,331 clinical active ingredients (new generic additions, aliases, mechanism).
  - `drug_dosage_form`: 129 standardized pharmaceutical delivery forms.
  - `drug_generic_product`: 6,017 normalized clinical products (`generic_id` + `form` + `strength`).
  - `drug_template`: Standard dosage regimens (English templates `lang = 'en'`).
  - `drug_therapeutic_class`, `drug_indication`, `drug_interaction`, `drug_pregnancy_category`.
  - `who_atc_hierarchy` (7,000 nodes), `who_ddd_reference` (2,768 records), `who_inn_catalog` (7,268 records).
- **Size**: ~32.8 MB plain text SQL.

### B. Country Packs (`zimrx_drugs_<country>.sql`)
- **Nature**: National commercial brand catalogs and localized clinical phrasing.
- **Example (Bangladesh Pack `zimrx_drugs_bd.sql`)**:
  - `drug_brand_bd`: 37,260 commercial trade brand names.
  - `drug_manufacturer_bd`: 751 licensed pharmaceutical manufacturers.
  - `drug_template` (Bengali extensions `lang = 'bn'`): Localized phrasing (`খাবার পর`, `১+০+১`).
- **Size**: ~6.3 MB plain text SQL.

### C. Just-In-Time Materialization
The heavy `drug_prescribe` catalog (37,260 commercial rows) and `fts_drug_prescribe` FTS5 virtual search index are **not shipped in SQL dumps**. They are precomputed and materialized dynamically at build time via `build_db.php` in under 3 seconds, eliminating ~50 MB of redundant plain-text repository bloat.

---

## 3. Drug Studio: Comprehensive Drug Database & Clinical Curation Interface

Hand-editing a 33 MB plain-text SQL file is error-prone, intimidating for medical professionals, and vulnerable to syntax corruption. Drug Studio provides a centralized, visual workspace for all drug database management:

### A. Studio Architecture
- **Location**: Standalone tool in `ZimRx/tools/drug-studio/`.
- **Runtime**: Launched locally via PHP built-in server (`php -S localhost:8090`).
- **Isolation**: Completely decoupled from the production doctor application. Production `zimrx_drugs.db` remains strictly read-only for daily clinic safety.

### B. Comprehensive Clinical Scope
Drug Studio manages the full clinical pharmaceutical lifecycle:
1. **Core Generic & Substance Management**:
   - Adding and curating clinical active substances (`drug_generic`).
   - Maintaining CAS Registry numbers, INN stems, and WHO ATC classifications.
2. **Clinical Indications & Therapeutics**:
   - Mapping clinical indications and diagnostic keywords to generics.
   - Managing therapeutic classifications and contraindications.
3. **Drug-Drug Interactions & Safety**:
   - Registering severity levels, clinical mechanisms, and management advice between active generics.
   - Managing FDA/TGA pregnancy categories and lactation safety profiles.
4. **Pharmaceutical Products & Regimens**:
   - Creating normalized delivery forms (`drug_dosage_form`) and products (`drug_generic_product`).
   - Pre-configuring recommended dosage regimens, administration instructions, and durations (`drug_template`).
5. **Country Formularies & Commercial Brands**:
   - Managing country-specific brand trade names (`drug_brand_<country>`) and licensed manufacturers (`drug_manufacturer_<country>`).
   - Multi-country formulary isolation and pack exports.

### C. Curation Workflow
1. **Interactive CRUD**:
   - Search, edit, and add Generics, Indications, Interactions, Products, Dosage Forms, Commercial Brands, and Regimen Templates.
   - Built-in form validation: prevents duplicate brands, validates strength spacing, enforces normalized dosage forms, and links pregnancy safety flags.
2. **Edits Hit Local SQLite Directly**:
   - All mutations execute against a local SQLite reference database with indexed, sub-millisecond queries.
   - Instant visual preview: contributors see the exact prescription autocomplete and grid formatting before exporting.
3. **Deterministic Seed Export**:
   - When the contributor clicks "Export Seeds", the Studio invokes `export_seeds.py`.
   - Generates deterministic, sorted `INSERT INTO` statements for `zimrx_drugs.sql` or `zimrx_drugs_<country>.sql`.
   - Because rows are strictly sorted by primary key, `git diff` produces clean, surgical line-by-line diffs.

---

## 4. Multi-Contributor Collaboration & Conflict Resolution

### A. Why Direct SQL Beats Staged JSON Files
Using intermediate staged JSON files forces the project to invent and maintain a brittle custom migration language (handling updates to existing rows, deletes, foreign key re-links). Direct SQL dumps generated by Drug Studio keep the SQL seed as the true single source of truth.

### B. Editing Existing Drugs (Painless Line-Level Merging)
- In `zimrx_drugs.sql`, each record occupies exactly one line, sorted by ID.
- If Contributor A updates pregnancy category for drug `1839` and Contributor B updates dosage form for drug `405`, Git merges both cleanly with zero merge conflicts.
- If both contributors edit the exact same drug, Git flags a clinical merge conflict for maintainer review.

### C. Adding New Drugs (Resolving ID Collisions)
ID collisions only occur when two contributors add brand-new records simultaneously:
1. **Country Pack Isolation**:
   Because country brands live in isolated tables (`drug_brand_bd`, `drug_brand_ke`), contributors in different countries never share ID sequences.
2. **Automated Maintainer Reconciler**:
   When Contributor A and Contributor B add a new core generic with the same local auto-increment ID:
   - Contributor A's PR is merged first.
   - When merging Contributor B's PR, the maintainer runs a 1-click reconciliation command.
   - The tool detects the occupied ID, renumbers Contributor B's new record to the next free ID (`2333`), cascades foreign keys to child brands and templates, and commits.

### D. Contributor Local Synchronization
After a PR is merged with reconciled IDs:
1. Contributor runs `git pull upstream main`.
2. Contributor clicks "Rebuild Database from Seeds" in Drug Studio (or runs `build_db.php`).
3. Local SQLite database recompiles to match the official upstream catalog in 2-3 seconds.
4. Clinic data (`zimrx_userdata.db`) is 100% untouched because patient records are strictly isolated.

---

## 5. SQLite Differential Generator (Delta Patches)

Instead of downloading a full 33 MB SQL dump or 90 MB database on rural connections, the release pipeline produces compact 15-30 KB delta patches.

### A. Difference Extraction Pipeline
1. The release script opens the previous release database (`v1.0.0.db`) and the newly compiled database (`v1.0.1.db`).
2. SQLite database attachment diffing:
   - Identifies inserted/updated rows across all tables via relational `EXCEPT` queries.
   - Identifies deleted rows (if any).
3. Produces a sequential SQL patch script (`patch_1002.sql`):
   - Sets of `INSERT OR REPLACE INTO ...` statements for updated rows.
   - Sets of `DELETE FROM ... WHERE id IN (...)` for retired rows.
   - Updates `zimrx_drug_db_version` and inserts audit entry into `zimrx_drug_patches`.
4. Typical patch size: **15 to 30 KB** (easily transmissible over 2G/3G connections or mobile hotspots).

---

## 6. Cryptographic Signing & Verification (Libsodium Ed25519)

### A. Zero Third-Party Dependencies
PHP includes `ext-sodium` (Libsodium) built-in since PHP 7.2. It requires zero external Composer packages or binaries, executing sub-millisecond cryptographic signatures natively.

### B. Key Management
1. **Private Signing Key (Maintainer / CI)**:
   - 64-byte Ed25519 secret key stored in encrypted CI secrets or offline maintainer key store.
   - Used only to sign release patches.
2. **Public Verification Key (Client ZimRx)**:
   - 32-byte Ed25519 public key embedded in `ZimRx/application/config/config.php` (`ZIMRX_UPDATE_PUBLIC_KEY`).
   - Shipped with every ZimRx installation.

### C. Signed Patch Manifest Structure
Patches are distributed as compact JSON packages:
```json
{
  "patch_id": 1002,
  "base_version": "1.0.1",
  "target_version": "1.0.2",
  "country_code": "all",
  "created_at": "2026-10-06T12:00:00Z",
  "payload_sql": "BEGIN TRANSACTION;\nINSERT OR REPLACE INTO ...;\nCOMMIT;",
  "signature": "base64_encoded_64_byte_ed25519_signature"
}
```

### D. Safe Client Verification Flow
When ZimRx receives a patch file (via background check or USB drive import):
1. Client extracts `payload_sql` and base64-decoded `signature`.
2. Client runs:
   `sodium_crypto_sign_verify_detached($signature, $payloadSql, $publicKey)`
3. **If verification fails**: The patch is rejected immediately; zero SQL is executed.
4. **If verification succeeds**:
   - Executes inside a transactional block (`BEGIN TRANSACTION; ... COMMIT;`).
   - Local database updates in < 0.5s.
   - If country brands were updated, triggers background rebuild of `drug_prescribe` and `fts_drug_prescribe`.

---

## 7. Implementation Roadmap & Milestones

1. **Milestone 1: Drug Studio UI (`ZimRx/tools/drug-studio/`)**:
   - Web interface with clean CRUD forms for Generics, Indications, Interactions, Brands, Dosage Forms, and Regimens.
   - One-click deterministic export calling `export_seeds.py`.
2. **Milestone 2: Seed Reconciler Script**:
   - Maintainer utility to detect and renumber conflicting generic IDs when merging overlapping PRs.
3. **Milestone 3: SQLite Differential Generator Script**:
   - Script that attaches two SQLite database versions and generates the minimal SQL patch script.
4. **Milestone 4: Libsodium Signer & Client Verifier**:
   - CLI script for maintainers to sign patch manifests with Ed25519 private key.
   - Endpoint in ZimRx client to verify signatures and apply transactional patches safely.
