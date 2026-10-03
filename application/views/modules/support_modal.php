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
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
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
                                    <svg class="copy-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
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
                                    <svg class="copy-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
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
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                    </svg>
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
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="18" cy="5" r="3"></circle>
                                        <circle cx="6" cy="12" r="3"></circle>
                                        <circle cx="18" cy="19" r="3"></circle>
                                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                                    </svg>
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
