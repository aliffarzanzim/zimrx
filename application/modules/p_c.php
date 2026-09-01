<?php
$pcInitialSettings = null;
if (!defined('ZIMRX_PC_SETTINGS_SEEDED')) {
    try {
        require_once __DIR__ . '/../api/pc_settings.php';
        if (isset($pdo) && $pdo instanceof PDO) {
            $pcInitialSettings = pc_settings_payload($pdo, current_user_doctor_id());
        }
    } catch (Throwable $e) {
        $pcInitialSettings = null;
    }
    define('ZIMRX_PC_SETTINGS_SEEDED', true);
}
?>
<div class="pc-wrapper">
    <div class="pc-table-container">
        <table class="pc-table" id="pc-table">
            <colgroup>
                <col style="width: 32px;">
                <col style="width: 36px;">
                <col>
                <col style="width: 76px;">
                <col style="width: 80px;">
                <col style="width: 36px;">
            </colgroup>
            <thead>
                <tr>
                    <th style="text-align: center;"></th>
                    <th style="text-align: center;">#</th>
                    <th>Presenting Complaints</th>
                    <th style="text-align: center;">Duration</th>
                    <th style="text-align: center;">Unit</th>
                    <th style="text-align: center; padding: 0;">
                        <button type="button" class="pc-settings-btn" title="Presenting Complaint Settings" aria-haspopup="dialog" aria-controls="pc-settings-modal">
                            <?= zrx_icon('settings', 14) ?>
                        </button>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php for($i=1; $i<=5; $i++): ?>
                <tr class="pc-row" draggable="true">
                    <td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="pc-row-no"><?= $i ?></td>
                    <td><textarea class="pc-input pc-complaint-input" autocomplete="off" rows="1"></textarea></td>
                    <td><input type="text" class="pc-input pc-duration-input" style="text-align: center;" autocomplete="off"></td>
                    <td><input type="text" class="pc-input pc-unit-input" style="text-align: center;" autocomplete="off"></td>
                    <td class="pc-action pc-drag">
                        <button type="button" class="pc-row-move-btn" title="Move Row">
                            <?= zrx_icon('move', 14) ?>
                        </button>
                    </td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <div class="pc-footer">
        <button type="button" class="pc-add-row-btn">Add More</button>
    </div>
    <template id="pc-row-template">
        <tr class="pc-row" draggable="true">
            <td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
            <td class="pc-row-no"></td>
            <td><textarea class="pc-input pc-complaint-input" autocomplete="off" rows="1"></textarea></td>
            <td><input type="text" class="pc-input pc-duration-input" style="text-align: center;" autocomplete="off"></td>
            <td><input type="text" class="pc-input pc-unit-input" style="text-align: center;" autocomplete="off"></td>
            <td class="pc-action pc-drag">
                <button type="button" class="pc-row-move-btn" title="Move Row">
                    <?= zrx_icon('move', 14) ?>
                </button>
            </td>
        </tr>
    </template>
</div>

<?php if ($pcInitialSettings !== null): ?>
<script type="application/json" id="zimrxInitialPcSettings"><?= htmlspecialchars(json_encode($pcInitialSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_NOQUOTES, 'UTF-8') ?></script>
<?php endif; ?>

<div class="pc-settings-modal" id="pc-settings-modal" hidden>
    <div class="pc-settings-backdrop" data-pc-settings-close></div>
    <div class="pc-settings-panel" role="dialog" aria-modal="true" aria-labelledby="pc-settings-title">
        <div class="pc-settings-header">
            <div class="pc-settings-header-left">
                <div class="pc-settings-header-icon">
                    <?= zrx_icon('settings', 20) ?>
                </div>
                <div>
                    <h3 id="pc-settings-title">Presenting Complaints (P/C) Settings</h3>
                    <p>Doctor-specific suggestion hierarchy, custom clinical entries, and term visibility.</p>
                </div>
            </div>
            <button type="button" class="pc-settings-close" data-pc-settings-close aria-label="Close Presenting Complaint Settings">
                <?= zrx_icon('x', 16) ?>
            </button>
        </div>

        <div class="pc-settings-body">
            <!-- Clinical Practice Guidance Banner -->
            <div class="pc-clinical-note-banner">
                <div class="pc-note-icon">
                    <?= zrx_icon('alert-circle', 18) ?>
                </div>
                <div class="pc-note-content">
                    <span class="pc-note-badge">Clinical Practice Note</span>
                    <p>In modern clinical practice, we encourage using <strong>'Presenting Complaints' (P/C)</strong> instead of <strong>'Chief Complaints' (C/C)</strong>. A patient may suffer from many problems simultaneously, but the P/C isolates exactly what they are presenting or appearing for right now to the doctor.</p>
                </div>
            </div>

            <div class="pc-settings-grid">
                <!-- Left Column: Priority & Hide Settings -->
                <div class="pc-settings-column">
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
                                        <th style="width: 54px; text-align: center;">Active</th>
                                        <th>Catalog Source</th>
                                        <th style="width: 100px; text-align: right;">Order</th>
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

                    <section class="pc-settings-card">
                        <div class="pc-settings-card-head">
                            <div class="pc-card-title-wrap">
                                <span class="pc-card-icon"><?= zrx_icon('eye', 16) ?></span>
                                <h4>Hide / Suppress Suggestions</h4>
                            </div>
                            <p>Hide unwanted suggestions for this doctor without deleting catalog data.</p>
                        </div>
                        <div class="pc-hide-search-box">
                            <span class="pc-search-box-icon"><?= zrx_icon('search', 14) ?></span>
                            <input type="text" class="pc-hide-search-input" placeholder="Search complaint term to hide or unhide...">
                            <button type="button" class="pc-search-clear-btn" title="Clear search" hidden><?= zrx_icon('x', 14) ?></button>
                        </div>
                        <div class="pc-hide-search-results"></div>
                    </section>
                </div>

                <!-- Right Column: Used P/C & Custom Additions -->
                <div class="pc-settings-column">
                    <section class="pc-settings-card">
                        <div class="pc-settings-card-head">
                            <div class="pc-card-title-wrap">
                                <span class="pc-card-icon"><?= zrx_icon('activity', 16) ?></span>
                                <h4>Used & Learned P/C</h4>
                            </div>
                            <p>Grouped by source (Most Used, Custom, and System P/C) with live usage tracking.</p>
                        </div>
                        <div class="pc-used-groups"></div>
                    </section>

                    <section class="pc-settings-card">
                        <div class="pc-settings-card-head">
                            <div class="pc-card-title-wrap">
                                <span class="pc-card-icon"><?= zrx_icon('plus', 16) ?></span>
                                <h4>Custom Added P/C</h4>
                            </div>
                            <p>Add doctor-specific complaints. They remain private to your local clinic profile.</p>
                        </div>
                        <div class="pc-custom-add-row">
                            <input type="text" class="pc-custom-input" placeholder="Type new custom complaint...">
                            <button type="button" class="pc-custom-add-btn" title="Add Custom P/C">
                                <?= zrx_icon('plus', 16) ?> Add
                            </button>
                        </div>
                        <div class="pc-custom-list"></div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>
