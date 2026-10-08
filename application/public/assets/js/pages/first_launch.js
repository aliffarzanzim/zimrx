// First-launch setup wizard handling practice configuration, credentials, and recovery keys.

function changeSetupLang(lang) {
    const url = new URL(window.location.href);
    url.searchParams.set('lang', lang);
    window.location.href = url.toString();
}

// Step 2 navigation
function goStep2() {
    const lang = document.getElementById('step1Lang')?.value 
        || document.getElementById('flLangSelect')?.value 
        || document.getElementById('currentSetupLang')?.value 
        || new URLSearchParams(window.location.search).get('lang') 
        || 'en';
    const setupToken = new URLSearchParams(window.location.search).get('setup_token') || '';
    let target = 'first_launch.php?step=2&lang=' + encodeURIComponent(lang);
    if (setupToken) {
        target += '&setup_token=' + encodeURIComponent(setupToken);
    }
    window.location = target;
}

// Step 3 navigation
function goStep3() {
    clearError();
    const lang = document.getElementById('flLangSelect')?.value 
        || new URLSearchParams(window.location.search).get('lang') 
        || 'en';
    const countrySelect = document.getElementById('practiceCountry');
    const country = countrySelect?.value 
        || new URLSearchParams(window.location.search).get('country') 
        || '';

    if (!country) {
        const i18n = window.ZimRxFirstLaunchI18n || {};
        showError(i18n.error_select_country || 'Please choose your country or practice territory.');
        if (countrySelect) countrySelect.focus();
        return;
    }

    const prescribingMode = document.getElementById('prescribingMode')?.value 
        || new URLSearchParams(window.location.search).get('prescribing_mode') 
        || 'generic';
    const clinicalLang = document.getElementById('clinicalLang')?.value 
        || new URLSearchParams(window.location.search).get('clinical_lang') 
        || 'en';
    const helpLang = document.getElementById('helpLang')?.value 
        || new URLSearchParams(window.location.search).get('help_lang') 
        || clinicalLang;
    const setupToken = new URLSearchParams(window.location.search).get('setup_token') || '';

    let target = 'first_launch.php?step=3&lang=' + encodeURIComponent(lang)
        + '&country=' + encodeURIComponent(country)
        + '&prescribing_mode=' + encodeURIComponent(prescribingMode)
        + '&clinical_lang=' + encodeURIComponent(clinicalLang)
        + '&help_lang=' + encodeURIComponent(helpLang);

    if (setupToken) {
        target += '&setup_token=' + encodeURIComponent(setupToken);
    }
    window.location = target;
}

// Back to Step 2 from Step 3
function goStep2FromStep3() {
    const lang = document.getElementById('flLangSelect')?.value 
        || new URLSearchParams(window.location.search).get('lang') 
        || 'en';
    const country = document.getElementById('practiceCountry')?.value 
        || new URLSearchParams(window.location.search).get('country') 
        || 'INT';
    const prescribingMode = document.getElementById('prescribingMode')?.value 
        || new URLSearchParams(window.location.search).get('prescribing_mode') 
        || 'generic';
    const clinicalLang = document.getElementById('clinicalLang')?.value 
        || new URLSearchParams(window.location.search).get('clinical_lang') 
        || 'en';
    const helpLang = document.getElementById('helpLang')?.value 
        || new URLSearchParams(window.location.search).get('help_lang') 
        || clinicalLang;
    const setupToken = new URLSearchParams(window.location.search).get('setup_token') || '';

    let target = 'first_launch.php?step=2&lang=' + encodeURIComponent(lang)
        + '&country=' + encodeURIComponent(country)
        + '&prescribing_mode=' + encodeURIComponent(prescribingMode)
        + '&clinical_lang=' + encodeURIComponent(clinicalLang)
        + '&help_lang=' + encodeURIComponent(helpLang);

    if (setupToken) {
        target += '&setup_token=' + encodeURIComponent(setupToken);
    }
    window.location = target;
}

// Prescribing Mode Selector
function selectPrescribingMode(mode) {
    const brandBtn = document.getElementById('choiceModeBrand');
    const genericBtn = document.getElementById('choiceModeGeneric');
    const input = document.getElementById('prescribingMode');
    if (!input) return;

    if (mode === 'brand') {
        if (brandBtn && brandBtn.classList.contains('disabled')) {
            return; // Locked if no formulary pack available
        }
        if (brandBtn) brandBtn.classList.add('selected');
        if (genericBtn) genericBtn.classList.remove('selected');
        input.value = 'brand';
    } else {
        if (genericBtn) genericBtn.classList.add('selected');
        if (brandBtn) brandBtn.classList.remove('selected');
        input.value = 'generic';
    }
}

