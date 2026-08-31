# ZimRx — Privacy-First, Local-First Digital Prescription & EMR Suite

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![Local-First](https://img.shields.io/badge/Architecture-Local--First-green.svg)](#architecture--data-sovereignty)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B%20%7C%20PDO-8892BF.svg)](https://www.php.net/)
[![FrankenPHP](https://img.shields.io/badge/Runtime-FrankenPHP%20%2B%20Caddy-blueviolet.svg)](https://frankenphp.dev/)

**ZimRx** is an open-source, high-performance digital prescription and Electronic Medical Record (EMR) system built for solo doctors, medical practitioners, and clinics in low-resource or bandwidth-constrained regions. Built on a strict **local-first philosophy**, ZimRx runs 100% offline, eliminates recurring SaaS subscription costs, and guarantees complete patient data privacy and sovereignty.

---

## ✨ Key Features

* **⚡ 100% Offline & Portable**: Runs anywhere without an internet connection using a self-contained portable runtime (powered by FrankenPHP and Caddy). Launchable with a single click from a laptop or local clinic LAN.
* **💊 Sub-Millisecond Pharmaceutical Search**: Instant full-text search across 30,000+ national commercial drug brands, formulations, strengths, and generic equivalents powered by SQLite FTS5.
* **🛡️ Clinical Decision Support (CDS)**: Built-in safety checks for drug-drug interactions, pregnancy & lactation contraindications, renal/hepatic adjustments, and pediatric dosage calculators.
* **📋 Rapid 30-Second Prescription Flow**: Streamlined interface for presenting complaints, vitals, medical history, physical examinations, diagnostic investigations, and individualized patient advice templates.
* **🖨️ Pixel-Perfect Print Formatting**: Highly customizable prescription pad layout engine supporting custom doctor headers, clinic logos, multi-column formatters, and watermark overlays for standard A4/A5 or thermal printers.
* **🔌 Driver-Agnostic Central PDO Core**: Database abstraction layer with automated, versioned schema migrations (`DbMigrator`), allowing seamless operation on SQLite locally, or scaling to MariaDB, MySQL, and PostgreSQL for multi-user hospital networks.

---

## 🏛️ Architecture & Data Sovereignty

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
│  (30k+ Catalog   │  (Standard DX / IX /  │  (Doctor EMR &   │
│  & Interactions) │    Examinations)      │  Patient Visits) │
└──────────────────┴───────────────────────┴──────────────────┘
```

* **Zero Cloud Lock-in**: All patient encounters, appointments, and billing data stay strictly inside `application/userdata/`.
* **Zero Telemetry**: No tracking pixels, no analytics backdoors, and no remote surveillance.

---

## 🚀 Quick Start (Windows)

1. **Download the latest release ZIP** from the [Releases](https://github.com/aliffarzanzim/zimrx/releases) tab.
2. Extract the archive to any folder (e.g. `C:\ZimRx`).
3. Double-click **`start.bat`**.
4. ZimRx automatically opens in your browser at `http://localhost:8080`.

---

## 🛠️ Development & Deployment

### Requirements
* PHP 8.2+ with PDO SQLite extension (or Docker / FrankenPHP)
* Caddy / Apache / Nginx (optional for production web servers)

### Database Migrations
Database schemas and versioning are managed through `DbMigrator.php`. Run the application or execute `db.php` to apply any pending schema migrations automatically:
```bash
php -r "require_once 'application/db.php';"
```

---

## 📄 License

ZimRx is licensed under the **GNU Affero General Public License v3.0 (AGPL-3.0)**. See the [LICENSE](LICENSE) file for details.
