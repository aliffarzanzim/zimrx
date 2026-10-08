# Security Policy

ZimRx handles patient records, prescriptions, and clinical data. Protecting patient privacy and preventing data leaks or corruption are core design requirements.

## Reporting Security Issues

If you find a security bug, data leak, or authentication issue, please report it privately:

- **Email:** `aliffarzanzim@gmail.com`
- **Subject:** `ZimRx Security: <brief description>`

Please do not open public GitHub issues for active vulnerabilities. Send the steps to reproduce, and I will review, verify the fix locally against our test suite, and push a patch to `master` as quickly as possible.

## Project Status

ZimRx is currently in active pre-release development. All security fixes are applied directly to the `master` branch.

## Security Model & Design

ZimRx is built as an offline-first system:

### 1. Local-First Data (Zero Telemetry)
All patient records, clinical notes, and databases live on the clinic machine inside `application/userdata/`. The software has no tracking scripts, analytics, or external cloud calls. Patient data stays on your machine.

### 2. Network & LAN Access
- ZimRx runs on `localhost` by default for the prescribing doctor workstation.
- When accessed across clinic Wi-Fi (such as an assistant managing queue tokens or patient check-in from a tablet), all traffic stays strictly inside the local area network (LAN) with no data leaving the clinic.
- State-changing actions (POST, PUT, DELETE) use session-bound CSRF tokens verified on the server.
- Session cookies are configured with `HttpOnly` and `SameSite=Lax` to prevent client-side script access.

### 3. Database Security
- Every SQL query across the codebase uses PDO prepared statements with parameter binding. Raw variable interpolation in queries is strictly avoided.
- Database writes use transactions with rollback on `Throwable` to prevent half-written records if an unexpected error occurs.
- Patient visit records use revision tracking to catch conflicting concurrent edits.

### 4. File Uploads & Medical Reports
- Uploaded patient reports and lab files in `application/userdata/uploads/reports/` cannot be accessed directly via web browser URLs. Web server rules block direct HTTP access.
- Reports are only viewable through `api/view_report.php`, which verifies that the doctor is logged in and authorized to view that patient's records.
- Uploaded files are renamed with random bytes (`random_bytes`) to avoid predictable URLs.
- SVG uploads (for prescription stamps and headers) are checked for embedded scripts, external entities (XXE), and event handlers before being saved.
