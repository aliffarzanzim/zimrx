<?php
declare(strict_types=1);

// Clinical analytics dashboard: footfall trends, patient retention, demographics, and monthly financial summaries.

$page_title = "Performance Dashboard - ZimRx";
require_once __DIR__ . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/Services/PracticeProfileService.php';

$pdo = DbConnections::userdata();
$activePracticeProfile = (new \ZimRx\Services\PracticeProfileService($pdo))->activeForCurrentUser();
$profileId = (int)($activePracticeProfile['id'] ?? 0);
$doctorId = (int)($activePracticeProfile['doctor_id'] ?? 0);
if ($doctorId <= 0 || $profileId <= 0) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><title>Practice Profile Required</title></head><body><h2>Practice profile required</h2><p>Select an active practice profile from the application header.</p><p><a href="index.php">Return to Dashboard</a></p></body></html>';
    exit;
}

// Period Filter
$period = trim((string)($_GET['period'] ?? '30_days'));
$today = date('Y-m-d');
$startDate = '';

if ($period === 'this_month') {
    $startDate = date('Y-m-01');
} elseif ($period === '3_months') {
    $startDate = date('Y-m-d', strtotime('-90 days'));
} elseif ($period === 'this_year') {
    $startDate = date('Y-01-01');
} elseif ($period === 'all_time') {
    $startDate = '2000-01-01';
} else {
    $period = '30_days';
    $startDate = date('Y-m-d', strtotime('-30 days'));
}

// 1. Executive Summary KPIs
$kpiStmt = $pdo->prepare(
    "SELECT 
        COUNT(DISTINCT v.id) AS total_visits,
        COUNT(DISTINCT CASE WHEN v.visit_no = 1 THEN v.id END) AS new_visits,
        COUNT(DISTINCT CASE WHEN v.visit_no > 1 THEN v.id END) AS return_visits,
        COUNT(DISTINCT substr(v.visit_date, 1, 10)) AS active_days
     FROM zimrx_visits v
     WHERE v.doctor_id = :doc AND v.profile_id = :profile_id AND substr(v.visit_date, 1, 10) >= :start"
);
$kpiStmt->execute(['doc' => $doctorId, 'profile_id' => $profileId, 'start' => $startDate]);
$kpiData = $kpiStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$totalVisits = (int)($kpiData['total_visits'] ?? 0);
$newVisits = (int)($kpiData['new_visits'] ?? 0);
$returnVisits = (int)($kpiData['return_visits'] ?? 0);
$activeDays = max(1, (int)($kpiData['active_days'] ?? 1));
$returnRate = $totalVisits > 0 ? round(($returnVisits / $totalVisits) * 100, 1) : 0;
$avgDailyFootfall = round($totalVisits / $activeDays, 1);

// Revenue in Period
$revStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(paid_amount), 0) AS total_rev, COUNT(*) AS total_tx 
     FROM zimrx_payments 
     WHERE doctor_id = :doc AND profile_id = :profile_id AND date(created_at) >= :start"
);
$revStmt->execute(['doc' => $doctorId, 'profile_id' => $profileId, 'start' => $startDate]);
$revData = $revStmt->fetch(PDO::FETCH_ASSOC);
$totalRevenue = (float)($revData['total_rev'] ?? 0);
$avgRevPerPatient = $totalVisits > 0 ? round($totalRevenue / $totalVisits) : ($totalRevenue > 0 ? round($totalRevenue / max(1, (int)$revData['total_tx'])) : 0);

