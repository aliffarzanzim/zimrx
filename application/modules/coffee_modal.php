<?php
declare(strict_types=1);

// coffee_modal.php — Buy Me a Coffee modal (include once per page)
if (defined('ZIMRX_COFFEE_MODAL_LOADED')) return;
define('ZIMRX_COFFEE_MODAL_LOADED', true);
?>

<!-- ── Buy Me a Coffee Modal ──────────────────────────────────────────── -->
<div id="coffee-modal-backdrop" class="coffee-backdrop" onclick="zimrxCloseCoffeeModal()">
    <div class="coffee-dialog" onclick="event.stopPropagation()">
        
        <!-- Close Button (Always on top) -->
        <button type="button" class="coffee-close-btn" onclick="zimrxCloseCoffeeModal()" aria-label="Close">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>

        <!-- Scrollable Inner Wrapper -->
        <div class="coffee-scroll-area" id="coffee-scroll-area" onscroll="zimrxCheckScroll()">
            
            <!-- Header with Glowing Icon -->
            <div class="coffee-hero">
                <div class="coffee-icon-badge">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8h1a4 4 0 0 1 0 8h-1"></path>
                        <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path>
                        <line x1="6" y1="1" x2="6" y2="4"></line>
                        <line x1="10" y1="1" x2="10" y2="4"></line>
                        <line x1="14" y1="1" x2="14" y2="4"></line>
                    </svg>
                </div>
                <span class="coffee-pill">Doctor Appreciation &amp; Support</span>
                <h2 class="coffee-title">Enjoying this software? 🩺</h2>
                <p class="coffee-intro">
                    I built this tool to make chamber practice <strong>easier, faster, more standardized, and completely free</strong> for doctors. So your appreciation and support mean the world to me!
                </p>
                <p class="coffee-subintro">
                    If it brings value to your practice, you can show your support in a few ways:
                </p>
            </div>

            <!-- Main Content -->
            <div class="coffee-content">

                <!-- 1. Coffee / Donation Card -->
                <div class="support-item item-coffee">
                    <div class="item-head">
                        <div class="item-icon-circle icon-coffee">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8h1a4 4 0 0 1 0 8h-1"></path>
                                <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path>
                                <line x1="6" y1="1" x2="6" y2="4"></line>
                                <line x1="10" y1="1" x2="10" y2="4"></line>
                                <line x1="14" y1="1" x2="14" y2="4"></line>
                            </svg>
                        </div>
                        <div>
                            <div class="item-title">Buy me a coffee</div>
                            <div class="item-desc">You can send a token of appreciation of any amount via:</div>
                        </div>
                    </div>

                    <!-- Side-by-Side Payment Grid to fit all items cleanly on screen -->
                    <div class="pay-methods">
                        
                        <!-- bKash -->
                        <div class="pay-card pay-bkash">
                            <div class="pay-card-header">
                                <span class="pay-badge bkash-badge">1. bKash (Personal)</span>
                                <button type="button" class="btn-copy" onclick="zimrxCopyPayment('01408203753', this)">
                                    <svg class="copy-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                    <span class="copy-text">Copy</span>
                                </button>
                            </div>
                            <div class="pay-number-row">
                                <span class="pay-code">01408203753</span>
                            </div>
                        </div>

                        <!-- Bank Transfer -->
                        <div class="pay-card pay-bank">
                            <div class="pay-card-header">
                                <span class="pay-badge bank-badge">2. Bank Transfer</span>
                                <button type="button" class="btn-copy" onclick="zimrxCopyPayment('20503236700015600', this)">
                                    <svg class="copy-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                    <span class="copy-text">Copy</span>
                                </button>
                            </div>
                            <div class="bank-grid">
                                <div class="bank-field">
                                    <span class="bank-k">Bank:</span>
                                    <span class="bank-v">Islami Bank Bangladesh PLC</span>
                                </div>
                                <div class="bank-field">
                                    <span class="bank-k">Acct:</span>
                                    <span class="bank-v acct-mono">2050 323 67 00015600</span>
                                </div>
                                <div class="bank-field">
                                    <span class="bank-k">Name:</span>
                                    <span class="bank-v bank-bold">ALIF FARJAN (JIM)</span>
                                </div>
                                <div class="bank-field">
                                    <span class="bank-k">Branch:</span>
                                    <span class="bank-v">Jibon Nagar, Chuadanga Branch</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- 2. Pay it Forward -->
                <div class="support-item item-heart">
                    <div class="item-head">
                        <div class="item-icon-circle icon-heart">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                            </svg>
                        </div>
                        <div>
                            <div class="item-title">Pay it forward</div>
                            <div class="item-desc">Treat just one underprivileged patient for free every month.</div>
                        </div>
                    </div>
                </div>

                <!-- 3. Spread the Word -->
                <div class="support-item item-share">
                    <div class="item-head">
                        <div class="item-icon-circle icon-share">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21.2 8.4c.5.38.8.97.8 1.6v2a2 2 0 0 1-.8 1.6l-8.2 6.1a2 2 0 0 1-2.4 0l-8.2-6.1A2 2 0 0 1 1.6 12v-2c0-.63.3-1.22.8-1.6l8.2-6.1a2 2 0 0 1 2.4 0l8.2 6.1z"></path>
                                <path d="m22 10-10 7.5L2 10"></path>
                            </svg>
                        </div>
                        <div>
                            <div class="item-title">Spread the word</div>
                            <div class="item-desc">Recommend this software to your fellow colleagues and doctors.</div>
                        </div>
                    </div>
                </div>

                <!-- Heartfelt Blessing Box -->
                <div class="coffee-blessing">
                    <div class="blessing-icon">🤲</div>
                    <div class="blessing-text">
                        Above all, please keep me and my family in your prayers. Thank you for your support and for the invaluable service you provide to society every single day.
                    </div>
                </div>

            </div>
        </div>

        <!-- Dynamic Bottom Scroll Hint (appears only when content overflows) -->
        <div class="coffee-scroll-cue" id="coffee-scroll-cue" onclick="zimrxScrollDown()">
            <span>More ways to support below</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </div>

    </div>
</div>

<link rel="stylesheet" href="assets/css/modules/coffee_modal.css?v=<?= filemtime(__DIR__ . '/assets/css/modules/coffee_modal.css') ?>">

<script src="assets/js/layout/coffee_modal.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/coffee_modal.js') ?>"></script>
