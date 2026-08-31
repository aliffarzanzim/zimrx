<?php
require_once 'auth.php';
require_once 'db.php';

// Helper
function fl_config_get(PDO $pdo, string $key): string {
    if (!zimrx_db_table_exists($pdo, 'zimrx_app_config')) return '';
    $stmt = $pdo->prepare("SELECT config_value FROM zimrx_app_config WHERE config_key = :key LIMIT 1");
    $stmt->execute(['key' => $key]);
    return (string)($stmt->fetchColumn() ?? '');
}

// ── Quick Demo Mode Trigger ────────────────────────────────────────────────
if (!empty($_GET['quick_demo'])) {
    if (zimrx_db_table_exists($pdo, 'zimrx_app_config')) {
        $configs = [
            'setup_complete' => '1',
            'practice_type'  => 'solo',
            'install_type'   => 'local',
            'auto_login'     => '1',
            'recovery_email' => '',
        ];
        $cfgStmt = $pdo->prepare(
            "INSERT INTO zimrx_app_config (config_key, config_value, updated_at)
             VALUES (:key, :val, CURRENT_TIMESTAMP)
             ON CONFLICT(config_key) DO UPDATE SET config_value = :val, updated_at = CURRENT_TIMESTAMP"
        );
        foreach ($configs as $k => $v) {
            $cfgStmt->execute(['key' => $k, 'val' => $v]);
        }
    }

    // Populate standard sample doctor profile if empty
    if (zimrx_db_table_exists($pdo, 'zimrx_doctors')) {
        $doc = $pdo->query("SELECT id, name_en FROM zimrx_doctors WHERE id = 1 LIMIT 1")->fetch();
        if ($doc && empty(trim($doc['name_en'] ?? ''))) {
            $pdo->prepare(
                "UPDATE zimrx_doctors
                 SET name_en = 'Prof. Dr. M. A. Karim',
                     name_bn = 'প্রফেসর ডাঃ মোঃ আব্দুল করিম',
                     degrees_en = 'MBBS (DMC), FCPS (Medicine), MD (Internal Medicine)',
                     degrees_bn = 'এমবিবিএস (ডিএমসি), এফসিপিএস (মেডিসিন), এমডি (ইন্টারনাল মেডিসিন)',
                     designation_en = 'Professor & Head of Department of Medicine',
                     designation_bn = 'অধ্যাপক ও বিভাগীয় প্রধান, মেডিসিন বিভাগ',
                     hospital_en = 'Dhaka Medical College & Hospital',
                     hospital_bn = 'ঢাকা মেডিকেল কলেজ ও হাসপাতাল',
                     bmdc_reg_no = 'A-12345',
                     phone = '+8801700000000',
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = 1"
            )->execute();
        }
    }

    // Ensure active session for default solo doctor
    $user = zimrx_db_table_exists($pdo, 'zimrx_user_accounts')
        ? $pdo->query("SELECT id, display_name FROM zimrx_user_accounts WHERE role = 'doctor' AND doctor_id = 1 LIMIT 1")->fetch()
        : null;

    $_SESSION['user_id']   = $user ? (int)$user['id'] : 1;
    $_SESSION['user_role'] = 'doctor';
    $_SESSION['user_name'] = $user && !empty($user['display_name']) ? $user['display_name'] : 'Dr. M. A. Karim';
    $_SESSION['doctor_id'] = 1;

    header('Location: prescription.php');
    exit();
}

$setupComplete = fl_config_get($pdo, 'setup_complete') === '1';
$step = (int)($_GET['step'] ?? 1);

// If setup complete and not on step 3 (doctor onboarding), redirect to login
if ($setupComplete && $step !== 3) {
    header('Location: index.php');
    exit();
}

