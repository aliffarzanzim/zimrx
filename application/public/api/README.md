# ZimRx API Architecture & Endpoint Catalog

## Design Rationale

ZimRx implements a **zero-framework, micro-endpoint architecture**. Each API endpoint is a self-contained PHP controller responsible for a specific clinical or operational action.

### Key Architectural Standards
1. **Physical Webroot Boundary:** Located in `public/api/`, accessible via standard HTTP requests.
2. **Central Path & Env Discovery:** Every endpoint requires `public/init.php` at line 1 to resolve `ZIMRX_BASE_DIR` dynamically across local USB portable runtimes, VPS, and shared hosting.
3. **Defense-in-Depth Authentication:** Every endpoint enforces `require_login()`. Unauthenticated requests immediately return `HTTP 401 Unauthorized` with JSON error payload `{ "ok": false, "error": "Authentication required." }`.
4. **Tenant Isolation:** All mutations derive `doctor_id` exclusively from authenticated session state (`current_user_doctor_id()`), never from untrusted client parameters.
5. **Mutation Security:** State-altering endpoints (POST/PUT/DELETE) validate session CSRF tokens (`zimrx_verify_csrf()`) or strict content types.

---

## Endpoint Catalog by Clinical Domain

### 1. Prescribing & Encounters (Rx Domain)
Endpoints handling prescription authoring, dosage templates, regimen persistence, and adaptive learning.

| Endpoint | Method | Security | Description |
|---|---|---|---|
| `save_prescription_visit.php` | POST | Auth + CSRF | Atomic persistence of prescription visits, vitals, optimistic locking revision checks, and sync journal logging |
| `rx_regimen.php` | GET, POST, DELETE | Auth + CSRF | Regimen CRUD operations (custom doses, frequencies, durations) |
| `rx_template.php` | GET, POST | Auth + CSRF | Standard dose, duration, and instruction template management |
| `rx_user_templates.php` | GET, POST | Auth + CSRF | Doctor-specific custom drug templates with usage frequency tracking |
| `rx_learn.php` | POST | Auth + CSRF | Adaptive frequency learning algorithm updating regimen suggestions based on prescribing patterns |
| `rx_phrase_suggestions.php` | GET | Auth | Unified prescribing phrase auto-completion for dose, duration, and instruction fields (replaces removed legacy `getdose`, `getduration`, `getinstruction` micro-endpoints) |
| `user_drugs.php` | GET, POST, DELETE | Auth + CSRF | Doctor custom drug catalog additions, overrides, and personal generics |

---

### 2. Clinical Catalogs & Search (Read Domain)
High-performance read endpoints backed by SQLite FTS5 full-text indexing and normalized static medical reference tables.

| Endpoint | Method | Security | Description |
|---|---|---|---|
| `search_drug.php` | GET | Auth | SQLite FTS5 prefix-stemmed drug search across brand names, generics, and strengths |
| `drug_lookup.php` | GET | Auth | Detailed pharmaceutical monograph lookup (indications, contraindications, dosage instructions) |
| `drug_explorer.php` | GET | Auth | Hierarchical ATC classification and therapeutic drug category explorer |
| `check_drug_interactions.php` | POST | Auth | Offline local pairwise drug-drug interaction checker evaluating active prescription drugs |
| `search_dx.php` | GET | Auth | ICD-11 and clinical diagnosis lookup |
| `search_ix.php` | GET | Auth | Clinical lab test and diagnostic imaging investigations catalog |
| `search_ix_param.php` | GET | Auth | Quantitative lab test parameters, biological reference ranges, and unit standards |
| `search_advice.php` | GET | Auth | Medical advice, lifestyle guidance, and patient dietary suggestions |
| `get_static_advice.php` | GET | Auth | Standardized advice templates by disease category |
| `pc_suggestions.php` | GET | Auth | Presenting complaints (P/C) autocomplete with localized clinical terms |
| `pc_learn.php` | POST | Auth + CSRF | Adaptive frequency tracking for presenting complaints |
| `search_address.php` | GET | Auth | Patient address, district, and locality autocomplete |
| `get_occupations.php` | GET | Auth | Patient occupation vocabulary lookup |

---

### 3. Appointments & Queue Management (Operations Domain)
Daily patient queue, consultation tokens, scheduling overrides, and financial collection.

| Endpoint | Method | Security | Description |
|---|---|---|---|
| `appointments.php` | GET, POST, PUT, DELETE | Auth + CSRF | Complete appointment queue management, token sequencing, status transitions, and slot overrides |
| `billings_api.php` | GET, POST | Auth + CSRF | Consultation billing, payment receipt generation, fee discount recording, and financial reconciliation |
| `patient_referrals.php` | GET, POST | Auth + CSRF | Referral doctor directory lookup, referral source tracking, and autocomplete |

