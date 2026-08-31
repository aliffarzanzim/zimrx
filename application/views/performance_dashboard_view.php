
<link rel="stylesheet" href="assets/css/pages/dashboard.css?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/css/pages/dashboard.css') ?>">

<div class="dash-container zrx-page-container">
    <!-- Page Header -->
    <header class="dash-header zrx-page-hero">
        <div class="dash-title-wrap zrx-page-hero-copy">
            <h1>
                <span>Performance &amp; Clinical Analytics</span>
            </h1>
            <p>Real-time insights on patient volume, retention rate, clinical presentations, and revenue trends.</p>
        </div>

        <div class="zrx-page-hero-actions">
            <div class="dash-period-group">
                <a href="performance_dashboard.php?period=30_days" class="dash-period-btn <?= $period === '30_days' ? 'active' : '' ?>">Last 30 Days</a>
                <a href="performance_dashboard.php?period=this_month" class="dash-period-btn <?= $period === 'this_month' ? 'active' : '' ?>">This Month</a>
                <a href="performance_dashboard.php?period=3_months" class="dash-period-btn <?= $period === '3_months' ? 'active' : '' ?>">Last 3 Months</a>
                <a href="performance_dashboard.php?period=this_year" class="dash-period-btn <?= $period === 'this_year' ? 'active' : '' ?>">This Year</a>
                <a href="performance_dashboard.php?period=all_time" class="dash-period-btn <?= $period === 'all_time' ? 'active' : '' ?>">All Time</a>
            </div>
        </div>
    </header>

    <!-- Executive KPI Summary Cards -->
    <div class="dash-kpi-grid">
        <div class="dash-kpi-card">
            <div class="dash-kpi-top">
                <span class="dash-kpi-title">Total Consultations</span>
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
            </div>
            <div class="dash-kpi-num"><?= $returnRate ?>%</div>
            <div class="dash-kpi-footer">
                <span>Follow-up &amp; revisits share</span>
            </div>
        </div>

        <div class="dash-kpi-card">
            <div class="dash-kpi-top">
                <span class="dash-kpi-title">Daily Average Footfall</span>
            </div>
            <div class="dash-kpi-num"><?= $avgDailyFootfall ?> <span class="dash-kpi-unit">pts/day</span></div>
            <div class="dash-kpi-footer">
                <span>Across <?= $activeDays ?> clinic working days</span>
            </div>
        </div>

        <div class="dash-kpi-card">
            <div class="dash-kpi-top">
                <span class="dash-kpi-title">Avg Revenue Per Patient</span>
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
                <div class="dash-chart-legend">
                    <span class="dash-legend-indicator indicator-blue">
                        <span class="dash-legend-line line-blue"></span>
                        Patients (left)
                    </span>
                    <span class="dash-legend-indicator indicator-green">
                        <span class="dash-legend-line line-green"></span>
                        Revenue ৳ (right)
                    </span>
                </div>
            </div>
            <div class="dash-card-body dash-card-body-chart">
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
            <div class="dash-card-body dash-card-body-centered">
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
                <div class="donut-chart-box">
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

                <div class="chart-legend-wrap chart-legend-narrow">
                    <div class="legend-item">
                        <span><span class="legend-color-dot legend-dot-male"></span>Male</span>
                        <strong><?= $maleCount ?> (<?= $malePct ?>%)</strong>
                    </div>
                    <div class="legend-item">
                        <span><span class="legend-color-dot legend-dot-female"></span>Female</span>
                        <strong><?= $femaleCount ?> (<?= $femalePct ?>%)</strong>
                    </div>
                    <?php if ($otherCount > 0): ?>
                    <div class="legend-item">
                        <span><span class="legend-color-dot legend-dot-other"></span>Other / Unspecified</span>
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
                            <div class="bar-metric-fill bar-fill-blue" style="width: <?= max(4, $pct) ?>%;"></div>
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
                    <?php if (empty($topPc)): ?>
                        <div class="dash-empty-state">
                            No presenting complaints recorded yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($topPc as $pc):
                            $pct = round(($pc['use_count'] / $maxPcCount) * 100);
                        ?>
                        <div class="bar-metric-row">
                            <div class="bar-metric-label-row">
                                <span class="bar-metric-label"><?= htmlspecialchars($pc['pc_name']) ?></span>
                                <span class="bar-metric-value"><?= (int)$pc['use_count'] ?> cases</span>
                            </div>
                            <div class="bar-metric-track">
                                <div class="bar-metric-fill bar-fill-purple" style="width: <?= max(5, $pct) ?>%;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
                                <div class="bar-metric-fill bar-fill-green" style="width: 100%;"></div>
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
                                <div class="bar-metric-fill bar-fill-green" style="width: <?= max(4, $pct) ?>%;"></div>
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
        <div class="dash-card-body dash-card-body-table">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th class="th-month">Month</th>
                        <th class="zrx-ta-r">Total Visits</th>
                        <th class="zrx-ta-r">New Patients</th>
                        <th class="zrx-ta-r">Return Patients</th>
                        <th class="zrx-ta-r">Retention %</th>
                        <th class="zrx-ta-r">Total Revenue</th>
                        <th class="zrx-ta-r">Avg / Patient</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ledgerRows)): ?>
                        <tr>
                            <td colspan="7" class="td-empty-table">
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
                            <td class="td-bold-num"><?= $vCount ?></td>
                            <td class="td-blue-num"><?= $nCount ?></td>
                            <td class="td-green-num"><?= $rCount ?></td>
                            <td class="zrx-ta-r">
                                <span class="dash-retention-pill">
                                    <?= $retPct ?>%
                                </span>
                            </td>
                            <td class="td-revenue-num">৳<?= number_format($mRev) ?></td>
                            <td class="td-muted-num">৳<?= number_format($avgPer) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="assets/js/pages/performance_dashboard.js?v=<?= file_exists(ZIMRX_PUBLIC_DIR . '/assets/js/pages/performance_dashboard.js') ? filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/pages/performance_dashboard.js') : '1' ?>" defer></script>
