<?php
declare(strict_types=1);
?>
<div id="viewEditor" class="geo-view-wrapper" hidden>
    <div class="geo-stats-bar" id="statsBar">
        <span class="geo-stat-pill">Total: <strong>0</strong></span>
        <div class="geo-manifest-badge" id="statManifestBadge">
            <span>Version: <strong>Not specified</strong></span>
            <span>Released: <strong>Not specified</strong></span>
            <span>Updated: <strong><?= date('Y-m-d') ?></strong></span>
        </div>
    </div>

    <main class="geo-workspace">
        <section class="geo-card geo-tree-pane">
            <div class="geo-card-header">
                <span>Hierarchy Navigator</span>
                <span id="treeFilteredCount" class="geo-badge-count"></span>
            </div>
            <div class="geo-tree-search">
                <input type="text" id="treeSearchInput" class="geo-input-search" placeholder="Filter by name or identifier..." aria-label="Filter hierarchy places">
                <button type="button" class="geo-btn geo-btn-sm" id="btnExpandAll">
                    <?= \GeoStudio\IconRegistry::svg('plus', 11) ?>
                    <span>Expand</span>
                </button>
                <button type="button" class="geo-btn geo-btn-sm" id="btnCollapseAll">
                    <?= \GeoStudio\IconRegistry::svg('chevron-up', 11) ?>
                    <span>Collapse</span>
                </button>
            </div>
            <div class="geo-tree-list" id="treeContainer">
                <div class="geo-empty-state">Loading dataset...</div>
            </div>
        </section>

        <section class="geo-card geo-editor-pane">
            <div class="geo-card-header">
                <span id="editorTitle">Hierarchy Inspector</span>
            </div>

            <div class="geo-breadcrumbs" id="editorBreadcrumbs">
                <span>Select the pack root or a place on the left to inspect and edit.</span>
            </div>

            <div class="geo-editor-body" id="editorFormContainer" hidden>
                <div class="geo-form-grid">
                    <div class="geo-form-group full" id="placeNamesFields"></div>
                    <div class="geo-form-group" id="grpCurNodeCode">
                        <label class="geo-label" id="lblCurNodeCode" for="inpCode">Postal Code</label>
                        <input type="text" id="inpCode" class="geo-input" placeholder="e.g. 1216">
                    </div>
                    <div class="geo-form-group">
                        <label class="geo-label" id="lblCurNodeDepth">Depth Level</label>
                        <input type="text" id="inpDepth" class="geo-input geo-input-readonly" readonly>
                    </div>
                    <div class="geo-form-group full">
                        <div class="geo-classification-header">
                            <label class="geo-label geo-label-m0" id="lblCurNodeType">Unit Type</label>
                            <button type="button" class="geo-btn geo-btn-sm" id="btnManageHierarchyLevels" aria-haspopup="dialog" aria-controls="modalHierarchyLevelsBackdrop">
                                <?= \GeoStudio\IconRegistry::svg('edit', 12) ?>
                                <span>Manage hierarchy depth levels</span>
                            </button>
                        </div>
                        <div class="type-pills" id="typePillsContainer"></div>
                    </div>
                </div>

                <div class="subplaces-header">
                    <div class="geo-subplaces-meta">
                        <h4 class="geo-subplaces-title">
                            <span id="subPlacesLabel">Sub-Units</span> (<span id="subPlacesCount">0</span>)
                        </h4>
                        <input type="text" id="subplacesFilterInput" class="geo-input geo-input-sm" placeholder="Filter sub-units..." aria-label="Filter child places">
                    </div>
                    <button type="button" class="geo-btn geo-btn-sm" id="btnAddSubPlace">
                        <?= \GeoStudio\IconRegistry::svg('plus', 11) ?>
                        <span>Add Child Unit</span>
                    </button>
                </div>

                <div class="subplaces-table-wrapper">
                    <table class="subplaces-table">
                        <thead>
                            <tr>
                                <th>Default Language Name</th>
                                <th>Other Language Names</th>
                                <th>Type</th>
                                <th id="thSubplacesCode">Identifier</th>
                                <th class="geo-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="subplacesTableBody"></tbody>
                    </table>
                </div>

                <nav class="geo-pagination" id="subplacesPagination" aria-label="Sub-unit pages" hidden>
                    <button type="button" class="sub-btn" id="subplacesPrevious">Previous</button>
                    <span id="subplacesPageStatus" aria-live="polite"></span>
                    <button type="button" class="sub-btn" id="subplacesNext">Next</button>
                </nav>

                <div class="geo-editor-footer-actions">
                    <button type="button" class="sub-btn sub-btn-del" id="btnDeleteNode">
                        <?= \GeoStudio\IconRegistry::svg('trash', 12) ?>
                        <span>Delete Unit</span>
                    </button>
                </div>
            </div>

            <div id="editorEmptyState" class="geo-empty-state">
                No unit selected. Click the pack root or a place in the navigator to inspect and edit.
            </div>
        </section>
    </main>
</div>
