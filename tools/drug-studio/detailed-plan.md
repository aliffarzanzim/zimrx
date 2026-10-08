# Drug Studio Implementation Plan

## Goal

Build a standalone, local Drug Studio in `ZimRx/tools/drug-studio/` for curating the drug catalog used by ZimRx. Follow the modular structure and visual language of Geo Studio and Locale Studio. A contributor should be able to search, add, review, and edit the catalog, then produce deterministic SQL seed files and companion manifests that can be reviewed and committed.

The first screen has three areas:

- Generics
- Formularies
- Dosage Forms

The studio is a local development tool. Keep catalog data offline and local. Do not connect to remote services or add telemetry.

## Scope

Implement catalog browsing and CRUD for:

- Generic medicines, therapeutic classifications, and indications.
- Country formulary packs and their trade names and manufacturers.
- Dosage forms.
- Seed SQL compilation and generated manifest metadata.

Do not implement Libsodium, Ed25519 signing, differential patches, client-side patch verification, or automatic production database updates in this phase. Those are later work.

## Repository and Data Source Preflight (Resolved & Verified)

The canonical curation database and seed/rebuild pipeline have been identified, verified, and integrated:
- **Canonical Curation Database**: `ZimRx/tools/drug-studio/data/zimrx_drugs_curation.db`.
- **Central PDO Layer**: All operations use `DbConnections::openSqlite($curationDb)`.
- **Source Plain-Text Seeds**:
  - `application/systemdata/seeds/zimrx_drugs.sql` (Universal core catalog: 2,331 generics, 129 dosage forms, 520 therapeutic classes, 2,863 indications, etc.)
  - `application/systemdata/seeds/zimrx_drugs_bd.sql` (Bangladesh country pack: 37,260 commercial brands in `drug_brand_bd` and 751 manufacturers in `drug_manufacturer_bd`)
- **Deterministic Export Engine**: `ZimRx\DrugStudio\DrugCatalogExporter` dumps deterministic SQL directly into `systemdata/seeds/` and updates companion JSON manifests in `tools/drug-studio/manifests/`.
- **Production Shipped Database Rebuild Command**:
  ```bash
  php application/systemdata/seeds/build_db.php --force --country=BD
  ```
  This compiles `zimrx_drugs.db` (including dynamic materialization of `drug_prescribe_bd` and `fts_drug_prescribe` FTS5 index) in ~1.8 seconds. Production clinic database `zimrx_userdata.db` remains strictly isolated.

## Design and Code Requirements

- Match the Geo Studio and Locale Studio 50px slate header (`--zrx-slate-800`), spacing, typography, and ZimRx blue primary color (`--zrx-primary`, currently `#2563eb`; hover `--zrx-primary-hover`, currently `#1d4ed8`). Use shared background, card, text, and border tokens instead of ad hoc colors.
- Use the ZimRx icon registry. Do not add emoji, raw copied SVG, decorative tags, or novelty buttons.
- Reuse `.zrx-dropdown` and `.zrx-dropdown-item` behavior and appearance: square corners, full border, neutral slate hover (`#868e96`) with white text, and matching mouse and keyboard selection.
- Keep markup, styles, and behavior separate. No inline CSS, inline event handlers, or executable JavaScript in PHP views. Load dedicated files from `assets/css/` and `assets/js/`. If client-side code needs the icon map, expose it as data for an external script or through a local JSON endpoint.
- Keep the PHP entry point, views, API, database access, validation, and SQL export in small, purpose-specific files, following the Geo/Locale Studio layout.
- Use `declare(strict_types=1);`, prepared statements, server-side validation, and atomic transactions for writes. Do not accept table names or SQL fragments from request data.
- Use text-safe DOM rendering for catalog values. Escape server-rendered values and avoid inserting user or database text as executable HTML.
- Keep comments short and limited to non-obvious behavior. Do not add meta-comments about AI, vibe coding, code generation, or the quality of the code.
- Bind the local development server to `127.0.0.1` by default.

## Main Screens

### Studio Home

Show three simple cards for Generics, Formularies, and Dosage Forms. Each card has a registered icon, a short description, and a clear action. Use the same restrained card and header style as the other studios.

### Generics Workspace

Use a searchable list and a detail panel, following the ZimRx drug database viewer's general layout. Provide these six tabs in this order:

1. Generic
2. Add a Generic
3. Drug Classification
4. Add a Drug Classification
5. Drug Indication
6. Add a Drug Indication

The search/list pane filters the selected record type. Selecting a row loads its details in the right pane. The detail pane uses accordions. Every accordion has its own Edit action; editing one section must not replace unrelated fields. Provide Save and Cancel actions, show validation errors beside the relevant field, and refresh the selected detail after a successful save.

Use these accordion groups for a generic record:

