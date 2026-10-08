<p align="center">
  <img src="application/public/assets/images/favicon.svg" alt="ZimRx Logo" width="100" height="100">
</p>

<h1 align="center">ZimRx</h1>

<p align="center">
  <b>Privacy-First, Local-First Medical & Healthcare Suite</b><br>
  <i>Built for solo physicians, medical practitioners, and clinics in low-resource and low-connectivity regions.</i>
</p>

<p align="center">
  <a href="https://github.com/aliffarzanzim/zimrx/actions/workflows/tests.yml"><img src="https://github.com/aliffarzanzim/zimrx/actions/workflows/tests.yml/badge.svg?branch=master" alt="Tests"></a>
  <a href="https://www.gnu.org/licenses/agpl-3.0"><img src="https://img.shields.io/badge/License-AGPL_v3-blue.svg" alt="License: AGPL v3"></a>
  <a href="#architecture--data-sovereignty"><img src="https://img.shields.io/badge/Architecture-Local--First-green.svg" alt="Local-First"></a>
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.2%2B%20%7C%20PDO-8892BF.svg" alt="PHP"></a>
  <a href="https://frankenphp.dev/"><img src="https://img.shields.io/badge/Runtime-FrankenPHP%20%2B%20Caddy-blueviolet.svg" alt="FrankenPHP"></a>
</p>

---

![ZimRx Prescription Interface](preview/interface-preview.png)
*A preview of the ZimRx ultra-fast Prescription Grid UI*

> **Prototype Demo**: [Watch the Prototype Video (preview/prototype-preview.mp4)](preview/prototype-preview.mp4) - *Demonstration of the doctor-friendly, ultra-fast prescription workflow.*

---

**ZimRx** is an open-source, local-first, high-performance digital prescription and Electronic Medical Record (EMR) system built for solo doctors, medical practitioners, and clinics in low-resource or bandwidth-constrained regions. Built on a strict local-first philosophy, ZimRx is designed for fully air-gapped and offline environments with zero external network dependencies, eliminates recurring SaaS subscription costs, and guarantees complete patient data privacy and sovereignty.

---

## The Three Cardinal Laws of ZimRx

**LAW 1: IF IT CANNOT RUN AIR-GAPPED, IT DOES NOT ENTER ZIMRX.**  
Zero Cloud. Zero Telemetry. Zero Data Loss.

**LAW 2: NEVER BLOCK THE CLINICIAN.**  
No Spinners. No Loading Bars. Zero Keystroke Latency.

**LAW 3: BETTER INCOMPLETE THAN WRONG.**  
Never Guess. Never Fabricate. Zero Silent Assumptions.

---

## Key Features

* **100% Air-Gapped & Portable**: Operates entirely offline with zero network dependencies. Runs directly from a USB drive or laptop with zero installation overhead via portable FrankenPHP and Caddy.
* **Data Sovereignty & Open Formats**: Complete patient clinical histories and records export to open JSON/CSV formats, with HL7 FHIR International Patient Summary (IPS) export under active development.
* **Ultra-Fast Grid UI**: Pure Vanilla JS and CSS tokens. Keyboard-driven (Tab & Arrow navigation) to eliminate mouse fatigue and compose prescriptions in under 30 seconds.
* **Sub-Millisecond Search**: Fast full-text lookup across 30,000+ national commercial drug brands and generic equivalents powered by SQLite FTS5.
* **WHO ATC, DDD & INN Standards**: Mapped to official WHO ATC 2026 classification (7,000 nodes), WHO Defined Daily Doses (2,700+ DDDs), and WHO/WCO International Nonproprietary Names (7,200+ INNs).
* **Clinical Decision Support (Assistive & In Development)**: Informational screening aids for drug-drug interactions, pediatric growth curves, and dosing parameters. Sourced from standard prescribing literature.
* **Rapid Clinical Workflow**: Integrated modules for presenting complaints, vitals, medical history, physical exams, investigations, and patient advice templates.
* **Pixel-Perfect Print Engine**: Customizable prescription layout supporting custom doctor headers, clinic logos, multi-column formats, and print watermarks for A4/A5 or thermal printers.
* **Multi-Language Clinical Localization**: Patient instructions, dosage regimens, durations, and advice categories localized via simple JSON catalogs (`application/locales/`) with zero database migrations.
* **Driver-Agnostic Central PDO Core**: Works out-of-the-box on SQLite with versioned migrations (`DbMigrator`), with seamless scaling to MariaDB/MySQL/PostgreSQL for multi-user clinic networks.

