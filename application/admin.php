<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';

$page_title = 'ZimRx — Admin Dashboard';
$extra_css = ['assets/css/admin.css'];

$counts = [
    'doctors'      => (int)$pdo->query("SELECT COUNT(*) FROM zimrx_doctors WHERE is_active = 1")->fetchColumn(),
    'assistants'   => (int)$pdo->query("SELECT COUNT(*) FROM zimrx_user_accounts WHERE role = 'assistant' AND is_active = 1")->fetchColumn(),
    'patients'     => (int)$pdo->query("SELECT COUNT(*) FROM zimrx_patients")->fetchColumn(),
    'today'        => (int)$pdo->query("SELECT COUNT(*) FROM zimrx_appointments WHERE date(appointment_date) = date('now','localtime')")->fetchColumn(),
];

include 'header.php';
?>

<main class="adm-page">

    <!-- ── Top Bar ──────────────────────────────────────────── -->
    <div class="adm-topbar">
        <div>
            <div class="adm-eyebrow">Admin Dashboard</div>
            <h1 class="adm-title">Control Center</h1>
        </div>
        <div class="adm-topbar-actions">
            <a href="admin_doctors.php" class="btn btn-primary adm-btn-new">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Doctor
            </a>
        </div>
    </div>

    <!-- ── Stat Cards ────────────────────────────────────────── -->
    <div class="adm-stats">
        <div class="adm-stat adm-stat--blue">
            <div class="adm-stat-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <div class="adm-stat-body">
                <span class="adm-stat-label">Active Doctors</span>
                <span class="adm-stat-value"><?= $counts['doctors'] ?></span>
            </div>
        </div>
        <div class="adm-stat adm-stat--violet">
            <div class="adm-stat-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="adm-stat-body">
                <span class="adm-stat-label">Active Assistants</span>
                <span class="adm-stat-value"><?= $counts['assistants'] ?></span>
            </div>
        </div>
        <div class="adm-stat adm-stat--emerald">
            <div class="adm-stat-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            </div>
            <div class="adm-stat-body">
                <span class="adm-stat-label">Total Patients</span>
                <span class="adm-stat-value"><?= $counts['patients'] ?></span>
            </div>
        </div>
        <div class="adm-stat adm-stat--amber">
            <div class="adm-stat-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div class="adm-stat-body">
                <span class="adm-stat-label">Today's Appointments</span>
                <span class="adm-stat-value"><?= $counts['today'] ?></span>
            </div>
        </div>
    </div>

    <!-- ── Navigation Sections ───────────────────────────────── -->
    <div class="adm-section-label">People & Access</div>
    <div class="adm-nav-grid">

        <a href="admin_doctors.php" class="adm-nav-card">
            <div class="adm-nav-icon adm-nav-icon--blue">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <div class="adm-nav-body">
                <div class="adm-nav-title">Doctors</div>
                <div class="adm-nav-desc">Add, edit, and manage doctor profiles</div>
            </div>
            <div class="adm-nav-arrow">›</div>
        </a>

        <a href="admin_assistants.php" class="adm-nav-card">
            <div class="adm-nav-icon adm-nav-icon--violet">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="adm-nav-body">
                <div class="adm-nav-title">Assistants</div>
                <div class="adm-nav-desc">Manage front-desk and receptionist accounts</div>
            </div>
            <div class="adm-nav-arrow">›</div>
        </a>

        <a href="admin_assistants.php#mapping" class="adm-nav-card">
            <div class="adm-nav-icon adm-nav-icon--cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            </div>
            <div class="adm-nav-body">
                <div class="adm-nav-title">Doctor–Assistant Mapping</div>
                <div class="adm-nav-desc">Control which assistants serve which doctors</div>
            </div>
            <div class="adm-nav-arrow">›</div>
        </a>

        <a href="admin_patients.php" class="adm-nav-card">
            <div class="adm-nav-icon adm-nav-icon--emerald">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            </div>
            <div class="adm-nav-body">
                <div class="adm-nav-title">Patient Directory</div>
                <div class="adm-nav-desc">Demographics only — no clinical data visible</div>
            </div>
            <div class="adm-nav-arrow">›</div>
        </a>

    </div>

    <div class="adm-section-label">Finance & Operations</div>
    <div class="adm-nav-grid">

        <a href="admin_payments.php" class="adm-nav-card">
            <div class="adm-nav-icon adm-nav-icon--amber">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
            <div class="adm-nav-body">
                <div class="adm-nav-title">Payments</div>
                <div class="adm-nav-desc">Billing records and payment overview</div>
            </div>
            <div class="adm-nav-arrow">›</div>
        </a>

        <a href="appointments.php" class="adm-nav-card">
            <div class="adm-nav-icon adm-nav-icon--sky">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div class="adm-nav-body">
                <div class="adm-nav-title">Queue & Scheduling</div>
                <div class="adm-nav-desc">Daily appointments, wait times, cancellations</div>
            </div>
            <div class="adm-nav-arrow">›</div>
        </a>

    </div>

    <div class="adm-section-label">Compliance & Settings</div>
    <div class="adm-nav-grid">

        <a href="admin_settings.php" class="adm-nav-card adm-nav-card--coming">
            <div class="adm-nav-icon adm-nav-icon--slate">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            </div>
            <div class="adm-nav-body">
                <div class="adm-nav-title">System Settings</div>
                <div class="adm-nav-desc">App-wide preferences and configuration</div>
            </div>
            <span class="adm-badge-soon">Soon</span>
        </a>

        <a href="#" class="adm-nav-card adm-nav-card--coming">
            <div class="adm-nav-icon adm-nav-icon--rose">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <div class="adm-nav-body">
                <div class="adm-nav-title">Audit & Compliance Logs</div>
                <div class="adm-nav-desc">Immutable access log — who viewed what and when</div>
            </div>
            <span class="adm-badge-soon">Soon</span>
        </a>

        <a href="#" class="adm-nav-card adm-nav-card--coming">
            <div class="adm-nav-icon adm-nav-icon--indigo">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div class="adm-nav-body">
                <div class="adm-nav-title">Performance Dashboard</div>
                <div class="adm-nav-desc">Appointment stats, patient flow analytics</div>
            </div>
            <span class="adm-badge-soon">Soon</span>
        </a>

    </div>

</main>

<?php include 'footer.php'; ?>