// Dynamic Formulary Availability Detection
function updateFormularyState(countryCode) {
    const code = (countryCode || '').toUpperCase().trim();
    const formularies = window.ZimRxAvailableFormularies || {};
    const i18n = window.ZimRxFirstLaunchI18n || {};
    const pack = code ? (formularies[code] || null) : null;

    const box = document.getElementById('formularyStatusBox');
    const iconSpan = document.getElementById('formularyStatusIcon');
    const textSpan = document.getElementById('formularyStatusText');
    const brandBtn = document.getElementById('choiceModeBrand');
    const lockBadge = document.getElementById('brandLockBadge');
    const modeSection = document.getElementById('prescribingModeSection');
    const input = document.getElementById('prescribingMode');

    if (!code) {
        if (box) box.classList.add('hidden');
        if (modeSection) modeSection.classList.add('hidden');
        return;
    }

    if (box) box.classList.remove('hidden');
    if (modeSection) modeSection.classList.remove('hidden');

    if (pack) {
        // Formulary is available
        if (box) {
            box.classList.remove('unavailable');
            box.classList.add('available');
        }
        if (iconSpan && window.ZimRxIcon) {
            iconSpan.innerHTML = ZimRxIcon.render('info-circle', 14);
        }
        if (textSpan) {
            const rawTpl = i18n.formulary_available_desc || 'Dual Mode available for this region ({brands} brands, {manufacturers} manufacturers, {templates} templates).';
            const lang = window.ZimRxCurrentLang || 'en';
            const formatNum = (num) => {
                const formatted = Number(num || 0).toLocaleString('en-US');
                if (lang === 'bn') {
                    const bnDigits = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
                    return formatted.replace(/[0-9]/g, d => bnDigits[+d]);
                }
                return formatted;
            };
            textSpan.textContent = rawTpl
                .replace('{brands}', formatNum(pack.brands_count))
                .replace('{manufacturers}', formatNum(pack.manufacturers_count))
                .replace('{templates}', formatNum(pack.templates_count));
        }
        if (brandBtn) {
            brandBtn.classList.remove('disabled');
        }
        if (lockBadge) {
            lockBadge.classList.add('hidden');
        }
        const defaultMode = (pack && pack.default_mode)
            ? (['dual', 'brand'].includes(String(pack.default_mode).toLowerCase()) ? 'brand' : 'generic')
            : 'brand';
        selectPrescribingMode(defaultMode);
    } else {
        // Formulary is NOT available: enforce Generic Mode
        if (box) {
            box.classList.remove('available');
            box.classList.add('unavailable');
        }
        if (iconSpan && window.ZimRxIcon) {
            iconSpan.innerHTML = ZimRxIcon.render('info-circle', 14);
        }
        if (textSpan) {
            textSpan.textContent = i18n.formulary_unavailable_desc || 'Generic Mode only for this region.';
        }
        if (brandBtn) {
            brandBtn.classList.add('disabled');
        }
        if (lockBadge) {
            lockBadge.classList.remove('hidden');
        }
        selectPrescribingMode('generic');
    }
}

// Dynamic Address Hierarchy Availability Detection
function updateAddressState(countryCode) {
    const code = (countryCode || '').toUpperCase().trim();
    const addressPacks = window.ZimRxAvailableAddressPacks || {};
    const i18n = window.ZimRxFirstLaunchI18n || {};
    const pack = code ? (addressPacks[code] || null) : null;

    const box = document.getElementById('addressStatusBox');
    const iconSpan = document.getElementById('addressStatusIcon');
    const textSpan = document.getElementById('addressStatusText');

    if (!code) {
        if (box) box.classList.add('hidden');
        return;
    }

    if (box) box.classList.remove('hidden');

    const lang = window.ZimRxCurrentLang || 'en';
    const formatNum = (num) => {
        const formatted = Number(num || 0).toLocaleString('en-US');
        if (lang === 'bn') {
            const bnDigits = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
            return formatted.replace(/[0-9]/g, d => bnDigits[+d]);
        }
        return formatted;
    };

    if (pack) {
        if (box) {
            box.classList.remove('unavailable');
            box.classList.add('available');
        }
        if (iconSpan && window.ZimRxIcon) {
            iconSpan.innerHTML = ZimRxIcon.render('map-pin', 14);
        }
        if (textSpan) {
            const rawTpl = i18n.address_available_desc || 'Address hierarchy available for this region ({places} administrative places & postal codes). Auto-complete dropdown suggestions enabled.';
            textSpan.textContent = rawTpl.replace('{places}', formatNum(pack.places_count));
        }
    } else {
        if (box) {
            box.classList.remove('available');
            box.classList.add('unavailable');
        }
        if (iconSpan && window.ZimRxIcon) {
            iconSpan.innerHTML = ZimRxIcon.render('info-circle', 14);
        }
        if (textSpan) {
            textSpan.textContent = i18n.address_unavailable_desc || 'Address hierarchy is not yet bundled for this region. Patient addresses will be entered as free text. (Open-source contributors can contribute region places via Geo Studio in tools/geo-studio).';
        }
    }
}

