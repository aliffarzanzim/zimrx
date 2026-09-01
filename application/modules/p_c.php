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
            <div>
                <h3 id="pc-settings-title">Presenting Complaint Settings</h3>
                <p>Doctor-wise priority, hidden terms, and custom complaint management.</p>
            </div>
            <button type="button" class="pc-settings-close" data-pc-settings-close aria-label="Close Presenting Complaint Settings"><?= zrx_icon('x', 16) ?></button>
        </div>

        <div class="pc-settings-grid">
            <div class="pc-settings-column">
                <section class="pc-settings-card">
                    <div class="pc-settings-card-head">
                        <h4>Suggestion Priority</h4>
                        <p>Tick sources to keep them active, then move them up or down.</p>
                    </div>
                    <div class="pc-priority-table-wrap">
                        <table class="pc-priority-table">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">Use</th>
                                    <th>Source</th>
                                    <th style="width: 96px; text-align: right;">Order</th>
                                </tr>
                            </thead>
                            <tbody class="pc-priority-body"></tbody>
                        </table>
                    </div>
                    <div class="pc-settings-actions">
                        <button type="button" class="pc-settings-save-btn">Save Priority</button>
                    </div>
                </section>

                <section class="pc-settings-card">
                    <div class="pc-settings-card-head">
                        <h4>Hide Terms</h4>
                        <p>Hide suggestions for this doctor only without deleting source data.</p>
                    </div>
                    <div class="pc-hide-search-box">
                        <input type="text" class="pc-hide-search-input" placeholder="Search PC to hide or unhide">
                    </div>
                    <div class="pc-hide-search-results"></div>
                </section>
            </div>

            <div class="pc-settings-column">
                <section class="pc-settings-card">
                    <div class="pc-settings-card-head">
                        <h4>Used PC</h4>
                        <p>Grouped by source so learning, custom, Standard P/C (zimrx_static_pc), and ICD stay easy to audit.</p>
                    </div>
                    <div class="pc-used-groups"></div>
                </section>

                <section class="pc-settings-card">
                    <div class="pc-settings-card-head">
                        <h4>Custom Added PC</h4>
                        <p>Add doctor-specific complaints here. They stay separate from global catalogs.</p>
                    </div>
                    <div class="pc-custom-add-row">
                        <input type="text" class="pc-custom-input" placeholder="Add custom complaint">
                        <button type="button" class="pc-custom-add-btn" title="Add Custom PC"><?= zrx_icon('plus', 14) ?></button>
                    </div>
                    <div class="pc-custom-list"></div>
                </section>
            </div>
        </div>
    </div>
</div>
