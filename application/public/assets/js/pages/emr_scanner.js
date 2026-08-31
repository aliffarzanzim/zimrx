// Hardware barcode scanner listener and omni-search input routing for patient and visit IDs.

(function () {
    'use strict';

    let scanBuffer = '';
    let lastKeyTime = 0;
    const SCANNER_MAX_INTERVAL_MS = 45; // Hardware scanners type rapidly (<40ms per char)
    const MIN_SCAN_LENGTH = 3;

    window.addEventListener('keydown', function (e) {
        const currentTime = Date.now();
        const interval = currentTime - lastKeyTime;
        lastKeyTime = currentTime;

        // If time between keystrokes is too long, reset the buffer
        if (interval > SCANNER_MAX_INTERVAL_MS) {
            scanBuffer = '';
        }

        // Check for scanner termination (Enter key)
        if (e.key === 'Enter' || e.keyCode === 13) {
            if (scanBuffer.length >= MIN_SCAN_LENGTH) {
                const scannedCode = scanBuffer.trim();
                scanBuffer = '';

                // Patient master ID ('P...')
                if (/^P\d+/i.test(scannedCode)) {
                    e.preventDefault();
                    window.location.href = 'emr.php?reg=' + encodeURIComponent(scannedCode.toUpperCase());
                    return;
                }

                // Visit encounter token ('V...')
                if (/^V\d+/i.test(scannedCode)) {
                    e.preventDefault();
                    window.location.href = 'emr.php?visit=' + encodeURIComponent(scannedCode.toUpperCase());
                    return;
                }
            }
            scanBuffer = '';
            return;
        }

        // Accumulate printable ASCII characters
        if (e.key && e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
            scanBuffer += e.key;
        }
    }, true);

    // Omni-search input on EMR landing
    document.addEventListener('DOMContentLoaded', function () {
        const omniInput = document.getElementById('emr-omni-input');
        if (!omniInput) return;

        omniInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const val = omniInput.value.trim();
                if (!val) return;

                if (/^P\d+/i.test(val)) {
                    window.location.href = 'emr.php?reg=' + encodeURIComponent(val.toUpperCase());
                } else if (/^V\d+/i.test(val)) {
                    window.location.href = 'emr.php?visit=' + encodeURIComponent(val.toUpperCase());
                } else {
                    // Text search query
                    if (window.zimrxExecuteOmniSearch) {
                        window.zimrxExecuteOmniSearch(val);
                    }
                }
            }
        });
    });
})();