function selectChoice(group, el) {
    const parent = el.parentElement;
    parent.querySelectorAll('.choice-btn').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');

    if (group === 'practice') {
        document.getElementById('practiceType').value = el.dataset.val;
        const isMulti = el.dataset.val === 'multi';
        const uLabel = document.getElementById('usernameLabel');
        const uInput = document.getElementById('accountUsername');
        if (uLabel && uInput) {
            if (isMulti) {
                uLabel.textContent = uLabel.dataset.labelAdmin || 'Admin Username';
                uInput.placeholder = uLabel.dataset.placeholderAdmin || 'admin';
            } else {
                uLabel.textContent = uLabel.dataset.labelDoctor || 'Doctor Username';
                uInput.placeholder = uLabel.dataset.placeholderDoctor || 'doctor';
            }
        }
    } else if (group === 'install') {
        document.getElementById('installType').value = el.dataset.val;
    }
}

function showError(msg) {
    const el = document.getElementById('errorMsg');
    el.textContent = msg;
    el.classList.add('visible');
}
function clearError() {
    document.getElementById('errorMsg').classList.remove('visible');
}

function saveSetup() {
    clearError();
    const username = document.getElementById('accountUsername')?.value.trim() || '';
    const password = document.getElementById('password')?.value ?? '';
    const confirm  = document.getElementById('confirmPassword')?.value ?? '';
    const email    = document.getElementById('recoveryEmail')?.value ?? '';
    const practice = document.getElementById('practiceType')?.value ?? 'solo';
    const install  = document.getElementById('installType')?.value ?? 'local';
    const country  = document.getElementById('practiceCountry')?.value 
        || new URLSearchParams(window.location.search).get('country') 
        || 'INT';
    const prescribingMode = document.getElementById('prescribingMode')?.value 
        || new URLSearchParams(window.location.search).get('prescribing_mode') 
        || 'generic';
    const clinicalLang = document.getElementById('clinicalLang')?.value 
        || new URLSearchParams(window.location.search).get('clinical_lang') 
        || 'en';
    const helpLang = document.getElementById('helpLang')?.value 
        || new URLSearchParams(window.location.search).get('help_lang') 
        || clinicalLang;
    const interfaceLang = document.getElementById('flLangSelect')?.value 
        || new URLSearchParams(window.location.search).get('lang') 
        || 'en';

    if (!username) { showError('Please enter a username.'); return; }
    if (!/^[a-zA-Z0-9_.-]{2,50}$/.test(username)) {
        showError('Username must be 2-50 characters (letters, numbers, underscores, dots, or hyphens).');
        return;
    }
    if (!password) {
        if (!window.ZimRxHasExistingAccount) {
            showError('Please enter a password.');
            return;
        }
    } else {
        if (password.length < 8) { showError('Password must be at least 8 characters.'); return; }
        if (password !== confirm) { showError('Passwords do not match.'); return; }
    }

    const btn = document.getElementById('btnSaveSetup');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Setting up practice and databases...';

    const setupToken = document.getElementById('setupToken')?.value.trim() || new URLSearchParams(window.location.search).get('setup_token') || '';

    fetch('api/first_launch_save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            practice_type: practice,
            install_type: install,
            country: country,
            prescribing_mode: prescribingMode,
            clinical_lang: clinicalLang,
            help_lang: helpLang,
            interface_lang: interfaceLang,
            username: username,
            password, email,
            setup_token: setupToken
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            showError(data.error);
            btn.disabled = false;
            btn.innerHTML = origHtml;
            return;
        }
        if (data.help_lang) {
            localStorage.setItem('zimrx_help_lang', data.help_lang);
        }
        window.location = data.redirect;
    })
    .catch(() => {
        showError('A network error occurred. Please try again.');
        btn.disabled = false;
        btn.innerHTML = origHtml;
    });
}

