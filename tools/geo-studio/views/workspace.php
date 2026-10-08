<?php
declare(strict_types=1);
?>
<div id="viewEditor" class="geo-view-wrapper" style="display:none;">
    <div class="geo-stats-bar" id="statsBar">
        <span class="geo-stat-pill">Address Units: <strong id="statTotal">0</strong></span>
        <div class="geo-manifest-badge" id="statManifestBadge">
            <span>Author: <strong id="statAuthor">Alif Farzan Zim</strong></span>
            <span>Updated: <strong id="statUpdated"><?= date('Y-m-d') ?></strong></span>
        </div>
    </div>

    <main class="geo-workspace">
        <section class="geo-card geo-tree-pane">
            <div class="geo-card-header">
                <span>Hierarchy Navigator</span>
                <span id="treeFilteredCount" class="geo-badge-count"></span>
            </div>
            <div class="geo-tree-search">
                <input type="text" id="treeSearchInput" class="geo-input-search" placeholder="Filter by English, Local Name, or Postcode...">
                <button type="button" class="geo-btn geo-btn-sm" id="btnExpandAll">
                    <?= zrx_icon('plus', 11) ?>
                    <span>Expand</span>
                </button>
                <button type="button" class="geo-btn geo-btn-sm" id="btnCollapseAll">
                    <?= zrx_icon('chevron-up', 11) ?>
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
                <div>
                    <button type="button" class="geo-btn geo-btn-primary geo-btn-xs" id="btnUpdateNode">
                        <?= zrx_icon('check', 11) ?>
                        <span>Apply Changes</span>
                    </button>
                </div>
            </div>

            <div class="geo-breadcrumbs" id="editorBreadcrumbs">
                <span>Select an address unit on the left to inspect and edit.</span>
            </div>

            <div class="geo-editor-body" id="editorFormContainer" style="display:none;">
                <div class="geo-form-grid">
                    <div class="geo-form-group">
                        <label class="geo-label" for="inpNameEn">English Name</label>
                        <input type="text" id="inpNameEn" class="geo-input">
                    </div>
                    <div class="geo-form-group">
                        <label class="geo-label" for="inpNameLoc">Local Name</label>
                        <input type="text" id="inpNameLoc" class="geo-input">
                    </div>
                    <div class="geo-form-group">
                        <label class="geo-label" id="lblCurNodePostcode" for="inpPostcode">Postal Code</label>
                        <input type="text" id="inpPostcode" class="geo-input" placeholder="e.g. 1216">
                    </div>
                    <div class="geo-form-group">
                        <label class="geo-label">Depth Level</label>
                        <input type="text" id="inpDepth" class="geo-input geo-input-readonly" readonly>
                    </div>
                    <div class="geo-form-group full">
                        <div class="geo-classification-header">
                            <label class="geo-label geo-label-m0">Unit Classification</label>
                            <div class="geo-inline-actions">
                                <input type="text" id="inpNewTypeName" class="geo-input geo-input-sm" placeholder="New type (e.g. city)...">
                                <button type="button" class="geo-btn geo-btn-sm" id="btnAddTypeTag">
                                    <?= zrx_icon('plus', 11) ?>
                                    <span>Add Type</span>
                                </button>
                            </div>
                        </div>
                        <div class="type-pills" id="typePillsContainer"></div>
                    </div>
                </div>

                <div class="subplaces-header">
                    <div class="geo-subplaces-meta">
                        <h4 class="geo-subplaces-title">
                            Sub-Units (<span id="subPlacesCount">0</span>)
                        </h4>
                        <input type="text" id="subplacesFilterInput" class="geo-input geo-input-sm" placeholder="Filter sub-units...">
                    </div>
                    <button type="button" class="geo-btn geo-btn-sm" id="btnAddSubPlace">
                        <?= zrx_icon('plus', 11) ?>
                        <span>Add Child Unit</span>
                    </button>
                </div>

                <div class="subplaces-table-wrapper">
                    <table class="subplaces-table">
                        <thead>
                            <tr>
                                <th>English Name</th>
                                <th>Local Name</th>
                                <th>Type</th>
                                <th id="thSubplacesPostcode">Postcode</th>
                                <th class="geo-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="subplacesTableBody"></tbody>
                    </table>
                </div>

                <div class="geo-editor-footer-actions">
                    <button type="button" class="sub-btn sub-btn-del" id="btnDeleteNode">
                        <?= zrx_icon('trash', 12) ?>
                        <span>Delete Unit</span>
                    </button>
                </div>
            </div>

            <div id="editorEmptyState" class="geo-empty-state">
                No unit selected. Click any node in the navigator on the left to inspect and edit.
            </div>
        </section>
    </main>
</div>
