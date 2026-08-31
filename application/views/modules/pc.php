<?php
declare(strict_types=1);

// Presenting Complaints (P/C) module: complaint table and duration/unit pickers.

$pcInitialSettings = null;
$pcSettingsOnly = ($pcSettingsOnly ?? false) === true;
if ($pcSettingsOnly && !defined('ZIMRX_PC_SETTINGS_SEEDED')) {
    try {
        require_once ZIMRX_PUBLIC_DIR . '/api/pc_settings.php';
        if (isset($pdo) && $pdo instanceof PDO) {
            $pcInitialSettings = pc_settings_payload($pdo, current_user_doctor_id());
        }
    } catch (Throwable $e) {
        $pcInitialSettings = null;
    }
    define('ZIMRX_PC_SETTINGS_SEEDED', true);
}
?>
<?php if (!$pcSettingsOnly): ?>
<div class="pc-wrapper">
    <div class="pc-table-container">
        <table class="pc-table" id="pc-table">
            <colgroup>
                <col class="pc-col-del">
                <col class="pc-col-no">
                <col class="pc-col-complaint">
                <col class="pc-col-duration">
                <col class="pc-col-unit">
                <col class="pc-col-drag">
            </colgroup>
            <thead>
                <tr>
                    <th class="pc-th-center"></th>
                    <th class="pc-th-center">#</th>
                    <th>Presenting Complaints</th>
                    <th class="pc-th-center">Duration</th>
                    <th class="pc-th-center">Unit</th>
                    <th class="pc-th-settings">
                        <a class="pc-settings-btn" href="presenting_complaint_settings.php" title="Presenting Complaint Settings">
                            <?= zrx_icon('settings', 14) ?>
                        </a>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php for($i=1; $i<=5; $i++): ?>
                <tr class="pc-row" draggable="true">
                    <td class="pc-action pc-del"><button type="button" title="Remove Row">X</button></td>
                    <td class="pc-row-no"><?= $i ?></td>
                    <td><textarea class="pc-input pc-complaint-input" autocomplete="off" rows="1"></textarea></td>
                    <td><input type="text" class="pc-input pc-duration-input" autocomplete="off"></td>
                    <td><input type="text" class="pc-input pc-unit-input" autocomplete="off"></td>
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
            <td><input type="text" class="pc-input pc-duration-input" autocomplete="off"></td>
            <td><input type="text" class="pc-input pc-unit-input" autocomplete="off"></td>
            <td class="pc-action pc-drag">
                <button type="button" class="pc-row-move-btn" title="Move Row">
                    <?= zrx_icon('move', 14) ?>
                </button>
            </td>
        </tr>
    </template>
</div>
<?php endif; ?>

<?php if ($pcSettingsOnly) include __DIR__ . '/pc_settings.php'; ?>