// 2. Daily Trend Data (Last 14 - 30 days)
$trendDaysCount = ($period === '30_days' || $period === 'this_month') ? 14 : 20;
$trendStmt = $pdo->prepare(
    "SELECT 
        substr(v.visit_date, 1, 10) AS dt,
        COUNT(v.id) AS patient_count
     FROM zimrx_visits v
     WHERE v.doctor_id = :doc AND v.profile_id = :profile_id AND substr(v.visit_date, 1, 10) >= :start
     GROUP BY dt
     ORDER BY dt ASC"
);
$trendStmt->execute(['doc' => $doctorId, 'profile_id' => $profileId, 'start' => $startDate]);
$trendVisitsMap = [];
while ($r = $trendStmt->fetch(PDO::FETCH_ASSOC)) {
    $trendVisitsMap[$r['dt']] = (int)$r['patient_count'];
}

$revTrendStmt = $pdo->prepare(
    "SELECT 
        date(created_at) AS dt,
        COALESCE(SUM(paid_amount), 0) AS daily_rev
     FROM zimrx_payments 
     WHERE doctor_id = :doc AND profile_id = :profile_id AND date(created_at) >= :start
     GROUP BY dt
     ORDER BY dt ASC"
);
$revTrendStmt->execute(['doc' => $doctorId, 'profile_id' => $profileId, 'start' => $startDate]);
$trendRevMap = [];
while ($r = $revTrendStmt->fetch(PDO::FETCH_ASSOC)) {
    $trendRevMap[$r['dt']] = (float)$r['daily_rev'];
}

// Build unified dates list for SVG Trend Chart
$allTrendDates = array_unique(array_merge(array_keys($trendVisitsMap), array_keys($trendRevMap)));
sort($allTrendDates);
if (count($allTrendDates) < 7) {
    // Fill back at least 7 days for visualization
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        if (!in_array($d, $allTrendDates, true)) {
            $allTrendDates[] = $d;
        }
    }
    sort($allTrendDates);
}
$allTrendDates = array_slice($allTrendDates, -$trendDaysCount);

$trendPoints = [];
$maxPatients = 5;
$maxRevenue = 1000;
foreach ($allTrendDates as $dt) {
    $pts = (int)($trendVisitsMap[$dt] ?? 0);
    $rev = (float)($trendRevMap[$dt] ?? 0);
    if ($pts > $maxPatients) $maxPatients = $pts;
    if ($rev > $maxRevenue) $maxRevenue = $rev;
    $trendPoints[] = [
        'date' => $dt,
        'label' => date('d M', strtotime($dt)),
        'patients' => $pts,
        'revenue' => $rev
    ];
}

// 3. Patient Demographics (Gender)
$genderStmt = $pdo->prepare(
    "SELECT 
        COALESCE(NULLIF(p.gender, ''), 'Not Specified') AS g_name,
        COUNT(DISTINCT v.patient_id) AS p_count
     FROM zimrx_visits v
     JOIN zimrx_patients p ON p.id = v.patient_id
     WHERE v.doctor_id = :doc AND v.profile_id = :profile_id AND substr(v.visit_date, 1, 10) >= :start
     GROUP BY g_name"
);
$genderStmt->execute(['doc' => $doctorId, 'profile_id' => $profileId, 'start' => $startDate]);
$genderRows = $genderStmt->fetchAll(PDO::FETCH_ASSOC);

$maleCount = 0;
$femaleCount = 0;
$otherCount = 0;
foreach ($genderRows as $gr) {
    $gn = strtolower(trim((string)$gr['g_name']));
    $cnt = (int)$gr['p_count'];
    if (str_starts_with($gn, 'm')) $maleCount += $cnt;
    elseif (str_starts_with($gn, 'f')) $femaleCount += $cnt;
    else $otherCount += $cnt;
}
$genderTotal = max(1, $maleCount + $femaleCount + $otherCount);

