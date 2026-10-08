<?php
declare(strict_types=1);

// First launch wizard: onboarding flow for installation type, admin password, recovery key, and initial doctor letterhead.

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/api/zrx_icons.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/LocaleService.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/CountryService.php';

use ZimRx\Services\LocaleService;
use ZimRx\Services\CountryService;

// Helper
function fl_config_get(PDO $pdo, string $key): string {
    if (!zimrx_db_table_exists($pdo, 'zimrx_app_config')) return '';
    $stmt = $pdo->prepare("SELECT config_value FROM zimrx_app_config WHERE config_key = :key LIMIT 1");
    $stmt->execute(['key' => $key]);
    return (string)($stmt->fetchColumn() ?? '');
}

$setupComplete = fl_config_get($pdo, 'setup_complete') === '1';
$step = (int)($_GET['step'] ?? 1);

// Dynamic Language Discovery from available language packs
$localeService = LocaleService::default();
$availableLanguages = $localeService->availableForCatalog('first_launch');

// Determine active language: from query, or session, or default 'en'
$currentLang = trim((string)($_GET['lang'] ?? $_SESSION['setup_lang'] ?? 'en'));
if (!array_key_exists($currentLang, $availableLanguages)) {
    $currentLang = 'en';
}
$_SESSION['setup_lang'] = $currentLang;

$fl = $localeService->catalog($currentLang, 'first_launch');
$countryGroups = CountryService::getGrouped();
$availableFormularies = CountryService::getAvailableFormularies();
$availableAddressPacks = CountryService::getAvailableAddressPacks();
$defaultCountry = trim((string)($_GET['country'] ?? ''));
$defaultHasFormulary = ($defaultCountry !== '') ? CountryService::hasFormulary($defaultCountry) : false;
$defaultHasAddressHierarchy = ($defaultCountry !== '') ? CountryService::hasAddressHierarchy($defaultCountry) : false;
$defaultPrescribingMode = trim((string)($_GET['prescribing_mode'] ?? ''));
if ($defaultPrescribingMode === '') {
    $defaultPrescribingMode = $defaultHasFormulary ? 'brand' : 'generic';
} elseif (!$defaultHasFormulary) {
    $defaultPrescribingMode = 'generic';
}
$defaultClinicalLang = trim((string)($_GET['clinical_lang'] ?? ''));
if ($defaultClinicalLang === '') {
    $defaultClinicalLang = ($defaultCountry !== '')
        ? (CountryService::getDefaultClinicalLanguage($defaultCountry) ?? ($currentLang === 'bn' ? 'bn' : 'en'))
        : ($currentLang === 'bn' ? 'bn' : 'en');
}
$defaultHelpLang = trim((string)($_GET['help_lang'] ?? ''));
if ($defaultHelpLang === '') {
    $defaultHelpLang = $defaultClinicalLang;
}

$existingAccountUsername = '';
$existingRecoveryEmail = fl_config_get($pdo, 'recovery_email');
if (DbSchema::tableExists($pdo, 'zimrx_user_accounts')) {
    $st = $pdo->query("SELECT username FROM zimrx_user_accounts WHERE doctor_id = 1 OR role = 'doctor' ORDER BY id ASC LIMIT 1");
    $existingAccountUsername = (string)($st->fetchColumn() ?: '');
    if ($existingAccountUsername === 'root') {
        $existingAccountUsername = '';
    }
}
$defaultUsername = ($existingAccountUsername !== '' && $existingAccountUsername !== 'root') ? $existingAccountUsername : '';
$defaultRecoveryEmail = $existingRecoveryEmail;

$isLoopback = zimrx_is_loopback_request();

$setupTokenFile = ZIMRX_USERDATA_DIR . '/setup_token.txt';
if (!$isLoopback && !$setupComplete) {
    if (!file_exists($setupTokenFile)) {
        $initialToken = bin2hex(random_bytes(16));
        @file_put_contents($setupTokenFile, $initialToken);
        @chmod($setupTokenFile, 0600);
    }
}
$queryToken = trim((string)($_GET['setup_token'] ?? ''));

// If setup is already finalized, redirect to login
if ($setupComplete) {
    header('Location: index.php');
    exit();
}

