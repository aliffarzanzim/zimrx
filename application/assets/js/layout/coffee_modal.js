// Support and donation modal controller: scroll cue detection and payment info copying.
function zimrxOpenCoffeeModal() {
    const modal = document.getElementById('coffee-modal-backdrop');
    if (!modal) return;
    modal.style.display = 'flex';
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
    
    setTimeout(zimrxCheckScroll, 80);
}

function zimrxCloseCoffeeModal() {
    const modal = document.getElementById('coffee-modal-backdrop');
    if (!modal) return;
    modal.classList.remove('open');
    modal.style.display = 'none';
    document.body.style.overflow = '';
}

function zimrxCheckScroll() {
    const scrollArea = document.getElementById('coffee-scroll-area');
    const cue = document.getElementById('coffee-scroll-cue');
    if (!scrollArea || !cue) return;
    
    const lastItem = scrollArea.querySelector('.item-share');
    if (!lastItem) {
        cue.classList.remove('visible');
        return;
    }
    
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
