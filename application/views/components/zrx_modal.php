<?php
declare(strict_types=1);

$modal = is_array($zrx_modal_config ?? null) ? $zrx_modal_config : [];
$modal_id = (string)($modal['id'] ?? '');
$modal_title = (string)($modal['title'] ?? '');
$modal_title_id = (string)($modal['title_id'] ?? ($modal_id . '-title'));
$subtitle_html = (string)($modal['subtitle_html'] ?? '');
$dialog_class = trim('zrx-modal-dialog ' . (string)($modal['dialog_class'] ?? ''));
$body_class = trim('zrx-modal-body ' . (string)($modal['body_class'] ?? ''));
$footer_class = trim('zrx-modal-footer ' . (string)($modal['footer_class'] ?? ''));
$modal_icon = (string)($modal['icon'] ?? '');
$close_button_id = (string)($modal['close_button_id'] ?? '');
$close_label = (string)($modal['close_label'] ?? 'Close dialog');
$close_autofocus = !empty($modal['close_autofocus']);
$header_actions_html = (string)($modal['header_actions_html'] ?? '');
$body_html = (string)($modal['body_html'] ?? '');
$footer_html = (string)($modal['footer_html'] ?? '');

if ($modal_id === '' || $modal_title === '') {
    throw new InvalidArgumentException('Modal id and title are required.');
}
// HTML slots must be rendered by trusted application views with dynamic values escaped.
?>
<div id="<?= htmlspecialchars($modal_id, ENT_QUOTES, 'UTF-8') ?>" class="zrx-modal-overlay" hidden>
    <div class="<?= htmlspecialchars($dialog_class, ENT_QUOTES, 'UTF-8') ?>" role="dialog" tabindex="-1" aria-modal="true" aria-labelledby="<?= htmlspecialchars($modal_title_id, ENT_QUOTES, 'UTF-8') ?>">
        <header class="zrx-modal-header">
            <div class="zrx-modal-heading">
                <?php if ($modal_icon !== ''): ?>
                    <?= zrx_icon($modal_icon, 20) ?>
                <?php endif; ?>
                <div class="zrx-modal-title-content">
                    <h2 id="<?= htmlspecialchars($modal_title_id, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($modal_title, ENT_QUOTES, 'UTF-8') ?></h2>
                    <?= $subtitle_html ?>
                </div>
            </div>
            <div class="zrx-modal-header-actions">
                <?= $header_actions_html ?>
                <button type="button" class="zrx-modal-close" data-zrx-modal-close<?= $close_autofocus ? ' data-zrx-modal-autofocus' : '' ?><?= $close_button_id !== '' ? ' id="' . htmlspecialchars($close_button_id, ENT_QUOTES, 'UTF-8') . '"' : '' ?> aria-label="<?= htmlspecialchars($close_label, ENT_QUOTES, 'UTF-8') ?>">
                    <?= zrx_icon('x', 16) ?>
                </button>
            </div>
        </header>
        <div class="<?= htmlspecialchars($body_class, ENT_QUOTES, 'UTF-8') ?>">
            <?= $body_html ?>
        </div>
        <?php if ($footer_html !== ''): ?>
            <footer class="<?= htmlspecialchars($footer_class, ENT_QUOTES, 'UTF-8') ?>">
                <?= $footer_html ?>
            </footer>
        <?php endif; ?>
    </div>
</div>