// 4. Age Demographics
$ageGroups = [
    'Pediatric (<12)' => 0,
    'Adolescent (12-18)' => 0,
    'Adult (19-45)' => 0,
    'Middle-Aged (46-60)' => 0,
    'Geriatric (60+)' => 0
];
$ageStmt = $pdo->prepare(
    "SELECT coalesce(nullif(p.age, ''), v.age_at_visit, '') AS age_val
     FROM zimrx_visits v
     JOIN zimrx_patients p ON p.id = v.patient_id
     WHERE v.doctor_id = :doc AND v.profile_id = :profile_id AND substr(v.visit_date, 1, 10) >= :start"
);
$ageStmt->execute(['doc' => $doctorId, 'profile_id' => $profileId, 'start' => $startDate]);
while ($row = $ageStmt->fetch(PDO::FETCH_ASSOC)) {
    $val = (int)preg_replace('/[^0-9]/', '', (string)$row['age_val']);
    if ($val <= 0) continue;
    if ($val < 12) $ageGroups['Pediatric (<12)']++;
    elseif ($val <= 18) $ageGroups['Adolescent (12-18)']++;
    elseif ($val <= 45) $ageGroups['Adult (19-45)']++;
    elseif ($val <= 60) $ageGroups['Middle-Aged (46-60)']++;
    else $ageGroups['Geriatric (60+)']++;
}
$maxAgeGroup = max(1, max(array_values($ageGroups)));

// 5. Referral Channels
$refStmt = $pdo->prepare(
    "SELECT 
        COALESCE(NULLIF(referral_category, ''), 'self') AS cat,
        COUNT(*) AS ref_count
     FROM zimrx_visits
     WHERE doctor_id = :doc AND profile_id = :profile_id AND substr(visit_date, 1, 10) >= :start
     GROUP BY cat
     ORDER BY ref_count DESC LIMIT 5"
);
$refStmt->execute(['doc' => $doctorId, 'profile_id' => $profileId, 'start' => $startDate]);
$referralRows = $refStmt->fetchAll(PDO::FETCH_ASSOC);
$refTotal = max(1, array_sum(array_column($referralRows, 'ref_count')));

// 6. Top Complaints
$topPc = [];
try {
    $pcStmt = $pdo->prepare(
        "SELECT pc_name, use_count 
         FROM zimrx_user_pc 
         WHERE doctor_id = :doc 
         ORDER BY use_count DESC LIMIT 5"
    );
    $pcStmt->execute(['doc' => $doctorId]);
    $topPc = $pcStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[ZimRx] Performance dashboard top complaints query error: ' . $e->getMessage());
}
$maxPcCount = !empty($topPc) ? max(1, max(array_column($topPc, 'use_count'))) : 1;

// 7. Monthly Performance Ledger Table
$ledgerStmt = $pdo->prepare(
    "SELECT 
        substr(v.visit_date, 1, 7) AS ym,
        COUNT(v.id) AS visits,
        COUNT(CASE WHEN v.visit_no = 1 THEN 1 END) AS new_count,
        COUNT(CASE WHEN v.visit_no > 1 THEN 1 END) AS return_count
     FROM zimrx_visits v
     WHERE v.doctor_id = :doc AND v.profile_id = :profile_id
     GROUP BY ym
     ORDER BY ym DESC LIMIT 6"
);
$ledgerStmt->execute(['doc' => $doctorId, 'profile_id' => $profileId]);
$ledgerRows = $ledgerStmt->fetchAll(PDO::FETCH_ASSOC);

// Map monthly revenues
$ledgerRevStmt = $pdo->prepare(
    "SELECT 
        strftime('%Y-%m', created_at) AS ym,
        COALESCE(SUM(paid_amount), 0) AS rev
     FROM zimrx_payments
     WHERE doctor_id = :doc AND profile_id = :profile_id
     GROUP BY ym"
);
$ledgerRevStmt->execute(['doc' => $doctorId, 'profile_id' => $profileId]);
$ledgerRevMap = [];
while ($lr = $ledgerRevStmt->fetch(PDO::FETCH_ASSOC)) {
    $ledgerRevMap[$lr['ym']] = (float)$lr['rev'];
}

require_once __DIR__ . '/header.php';
require_once ZIMRX_BASE_DIR . '/views/performance_dashboard_view.php';
require_once __DIR__ . '/footer.php';