- Names and classification: `generic_name`, `us_generic_name`, `who_atc_class`.
- Safety flags: `is_antibiotic`, `is_high_alert_medicine`, `is_safe_in_pregnancy`, `is_safe_in_lactation`, `require_renal_adjustments`, `is_safe_in_hepatic_impairment`, `is_safe_in_paediatric`, `requires_tapering`.
- Warnings and precautions: `immediate_warning`, `precaution`, `contra_indication`, `side_effect`.
- Indications and mechanism: `indication`, `mode_of_action_summary`, `mode_of_action_flow`, `interaction`.
- Pregnancy and lactation: `pregnancy_category`, `pregnancy_modern_category`, `pregnancy_category_and_lactation_note`, `pregnancy_trimester_safety`.
- Dosing and administration: `adult_dose`, `child_dose`, `paediatric_calc_parameter`, `renal_dose`, `administration`.
- Overdose, storage, and counselling: `overdose_effect`, `overdose_treatment`, `storage`, `counselling_pearl`, `pubmed_query_base`.

An empty clinical field means “Not recorded”; do not display it as a safety assurance or as proof that no risk exists. The generic schema is `drug_generic` with `generic_id INTEGER PRIMARY KEY` and the fields listed above. Preserve IDs during edits. Assign new IDs inside the write transaction using a collision-safe strategy compatible with the current seed builder.

For classifications, support `class_name`, `category_name`, `subcategory_name`, and linked generics in `generic_ids`. For indications, support `indication_name` and linked generics in `generic_ids`. Both link fields are currently stored as text in the seed schema. Preserve the repository's established serialization format, validate every selected generic ID against `drug_generic`, and do not trust a client-submitted ID list. Use the existing `class_id` and `indication_id` primary key conventions when adding records.

### Formularies Workspace

Show country cards, initially Bangladesh (BD), with an Add Country action following Geo Studio's country-pack creation flow. Discover existing packs from the verified catalog/manifest source rather than hard-coding BD as the only possible country. Each card should show country name and code, catalog version, last updated date, and trade-name count when available.

Opening a country shows a searchable trade-name list on the left and an accordion detail panel on the right. Include the `Formularies` list tab and `Add a Trade Name` form. Every detail accordion has an Edit action.

The BD seed schema currently defines:

- `drug_brand_bd`: `brand_id`, `generic_id`, `manufacturer_id`, `brand_name`, `form`, `form_variant`, `strength`, `price`, `packsize`.
- `drug_manufacturer_bd`: `id`, `manufacturer_name`, `manufacturer_name_short`.

Country table names follow the existing `drug_brand_{country}` and `drug_manufacturer_{country}` seed convention. Generate identifiers only from a validated uppercase ISO 3166-1 alpha-2 country code; SQL parameters cannot bind identifiers. Keep the country code out of free-form SQL input.

The Add a Trade Name form must require the user to select a generic from the core `drug_generic` catalog using search. Do not allow an unlinked generic name or an inline generic creation path. If the desired generic is absent, direct the contributor to add it in Generics first. Provide country-pack manufacturer selection; if a manufacturer needs to be added, use a small country-scoped form that writes the manufacturer record before or in the same transaction as its trade name. Keep brand IDs stable on edit and collision-safe on insert. Preserve the source schema's text representation for IDs, strength, price, and pack size.

### Dosage Forms Workspace

Use the same searchable list and accordion detail pattern. Support adding, viewing, and editing every field from `drug_dosage_form`:

- `form` (required and unique), `form_variant`, `icon`, `prefix_short`, `prefix_full`, `suffix`, `suffix_generic`.
- `add_strength_brand`, `add_strength_generic`, `prescribe_short`, `prescribe_full`, `generic_subtitle`, `form_order`.

Show the prescription-format fields as text, but use explicit controls for boolean/integer flags. Do not silently invent an icon value; use the project's registered icon names or an existing dosage-form icon convention. Preserve `form_id` when editing and let the established schema assign new IDs.

## Search, Empty States, and Contributor Wording

Search generics, classifications, indications, country trade names, manufacturers, and dosage forms by their visible names and relevant aliases. Search should be server-bounded, parameterized, and return a predictable result shape. Support normal keyboard navigation in search results.

Use cautious, factual wording when a catalog search has no match. For example: “No matching records in this catalog. The item may be listed under another name or may not yet be included. Verify against an authoritative source before adding it.” For country products, say “No matching trade names in this country pack” rather than “not sold,” “unavailable,” or “does not exist.” These messages describe the catalog search only and must not imply a market-wide or clinical conclusion. Do not create a fake database record for a no-result state.

## Writes and Validation

- Perform every create/update as a POST action with server-side validation and an explicit transaction. Roll back the complete operation if a related record or validation step fails.
- Enforce required names, unique dosage-form names, valid country codes, valid linked generic IDs, and schema-compatible values. Check duplicates before insert and report a useful field-level error.
- Use a fixed allowlist of tables and columns for each operation. Keep request payloads limited to the fields supported by the selected form.
- Keep generic, classification, indication, manufacturer, and trade-name identifiers stable after creation. Allocate new IDs in a transaction and check for collisions.
- Do not infer clinical facts. The UI edits supplied catalog values and clearly distinguishes recorded data from blank or unverified data.

## SQL Export and Manifest