---

## Architecture & Data Sovereignty

Patient health records should **never** be monetized, tracked, or leaked to centralized third-party clouds.

| Layer | Component | Details & Technologies |
|---|---|---|
| **Frontend** | ZimRx Client | Vanilla JS, modern CSS design tokens, keyboard-first prescription grid |
| **Runtime** | Web Server & PHP Engine | Embedded FrankenPHP + Caddy runtime (100% offline, zero installation) |
| **Database** | Central PDO Abstraction | SQLite with schema migrations (`zimrx_drugs`, `zimrx_static`, `zimrx_userdata`) |

* **Zero Cloud Lock-in**: All patient encounters, appointments, and billing data stay strictly inside `application/userdata/`.
* **Zero Telemetry**: No tracking pixels, no analytics backdoors, and no remote surveillance.

### Patient Sovereignty

ZimRx recognizes that health data belongs fundamentally to the patient, not to software vendors or centralized cloud providers. Two core features guarantee genuine patient data sovereignty:

* **Open Export & Data Portability**:
  At any time, a patient can request their complete clinical history. The clinician can export their entire record in open, standardized formats (clean printable PDF summary, structured JSON, or HL7 FHIR IPS) directly to the patient's mobile phone, flash drive, or personal storage with zero proprietary lock-in.

* **The Right to be Forgotten**:
  If a patient requests complete profile deletion ("I am moving away, please delete my records"), the clinic can permanently scrub their record while safeguarding both parties against legal disputes:
  * **Physical Database Shredding**: Rather than a superficial soft-delete (`deleted_at` flag), ZimRx executes cascading deletion across clinical encounters, vitals, prescriptions, and media attachments, followed by an immediate database page-zeroing compaction (`VACUUM`) to prevent forensic recovery.
  * **Signed Request & Medico-Legal Defense**: In medical practice, records are never deleted on a verbal whim. ZimRx generates a printable "Request for Record Erasure" form for the patient to physically sign. The clinic files this signed document in their legal archive alongside an immutable, timestamped local audit log entry (`PATIENT_ERASURE_FULFILLED`), protecting the clinician against future malpractice disputes or bad-faith allegations.
  * **Certificate of Erasure**: Issues a verifiable confirmation slip specifying the exact purge timestamp, anonymized reference, and zeroed record counts as permanent proof of erasure for the patient.

### Project Structure

```text
zimrx/
├── application/
│   ├── public/       # Web root (index.php, prescription.php, assets, api)
│   ├── userdata/     # Doctor databases, backups, uploads (blocked from web access)
│   ├── systemdata/   # Master drug and clinical reference databases
│   ├── lib/          # Database connection, auth, and business logic
│   ├── views/        # Prescription workspace modules and templates
│   └── locales/      # Multi-language clinical catalogs (en, bn)
├── tools/            # Offline dataset tools (growth charts, geo hierarchies, locales)
├── Caddyfile         # Caddy / FrankenPHP server configuration
├── start.bat         # Windows startup script
└── start.sh          # Linux and macOS startup script
```

Only `application/public/` is exposed to the web server. Private patient data (`userdata/`), core libraries (`lib/`), and reference databases (`systemdata/`) are kept outside the document root.

---

## Global Adaptability & European Dimension

