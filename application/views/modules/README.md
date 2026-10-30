# Dashboard module manifests

Each built-in prescription workspace module keeps its JSON manifest beside its PHP view. The manifest filename must match the PHP view filename, such as `exam_breast.json` beside `exam_breast.php`. The loader rejects a manifest when those names do not match.

Required fields:

- `schema_version`: currently `1`.
- `id`: unique lowercase machine identifier.
- `name`: stable display and saved-layout name.
- `panel`: `left` or `right`.
- `view`: PHP view filename in this directory.
- `default_order`: positive default position, or `null` when the module has no default position.
- `enabled_by_default`: required boolean controlling whether the module appears in the default layout. Set it to `false` to keep the module available in Interface Settings without including it in the default layout. Modules with `default_order: null` must set this to `false`.
- `aliases`: optional saved-layout names that resolve to this module.

Manifests are data only. The loader resolves views within this directory and does not execute manifest content or discover extensions from `userdata/`.
