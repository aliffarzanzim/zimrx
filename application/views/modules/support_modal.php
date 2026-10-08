<?php
declare(strict_types=1);

// support_modal.php - ZimRx Support and Doctor Appreciation modal (include once per page)
if (defined('ZIMRX_SUPPORT_MODAL_LOADED')) return;
define('ZIMRX_SUPPORT_MODAL_LOADED', true);
?>

<!-- ZimRx Support & Doctor Appreciation Modal -->
<div id="support-modal-backdrop" class="support-backdrop" style="display: none;" onclick="zimrxCloseSupportModal()">
    <div class="support-dialog" onclick="event.stopPropagation()">
        
        <div class="support-titlebar">
            <div class="support-titlebar-title">
                <span class="support-heart-icon">❤️</span>
                <span>Support ZimRx</span>
            </div>
            <button type="button" class="support-close-btn" onclick="zimrxCloseSupportModal()" aria-label="Close">
                <?= zrx_icon('x', 18) ?>
            </button>
        </div>

        <div class="support-scroll-area" id="support-scroll-area" onscroll="zimrxCheckSupportScroll()">
            
            <div class="support-body">
                
                <!-- Introduction Text Block -->
                <div class="support-intro-block">
                    <p>
                        ZimRx is an open-source clinical initiative developed by Alif Farzan Zim (Dhaka Medical College) to provide practicing clinicians with a fast, reliable, and doctor-friendly prescription engine, completely free.
                    </p>
                    <p>
                        It is distributed under the open-source AGPL-3.0 license, without subscriptions, commercial advertisements, or corporate pharmaceutical sponsorship.
                    </p>
                    <p>
                        ZimRx will always remain open and accessible for every doctor. If it brings value to your daily practice and saves you time, your contribution is a meaningful gesture of appreciation for the developer behind it.
                    </p>
                </div>

                <!-- Token of Appreciation: bKash & Bank -->
                <div>
                    <div class="support-section-label">Token of Appreciation</div>
                    <div class="support-pay-grid">
                        
                        <!-- bKash -->
                        <div class="support-pay-card support-pay-bkash">
                            <div class="support-pay-card-header">
                                <span class="support-pay-badge bkash-badge">1. bKash (Personal)</span>
                                <button type="button" class="btn-copy" onclick="zimrxCopyPayment('01408203753', this)">
                                    <?= zrx_icon('copy', 12, ['class' => 'copy-icon']) ?>
                                    <span class="copy-text">Copy</span>
                                </button>
                            </div>
                            <div class="support-pay-number-row">
                                <span class="support-pay-code">01408203753</span>
                            </div>
                        </div>

                        <!-- Bank Transfer -->
                        <div class="support-pay-card support-pay-bank">
                            <div class="support-pay-card-header">
                                <span class="support-pay-badge bank-badge">2. Bank Transfer</span>
                                <button type="button" class="btn-copy" onclick="zimrxCopyPayment('20503236700015600', this)">
                                    <?= zrx_icon('copy', 12, ['class' => 'copy-icon']) ?>
                                    <span class="copy-text">Copy</span>
                                </button>
                            </div>
                            <div class="support-bank-grid">
                                <div class="support-bank-field">
                                    <span class="bank-k">Bank:</span>
                                    <span class="bank-v">Islami Bank Bangladesh PLC</span>
                                </div>
                                <div class="support-bank-field">
                                    <span class="bank-k">Acct:</span>
                                    <span class="bank-v acct-mono">2050 323 67 00015600</span>
                                </div>
                                <div class="support-bank-field">
                                    <span class="bank-k">Name:</span>
                                    <span class="bank-v bank-bold">ALIF FARJAN (JIM)</span>
                                </div>
                                <div class="support-bank-field">
                                    <span class="bank-k">Branch:</span>
                                    <span class="bank-v">Jibon Nagar Branch</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Other Meaningful Ways to Support -->
                <div>
                    <div class="support-section-label">Other Meaningful Ways to Support</div>
                    <div class="support-ways-list">
                        
                        <div class="support-way-item">
                            <div class="support-way-head">
                                <div class="support-icon-circle icon-heart">
                                    <?= zrx_icon('heart', 16) ?>
                                </div>
                                <div>
                                    <div class="support-way-title">Pay it forward</div>
                                    <div class="support-way-desc">Treat just one underprivileged patient for free every month.</div>
                                </div>
                            </div>
                        </div>

                        <div class="support-way-item item-share">
                            <div class="support-way-head">
                                <div class="support-icon-circle icon-share">
                                    <?= zrx_icon('share-2', 16) ?>
                                </div>
                                <div>
                                    <div class="support-way-title">Spread the word</div>
                                    <div class="support-way-desc">Recommend this software to your fellow colleagues and doctors.</div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Prayer / Blessing -->
                <div class="support-blessing-box">
                    <div class="blessing-icon">🤲</div>
                    <div class="blessing-text">
                        Above all, please keep me and my family in your prayers. Thank you for your support and for the invaluable service you provide to society every single day.
                    </div>
                </div>

            </div>
        </div>

        <div class="support-modal-footer">
            <span>Alif Farzan Zim &bull; Dhaka Medical College</span>
            <span class="support-license-tag">AGPL-3.0 Open Source</span>
        </div>

    </div>
</div>

<link rel="stylesheet" href="assets/css/modules/support_modal.css?v=<?= file_exists(ZIMRX_PUBLIC_DIR . '/assets/css/modules/support_modal.css') ? filemtime(ZIMRX_PUBLIC_DIR . '/assets/css/modules/support_modal.css') : '1' ?>">
<script src="assets/js/layout/support_modal.js?v=<?= file_exists(ZIMRX_PUBLIC_DIR . '/assets/js/layout/support_modal.js') ? filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/layout/support_modal.js') : '1' ?>" defer></script>