* **Decoupled Formularies & Generic Prescribing**: Generic substances are decoupled from local brand names and mapped directly to WHO ATC 2026 and INN standards. International clinics can prescribe immediately in generic mode, or plug in national drug formularies (such as BNF, Vidal, or Rote Liste) without modifying application code.
* **International Patient Summary (HL7 FHIR IPS)**: Developing an offline export engine aligned with European cross-border healthcare frameworks (MyHealth@EU / EHDS).
* **Multilingual Clinical Catalogs**: Standalone JSON locale catalogs enable healthcare teams operating across linguistic borders (EU cross-border care or humanitarian missions) to support regional languages (English, Bengali, French, German, Spanish) and search aliases with zero database schema migrations.
* **Humanitarian Aid & Crisis Relief**: Released under the GNU AGPLv3 license, ZimRx provides medical teams with an offline, auditable clinical tool for disaster response zones, refugee health posts, and conflict areas where cloud SaaS is unusable or legally restricted.
* **Privacy by Design (GDPR Article 25)**: Operates on a strict local-first architecture. Patient records remain entirely on local clinic hardware, eliminating commercial cloud tracking, subscription lock-in, and remote data jurisdiction risks.
* **Open Collaboration & Clinical Pilots**: We welcome health informatics contributors, clinical pilot sites, and independent security researchers to collaborate on testing, field deployments, and localized national drug formularies.

---

## Quick Start

### Windows
1. Clone or download the repository:
   ```bash
   git clone https://github.com/aliffarzanzim/zimrx.git
   ```
2. Double-click **`start.bat`** (downloads and configures FrankenPHP automatically on first run).
3. ZimRx automatically opens in your browser at `http://localhost:8080`.

### Linux & macOS
1. Clone or download the repository.
2. Run the startup script:
   ```bash
   chmod +x start.sh
   ./start.sh
   ```
3. Open `http://localhost:8080` in your browser.

### Direct PHP CLI (Cross-Platform)
If you already have PHP 8.2+ installed on your computer:
```bash
php -S localhost:8080 -t application/public
```
Open `http://localhost:8080` in your browser.

> **First-Launch Wizard**: On first launch, ZimRx automatically compiles master reference databases and launches the onboarding setup wizard to configure your doctor profile, clinic letterhead, and initial credentials.

### Web Server / cPanel / Shared Hosting (Apache, Nginx, LiteSpeed)
1. Deploy the `application/` directory to your web root (or configure your virtual host root to `application/public`).
2. Ensure `application/userdata/` is writable by the web server process.
3. Open the application URL in your browser:
   * ZimRx automatically provisions the master drug catalog, clinical lookup databases, and doctor user schemas in-process on first visit.
   * No terminal access, SSH, or external command-line database tools are required.

### Docker (Multi-Platform)
```bash
# Build and launch FrankenPHP runtime
docker compose up --build

# Run automated tests inside the container
docker compose exec zimrx php application/tests/run_tests.php
```
Open `http://localhost:8080` in your browser. Clinical records and system state are automatically persisted in the `zimrx_userdata` volume.

---

## Development & Deployment

### Requirements
* **PHP 8.2+** with standard extensions: `pdo_sqlite`, `mbstring`, `curl`, `fileinfo`, `gd`, `intl`, `openssl` (or Docker / FrankenPHP).
* **Zero External CLI Dependencies**: No `sqlite3` CLI binary, no Python, and no Node.js are required at runtime or deployment.
* **Web Server**: Caddy (included with FrankenPHP), Apache, Nginx, or PHP built-in web server.

### Automated Setup for Developers
Windows developers can run the included setup script to configure FrankenPHP and required extensions with one click:
```cmd
setup-franken-for-dev.bat
```

### Reference Database Auto-Provisioning & Seeds
Master reference databases (`zimrx_drugs.db` and `zimrx_static.db`) are compiled directly from plain-text SQL seeds (`application/systemdata/seeds/*.sql`) using native PHP PDO. Compilation occurs automatically on first application launch, or can be triggered manually via CLI:
```bash
# Verify or compile reference databases (skips if healthy databases already exist)
php application/systemdata/seeds/build_db.php

# Force recompilation from seeds
php application/systemdata/seeds/build_db.php --force
```

