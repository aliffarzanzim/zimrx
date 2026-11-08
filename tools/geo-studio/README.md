# Geo Studio

Geo Studio is a local editor for geographic hierarchy packs used by ZimRx. It creates and edits the full JSON seed files that the ZimRx database builder imports.

Geo Studio runs from `tools/geo-studio` inside a ZimRx source checkout. Pack JSON is stored in `application/systemdata/seeds` and is the single source used to build the static address database.

## Requirements

- PHP 8.2 or newer.
- A browser with JavaScript modules, `inert`, and `ResizeObserver` support.

## Start

From this directory, run the PHP development server:

```powershell
php -S 127.0.0.1:8085 -t .
```

Open `http://localhost:8085`. Keep the server bound to `127.0.0.1`: the PHP development server serves files from its document root directly, including pack JSON. On first launch, enter the contributor name used in pack metadata. The name is stored in this browser's local storage.

Choose **New** to create a blank pack. Existing packs can be opened, edited, saved, or deleted from the home page. Saving writes the complete pack to `application/systemdata/seeds/zimrx_address_pack_{pack_id}.json`. Deleting removes that JSON seed; the next static database build removes the pack from the generated database.

## Pack format

Packs are stored as `application/systemdata/seeds/zimrx_address_pack_{pack_id}.json`. The document has exactly three root fields: `manifest`, `hierarchy`, and `places`. A place has `names`, `type`, optional `code`, and `places`. Child nodes are always stored in `places`.

The manifest uses `pack_id` as the pack identity and filename. National packs use the ISO two-letter country code as their ID. Custom packs use a unique ID. `root_names` contains the localized root labels and must include every declared language. Place names and hierarchy type labels must include the default language; translations in other declared languages are optional. `manifest.code_label` describes the meaning of an optional place `code` value.

The application database builder imports every JSON seed into three tables: `zimrx_address_packs` stores pack metadata and JSON hierarchy definitions, `zimrx_address_places` stores the parent-child tree, and `zimrx_address_place_names` stores one localized name per place and language. `pack_id` identifies and scopes each pack and its places. Existing static databases must be rebuilt from `application/systemdata/seeds/build_db.php` after the schema change.

```json
{
  "manifest": {
    "pack_id": "BD",
    "pack_type": "national",
    "code_label": "Postal Code",
    "root_names": {
      "en": "Bangladesh",
      "bn": "বাংলাদেশ"
    },
    "default_lang": "en",
    "languages": ["en", "bn"],
    "version": "1.0.0",
    "release_date": "2026-11-08",
    "last_updated": "2026-11-08",
    "author": "Contributor Name",
    "contributors": ["Contributor Name"],
    "license": "CC-BY-4.0",
    "sources": [],
    "notes": ""
  },
  "hierarchy": {
    "levels": [
      {
        "depth": 1,
        "types": [
          {
            "key": "division",
            "names": {"en": "Division", "bn": "বিভাগ"}
          }
        ]
      }
    ]
  },
  "places": [
    {
      "names": {"en": "Dhaka", "bn": "ঢাকা"},
      "type": "division",
      "code": null,
      "places": []
    }
  ]
}
```

`hierarchy.levels` is ordered and numbered from 1. Each place node requires a `type` and a child `places` array, including leaf nodes. Unknown fields and alternate child keys are rejected.

## Layout

- `index.php` is the page entry point. Bind the development server to `127.0.0.1`; the API rejects requests that do not originate from loopback.
- `api/` contains the HTTP endpoints. `assets/` contains the stylesheets and JavaScript modules.
- `views/` contains the server-rendered page sections and dialog markup.
- `assets/css/app.css` is the stylesheet entry point. It imports design tokens, the foundation, and focused workspace, tree, sub-place, dialog, pack, and responsive stylesheets.
- `assets/js/app.js` is the JavaScript entry point. It composes focused native modules for API access, pack creation and editing, hierarchy navigation, and persistence.
- `lib/` contains pack validation, storage, and request security code.

Geo Studio uses PHP and native browser JavaScript. It has no frontend build step or JavaScript package manager.



## Configuration

| Variable | Default | Purpose |
| --- | --- | --- |
| `GEOSTUDIO_MAX_PACK_BYTES` | `4194304` | Maximum encoded size of one pack. |
| `GEOSTUDIO_MAX_PLACES` | `20000` | Maximum place count in one pack. |
