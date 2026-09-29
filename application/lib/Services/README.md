# ZimRx `lib/Services/` - Namespaced Service Layer

## Overview

This directory contains PHP classes in the `ZimRx\Services` namespace (PSR-4).

These classes wrap or extend functionality from `lib/*.php` procedural functions where OOP is
beneficial - e.g., when complex state, dependency injection, or unit-testability is required.

## Autoloading

`composer.json` maps `ZimRx\\Services\\` → `lib/Services/` via PSR-4.
Run `composer dump-autoload` after adding a new class.

## Rules

- Class files MUST declare `strict_types=1` and `namespace ZimRx\Services;`.
- Classes MUST NOT duplicate logic that already exists in `lib/*.php`. Call the procedural function instead.
- Only inject `PDO` instances obtained from `DbConnections::*()` - never create new `PDO` connections.

## Classes

| Class | Wraps | Purpose |
|-------|-------|---------|
| `AppointmentService` | `appointments.php` | Queue scheduling, time calculations, and revisit logic |
| `BillingService` | `billings.php` | Transactional consultation fee reconciliation & accounting |
| `DrugInteractionService` | `drug_catalog_lib.php` | Multi-drug interaction analysis |
| `PrintLayoutService` | `print_setup_lib.php` | Structured print layout resolution |
| `SyncJournalService` | `zimrx_sync_changes` | Append-only clinical delta log & UUID generation |
