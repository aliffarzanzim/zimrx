<style>
.tp-wrapper {
    border: none;
    border-radius: 0;
    background: transparent;
    overflow: visible;
    display: flex;
    flex-direction: column;
    box-shadow: none;
    font-family: var(--font-family);
    margin: -2px;
    width: calc(100% + 4px);
}

.tp-header {
    background: #cbd5e1;
    color: var(--text-dark);
    height: 32px;
    box-sizing: border-box;
    padding: 0 0.85rem;
    display: flex;
    justify-content: center;
    align-items: center;
    border-bottom: 1px solid #94a3b8;
    text-align: center;
}

.tp-title {
    font-weight: 600;
    font-size: 0.9rem;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.tp-sub-options {
    background: #f8fafc;
    border-bottom: 1px solid var(--border-color);
    padding: 10px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    font-size: 0.85rem;
    color: var(--text-dark);
    font-weight: 600;
}

.tp-sub-group {
    display: flex;
    gap: 18px;
    align-items: center;
}

.tp-sub-options label {
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
}

.tp-sub-options input[type="radio"] {
    accent-color: var(--primary);
    cursor: pointer;
    width: 14px;
    height: 14px;
    margin: 0;
}

.tp-search-bar {
    display: flex;
    border-bottom: 1px solid var(--border-color);
    background: #fff;
}

.tp-search-field {
    flex: 1;
    padding: 8px 12px;
    position: relative;
}

.tp-search-field:first-child {
    border-right: 1px solid var(--border-color);
}

.tp-search-field input {
    width: 100%;
    height: 32px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 0 10px 0 32px;
    font-size: 0.85rem;
    font-family: inherit;
    color: var(--text-dark);
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.tp-search-field input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

.tp-search-icon {
    position: absolute;
    left: 20px;
    top: 50%;
    transform: translateY(-50%);
    width: 15px;
    height: 15px;
    color: #64748b;
    pointer-events: none;
}

.tp-editor-container {
    width: 100%;
    background: #fff;
}

/* NicEdit Force Overrides for Responsive 100% Width */
.nicEdit-panelContain {
    border-top: none !important;
    border-left: none !important;
    border-right: none !important;
    border-bottom: 1px solid var(--border-color) !important;
    background: #f1f5f9 !important;
    width: 100% !important;
    border-radius: 0 !important;
}

.nicEdit-main {
    width: 100% !important;
    box-sizing: border-box !important;
    padding: 12px 16px !important;
    min-height: 250px !important;
    outline: none !important;
    font-family: var(--font-family) !important;
    font-size: 0.95rem !important;
    line-height: 1.5 !important;
    color: var(--text-dark) !important;
}

.nicEdit-main:focus {
    background: #f8fafc;
}
</style>

<div class="tp-wrapper">
    <!-- Main Header -->
    <div class="tp-header">
        <span class="tp-title">Note Pad</span>
    </div>

    <!-- Print Options Sub-Header -->
    <div class="tp-sub-options">
        <div class="tp-sub-group">
            <label><input type="radio" name="tp_sidebar_print" value="print_sidebar"> Print the left sidebar (P/C, P/E, Dx)</label>
            <label><input type="radio" name="tp_sidebar_print" value="no_sidebar" checked> Do not print left sidebar</label>
        </div>
        <div class="tp-sub-group">
            <label><input type="radio" name="tp_print_status" value="print" checked> Print Note Pad</label>
            <label><input type="radio" name="tp_print_status" value="no_print"> Do not print Note Pad</label>
        </div>
    </div>

    <!-- Search Fields -->
    <div class="tp-search-bar">
        <div class="tp-search-field">
            <svg class="tp-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" placeholder="Documents" autocomplete="off">
        </div>
        <div class="tp-search-field">
            <svg class="tp-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" placeholder="Drugs" autocomplete="off">
        </div>
    </div>

    <!-- Text Editor -->
    <div class="tp-editor-container">
        <textarea id="textpad-editor" style="width: 100%;"></textarea>
    </div>
</div>

<!-- Load NicEditor & Initialize -->
<script src="vendor/nicedit/nicEdit-latest.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    if (typeof bkLib !== 'undefined') {
        bkLib.onDomLoaded(function() {
            // Initialize NicEditor
            var tpEditor = new nicEditor({
                fullPanel: true,
                iconsPath: 'vendor/nicedit/images/nicEditIcons-latest.gif'
            }).panelInstance('textpad-editor');

            // Force NicEditor container and main editing area to be 100% width
            // NicEditor sometimes sets hardcoded inline widths, this overrides it
            setTimeout(() => {
                const nicContainers = document.querySelectorAll('.nicEdit-panelContain');
                const nicMains = document.querySelectorAll('.nicEdit-main');

                nicContainers.forEach(container => {
                    container.style.width = '100%';
                });

                nicMains.forEach(main => {
                    main.style.width = '100%';
                    if (main.parentElement) {
                        main.parentElement.style.width = '100%';
                    }
                });
            }, 50);
        });
    }
});
</script>
