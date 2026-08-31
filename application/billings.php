<?php
$page_title = "Billings & Financials - ZimRx";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/db.php';

$pdo = DbConnections::userdata();
$doctorId = max(1, (int)(function_exists('current_user_doctor_id') ? current_user_doctor_id() : 1));

// Sync missing appointment payments into zimrx_payments so historical data is accounted for
try {
    $syncAppointments = $pdo->query(
        "SELECT a.id, a.patient_id, a.doctor_id, a.visit_fee, a.discount, a.discount_note, a.paid_amount, a.appointment_date
         FROM zimrx_appointments a
         LEFT JOIN zimrx_payments p ON p.appointment_id = a.id
         WHERE p.id IS NULL AND (a.visit_fee > 0 OR a.paid_amount > 0)"
    )->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($syncAppointments)) {
        $insP = $pdo->prepare(
            "INSERT INTO zimrx_payments 
             (appointment_id, patient_id, doctor_id, visit_fee, discount, discount_note, paid_amount, payment_method, payment_status, service_type, created_at, updated_at)
             VALUES (:aid, :pid, :doc, :fee, :disc, :dnote, :paid, 'Cash', :status, 'Consultation', :c_at, :u_at)"
        );
        foreach ($syncAppointments as $sa) {
            $fee = (float)($sa['visit_fee'] ?? 0);
            $disc = (float)($sa['discount'] ?? 0);
            $paid = (float)($sa['paid_amount'] ?? 0);
            $net = max(0, $fee - $disc);
            $st = ($net > 0 && $paid >= $net) ? 'paid' : ($paid > 0 ? 'partial' : 'due');
            $createdAt = !empty($sa['appointment_date']) ? $sa['appointment_date'] . ' 10:00:00' : date('Y-m-d H:i:s');
            $insP->execute([
                'aid' => $sa['id'],
                'pid' => (int)$sa['patient_id'],
                'doc' => (int)($sa['doctor_id'] ?: $doctorId),
                'fee' => $fee,
                'disc' => $disc,
                'dnote' => (string)($sa['discount_note'] ?? ''),
                'paid' => $paid,
                'status' => $st,
                'c_at' => $createdAt,
                'u_at' => $createdAt
            ]);
            $newPid = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE zimrx_payments SET receipt_no = :rno WHERE id = :id")->execute([
                'rno' => sprintf('INV-%s-%04d', substr($createdAt, 0, 4), $newPid),
                'id' => $newPid
            ]);
        }
    }
} catch (Throwable $e) {
    // Non-fatal sync
}

