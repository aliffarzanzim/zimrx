# Geo Studio

Developer tool, visual editor, and reference catalog for managing, compiling, and validating offline geographic administrative hierarchies in ZimRx.

## Purpose

ZimRx is an offline-first prescription software. When a doctor types a patient address, the system autocompletes administrative areas (such as district, upazila, thana, union, post office, village, or postal code) locally from `zimrx_static.db` without requiring an internet connection or external mapping APIs.

Geo Studio provides a unified, recursive workflow for community contributors and developers to add or update any country's administrative hierarchy without modifying database schemas.

## Data Structure

Source place and regional hierarchy datasets live in `tools/geo-studio/hierarchies/{ISO2}.json` (e.g. `BD.json`). Live catalog caches live in `tools/geo-studio/cache/`.

The catalog uses a **recursive polymorphic model** where every administrative node can optionally contain a `places` array of arbitrary child places at any depth.

### Schema Example (`hierarchies/BD.json`)

```json
{
  "manifest": {
    "country_code": "BD",
    "country_name": "Bangladesh",
    "default_lang": "en",
    "local_lang": "bn",
    "version": "1.0.0",
    "release_date": "2026-10-07",
    "last_updated": "2026-10-07",
    "author": "Alif Farzan Zim",
    "contributors": [
      "Alif Farzan Zim"
    ],
    "license": "CC-BY-4.0",
    "notes": "Universal hierarchical catalog of Bangladesh administrative places with full recursive nesting flexibility."
  },
  "hierarchy": {
    "model": "recursive_polymorphic",
    "child_key": "places",
    "levels": [
      {
        "depth": 1,
        "types": [
          { "key": "district", "name_en": "District", "name_loc": "জেলা" }
        ]
      },
      {
        "depth": 2,
        "types": [
          { "key": "upazila", "name_en": "Upazila", "name_loc": "উপজেলা" },
          { "key": "thana", "name_en": "Thana", "name_loc": "থানা" }
        ]
      },
      {
        "depth": 3,
        "types": [
          { "key": "union", "name_en": "Union", "name_loc": "ইউনিয়ন" },
          { "key": "postoffice", "name_en": "Post Office", "name_loc": "ডাকঘর" },
          { "key": "ward", "name_en": "Ward", "name_loc": "ওয়ার্ড" }
        ]
      },
      {
        "depth": 4,
        "types": [
          { "key": "village", "name_en": "Village", "name_loc": "গ্রাম" },
          { "key": "area", "name_en": "Area", "name_loc": "এলাকা" },
          { "key": "sector", "name_en": "Sector", "name_loc": "সেক্টর" },
          { "key": "moholla", "name_en": "Moholla", "name_loc": "মহল্লা" }
        ]
      }
    ]
  },
  "places": [
    {
      "en": "Dhaka",
      "loc": "ঢাকা",
      "type": "district",
      "places": [
        {
          "en": "Dhamrai",
          "loc": "ধামরাই",
          "type": "upazila",
          "places": [
            {
              "en": "Amta",
              "loc": "আমতা",
              "type": ["union", "postoffice"],
              "postcode": "1351"
            }
          ]
        },
        {
          "en": "Mirpur",
          "loc": "মিরপুর",
          "type": "thana",
          "places": [
            {
              "en": "Mirpur Section 1",
              "loc": "মিরপুর সেকশন ১",
              "type": "postoffice",
              "postcode": "1216",
              "places": [
                {
                  "en": "Block B",
                  "loc": "ব্লক বি",
                  "type": "moholla"
                }
              ]
            }
          ]
        }
      ]
    }
  ]
}
```

### Hierarchy Specification Fields

- **`levels`**: Array of administrative depth tiers (`depth: 1`, `depth: 2`, ...). Each tier declares its discrete entity `types` with explicit `key`, `name_en`, and `name_loc`.
- **`child_key`**: Universal array key for child entities (`places`).
- **`code` / `postcode`**: Any node at any depth can optionally carry a code identifier. The dataset's `manifest.code_label` dictates what the code represents (e.g. Postal Code, ZIP Code, Block Code, Facility ID).
- **`type`**: Supports string tags (e.g. `"type": "district"`) or arrays for places that share dual classifications (e.g. `"type": ["union", "postoffice"]`).

## Database Compilation

The compiler script (`compile_address_hierarchy.php`) reads hierarchy JSON files, resolves recursive parent-child relationships, and compiles records into the unified `zimrx_address_hierarchy` table inside `application/systemdata/database/zimrx_static.db`:

```sql
CREATE TABLE IF NOT EXISTS zimrx_address_hierarchy (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    country TEXT NOT NULL,
    parent_id INTEGER NULL,
    level INTEGER NOT NULL,
    place_type TEXT NOT NULL,
    name_en TEXT NOT NULL,
    name_loc TEXT NULL,
    postcode TEXT NULL,
    FOREIGN KEY(parent_id) REFERENCES zimrx_address_hierarchy(id)
);
```

