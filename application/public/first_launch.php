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
$t = static fn (string $key): string => htmlspecialchars((string)($fl[$key] ?? ''), ENT_QUOTES, 'UTF-8');
$countryGroups = CountryService::getGrouped();
$availableFormularies = CountryService::getAvailableFormularies();
$availableAddressPacks = CountryService::getAvailableAddressPacks();
$defaultCountry = trim((string)($_GET['country'] ?? ''));
if ($defaultCountry === '') {
    $defaultCountry = fl_config_get($pdo, 'practice_country');
}
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
    $defaultHelpLang = ($defaultCountry !== '')
        ? (CountryService::getDefaultHelpLanguage($defaultCountry) ?? $defaultClinicalLang)
        : $defaultClinicalLang;
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
    <title><?= $t('page_title') ?></title>
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
                <select id="flLangSelect" class="zrx-select" aria-label="<?= $t('setup_language_label') ?>" onchange="changeSetupLang(this.value)">
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
                1 => $fl['welcome_title'] ?? '',
                2 => $fl['step2_title'] ?? '',
                3 => $fl['step3_title'] ?? '',
                4 => $fl['step4_title'] ?? '',
                default => $fl['welcome_title'] ?? ''
            };
            $headerSubtitle = match($step) {
                1 => $fl['welcome_subtitle'] ?? '',
                2 => $fl['step2_subtitle'] ?? '',
                3 => $fl['step3_subtitle'] ?? '',
                4 => $fl['step3_desc'] ?? '',
                default => $fl['welcome_subtitle'] ?? ''
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
                    <?= $t('lead_desc') ?>
                    <span class="fl-sub-lead"><?= $t('lead_sub') ?></span>
                </p>

                <div class="field fl-lang-field">
                    <label for="step1Lang"><?= $t('setup_language_label') ?></label>
                    <div class="fl-select-with-icon">
                        <span class="fl-leading-icon"><?= zrx_icon('globe', 16) ?></span>
                        <select id="step1Lang" class="zrx-select" onchange="changeSetupLang(this.value)">
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

                <!-- Terms of Use -->
                <div class="fl-terms-section">
                    <label class="fl-terms-label"><?= $t('terms_title') ?></label>
                    <div class="fl-terms-box-wrap">
                        <div class="fl-terms-box" id="flTermsBody" tabindex="0">
                            <p class="fl-terms-p"><strong><?= $t('terms_intro') ?></strong></p>
                            
                            <p class="fl-terms-p"><strong>1. <?= $t('terms_item_practitioners_title') ?></strong> <?= $t('terms_item_practitioners_desc') ?></p>
                            
                            <p class="fl-terms-p"><strong>2. <?= $t('terms_item_judgment_title') ?></strong> <?= $t('terms_item_judgment_desc') ?></p>
                            
                            <p class="fl-terms-p"><strong>3. <?= $t('terms_item_safety_title') ?></strong> <?= $t('terms_item_safety_desc') ?></p>
                            
                            <p class="fl-terms-p"><strong>4. <?= $t('terms_item_custody_title') ?></strong> <?= $t('terms_item_custody_desc') ?></p>
                            
                            <p class="fl-terms-p"><strong>5. <?= $t('terms_item_warranty_title') ?></strong> <?= $t('terms_item_warranty_desc') ?></p>
                        </div>
                    </div>

                    <div class="fl-terms-action">
                        <label class="fl-terms-check" id="flTermsCheckLabel" for="termsAccepted">
                            <input type="checkbox" id="termsAccepted" disabled onchange="onTermsCheckboxChange(this.checked)">
                            <span><?= $t('terms_checkbox_label') ?></span>
                        </label>
                    </div>
                </div>

                <input type="hidden" id="currentSetupLang" value="<?= htmlspecialchars($currentLang) ?>">
                <div class="btn-row fl-mt-24">
                    <button class="btn btn-primary btn-full" id="btnGetStarted" disabled onclick="goStep2()"><?= $t('btn_get_started') ?> <?= zrx_icon('arrow-right', 14) ?></button>
                </div>
            </div>

            <?php elseif ($step === 2): ?>
            <!-- Step 2: Practice Region & Languages -->
            <div class="step-panel active" id="panel2">

                <div class="section-label"><?= $t('section_country') ?></div>
                <div class="field">
                    <select id="practiceCountry">
                        <option value="" disabled <?= $defaultCountry === '' ? 'selected' : '' ?>>
                            <?= $t('placeholder_select_country') ?>
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

                            $descTpl = $fl['formulary_available_desc'] ?? '';
                            echo htmlspecialchars(str_replace(
                                ['{brands}', '{manufacturers}', '{templates}'],
                                [$formatNum($bCount), $formatNum($mCount), $formatNum($tCount)],
                                $descTpl
                            ));
                        } else {
                            echo $t('formulary_unavailable_desc');
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
                            $descTpl = $fl['address_available_desc'] ?? '';
                            echo htmlspecialchars(str_replace('{places}', $formatNum($pCount), $descTpl));
                        } else {
                            echo $t('address_unavailable_desc');
                        }
                        ?>
                    </span>
                </div>

                <!-- Prescribing Mode Selection -->
                <div class="field fl-prescribing-field <?= $defaultCountry === '' ? 'hidden' : '' ?>" id="prescribingModeSection">
                    <label class="fl-field-label"><?= $t('prescribing_mode_label') ?></label>
                    <div class="choice-group">
                        <div class="choice-btn <?= $defaultPrescribingMode === 'brand' ? 'selected' : '' ?> <?= !$defaultHasFormulary ? 'disabled' : '' ?>" 
                             id="choiceModeBrand" 
                             data-mode="brand" 
                             onclick="selectPrescribingMode('brand')">
                            <div class="choice-icon">
                                <?= zrx_icon('pill', 22) ?>
                                <span class="choice-lock-badge <?= !$defaultHasFormulary ? '' : 'hidden' ?>" id="brandLockBadge" title="<?= $t('mode_locked_notice') ?>">
                                    <?= zrx_icon('lock', 10) ?>
                                </span>
                            </div>
                            <div class="choice-label"><?= $t('mode_brand_title') ?></div>
                            <div class="choice-desc"><?= $t('mode_brand_desc') ?></div>
                        </div>

                        <div class="choice-btn <?= $defaultPrescribingMode === 'generic' ? 'selected' : '' ?>" 
                             id="choiceModeGeneric" 
                             data-mode="generic" 
                             onclick="selectPrescribingMode('generic')">
                            <div class="choice-icon">
                                <?= zrx_icon('globe', 22) ?>
                            </div>
                            <div class="choice-label"><?= $t('mode_generic_title') ?></div>
                            <div class="choice-desc"><?= $t('mode_generic_desc') ?></div>
                        </div>
                    </div>
                    <input type="hidden" id="prescribingMode" value="<?= htmlspecialchars($defaultPrescribingMode) ?>">
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="clinicalLang"><?= $t('section_clinical_lang') ?></label>
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
                        <small class="fl-field-hint"><?= $t('clinical_lang_hint') ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="helpLang"><?= $t('section_help_lang') ?></label>
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
                        <small class="fl-field-hint"><?= $t('help_lang_hint') ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="btn-row fl-mt-24">
                    <button class="btn btn-ghost" onclick="window.location='first_launch.php?step=1&lang=<?= urlencode($currentLang) ?>'"><?= zrx_icon('arrow-left', 14) ?> <?= $t('btn_back') ?></button>
                    <button class="btn btn-primary" onclick="goStep3()"><?= $t('btn_continue') ?> <?= zrx_icon('arrow-right', 14) ?></button>
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

                <div class="section-label"><?= $t('section_setting') ?></div>
                <div class="choice-group" id="practiceChoice">
                    <div class="choice-btn selected" data-val="solo" onclick="selectChoice('practice', this)">
                        <div class="choice-icon"><?= zrx_icon('user', 24) ?></div>
                        <div class="choice-label"><?= $t('setting_solo') ?></div>
                        <div class="choice-desc"><?= $t('setting_solo_desc') ?></div>
                    </div>
                    <div class="choice-btn" data-val="multi" onclick="selectChoice('practice', this)">
                        <div class="choice-icon"><?= zrx_icon('hospital', 24) ?></div>
                        <div class="choice-label"><?= $t('setting_hospital') ?></div>
                        <div class="choice-desc"><?= $t('setting_hospital_desc') ?></div>
                    </div>
                </div>
                <input type="hidden" id="practiceType" value="solo">

                <?php if (!$isLoopback): ?>
                <div class="section-label fl-warn-icon"><?= $t('section_token') ?></div>
                <div class="field recovery-box fl-mb-20">
                    <label><?= $t('token_label') ?> <span class="fl-label-opt"><?= $t('token_opt') ?></span></label>
                    <input type="text" id="setupToken" value="<?= htmlspecialchars($queryToken, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= $t('token_placeholder') ?>" autocomplete="off" class="fl-mono">
                    <small class="fl-field-hint"><?= sprintf($t('token_hint'), '<code>userdata/setup_token.txt</code>') ?></small>
                </div>
                <?php endif; ?>

                <div class="section-label"><?= $t('section_security') ?></div>
                <div class="field">
                    <label id="usernameLabel"
                           data-label-doctor="<?= $t('doctor_username_label') ?>"
                           data-label-admin="<?= $t('admin_username_label') ?>"
                           data-placeholder-doctor="<?= $t('doctor_username_placeholder') ?>"
                           data-placeholder-admin="<?= $t('admin_username_placeholder') ?>">
                        <?= $t('doctor_username_label') ?>
                    </label>
                    <input type="text" id="accountUsername" value="<?= htmlspecialchars($defaultUsername) ?>" placeholder="<?= $t('doctor_username_placeholder') ?>" autocomplete="off" spellcheck="false">
                </div>
                <div class="field-row">
                    <div class="field">
                        <label><?= $t('password_label') ?> <span class="fl-label-opt"><?= $t('password_opt') ?></span> <span class="fl-star">*</span></label>
                        <input type="password" id="password" autocomplete="new-password" placeholder="<?= $t('password_placeholder') ?>">
                    </div>
                    <div class="field">
                        <label><?= $t('confirm_password_label') ?> <span class="fl-star">*</span></label>
                        <input type="password" id="confirmPassword" autocomplete="new-password" placeholder="<?= $t('confirm_password_placeholder') ?>">
                    </div>
                </div>

                <div class="field">
                    <label><?= $t('doctor_email_label') ?> <span class="fl-label-opt"><?= $t('doctor_email_opt') ?></span></label>
                    <input type="email" id="recoveryEmail" value="<?= htmlspecialchars($defaultRecoveryEmail) ?>" placeholder="<?= $t('doctor_email_placeholder') ?>">
                </div>

                <div class="btn-row fl-mt-24">
                    <button class="btn btn-ghost" onclick="goStep2FromStep3()"><?= zrx_icon('arrow-left', 14) ?> <?= $t('btn_back') ?></button>
                    <button class="btn btn-primary" id="btnSaveSetup" onclick="saveSetup()"><?= $t('btn_continue') ?> <?= zrx_icon('arrow-right', 14) ?></button>
                </div>

            </div>

            <?php elseif ($step === 4): ?>
            <!-- Step 4: Doctor Onboarding -->
            <?php
            $isBdCountry = ($defaultCountry === 'BD');
            $headerSides = CountryService::getDefaultHeaderSides($defaultCountry);
            $initialLeftType = $headerSides['left'];
            $initialRightType = $headerSides['right'];

            $headerSchemas = [
                'doctor_native' => [
                    'title' => $isBdCountry ? ($fl['type_doctor_native_bd'] ?? '') : ($fl['type_doctor_native'] ?? ''),
                    'labels' => $isBdCountry ? [
                        $fl['field_doc_name_bn'] ?? '',
                        $fl['field_qual_bn'] ?? '',
                        $fl['field_desig_bn'] ?? '',
                        $fl['field_inst_bn'] ?? '',
                        $fl['field_spec_bn'] ?? '',
                        $fl['field_lic_bn'] ?? '',
                        $fl['field_phone_bn'] ?? '',
                    ] : [
                        $fl['field_doc_name_native'] ?? '',
                        $fl['field_qual_native'] ?? '',
                        $fl['field_desig_native'] ?? '',
                        $fl['field_inst_native'] ?? '',
                        $fl['field_spec_native'] ?? '',
                        $fl['field_lic_native'] ?? '',
                        $fl['field_phone_native'] ?? '',
                    ],
                    'placeholders' => $isBdCountry ? [
                        $fl['placeholder_doc_name_bn'] ?? '',
                        $fl['placeholder_qual_bn'] ?? '',
                        $fl['placeholder_desig_bn'] ?? '',
                        $fl['placeholder_inst_bn'] ?? '',
                        $fl['placeholder_spec_bn'] ?? '',
                        $fl['placeholder_lic_bn'] ?? '',
                        $fl['placeholder_phone_bn'] ?? '',
                    ] : [
                        $fl['placeholder_doc_name_native'] ?? '',
                        $fl['placeholder_qual_native'] ?? '',
                        $fl['placeholder_desig_native'] ?? '',
                        $fl['placeholder_inst_native'] ?? '',
                        $fl['placeholder_spec_native'] ?? '',
                        $fl['placeholder_lic_native'] ?? '',
                        $fl['placeholder_phone_native'] ?? '',
                    ],
                    'required' => [true, true, false, false, false, false, false]
                ],
                'doctor_en' => [
                    'title' => $fl['type_doctor_en'] ?? '',
                    'labels' => [
                        $fl['field_doc_name_en'] ?? '',
                        $fl['field_qual_en'] ?? '',
                        $fl['field_desig_en'] ?? '',
                        $fl['field_inst_en'] ?? '',
                        $fl['field_spec_en'] ?? '',
                        $fl['field_lic_en'] ?? '',
                        $fl['field_phone_en'] ?? '',
                    ],
                    'placeholders' => [
                        $fl['placeholder_doc_name_en'] ?? '',
                        $fl['placeholder_qual_en'] ?? '',
                        $fl['placeholder_desig_en'] ?? '',
                        $fl['placeholder_inst_en'] ?? '',
                        $fl['placeholder_spec_en'] ?? '',
                        $fl['placeholder_lic_en'] ?? '',
                        $fl['placeholder_phone_en'] ?? '',
                    ],
                    'required' => [true, true, false, false, false, false, false]
                ],
                'clinic' => [
                    'title' => $fl['type_clinic'] ?? '',
                    'labels' => [
                        $fl['field_clinic_name'] ?? '',
                        $fl['field_clinic_suite'] ?? '',
                        $fl['field_clinic_address'] ?? '',
                        $fl['field_clinic_hours'] ?? '',
                        $fl['field_clinic_policy'] ?? '',
                        $fl['field_clinic_helpline'] ?? '',
                        $fl['field_clinic_portal'] ?? '',
                    ],
                    'placeholders' => [
                        $fl['placeholder_clinic_name'] ?? '',
                        $fl['placeholder_clinic_suite'] ?? '',
                        $fl['placeholder_clinic_address'] ?? '',
                        $fl['placeholder_clinic_hours'] ?? '',
                        $fl['placeholder_clinic_policy'] ?? '',
                        $fl['placeholder_clinic_helpline'] ?? '',
                        $fl['placeholder_clinic_portal'] ?? '',
                    ],
                    'required' => [true, false, false, false, false, false, false]
                ]
            ];

            $leftSchema = $headerSchemas[$initialLeftType];
            $rightSchema = $headerSchemas[$initialRightType];
            $fieldPairs = [
                ['left_id' => 'name_native', 'right_id' => 'name_en'],
                ['left_id' => 'qualifications_native', 'right_id' => 'qualifications_en'],
                ['left_id' => 'designation_native', 'right_id' => 'designation_en'],
                ['left_id' => 'institute_native', 'right_id' => 'institute_en'],
                ['left_id' => 'speciality_native', 'right_id' => 'speciality_en'],
                ['left_id' => 'license_native', 'right_id' => 'license_en'],
                ['left_id' => 'phone_native', 'right_id' => 'phone_en'],
            ];
            ?>
            <div class="step-panel active" id="panel4">
                <input type="hidden" id="csrf_token" name="csrf_token" value="<?= htmlspecialchars(zimrx_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="bilingual-header-controls">
                    <div class="header-col-control">
                        <label class="col-control-label"><?= $t('header_left_side_label') ?></label>
                        <select id="leftSideType" class="zrx-select" onchange="onHeaderTypeChange('left', this.value)">
                            <option value="doctor_native" <?= ($initialLeftType === 'doctor_native') ? 'selected' : '' ?>><?= htmlspecialchars($headerSchemas['doctor_native']['title']) ?></option>
                            <option value="doctor_en" <?= ($initialLeftType === 'doctor_en') ? 'selected' : '' ?>><?= htmlspecialchars($headerSchemas['doctor_en']['title']) ?></option>
                            <option value="clinic" <?= ($initialLeftType === 'clinic') ? 'selected' : '' ?>><?= htmlspecialchars($headerSchemas['clinic']['title']) ?></option>
                        </select>
                    </div>
                    <div class="header-col-control">
                        <label class="col-control-label"><?= $t('header_right_side_label') ?></label>
                        <select id="rightSideType" class="zrx-select" onchange="onHeaderTypeChange('right', this.value)">
                            <option value="doctor_en" <?= ($initialRightType === 'doctor_en') ? 'selected' : '' ?>><?= htmlspecialchars($headerSchemas['doctor_en']['title']) ?></option>
                            <option value="clinic" <?= ($initialRightType === 'clinic') ? 'selected' : '' ?>><?= htmlspecialchars($headerSchemas['clinic']['title']) ?></option>
                            <option value="doctor_native" <?= ($initialRightType === 'doctor_native') ? 'selected' : '' ?>><?= htmlspecialchars($headerSchemas['doctor_native']['title']) ?></option>
                        </select>
                    </div>
                </div>

                <div class="header-settings-note">
                    <span class="note-icon"><?= zrx_icon('info-circle', 14) ?></span>
                    <span><?= $t('header_edit_note') ?></span>
                </div>

                <?php foreach ($fieldPairs as $idx => $fp): 
                    $isLeftReq = !empty($leftSchema['required'][$idx]);
                    $isRightReq = !empty($rightSchema['required'][$idx]);
                ?>
                <div class="bilingual-grid fl-mb-12">
                    <div class="field">
                        <label id="lbl_left_<?= $idx + 1 ?>">
                            <span id="txt_left_<?= $idx + 1 ?>"><?= htmlspecialchars($leftSchema['labels'][$idx]) ?></span>
                            <span class="fl-star <?= $isLeftReq ? '' : 'hidden' ?>" id="star_left_<?= $idx + 1 ?>">*</span>
                        </label>
                        <input type="text" id="<?= $fp['left_id'] ?>" placeholder="<?= htmlspecialchars($leftSchema['placeholders'][$idx]) ?>" aria-label="<?= htmlspecialchars($leftSchema['labels'][$idx]) ?>" <?= $isLeftReq ? 'data-required="1"' : '' ?>>
                    </div>
                    <div class="field">
                        <label id="lbl_right_<?= $idx + 1 ?>">
                            <span id="txt_right_<?= $idx + 1 ?>"><?= htmlspecialchars($rightSchema['labels'][$idx]) ?></span>
                            <span class="fl-star <?= $isRightReq ? '' : 'hidden' ?>" id="star_right_<?= $idx + 1 ?>">*</span>
                        </label>
                        <input type="text" id="<?= $fp['right_id'] ?>" placeholder="<?= htmlspecialchars($rightSchema['placeholders'][$idx]) ?>" aria-label="<?= htmlspecialchars($rightSchema['labels'][$idx]) ?>" <?= $isRightReq ? 'data-required="1"' : '' ?>>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="btn-row fl-mt-24">
                    <button type="button" class="btn btn-ghost" onclick="goStep3FromStep4()"><?= zrx_icon('arrow-left', 14) ?> <?= $t('btn_back') ?></button>
                    <button type="button" class="btn btn-primary" id="btnSaveDoctor" onclick="saveDoctor()"><?= $t('btn_save_doctor') ?> <?= zrx_icon('arrow-right', 14) ?></button>
                </div>
                <div class="step3-skip step4-skip">
                    <a href="#" onclick="skipDoctor(); return false;"><?= $t('btn_skip') ?></a>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- card-body -->
    </div><!-- wizard-card -->

