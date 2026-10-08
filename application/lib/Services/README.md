# ZimRx `lib/Services/` - Namespaced Service Layer

## Overview

This directory contains PHP classes in the `ZimRx\Services` namespace (PSR-4).

These classes wrap or extend functionality from `lib/*.php` procedural functions where OOP is
beneficial - e.g., when complex state, dependency injection, or unit-testability is required.

## Autoloading

`composer.json` maps `ZimRx\\Services\\` to `lib/Services/` via PSR-4.
Run `composer dump-autoload` after adding a new class.

## Rules

- Class files MUST declare `strict_types=1` and `namespace ZimRx\Services;`.
- Classes MUST NOT duplicate logic that already exists in `lib/*.php`. Call the procedural function instead.
- Only inject `PDO` instances obtained from `DbConnections::*()` - never create new `PDO` connections.

## Classes

| Class | Primary Responsibility |
|---|---|
| `AppointmentService` | Queue scheduling, time calculations, status workflows, and doctor isolation |
| `BillingService` | Transactional consultation fee reconciliation, invoicing, and accounting |
| `ClinicalCalculationService` | Clinical formulas (CKD-EPI 2021 eGFR, BMI, Mosteller BSA, unit conversions) |
| `CountryService` | ISO 3166-1 country catalog, regulatory bodies, and national formulary packs |
| `DrugInteractionService` | Master pairwise drug interaction screening and clinical severity analysis |
| `LocaleService` | Multilingual JSON catalog loading (`application/locales/`), aliases, and fallbacks |
| `PatientIpsExportService` | Standard HL7 FHIR IPS (International Patient Summary) JSON document export |
| `PrintLayoutService` | Prescription layout geometry, canvas margins, and letterhead positioning |
| `SyncJournalService` | Append-only clinical delta log and RFC 4122 v4 UUID generation for replication |