### Column Definitions

- `id`: Unique auto-increment primary key.
- `country`: ISO 3166-1 alpha-2 uppercase country code (`BD`, `US`, `GB`, etc.).
- `parent_id`: Integer pointer to the parent place (enabling unbounded tree depth).
- `level`: Depth rank (1 = District/State, 2 = Upazila/Thana/County, 3 = Union/Post Office/Ward, 4 = Village/Area/Moholla).
- `place_type`: Classification tag (e.g. `district`, `upazila`, `thana`, `union`, `postoffice`, `union,postoffice`, `village`, `moholla`).
- `name_en`: Standard English / Latin transliteration.
- `name_loc`: Local script name (e.g. Bengali).
- `postcode`: Optional postal code.

## Visual Web GUI (Browser Studio)

Geo Studio includes a visual web interface matching ZimRx's design system:

```bash
php -S localhost:8085 -t tools/geo-studio
```
Then visit **`http://localhost:8085`** in your browser.

### Key Capabilities

1. **Interactive Places Navigator**: Search across thousands of nodes in real time with instant filtering by English text, local script, or postal code.
2. **Node Inspector & Hierarchy Editor**: Edit names, local language scripts, classification tags, and postal codes with breadcrumb navigation.
3. **Multi-Type Classifier**: Click pill badges to toggle single or dual types (such as `union` + `postoffice`).
4. **Sub-Places Manager**: Inspect, add, reorder, or delete child places at any level with live child counters.
5. **One-Click Compiling**: Recompile the active country dataset directly into `zimrx_static.db` via native PHP PDO SQLite with immediate status feedback.
6. **Save JSON & Active Timestamps**: Write formatted changes back to `hierarchies/{ISO2}.json`, actively updating `manifest.last_updated` to the current date and appending your name to `manifest.contributors`.
7. **Manifest & Contributor Settings**: Inspect and modify author, registered contributors, license, and release notes directly from the UI.
8. **GeoNames Global Importer**: One-click import for any country using GeoNames CC-BY 4.0 data dumps (`postalCodes.zip` or JSON). Automatically establishes `State -> County -> Place` hierarchies and embeds required legal attribution to GeoNames (geonames.org).
9. **Wikidata / Wikipedia Importer**: Direct live SPARQL pipeline querying Wikimedia administrative divisions, ceremonial counties, council areas, districts, and postal codes under Creative Commons CC0 1.0 Public Domain Dedication.

## Architecture & Modular Components

Geo Studio is designed with clean modularity so components can be used across ZimRx or in automated scripts:

- `src/CountryCatalog.php`: Country and custom regional pack repository. Manages reading, writing, listing, and validation with directory traversal protection.
- `src/GeoNamesImporter.php`: Downloads and parses GeoNames postal dumps (TSV or JSON) into hierarchical ZimRx places trees with CC-BY-4.0 attribution.
- `src/WikidataImporter.php`: Queries the Wikidata SPARQL endpoint natively in pure PHP with zero dependencies. Generates national administrative hierarchies under CC0-1.0.
- `src/GeoStudioApi.php`: Central JSON API router and controller for studio actions.
- `assets/css/geo-studio.css` & `assets/js/geo-studio.js`: Dedicated styling and client-side tree navigator and editor.
- `views/`: Clean view partials (`appbar.php`, `hub.php`, `workspace.php`, `modals.php`).
- `compile_address_hierarchy.php`: Standalone compiler translating JSON catalogs into SQLite `zimrx_static.db` and generating seed SQL dumps with companion JSON manifests.

## Data Licensing & Attributions

### GeoNames (CC-BY 4.0)
GeoNames data is licensed under the **Creative Commons Attribution 4.0 International License (CC-BY 4.0)**. Geo Studio embeds required legal attribution in `manifest.contributors`, `manifest.sources`, and `manifest.notes`.

### Wikidata (CC0 1.0)
Wikidata content is dedicated to the public domain under **Creative Commons CC0 1.0 Universal Public Domain Dedication**. Official CC0 attribution to Wikimedia contributors is embedded in the country manifest.

## CLI Command (100% Native PHP)

The compiler is written in pure native PHP with zero external dependencies or runtime requirements:

```bash
# Compile directly into zimrx_static.db
php tools/geo-studio/compile_address_hierarchy.php BD

# Compile to standalone SQL dump + companion JSON manifest in systemdata/seeds/
php tools/geo-studio/compile_address_hierarchy.php sql BD
```

## Adding a New Country or Region

1. Use the **+ New / Import** button in the Web GUI (or create `{CODE}.json` in `hierarchies/`).
2. Run the compiler:
   ```bash
   php tools/geo-studio/compile_address_hierarchy.php sql {CODE}
   ```
3. Verify the application test suite passes:
   ```bash
   php application/tests/run_tests.php
   ```
