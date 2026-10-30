# Local extension and form data

This directory is private runtime storage. It is outside `public/` and must not be exposed by the web server.

- `incoming/`: offline package files awaiting a future verified installer. Files here are inert and must never be included or executed.
- `staging/`: temporary extraction and validation area for a future installer. Do not load code from this directory.
- `installed/`: reserved for explicitly installed extension packages. The current application does not discover or execute packages from this directory.
- `data/<extension-id>/`: private persistent state owned by an installed extension.
- `forms/exports/`: private storage reserved for manually managed declarative form packages. Import and export currently happen through the settings pages; active definitions and clinical submissions remain in the doctor-scoped userdata database.

Supported data-only packages are versioned JSON:

- `zimrx-patient-fields` version 1 contains patient-field definitions.
- `zimrx-visit-forms` version 1 contains visit form modules and field definitions.

Imports are validated by the application before they replace active definitions. Visit field values are stored in the visit clinical snapshot and included in visit revisions. A field must be explicitly marked `Include in print` before its value can appear in printed output. Packages cannot carry executable code.

Executable extension packages are not supported or auto-discovered. The `incoming`, `staging`, and `installed` directories are inert reservations, not an installer. Enabling a PHP extension would grant it the privileges of the ZimRx PHP process. A warning or package signature does not sandbox that code.

Before executable extensions are considered, the project needs a reviewed extension API, a capability model that limits host APIs, administrator approval, package identity and path validation, integrity and version records, upgrade rollback, and a documented threat model. If modules need an actual security boundary, they must run out of process with a narrow local protocol. Online discovery is a separate future service and must not be required for offline operation.

The first supported module API is data-only: a package may declare a title, approved field types, choices, requiredness, workspace column, and print inclusion. The host owns rendering, persistence, validation, and revision history. Packages cannot provide templates, hooks, SQL, or file paths.

Any future executable API must start with a small versioned event contract and explicit capabilities such as reading a visit snapshot, submitting validated form values, and contributing escaped print data. A capability list is an authorization boundary only if the host mediates every operation. It is not a sandbox for PHP running in the host process. An executable package must therefore remain disabled until an OS-enforced process boundary, local protocol, administrator trust flow, version compatibility, data migration, rollback, and network/filesystem denial have been designed and reviewed.

Form definitions must remain declarative. They may describe approved field types, validation, placement, and print visibility, but must not contain PHP, JavaScript, SQL, or arbitrary templates. Do not add a runtime loader until the extension API, permission model, upgrade behavior, and rollback process have been reviewed.