### Database Migrations
Doctor user database schemas (`application/userdata/database/zimrx_userdata.db`) and versioning are managed through `DbMigrator` (`application/lib/db/db_migrator.php`). Pending schema migrations are applied automatically on boot or can be checked via CLI:
```bash
php -r "require_once 'application/lib/db/db.php';"
```

### Automated Test Suite
ZimRx includes an automated test suite covering security controls, static source integrity checks, SQLite concurrency, and clinical calculations:
```bash
# Run comprehensive test suite (235 tests across 25 security and clinical groups):
php application/tests/run_tests.php

# Verify pediatric growth reference tables and Cole's LMS transformations:
php tools/growth-chart/fetch_and_build_reference_json.php
```

### Code Organization & Repository Notes
* **Repository Size & Seed Integrity**: The reference catalog seeds (`application/systemdata/seeds/*.sql`) are tracked as plain-text SQL. Line-ending normalization churn is prevented via `.gitattributes` (`-text` directive for large seeds), keeping the repository free from binary bloat.
* **Code Organization**: Several frontend modules (`drug_detail.js`, `history.js`, `appointments.js`, `pc.js`) and `prescription_preview.php` exceed 1,000 lines. These files originated during initial feature prototyping; refactoring them into smaller, focused components is scheduled for the next development cycle.

---

## Roadmap & Upcoming Features

ZimRx is actively under development. Our immediate technical roadmap includes:
* **Cryptographic Delta-Updates**: 15-30 KB differential SQL patches for low-bandwidth database syncing (cryptographically signed and verified via Libsodium Ed25519).
* **Smart TV Waiting Room Dashboard**: Live, anonymized token tracking and ethical queue management for clinic waiting areas.
* **Native Bengali Transliteration**: In-app phonetic typing engine (Avro-compatible) to remove reliance on OS-level keyboard bloatware.
* **Hardware Normalization**: ESC/POS dialect translation for thermal receipt printers and barcode buffer normalization for unstandardized clinic hardware.
* **Dynamic Pediatric Growth Charts**: Multi-visit child growth plotting against WHO and CDC Z-score curves.
* **HL7 FHIR IPS Calibration**: Connecting clinical recording modules (allergies, problem list, investigations) into the offline International Patient Summary export engine.
* **Full UI & Workspace Localization**: Expanding the `LocaleService` architecture to support complete interface translation (`ui.json`) across navigation bars, clinical card headers, modal dialogs, and print settings for multi-language clinic deployments.
* **Central Hub & Satellite Peer Sync**: Offline-first multi-room mesh syncing patient encounters between a local central clinic hub and satellite clinic laptops via `SyncJournalService`.
* **Enterprise Database Scaling (MariaDB & PostgreSQL)**: Scaling the central PDO abstraction (`DbConnections` and `DbSql`) from local SQLite files to external MariaDB or PostgreSQL clusters for high-concurrency hospital networks.
* **Multi-Platform Packaging**: Pre-built one-click standalone distributions for Windows, Linux, macOS, and Docker.

---

## Acknowledgements & Special Thanks

Heartfelt gratitude to the contributors and researchers who supported the ZimRx medical intelligence and pharmaceutical database:

### Drug Database Contributions
* **Sifat Islam**
* **Sifat Bin Siddique Urfi** *(DMC K-79)*
* **Yeamin Faiaj**
* **Azithromycin** *(Pseudonym - Guess The Case Community Member)*
* **Sauda Noor Sara** *(NMC)*
* **Sohaila Raida** *(CMC)*
* **Asif Iqbal** *(KMC K31)*
* **Shamsul** *(RpMC)*
* **Ramisa Subah** *(CMC)*
* **Mahbub ("The Dark Lord")** *(Pseudonym - Guess The Case Community Member)*
* **Jaowad Arham**

