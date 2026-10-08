# ZimRx Locale Studio

Companion developer tool and translation studio for managing, translating, and clinically verifying ZimRx language packs and dynamic clinical locale catalogs.

## Features

- **Universal Design & Global Icon Registry**: Built with the exact same slate navbar, color tokens, and vector icon registry (`zrx_icons.php`) as ZimRx and Geo Studio.
- **Dynamic Catalog Discovery**: Automatically scans and supports all `.json` files inside `application/locales/{code}/` (`instructions.json`, `first_launch.json`, `advices.json`, `doses.json`, `durations.json`, `help_guidelines.json`, and any new catalog files).
- **Side-by-Side Dual-Pane Workspace**: Synchronized paired translation view comparing a reference language (default: `en` English) with the target language (e.g. `bn` Bengali or any target locale).
- **Two Option Status Toggles per Catalog**:
  - `[Mark as Complete]`: Toggles catalog between Draft and Completed.
  - `[Mark as Verified]`: Toggles doctor-verified clinical translation badge, tracking clinician name and verification date.
- **Dynamic Catalog Scaffolding**: Create new `.json` catalogs dynamically across all languages with a single click.
- **Language Pack Scaffolding**: Create new language packs (e.g. `es`, `fr`, `ar`, `hi`, `ur`, `de`) with automated skeleton cloning from English.
- **Keyboard Shortcuts**: `Ctrl+S` quick save, `Escape` to close modals.

## Running Locale Studio

Run the PHP development server pointing to this folder:

```powershell
php -S 127.0.0.1:8086 -t C:\Zimrx-working\ZimRx\tools\locale-studio
```

Then open `http://127.0.0.1:8086` in your browser.

## Validation & Testing

Verify that all locale catalogs and JSON syntax remain valid:

```powershell
php application/tests/run_tests.php
```
