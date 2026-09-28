<?php
$page_title = "Manufacturer Preferences - ZimRx";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/db.php';

$doctorId = max(1, (int)(function_exists('current_user_doctor_id') ? current_user_doctor_id() : 1));

require_once __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="assets/css/pages/manufacturer_preferences.css?v=<?= filemtime(__DIR__ . '/assets/css/pages/manufacturer_preferences.css') ?>">

<div class="mpref-container">
    <!-- Hero Header -->
    <div class="mpref-header">
        <div class="mpref-title-wrap">
            <h1>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" color="#2563eb"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                <span>Manufacturer Preferences &amp; Priority Ranking</span>
            </h1>
            <p>Customize which pharmaceutical companies appear first during drug autocomplete in the prescription editor. Prioritize your trusted brands, reorder via drag-and-drop, or hide companies you do not wish to see.</p>
        </div>

        <div class="mpref-header-actions">
            <button type="button" class="btn-mpref-danger" id="btn-reset-defaults" title="Reset all rankings back to national defaults">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                <span>Reset Defaults</span>
            </button>
            <button type="button" class="btn-mpref-primary" id="btn-save-all">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                <span>Save Changes</span>
            </button>
        </div>
    </div>

    <!-- KPI Summary Badges Bar -->
    <div class="mpref-summary-bar">
        <div class="mpref-summary-pill">
            <span class="pill-dot dot-blue"></span>
            <span>My Prioritized Companies:</span>
            <strong id="count-custom" style="color: #2563eb;">0</strong>
        </div>
        <div class="mpref-summary-pill">
            <span class="pill-dot dot-amber"></span>
            <span>Hidden Companies:</span>
            <strong id="count-hidden" style="color: #d97706;">0</strong>
        </div>
        <div class="mpref-summary-pill">
            <span class="pill-dot dot-slate"></span>
            <span>Total Available in Drug DB:</span>
            <strong id="count-total">0</strong>
        </div>
    </div>

    <!-- Main Dual Panels Grid -->
    <div class="mpref-grid">
        <!-- Left Panel: My Custom Ranking (Drag and Drop) -->
        <div class="mpref-panel">
            <div class="mpref-panel-header">
                <h2>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" color="#2563eb"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                    <span>My Ranked Priority (Shows First in Search)</span>
                </h2>
                <span id="rank-header-sub">Drag rows or use arrows to reorder</span>
            </div>

            <div class="mpref-list" id="custom-rank-list">
                <!-- Populated dynamically -->
            </div>
        </div>

        <!-- Right Panel: All Manufacturers Directory -->
        <div class="mpref-panel">
            <div class="mpref-panel-header">
                <h2>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" color="#64748b"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    <span>Manufacturer Directory</span>
                </h2>
                <span id="directory-count">676 Companies</span>
            </div>

            <div class="mpref-search-toolbar">
                <div class="mpref-search-box">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" color="#94a3b8"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" id="dir-search-input" placeholder="Search company name (e.g. Square, Renata, Incepta)..." autocomplete="off">
                </div>

                <div class="mpref-chips">
                    <button type="button" class="mpref-chip active" data-filter="all">All</button>
                    <button type="button" class="mpref-chip" data-filter="available">Available</button>
                    <button type="button" class="mpref-chip" data-filter="hidden">Hidden</button>
                </div>
            </div>

            <div class="mpref-list" id="directory-list">
                <!-- Populated dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- Floating Toast Feedback -->
<div class="mpref-toast" id="mpref-toast">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
    <span id="toast-message">Changes saved successfully!</span>
</div>

<script src="assets/js/pages/manufacturer_preferences.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/manufacturer_preferences.js') ?>"></script>

<?php require_once __DIR__ . '/footer.php'; ?>
