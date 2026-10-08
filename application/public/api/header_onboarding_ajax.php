<?php
declare(strict_types=1);

require_once __DIR__ . '/../init.php';
require_once ZIMRX_BASE_DIR . '/lib/auth.php';
require_once ZIMRX_BASE_DIR . '/lib/print_setup_lib.php';

// Endpoint handles doctor profile onboarding from first launch or header editor.
// Updates physician record and compiles bilingual print layout header.

header('Content-Type: text/plain; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(403);
    echo 'Forbidden';
    exit();
}

if (!zimrx_verify_csrf()) {
    http_response_code(403);
    echo 'Forbidden: Invalid CSRF token.';
    exit();
}

try {
    $doctorId = max(1, current_user_doctor_id());
    $isSkip = (trim((string)($_POST['skip'] ?? '')) === '1');
    $onboardDefaults = zimrx_onboarding_defaults();

    if ($isSkip) {
        $prof = (array)($onboardDefaults['doctor_profile'] ?? []);
        $name_native           = trim((string)($prof['name_native'] ?? ''));
        $qualifications_native = trim((string)($prof['qualifications_native'] ?? ''));
        $designation_native    = trim((string)($prof['designation_native'] ?? ''));
        $institute_native      = trim((string)($prof['institute_native'] ?? ''));
        $speciality_native     = trim((string)($prof['speciality_native'] ?? ''));
        $license_native        = trim((string)($prof['license_native'] ?? ''));
        $phone_native          = trim((string)($prof['phone_native'] ?? ''));

        $name_en               = trim((string)($prof['name_en'] ?? ''));
        $qualifications_en     = trim((string)($prof['qualifications_en'] ?? ''));
        $designation_en        = trim((string)($prof['designation_en'] ?? ''));
        $institute_en          = trim((string)($prof['institute_en'] ?? ''));
        $speciality_en         = trim((string)($prof['speciality_en'] ?? ''));
        $license_en            = trim((string)($prof['license_en'] ?? ''));
        $phone_en              = trim((string)($prof['phone_en'] ?? ''));
    } else {
        // Native Language Inputs
        $name_native           = trim((string)($_POST['name_native'] ?? ''));
        $qualifications_native = trim((string)($_POST['qualifications_native'] ?? ''));
        $designation_native    = trim((string)($_POST['designation_native'] ?? ''));
        $institute_native      = trim((string)($_POST['institute_native'] ?? ''));
        $speciality_native     = trim((string)($_POST['speciality_native'] ?? ''));
        $license_native        = trim((string)($_POST['license_native'] ?? ''));
        $phone_native          = trim((string)($_POST['phone_native'] ?? ''));

        // English Inputs
        $name_en               = trim((string)($_POST['name_en'] ?? ''));
        $qualifications_en     = trim((string)($_POST['qualifications_en'] ?? ''));
        $designation_en        = trim((string)($_POST['designation_en'] ?? ''));
        $institute_en          = trim((string)($_POST['institute_en'] ?? ''));
        $speciality_en         = trim((string)($_POST['speciality_en'] ?? ''));
        $license_en            = trim((string)($_POST['license_en'] ?? ''));
        $phone_en              = trim((string)($_POST['phone_en'] ?? ''));
    }

    // Helper to format space-parentheses combinations with non-breaking spaces
    $fmt = function($str) {
        $escaped = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
        return preg_replace('/(\S+)\s+(\([^)]+\))/', '$1&nbsp;$2', $escaped);
    };

    // Helper to split and sync qualifications line breaks if they have the same structure
    $formatQualifications = function($text, $otherText) use ($fmt) {
        $text = trim($text);
        $otherText = trim($otherText);
        $items = array_map('trim', explode(',', $text));
        $otherItems = array_map('trim', explode(',', $otherText));
        
        if (count($items) === count($otherItems) && count($items) > 1) {
            $count = count($items);
            $splitIndex = (int)ceil($count / 2);
            $firstPart = array_slice($items, 0, $splitIndex);
            $secondPart = array_slice($items, $splitIndex);
            return $fmt(implode(', ', $firstPart)) . ',<br>' . $fmt(implode(', ', $secondPart));
        }
        return $fmt($text);
    };

    // Detect whether left/native inputs contain Bengali script
    $isNativeBn = (bool)preg_match('/[\x{0980}-\x{09FF}]/u', $name_native . $qualifications_native);

    // If an international user fills English details but leaves native side empty, auto-balance
    if ($name_native === '' && $name_en !== '') {
        $name_native           = $name_en;
        $qualifications_native = $qualifications_en;
        $designation_native    = $designation_en;
        $institute_native      = $institute_en;
        $speciality_native     = $speciality_en;
        $license_native        = $license_en;
        $phone_native          = $phone_en;

        $name_en           = $institute_en !== '' ? $institute_en : 'Clinical Practice';
        $qualifications_en = 'Outpatient & Consultation Suite';
        $designation_en    = 'Department of ' . ($speciality_native !== '' ? $speciality_native : 'General Medicine');
        $institute_en      = '';
        $speciality_en     = '';
        $license_en        = '';
        $isNativeBn        = false;
    }

    // Font selection: SolaimanLipi/Kongsho for Bengali, Tinos for English/Latin
    $leftHeadFont = $isNativeBn ? 'kongsho' : 'tinos';
    $leftBodyFont = $isNativeBn ? 'solaimanlipi' : 'tinos';
    $leftHeadSize = $isNativeBn ? 'size="5"' : 'size="4"';
    $wordSpacing  = $isNativeBn ? '' : 'word-spacing:-0.05em;';

    // Left (Native / Doctor) Block HTML generation
    $leftHtml = [];
    if ($name_native !== '') {
        $leftHtml[] = '<p class="zrx-header-left-line-1" style="margin-bottom:-3.5px;"><b><font face="' . $leftHeadFont . '" ' . $leftHeadSize . '><span style="' . $wordSpacing . '">' . $fmt($name_native) . '</span></font></b></p>';
    }
    if ($qualifications_native !== '') {
        $leftHtml[] = '<p class="zrx-header-left-line-2"><b><font face="' . $leftBodyFont . '"><span style="font-size:11pt;' . $wordSpacing . '">' . $formatQualifications($qualifications_native, $qualifications_en) . '</span></font></b></p>';
    }
    if ($designation_native !== '') {
        $leftHtml[] = '<p class="zrx-header-left-line-3"><b><font face="' . $leftBodyFont . '"><span style="font-size:11pt;' . $wordSpacing . '">' . $fmt($designation_native) . '</span></font></b></p>';
    }
    if ($institute_native !== '') {
        $leftHtml[] = '<p class="zrx-header-left-line-4"><font face="' . $leftBodyFont . '"><span style="font-size:11pt;' . $wordSpacing . '">' . $fmt($institute_native) . '</span></font></p>';
    }
    if ($speciality_native !== '') {
        $leftHtml[] = '<p class="zrx-header-left-line-5"><font face="' . $leftBodyFont . '"><span style="font-size:11pt;' . $wordSpacing . '">' . $fmt($speciality_native) . '</span></font></p>';
    }
    if ($license_native !== '') {
        $leftHtml[] = '<p class="zrx-header-left-line-6"><font face="' . $leftBodyFont . '"><span style="font-size:11pt;' . $wordSpacing . '">' . $fmt($license_native) . '</span></font></p>';
    }
    if ($phone_native !== '') {
        $leftHtml[] = '<p class="zrx-header-left-line-7"><font face="' . $leftBodyFont . '"><span style="font-size:11pt;' . $wordSpacing . '">' . $fmt($phone_native) . '</span></font></p>';
    }
    $leftBlockHtml = implode('', $leftHtml);

    // Right (English / Clinic) Block HTML generation
    $rightHtml = [];
    if ($name_en !== '') {
        $rightHtml[] = '<p class="zrx-header-right-line-1"><b><font face="tinos" size="4"><span style="word-spacing:-0.05em;">' . $fmt($name_en) . '</span></font></b></p>';
    }
    if ($qualifications_en !== '') {
        $rightHtml[] = '<p class="zrx-header-right-line-2"><b><font face="tinos"><span style="font-size:11pt;word-spacing:-0.05em;">' . $formatQualifications($qualifications_en, $qualifications_native) . '</span></font></b></p>';
    }
    if ($designation_en !== '') {
        $rightHtml[] = '<p class="zrx-header-right-line-3"><b><font face="tinos"><span style="font-size:11pt;word-spacing:-0.05em;">' . $fmt($designation_en) . '</span></font></b></p>';
    }
    if ($institute_en !== '') {
        $rightHtml[] = '<p class="zrx-header-right-line-4"><font face="tinos"><span style="font-size:11pt;word-spacing:-0.05em;">' . $fmt($institute_en) . '</span></font></p>';
    }
    if ($speciality_en !== '') {
        $rightHtml[] = '<p class="zrx-header-right-line-5"><font face="tinos"><span style="font-size:11pt;word-spacing:-0.05em;">' . $fmt($speciality_en) . '</span></font></p>';
    }
    if ($license_en !== '') {
        $rightHtml[] = '<p class="zrx-header-right-line-6"><font face="tinos"><span style="font-size:11pt;word-spacing:-0.05em;">' . $fmt($license_en) . '</span></font></p>';
    }
    if ($phone_en !== '') {
        $rightHtml[] = '<p class="zrx-header-right-line-7"><font face="tinos"><span style="font-size:11pt;word-spacing:-0.05em;">' . $fmt($phone_en) . '</span></font></p>';
    }
    $rightBlockHtml = implode('', $rightHtml);
    $onboardDefaults = zimrx_onboarding_defaults();
    $footerHtml = (string)($onboardDefaults['footer_html'] ?? '');

    // Resolve physician profile attributes based on regional mode
    if ($isNativeBn) {
        $displayName       = $name_en !== '' ? $name_en : ($name_native !== '' ? $name_native : 'Doctor');
        $docFullNameEn     = $name_en !== '' ? $name_en : 'Doctor';
        $docFullNameNative = $name_native;
        $docDesigEn        = $designation_en;
        $docDesigNative    = $designation_native;
        $docInstEn         = $institute_en;
        $docInstNative     = $institute_native;
        $docQualEn         = $qualifications_en;
        $docQualNative     = $qualifications_native;
        $docSpecEn         = $speciality_en;
        $docSpecNative     = $speciality_native;
        $docLicEn          = $license_en;
        $docLicNative      = $license_native;
        $docPhone          = $phone_en !== '' ? $phone_en : $phone_native;
        $headerDoctorName  = $name_en !== '' ? $name_en : $name_native;
        $headerQual        = $qualifications_en !== '' ? $qualifications_en : $qualifications_native;
        $headerSpec        = $speciality_en !== '' ? $speciality_en : $speciality_native;
        $headerLic         = $license_en !== '' ? $license_en : $license_native;
        $chamberName       = $institute_en !== '' ? $institute_en : $institute_native;
    } else {
        // International/Monolingual: Left side represents the physician; Right side represents the practice
        $displayName       = $name_native !== '' ? $name_native : ($name_en !== '' ? $name_en : 'Doctor');
        $docFullNameEn     = $name_native !== '' ? $name_native : ($name_en !== '' ? $name_en : 'Doctor');
        $docFullNameNative = '';
        $docDesigEn        = $designation_native;
        $docDesigNative    = '';
        $docInstEn         = $institute_native !== '' ? $institute_native : $name_en;
        $docInstNative     = '';
        $docQualEn         = $qualifications_native;
        $docQualNative     = '';
        $docSpecEn         = $speciality_native;
        $docSpecNative     = '';
        $docLicEn          = $license_native;
        $docLicNative      = '';
        $docPhone          = $phone_native !== '' ? $phone_native : $phone_en;
        $headerDoctorName  = $displayName;
        $headerQual        = $qualifications_native;
        $headerSpec        = $speciality_native;
        $headerLic         = $license_native;
        $chamberName       = $name_en;
    }

    // Synchronize zimrx_doctors table
    if (DbSchema::tableExists($pdo, 'zimrx_doctors')) {
        $pdo->prepare(
            "UPDATE zimrx_doctors SET
                display_name          = :dname,
                full_name_en          = :name_en,
                full_name_native      = :name_native,
                designation_en        = :desig_en,
                designation_native    = :desig_native,
                institute_en          = :inst_en,
                institute_native      = :inst_native,
                qualifications_en     = :qual_en,
                qualifications_native = :qual_native,
                specialty_en          = :spec_en,
                specialty_native      = :spec_native,
                license_no_en         = :lic_en,
                license_no_native     = :lic_native,
                phone_number          = :phone,
                updated_at            = CURRENT_TIMESTAMP
             WHERE id = :doc_id"
        )->execute([
            'dname'        => $displayName,
            'name_en'      => $docFullNameEn,
            'name_native'  => $docFullNameNative,
            'desig_en'     => $docDesigEn,
            'desig_native' => $docDesigNative,
            'inst_en'      => $docInstEn,
            'inst_native'  => $docInstNative,
            'qual_en'      => $docQualEn,
            'qual_native'  => $docQualNative,
            'spec_en'      => $docSpecEn,
            'spec_native'  => $docSpecNative,
            'lic_en'       => $docLicEn,
            'lic_native'   => $docLicNative,
            'phone'        => $docPhone,
            'doc_id'       => $doctorId,
        ]);
    }

    // Assemble DB payload
    $payload = [
        'doctor_name'      => $headerDoctorName,
        'qualifications'   => $headerQual,
        'specialty'        => $headerSpec,
        'license_no'       => $headerLic,
        'chamber_name'     => $chamberName,

        'left_line_1'      => $name_native,
        'left_line_2'      => $qualifications_native,
        'left_line_3'      => $designation_native,
        'left_line_4'      => $institute_native,
        'left_line_5'      => $speciality_native,
        'left_line_6'      => $license_native,
        'left_line_7'      => $phone_native,
        
        'right_line_1'     => $name_en,
        'right_line_2'     => $qualifications_en,
        'right_line_3'     => $designation_en,
        'right_line_4'     => $institute_en,
        'right_line_5'     => $speciality_en,
        'right_line_6'     => $license_en,
        'right_line_7'     => $phone_en,
        
        'left_block_html'  => $leftBlockHtml,
        'right_block_html' => $rightBlockHtml,
        'footer_html'      => $footerHtml,
        'has_onboarded'    => 1
    ];

    // Save using standard bridge function (which updates print layout + header settings)
    zimrx_print_save_setup($pdo, $doctorId, $payload);

    // Finalize first launch setup completion
    if (zimrx_db_table_exists($pdo, 'zimrx_app_config')) {
        $pdo->prepare(
            "INSERT INTO zimrx_app_config (config_key, config_value, updated_at)
             VALUES ('setup_complete', '1', CURRENT_TIMESTAMP)
             ON CONFLICT(config_key) DO UPDATE SET config_value = '1', updated_at = CURRENT_TIMESTAMP"
        )->execute();
    }

    // Ensure clinical reference databases are active before entering workspace
    $practiceCountry = 'INT';
    if (zimrx_db_table_exists($pdo, 'zimrx_app_config')) {
        $stCountry = $pdo->prepare("SELECT config_value FROM zimrx_app_config WHERE config_key = 'practice_country' LIMIT 1");
        $stCountry->execute();
        $practiceCountry = (string)($stCountry->fetchColumn() ?: 'INT');
    }
    \ZimRx\Db\DbConnections::ensureReferenceDatabaseExists(ZIMRX_DB_SYSTEMDATA, $practiceCountry);
    \ZimRx\Db\DbConnections::ensureReferenceDatabaseExists(ZIMRX_DB_STATIC, $practiceCountry);
    
    echo '1';
} catch (Throwable $e) {
    error_log('[ZimRx] header_onboarding_ajax error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Save failed. Please try again.';
}