// Step 4 requires being logged in (session created from Step 3)
if ($step === 4 && !is_logged_in()) {
    header('Location: first_launch.php?step=3');
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($fl['page_title'] ?? 'ZimRx - Setup') ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link rel="stylesheet" href="assets/css/layout/global.css?v=<?= filemtime(__DIR__ . '/assets/css/layout/global.css') ?>">
    <link rel="stylesheet" href="assets/css/pages/first_launch.css?v=<?= filemtime(__DIR__ . '/assets/css/pages/first_launch.css') ?>">
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
        <div class="step-dot <?= $step >= 3 ? ($step > 3 ? 'done' : 'active') : '' ?>" id="dot3">
            <?= $step > 3 ? '✓' : '3' ?>
        </div>
        <div class="step-line <?= $step > 3 ? 'done' : '' ?>"></div>
        <div class="step-dot <?= $step >= 4 ? 'active' : '' ?>" id="dot4">4</div>
    </div>

    <div class="wizard-card">
        <div class="card-header">
            <?php if ($step > 1): ?>
            <div class="fl-lang-picker">
                <span class="fl-lang-icon"><?= zrx_icon('globe', 13) ?></span>
                <select id="flLangSelect" aria-label="<?= htmlspecialchars($fl['setup_language_label'] ?? 'Choose your language') ?>" onchange="changeSetupLang(this.value)">
                    <?php foreach ($availableLanguages as $code => $meta): 
                        $label = ($meta['native_name'] === $meta['name'])
                            ? $meta['name']
                            : $meta['native_name'] . ' (' . $meta['name'] . ')';
                    ?>
                    <option value="<?= htmlspecialchars($code) ?>" <?= $code === $currentLang ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="wizard-header-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                </svg>
            </div>
            <?php
            $headerTitle = match($step) {
                1 => $fl['welcome_title'] ?? 'Welcome to ZimRx',
                2 => $fl['step2_title'] ?? 'Practice Region & Languages',
                3 => $fl['step3_title'] ?? 'Deployment & Security',
                4 => $fl['step4_title'] ?? 'Prescription Letterhead',
                default => $fl['welcome_title'] ?? 'Welcome to ZimRx'
            };
            $headerSubtitle = match($step) {
                1 => $fl['welcome_subtitle'] ?? 'Privacy-First, Local-First Medical & Healthcare Suite',
                2 => $fl['step2_subtitle'] ?? 'Select your operating region and default clinical languages.',
                3 => $fl['step3_subtitle'] ?? 'Configure practice mode and master account credentials.',
                4 => $fl['step3_desc'] ?? 'Fill in your profile to set up your prescription letterhead.',
                default => $fl['welcome_subtitle'] ?? 'Privacy-First, Local-First Medical & Healthcare Suite'
            };
            ?>
            <h1 id="cardTitle"><?= htmlspecialchars($headerTitle) ?></h1>
            <p id="cardSubtitle"><?= htmlspecialchars($headerSubtitle) ?></p>
        </div>

        <div class="card-body">
            <div class="error-msg" id="errorMsg"></div>

            <?php if ($step === 1): ?>
            <!-- Step 1: Welcome -->
            <div class="step-panel active" id="panel1">
                <p class="fl-desc-lead">
                    <?= htmlspecialchars($fl['lead_desc'] ?? '') ?>
                    <br><br>
                    <?= htmlspecialchars($fl['lead_sub'] ?? '') ?>
                </p>

                <div class="field fl-lang-field">
                    <label for="step1Lang"><?= htmlspecialchars($fl['setup_language_label'] ?? 'Choose your language') ?></label>
                    <div class="fl-select-with-icon">
                        <span class="fl-leading-icon"><?= zrx_icon('globe', 16) ?></span>
                        <select id="step1Lang" onchange="changeSetupLang(this.value)">
                            <?php foreach ($availableLanguages as $code => $meta): 
                                $label = ($meta['native_name'] === $meta['name'])
                                    ? $meta['name']
                                    : $meta['native_name'] . ' (' . $meta['name'] . ')';
                            ?>
                            <option value="<?= htmlspecialchars($code) ?>" <?= $code === $currentLang ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <input type="hidden" id="currentSetupLang" value="<?= htmlspecialchars($currentLang) ?>">
                <div class="btn-row fl-mt-24">
                    <button class="btn btn-primary btn-full" onclick="goStep2()"><?= htmlspecialchars($fl['btn_get_started'] ?? 'Get Started') ?> <?= zrx_icon('arrow-right', 14) ?></button>
                </div>
            </div>

            <?php elseif ($step === 2): ?>
            <!-- Step 2: Practice Region & Languages -->
            <div class="step-panel active" id="panel2">

                <div class="section-label"><?= htmlspecialchars($fl['section_country'] ?? 'Country / Territory of Practice') ?></div>
                <div class="field">
                    <select id="practiceCountry">
                        <option value="" disabled <?= $defaultCountry === '' ? 'selected' : '' ?>>
                            <?= htmlspecialchars($fl['placeholder_select_country'] ?? 'Choose your country or practice territory') ?>
                        </option>
                        <?php foreach ($countryGroups as $groupLabel => $countries): ?>
                        <optgroup label="<?= htmlspecialchars($groupLabel) ?>">
                            <?php foreach ($countries as $cCode => $cName): ?>
                            <option value="<?= htmlspecialchars($cCode) ?>" <?= $cCode === $defaultCountry ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cName) ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Formulary Status Notice -->
                <div class="fl-formulary-box <?= $defaultCountry === '' ? 'hidden' : '' ?>" id="formularyStatusBox">
                    <span class="fl-status-icon" id="formularyStatusIcon">
                        <?= zrx_icon('info-circle', 14) ?>
                    </span>
                    <span class="fl-status-text" id="formularyStatusText">
                        <?php 
                        if ($defaultHasFormulary) {
                            $pack = $availableFormularies[$defaultCountry] ?? [];
                            $bCount = (int)($pack['brands_count'] ?? 0);
                            $mCount = (int)($pack['manufacturers_count'] ?? 0);
                            $tCount = (int)($pack['templates_count'] ?? 0);

                            $formatNum = function(int $num) use ($currentLang): string {
                                $formatted = number_format($num);
                                if ($currentLang === 'bn') {
                                    return str_replace(['0','1','2','3','4','5','6','7','8','9'], ['০','১','২','৩','৪','৫','৬','৭','৮','৯'], $formatted);
                                }
                                return $formatted;
                            };

                            $descTpl = $fl['formulary_available_desc'] ?? 'Dual Mode available for this region ({brands} brands, {manufacturers} manufacturers, {templates} templates).';
                            echo htmlspecialchars(str_replace(
                                ['{brands}', '{manufacturers}', '{templates}'],
                                [$formatNum($bCount), $formatNum($mCount), $formatNum($tCount)],
                                $descTpl
                            ));
                        } else {
                            echo htmlspecialchars($fl['formulary_unavailable_desc'] ?? 'Generic Mode only for this region.');
                        }
                        ?>
                    </span>
                </div>

                <!-- Address Hierarchy Status Notice -->
                <div class="fl-address-box <?= $defaultCountry === '' ? 'hidden' : '' ?> <?= $defaultHasAddressHierarchy ? 'available' : 'unavailable' ?>" id="addressStatusBox">
                    <span class="fl-status-icon" id="addressStatusIcon">
                        <?= $defaultHasAddressHierarchy ? zrx_icon('map-pin', 14) : zrx_icon('info-circle', 14) ?>
                    </span>
                    <span class="fl-status-text" id="addressStatusText">
                        <?php 
                        if ($defaultHasAddressHierarchy) {
                            $aPack = $availableAddressPacks[$defaultCountry] ?? [];
                            $pCount = (int)($aPack['places_count'] ?? 0);
                            $formatNum = function(int $num) use ($currentLang): string {
                                $formatted = number_format($num);
                                if ($currentLang === 'bn') {
                                    return str_replace(['0','1','2','3','4','5','6','7','8','9'], ['০','১','২','৩','৪','৫','৬','৭','৮','৯'], $formatted);
                                }
                                return $formatted;
                            };
                            $descTpl = $fl['address_available_desc'] ?? 'Address hierarchy available for this region ({places} administrative places & postal codes). Auto-complete dropdown suggestions enabled.';
                            echo htmlspecialchars(str_replace('{places}', $formatNum($pCount), $descTpl));
                        } else {
                            echo htmlspecialchars($fl['address_unavailable_desc'] ?? 'Address hierarchy is not yet bundled for this region. Patient addresses will be entered as free text. (Open-source contributors can contribute region places via Geo Studio in tools/geo-studio).');
                        }
                        ?>
                    </span>
                </div>

                <!-- Prescribing Mode Selection -->
                <div class="field fl-prescribing-field <?= $defaultCountry === '' ? 'hidden' : '' ?>" id="prescribingModeSection">
                    <label class="fl-field-label"><?= htmlspecialchars($fl['prescribing_mode_label'] ?? 'Prescribing Mode') ?></label>
                    <div class="choice-group">
                        <div class="choice-btn <?= $defaultPrescribingMode === 'brand' ? 'selected' : '' ?> <?= !$defaultHasFormulary ? 'disabled' : '' ?>" 
                             id="choiceModeBrand" 
                             data-mode="brand" 
                             onclick="selectPrescribingMode('brand')">
                            <div class="choice-icon">
                                <?= zrx_icon('pill', 22) ?>
                                <span class="choice-lock-badge <?= !$defaultHasFormulary ? '' : 'hidden' ?>" id="brandLockBadge" title="<?= htmlspecialchars($fl['mode_locked_notice'] ?? 'Brand formulary not available for this country') ?>">
                                    <?= zrx_icon('lock', 10) ?>
                                </span>
                            </div>
                            <div class="choice-label"><?= htmlspecialchars($fl['mode_brand_title'] ?? 'Dual Mode') ?></div>
                            <div class="choice-desc"><?= htmlspecialchars($fl['mode_brand_desc'] ?? 'Generic Name + Trade Name') ?></div>
                        </div>

                        <div class="choice-btn <?= $defaultPrescribingMode === 'generic' ? 'selected' : '' ?>" 
                             id="choiceModeGeneric" 
                             data-mode="generic" 
                             onclick="selectPrescribingMode('generic')">
                            <div class="choice-icon">
                                <?= zrx_icon('globe', 22) ?>
                            </div>
                            <div class="choice-label"><?= htmlspecialchars($fl['mode_generic_title'] ?? 'Generic Mode') ?></div>
                            <div class="choice-desc"><?= htmlspecialchars($fl['mode_generic_desc'] ?? 'International nonproprietary names') ?></div>
                        </div>
                    </div>
                    <input type="hidden" id="prescribingMode" value="<?= htmlspecialchars($defaultPrescribingMode) ?>">
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="clinicalLang"><?= htmlspecialchars($fl['section_clinical_lang'] ?? 'Patient Prescription Language') ?></label>
                        <select id="clinicalLang">
                            <?php 
                            foreach ($availableLanguages as $code => $meta): 
                                $clinicalLabel = ($meta['native_name'] === $meta['name'])
                                    ? $meta['name']
                                    : $meta['native_name'] . ' (' . $meta['name'] . ')';
                            ?>
                            <option value="<?= htmlspecialchars($code) ?>" <?= $code === $defaultClinicalLang ? 'selected' : '' ?>>
                                <?= htmlspecialchars($clinicalLabel) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($fl['clinical_lang_hint'])): ?>
                        <small class="fl-field-hint"><?= htmlspecialchars($fl['clinical_lang_hint']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="helpLang"><?= htmlspecialchars($fl['section_help_lang'] ?? 'In-App Guide Language') ?></label>
                        <select id="helpLang">
                            <?php 
                            foreach ($availableLanguages as $code => $meta): 
                                $helpLabel = ($meta['native_name'] === $meta['name'])
                                    ? $meta['name']
                                    : $meta['native_name'] . ' (' . $meta['name'] . ')';
                            ?>
                            <option value="<?= htmlspecialchars($code) ?>" <?= $code === $defaultHelpLang ? 'selected' : '' ?>>
                                <?= htmlspecialchars($helpLabel) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($fl['help_lang_hint'])): ?>
                        <small class="fl-field-hint"><?= htmlspecialchars($fl['help_lang_hint']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="btn-row fl-mt-24">
                    <button class="btn btn-ghost" onclick="window.location='first_launch.php?step=1&lang=<?= urlencode($currentLang) ?>'"><?= zrx_icon('arrow-left', 14) ?> <?= htmlspecialchars($fl['btn_back'] ?? 'Back') ?></button>
                    <button class="btn btn-primary" onclick="goStep3()"><?= htmlspecialchars($fl['btn_continue'] ?? 'Continue') ?> <?= zrx_icon('arrow-right', 14) ?></button>
                </div>

            </div>

            <?php elseif ($step === 3): ?>
            <!-- Step 3: Deployment & Security -->
            <div class="step-panel active" id="panel3">

                <input type="hidden" id="installType" value="local">
                <input type="hidden" id="practiceCountry" value="<?= htmlspecialchars($defaultCountry) ?>">
                <input type="hidden" id="prescribingMode" value="<?= htmlspecialchars($defaultPrescribingMode) ?>">
                <input type="hidden" id="clinicalLang" value="<?= htmlspecialchars($defaultClinicalLang) ?>">
                <input type="hidden" id="helpLang" value="<?= htmlspecialchars($defaultHelpLang) ?>">

                <div class="section-label"><?= htmlspecialchars($fl['section_setting'] ?? 'Deployment & Clinical Setting') ?></div>
                <div class="choice-group" id="practiceChoice">
                    <div class="choice-btn selected" data-val="solo" onclick="selectChoice('practice', this)">
                        <div class="choice-icon"><?= zrx_icon('user', 24) ?></div>
                        <div class="choice-label"><?= htmlspecialchars($fl['setting_solo'] ?? 'Solo Doctor') ?></div>
                        <div class="choice-desc"><?= htmlspecialchars($fl['setting_solo_desc'] ?? 'Single physician') ?></div>
                    </div>
                    <div class="choice-btn" data-val="multi" onclick="selectChoice('practice', this)">
                        <div class="choice-icon"><?= zrx_icon('hospital', 24) ?></div>
                        <div class="choice-label"><?= htmlspecialchars($fl['setting_hospital'] ?? 'Hospital / Clinic') ?></div>
                        <div class="choice-desc"><?= htmlspecialchars($fl['setting_hospital_desc'] ?? 'Multi-doctor facility') ?></div>
                    </div>
                </div>
                <input type="hidden" id="practiceType" value="solo">

                <?php if (!$isLoopback): ?>
                <div class="section-label fl-warn-icon"><?= htmlspecialchars($fl['section_token'] ?? 'Server Setup Token') ?></div>
                <div class="field recovery-box fl-mb-20">
                    <label><?= htmlspecialchars($fl['token_label'] ?? 'Setup Token') ?> <span class="fl-label-opt"><?= htmlspecialchars($fl['token_opt'] ?? '(from userdata/setup_token.txt)') ?></span></label>
                    <input type="text" id="setupToken" value="<?= htmlspecialchars($queryToken, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars($fl['token_placeholder'] ?? 'Paste setup token generated on server host') ?>" autocomplete="off" class="fl-mono">
                    <small class="fl-field-hint"><?= sprintf(htmlspecialchars($fl['token_hint'] ?? 'Because this setup wizard is accessed over the network, please enter the security token found in %s on the server to claim administration.'), '<code>userdata/setup_token.txt</code>') ?></small>
                </div>
                <?php endif; ?>

                <div class="section-label"><?= htmlspecialchars($fl['section_security'] ?? 'Account Credentials & Security') ?></div>
                <div class="field">
                    <label id="usernameLabel"
                           data-label-doctor="<?= htmlspecialchars($fl['doctor_username_label'] ?? 'Doctor Username') ?>"
                           data-label-admin="<?= htmlspecialchars($fl['admin_username_label'] ?? 'Admin Username') ?>"
                           data-placeholder-doctor="<?= htmlspecialchars($fl['doctor_username_placeholder'] ?? 'doctor') ?>"
                           data-placeholder-admin="<?= htmlspecialchars($fl['admin_username_placeholder'] ?? 'admin') ?>">
                        <?= htmlspecialchars($fl['doctor_username_label'] ?? 'Doctor Username') ?>
                    </label>
                    <input type="text" id="accountUsername" value="<?= htmlspecialchars($defaultUsername) ?>" placeholder="<?= htmlspecialchars($fl['doctor_username_placeholder'] ?? 'doctor') ?>" autocomplete="off" spellcheck="false">
                </div>
                <div class="field-row">
                    <div class="field">
                        <label><?= htmlspecialchars($fl['password_label'] ?? 'Password') ?> <span class="fl-label-opt"><?= htmlspecialchars($fl['password_opt'] ?? '(min 8 chars)') ?></span></label>
                        <input type="password" id="password" autocomplete="new-password" placeholder="<?= $existingAccountUsername !== '' ? '••••••••' : htmlspecialchars($fl['password_placeholder'] ?? 'At least 8 characters') ?>">
                    </div>
                    <div class="field">
                        <label><?= htmlspecialchars($fl['confirm_password_label'] ?? 'Confirm Password') ?></label>
                        <input type="password" id="confirmPassword" autocomplete="new-password" placeholder="<?= $existingAccountUsername !== '' ? '••••••••' : htmlspecialchars($fl['confirm_password_placeholder'] ?? '••••••••') ?>">
                    </div>
                </div>

                <div class="field">
                    <label><?= htmlspecialchars($fl['doctor_email_label'] ?? $fl['recovery_email_label'] ?? 'Doctor Email') ?> <span class="fl-label-opt"><?= htmlspecialchars($fl['doctor_email_opt'] ?? '(optional - for letterhead & records)') ?></span></label>
                    <input type="email" id="recoveryEmail" value="<?= htmlspecialchars($defaultRecoveryEmail) ?>" placeholder="<?= htmlspecialchars($fl['doctor_email_placeholder'] ?? 'doctor@example.com') ?>">
                </div>

                <div class="btn-row fl-mt-24">
                    <button class="btn btn-ghost" onclick="goStep2FromStep3()"><?= zrx_icon('arrow-left', 14) ?> <?= htmlspecialchars($fl['btn_back'] ?? 'Back') ?></button>
                    <button class="btn btn-primary" id="btnSaveSetup" onclick="saveSetup()"><?= htmlspecialchars($fl['btn_continue'] ?? 'Continue') ?> <?= zrx_icon('arrow-right', 14) ?></button>
                </div>

            </div>

            <?php elseif ($step === 4): ?>
            <!-- Step 4: Doctor Onboarding -->
            <div class="step-panel active" id="panel4">
                <input type="hidden" id="csrf_token" name="csrf_token" value="<?= htmlspecialchars(zimrx_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="section-label"><?= htmlspecialchars($fl['section_name'] ?? 'Name') ?></div>
                <div class="bilingual-grid">
                    <div class="field">
                        <label><span class="lang-tag bn"><?= htmlspecialchars($fl['badge_native'] ?? 'BN') ?></span><?= htmlspecialchars($fl['label_name_native'] ?? 'নাম') ?></label>
                        <input type="text" id="name_native" placeholder="<?= htmlspecialchars($fl['placeholder_name_native'] ?? 'ডাঃ আহমেদ') ?>">
                    </div>
                    <div class="field">
                        <label><span class="lang-tag en"><?= htmlspecialchars($fl['badge_en'] ?? 'EN') ?></span><?= htmlspecialchars($fl['label_name_en'] ?? 'Name') ?></label>
                        <input type="text" id="name_en" placeholder="<?= htmlspecialchars($fl['placeholder_name_en'] ?? 'Dr. Ahmed') ?>">
                    </div>
                </div>

                <div class="section-label"><?= htmlspecialchars($fl['section_qualifications'] ?? 'Qualifications') ?></div>
                <div class="bilingual-grid">
                    <div class="field">
                        <label><span class="lang-tag bn"><?= htmlspecialchars($fl['badge_native'] ?? 'BN') ?></span><?= htmlspecialchars($fl['label_qualifications_native'] ?? 'যোগ্যতা') ?></label>
                        <input type="text" id="qualifications_native" placeholder="<?= htmlspecialchars($fl['placeholder_qualifications_native'] ?? 'এমবিবিএস, এমডি') ?>">
                    </div>
                    <div class="field">
                        <label><span class="lang-tag en"><?= htmlspecialchars($fl['badge_en'] ?? 'EN') ?></span><?= htmlspecialchars($fl['label_qualifications_en'] ?? 'Qualifications') ?></label>
                        <input type="text" id="qualifications_en" placeholder="<?= htmlspecialchars($fl['placeholder_qualifications_en'] ?? 'MBBS, MD') ?>">
                    </div>
                </div>

                <div class="section-label"><?= htmlspecialchars($fl['section_designation'] ?? 'Designation & Institute') ?></div>
                <div class="bilingual-grid">
                    <div class="field">
                        <label><span class="lang-tag bn"><?= htmlspecialchars($fl['badge_native'] ?? 'BN') ?></span><?= htmlspecialchars($fl['label_designation_native'] ?? 'পদবি') ?></label>
                        <input type="text" id="designation_native" placeholder="<?= htmlspecialchars($fl['placeholder_designation_native'] ?? 'সহকারী অধ্যাপক') ?>">
                    </div>
                    <div class="field">
                        <label><span class="lang-tag en"><?= htmlspecialchars($fl['badge_en'] ?? 'EN') ?></span><?= htmlspecialchars($fl['label_designation_en'] ?? 'Designation') ?></label>
                        <input type="text" id="designation_en" placeholder="<?= htmlspecialchars($fl['placeholder_designation_en'] ?? 'Asst. Professor') ?>">
                    </div>
                </div>
                <div class="bilingual-grid">
                    <div class="field">
                        <label><span class="lang-tag bn"><?= htmlspecialchars($fl['badge_native'] ?? 'BN') ?></span><?= htmlspecialchars($fl['label_institute_native'] ?? 'প্রতিষ্ঠান / মিশন') ?></label>
                        <input type="text" id="institute_native" placeholder="<?= htmlspecialchars($fl['placeholder_institute_native'] ?? 'ঢামেক / এমএসএফ') ?>">
                    </div>
                    <div class="field">
                        <label><span class="lang-tag en"><?= htmlspecialchars($fl['badge_en'] ?? 'EN') ?></span><?= htmlspecialchars($fl['label_institute_en'] ?? 'Institute / Mission') ?></label>
                        <input type="text" id="institute_en" placeholder="<?= htmlspecialchars($fl['placeholder_institute_en'] ?? 'DMCH / MSF Field Hospital') ?>">
                    </div>
                </div>

                <div class="section-label"><?= htmlspecialchars($fl['section_specialty'] ?? 'Specialty & Regulatory ID') ?></div>
                <div class="bilingual-grid">
                    <div class="field">
                        <label><span class="lang-tag bn"><?= htmlspecialchars($fl['badge_native'] ?? 'BN') ?></span><?= htmlspecialchars($fl['label_specialty_native'] ?? 'বিশেষত্ব') ?></label>
                        <input type="text" id="speciality_native" placeholder="<?= htmlspecialchars($fl['placeholder_specialty_native'] ?? 'হৃদরোগ বিশেষজ্ঞ') ?>">
                    </div>
                    <div class="field">
                        <label><span class="lang-tag en"><?= htmlspecialchars($fl['badge_en'] ?? 'EN') ?></span><?= htmlspecialchars($fl['label_specialty_en'] ?? 'Specialty') ?></label>
                        <input type="text" id="speciality_en" placeholder="<?= htmlspecialchars($fl['placeholder_specialty_en'] ?? 'Cardiologist') ?>">
                    </div>
                </div>
                <div class="bilingual-grid">
                    <div class="field">
                        <label><span class="lang-tag bn"><?= htmlspecialchars($fl['badge_native'] ?? 'BN') ?></span><?= htmlspecialchars($fl['label_license_native'] ?? 'নিবন্ধন নং') ?></label>
                        <input type="text" id="license_native" placeholder="<?= htmlspecialchars($fl['placeholder_license_native'] ?? 'এ-১২৩৪৫') ?>">
                    </div>
                    <div class="field">
                        <label><span class="lang-tag en"><?= htmlspecialchars($fl['badge_en'] ?? 'EN') ?></span><?= htmlspecialchars($fl['label_license_en'] ?? 'License / Reg. No.') ?></label>
                        <input type="text" id="license_en" placeholder="<?= htmlspecialchars($fl['placeholder_license_en'] ?? 'A-12345 / BIG / ID') ?>">
                    </div>
                </div>

                <div class="section-label"><?= htmlspecialchars($fl['section_phone'] ?? 'Phone') ?></div>
                <div class="bilingual-grid">
                    <div class="field">
                        <label><span class="lang-tag bn"><?= htmlspecialchars($fl['badge_native'] ?? 'BN') ?></span><?= htmlspecialchars($fl['label_phone_native'] ?? 'ফোন') ?></label>
                        <input type="text" id="phone_native" placeholder="<?= htmlspecialchars($fl['placeholder_phone_native'] ?? '০১৭১২-XXXXXX') ?>">
                    </div>
                    <div class="field">
                        <label><span class="lang-tag en"><?= htmlspecialchars($fl['badge_en'] ?? 'EN') ?></span><?= htmlspecialchars($fl['label_phone_en'] ?? 'Phone') ?></label>
                        <input type="text" id="phone_en" placeholder="<?= htmlspecialchars($fl['placeholder_phone_en'] ?? '01712-XXXXXX') ?>">
                    </div>
                </div>

                <div class="btn-row fl-mt-24">
                    <button type="button" class="btn btn-ghost" onclick="goStep3FromStep4()"><?= zrx_icon('arrow-left', 14) ?> <?= htmlspecialchars($fl['btn_back'] ?? 'Back') ?></button>
                    <button type="button" class="btn btn-primary" id="btnSaveDoctor" onclick="saveDoctor()"><?= htmlspecialchars($fl['btn_save_doctor'] ?? 'Save & Enter ZimRx') ?> <?= zrx_icon('arrow-right', 14) ?></button>
                </div>
                <div class="step3-skip step4-skip">
                    <a href="#" onclick="skipDoctor(); return false;"><?= htmlspecialchars($fl['btn_skip'] ?? "Skip - I'll fill this in later") ?></a>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- card-body -->
    </div><!-- wizard-card -->

</div><!-- wizard-wrap -->

<script>
    window.ZimRxHasExistingAccount = <?= $existingAccountUsername !== '' ? 'true' : 'false' ?>;
    window.ZimRxCurrentLang = <?= json_encode($currentLang) ?>;
    window.ZimRxIconsMap = <?= json_encode(ZimRxIcon::getAll(), JSON_UNESCAPED_SLASHES) ?>;
    window.ZimRxCountryClinicalMap = <?= json_encode(CountryService::getCountryClinicalLanguages(), JSON_UNESCAPED_SLASHES) ?>;
    window.ZimRxAvailableFormularies = <?= json_encode(CountryService::getAvailableFormularies(), JSON_UNESCAPED_UNICODE) ?>;
    window.ZimRxAvailableAddressPacks = <?= json_encode(CountryService::getAvailableAddressPacks(), JSON_UNESCAPED_UNICODE) ?>;
    window.ZimRxFirstLaunchI18n = <?= json_encode([
        'formulary_available_desc' => $fl['formulary_available_desc'] ?? 'Dual Mode available for this region ({brands} brands, {manufacturers} manufacturers, {templates} templates).',
        'formulary_unavailable_desc' => $fl['formulary_unavailable_desc'] ?? 'Generic Mode only for this region.',
        'address_available_desc' => $fl['address_available_desc'] ?? 'Address hierarchy is available for this region ({places} administrative places & postal codes). Auto-complete dropdown suggestions enabled.',
        'address_unavailable_desc' => $fl['address_unavailable_desc'] ?? 'Address hierarchy is not yet bundled for this region. Patient addresses will be entered as free text. (Open-source contributors can contribute region places via Geo Studio in tools/geo-studio).',
        'mode_locked_notice' => $fl['mode_locked_notice'] ?? 'Brand formulary not available for this country',
        'error_select_country' => $fl['error_select_country'] ?? 'Please choose your country or practice territory.',
    ], JSON_UNESCAPED_UNICODE) ?>;
</script>

<script src="assets/js/layout/zrx_icons.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/zrx_icons.js') ?>"></script>
<script src="assets/js/layout/zrx_dropdown.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/zrx_dropdown.js') ?>"></script>
<script src="assets/js/pages/first_launch.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/first_launch.js') ?>"></script>
</body>
</html>
