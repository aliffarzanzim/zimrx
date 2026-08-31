<?php
declare(strict_types=1);
?>

<?php if ($pcInitialSettings !== null): ?>
<script type="application/json" id="zimrxInitialPcSettings"><?= htmlspecialchars(json_encode($pcInitialSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_NOQUOTES, 'UTF-8') ?></script>
<?php endif; ?>

<div class="pc-settings-modal<?= $pcSettingsOnly ? ' pc-settings-page-modal' : '' ?>" id="pc-settings-modal"<?= $pcSettingsOnly ? ' data-settings-page="true"' : ' hidden' ?>>
    <?php if (!$pcSettingsOnly): ?>
    <div class="pc-settings-backdrop" data-pc-settings-close></div>
    <?php endif; ?>
    <div class="pc-settings-panel" role="dialog" aria-modal="true" aria-labelledby="pc-settings-title">
        <div class="pc-settings-header">
            <div>
                <h3 id="pc-settings-title">Presenting Complaints (P/C) Settings</h3>
                <p>Doctor-specific suggestion hierarchy, custom clinical entries, and term visibility.</p>
            </div>
            <?php if (!$pcSettingsOnly): ?>
            <button type="button" class="pc-settings-close" data-pc-settings-close aria-label="Close Presenting Complaint Settings">
                <?= zrx_icon('x', 16) ?>
            </button>
            <?php endif; ?>
        </div>

        <!-- P/C Settings Tabs Bar -->
        <div class="pc-settings-tabs-bar">
            <button type="button" class="pc-settings-tab-btn active" data-pc-tab="settings">
                <?= zrx_icon('sliders', 14) ?>
                <span>Settings</span>
            </button>
            <button type="button" class="pc-settings-tab-btn" data-pc-tab="usage">
                <?= zrx_icon('activity', 14) ?>
                <span>Usage Ranking</span>
            </button>
        </div>

        <div class="pc-settings-body">
            <!-- Tab Pane 1: General Settings -->
            <div class="pc-tab-pane active" id="pc-tab-pane-settings">
                <!-- Modern Clinical Practice Guideline Card -->
                <div class="pc-clinical-insight-card">
                    <div class="pc-insight-header">
                        <div class="pc-insight-title-group">
                            <span class="pc-insight-icon-wrap">
                                <?= zrx_icon('activity', 14) ?>
                            </span>
                            <span class="pc-insight-title">P/C vs. C/C Rationale</span>
                        </div>
                    </div>
                    <div class="pc-insight-body">
                        <p>In modern clinical practice, we encourage prioritizing <strong>Presenting Complaints (P/C)</strong> over <strong>Chief Complaints (C/C)</strong>. A patient may suffer from multiple concurrent conditions, but the P/C isolates exactly what they are presenting or appearing for right now to the doctor.</p>
                    </div>
                </div>

                <!-- Suggestion Priority -->
                <section class="pc-settings-card">
                    <div class="pc-settings-card-head">
                        <div class="pc-card-title-wrap">
                            <span class="pc-card-icon"><?= zrx_icon('sliders', 16) ?></span>
                            <h4>Suggestion Priority</h4>
                        </div>
                        <p>Enable sources and adjust search ranking hierarchy (Most Used, Custom, System P/C).</p>
                    </div>
                    <div class="pc-priority-table-wrap">
                        <table class="pc-priority-table">
                            <thead>
                                <tr>
                                    <th class="pc-priority-th-active">Active</th>
                                    <th>Catalog Source</th>
                                    <th class="pc-priority-th-order">Order</th>
                                </tr>
                            </thead>
                            <tbody class="pc-priority-body"></tbody>
                        </table>
                    </div>
                    <div class="pc-settings-actions">
                        <button type="button" class="pc-settings-save-btn">
                            <?= zrx_icon('save', 14) ?> Save Priority
                        </button>
                    </div>
                </section>

                <!-- Edit PC (Combined Custom Added P/C & Hide/Suppress Suggestions) -->
                <section class="pc-settings-card pc-edit-card">
                    <div class="pc-settings-card-head">
                        <div class="pc-card-title-wrap">
                            <span class="pc-card-icon"><?= zrx_icon('edit', 16) ?></span>
                            <h4>Edit PC</h4>
                        </div>
                        <p>Add custom complaints, rename or remove them, and hide unwanted catalog suggestions.</p>
                    </div>

                    <div class="pc-edit-tabs" role="tablist">
                        <button type="button" class="pc-edit-tab-btn active" data-edit-tab="all">
                            All
                        </button>
                        <button type="button" class="pc-edit-tab-btn" data-edit-tab="custom">
                            <?= zrx_icon('plus', 12) ?> Custom Added (<span class="pc-custom-count">0</span>)
                        </button>
                        <button type="button" class="pc-edit-tab-btn" data-edit-tab="hidden">
                            <?= zrx_icon('eye', 12) ?> Hide / Suppress (<span class="pc-hidden-count">0</span>)
                        </button>
                    </div>

                    <!-- Custom Complaints Subsection -->
                    <div class="pc-edit-custom-section">
                        <div class="pc-edit-subhead">
                            <span class="pc-edit-subhead-title">Custom Added P/C</span>
                            <span class="pc-edit-subhead-desc">Doctor-specific complaints private to your local clinic profile.</span>
                        </div>
                        <div class="pc-custom-add-row">
                            <input type="text" class="pc-custom-input" placeholder="Type new custom complaint...">
                            <button type="button" class="pc-custom-add-btn" title="Add Custom P/C">
                                <?= zrx_icon('plus', 16) ?> Add
                            </button>
                        </div>
                        <div class="pc-custom-complaint-list pc-custom-list"></div>
                    </div>

                    <!-- Divider -->
                    <div class="pc-edit-divider" aria-hidden="true"></div>

                    <!-- Hide / Suppress Subsection -->
                    <div class="pc-edit-suppress-section">
                        <div class="pc-edit-subhead">
                            <span class="pc-edit-subhead-title">Hide / Suppress Suggestions</span>
                            <span class="pc-edit-subhead-desc">Hide unwanted suggestions for this doctor without deleting catalog data.</span>
                        </div>
                        <div class="pc-hide-search-box">
                            <span class="pc-search-box-icon"><?= zrx_icon('search', 14) ?></span>
                            <input type="text" class="pc-hide-search-input" placeholder="Search complaint term to hide or unhide...">
                            <button type="button" class="pc-search-clear-btn" title="Clear search" hidden><?= zrx_icon('x', 14) ?></button>
                        </div>
                        <div class="pc-hide-search-results"></div>
                    </div>
                </section>

                <!-- Edit Duration -->
                <section class="pc-settings-card">
                    <div class="pc-settings-card-head">
                        <div class="pc-card-title-wrap">
                            <span class="pc-card-icon"><?= zrx_icon('clock', 16) ?></span>
                            <h4>Edit Duration</h4>
                        </div>
                        <p>Add and manage custom duration values (e.g. 21, 28, 45, 60). They slot numerically into the duration dropdown.</p>
                    </div>
                    <div class="pc-custom-duration-add-row pc-custom-add-row">
                        <input type="text" class="pc-custom-duration-input pc-custom-input" placeholder="Type new duration (e.g. 21)...">
                        <button type="button" class="pc-custom-duration-add-btn pc-custom-add-btn" title="Add Custom Duration">
                            <?= zrx_icon('plus', 16) ?> Add
                        </button>
                    </div>
                    <div class="pc-custom-duration-list pc-custom-list"></div>
                </section>

                <!-- Edit Units -->
                <section class="pc-settings-card">
                    <div class="pc-settings-card-head">
                        <div class="pc-card-title-wrap">
                            <span class="pc-card-icon"><?= zrx_icon('calendar', 16) ?></span>
                            <h4>Edit Units</h4>
                        </div>
                        <p>Add and manage custom time units (e.g. Cycles, Doses, Puffs). They slot into the unit dropdown.</p>
                    </div>
                    <div class="pc-custom-unit-add-row pc-custom-add-row">
                        <input type="text" class="pc-custom-unit-input pc-custom-input" placeholder="Type new unit (e.g. Cycles)...">
                        <button type="button" class="pc-custom-unit-add-btn pc-custom-add-btn" title="Add Custom Unit">
                            <?= zrx_icon('plus', 16) ?> Add
                        </button>
                    </div>
                    <div class="pc-custom-unit-list pc-custom-list"></div>
                </section>
                <div class="pc-settings-bottom-spacer" aria-hidden="true"></div>
            </div>

            <!-- Tab Pane 2: Usage Ranking -->
            <div class="pc-tab-pane" id="pc-tab-pane-usage" hidden>
                <section class="pc-settings-card pc-usage-card">
                    <div class="pc-insight-header">
                        <div class="pc-insight-title-group">
                            <span class="pc-insight-icon-wrap">
                                <?= zrx_icon('activity', 14) ?>
                            </span>
                            <span class="pc-insight-title">Clinical Usage Ranking (Top 100)</span>
                        </div>
                    </div>
                    <div class="pc-insight-body">
                        <p>Unified leaderboard of your most frequently prescribed complaints with live usage count and catalog classification.</p>
                    </div>
                    <div class="pc-usage-search-box">
                        <span class="pc-search-box-icon"><?= zrx_icon('search', 14) ?></span>
                        <input type="text" class="pc-usage-search-input" placeholder="Filter usage rankings by complaint name...">
                        <button type="button" class="pc-usage-search-clear-btn" title="Clear search" hidden><?= zrx_icon('x', 14) ?></button>
                    </div>
                    <div class="pc-usage-ranking-list"></div>
                </section>
            </div>
        </div>
    </div>
</div>