---

### 4. EMR & Longitudinal Patient Trajectory
Patient master index, historical encounters, vital sign graphing, and encrypted report streaming.

| Endpoint | Method | Security | Description |
|---|---|---|---|
| `emr_api.php` | GET, POST | Auth + CSRF | Longitudinal patient profile, visit timeline history, vitals trends graphing, and sync tombstones |
| `upload_report.php` | POST | Auth + CSRF | Patient diagnostic report upload (PDF/PNG/JPEG) with MIME sniffing and sanitized filenames |
| `view_report.php` | GET | Auth + Traversal Guard | Authenticated clinical report streamer with strict directory traversal prevention and nosniff headers |
| `mobile_sync.php` | GET, POST | Auth | Offline local network mobile upload queue consumption and active patient binding |

---

### 5. Tenant Customization & Clinical Setup
Doctor-specific prescription layout formatting, print margins, and examination parameters.

| Endpoint | Method | Security | Description |
|---|---|---|---|
| `header_edit_ajax.php` | POST | Auth + CSRF | Prescription letterhead configuration, typography settings, and doctor credential lines |
| `header_onboarding_ajax.php` | POST | Auth + CSRF | Initial wizard setup for clinic branding and contact headers |
| `first_launch_save.php` | POST | Setup Token (Pre-auth) | First-run setup initialization, master admin password hashing, and clinic registration (one-time setup token gated) |
| `print_setup_save.php` | POST | Auth + CSRF | Print layout margins, page size (A4, Letter, Custom), and padding geometry persistence |
| `save_print_setup.php` | POST | Auth + CSRF | Secondary print configuration handler for custom layout preferences |
| `reset_print_setup.php` | POST | Auth + CSRF | Reset print geometry to factory defaults |
| `save_interface_layout.php` | POST | Auth + CSRF | Doctor workspace layout customization (left/right module card ordering and visibility) |
| `manufacturer_preference_api.php` | GET, POST | Auth + CSRF | Pharmaceutical brand weighting and manufacturer preference scoring |
| `pc_settings.php` | GET, POST | Auth + CSRF | Presenting complaints custom terms and category configuration |
| `medical_history_settings.php` | GET, POST | Auth + CSRF | Past medical history questionnaire templates and group settings |
| `physical_examination_settings.php`| GET, POST | Auth + CSRF | Physical examination parameters and clinical findings options |
| `occupation_settings.php` | GET, POST | Auth + CSRF | Custom occupation vocabulary management |
| `address_settings.php` | GET, POST | Auth + CSRF | Custom address and regional locality entries |
| `instruction_template.php` | GET, POST | Auth + CSRF | Custom patient instruction templates |
| `save_custom_address.php` | POST | Auth + CSRF | Add new doctor-defined address entries |
| `save_custom_occupation.php` | POST | Auth + CSRF | Add new doctor-defined occupation entries |

---

### 6. Media Assets & Visual Components
Brand imagery, digital seals, signature stamps, and icon rendering.

| Endpoint | Method | Security | Description |
|---|---|---|---|
| `upload_header_logo.php` | POST | Auth + CSRF | Clinic logo upload with safe image validation and randomized filenames |
| `delete_header_logo.php` | POST | Auth + CSRF | Remove active clinic header logo |
| `list_header_logos.php` | GET | Auth | List uploaded clinic logo media |
| `upload_full_body_header.php` | POST | Auth + CSRF | Full-width vector/raster header banner upload with strict SVG sanitization |
| `upload_background_image.php` | POST | Auth + CSRF | Prescription watermark and background canvas upload |
| `delete_background_image.php` | POST | Auth + CSRF | Remove custom watermark image |
| `list_background_images.php` | GET | Auth | List uploaded background media |
| `upload_seal_and_stamp.php` | POST | Auth + CSRF | Doctor signature seal and certification stamp upload |
| `delete_seal_and_stamp.php` | POST | Auth + CSRF | Remove custom stamp asset |
| `list_seal_and_stamps.php` | GET | Auth | List available seal and stamp assets |
| `chat.php` | GET, POST | Auth + CSRF | Local LAN clinic communication, realtime assistance notifications, and authenticated private attachment streaming (`?action=view_attachment`) |
| `zrx_icons.php` | GET | Public Registry | Single-source-of-truth SVG icon registry mapping exported to client-side window.ZimRxIconsMap |
