# Contributing to ZimRx

Thank you for your interest in contributing to ZimRx. ZimRx is an open-source, local-first Electronic Medical Record (EMR) and digital prescription system designed for solo physicians, health practitioners, and clinics in low-resource and low-bandwidth environments.

---

## Core Development Philosophy

Every contribution to ZimRx should align with these core architectural pillars:

1. **Patient Privacy & Local-First:** Patient health data belongs strictly to the clinic and patient. We maintain zero telemetry, zero analytics tracking, and zero cloud lock-in.
2. **Sub-Millisecond Ergonomics:** Solo clinicians frequently see 60 to 100 patients per session. The interface must be lightweight, instant, and 100% keyboard-navigable (Tab, Enter, Alt+Arrows).
3. **Zero Build Step:** The frontend is built in pure Vanilla JS and modern CSS design tokens without complex build pipelines (Webpack, Vite, Node). Files can be edited, inspected, and run offline immediately.
4. **Clinical Safety First:** Prescriptions and medical advice impact human lives. Every calculation, drug interaction check, and pediatric dosing formula must be accurate, deterministic, and verifiable against medical literature.

---

## Getting Started

### Prerequisites
* PHP 8.2+ with PDO SQLite extension
* SQLite3
* FrankenPHP + Caddy (or Apache / Nginx for production setups)

### Running the Test Suite
Before writing code or submitting changes, ensure the automated test suite passes:
```bash
php application/tests/run_tests.php
```
All assertions must pass with 0 failures.

---

## Coding Standards

### Backend (PHP)
* **Strict Types:** Every PHP script must begin with `declare(strict_types=1);` immediately after the opening `<?php` tag.
* **100% Parameterized PDO:** Never interpolate raw variables into SQL queries. Always use PDO prepared statements with explicit parameter binding (`:param`).
* **Transaction Safety:** Wrap multi-row or multi-table mutations in explicit transactions (`beginTransaction()`, `commit()`, `rollBack()`) and catch `Throwable` to handle both runtime exceptions and PHP 8 engine errors.
* **CSRF Protection:** State-mutating endpoints (POST/PUT/DELETE) must verify CSRF tokens via `zimrx_verify_csrf()`.
* **Path Traversal Neutralization:** All file handling logic must sanitize user-supplied filenames using `basename()`.

### Frontend (Vanilla JS & CSS)
* **Vanilla Architecture:** Use native JavaScript (ES6+). Do not introduce heavy frontend frameworks (React, Vue, Angular).
* **XSS Defense:** Prefer `textContent` or DOM element creation over raw `.innerHTML` when rendering dynamic user data.
* **Icon System:** Do not paste raw inline `<svg>` blocks into modules or views. Register new icons in `application/public/api/zrx_icons.php` and render them via `zrx_icon()` in PHP or `ZimRxIcon.render()` in JavaScript.
* **Design Tokens:** Always consume semantic CSS variables from `application/public/assets/css/layout/global.css` (e.g. `var(--zrx-primary)`, `var(--zrx-border)`) rather than hardcoding hex colors.

---

## Policy on AI-Assisted Contributions

Patient safety comes first. While AI tools can be used to accelerate development and write tests, all clinical rules, drug interactions, edge cases and database logic are strictly designed and checked by real physicians and human developers.

* **Human Accountability:** Every submitted line of code must be reviewed, tested, and fully understood by the human contributor. You must be able to explain the logic and design decisions behind your pull request.
* **Clinical Integrity:** Medical algorithms, dosage calculators, and clinical data structures cannot be accepted without verification against standard clinical reference texts or pharmacopoeias.
* **FLOSS License Compliance:** All contributions must be strictly compatible with the **GNU Affero General Public License v3.0 (AGPL-3.0)**. Ensure that AI-assisted code does not reproduce copyrighted code from third-party proprietary repositories.
* **Pull Request Transparency:** If an AI coding assistant was used for significant portions of the contribution, please disclose the tool and describe how the logic was verified in your PR description.

---

## Pull Request Process

1. **Discuss Before Building:** Before starting work on a new feature, clinical module, or database change, please reach out first to discuss what you want to contribute. You can open an issue or email me directly at `aliffarzanzim@gmail.com`. This ensures the change aligns with real clinical workflows and avoids duplicate or conflicting effort.
2. Fork the repository and create your branch from `master`.
3. Ensure your changes follow the coding standards and file conventions.
4. Run the automated test suite (`php application/tests/run_tests.php`) and ensure 100% pass rate.
5. If adding new features or endpoints, add corresponding regression tests in `application/tests/run_tests.php`.
6. Submit a pull request with a clear, concise summary of changes, rationale, and testing confirmation.

---

## License

By contributing to ZimRx, you agree that your contributions will be licensed under the [GNU Affero General Public License v3.0 (AGPL-3.0)](LICENSE).
