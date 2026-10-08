# Locales

Translatable text lives here, one folder per language code (`en`, `bn`, `fr`, ...).

```
locales/
    en/   instructions.json  doses.json  durations.json  advices.json
    bn/   instructions.json  doses.json  durations.json  advices.json
```

## Adding a language

1. Copy the `en` folder and rename it with the language code (for example `fr`).
2. Translate the values in each file. Keep the keys unchanged.
3. Run `php application/tests/run_tests.php`.

Missing entries fall back to English, so a partial translation still works.

## Format

Keys are the row ids from the static reference database, so they must not change.
Each entry has the visible `text` and an optional `alias` (extra words doctors type to find it).

```json
{
    "1": { "text": "Before meal", "alias": "ac, before food" },
    "2": { "text": "After meal" }
}
```

`advices.json` has two sections, `categories` and `items`.

Interface labels (`ui.json`) will be added per language when the first interface module is converted.
