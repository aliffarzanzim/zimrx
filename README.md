<p align="center">
  <img src="application/assets/images/favicon.svg" alt="ZimRx Logo" width="100" height="100">
</p>

<h1 align="center">ZimRx</h1>

<p align="center">
  <b>Privacy-First, Local-First Digital Prescription & EMR Suite</b><br>
  <i>Built for solo physicians, medical practitioners, and clinics in low-resource and low-connectivity regions.</i>
</p>

<p align="center">
  <a href="https://github.com/aliffarzanzim/zimrx/actions/workflows/tests.yml"><img src="https://github.com/aliffarzanzim/zimrx/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
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

## Key Features

* **100% Air-Gapped & Portable**: Operates entirely offline without an internet connection using a self-contained portable runtime (powered by FrankenPHP and Caddy). Launchable with a single double-click directly from a USB flash drive or laptop with zero installation overhead.
* **Open Data Portability & Sovereignty**: Export complete patient clinical histories, visit notes, and records into standardized open formats (JSON / CSV) at any time with zero vendor lock-in.
* **Ultra-Fast Grid UI**: Custom-engineered, lightweight Prescription Grid UI built with pure Vanilla JS and CSS tokens. Entirely keyboard-driven (Tab & Arrow keys) to eliminate mouse fatigue, allowing doctors to compose an error-free prescription in under 20-30 seconds.
* **Sub-Millisecond Pharmaceutical Search**: Instant full-text search across 30,000+ national commercial drug brands, formulations, strengths, and generic equivalents powered by SQLite FTS5.
* **WHO ATC, DDD & INN International Standards**: Integrated 5-level WHO Anatomical Therapeutic Chemical classification (7,000 nodes across all 14 anatomical groups A through V), official WHO Defined Daily Doses (2,700+ DDD records), and WCO/WHO International Nonproprietary Names (7,200+ INNs with CAS numbers and HS customs codes) for cross-border generic mapping and international interoperability.
* **Clinical Decision Support (CDS)**: Built-in safety checks for drug-drug interactions, pregnancy & lactation contraindications, renal/hepatic adjustments, and pediatric dosage calculators.
* **Rapid Consultation Workflow**: Streamlined interface for presenting complaints, vitals, medical history, physical examinations, diagnostic investigations, and individualized patient advice templates.
* **Pixel-Perfect Print Formatting**: Highly customizable prescription pad layout engine supporting custom doctor headers, clinic logos, multi-column formatters, and watermark overlays for standard A4/A5 or thermal printers.
* **Driver-Agnostic Central PDO Core**: Database abstraction layer with automated, versioned schema migrations (`DbMigrator`), allowing seamless operation on SQLite locally, or scaling to MariaDB, MySQL, and PostgreSQL for multi-user hospital networks.

---

## Architecture & Data Sovereignty

Patient health records should **never** be monetized, tracked, or leaked to centralized third-party clouds.

```
┌─────────────────────────────────────────────────────────────┐
│                       ZimRx Client                          │
│          (Vanilla JS + Modern CSS Design Tokens)            │
└──────────────────────────────┬──────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────┐
│            Portable Web Server & PHP Runtime                │
│                 (FrankenPHP + Caddy Core)                   │
└──────────────────────────────┬──────────────────────────────┘
                               │ Central PDO Abstraction
                               ▼
┌──────────────────┬───────────────────────┬──────────────────┐
│   zimrx_drugs    │     zimrx_static      │  zimrx_userdata  │
│  (30k+ Brands,   │  (Standard DX / IX /  │  (Doctor EMR &   │
│   Interactions,  │    Examinations)      │  Patient Visits) │
│  WHO ATC/DDD/INN)│                       │                  │
└──────────────────┴───────────────────────┴──────────────────┘
```

* **Zero Cloud Lock-in**: All patient encounters, appointments, and billing data stay strictly inside `application/userdata/`.
* **Zero Telemetry**: No tracking pixels, no analytics backdoors, and no remote surveillance.

### Directory Architecture & Security Boundaries

ZimRx implements a hardened **public webroot boundary** (`application/public/`) configured directly in Caddy and `.htaccess`. Sensitive backend engines, private user databases, and reference seeds sit outside the web root:

* **`application/public/` (Web Root)**:
  The only directory exposed to the web server (`root * ./application/public`). Houses entry controllers (`index.php`, `prescription.php`, `emr.php`), the `public/api/` domain controllers, and static browser assets (`public/assets/`). Direct execution is routed through `init.php`.
* **`application/userdata/` (Private State)**:
  Contains all mutable doctor state: SQLite user database (`zimrx_userdata.db`), local backups, and uploads.
  * *Portability*: A doctor can back up, clone, or migrate their entire clinic simply by copying this single folder to a flash drive, with zero database dump scripts.
  * *Security*: Protected with `Require all denied` in `.htaccess` and 404 route blocks in Caddy. Patient clinical reports in `userdata/uploads/reports/` cannot be accessed directly via URLs; they are securely streamed only to authenticated doctors via `api/view_report.php`.
* **`application/systemdata/` (Clinical Reference Catalogs)**:
  Houses static read-only clinical reference databases (`systemdata/database/zimrx_drugs.db` and `zimrx_static.db`) and SQL seeds (`systemdata/seeds/`). Blocked from direct web access.
* **`application/lib/` (Core Logic & Database Layer)**:
  Houses authentication (`lib/auth.php`), the central PDO database connection layer (`lib/db/db.php`), versioned schema migrations (`lib/db/db_migrator.php`), and clinical helper services.
* **`application/views/` (Module Templates)**:
  Prescription grid workspace modules (`views/modules/`) and page templates included securely by server-side controllers.


---

## Deployment Scope & Global Adaptability

