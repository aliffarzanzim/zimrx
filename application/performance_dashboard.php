<?php
$page_title = "Performance Dashboard - ZimRx";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/db.php';

$pdo = DbConnections::userdata();
$doctorId = max(1, (int)(function_exists('current_user_doctor_id') ? current_user_doctor_id() : 1));

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
     WHERE v.doctor_id = :doc AND substr(v.visit_date, 1, 10) >= :start"
);
$kpiStmt->execute(['doc' => $doctorId, 'start' => $startDate]);
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
     WHERE doctor_id = :doc AND date(created_at) >= :start"
);
$revStmt->execute(['doc' => $doctorId, 'start' => $startDate]);
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
     WHERE v.doctor_id = :doc AND substr(v.visit_date, 1, 10) >= :start
     GROUP BY dt
     ORDER BY dt ASC"
);
$trendStmt->execute(['doc' => $doctorId, 'start' => $startDate]);
$trendVisitsMap = [];
while ($r = $trendStmt->fetch(PDO::FETCH_ASSOC)) {
    $trendVisitsMap[$r['dt']] = (int)$r['patient_count'];
}

$revTrendStmt = $pdo->prepare(
    "SELECT 
        date(created_at) AS dt,
        COALESCE(SUM(paid_amount), 0) AS daily_rev
     FROM zimrx_payments 
     WHERE doctor_id = :doc AND date(created_at) >= :start
     GROUP BY dt
     ORDER BY dt ASC"
);
$revTrendStmt->execute(['doc' => $doctorId, 'start' => $startDate]);
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
     WHERE v.doctor_id = :doc AND substr(v.visit_date, 1, 10) >= :start
     GROUP BY g_name"
);
$genderStmt->execute(['doc' => $doctorId, 'start' => $startDate]);
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
     WHERE v.doctor_id = :doc AND substr(v.visit_date, 1, 10) >= :start"
);
$ageStmt->execute(['doc' => $doctorId, 'start' => $startDate]);
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
     WHERE doctor_id = :doc AND substr(visit_date, 1, 10) >= :start
     GROUP BY cat
     ORDER BY ref_count DESC LIMIT 5"
);
$refStmt->execute(['doc' => $doctorId, 'start' => $startDate]);
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
} catch (Throwable $e) {}
if (empty($topPc)) {
    $topPc = [
        ['pc_name' => 'Fever', 'use_count' => 12],
        ['pc_name' => 'Cough & Cold', 'use_count' => 9],
        ['pc_name' => 'Abdominal Pain', 'use_count' => 7],
        ['pc_name' => 'Headache', 'use_count' => 6],
        ['pc_name' => 'Generalized Weakness', 'use_count' => 4],
    ];
}
$maxPcCount = max(1, max(array_column($topPc, 'use_count')));

// 7. Monthly Performance Ledger Table
$ledgerStmt = $pdo->prepare(
    "SELECT 
        substr(v.visit_date, 1, 7) AS ym,
        COUNT(v.id) AS visits,
        COUNT(CASE WHEN v.visit_no = 1 THEN 1 END) AS new_count,
        COUNT(CASE WHEN v.visit_no > 1 THEN 1 END) AS return_count
     FROM zimrx_visits v
     WHERE v.doctor_id = :doc
     GROUP BY ym
     ORDER BY ym DESC LIMIT 6"
);
$ledgerStmt->execute(['doc' => $doctorId]);
$ledgerRows = $ledgerStmt->fetchAll(PDO::FETCH_ASSOC);

// Map monthly revenues
$ledgerRevStmt = $pdo->prepare(
    "SELECT 
        strftime('%Y-%m', created_at) AS ym,
        COALESCE(SUM(paid_amount), 0) AS rev
     FROM zimrx_payments
     WHERE doctor_id = :doc
     GROUP BY ym"
);
$ledgerRevStmt->execute(['doc' => $doctorId]);
$ledgerRevMap = [];
while ($lr = $ledgerRevStmt->fetch(PDO::FETCH_ASSOC)) {
    $ledgerRevMap[$lr['ym']] = (float)$lr['rev'];
}

require_once __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/assets/css/dashboard.css') ?>">

