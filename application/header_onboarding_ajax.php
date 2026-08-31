<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/print_setup_lib.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $doctorId = current_user_doctor_id();

    // Bangla Inputs
    $name_bn           = trim((string)($_POST['name_bn'] ?? ''));
    $qualifications_bn = trim((string)($_POST['qualifications_bn'] ?? ''));
    $designation_bn    = trim((string)($_POST['designation_bn'] ?? ''));
    $institute_bn      = trim((string)($_POST['institute_bn'] ?? ''));
    $speciality_bn     = trim((string)($_POST['speciality_bn'] ?? ''));
    $bmdc_bn           = trim((string)($_POST['bmdc_bn'] ?? ''));
    $phone_bn          = trim((string)($_POST['phone_bn'] ?? ''));

    // English Inputs
    $name_en           = trim((string)($_POST['name_en'] ?? ''));
    $qualifications_en = trim((string)($_POST['qualifications_en'] ?? ''));
    $designation_en    = trim((string)($_POST['designation_en'] ?? ''));
    $institute_en      = trim((string)($_POST['institute_en'] ?? ''));
    $speciality_en     = trim((string)($_POST['speciality_en'] ?? ''));
    $bmdc_en           = trim((string)($_POST['bmdc_en'] ?? ''));
    $phone_en          = trim((string)($_POST['phone_en'] ?? ''));

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
            $splitIndex = ceil($count / 2);
            $firstPart = array_slice($items, 0, $splitIndex);
            $secondPart = array_slice($items, $splitIndex);
            return $fmt(implode(', ', $firstPart)) . ',<br>' . $fmt(implode(', ', $secondPart));
        }
        return $fmt($text);
    };

    // Left (Bangla) Block HTML generation
    $leftHtml = [];
    if ($name_bn !== '') {
        $leftHtml[] = '<p class="zrx-header-left-line-1" style="margin-bottom:-3.5px;"><b><font face="kongsho" size="5">' . $fmt($name_bn) . '</font></b></p>';
    }
    if ($qualifications_bn !== '') {
        $leftHtml[] = '<p class="zrx-header-left-line-2"><b><font face="solaimanlipi"><span style="font-size:11pt;">' . $formatQualifications($qualifications_bn, $qualifications_en) . '</span></font></b></p>';
    }
    if ($designation_bn !== '') {
        $leftHtml[] = '<p class="zrx-header-left-line-3"><b><font face="solaimanlipi"><span style="font-size:11pt;">' . $fmt($designation_bn) . '</span></font></b></p>';
    }
    if ($institute_bn !== '') {
        $leftHtml[] = '<p class="zrx-header-left-line-4"><font face="solaimanlipi"><span style="font-size:11pt;">' . $fmt($institute_bn) . '</span></font></p>';
    }
    if ($speciality_bn !== '') {
        $leftHtml[] = '<p class="zrx-header-left-line-5"><font face="solaimanlipi"><span style="font-size:11pt;">' . $fmt($speciality_bn) . '</span></font></p>';
    }
    if ($bmdc_bn !== '') {
        $leftHtml[] = '<p class="zrx-header-left-line-6"><font face="solaimanlipi"><span style="font-size:11pt;">' . $fmt($bmdc_bn) . '</span></font></p>';
    }
    if ($phone_bn !== '') {
        $leftHtml[] = '<p class="zrx-header-left-line-7"><font face="solaimanlipi"><span style="font-size:11pt;">' . $fmt($phone_bn) . '</span></font></p>';
    }
    $leftBlockHtml = implode('', $leftHtml);

    // Right (English) Block HTML generation
    $rightHtml = [];
    if ($name_en !== '') {
        $rightHtml[] = '<p class="zrx-header-right-line-1"><b><font face="times new roman" size="4"><span style="word-spacing:-0.05em;">' . $fmt($name_en) . '</span></font></b></p>';
    }
    if ($qualifications_en !== '') {
        $rightHtml[] = '<p class="zrx-header-right-line-2"><b><font face="times new roman"><span style="font-size:11pt;word-spacing:-0.05em;">' . $formatQualifications($qualifications_en, $qualifications_bn) . '</span></font></b></p>';
    }
    if ($designation_en !== '') {
        $rightHtml[] = '<p class="zrx-header-right-line-3"><b><font face="times new roman"><span style="font-size:11pt;word-spacing:-0.05em;">' . $fmt($designation_en) . '</span></font></b></p>';
    }
    if ($institute_en !== '') {
        $rightHtml[] = '<p class="zrx-header-right-line-4"><font face="times new roman"><span style="font-size:11pt;word-spacing:-0.05em;">' . $fmt($institute_en) . '</span></font></p>';
    }
    if ($speciality_en !== '') {
        $rightHtml[] = '<p class="zrx-header-right-line-5"><font face="times new roman"><span style="font-size:11pt;word-spacing:-0.05em;">' . $fmt($speciality_en) . '</span></font></p>';
    }
    if ($bmdc_en !== '') {
        $rightHtml[] = '<p class="zrx-header-right-line-6"><font face="times new roman"><span style="font-size:11pt;word-spacing:-0.05em;">' . $fmt($bmdc_en) . '</span></font></p>';
    }
    if ($phone_en !== '') {
        $rightHtml[] = '<p class="zrx-header-right-line-7"><font face="times new roman"><span style="font-size:11pt;word-spacing:-0.05em;">' . $fmt($phone_en) . '</span></font></p>';
    }
    $rightBlockHtml = implode('', $rightHtml);
    $footerHtml = '<p style="text-align: center; margin: 0; line-height: 1.5;"><font face="solaimanlipi"><span style="font-size: 10pt;"><b>চেম্বারঃ</b> ZimRx ডায়াগনস্টিক এন্ড কনসালটেশন সেন্টার, ঢাকা।<br><b>চেম্বারে আসার পূর্বে সিরিয়ালঃ ০১৪০৮-XXXXXX নম্বরে যোগাযোগ করে সিরিয়াল দিবেন।</b><br><b>রোগী দেখার সময়ঃ</b> বিকাল ৪ টা থেকে রাত ৮ টা (সপ্তাহে ৬ দিন)। ওয়েবসাইটঃ www.zimrx.guessthecase.eu.org</span></font></p>';

    // Assemble DB payload
    $payload = [
        'doctor_name'      => $name_en,
        'qualifications'   => $qualifications_en,
        'specialty'        => $speciality_en,
        'bmdc_no'          => $bmdc_en,

        'left_line_1'      => $name_bn,
        'left_line_2'      => $qualifications_bn,
        'left_line_3'      => $designation_bn,
        'left_line_4'      => $institute_bn,
        'left_line_5'      => $speciality_bn,
        'left_line_6'      => $bmdc_bn,
        'left_line_7'      => $phone_bn,
        
        'right_line_1'     => $name_en,
        'right_line_2'     => $qualifications_en,
        'right_line_3'     => $designation_en,
        'right_line_4'     => $institute_en,
        'right_line_5'     => $speciality_en,
        'right_line_6'     => $bmdc_en,
        'right_line_7'     => $phone_en,
        
        'left_block_html'  => $leftBlockHtml,
        'right_block_html' => $rightBlockHtml,
        'footer_html'      => $footerHtml,
        'has_onboarded'    => 1
    ];

    // Save using standard bridge function (which updates print layout + header settings)
    zimrx_bridge_save_print_setup($pdo, $doctorId, $payload);
    
    echo '1';
} catch (Throwable $e) {
    http_response_code(500);
    echo $e->getMessage();
}