</div><!-- wizard-wrap -->

<script>
    window.ZimRxHasExistingAccount = <?= $existingAccountUsername !== '' ? 'true' : 'false' ?>;
    window.ZimRxCurrentLang = <?= json_encode($currentLang) ?>;
    window.ZimRxHeaderSchemas = <?= json_encode($headerSchemas ?? [], JSON_UNESCAPED_UNICODE) ?>;
    window.ZimRxIconsMap = <?= json_encode(ZimRxIcon::getAll(), JSON_UNESCAPED_SLASHES) ?>;
    window.ZimRxCountryClinicalMap = <?= json_encode(CountryService::getCountryClinicalLanguages(), JSON_UNESCAPED_SLASHES) ?>;
    window.ZimRxCountryHelpMap = <?= json_encode(CountryService::getCountryHelpLanguages(), JSON_UNESCAPED_SLASHES) ?>;
    window.ZimRxCountryHeaderDefaults = <?= json_encode(CountryService::getCountryHeaderDefaults(), JSON_UNESCAPED_SLASHES) ?>;
    window.ZimRxAvailableFormularies = <?= json_encode(CountryService::getAvailableFormularies(), JSON_UNESCAPED_UNICODE) ?>;
    window.ZimRxAvailableAddressPacks = <?= json_encode(CountryService::getAvailableAddressPacks(), JSON_UNESCAPED_UNICODE) ?>;
    window.ZimRxFirstLaunchI18n = <?= json_encode($fl, JSON_UNESCAPED_UNICODE) ?>;
</script>

<script src="assets/js/layout/zrx_icons.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/zrx_icons.js') ?>"></script>
<script src="assets/js/layout/zrx_dropdown.js?v=<?= filemtime(__DIR__ . '/assets/js/layout/zrx_dropdown.js') ?>"></script>
<script src="assets/js/pages/first_launch.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/first_launch.js') ?>"></script>
</body>
</html>
