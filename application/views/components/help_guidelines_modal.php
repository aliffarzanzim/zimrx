<?php
declare(strict_types=1);

ob_start();
?>
<div class="zrx-help-language-picker">
    <select id="zrx-help-language-select" class="zrx-select" data-zrx-help-language aria-label="Help language">
        <?php foreach (($zrx_avail_help_langs ?? []) as $code => $meta):
            $language_label = ($meta['native_name'] === $meta['name'])
                ? $meta['name']
                : $meta['native_name'] . ' (' . $meta['name'] . ')';
        ?>
            <option value="<?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?>" <?= (string)$code === ($zrx_help_initial_lang ?? '') ? 'selected' : '' ?>><?= htmlspecialchars($language_label, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
    </select>
</div>
<?php
$help_header_actions_html = ob_get_clean();
$zrx_modal_config = [
    'id' => 'zrx-help-modal-overlay',
    'title_id' => 'zrx-help-modal-title',
    'title' => 'Help',
    'icon' => 'help-circle',
    'dialog_class' => 'zrx-help-modal-dialog',
    'body_class' => 'zrx-help-modal-body',
    'close_label' => 'Close help dialog',
    'close_autofocus' => true,
    'header_actions_html' => $help_header_actions_html,
    'body_html' => '<div id="zrx-help-modal-body"></div>',
    'footer_html' => '<button type="button" id="zrx-help-modal-ok-btn" class="btn btn-primary btn-sm" data-zrx-modal-close>Got it</button>',
];
include __DIR__ . '/zrx_modal.php';
unset($help_header_actions_html, $zrx_modal_config);