* **Cross-Border Scalability & Decoupled Formularies**: While currently being developed in Bangladesh, ZimRx is architected from the ground up for cross-border adoption. Generic names, class, indications are strictly decoupled from commercial brand names in the database, allowing international clinics, health services, or humanitarian teams to operate on Day 1 in generic-prescribing mode. Developers and medical contributors from other countries can easily plug in, customize, or maintain their own national drug formularies without altering the application core.
* **Humanitarian Aid & Crisis Relief Ready**: Released under the copyleft **GNU AGPLv3** license, ZimRx provides medical teams and humanitarian organizations (such as *Médecins Sans Frontières*) with a zero-cost, resilient clinical prescribing engine that operates reliably in disaster response zones, refugee health posts, and rural clinics during complete telecommunication or power blackouts.
* **Privacy by Design & Data Sovereignty**: Operates on a strict local-first, zero-telemetry model aligned with global privacy standards (GDPR, medical confidentiality). Sensitive patient health records remain strictly on local clinic hardware, entirely eliminating exposure to commercial cloud providers, telemetry backdoors, and recurring subscription lock-in.
* **Modern Edge Infrastructure**: Powered by **[FrankenPHP](https://frankenphp.dev/)** and Caddy core, delivering a self-contained, single-binary execution environment that runs natively on budget laptops and clinic edge hardware without external database or web server configuration.

### European Dimension & Humanitarian Collaboration

While designed for low-resource environments globally, ZimRx incorporates European healthcare and open data dimensions:
* **Standardized WHO ATC 2026 & INN Ontologies**: Clinical substances in `zimrx_drugs.db` map natively to the 5-level WHO Anatomical Therapeutic Chemical classification and International Nonproprietary Names (INN). European medical professionals can prescribe immediately using international substance standards, or integrate European national drug catalogs (such as BNF, Vidal, or Rote Liste equivalents) without touching application logic.
* **European Humanitarian & Field Mission Utility**: Provides European humanitarian NGOs (such as *Médecins Sans Frontières*, ICRC, and emergency medical teams) with an offline, auditable clinical tool for crisis deployments where commercial cloud infrastructure is inaccessible or illegal under strict data protection mandates.
* **Open European Co-Maintainership**: We actively invite European free software health informatics contributors, clinical pilot sites, and independent European security auditing firms (such as Radically Open Security) to pair for collaborative milestone delivery, security audits, and multi-language European localizations.

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

### Web Server / cPanel / Shared Hosting (Apache, Nginx, LiteSpeed)
1. Deploy the `application/` directory to your web root (or configure your virtual host root to `application/public`).
2. Ensure `application/userdata/` is writable by the web server process.
3. Open the application URL in your browser:
   * ZimRx automatically provisions the master drug catalog, clinical lookup databases, and doctor user schemas in-process on first visit.
   * No terminal access, SSH, or external command-line database tools are required.

### Docker (Multi-Platform)
```bash
docker compose up
```
Open `http://localhost:8080` in your browser. User data and prescriptions are automatically persisted in `./application/userdata`.

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
php application/tests/run_tests.php
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

---

## Generative AI & Development Policy

Patient safety comes first. While AI tools can be used to accelerate development and write tests, all clinical rules, drug interactions, edge cases and database logic are strictly designed and checked by real physicians and human developers.

* **Clinical & Architectural Integrity:** All medical workflows, pediatric dosing models, and database schemas are manually verified against standard clinical reference literature and our automated regression tests.
* **Open Source & License Compliance:** All contributions must be legally compatible with the GNU AGPL-3.0 license and free of third-party copyrighted materials.
* **Contributor Guidelines:** Contributors using AI assistance for pull requests must ensure all changes are human-audited, deterministic, and fully explained.

---

## License & Medical Data Attribution

### Software Code (GNU AGPLv3)
ZimRx application source code, UI components, database migration engine (`DbMigrator`), and backend APIs are licensed under the **[GNU Affero General Public License v3.0 (AGPL-3.0)](LICENSE)**.

### Clinical Catalogs & Data Aggregation
The standalone reference databases bundled in `application/systemdata/database/` are distributed alongside the software under a mere aggregation model:
* **Presenting Complaints (`zimrx_static_pc`)**: 
  The medical terminology catalogs represent standard, public clinical vocabulary curated from standard medical literature & textbooks, clinical practitioner notes, and the **SNOMED CT Global Patient Set (GPS)** for workflow efficiency and rapid autocomplete. They do not incorporate proprietary code systems or relational ontologies. Applicable SNOMED descriptions are used under the SNOMED International GPS Open License:
  > *"This material includes SNOMED Clinical Terms ® (SNOMED CT ®) which is used by permission of SNOMED International. All rights reserved. SNOMED CT ® was originally created by the College of American Pathologists."*
* **Pharmaceutical & Chemical Standardization (`zimrx_drugs`)**:
  * **Commercial Brands & National Formularies**: Curated from public national pharmacopoeias, clinical formularies, open drug registries, and standard pharmacology reference texts.
  * **WHO Anatomical Therapeutic Chemical (ATC) Classification & Defined Daily Dose (DDD)**: Derived from the official **WHO Collaborating Centre for Drug Statistics Methodology (2026)** reference datasets. Provides a normalized 5-level hierarchical taxonomy (7,000 nodes across all 14 anatomical groups A through V) and adult maintenance doses (2,768 records) for cross-border generic equivalence, clinical decision support, and epidemiological interoperability.
  * **International Nonproprietary Names (INN)**: Standardized pharmaceutical nomenclature from the **World Health Organization (WHO)** and **World Customs Organization (WCO)** Harmonized System table (7,268 records), mapping generic substances to chemical CAS registry numbers and international tariff classifications.