Provide an explicit export/compile action for the core catalog and the selected country pack. Generate deterministic SQL compatible with the verified seed/rebuild pipeline, with explicit column lists and records sorted by stable primary key. Escape SQL values correctly, include a transaction, and do not emit runtime search indexes or other build-generated tables unless the existing seed pipeline requires them.

Generate the SQL dump and a companion JSON manifest, following Geo Studio's SQL-plus-manifest workflow. Ask for manifest information in a modal when required values are missing and make it editable later. Include:

- Dataset name and scope (`core` or country code/name).
- Version, author, and contributor list.
- License identifier and source/attribution details. Do not assume a license for external drug data.
- Release notes.
- Release date and last-updated date, with last-updated set automatically on export.
- Generated table/record counts, dump filename, and SHA-256 checksum.

Write generated files to a temporary path first, validate the result, then replace the target files atomically. Show the exact output paths and a concise success or error message. Export must not sign, patch, or apply updates to a running clinic database.

## Suggested Module Boundaries

Keep the first implementation close to the existing studio structure:

```text
tools/drug-studio/
  index.php
  src/
    DrugStudioApi.php
    DrugCatalogRepository.php
    DrugCatalogValidator.php
    DrugCatalogExporter.php
  views/
    appbar.php
    hub.php
    workspace.php
    modals.php
  assets/
    css/drug-studio.css
    js/drug-studio.js
  README.md
  detailed-plan.md
```

The API routes actions and returns JSON. The repository owns bounded queries and writes through the centralized PDO layer. The validator owns field and relationship validation. The exporter owns deterministic SQL and manifest generation. Keep country-specific SQL identifier handling inside the repository/exporter and validate it before use.

## Build Order

1. Resolve the curation database, source seed files, and rebuild command. Record the verified schema and output paths in the Drug Studio README.
2. Build the shared studio shell: appbar, three home cards, modular views, theme, icon registry, local API routing, and external CSS/JS.
3. Implement read-only search and detail screens for generics, classes, indications, BD formulary, and dosage forms. Confirm the viewer matches the intended list/detail accordion pattern.
4. Implement generic, classification, indication, manufacturer, trade-name, and dosage-form writes with validation and transactions.
5. Add country-card discovery and Add Country metadata flow.
6. Add deterministic seed export and manifest editing/generation. Compare a generated dump with the established rebuild pipeline before treating it as commit-ready.
7. Update the README with local launch instructions, data-source paths, export paths, and the supported scope. Keep Libsodium work clearly listed as future work.

## Completion Criteria

- Home presents only the three requested areas and follows the Geo/Locale Studio theme.
- Generics has all six requested tabs, searchable results, right-side accordions, and per-accordion editing.
- Formularies discovers BD and supports adding country packs. A trade name cannot be saved without selecting a catalog generic.
- Dosage Forms supports all current schema fields and enforces the unique form name.
- No-result wording describes only the catalog search and does not imply a product is absent from the market.
- No inline CSS or executable inline JavaScript, emoji, raw SVG copies, AI meta-comments, or verbose generated commentary appears in the implementation.
- Writes use the centralized PDO path, prepared statements, validation, and atomic transactions.
- Core and country SQL exports are deterministic, importable by the verified build pipeline, and accompanied by complete manifests.
- No Libsodium patch or signature workflow is included in this phase.

## Original Prompt

The following is the original request, retained for the implementation agent:

```text
Write the drug studio same theme as zimrx, like same primary color, same header, same dropdown style etc. Even on geo studio and locale studio we used the same. Everything should be modular, see geo and locale studio, it must not contain ai dumped code or ai Over explaining comments. And sometimes ai use functions like aintivibecoding { or comment like antivibe coding or removrd ai coding pattern, you must never do so. Dont use inline js or css, keep them separate like geo.

At first it will offer few cards, Like Generics, Formularies, Dosage Forms

If entered on generics, you will be able to see several tabs like zimrx drug db viewer kind of samme design with a search,
like Generic, Add a Generic, Drug Classification, Add a Drug Classification, Drug Indication, Add a Drug indication, and on right side same as zimrx drug viewerr type accordion like window. There will be edit button for every accordion. For the field what u need on add a new generic see drug db schema

Then for formularies, country wise cards like now only bd exist, so bd will be loaded, add country option like geo. For each country same drug db viewer type look, Formularies, Add a Trade name(for it when chosing generic generic will be search from the generic db, he must chose from there, if drug doesnt exist he has to add the generic first) accordion like look,&#x20;

For them some common suggestion will be there like Not found or Not availble. Dont just add bluntly add what is professional sounding of I havent search for it or i havent found it but it can be there type so legal c9nsequence can be avoided.

Same for dosage forms

No need to implement the libsodium patch, we will do it later, it will write to db and cam compiled to sql dump for git commit, auto manifest generation, manifest info asking like geo studio

Dont use emoj,i use icons, no fancy ai designing or tags or buttons

improve the plan for drug studio here C:\Zimrx-working\ZimRx\tools\drug-studio\detailed-plan.md

so another agent can read from here and build from  here, include my rawprompt also
```