// Back to Step 3 from Step 4
function goStep3FromStep4() {
    const params = new URLSearchParams(window.location.search);
    params.set('step', '3');
    window.location = 'first_launch.php?' + params.toString();
}

// Step 4 doctor profile setup
function saveDoctor() {
    clearError();
    const btn = document.getElementById('btnSaveDoctor');
    if (!btn) return;
    const origHtml = btn.innerHTML;

    const fields = ['name_native','qualifications_native','designation_native','institute_native',
                    'speciality_native','license_native','phone_native',
                    'name_en','qualifications_en','designation_en','institute_en',
                    'speciality_en','license_en','phone_en', 'csrf_token'];

    const form = new FormData();
    fields.forEach(f => {
        const el = document.getElementById(f);
        if (el) form.append(f, el.value);
    });

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Saving…';

    fetch('api/header_onboarding_ajax.php', {
        method: 'POST',
        body: form
    })
    .then(r => r.text())
    .then(res => {
        if (res.trim() === '1') {
            window.location = 'prescription.php';
        } else {
            showError('Could not save doctor info: ' + res);
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    })
    .catch(() => {
        showError('Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = origHtml;
    });
}

// Step 4 skip and finalize setup
function skipDoctor() {
    clearError();
    const btn = document.getElementById('btnSaveDoctor');
    const skipLink = document.querySelector('.step4-skip a');
    if (skipLink) {
        skipLink.style.pointerEvents = 'none';
        skipLink.textContent = 'Finalizing...';
    }
    if (btn) btn.disabled = true;

    const csrf = document.getElementById('csrf_token')?.value || '';
    const form = new FormData();
    form.append('csrf_token', csrf);
    form.append('skip', '1');

    fetch('api/header_onboarding_ajax.php', {
        method: 'POST',
        body: form
    })
    .then(r => r.text())
    .then(res => {
        if (res.trim() === '1') {
            window.location = 'prescription.php';
        } else {
            showError('Could not finalize setup: ' + res);
            if (btn) btn.disabled = false;
            if (skipLink) {
                skipLink.style.pointerEvents = 'auto';
                skipLink.textContent = "Skip - I'll fill this in later";
            }
        }
    })
    .catch(() => {
        showError('Network error while finalizing setup.');
        if (btn) btn.disabled = false;
        if (skipLink) {
            skipLink.style.pointerEvents = 'auto';
            skipLink.textContent = "Skip - I'll fill this in later";
        }
    });
}

// Enhance select elements with the unified global dropdown design system
(function() {
    function initDropdowns() {
        if (window.ZimRxDropdown && typeof ZimRxDropdown.enhanceSelect === 'function') {
            document.querySelectorAll('.field select').forEach(function (sel) {
                ZimRxDropdown.enhanceSelect(sel);
            });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDropdowns);
    } else {
        initDropdowns();
    }
})();

// Automatically sync default patient clinical and guide languages based on selected country
(function() {
    function setupCountryClinicalSync() {
        const countrySelect = document.getElementById('practiceCountry');
        const clinicalSelect = document.getElementById('clinicalLang');
        const helpSelect = document.getElementById('helpLang');
        if (!countrySelect) return;

        countrySelect.addEventListener('change', function() {
            clearError();
            const countryCode = (this.value || '').toUpperCase();
            if (!countryCode) {
                if (typeof updateFormularyState === 'function') {
                    updateFormularyState('');
                }
                if (typeof updateAddressState === 'function') {
                    updateAddressState('');
                }
                return;
            }
            const map = window.ZimRxCountryClinicalMap || {};
            const suggestedLang = map[countryCode] || 'en';

            if (clinicalSelect && clinicalSelect.value !== suggestedLang) {
                clinicalSelect.value = suggestedLang;
                clinicalSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (helpSelect && helpSelect.value !== suggestedLang) {
                helpSelect.value = suggestedLang;
                helpSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }

            // Dynamically refresh formulary pack & address hierarchy availability
            if (typeof updateFormularyState === 'function') {
                updateFormularyState(countryCode);
            }
            if (typeof updateAddressState === 'function') {
                updateAddressState(countryCode);
            }
        });

        // Initialize formulary and address hierarchy status for current selection on Step 2
        if (countrySelect) {
            if (typeof updateFormularyState === 'function') {
                updateFormularyState(countrySelect.value);
            }
            if (typeof updateAddressState === 'function') {
                updateAddressState(countrySelect.value);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupCountryClinicalSync);
    } else {
        setupCountryClinicalSync();
    }
})();


