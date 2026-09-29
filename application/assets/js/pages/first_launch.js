// First-launch setup wizard handling practice configuration, credentials, and recovery keys.
// Step 2 navigation
function goStep2() {
    window.location = 'first_launch.php?step=2';
}

function selectChoice(group, el) {
    const parent = el.parentElement;
    parent.querySelectorAll('.choice-btn').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');

    if (group === 'practice') {
        document.getElementById('practiceType').value = el.dataset.val;
        const isMulti = el.dataset.val === 'multi';
        document.getElementById('autoLoginWrap').classList.toggle('hidden', isMulti);
        document.getElementById('adminUsernameWrap').classList.toggle('hidden', !isMulti);
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
    const password = document.getElementById('password')?.value ?? '';
    const confirm  = document.getElementById('confirmPassword')?.value ?? '';
    const email    = document.getElementById('recoveryEmail')?.value ?? '';
    const practice = document.getElementById('practiceType')?.value ?? 'solo';
    const install  = document.getElementById('installType')?.value ?? 'local';
    const autoLogin = document.getElementById('autoLogin')?.checked ?? false;
    const adminUser = document.getElementById('adminUsername')?.value ?? '';

    if (!password) { showError('Please enter a password.'); return; }
    if (password.length < 14) { showError('Password must be at least 14 characters.'); return; }
    if (password !== confirm) { showError('Passwords do not match.'); return; }

    const btn = document.getElementById('btnSaveSetup');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Setting up…';

    const setupToken = document.getElementById('setupToken')?.value.trim() || new URLSearchParams(window.location.search).get('setup_token') || '';

    fetch('api/first_launch_save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            practice_type: practice,
            install_type: install,
            password, email,
            auto_login: autoLogin,
            admin_username: adminUser,
            setup_token: setupToken
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            showError(data.error);
            btn.disabled = false;
            btn.textContent = 'Continue →';
            return;
        }
        // Save recovery key in session storage for step 3
        if (data.recovery_key) {
            sessionStorage.setItem('zimrx_rkey', data.recovery_key);
        }
        window.location = data.redirect;
    })
    .catch(() => {
        showError('A network error occurred. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Continue →';
    });
}

// Step 3 doctor profile setup
function saveDoctor() {
    clearError();
    const btn = document.getElementById('btnSaveDoctor');
    if (!btn) return;

    const fields = ['name_bn','qualifications_bn','designation_bn','institute_bn',
                    'speciality_bn','bmdc_bn','phone_bn',
                    'name_en','qualifications_en','designation_en','institute_en',
                    'speciality_en','bmdc_en','phone_en'];

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
            btn.textContent = 'Save & Enter ZimRx →';
        }
    })
    .catch(() => {
        showError('Network error. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Save & Enter ZimRx →';
    });
}

// Populate recovery key from session storage on step 3
(function() {
    const rkey = sessionStorage.getItem('zimrx_rkey');
    if (rkey) {
        sessionStorage.removeItem('zimrx_rkey');
        const box = document.createElement('div');
        box.className = 'recovery-box';
        box.innerHTML = `
            <div class="warn-title">⚠️ Save Your Recovery Key</div>
            <div class="rkey">${rkey}</div>
            <p>Write this down. If you forget your password and have no internet, this is your only way back in. It is also saved at <code>userdata/recovery.key</code>.</p>
        `;
        const panel = document.getElementById('panel3');
        if (panel) panel.insertBefore(box, panel.firstChild);
    }
})();
