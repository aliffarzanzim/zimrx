<?php
declare(strict_types=1);

if (!function_exists('appointment_page_h')) {
    function appointment_page_h(?string $value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('appointment_page_iso_to_dmy')) {
    function appointment_page_iso_to_dmy(string $date): string {
        return AppointmentService::isoToDmy($date);
    }
}

?>
<main class="appointments-page zrx-page-container">
<?php require ZIMRX_BASE_DIR . '/views/components/section_navigation.php'; ?>


        <section class="appointment-hero appointment-hero-compact">
            <div>
                <p class="eyebrow">Patient Revisit Hub</p>
                <h1>Follow-up Patient Tracker</h1>
                <p class="appointment-subtitle">Monitor scheduled revisit consultations from previous prescriptions and queue them for today with 1-click.</p>
            </div>
        </section>

        <!-- KPI Summary Cards -->
        <div class="followup-kpi-grid">
            <div class="followup-kpi-card" onclick="filterFollowupStatus('all', document.querySelector('.followup-chip-btn[data-filter=all]'))">
                <div class="kpi-icon-wrap zrx-kpi-blue">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div>
                    <span class="kpi-val" id="kpi-all"><?= $totalFollowups ?></span>
                    <span class="kpi-lbl">Total Scheduled</span>
                </div>
            </div>

            <div class="followup-kpi-card" onclick="filterFollowupStatus('due', document.querySelector('.followup-chip-btn[data-filter=due]'))">
                <div class="kpi-icon-wrap zrx-kpi-green">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                </div>
                <div>
                    <span class="kpi-val zrx-c-success" id="kpi-due"><?= $dueTodayCount ?></span>
                    <span class="kpi-lbl">Due Today</span>
                </div>
            </div>

            <div class="followup-kpi-card" onclick="filterFollowupStatus('upcoming', document.querySelector('.followup-chip-btn[data-filter=upcoming]'))">
                <div class="kpi-icon-wrap icon-sky">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <div>
                    <span class="kpi-val val-sky" id="kpi-upcoming"><?= $upcomingCount ?></span>
                    <span class="kpi-lbl">Upcoming Revisit</span>
                </div>
            </div>

            <div class="followup-kpi-card" onclick="filterFollowupStatus('overdue', document.querySelector('.followup-chip-btn[data-filter=overdue]'))">
                <div class="kpi-icon-wrap icon-amber">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <div>
                    <span class="kpi-val zrx-c-warning" id="kpi-overdue"><?= $overdueCount ?></span>
                    <span class="kpi-lbl">Overdue Follow-ups</span>
                </div>
            </div>
        </div>

        <!-- Filter and Search Bar -->
        <div class="followup-filter-bar">
            <div class="followup-filter-chips">
                <button type="button" class="followup-chip-btn active" data-filter="all" onclick="filterFollowupStatus('all', this)">All (<?= count($initialFollowups) ?>)</button>
                <button type="button" class="followup-chip-btn" data-filter="due" onclick="filterFollowupStatus('due', this)">Due Today (<?= $dueTodayCount ?>)</button>
                <button type="button" class="followup-chip-btn" data-filter="upcoming" onclick="filterFollowupStatus('upcoming', this)">Upcoming (<?= $upcomingCount ?>)</button>
                <button type="button" class="followup-chip-btn" data-filter="overdue" onclick="filterFollowupStatus('overdue', this)">Overdue (<?= $overdueCount ?>)</button>
            </div>
            <input type="text" id="followup-search-input" class="followup-search-input" placeholder="Search by name, reg no, mobile..." oninput="filterFollowupStatus(getCurrentFollowupFilter())">
        </div>

        <!-- Follow-ups Table Card -->
        <section class="appointment-list-card">
            <div class="appointment-list-header">
                <div>
                    <h2>Scheduled Revisit List</h2>
                    <p id="followup-count-label"><?= count($initialFollowups) ?> scheduled follow-up<?= count($initialFollowups) === 1 ? '' : 's' ?></p>
                </div>
                <button type="button" class="btn btn-secondary btn-sm btn-print-action" onclick="window.print()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    Print Call Sheet
                </button>
            </div>
            <div class="appointment-table-wrap">
                <table class="appointment-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Status</th>
                            <th>Reg No</th>
                            <th>Patient Details</th>
                            <th>Contact</th>
                            <th>Last Prescribed</th>
                            <th>Follow-up Due</th>
                            <th>Estimated Fee</th>
                        </tr>
                    </thead>
                    <tbody id="followup-table-body">
                        <?php if (empty($initialFollowups)): ?>
                        <tr><td colspan="8" class="empty-appointments">No follow-up records found for this doctor.</td></tr>
                        <?php else: ?>
                            <?php foreach ($initialFollowups as $f): ?>
                                <?php
                                $nextVisitRaw = trim((string)$f['next_visit']);
                                $lastVisitRaw = trim((string)$f['last_visit_date']);
                                $todayStr = date('Y-m-d');
                                $dueStatus = 'upcoming';
                                $dueBadgeClass = 'status-badge-upcoming';
                                $dueText = 'Upcoming';

                                $dtNext = DateTime::createFromFormat('Y-m-d', substr($nextVisitRaw, 0, 10));
                                $dtToday = DateTime::createFromFormat('Y-m-d', $todayStr);
                                $displayNext = $dtNext ? $dtNext->format('d/m/Y') : $nextVisitRaw;
                                $displayLast = $lastVisitRaw ? appointment_page_iso_to_dmy($lastVisitRaw) : '-';

                                $daysAgo = 999;
                                if ($lastVisitRaw) {
                                    $dtLast = DateTime::createFromFormat('Y-m-d', $lastVisitRaw);
                                    if ($dtLast && $dtToday) {
                                        $daysAgo = (int)$dtLast->diff($dtToday)->format('%r%a');
                                    }
                                }

                                $revisitValidDays = (int)($initialSettings['revisit_validity_days'] ?? 60);
                                $isRevisitValid = $daysAgo >= 0 && $daysAgo <= $revisitValidDays;
                                $estFee = $isRevisitValid ? (float)($initialSettings['revisit_fee'] ?? 400) : (float)($initialSettings['visit_fee'] ?? 500);

                                if ($dtNext && $dtToday) {
                                    $diff = (int)$dtToday->diff($dtNext)->format('%r%a');
                                    if ($diff === 0) {
                                        $dueStatus = 'due';
                                        $dueBadgeClass = 'status-badge-due';
                                        $dueText = 'Due Today';
                                    } elseif ($diff < 0) {
                                        $dueStatus = 'overdue';
                                        $dueBadgeClass = 'status-badge-overdue';
                                        $dueText = abs($diff) . 'd overdue';
                                    } else {
                                        $dueStatus = 'upcoming';
                                        $dueBadgeClass = 'status-badge-upcoming';
                                        $dueText = 'In ' . $diff . 'd';
                                    }
                                }

                                $fDataJson = json_encode([
                                    'visit_id' => $f['visit_id'],
                                    'patient_id' => $f['patient_id'],
                                    'reg_no' => $f['reg_no'],
                                    'patient_name' => $f['patient_name'],
                                    'mobile' => $f['mobile'],
                                    'age' => $f['age'],
                                    'gender' => $f['gender'],
                                    'address' => $f['address'],
                                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

                                $cleanMobile = preg_replace('/\D/', '', (string)$f['mobile']);
                                $waPhone = (strlen($cleanMobile) === 11 && strpos($cleanMobile, '01') === 0) ? '88' . $cleanMobile : $cleanMobile;
                                $waText = "Dear " . ($f['patient_name'] ?? 'Patient') . ", reminder: your scheduled follow-up consultation is on " . $displayNext . ". Please call or visit us for appointment booking.";
                                $waUrl = "https://api.whatsapp.com/send?phone=" . rawurlencode($waPhone) . "&text=" . rawurlencode($waText);
                                ?>
                                <tr class="followup-row" data-status="<?= $dueStatus ?>">
                                    <td class="queue-start-cell">
                                        <button type="button" class="queue-start-btn btn-quick-queue" onclick='quickQueueFollowup(<?= $fDataJson ?>)'>+ Queue Today</button>
                                        <a class="queue-start-btn prescribe-btn btn-quick-prescribe" href="prescription.php?patient_id=<?= urlencode((string)$f['patient_id']) ?>&reg_no=<?= urlencode((string)$f['reg_no']) ?>">Prescribe</a>
                                    </td>
                                    <td>
                                        <span class="<?= $dueBadgeClass ?>"><?= htmlspecialchars($dueText) ?></span>
                                    </td>
                                    <td class="reg-cell"><strong><?= htmlspecialchars((string)$f['reg_no']) ?></strong></td>
                                    <td>
                                        <strong class="followup-patient-name"><?= htmlspecialchars((string)$f['patient_name']) ?></strong>
                                        <?php if (!empty($f['age']) || !empty($f['gender'])): ?>
                                        <span class="followup-patient-meta"><?= htmlspecialchars(trim(($f['age'] ? $f['age'] . 'y ' : '') . ($f['gender'] ?: ''))) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($f['address'])): ?>
                                        <span class="followup-patient-phone"><?= htmlspecialchars((string)$f['address']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($f['mobile'])): ?>
                                            <div class="followup-date-row">
                                                <a href="tel:<?= htmlspecialchars((string)$f['mobile']) ?>" class="quick-contact-link" title="Call patient">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l2.28-2.28a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                                    <?= htmlspecialchars((string)$f['mobile']) ?>
                                                </a>
                                                <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" rel="noopener noreferrer" class="whatsapp-reminder-btn" title="Send WhatsApp Revisit Reminder">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.699c.983.537 1.776.84 2.796.84 3.185 0 5.77-2.587 5.77-5.766.001-3.187-2.575-5.77-5.77-5.772zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.694.072-2.155-.544-1.859-.785-3.045-2.673-3.137-2.797-.093-.124-.755-1.006-.755-1.921s.478-1.366.647-1.554c.17-.188.371-.235.495-.235.124 0 .247.002.355.007.113.006.264-.043.413.315.154.371.526 1.282.573 1.376.046.093.077.202.015.326-.062.124-.093.202-.185.31-.093.108-.196.241-.28.324-.093.093-.19.194-.082.38.109.186.481.794 1.033 1.285.711.634 1.31.83 1.496.923.186.093.294.077.402-.047.109-.124.465-.542.589-.728.124-.186.248-.155.418-.093.17.062 1.082.51 1.268.603.186.093.309.139.355.216.047.078.047.45-.097.855z"/></svg>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="followup-bullet">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span><?= htmlspecialchars($displayLast) ?></span>
                                        <?php if ($daysAgo < 999): ?>
                                        <span class="followup-days-ago"><?= $daysAgo ?>d ago</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong class="followup-next-date"><?= htmlspecialchars($displayNext) ?></strong></td>
                                    <td>
                                        <?php if ($isRevisitValid): ?>
                                            <span class="followup-badge-active">Revisit: ৳<?= (int)$estFee ?></span>
                                        <?php else: ?>
                                            <span class="followup-badge-scheduled">Standard: ৳<?= (int)$estFee ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

</main>
<script src="assets/js/pages/followups.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/pages/followups.js') ?>"></script>
