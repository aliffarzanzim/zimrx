/**
 * ZimRx Hardware Barcode Scanner & Omni-Search Interceptor
 * Detects lightning-fast hardware keyboard inputs (<40ms per keystroke)
 * Auto-routes:
 *   - 'P...' (Patient Master Reg ID) -> emr.php?reg=P...
 *   - 'V...' (Visit Encounter Token) -> emr.php?visit=V...
 */

(function () {
    'use strict';

    let scanBuffer = '';
    let lastKeyTime = 0;
    const SCANNER_MAX_INTERVAL_MS = 45; // Hardware barcode scanners type < 35-40ms per char
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

                // Handle Rule A: Patient Master ID ('P...')
                if (/^P\d+/i.test(scannedCode)) {
                    e.preventDefault();
                    window.location.href = 'emr.php?reg=' + encodeURIComponent(scannedCode.toUpperCase());
                    return;
                }

                // Handle Rule B: Visit ID ('V...')
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

    // Initialize Omni Search Box on EMR Landing
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
                    // Regular query search
                    if (window.zimrxExecuteOmniSearch) {
                        window.zimrxExecuteOmniSearch(val);
                    }
                }
            }
        });
    });
})();