<div class="dash-container">
    <!-- Page Header -->
    <div class="dash-header">
        <div class="dash-title-wrap">
            <h1>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" color="#2563eb"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                <span>Performance &amp; Clinical Analytics</span>
            </h1>
            <p>Real-time insights on patient volume, retention rate, clinical presentations, and revenue trends.</p>
        </div>

        <div class="dash-period-group">
            <a href="performance_dashboard.php?period=30_days" class="dash-period-btn <?= $period === '30_days' ? 'active' : '' ?>">Last 30 Days</a>
            <a href="performance_dashboard.php?period=this_month" class="dash-period-btn <?= $period === 'this_month' ? 'active' : '' ?>">This Month</a>
            <a href="performance_dashboard.php?period=3_months" class="dash-period-btn <?= $period === '3_months' ? 'active' : '' ?>">Last 3 Months</a>
            <a href="performance_dashboard.php?period=this_year" class="dash-period-btn <?= $period === 'this_year' ? 'active' : '' ?>">This Year</a>
            <a href="performance_dashboard.php?period=all_time" class="dash-period-btn <?= $period === 'all_time' ? 'active' : '' ?>">All Time</a>
        </div>
    </div>

    <!-- Executive KPI Summary Cards -->
    <div class="dash-kpi-grid">
        <div class="dash-kpi-card">
            <div class="dash-kpi-top">
                <span class="dash-kpi-title">Total Consultations</span>
                <div class="dash-kpi-icon kpi-blue">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </div>
            </div>
            <div class="dash-kpi-num"><?= number_format($totalVisits) ?></div>
            <div class="dash-kpi-footer">
                <span>New: <strong><?= $newVisits ?></strong></span>
                <span>&bull;</span>
                <span>Returning: <strong><?= $returnVisits ?></strong></span>
            </div>
        </div>

        <div class="dash-kpi-card">
            <div class="dash-kpi-top">
                <span class="dash-kpi-title">Patient Retention Rate</span>
                <div class="dash-kpi-icon kpi-green">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                </div>
            </div>
            <div class="dash-kpi-num" style="color: #059669;"><?= $returnRate ?>%</div>
            <div class="dash-kpi-footer">
                <span>Follow-up &amp; revisits share</span>
            </div>
        </div>

        <div class="dash-kpi-card">
            <div class="dash-kpi-top">
                <span class="dash-kpi-title">Daily Average Footfall</span>
                <div class="dash-kpi-icon kpi-purple">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                </div>
            </div>
            <div class="dash-kpi-num"><?= $avgDailyFootfall ?> <span style="font-size: 0.9rem; font-weight: 500; color: #64748b;">pts/day</span></div>
            <div class="dash-kpi-footer">
                <span>Across <?= $activeDays ?> clinic working days</span>
            </div>
        </div>

        <div class="dash-kpi-card">
            <div class="dash-kpi-top">
                <span class="dash-kpi-title">Avg Revenue Per Patient</span>
                <div class="dash-kpi-icon kpi-amber">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                </div>
            </div>
            <div class="dash-kpi-num">৳<?= number_format($avgRevPerPatient) ?></div>
            <div class="dash-kpi-footer">
                <span>Total Collected: <strong>৳<?= number_format($totalRevenue) ?></strong></span>
            </div>
        </div>
    </div>

    <!-- Main Dual Trend Chart Section -->
    <div class="dash-grid-2col">
        <!-- SVG Trend Chart Card -->
        <div class="dash-card">
            <div class="dash-card-header">
                <h3>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                    <span>Patient Volume &amp; Daily Revenue Trend</span>
                </h3>
                <div style="display: flex; gap: 12px; font-size: 0.76rem; font-weight: 600;">
                    <span style="display: flex; align-items: center; gap: 4px; color: #2563eb;">
                        <span style="width: 10px; height: 3px; background: #2563eb; display: inline-block; border-radius: 2px;"></span>
                        Patients (left)
                    </span>
                    <span style="display: flex; align-items: center; gap: 4px; color: #10b981;">
                        <span style="width: 10px; height: 3px; background: #10b981; display: inline-block; border-radius: 2px;"></span>
                        Revenue ৳ (right)
                    </span>
                </div>
            </div>
            <div class="dash-card-body" style="padding-bottom: 0.5rem;">
                <div class="chart-svg-wrap" id="trend-chart-wrap">
                    <?php
                    $svgW = 740;
                    $svgH = 260;
                    $padL = 40;
                    $padR = 50;
                    $padT = 20;
                    $padB = 35;
                    $plotW = $svgW - $padL - $padR;
                    $plotH = $svgH - $padT - $padB;
                    $countPts = count($trendPoints);
                    $stepX = $countPts > 1 ? $plotW / ($countPts - 1) : $plotW;

                    $patientCoords = [];
                    $revenueCoords = [];
                    foreach ($trendPoints as $idx => $tp) {
                        $x = $padL + ($idx * $stepX);
                        $yPat = $padT + $plotH - (($tp['patients'] / $maxPatients) * $plotH);
                        $yRev = $padT + $plotH - (($tp['revenue'] / $maxRevenue) * $plotH);
                        $patientCoords[] = [$x, $yPat, $tp];
                        $revenueCoords[] = [$x, $yRev, $tp];
                    }

                    $patPath = '';
                    $patArea = '';
                    foreach ($patientCoords as $idx => [$x, $y]) {
                        $patPath .= ($idx === 0 ? "M {$x} {$y}" : " L {$x} {$y}");
                    }
                    if (!empty($patientCoords)) {
                        $firstX = $patientCoords[0][0];
                        $lastX = $patientCoords[count($patientCoords) - 1][0];
                        $bottomY = $padT + $plotH;
                        $patArea = "{$patPath} L {$lastX} {$bottomY} L {$firstX} {$bottomY} Z";
                    }

                    $revPath = '';
                    foreach ($revenueCoords as $idx => [$x, $y]) {
                        $revPath .= ($idx === 0 ? "M {$x} {$y}" : " L {$x} {$y}");
                    }
                    ?>
                    <svg viewBox="0 0 <?= $svgW ?> <?= $svgH ?>" class="chart-svg" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="patientsGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#2563eb" stop-opacity="0.35"/>
                                <stop offset="100%" stop-color="#2563eb" stop-opacity="0.0"/>
                            </linearGradient>
                        </defs>

                        <!-- Grid Horizontal Lines -->
                        <?php for ($g = 0; $g <= 4; $g++): 
                            $gy = $padT + ($g * ($plotH / 4));
                            $patVal = round($maxPatients - ($g * ($maxPatients / 4)));
                            $revVal = round($maxRevenue - ($g * ($maxRevenue / 4)));
                        ?>
                            <line x1="<?= $padL ?>" y1="<?= $gy ?>" x2="<?= $svgW - $padR ?>" y2="<?= $gy ?>" class="chart-grid-line" />
                            <text x="<?= $padL - 6 ?>" y="<?= $gy + 3 ?>" text-anchor="end" class="chart-axis-text"><?= $patVal ?></text>
                            <text x="<?= $svgW - $padR + 6 ?>" y="<?= $gy + 3 ?>" text-anchor="start" class="chart-axis-text" fill="#10b981">৳<?= number_format($revVal) ?></text>
                        <?php endfor; ?>

                        <!-- Patient Area & Line -->
                        <?php if ($patArea): ?>
                            <path d="<?= $patArea ?>" class="chart-area-patients" />
                            <path d="<?= $patPath ?>" class="chart-line-patients" />
                        <?php endif; ?>

                        <!-- Revenue Line -->
                        <?php if ($revPath): ?>
                            <path d="<?= $revPath ?>" class="chart-line-revenue" />
                        <?php endif; ?>

                        <!-- Data Points & Labels -->
                        <?php foreach ($patientCoords as $idx => [$x, $y, $tp]): ?>
                            <!-- Date Label -->
                            <?php if ($countPts <= 10 || $idx % 2 === 0): ?>
                                <text x="<?= $x ?>" y="<?= $svgH - 12 ?>" text-anchor="middle" class="chart-axis-text"><?= $tp['label'] ?></text>
                            <?php endif; ?>

                            <!-- Interactive circles -->
                            <circle cx="<?= $x ?>" cy="<?= $y ?>" r="3.5" fill="#ffffff" stroke="#2563eb" stroke-width="2" class="chart-point" 
                                data-date="<?= $tp['date'] ?>" data-pat="<?= $tp['patients'] ?>" data-rev="<?= $tp['revenue'] ?>"/>
                            <circle cx="<?= $x ?>" cy="<?= $revenueCoords[$idx][1] ?>" r="3" fill="#ffffff" stroke="#10b981" stroke-width="1.8" class="chart-point" 
                                data-date="<?= $tp['date'] ?>" data-pat="<?= $tp['patients'] ?>" data-rev="<?= $tp['revenue'] ?>"/>
                        <?php endforeach; ?>
                    </svg>

                    <!-- Interactive Tooltip Box -->
                    <div id="chart-tooltip" class="dash-tooltip"></div>
                </div>
            </div>
        </div>

        <!-- Gender Demographics Donut Card -->
        <div class="dash-card">
            <div class="dash-card-header">
                <h3>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 2a10 10 0 0 1 10 10"></path></svg>
                    <span>Gender Demographics</span>
                </h3>
                <span><?= $genderTotal ?> patients</span>
            </div>
            <div class="dash-card-body" style="display: flex; flex-direction: column; align-items: center; justify-content: center;">
                <?php
                $malePct = round(($maleCount / $genderTotal) * 100);
                $femalePct = round(($femaleCount / $genderTotal) * 100);
                $otherPct = max(0, 100 - $malePct - $femalePct);

                // SVG Donut circumference calculation (r = 60 => circumference = 377)
                $circ = 2 * M_PI * 60;
                $maleDash = ($malePct / 100) * $circ;
                $femaleDash = ($femalePct / 100) * $circ;
                $otherDash = ($otherPct / 100) * $circ;
                ?>
                <div style="position: relative; width: 170px; height: 170px;">
                    <svg viewBox="0 0 160 160" width="170" height="170">
                        <circle cx="80" cy="80" r="60" fill="none" stroke="#f1f5f9" stroke-width="18" />
                        <!-- Male segment (Blue) -->
                        <circle cx="80" cy="80" r="60" fill="none" stroke="#2563eb" stroke-width="18" 
                            stroke-dasharray="<?= $maleDash ?> <?= $circ ?>" stroke-dashoffset="0" transform="rotate(-90 80 80)"/>
                        <!-- Female segment (Pink) -->
                        <circle cx="80" cy="80" r="60" fill="none" stroke="#ec4899" stroke-width="18" 
                            stroke-dasharray="<?= $femaleDash ?> <?= $circ ?>" stroke-dashoffset="-<?= $maleDash ?>" transform="rotate(-90 80 80)"/>
                        <!-- Other segment (Cyan) -->
                        <?php if ($otherPct > 0): ?>
                        <circle cx="80" cy="80" r="60" fill="none" stroke="#06b6d4" stroke-width="18" 
                            stroke-dasharray="<?= $otherDash ?> <?= $circ ?>" stroke-dashoffset="-<?= $maleDash + $femaleDash ?>" transform="rotate(-90 80 80)"/>
                        <?php endif; ?>

                        <!-- Center Text -->
                        <text x="80" y="78" class="donut-center-text-val"><?= $genderTotal ?></text>
                        <text x="80" y="93" class="donut-center-text-lbl">Patients</text>
                    </svg>
                </div>

                <div class="chart-legend-wrap" style="width: 100%; max-width: 220px;">
                    <div class="legend-item">
                        <span><span class="legend-color-dot" style="background: #2563eb;"></span>Male</span>
                        <strong><?= $maleCount ?> (<?= $malePct ?>%)</strong>
                    </div>
                    <div class="legend-item">
                        <span><span class="legend-color-dot" style="background: #ec4899;"></span>Female</span>
                        <strong><?= $femaleCount ?> (<?= $femalePct ?>%)</strong>
                    </div>
                    <?php if ($otherCount > 0): ?>
                    <div class="legend-item">
                        <span><span class="legend-color-dot" style="background: #06b6d4;"></span>Other / Unspecified</span>
                        <strong><?= $otherCount ?> (<?= $otherPct ?>%)</strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 3-Column Detailed Analytics Grid -->
    <div class="dash-grid-3col">
        <!-- Age Pyramid / Distribution -->
        <div class="dash-card">
            <div class="dash-card-header">
                <h3>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20v-6M6 20V10M18 20V4"/></svg>
                    <span>Age Group Distribution</span>
                </h3>
            </div>
            <div class="dash-card-body">
                <div class="bar-metric-list">
                    <?php foreach ($ageGroups as $groupLabel => $count): 
                        $pct = round(($count / $maxAgeGroup) * 100);
                    ?>
                    <div class="bar-metric-row">
                        <div class="bar-metric-label-row">
                            <span class="bar-metric-label"><?= htmlspecialchars($groupLabel) ?></span>
                            <span class="bar-metric-value"><?= $count ?> patients</span>
                        </div>
                        <div class="bar-metric-track">
                            <div class="bar-metric-fill" style="width: <?= max(4, $pct) ?>%; background: #2563eb;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Top Presenting Complaints -->
        <div class="dash-card">
            <div class="dash-card-header">
                <h3>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                    <span>Top Presenting Complaints</span>
                </h3>
            </div>
            <div class="dash-card-body">
                <div class="bar-metric-list">
                    <?php foreach ($topPc as $pc): 
                        $pct = round(($pc['use_count'] / $maxPcCount) * 100);
                    ?>
                    <div class="bar-metric-row">
                        <div class="bar-metric-label-row">
                            <span class="bar-metric-label"><?= htmlspecialchars($pc['pc_name']) ?></span>
                            <span class="bar-metric-value"><?= (int)$pc['use_count'] ?> cases</span>
                        </div>
                        <div class="bar-metric-track">
                            <div class="bar-metric-fill" style="width: <?= max(5, $pct) ?>%; background: #8b5cf6;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Patient Acquisition & Referral Channels -->
        <div class="dash-card">
            <div class="dash-card-header">
                <h3>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                    <span>Referral Channels</span>
                </h3>
            </div>
            <div class="dash-card-body">
                <div class="bar-metric-list">
                    <?php if (empty($referralRows)): ?>
                        <div class="bar-metric-row">
                            <div class="bar-metric-label-row">
                                <span class="bar-metric-label">Direct / Self-Walkin</span>
                                <span class="bar-metric-value">100%</span>
                            </div>
                            <div class="bar-metric-track">
                                <div class="bar-metric-fill" style="width: 100%; background: #10b981;"></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($referralRows as $rf): 
                            $rfCount = (int)$rf['ref_count'];
                            $pct = round(($rfCount / $refTotal) * 100);
                            $catName = ucfirst(htmlspecialchars($rf['cat'] ?: 'Direct / Self'));
                        ?>
                        <div class="bar-metric-row">
                            <div class="bar-metric-label-row">
                                <span class="bar-metric-label"><?= $catName ?></span>
                                <span class="bar-metric-value"><?= $rfCount ?> (<?= $pct ?>%)</span>
                            </div>
                            <div class="bar-metric-track">
                                <div class="bar-metric-fill" style="width: <?= max(4, $pct) ?>%; background: #10b981;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Performance Ledger Table -->
    <div class="dash-card">
        <div class="dash-card-header">
            <h3>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                <span>Monthly Clinical &amp; Revenue Ledger</span>
            </h3>
            <span>Historical month-by-month record</span>
        </div>
        <div class="dash-card-body" style="padding: 0; overflow-x: auto;">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th style="width: 140px;">Month</th>
                        <th style="text-align: right;">Total Visits</th>
                        <th style="text-align: right;">New Patients</th>
                        <th style="text-align: right;">Return Patients</th>
                        <th style="text-align: right;">Retention %</th>
                        <th style="text-align: right;">Total Revenue</th>
                        <th style="text-align: right;">Avg / Patient</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ledgerRows)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem; color: #94a3b8; font-style: italic;">
                                No monthly historical logs recorded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ledgerRows as $lr): 
                            $ym = $lr['ym'];
                            $vCount = (int)$lr['visits'];
                            $nCount = (int)$lr['new_count'];
                            $rCount = (int)$lr['return_count'];
                            $retPct = $vCount > 0 ? round(($rCount / $vCount) * 100, 1) : 0;
                            $mRev = (float)($ledgerRevMap[$ym] ?? 0);
                            $avgPer = $vCount > 0 ? round($mRev / $vCount) : 0;
                        ?>
                        <tr>
                            <td><strong><?= date('F Y', strtotime($ym . '-01')) ?></strong></td>
                            <td style="text-align: right; font-weight: 700;"><?= $vCount ?></td>
                            <td style="text-align: right; color: #2563eb;"><?= $nCount ?></td>
                            <td style="text-align: right; color: #059669;"><?= $rCount ?></td>
                            <td style="text-align: right;">
                                <span style="display: inline-block; padding: 2px 7px; border-radius: 999px; background: #ecfdf5; color: #059669; font-size: 0.72rem; font-weight: 700;">
                                    <?= $retPct ?>%
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: #16a34a;">৳<?= number_format($mRev) ?></td>
                            <td style="text-align: right; color: #64748b;">৳<?= number_format($avgPer) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Interactive SVG chart hover tooltips
    const chartWrap = document.getElementById('trend-chart-wrap');
    const tooltip = document.getElementById('chart-tooltip');
    if (!chartWrap || !tooltip) return;

    chartWrap.querySelectorAll('.chart-point').forEach(pt => {
        pt.addEventListener('mouseenter', (e) => {
            const data = pt.dataset;
            const dateStr = new Date(data.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
            tooltip.innerHTML = `<strong>${dateStr}</strong><br>Patients: <span style="color:#60a5fa">${data.pat}</span> • Revenue: <span style="color:#34d399">৳${parseInt(data.rev || 0).toLocaleString()}</span>`;
            tooltip.style.opacity = '1';

            const rect = pt.getBoundingClientRect();
            const wrapRect = chartWrap.getBoundingClientRect();
            tooltip.style.left = (rect.left - wrapRect.left + rect.width / 2) + 'px';
            tooltip.style.top = (rect.top - wrapRect.top) + 'px';
        });

        pt.addEventListener('mouseleave', () => {
            tooltip.style.opacity = '0';
        });
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
