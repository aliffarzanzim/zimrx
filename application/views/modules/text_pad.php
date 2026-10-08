<?php
declare(strict_types=1);

// Free-text Note Pad module: rich-text documentation editor with print layout controls and document search.
?>
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
            <?= zrx_icon('search', 14, ['class' => 'tp-search-icon']) ?>
            <input type="text" placeholder="Documents" autocomplete="off">
        </div>
        <div class="tp-search-field">
            <?= zrx_icon('search', 14, ['class' => 'tp-search-icon']) ?>
            <input type="text" placeholder="Drugs" autocomplete="off">
        </div>
    </div>

    <!-- Text Editor -->
    <div class="tp-editor-container">
        <textarea id="textpad-editor" style="width: 100%;"></textarea>
    </div>
</div>

<!-- Load NicEditor & Initialize -->
<script src="assets/vendor/nicedit/nicEdit-latest.js"></script>
<script src="assets/vendor/nicedit/nicEdit-zimrx-custom.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/vendor/nicedit/nicEdit-zimrx-custom.js') ?>"></script>
<script src="assets/js/modules/text_pad.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/modules/text_pad.js') ?>"></script>