// Parse filters
$preset = trim((string)($_GET['preset'] ?? 'this_month'));
$fromDate = trim((string)($_GET['from_date'] ?? ''));
$toDate = trim((string)($_GET['to_date'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$methodFilter = trim((string)($_GET['method'] ?? 'all'));
$searchQuery = trim((string)($_GET['q'] ?? ''));

$today = date('Y-m-d');
if ($preset === 'today') {
    $fromDate = $today;
    $toDate = $today;
} elseif ($preset === 'yesterday') {
    $fromDate = date('Y-m-d', strtotime('-1 day'));
    $toDate = date('Y-m-d', strtotime('-1 day'));
} elseif ($preset === 'this_week') {
    $fromDate = date('Y-m-d', strtotime('monday this week'));
    $toDate = $today;
} elseif ($preset === 'this_month') {
    $fromDate = date('Y-m-01');
    $toDate = $today;
} elseif ($preset === 'last_month') {
    $fromDate = date('Y-m-01', strtotime('first day of last month'));
    $toDate = date('Y-m-t', strtotime('last day of last month'));
} elseif (!$fromDate && !$toDate) {
    $fromDate = date('Y-m-01');
    $toDate = $today;
    $preset = 'this_month';
}

// Build query
$where = ["p.doctor_id = :doc"];
$params = ['doc' => $doctorId];

if ($fromDate) {
    $where[] = "date(p.created_at) >= :from_date";
    $params['from_date'] = $fromDate;
}
if ($toDate) {
    $where[] = "date(p.created_at) <= :to_date";
    $params['to_date'] = $toDate;
}
if ($statusFilter && $statusFilter !== 'all') {
    if ($statusFilter === 'paid') {
        $where[] = "p.payment_status = 'paid'";
    } elseif ($statusFilter === 'due') {
        $where[] = "(p.payment_status = 'due' OR p.payment_status = 'partial')";
    } elseif ($statusFilter === 'discounted') {
        $where[] = "p.discount > 0";
    }
}
if ($methodFilter && $methodFilter !== 'all') {
    $where[] = "p.payment_method LIKE :method";
    $params['method'] = "%{$methodFilter}%";
}
if ($searchQuery) {
    $where[] = "(pat.full_name LIKE :sq OR pat.mobile LIKE :sq OR pat.reg_no LIKE :sq OR p.receipt_no LIKE :sq OR app.patient_name LIKE :sq)";
    $params['sq'] = "%{$searchQuery}%";
}

$whereClause = implode(' AND ', $where);

// Fetch transactions
$query = "SELECT 
            p.*,
            coalesce(nullif(pat.full_name, ''), app.patient_name, 'Patient #' || p.patient_id) AS patient_name,
            coalesce(nullif(pat.reg_no, ''), app.reg_no, '') AS reg_no,
            coalesce(nullif(pat.mobile, ''), app.mobile, '') AS mobile,
            coalesce(pat.age, app.age, '') AS age,
            coalesce(pat.gender, app.gender, '') AS gender
          FROM zimrx_payments p
          LEFT JOIN zimrx_patients pat ON pat.id = p.patient_id
          LEFT JOIN zimrx_appointments app ON app.id = p.appointment_id
          WHERE {$whereClause}
          ORDER BY p.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate overall summary metrics
$todayStmt = $pdo->prepare(
    "SELECT 
        COALESCE(SUM(paid_amount), 0) AS today_collected,
        COALESCE(SUM(visit_fee), 0) AS today_invoiced,
        COALESCE(SUM(discount), 0) AS today_discount,
        COUNT(*) AS today_count
     FROM zimrx_payments 
     WHERE doctor_id = :doc AND date(created_at) = :today"
);
$todayStmt->execute(['doc' => $doctorId, 'today' => $today]);
$todayMetrics = $todayStmt->fetch(PDO::FETCH_ASSOC);

$monthStmt = $pdo->prepare(
    "SELECT 
        COALESCE(SUM(paid_amount), 0) AS month_collected,
        COALESCE(SUM(visit_fee), 0) AS month_invoiced,
        COALESCE(SUM(discount), 0) AS month_discount,
        COUNT(*) AS month_count
     FROM zimrx_payments 
     WHERE doctor_id = :doc AND date(created_at) >= :m_start AND date(created_at) <= :m_end"
);
$monthStmt->execute([
    'doc' => $doctorId,
    'm_start' => date('Y-m-01'),
    'm_end' => $today
]);
$monthMetrics = $monthStmt->fetch(PDO::FETCH_ASSOC);

// Outstanding Due across all time
$dueStmt = $pdo->prepare(
    "SELECT 
        COALESCE(SUM(MAX(0, (visit_fee - discount) - paid_amount)), 0) AS total_due,
        COUNT(CASE WHEN (visit_fee - discount) > paid_amount THEN 1 END) AS due_count
     FROM zimrx_payments 
     WHERE doctor_id = :doc"
);
$dueStmt->execute(['doc' => $doctorId]);
$dueMetrics = $dueStmt->fetch(PDO::FETCH_ASSOC);

// Filtered list aggregates
$filteredCollected = 0.0;
$filteredInvoiced = 0.0;
$filteredDiscount = 0.0;
$filteredDue = 0.0;
foreach ($transactions as $t) {
    $vf = (float)($t['visit_fee'] ?? 0);
    $dc = (float)($t['discount'] ?? 0);
    $pd = (float)($t['paid_amount'] ?? 0);
    $net = max(0, $vf - $dc);
    $due = max(0, $net - $pd);
    $filteredCollected += $pd;
    $filteredInvoiced += $vf;
    $filteredDiscount += $dc;
    $filteredDue += $due;
}

require_once __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="assets/css/billings.css?v=<?= filemtime(__DIR__ . '/assets/css/billings.css') ?>">

<div class="billings-container">
    <!-- Page Header -->
    <div class="billings-header">
        <div class="billings-title-wrap">
            <h1>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" color="#2563eb"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                <span>Billings &amp; Financials</span>
            </h1>
            <p>Track patient consultation fees, payments, discounts, outstanding dues, and print invoices.</p>
        </div>
        <div class="billings-header-actions no-print">
            <button type="button" class="btn-billing-outline" id="btn-export-csv" title="Export transactions to CSV spreadsheet">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                <span>Export CSV</span>
            </button>
            <button type="button" class="btn-billing-outline" onclick="window.print()" title="Print current transaction report">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                <span>Print Report</span>
            </button>
            <button type="button" class="btn-billing-primary" id="btn-new-billing">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                <span>+ New Transaction</span>
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="billing-kpi-grid">
        <div class="billing-kpi-card">
            <div class="billing-kpi-head">
                <span class="billing-kpi-label">Today's Collection</span>
                <div class="billing-kpi-icon kpi-icon-green">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
            </div>
            <div class="billing-kpi-value text-money-paid">৳<?= number_format((float)$todayMetrics['today_collected'], 0) ?></div>
            <div class="billing-kpi-sub">
                <span>Invoiced: <strong>৳<?= number_format((float)$todayMetrics['today_invoiced'], 0) ?></strong></span>
                <span>&bull;</span>
                <span><?= (int)$todayMetrics['today_count'] ?> visits</span>
            </div>
        </div>

        <div class="billing-kpi-card">
            <div class="billing-kpi-head">
                <span class="billing-kpi-label">This Month Collection</span>
                <div class="billing-kpi-icon kpi-icon-blue">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                </div>
            </div>
            <div class="billing-kpi-value">৳<?= number_format((float)$monthMetrics['month_collected'], 0) ?></div>
            <div class="billing-kpi-sub">
                <span>Discounts: <strong>৳<?= number_format((float)$monthMetrics['month_discount'], 0) ?></strong></span>
                <span>&bull;</span>
                <span><?= (int)$monthMetrics['month_count'] ?> invoices</span>
            </div>
        </div>

        <div class="billing-kpi-card">
            <div class="billing-kpi-head">
                <span class="billing-kpi-label">Total Outstanding Due</span>
                <div class="billing-kpi-icon kpi-icon-amber">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                </div>
            </div>
            <div class="billing-kpi-value text-money-due">৳<?= number_format((float)$dueMetrics['total_due'], 0) ?></div>
            <div class="billing-kpi-sub">
                <a href="billings.php?status=due&preset=all" style="color: #d97706; font-weight: 600; text-decoration: none;">
                    <?= (int)$dueMetrics['due_count'] ?> pending accounts &rarr;
                </a>
            </div>
        </div>

        <div class="billing-kpi-card">
            <div class="billing-kpi-head">
                <span class="billing-kpi-label">Filtered Net Revenue</span>
                <div class="billing-kpi-icon kpi-icon-purple">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                </div>
            </div>
            <div class="billing-kpi-value">৳<?= number_format($filteredCollected, 0) ?></div>
            <div class="billing-kpi-sub">
                <span><?= count($transactions) ?> records in selected view</span>
            </div>
        </div>
    </div>

    <!-- Filter Toolbar -->
    <div class="billing-filter-panel no-print">
        <form method="GET" action="billings.php" id="billing-filter-form">
            <div class="billing-filter-row-top">
                <div class="billing-preset-group">
                    <a href="billings.php?preset=today" class="billing-preset-btn <?= $preset === 'today' ? 'active' : '' ?>">Today</a>
                    <a href="billings.php?preset=yesterday" class="billing-preset-btn <?= $preset === 'yesterday' ? 'active' : '' ?>">Yesterday</a>
                    <a href="billings.php?preset=this_week" class="billing-preset-btn <?= $preset === 'this_week' ? 'active' : '' ?>">This Week</a>
                    <a href="billings.php?preset=this_month" class="billing-preset-btn <?= $preset === 'this_month' ? 'active' : '' ?>">This Month</a>
                    <a href="billings.php?preset=last_month" class="billing-preset-btn <?= $preset === 'last_month' ? 'active' : '' ?>">Last Month</a>
                </div>

                <div style="display: flex; align-items: center; gap: 8px;">
                    <label style="font-size: 0.78rem; font-weight: 600; color: #64748b;">Custom Range:</label>
                    <input type="text" name="from_date" id="filter-from-date" class="billing-date-input" placeholder="From Date" value="<?= htmlspecialchars($fromDate) ?>">
                    <span style="color: #94a3b8;">&ndash;</span>
                    <input type="text" name="to_date" id="filter-to-date" class="billing-date-input" placeholder="To Date" value="<?= htmlspecialchars($toDate) ?>">
                    <button type="submit" class="btn-billing-primary" style="padding: 0.45rem 0.8rem; font-size: 0.8rem;">Apply</button>
                </div>
            </div>

            <div class="billing-filter-row-inputs" style="margin-top: 0.75rem;">
                <div class="billing-search-box">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" color="#94a3b8"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="q" placeholder="Search patient name, phone, reg no, or invoice #..." value="<?= htmlspecialchars($searchQuery) ?>" autocomplete="off">
                </div>

                <select name="status" class="billing-select" onchange="this.form.submit()">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Fully Paid</option>
                    <option value="due" <?= $statusFilter === 'due' ? 'selected' : '' ?>>Due / Partial</option>
                    <option value="discounted" <?= $statusFilter === 'discounted' ? 'selected' : '' ?>>Discounted</option>
                </select>

                <select name="method" class="billing-select" onchange="this.form.submit()">
                    <option value="all" <?= $methodFilter === 'all' ? 'selected' : '' ?>>All Payment Methods</option>
                    <option value="Cash" <?= $methodFilter === 'Cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="Card" <?= $methodFilter === 'Card' ? 'selected' : '' ?>>Card</option>
                    <option value="bKash" <?= $methodFilter === 'bKash' ? 'selected' : '' ?>>bKash / MFS</option>
                    <option value="Nagad" <?= $methodFilter === 'Nagad' ? 'selected' : '' ?>>Nagad</option>
                </select>

                <?php if ($fromDate || $toDate || $searchQuery || $statusFilter !== 'all' || $methodFilter !== 'all'): ?>
                    <a href="billings.php?preset=this_month" class="btn-billing-outline" style="padding: 0.4rem 0.75rem; font-size: 0.8rem; color: #ef4444;">Clear Filters</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Data Table Card -->
    <div class="billing-table-card">
        <div class="billing-table-head-bar">
            <span>SHOWING <?= count($transactions) ?> TRANSACTIONS</span>
            <span>Collected in View: <strong style="color: #16a34a;">৳<?= number_format($filteredCollected, 0) ?></strong> &bull; Due: <strong style="color: #dc2626;">৳<?= number_format($filteredDue, 0) ?></strong></span>
        </div>

        <div class="billing-table-wrap">
            <table class="billing-table" id="transactions-table">
                <thead>
                    <tr>
                        <th style="width: 110px;">Date &amp; Time</th>
                        <th style="width: 120px;">Receipt #</th>
                        <th>Patient Details</th>
                        <th style="width: 120px;">Service</th>
                        <th style="width: 90px; text-align: right;">Gross Fee</th>
                        <th style="width: 85px; text-align: right;">Discount</th>
                        <th style="width: 95px; text-align: right;">Net Payable</th>
                        <th style="width: 95px; text-align: right;">Paid</th>
                        <th style="width: 95px; text-align: right;">Due</th>
                        <th style="width: 90px; text-align: center;">Method</th>
                        <th style="width: 80px; text-align: center;" class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 2.5rem; color: #94a3b8; font-style: italic;">
                                No billing records match the selected date and filter criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): 
                            $fee = (float)($t['visit_fee'] ?? 0);
                            $disc = (float)($t['discount'] ?? 0);
                            $paid = (float)($t['paid_amount'] ?? 0);
                            $net = max(0, $fee - $disc);
                            $due = max(0, $net - $paid);
                            $isPaid = ($net > 0 && $paid >= $net);
                            $receiptNo = $t['receipt_no'] ?: sprintf('INV-%s-%04d', date('Y', strtotime($t['created_at'])), $t['id']);
                        ?>
                        <tr data-payment-id="<?= (int)$t['id'] ?>">
                            <td style="font-size: 0.78rem; color: #64748b; white-space: nowrap;">
                                <?= date('d M Y', strtotime($t['created_at'])) ?><br>
                                <span style="font-size: 0.72rem;"><?= date('h:i A', strtotime($t['created_at'])) ?></span>
                            </td>
                            <td>
                                <strong style="font-size: 0.8rem; color: #2563eb; font-feature-settings: 'tnum';">
                                    <?= htmlspecialchars($receiptNo) ?>
                                </strong>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #0f172a;">
                                    <?= htmlspecialchars($t['patient_name']) ?>
                                </div>
                                <div style="font-size: 0.75rem; color: #64748b; display: flex; gap: 8px;">
                                    <?php if (!empty($t['reg_no'])): ?>
                                        <span>Reg: <?= htmlspecialchars($t['reg_no']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($t['mobile'])): ?>
                                        <span>📱 <?= htmlspecialchars($t['mobile']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($t['gender']) || !empty($t['age'])): ?>
                                        <span>(<?= htmlspecialchars(trim(($t['age'] ?? '') . ' ' . ($t['gender'] ?? ''))) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 0.78rem; font-weight: 500;">
                                    <?= htmlspecialchars($t['service_type'] ?: 'Consultation') ?>
                                </span>
                            </td>
                            <td style="text-align: right;" class="text-money">৳<?= number_format($fee, 0) ?></td>
                            <td style="text-align: right; color: #d97706;" class="text-money">
                                <?= $disc > 0 ? '৳' . number_format($disc, 0) : '-' ?>
                                <?php if (!empty($t['discount_note'])): ?>
                                    <div style="font-size: 0.68rem; color: #94a3b8; font-weight: 400;"><?= htmlspecialchars($t['discount_note']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; font-weight: 700;" class="text-money">৳<?= number_format($net, 0) ?></td>
                            <td style="text-align: right; color: #16a34a; font-weight: 700;" class="text-money">৳<?= number_format($paid, 0) ?></td>
                            <td style="text-align: right;">
                                <?php if ($due <= 0): ?>
                                    <span class="badge-status badge-paid">Paid</span>
                                <?php else: ?>
                                    <span class="badge-status badge-due">৳<?= number_format($due, 0) ?> Due</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge-method"><?= htmlspecialchars($t['payment_method'] ?: 'Cash') ?></span>
                            </td>
                            <td style="text-align: center;" class="no-print">
                                <div class="billing-actions">
                                    <button type="button" class="btn-action-icon btn-edit-payment" title="Collect / Edit Payment" 
                                        data-id="<?= (int)$t['id'] ?>"
                                        data-receipt="<?= htmlspecialchars($receiptNo) ?>"
                                        data-patient="<?= htmlspecialchars($t['patient_name']) ?>"
                                        data-fee="<?= $fee ?>"
                                        data-disc="<?= $disc ?>"
                                        data-disc-note="<?= htmlspecialchars($t['discount_note'] ?? '') ?>"
                                        data-paid="<?= $paid ?>"
                                        data-method="<?= htmlspecialchars($t['payment_method'] ?: 'Cash') ?>"
                                        data-notes="<?= htmlspecialchars($t['notes'] ?? '') ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </button>
                                    <button type="button" class="btn-action-icon btn-print-receipt" title="Print Invoice / Receipt" data-id="<?= (int)$t['id'] ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL: Collect / Edit Payment
     ========================================================================= -->
<div class="billing-modal" id="modal-edit-payment" hidden style="display: none;">
    <div class="billing-modal-backdrop" data-close-modal></div>
    <div class="billing-modal-panel" role="dialog" aria-modal="true">
        <div class="billing-modal-header">
            <div>
                <h3 id="edit-modal-title">Collect / Update Payment</h3>
                <span id="edit-modal-subtitle" style="font-size: 0.78rem; color: #64748b;"></span>
            </div>
            <button type="button" class="billing-modal-close" data-close-modal>&times;</button>
        </div>
        <form id="form-edit-payment">
            <input type="hidden" name="payment_id" id="edit-payment-id">
            <div class="billing-modal-body">
                <div style="background: #f8fafc; padding: 0.75rem; border-radius: 8px; display: flex; justify-content: space-between; font-size: 0.85rem;">
                    <div>Gross Consultation Fee: <strong id="edit-display-fee">৳0</strong></div>
                    <div>Net Payable: <strong id="edit-display-net" style="color: #2563eb;">৳0</strong></div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <div class="billing-form-group">
                        <label>Discount Amount (৳)</label>
                        <input type="number" step="10" min="0" class="billing-form-control" id="edit-discount" name="discount" value="0">
                    </div>
                    <div class="billing-form-group">
                        <label>Discount Note / Reason</label>
                        <input type="text" class="billing-form-control" id="edit-discount-note" name="discount_note" placeholder="e.g. Revisit, Courtesy">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <div class="billing-form-group">
                        <label>Paid Amount (৳)</label>
                        <input type="number" step="10" min="0" class="billing-form-control" id="edit-paid-amount" name="paid_amount" required>
                    </div>
                    <div class="billing-form-group">
                        <label>Payment Method</label>
                        <select class="billing-form-control" id="edit-payment-method" name="payment_method">
                            <option value="Cash">Cash</option>
                            <option value="Card">Card</option>
                            <option value="bKash">bKash</option>
                            <option value="Nagad">Nagad</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                </div>

                <div class="billing-form-group">
                    <label>Payment Remarks / Reference</label>
                    <input type="text" class="billing-form-control" id="edit-notes" name="notes" placeholder="Optional transaction ID or notes">
                </div>
            </div>
            <div class="billing-modal-footer">
                <button type="button" class="btn-billing-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn-billing-primary" id="btn-save-edit-payment">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL: New Transaction
     ========================================================================= -->
<div class="billing-modal" id="modal-new-billing" hidden style="display: none;">
    <div class="billing-modal-backdrop" data-close-modal></div>
    <div class="billing-modal-panel" role="dialog" aria-modal="true" style="width: min(560px, calc(100vw - 32px));">
        <div class="billing-modal-header">
            <h3>+ Record New Transaction</h3>
            <button type="button" class="billing-modal-close" data-close-modal>&times;</button>
        </div>
        <form id="form-new-billing">
            <div class="billing-modal-body">
                <div class="billing-form-group">
                    <label>Patient Name *</label>
                    <input type="text" class="billing-form-control" id="new-patient-name" name="patient_name" placeholder="Patient Full Name" required autocomplete="off">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <div class="billing-form-group">
                        <label>Service Type</label>
                        <select class="billing-form-control" id="new-service-type" name="service_type">
                            <option value="Doctor Consultation">Doctor Consultation</option>
                            <option value="Follow-up Consultation">Follow-up Consultation</option>
                            <option value="Minor Procedure">Minor Procedure</option>
                            <option value="ECG / Diagnostic">ECG / Diagnostic</option>
                            <option value="Wound Dressing">Wound Dressing</option>
                            <option value="Emergency Care">Emergency Care</option>
                            <option value="Other Service">Other Service</option>
                        </select>
                    </div>
                    <div class="billing-form-group">
                        <label>Service Fee (৳) *</label>
                        <input type="number" step="10" min="0" class="billing-form-control" id="new-fee" name="visit_fee" value="500" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <div class="billing-form-group">
                        <label>Discount (৳)</label>
                        <input type="number" step="10" min="0" class="billing-form-control" id="new-discount" name="discount" value="0">
                    </div>
                    <div class="billing-form-group">
                        <label>Discount Reason</label>
                        <input type="text" class="billing-form-control" id="new-discount-note" name="discount_note" placeholder="e.g. Concession, Staff">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <div class="billing-form-group">
                        <label>Amount Received (৳) *</label>
                        <input type="number" step="10" min="0" class="billing-form-control" id="new-paid-amount" name="paid_amount" value="500" required>
                    </div>
                    <div class="billing-form-group">
                        <label>Payment Method</label>
                        <select class="billing-form-control" id="new-payment-method" name="payment_method">
                            <option value="Cash">Cash</option>
                            <option value="Card">Card</option>
                            <option value="bKash">bKash</option>
                            <option value="Nagad">Nagad</option>
                        </select>
                    </div>
                </div>

                <div class="billing-form-group">
                    <label>Remarks</label>
                    <input type="text" class="billing-form-control" id="new-notes" name="notes" placeholder="Optional notes">
                </div>
            </div>
            <div class="billing-modal-footer">
                <button type="button" class="btn-billing-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn-billing-primary" id="btn-save-new-billing">Generate Invoice &amp; Save</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL: Printable Invoice / Receipt
     ========================================================================= -->
<div class="billing-modal" id="modal-receipt" hidden style="display: none;">
    <div class="billing-modal-backdrop" data-close-modal></div>
    <div class="billing-modal-panel" role="dialog" aria-modal="true" style="width: min(520px, calc(100vw - 32px));">
        <div class="billing-modal-header no-print">
            <h3>Print Money Receipt</h3>
            <div style="display: flex; gap: 6px;">
                <button type="button" class="btn-billing-primary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;" onclick="window.print()">
                    🖨️ Print
                </button>
                <button type="button" class="billing-modal-close" data-close-modal>&times;</button>
            </div>
        </div>
        <div class="billing-modal-body" style="padding: 1.5rem; background: #f8fafc;">
            <div class="receipt-sheet" id="printable-receipt-area">
                <div class="receipt-header">
                    <h2 id="rcpt-clinic-name">ZimRx Consultation &amp; Care</h2>
                    <p id="rcpt-doctor-title">Consultant Physician</p>
                    <p style="font-size: 0.72rem; color: #94a3b8; margin-top: 2px;">Official Money Receipt</p>
                </div>

                <div class="receipt-meta">
                    <div>
                        <div>Receipt: <strong id="rcpt-invoice-no" style="font-feature-settings: 'tnum';">INV-0000</strong></div>
                        <div>Date: <span id="rcpt-date">-</span></div>
                    </div>
                    <div style="text-align: right;">
                        <div>Patient: <strong id="rcpt-patient-name">-</strong></div>
                        <div id="rcpt-patient-sub" style="font-size: 0.75rem; color: #64748b;">-</div>
                    </div>
                </div>

                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th>Item Description</th>
                            <th style="text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td id="rcpt-service-item">Consultation Fee</td>
                            <td style="text-align: right;" id="rcpt-gross-fee">৳0</td>
                        </tr>
                        <tr id="rcpt-discount-row">
                            <td style="color: #d97706;">Discount <span id="rcpt-disc-note" style="font-size: 0.7rem;"></span></td>
                            <td style="text-align: right; color: #d97706;" id="rcpt-discount">-৳0</td>
                        </tr>
                    </tbody>
                </table>

                <div class="receipt-totals">
                    <div class="receipt-total-row">
                        <span>Net Payable:</span>
                        <strong id="rcpt-net-payable">৳0</strong>
                    </div>
                    <div class="receipt-total-row">
                        <span>Paid (<span id="rcpt-method">Cash</span>):</span>
                        <strong style="color: #16a34a;" id="rcpt-paid-amount">৳0</strong>
                    </div>
                    <div class="receipt-total-row grand">
                        <span>Balance Due:</span>
                        <span id="rcpt-due-amount" style="color: #dc2626;">৳0</span>
                    </div>
                </div>

                <div class="receipt-footer">
                    <div>Thank you for choosing our medical consultation services.</div>
                    <div style="margin-top: 4px; font-weight: 600;">Status: <span id="rcpt-status-text">PAID</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Initialize date pickers with Flatpickr if available
    if (typeof flatpickr !== 'undefined') {
        flatpickr('#filter-from-date', { dateFormat: 'Y-m-d' });
        flatpickr('#filter-to-date', { dateFormat: 'Y-m-d' });
    }

    // Modal Helpers
    const openModal = (el) => {
        if (!el) return;
        el.hidden = false;
        el.removeAttribute('hidden');
        el.style.display = 'flex';
    };
    const closeModal = (el) => {
        if (!el) return;
        el.hidden = true;
        el.setAttribute('hidden', '');
        el.style.display = 'none';
    };

    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const m = btn.closest('.billing-modal');
            if (m) closeModal(m);
        });
    });

    // Edit Payment Modal logic
    const editModal = document.getElementById('modal-edit-payment');
    const editFeeDisplay = document.getElementById('edit-display-fee');
    const editNetDisplay = document.getElementById('edit-display-net');
    const editDiscInput = document.getElementById('edit-discount');
    const editPaidInput = document.getElementById('edit-paid-amount');
    let currentEditFee = 0;

    const recalcEditTotals = () => {
        const disc = Math.max(0, parseFloat(editDiscInput.value) || 0);
        const net = Math.max(0, currentEditFee - disc);
        editNetDisplay.textContent = '৳' + net.toLocaleString();
    };

    if (editDiscInput) {
        editDiscInput.addEventListener('input', recalcEditTotals);
    }

    document.querySelectorAll('.btn-edit-payment').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = btn.dataset;
            document.getElementById('edit-payment-id').value = data.id;
            document.getElementById('edit-modal-subtitle').textContent = `Receipt: ${data.receipt} • ${data.patient}`;
            currentEditFee = parseFloat(data.fee) || 0;
            editFeeDisplay.textContent = '৳' + currentEditFee.toLocaleString();
            editDiscInput.value = data.disc || 0;
            document.getElementById('edit-discount-note').value = data.discNote || '';
            editPaidInput.value = data.paid || 0;
            document.getElementById('edit-payment-method').value = data.method || 'Cash';
            document.getElementById('edit-notes').value = data.notes || '';
            recalcEditTotals();
            openModal(editModal);
        });
    });

    const editForm = document.getElementById('form-edit-payment');
    if (editForm) {
        editForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('btn-save-edit-payment');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';

            const formData = new FormData(editForm);
            formData.append('action', 'save_payment');

            try {
                const resp = await fetch('api/billings_api.php', { method: 'POST', body: formData });
                const res = await resp.json();
                if (res.success) {
                    window.location.reload();
                } else {
                    alert('Error saving payment: ' + (res.error || 'Unknown error'));
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Save Changes';
                }
            } catch (err) {
                alert('Network error saving payment: ' + err.message);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Changes';
            }
        });
    }

    // New Transaction Modal
    const newModal = document.getElementById('modal-new-billing');
    const newBtn = document.getElementById('btn-new-billing');
    if (newBtn && newModal) {
        newBtn.addEventListener('click', () => {
            openModal(newModal);
            setTimeout(() => document.getElementById('new-patient-name')?.focus(), 50);
        });
    }

    const newFeeInput = document.getElementById('new-fee');
    const newDiscInput = document.getElementById('new-discount');
    const newPaidInput = document.getElementById('new-paid-amount');
    const syncNewPaid = () => {
        const fee = Math.max(0, parseFloat(newFeeInput.value) || 0);
        const disc = Math.max(0, parseFloat(newDiscInput.value) || 0);
        newPaidInput.value = Math.max(0, fee - disc);
    };
    if (newFeeInput && newDiscInput) {
        newFeeInput.addEventListener('input', syncNewPaid);
        newDiscInput.addEventListener('input', syncNewPaid);
    }

    const newForm = document.getElementById('form-new-billing');
    if (newForm) {
        newForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('btn-save-new-billing');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';

            const formData = new FormData(newForm);
            formData.append('action', 'create_transaction');

            try {
                const resp = await fetch('api/billings_api.php', { method: 'POST', body: formData });
                const res = await resp.json();
                if (res.success) {
                    window.location.reload();
                } else {
                    alert('Error creating transaction: ' + (res.error || 'Unknown error'));
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Generate Invoice & Save';
                }
            } catch (err) {
                alert('Network error: ' + err.message);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Generate Invoice & Save';
            }
        });
    }

    // Print Receipt Modal logic
    const receiptModal = document.getElementById('modal-receipt');
    document.querySelectorAll('.btn-print-receipt').forEach(btn => {
        btn.addEventListener('click', async () => {
            const paymentId = btn.dataset.id;
            try {
                const resp = await fetch(`api/billings_api.php?action=get_receipt&payment_id=${paymentId}`);
                const res = await resp.json();
                if (res.success && res.receipt) {
                    const r = res.receipt;
                    document.getElementById('rcpt-clinic-name').textContent = r.clinic_name;
                    document.getElementById('rcpt-doctor-title').textContent = `${r.doctor_name} (${r.doctor_qualifications}) • ${r.doctor_speciality}`;
                    document.getElementById('rcpt-invoice-no').textContent = r.receipt_no;
                    document.getElementById('rcpt-date').textContent = r.date;
                    document.getElementById('rcpt-patient-name').textContent = r.patient_name;
                    document.getElementById('rcpt-patient-sub').textContent = [r.reg_no ? `Reg: ${r.reg_no}` : '', r.mobile, r.age_gender].filter(Boolean).join(' • ');
                    document.getElementById('rcpt-service-item').textContent = r.service_type;
                    document.getElementById('rcpt-gross-fee').textContent = '৳' + r.gross_fee.toLocaleString();
                    
                    const discRow = document.getElementById('rcpt-discount-row');
                    if (r.discount > 0) {
                        discRow.style.display = '';
                        document.getElementById('rcpt-discount').textContent = '-৳' + r.discount.toLocaleString();
                        document.getElementById('rcpt-disc-note').textContent = r.discount_note ? `(${r.discount_note})` : '';
                    } else {
                        discRow.style.display = 'none';
                    }

                    document.getElementById('rcpt-net-payable').textContent = '৳' + r.net_payable.toLocaleString();
                    document.getElementById('rcpt-method').textContent = r.payment_method;
                    document.getElementById('rcpt-paid-amount').textContent = '৳' + r.paid_amount.toLocaleString();
                    document.getElementById('rcpt-due-amount').textContent = r.due_amount > 0 ? '৳' + r.due_amount.toLocaleString() : '৳0 (Nil)';
                    document.getElementById('rcpt-status-text').textContent = r.payment_status;
                    openModal(receiptModal);
                } else {
                    alert('Could not fetch receipt details: ' + (res.error || 'Unknown error'));
                }
            } catch (err) {
                alert('Network error loading receipt: ' + err.message);
            }
        });
    });

    // CSV Export
    const exportBtn = document.getElementById('btn-export-csv');
    if (exportBtn) {
        exportBtn.addEventListener('click', () => {
            const table = document.getElementById('transactions-table');
            if (!table) return;
            const rows = [];
            table.querySelectorAll('tr').forEach(tr => {
                const cols = [];
                tr.querySelectorAll('th, td').forEach((td, idx) => {
                    if (idx === 10) return; // skip action column
                    let text = td.innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/\s+/g, ' ').trim();
                    text = text.replace(/"/g, '""');
                    cols.push(`"${text}"`);
                });
                if (cols.length) rows.push(cols.join(','));
            });

            const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(rows.join('\n'));
            const downloadLink = document.createElement('a');
            downloadLink.setAttribute('href', csvContent);
            downloadLink.setAttribute('download', `billings_export_${new Date().toISOString().slice(0, 10)}.csv`);
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        });
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