// Step 3 requires being logged in
if ($step === 3 && !is_logged_in()) {
    header('Location: first_launch.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZimRx — Setup</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            color: #e2e8f0;
        }

        .wizard-wrap {
            width: 100%;
            max-width: 560px;
        }

        /* Step Indicator */
        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
            gap: 0;
        }

        .step-dot {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #1e293b;
            border: 2px solid #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            transition: all 0.3s;
            flex-shrink: 0;
        }
        .step-dot.active {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
            box-shadow: 0 0 0 4px rgba(37,99,235,0.2);
        }
        .step-dot.done {
            background: #16a34a;
            border-color: #16a34a;
            color: #fff;
        }
        .step-line {
            flex: 1;
            height: 2px;
            background: #334155;
            max-width: 80px;
        }
        .step-line.done { background: #16a34a; }

        /* Card */
        .wizard-card {
            background: #1e293b;
            border-radius: 16px;
            border: 1px solid #334155;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
            overflow: hidden;
        }

        .card-header {
            padding: 2rem 2rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid #334155;
        }

        .logo-icon {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .card-header h1 { font-size: 1.6rem; font-weight: 700; color: #f1f5f9; margin-bottom: 0.4rem; }
        .card-header p  { font-size: 0.9rem; color: #94a3b8; line-height: 1.5; }

        .card-body { padding: 2rem; }

        /* Step panels */
        .step-panel { display: none; }
        .step-panel.active { display: block; }

        /* Form elements */
        .field { margin-bottom: 1.25rem; }
        .field label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 0.5rem;
        }
        .field input[type="text"],
        .field input[type="email"],
        .field input[type="password"],
        .field select {
            width: 100%;
            padding: 0.6rem 0.9rem;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            color: #e2e8f0;
            font-size: 0.9rem;
            font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .field input:focus, .field select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
        }
        .field select option { background: #1e293b; }

        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        .check-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 1rem;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            cursor: pointer;
            margin-bottom: 1.25rem;
            transition: border-color 0.2s;
        }
        .check-row:hover { border-color: #2563eb; }
        .check-row input[type="checkbox"] { width: 16px; height: 16px; accent-color: #2563eb; flex-shrink: 0; }
        .check-row span { font-size: 0.9rem; color: #e2e8f0; }
        .check-row small { display: block; font-size: 0.78rem; color: #64748b; margin-top: 2px; }

        .section-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            margin: 1.5rem 0 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #334155;
        }

        .choice-group { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.25rem; }
        .choice-btn {
            padding: 0.9rem;
            border: 2px solid #334155;
            border-radius: 10px;
            background: #0f172a;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
            color: #94a3b8;
        }
        .choice-btn:hover { border-color: #475569; color: #e2e8f0; }
        .choice-btn.selected { border-color: #2563eb; background: rgba(37,99,235,0.1); color: #93c5fd; }
        .choice-btn .choice-icon { font-size: 1.6rem; margin-bottom: 0.4rem; }
        .choice-btn .choice-label { font-size: 0.85rem; font-weight: 600; }
        .choice-btn .choice-desc { font-size: 0.75rem; color: #64748b; margin-top: 2px; }

        .hidden { display: none !important; }

        /* Recovery box */
        .recovery-box {
            background: rgba(234,179,8,0.1);
            border: 1px solid rgba(234,179,8,0.3);
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
        }
        .recovery-box .rkey {
            font-family: monospace;
            font-size: 1.05rem;
            font-weight: 700;
            color: #fde047;
            letter-spacing: 0.08em;
            word-break: break-all;
            margin: 0.5rem 0;
        }
        .recovery-box p { font-size: 0.82rem; color: #fef08a; line-height: 1.5; }
        .recovery-box .warn-title { font-size: 0.8rem; font-weight: 700; color: #fde047; margin-bottom: 0.3rem; }

        /* Buttons */
        .btn-row { display: flex; gap: 0.75rem; margin-top: 1.5rem; }
        .btn {
            flex: 1;
            padding: 0.75rem 1.5rem;
            border-radius: 9px;
            font-size: 0.95rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-primary:disabled { background: #475569; cursor: not-allowed; }
        .btn-ghost { background: transparent; color: #94a3b8; border: 1px solid #334155; flex: 0 0 auto; }
        .btn-ghost:hover { background: #334155; color: #e2e8f0; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-success:hover { background: #15803d; }
        .btn-full { flex: none; width: 100%; }

        /* Error */
        .error-msg {
            background: rgba(220,38,38,0.1);
            border: 1px solid rgba(220,38,38,0.3);
            color: #fca5a5;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
            margin-bottom: 1rem;
            display: none;
        }
        .error-msg.visible { display: block; }

        /* Onboarding form (step 3) */
        .bilingual-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem; }
        .bilingual-grid .field { margin-bottom: 0; }
        .lang-tag {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.1rem 0.4rem;
            border-radius: 4px;
            margin-right: 0.3rem;
            text-transform: uppercase;
            vertical-align: middle;
        }
        .lang-tag.bn { background: rgba(234,179,8,0.15); color: #fde047; }
        .lang-tag.en { background: rgba(37,99,235,0.15); color: #93c5fd; }

        /* Spinner */
        .spinner {
            width: 18px; height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-right: 0.5rem;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .step3-skip { text-align: center; margin-top: 1rem; }
        .step3-skip a { color: #64748b; font-size: 0.82rem; text-decoration: none; }
        .step3-skip a:hover { color: #94a3b8; }

        .btn-demo-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(37, 99, 235, 0.12);
            color: #60a5fa;
            border: 1px dashed rgba(59, 130, 246, 0.5);
            border-radius: 9px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            box-sizing: border-box;
        }
        .btn-demo-link:hover {
            background: rgba(37, 99, 235, 0.25);
            border-color: #3b82f6;
            color: #93c5fd;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
<div class="wizard-wrap">

    <!-- Step Indicator -->
    <div class="step-indicator" id="stepIndicator">
        <div class="step-dot <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>" id="dot1">
            <?= $step > 1 ? '✓' : '1' ?>
        </div>
        <div class="step-line <?= $step > 1 ? 'done' : '' ?>"></div>
        <div class="step-dot <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>" id="dot2">
            <?= $step > 2 ? '✓' : '2' ?>
        </div>
        <div class="step-line <?= $step > 2 ? 'done' : '' ?>"></div>
        <div class="step-dot <?= $step >= 3 ? 'active' : '' ?>" id="dot3">3</div>
    </div>

    <div class="wizard-card">
        <div class="card-header">
            <div class="logo-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                </svg>
            </div>
            <h1 id="cardTitle">Welcome to ZimRx</h1>
            <p id="cardSubtitle">A highly customizable Prescription Software<br>by Alif Farzan Zim (DMC K-79)</p>
        </div>

        <div class="card-body">
            <div class="error-msg" id="errorMsg"></div>

            <?php if ($step === 1): ?>
            <!-- ── STEP 1: Welcome ───────────────────────── -->
            <div class="step-panel active" id="panel1">
                <p style="color:#94a3b8;line-height:1.7;margin-bottom:1.5rem;font-size:0.95rem;">
                    ZimRx helps you write, manage, and print professional bilingual prescriptions — completely free.
                    <br><br>
                    Let's get you set up in under a minute.
                </p>
                <button class="btn btn-primary btn-full" onclick="goStep2()">Get Started →</button>

                <div style="margin-top: 1rem; text-align: center;">
                    <a href="first_launch.php?quick_demo=1" class="btn-demo-link" title="Skip all setup wizards and immediately launch the prescription interface with demo defaults">
                        ⚡ Skip All (Quick Demo) →
                    </a>
                </div>
            </div>

            <?php elseif ($step === 2): ?>
            <!-- ── STEP 2: Setup ──────────────────────────── -->
            <div class="step-panel active" id="panel2">

                <div class="section-label">Installation Type</div>
                <div class="choice-group" id="installChoice">
                    <div class="choice-btn selected" data-val="local" onclick="selectChoice('install', this)">
                        <div class="choice-icon">💻</div>
                        <div class="choice-label">Local Device</div>
                        <div class="choice-desc">This PC only</div>
                    </div>
                    <div class="choice-btn" data-val="server" onclick="selectChoice('install', this)">
                        <div class="choice-icon">🖥️</div>
                        <div class="choice-label">Server</div>
                        <div class="choice-desc">Hosted / network access</div>
                    </div>
                </div>
                <input type="hidden" id="installType" value="local">

                <div class="section-label">Practice Type</div>
                <div class="choice-group" id="practiceChoice">
                    <div class="choice-btn selected" data-val="solo" onclick="selectChoice('practice', this)">
                        <div class="choice-icon">👨‍⚕️</div>
                        <div class="choice-label">Solo Doctor</div>
                        <div class="choice-desc">Single physician</div>
                    </div>
                    <div class="choice-btn" data-val="multi" onclick="selectChoice('practice', this)">
                        <div class="choice-icon">🏥</div>
                        <div class="choice-label">Multiple Doctors</div>
                        <div class="choice-desc">Clinic / hospital</div>
                    </div>
                </div>
                <input type="hidden" id="practiceType" value="solo">

                <!-- Multi-doctor: admin username -->
                <div id="adminUsernameWrap" class="hidden">
                    <div class="section-label">Admin Account</div>
                    <div class="field">
                        <label>Admin Username</label>
                        <input type="text" id="adminUsername" placeholder="admin" autocomplete="off">
                    </div>
                </div>

                <div class="section-label">Security</div>
                <div class="field-row">
                    <div class="field">
                        <label>Password</label>
                        <input type="password" id="password" placeholder="••••••••">
                    </div>
                    <div class="field">
                        <label>Confirm Password</label>
                        <input type="password" id="confirmPassword" placeholder="••••••••">
                    </div>
                </div>

                <div class="field">
                    <label>Recovery Email <span style="color:#64748b;font-weight:400">(for password reset)</span></label>
                    <input type="email" id="recoveryEmail" placeholder="doctor@example.com">
                </div>

                <!-- Solo only: auto-login -->
                <div id="autoLoginWrap">
                    <label class="check-row" for="autoLogin">
                        <input type="checkbox" id="autoLogin" checked>
                        <div>
                            <span>Auto-login on startup</span>
                            <small>Opens directly to prescription — recommended for personal devices</small>
                        </div>
                    </label>
                </div>

                <div class="btn-row">
                    <button class="btn btn-ghost" onclick="window.location='first_launch.php?step=1'">← Back</button>
                    <button class="btn btn-primary" id="btnSaveSetup" onclick="saveSetup()">Continue →</button>
                </div>

                <div style="margin-top: 1rem; text-align: center;">
                    <a href="first_launch.php?quick_demo=1" class="btn-demo-link" title="Skip all setup wizards and immediately launch the prescription interface with demo defaults">
                        ⚡ Skip All (Quick Demo) →
                    </a>
                </div>
            </div>

            <?php elseif ($step === 3): ?>
            <!-- ── STEP 3: Doctor Onboarding ──────────────── -->
            <div class="step-panel active" id="panel3">
                <?php
                // Show recovery key if just set up
                $rkey = $_SESSION['recovery_key_shown'] ?? '';
                if (isset($_SESSION['recovery_key_shown'])) {
                    unset($_SESSION['recovery_key_shown']);
                }
                ?>
                <?php if ($rkey): ?>
                <div class="recovery-box">
                    <div class="warn-title">⚠️ Save Your Recovery Key</div>
                    <div class="rkey"><?= htmlspecialchars($rkey) ?></div>
                    <p>Write this down or store it safely. If you forget your password and have no internet, this is your only way back in. It is also saved at <code>userdata/recovery.key</code>.</p>
                </div>
                <?php endif; ?>

                <p style="color:#94a3b8;font-size:0.88rem;margin-bottom:1.25rem;">
                    Fill in your profile to set up your prescription letterhead. You can always update this later from settings.
                </p>

                <div class="section-label">Name</div>
                <div class="bilingual-grid">
                    <div class="field">
                        <label><span class="lang-tag bn">BN</span>নাম</label>
                        <input type="text" id="name_bn" placeholder="ডাঃ আহমেদ">
                    </div>
                    <div class="field">
                        <label><span class="lang-tag en">EN</span>Name</label>
                        <input type="text" id="name_en" placeholder="Dr. Ahmed">
                    </div>
                </div>

                <div class="section-label">Qualifications</div>
                <div class="bilingual-grid">
                    <div class="field">
                        <label><span class="lang-tag bn">BN</span>যোগ্যতা</label>
                        <input type="text" id="qualifications_bn" placeholder="এমবিবিএস, এমডি">
                    </div>
                    <div class="field">
                        <label><span class="lang-tag en">EN</span>Qualifications</label>
                        <input type="text" id="qualifications_en" placeholder="MBBS, MD">
                    </div>
                </div>

                <div class="section-label">Designation & Institute</div>
                <div class="bilingual-grid">
                    <div class="field">
                        <label><span class="lang-tag bn">BN</span>পদবি</label>
                        <input type="text" id="designation_bn" placeholder="সহকারী অধ্যাপক">
                    </div>
                    <div class="field">
                        <label><span class="lang-tag en">EN</span>Designation</label>
                        <input type="text" id="designation_en" placeholder="Asst. Professor">
                    </div>
                </div>
                <div class="bilingual-grid">
                    <div class="field">
                        <label><span class="lang-tag bn">BN</span>প্রতিষ্ঠান</label>
                        <input type="text" id="institute_bn" placeholder="ঢামেক">
                    </div>
                    <div class="field">
                        <label><span class="lang-tag en">EN</span>Institute</label>
                        <input type="text" id="institute_en" placeholder="DMCH">
                    </div>
                </div>

                <div class="section-label">Specialty & BMDC</div>
                <div class="bilingual-grid">
                    <div class="field">
                        <label><span class="lang-tag bn">BN</span>বিশেষত্ব</label>
                        <input type="text" id="speciality_bn" placeholder="হৃদরোগ বিশেষজ্ঞ">
                    </div>
                    <div class="field">
                        <label><span class="lang-tag en">EN</span>Specialty</label>
                        <input type="text" id="speciality_en" placeholder="Cardiologist">
                    </div>
                </div>
                <div class="bilingual-grid">
                    <div class="field">
                        <label><span class="lang-tag bn">BN</span>বিএমডিসি</label>
                        <input type="text" id="bmdc_bn" placeholder="এ-১২৩৪৫">
                    </div>
                    <div class="field">
                        <label><span class="lang-tag en">EN</span>BMDC No.</label>
                        <input type="text" id="bmdc_en" placeholder="A-12345">
                    </div>
                </div>

                <div class="section-label">Phone</div>
                <div class="bilingual-grid">
                    <div class="field">
                        <label><span class="lang-tag bn">BN</span>ফোন (Bengali)</label>
                        <input type="text" id="phone_bn" placeholder="০১৭১২-XXXXXX">
                    </div>
                    <div class="field">
                        <label><span class="lang-tag en">EN</span>Phone (English)</label>
                        <input type="text" id="phone_en" placeholder="01712-XXXXXX">
                    </div>
                </div>

                <div class="btn-row" style="margin-top:1.5rem;">
                    <button class="btn btn-primary" id="btnSaveDoctor" onclick="saveDoctor()">Save & Enter ZimRx →</button>
                </div>
                <div class="step3-skip">
                    <a href="prescription.php">Skip — I'll fill this in later</a>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- card-body -->
    </div><!-- wizard-card -->

</div><!-- wizard-wrap -->

<script>
// ── Step 2 Logic ──────────────────────────────────────
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
    if (password.length < 4) { showError('Password must be at least 4 characters.'); return; }
    if (password !== confirm) { showError('Passwords do not match.'); return; }

    const btn = document.getElementById('btnSaveSetup');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Setting up…';

    fetch('api/first_launch_save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            practice_type: practice,
            install_type: install,
            password, email,
            auto_login: autoLogin,
            admin_username: adminUser
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
        // Store recovery key in sessionStorage to display on step 3
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

// ── Step 3 Logic ──────────────────────────────────────
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

    fetch('header_onboarding_ajax.php', {
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

// Show recovery key from sessionStorage on step 3
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
</script>
</body>
</html>