### Drug Interaction & Research Concept
* **Saif** - for pioneering drug interaction research ideas and clinical logic.

---

## Clinical Disclaimer & Regulatory Status

**ZimRx is strictly an assistive administrative workflow and clinical recordkeeping tool. It is not an autonomous medical device, nor does it provide automated medical diagnoses or treatment decisions.**

- **Physician Responsibility:** All clinical decision support features, including drug-drug interaction alerts, pediatric dose calculations, and contraindication warnings, serve purely as evidence-based informational reference aids.
- **Professional Oversight:** Final clinical evaluation, diagnostic judgment, and prescribing authority rest solely and exclusively with the attending licensed medical practitioner.
- **No Warranty:** In accordance with the GNU AGPL-3.0 license, this open-source software is provided "as is", without warranty of any kind. Clinicians must independently verify all pharmacological doses, contraindications, and patient parameters prior to administering or prescribing any medical therapy.
- **No Inferred Safety from Absence:** The absence of a drug-drug interaction alert or dosage adjustment warning in the database signifies that no verified rule is currently recorded in the catalog. It does NOT imply that the combination or dosage is clinically safe. Prescribing physicians must independently verify all pharmacological parameters.

---

## Medical Data Lineage & Governance

Patient safety and transparent evidence provenance are foundational to ZimRx. Reference catalogs in `application/systemdata/database/` are maintained under open standards:

* **WHO Anatomical Therapeutic Chemical (ATC) & Defined Daily Dose (DDD)**:
  Derived from the official **WHO Collaborating Centre for Drug Statistics Methodology (2026)** reference datasets. Provides a normalized 5-level hierarchical taxonomy (7,000 nodes across all 14 anatomical groups A through V) and adult maintenance doses (2,768 records) for cross-border generic equivalence and clinical decision support.
* **International Nonproprietary Names (INN)**:
  Standardized pharmaceutical nomenclature from the **World Health Organization (WHO)** and **World Customs Organization (WCO)** Harmonized System table (7,268 records), mapping generic substances to chemical CAS registry numbers and international tariff classifications.
* **Presenting Complaints (`zimrx_static_pc`)**:
  Clinical vocabulary curated from medical literature and practitioner notes, incorporating descriptions from the **SNOMED CT Global Patient Set (GPS)** under the SNOMED International GPS Open License:
  > *"This material includes SNOMED Clinical Terms ® (SNOMED CT ®) which is used by permission of SNOMED International. All rights reserved. SNOMED CT ® was originally created by the College of American Pathologists."*
* **Commercial Brands & National Formularies**:
  Curated from public regulatory registrations (**DGDA Bangladesh**) and reference pharmacopoeias and formularies.
* **Clinical Monographs & Interaction Screening**:
  Generic monographs (indications, dose ranges, warnings) and preliminary drug interaction pairs are curated from approved manufacturer package inserts, the British National Formulary (BNF), and clinical pharmacology literature. Maintained as working drafts subject to ongoing multi-physician review during clinical pilots.

---

## Generative AI & Human Review Policy

Patient safety comes first. While AI tools can be used to accelerate development and write tests, all clinical rules, drug interactions, edge cases, and database logic are strictly designed and checked by real physicians and human developers.

* **Clinical & Architectural Integrity:** All medical workflows, pediatric dosing models, and database schemas are manually verified against standard clinical reference literature and our automated regression tests.
* **Open Source & License Compliance:** All contributions must be legally compatible with the GNU AGPL-3.0 license and free of third-party copyrighted materials.
* **Contributor Guidelines:** Contributors using AI assistance for pull requests must ensure all changes are human-audited, deterministic, and fully explained.

---

## License

ZimRx application source code, UI components, database migration engine (`DbMigrator`), and backend APIs are licensed under the **[GNU Affero General Public License v3.0 (AGPL-3.0)](LICENSE)**. Standalone clinical reference catalogs are distributed alongside the software under a mere aggregation model.

