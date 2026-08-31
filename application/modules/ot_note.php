<style>
.ot-wrapper {
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

.ot-header {
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

.ot-title {
    font-weight: 600;
    font-size: 0.9rem;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.ot-print-options {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    column-gap: 48px;
    row-gap: 8px;
    min-width: 0;
    font-size: 0.82rem;
    font-weight: 600;
    padding: 8px 12px;
    background: #e2e8f0;
    border-top: 1px solid #c2cfdd;
    border-bottom: 1px solid #aab9cc;
}

.ot-print-options label {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    padding: 0 0.75rem;
    border: 1px solid #7d90a8;
    border-radius: 6px;
    background: #f8fafc;
    color: #0f172a;
    cursor: pointer;
    white-space: nowrap;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
}

.ot-print-options label:has(input:checked) {
    border-color: var(--primary);
    background: #eff6ff;
    color: #0f3ea8;
    box-shadow: inset 0 0 0 1px rgba(37, 99, 235, 0.28), 0 1px 2px rgba(15, 23, 42, 0.08);
}

.ot-print-options input[type="radio"],
.ot-print-options input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.ot-print-group {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    min-width: 0;
}

.ot-print-group-title {
    color: #1e293b;
    font-size: 0.76rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

.ot-tabs-container {
    background: #e2e8f0;
    padding: 10px 16px 0;
    display: flex;
    justify-content: center;
    align-items: flex-end;
    gap: 6px;
}

.ot-tab-list {
    display: flex;
    align-items: flex-end;
    gap: 6px;
    min-width: 0;
    flex-wrap: wrap;
    justify-content: center;
}

.ot-tab {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-bottom: none;
    padding: 8px 20px;
    border-radius: 7px 7px 0 0;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-dark);
    cursor: pointer;
    box-shadow: 0 -2px 4px rgba(0,0,0,0.02);
    transition: all 0.15s ease;
    margin-bottom: -1px;
}

.ot-tab:hover {
    background: #f1f5f9;
}

.ot-tab.active {
    background: #ffffff;
    color: var(--primary);
    border-color: #94a3b8;
    z-index: 3;
    position: relative;
    box-shadow: 0 -2px 6px rgba(15,23,42,0.08);
}

@media (max-width: 760px) {
    .ot-print-options {
        align-items: stretch;
    }
    .ot-print-group {
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
    }
}

.ot-panes {
    background: #fff;
    min-height: 250px;
}

.ot-pane {
    display: none;
    flex-direction: column;
}

.ot-pane.active {
    display: flex;
}

/* OT Table Styles */
.ot-table-container {
    width: 100%;
    overflow-x: auto;
}

.ot-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.ot-table th {
    background: #cbd5e1;
    color: var(--text-dark);
    font-weight: 600;
    font-size: 0.85rem;
    padding: 0.5rem;
    border: none;
    border-right: var(--zrx-table-border-width, 2px) solid #94a3b8;
    border-bottom: var(--zrx-table-border-width, 2px) solid #94a3b8;
    text-align: left;
}

.ot-table td {
    border: none;
    border-right: var(--zrx-table-border-width, 2px) solid var(--zrx-table-border, #b8c5d6);
    border-bottom: var(--zrx-table-border-width, 2px) solid var(--zrx-table-border, #b8c5d6);
    padding: 0;
    vertical-align: middle;
    background: #ffffff;
}

.ot-table thead th { border-top: none; }
.ot-table thead th:first-child { border-left: none; }
.ot-table thead th:last-child { border-right: none; }

.ot-action {
    text-align: center !important;
    background: #f1f5f9 !important;
    vertical-align: middle !important;
    padding: 0 !important;
}

.ot-del { padding: 0 !important; }
.ot-del button {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 20px !important;
    height: 20px !important;
    min-width: 20px !important;
    min-height: 20px !important;
    padding: 0 !important;
    margin: 0 auto !important;
    box-sizing: border-box !important;
    background: #ffffff;
    border: 1px solid #94a3b8;
    border-radius: 3px;
    font-size: 0.72rem;
    font-weight: 700;
    color: #334155;
    cursor: pointer;
    box-shadow: 0 1px 1px rgba(0,0,0,0.05);
    line-height: 1;
}
.ot-del button:hover { background: #fee2e2; border-color: #ef4444; color: #dc2626; }

.ot-drag { color: #64748b; padding: 0 !important; }
.ot-row-move-btn {
    width: 100%;
    height: 30px;
    border: none;
    background: transparent;
    color: inherit;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: grab;
    padding: 0;
}
.ot-row-move-btn:hover { background: #e2e8f0; color: #334155; }
.ot-row-move-btn:active { cursor: grabbing; }
.ot-row-move-btn svg { pointer-events: none; }

.ot-input {
    width: 100%;
    min-height: 38px;
    border: none;
    padding: 8px 0.6rem;
    font-size: 15px;
    background: transparent;
    outline: none;
    font-family: var(--font-family);
    color: var(--text-dark);
    display: block;
    resize: none;
    overflow: hidden;
}

.ot-input:focus {
    background: #f0f9ff;
    box-shadow: inset 0 0 0 2px rgba(37,99,235,0.3);
}

.ot-input[readonly] {
    color: #0f172a;
    background: #f8fafc;
    font-weight: 600;
}

.ot-footer {
    display: flex;
    justify-content: center;
    border-top: var(--zrx-table-border-width, 2px) solid var(--zrx-table-border, #b8c5d6);
    background: #f8fafc;
    padding: 0.45rem 0;
}

.ot-add-row-btn {
    min-width: 110px;
    height: 30px;
    border: 1px solid #94a3b8;
    border-radius: 8px;
    background: #ffffff;
    color: #0f172a;
    font: inherit;
    font-size: 0.84rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
}

.ot-add-row-btn:hover {
    background: #eff6ff;
    border-color: var(--primary);
    color: var(--primary);
}

/* NicEdit Force Overrides for Responsive 100% Width */
.ot-nicedit-container .nicEdit-panelContain {
    border-top: none !important;
    border-left: none !important;
    border-right: none !important;
    border-bottom: 1px solid var(--border-color) !important;
    background: #f1f5f9 !important;
    width: 100% !important;
    border-radius: 0 !important;
}

.ot-nicedit-container .nicEdit-main {
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

.ot-nicedit-container .nicEdit-main:focus {
    background: #f8fafc;
}
</style>

<?php
$ot_predefined_particulars = [
    'Date',
    'Time',
    'Indication',
    'Name of the operation',
    'Procedure',
    'Pre Operative Dx',
    'Post Operative Finding',
    'Type of Anesthesia',
    'Name of the Surgeon',
    'Name of the Anesthesiologist',
    'Name of the assistant',
    'Hospital Stay Time',
    'Special Note'
];
?>

<div class="ot-wrapper">
    <!-- Main Header -->
    <div class="ot-header">
        <span class="ot-title">OT Note</span>
    </div>

    <div class="ot-print-options">
        <div class="ot-print-group" role="radiogroup" aria-label="OT note print status">
            <span class="ot-print-group-title">Print</span>
            <label><input type="radio" name="ot_print_status" value="print" checked> Print OT Note</label>
            <label><input type="radio" name="ot_print_status" value="no_print"> Do not print OT Note</label>
        </div>
        <div class="ot-print-group" role="radiogroup" aria-label="OT note print layout">
            <span class="ot-print-group-title">Layout</span>
            <label><input type="radio" name="ot_print_layout" value="sidebar" checked> Sidebar</label>
            <label><input type="radio" name="ot_print_layout" value="full_page"> Full Page</label>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="ot-tabs-container">
        <div class="ot-tab-list">
            <button type="button" class="ot-tab active" data-target="tab-ot-table">OT Notes</button>
            <button type="button" class="ot-tab" data-target="tab-ot-salient">Salient Feature</button>
            <button type="button" class="ot-tab" data-target="tab-ot-history">History</button>
            <button type="button" class="ot-tab" data-target="tab-ot-others">Others</button>
        </div>
    </div>

    <!-- Tabs Content -->
    <div class="ot-panes">
        
        <!-- 1. OT Notes Table Tab -->
        <div class="ot-pane active" id="tab-ot-table">
            <div class="ot-table-container">
                <table class="ot-table" id="ot-table">
                    <colgroup>
                        <col style="width: 32px;">
                        <col style="width: 35%;">
                        <col>
                        <col style="width: 38px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th style="text-align: center;"></th>
                            <th>Particulars</th>
                            <th>Value</th>
                            <th style="text-align: center;"></th>
                        </tr>
                    </thead>
                    <tbody id="ot-tbody">
                        <?php foreach ($ot_predefined_particulars as $particular): ?>
                        <tr class="ot-row pc-row" draggable="true">
                            <td class="ot-action ot-del pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
                            <td><input type="text" class="ot-input" value="<?= htmlspecialchars($particular) ?>" readonly></td>
                            <td><textarea class="ot-input ot-value-input" autocomplete="off" rows="1"></textarea></td>
                            <td class="ot-action ot-drag pc-action pc-drag">
                                <button type="button" class="ot-row-move-btn pc-row-move-btn zrx-drag-handle" title="Move Row">
                                    <?= zrx_icon('move', 14) ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <!-- Empty rows at the bottom -->
                        <?php for ($i = 0; $i < 3; $i++): ?>
                        <tr class="ot-row pc-row" draggable="true">
                            <td class="ot-action ot-del pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
                            <td><input type="text" class="ot-input" placeholder="Custom Particular..." autocomplete="off"></td>
                            <td><textarea class="ot-input ot-value-input" autocomplete="off" rows="1"></textarea></td>
                            <td class="ot-action ot-drag pc-action pc-drag">
                                <button type="button" class="ot-row-move-btn pc-row-move-btn zrx-drag-handle" title="Move Row">
                                    <?= zrx_icon('move', 14) ?>
                                </button>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <div class="ot-footer">
                <button type="button" class="ot-add-row-btn">Add More</button>
            </div>
        </div>

        <!-- 2. Salient Feature Tab -->
        <div class="ot-pane ot-nicedit-container" id="tab-ot-salient">
            <textarea id="ot-salient-editor" style="width: 100%;"></textarea>
        </div>

        <!-- 3. History Tab -->
        <div class="ot-pane ot-nicedit-container" id="tab-ot-history">
            <textarea id="ot-history-editor" style="width: 100%;"></textarea>
        </div>

        <!-- 4. Others Tab -->
        <div class="ot-pane ot-nicedit-container" id="tab-ot-others">
            <textarea id="ot-others-editor" style="width: 100%;"></textarea>
        </div>

    </div>

    <!-- Template for new table rows -->
    <template id="ot-row-template">
        <tr class="ot-row pc-row" draggable="true">
            <td class="ot-action ot-del pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
            <td><input type="text" class="ot-input" placeholder="Custom Particular..." autocomplete="off"></td>
            <td><textarea class="ot-input ot-value-input" autocomplete="off" rows="1"></textarea></td>
            <td class="ot-action ot-drag pc-action pc-drag">
                <button type="button" class="ot-row-move-btn pc-row-move-btn zrx-drag-handle" title="Move Row">
                    <?= zrx_icon('move', 14) ?>
                </button>
            </td>
        </tr>
    </template>
</div>

<!-- Load NicEditor -->
<script src="vendor/nicedit/nicEdit-latest.js?v=<?= filemtime(__DIR__ . '/../vendor/nicedit/nicEdit-latest.js') ?>"></script>
<script src="vendor/nicedit/nicEdit-zimrx-custom.js?v=<?= filemtime(__DIR__ . '/../vendor/nicedit/nicEdit-zimrx-custom.js') ?>"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // --- Tab Switching Logic ---
    const tabs = document.querySelectorAll('.ot-tab');
    const panes = document.querySelectorAll('.ot-pane');
    let editorsInitialized = false;

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class
            tabs.forEach(t => t.classList.remove('active'));
            panes.forEach(p => p.classList.remove('active'));
            
            // Set active class
            this.classList.add('active');
            const targetId = this.getAttribute('data-target');
            document.getElementById(targetId).classList.add('active');

            // Initialize NicEditors the first time a rich text tab is opened
            if (!editorsInitialized && targetId !== 'tab-ot-table') {
                initOTEditors();
                editorsInitialized = true;
            }
        });
    });

    // --- NicEditor Initialization ---
    function initOTEditors() {
        if (typeof bkLib === 'undefined') return;

        const config = {
            fullPanel: true,
            iconsPath: 'vendor/nicedit/images/nicEditIcons-latest.gif'
        };

        new nicEditor(config).panelInstance('ot-salient-editor');
        new nicEditor(config).panelInstance('ot-history-editor');
        new nicEditor(config).panelInstance('ot-others-editor');

        // Force 100% width on containers generated by NicEdit
        setTimeout(() => {
            const containers = document.querySelectorAll('#tab-ot-salient .nicEdit-panelContain, #tab-ot-history .nicEdit-panelContain, #tab-ot-others .nicEdit-panelContain');
            const mains = document.querySelectorAll('#tab-ot-salient .nicEdit-main, #tab-ot-history .nicEdit-main, #tab-ot-others .nicEdit-main');
            
            containers.forEach(c => c.style.width = '100%');
            mains.forEach(m => {
                m.style.width = '100%';
                if (m.parentElement) m.parentElement.style.width = '100%';
            });
        }, 100);
    }

    // --- Table Row Management ---
    const tbody = document.getElementById('ot-tbody');
    const template = document.getElementById('ot-row-template');
    const addBtn = document.querySelector('.ot-add-row-btn');

    addBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const tr = template.content.firstElementChild.cloneNode(true);
        tbody.appendChild(tr);
        
        const textarea = tr.querySelector('.ot-value-input');
        if (textarea) {
            autoResizeTextarea(textarea);
            tr.querySelector('input').focus();
        }
    });

    document.getElementById('ot-table').addEventListener('click', (e) => {
        const delBtn = e.target.closest('.ot-del button');
        if (delBtn) {
            e.stopPropagation();
            delBtn.closest('tr').remove();
        }
    });

    // --- Textarea Auto-Resize ---
    function autoResizeTextarea(textarea) {
        if (!textarea || textarea.tagName !== 'TEXTAREA') return;
        textarea.style.transition = 'none';
        textarea.style.height = '0';
        const natural = Math.max(38, textarea.scrollHeight);
        textarea.style.height = natural + 'px';
        requestAnimationFrame(() => { textarea.style.transition = ''; });
    }

    document.querySelectorAll('#ot-table textarea').forEach(autoResizeTextarea);

    document.getElementById('ot-table').addEventListener('input', (e) => {
        if (e.target.tagName === 'TEXTAREA') {
            autoResizeTextarea(e.target);
        }
    });

});
</script>
