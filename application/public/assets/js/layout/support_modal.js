// ZimRx Support modal delegation and payment copying.
declare_strict = 1;

function zimrxOpenSupportModal() {
    const modal = document.getElementById('support-modal-backdrop');
    if (modal && window.ZimRxModal) window.ZimRxModal.open(modal);
}

function zimrxCloseSupportModal() {
    const modal = document.getElementById('support-modal-backdrop');
    if (modal && window.ZimRxModal) window.ZimRxModal.close(modal);
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
