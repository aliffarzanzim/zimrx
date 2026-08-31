<?php
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

<style>
    /* Backdrop */
    .coffee-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.6);
        z-index: 99999;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        animation: coffeeFade 0.2s ease-out;
    }
    .coffee-backdrop.open {
        display: flex;
    }

    @keyframes coffeeFade {
        from { opacity: 0; }
        to   { opacity: 1; }
    }

    /* Dialog Box */
    .coffee-dialog {
        background: #ffffff;
        border-radius: 20px;
        max-width: 560px;
        width: 100%;
        max-height: 92vh;
        overflow: hidden;
        box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.35), 0 0 0 1px rgba(0, 0, 0, 0.05);
        position: relative;
        animation: coffeePop 0.26s cubic-bezier(0.16, 1, 0.3, 1);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        display: flex;
        flex-direction: column;
    }

    @keyframes coffeePop {
        from { opacity: 0; transform: translateY(20px) scale(0.96); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    /* Internal Scroll Area */
    .coffee-scroll-area {
        overflow-y: auto;
        overflow-x: hidden;
        max-height: 92vh;
        flex: 1 1 auto;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }
    .coffee-scroll-area::-webkit-scrollbar {
        width: 5px;
    }
    .coffee-scroll-area::-webkit-scrollbar-track {
        background: transparent;
        margin: 16px 0;
    }
    .coffee-scroll-area::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 999px;
    }
    .coffee-scroll-area::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    /* Close Button */
    .coffee-close-btn {
        position: absolute;
        top: 12px;
        right: 12px;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: rgba(241, 245, 249, 0.9);
        border: 1px solid rgba(226, 232, 240, 0.8);
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        z-index: 30;
    }
    .coffee-close-btn:hover {
        background: #e2e8f0;
        color: #0f172a;
        transform: rotate(90deg);
    }

    /* Hero Section */
    .coffee-hero {
        padding: 1.5rem 1.75rem 0.65rem;
        text-align: center;
        position: relative;
        background: radial-gradient(circle at 50% 0%, rgba(245, 158, 11, 0.14) 0%, rgba(255, 255, 255, 0) 70%);
    }

    .coffee-icon-badge {
        width: 48px;
        height: 48px;
        margin: 0 auto 0.6rem;
        border-radius: 14px;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 18px -3px rgba(245, 158, 11, 0.4);
    }

    .coffee-pill {
        display: inline-block;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #b45309;
        background: #fef3c7;
        border: 1px solid #fde68a;
        padding: 0.18rem 0.65rem;
        border-radius: 999px;
        margin-bottom: 0.35rem;
    }

    .coffee-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0 0 0.35rem;
        letter-spacing: -0.01em;
    }

    .coffee-intro {
        margin: 0;
        font-size: 0.84rem;
        line-height: 1.5;
        color: #475569;
    }
    .coffee-intro strong {
        color: #0f172a;
    }
    .coffee-subintro {
        margin: 0.5rem 0 0;
        font-size: 0.84rem;
        font-weight: 600;
        color: #334155;
    }

    /* Content Area */
    .coffee-content {
        padding: 0 1.5rem 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
    }

    /* Support Cards */
    .support-item {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.85rem 1rem;
        transition: all 0.2s ease;
    }
    .support-item:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px -3px rgba(15, 23, 42, 0.05);
    }

    .item-coffee {
        background: #ffffff;
        border-color: #fed7aa;
        box-shadow: 0 2px 8px rgba(245, 158, 11, 0.04);
    }

    .item-head {
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
    }

    .item-icon-circle {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .icon-coffee { background: #fff7ed; color: #ea580c; }
    .icon-heart  { background: #ffe4e6; color: #e11d48; }
    .icon-share  { background: #e0f2fe; color: #0284c7; }

    .item-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.2;
    }
    .item-desc {
        font-size: 0.78rem;
        color: #64748b;
        margin-top: 0.15rem;
        line-height: 1.35;
    }

    /* Side-by-Side Payment Grid */
    .pay-methods {
        display: grid;
        grid-template-columns: 1fr 1.35fr;
        gap: 0.55rem;
        margin-top: 0.65rem;
    }

    @media (max-width: 520px) {
        .pay-methods {
            grid-template-columns: 1fr;
        }
    }

    .pay-card {
        border-radius: 9px;
        padding: 0.6rem 0.75rem;
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .pay-bkash {
        background: #fff5f8;
        border-color: #fbcfe8;
    }
    .pay-bank {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }

    .pay-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.4rem;
        margin-bottom: 0.3rem;
    }

    .pay-badge {
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding: 0.12rem 0.45rem;
        border-radius: 5px;
    }
    .bkash-badge {
        background: #ffffff;
        color: #db2777;
        border: 1px solid #f472b6;
    }
    .bank-badge {
        background: #ffffff;
        color: #059669;
        border: 1px solid #6ee7b7;
    }

    .btn-copy {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #475569;
        font-size: 0.68rem;
        font-weight: 600;
        padding: 0.18rem 0.48rem;
        border-radius: 5px;
        cursor: pointer;
        font-family: inherit;
        transition: all 0.15s ease;
    }
    .btn-copy:hover {
        background: #0f172a;
        border-color: #0f172a;
        color: #ffffff;
    }
    .btn-copy.copied {
        background: #059669 !important;
        border-color: #059669 !important;
        color: #ffffff !important;
    }

    .pay-number-row {
        margin-top: 0.2rem;
    }
    .pay-code {
        font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
        font-size: 0.98rem;
        font-weight: 700;
        color: #831843;
        letter-spacing: 0.04em;
    }

    .bank-grid {
        display: flex;
        flex-direction: column;
        gap: 0.18rem;
        font-size: 0.74rem;
        margin-top: 0.15rem;
    }
    .bank-field {
        display: flex;
        align-items: baseline;
        gap: 0.35rem;
        line-height: 1.3;
    }
    .bank-k {
        color: #64748b;
        font-size: 0.7rem;
        min-width: 44px;
    }
    .bank-v {
        color: #0f172a;
    }
    .bank-bold {
        font-weight: 700;
    }
    .acct-mono {
        font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
        font-weight: 700;
        color: #065f46;
        letter-spacing: 0.03em;
        font-size: 0.78rem;
    }

    /* Blessing Box */
    .coffee-blessing {
        background: #fffbeb;
        border: 1px solid #fef3c7;
        border-radius: 10px;
        padding: 0.7rem 0.85rem;
        display: flex;
        align-items: flex-start;
        gap: 0.55rem;
    }
    .blessing-icon {
        font-size: 1.05rem;
        line-height: 1;
        flex-shrink: 0;
    }
    .blessing-text {
        font-size: 0.76rem;
        line-height: 1.45;
        color: #92400e;
        font-style: italic;
    }

    /* Scroll Cue */
    .coffee-scroll-cue {
        display: none;
        position: absolute;
        bottom: 8px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(15, 23, 42, 0.85);
        color: #ffffff;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 0.3rem 0.75rem;
        border-radius: 999px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        cursor: pointer;
        backdrop-filter: blur(4px);
        align-items: center;
        gap: 0.35rem;
        z-index: 40;
        animation: coffeeBounce 1.5s infinite;
        transition: opacity 0.2s ease;
    }
    .coffee-scroll-cue.visible {
        display: inline-flex;
    }

    @keyframes coffeeBounce {
        0%, 100% { transform: translate(-50%, 0); }
        50% { transform: translate(-50%, -4px); }
    }
</style>

<script>
function zimrxOpenCoffeeModal() {
    const modal = document.getElementById('coffee-modal-backdrop');
    if (!modal) return;
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
    
    // Check if scroll cue should be shown
    setTimeout(zimrxCheckScroll, 80);
}

function zimrxCloseCoffeeModal() {
    const modal = document.getElementById('coffee-modal-backdrop');
    if (!modal) return;
    modal.classList.remove('open');
    document.body.style.overflow = '';
}

function zimrxCheckScroll() {
    const scrollArea = document.getElementById('coffee-scroll-area');
    const cue = document.getElementById('coffee-scroll-cue');
    if (!scrollArea || !cue) return;
    
    // Find the last support option box ("Spread the word")
    const lastItem = scrollArea.querySelector('.item-share');
    if (!lastItem) {
        cue.classList.remove('visible');
        return;
    }
    
    // Show cue ONLY if the last box is completely invisible (its top is below the viewport)
    // If all boxes are shown (even if the last one or prayer is partial), cue stays hidden
    const visibleBottom = scrollArea.scrollTop + scrollArea.clientHeight;
    const isAnyBoxFullyInvisible = lastItem.offsetTop >= (visibleBottom - 5);
    
    if (isAnyBoxFullyInvisible) {
        cue.classList.add('visible');
    } else {
        cue.classList.remove('visible');
    }
}

function zimrxScrollDown() {
    const scrollArea = document.getElementById('coffee-scroll-area');
    if (!scrollArea) return;
    scrollArea.scrollBy({ top: 160, behavior: 'smooth' });
}

function zimrxCopyPayment(text, btn) {
    if (!text || !btn) return;
    const copyText = btn.querySelector('.copy-text');
    const originalText = copyText ? copyText.innerText : 'Copy';

    const onSuccess = () => {
        btn.classList.add('copied');
        if (copyText) copyText.innerText = 'Copied!';
        setTimeout(() => {
            btn.classList.remove('copied');
            if (copyText) copyText.innerText = originalText;
        }, 1800);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(onSuccess).catch(() => {
            zimrxFallbackCopy(text, onSuccess);
        });
    } else {
        zimrxFallbackCopy(text, onSuccess);
    }
}

function zimrxFallbackCopy(text, cb) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try {
        document.execCommand('copy');
        if (cb) cb();
    } catch (e) {}
    document.body.removeChild(ta);
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') zimrxCloseCoffeeModal();
});
</script>
