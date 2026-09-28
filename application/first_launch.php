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



$setupComplete = fl_config_get($pdo, 'setup_complete') === '1';
$step = (int)($_GET['step'] ?? 1);

$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$isLoopback = in_array($remoteAddr, ['127.0.0.1', '::1', ''], true)
    || str_starts_with($remoteAddr, '127.')
    || (isset($_SERVER['SERVER_ADDR']) && $remoteAddr === $_SERVER['SERVER_ADDR']);

$setupTokenFile = ZIMRX_USERDATA_DIR . '/setup_token.txt';
if (!$isLoopback && !$setupComplete) {
    if (!file_exists($setupTokenFile)) {
        $initialToken = bin2hex(random_bytes(16));
        @file_put_contents($setupTokenFile, $initialToken);
        @chmod($setupTokenFile, 0600);
    }
}
$queryToken = trim((string)($_GET['setup_token'] ?? ''));

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
    <link rel="stylesheet" href="assets/css/first_launch.css?v=<?= filemtime(__DIR__ . '/assets/css/first_launch.css') ?>">
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

                <?php if (!$isLoopback): ?>
                <div class="section-label" style="color: #fbbf24;">Server Setup Token</div>
                <div class="field" style="margin-bottom: 1.25rem;">
                    <label>Setup Token <span style="color:#64748b;font-weight:400">(from userdata/setup_token.txt)</span></label>
                    <input type="text" id="setupToken" value="<?= htmlspecialchars($queryToken, ENT_QUOTES, 'UTF-8') ?>" placeholder="Paste setup token generated on server host" autocomplete="off" style="font-family: monospace;">
                    <small style="color: #94a3b8; display: block; margin-top: 0.35rem;">Because this setup wizard is accessed over the network, please enter the security token found in <code>userdata/setup_token.txt</code> on the server to claim administration.</small>
                </div>
                <?php endif; ?>

                <div class="section-label">Security</div>
                <div class="field-row">
                    <div class="field">
                        <label>Password <span style="color:#64748b;font-weight:400">(min 14 chars)</span></label>
                        <input type="password" id="password" placeholder="At least 14 characters">
                    </div>
                    <div class="field">
                        <label>Confirm Password</label>
                        <input type="password" id="confirmPassword" placeholder="••••••••••••••">
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

<script src="assets/js/pages/first_launch.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/first_launch.js') ?>"></script>
</body>
</html>
