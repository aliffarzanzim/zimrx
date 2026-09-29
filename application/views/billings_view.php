<?php
declare(strict_types=1);

// Billings view: transaction ledger, financial summaries, receipts, and payment collection modal.
?>
<link rel="stylesheet" href="assets/css/pages/billings.css?v=<?= filemtime(__DIR__ . '/../assets/css/pages/billings.css') ?>">

<div class="billings-container zrx-page-container">
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
            <div class="billing-kpi-value text-money-paid">৳<?= number_format((float)($todayMetrics['today_collected'] ?? 0), 0) ?></div>
            <div class="billing-kpi-sub">
                <span>Invoiced: <strong>৳<?= number_format((float)($todayMetrics['today_invoiced'] ?? 0), 0) ?></strong></span>
                <span>&bull;</span>
                <span><?= (int)($todayMetrics['today_count'] ?? 0) ?> visits</span>
            </div>
        </div>

        <div class="billing-kpi-card">
            <div class="billing-kpi-head">
                <span class="billing-kpi-label">This Month Collection</span>
                <div class="billing-kpi-icon kpi-icon-blue">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                </div>
            </div>
            <div class="billing-kpi-value">৳<?= number_format((float)($monthMetrics['month_collected'] ?? 0), 0) ?></div>
            <div class="billing-kpi-sub">
                <span>Discounts: <strong>৳<?= number_format((float)($monthMetrics['month_discount'] ?? 0), 0) ?></strong></span>
                <span>&bull;</span>
                <span><?= (int)($monthMetrics['month_count'] ?? 0) ?> invoices</span>
            </div>
        </div>

        <div class="billing-kpi-card">
            <div class="billing-kpi-head">
                <span class="billing-kpi-label">Total Outstanding Due</span>
                <div class="billing-kpi-icon kpi-icon-amber">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                </div>
            </div>
            <div class="billing-kpi-value text-money-due">৳<?= number_format((float)($dueMetrics['total_due'] ?? 0), 0) ?></div>
            <div class="billing-kpi-sub">
                <a href="billings.php?status=due&preset=all" class="billing-filter-due-link">
                    <?= (int)($dueMetrics['due_count'] ?? 0) ?> pending accounts &rarr;
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

                <div class="zrx-flex-ac">
                    <label class="billing-filter-label">Custom Range:</label>
                    <input type="text" name="from_date" id="filter-from-date" class="billing-date-input" placeholder="From Date" value="<?= htmlspecialchars($fromDate) ?>">
                    <span class="zrx-c-muted2">&ndash;</span>
                    <input type="text" name="to_date" id="filter-to-date" class="billing-date-input" placeholder="To Date" value="<?= htmlspecialchars($toDate) ?>">
                    <button type="submit" class="btn-billing-primary btn-billing-filter-submit">Apply</button>
                </div>
            </div>

            <div class="billing-filter-row-inputs billing-filter-inputs-spaced">
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
                    <a href="billings.php?preset=this_month" class="btn-billing-outline btn-billing-filter-reset">Clear Filters</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Data Table Card -->
    <div class="billing-table-card">
        <div class="billing-table-head-bar">
            <span>SHOWING <?= count($transactions) ?> TRANSACTIONS</span>
            <span>Collected in View: <strong class="billing-collected-view-val">৳<?= number_format($filteredCollected, 0) ?></strong> &bull; Due: <strong class="zrx-c-danger">৳<?= number_format($filteredDue, 0) ?></strong></span>
        </div>

        <div class="billing-table-wrap">
            <table class="billing-table" id="transactions-table">
                <thead>
                    <tr>
                        <th class="col-bill-datetime">Date &amp; Time</th>
                        <th class="col-bill-receipt">Receipt #</th>
                        <th>Patient Details</th>
                        <th class="col-bill-service">Service</th>
                        <th class="col-bill-gross">Gross Fee</th>
                        <th class="col-bill-discount">Discount</th>
                        <th class="col-bill-net">Net Payable</th>
                        <th class="col-bill-paid">Paid</th>
                        <th class="col-bill-due">Due</th>
                        <th class="col-bill-method">Method</th>
                        <th class="col-bill-actions no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="11" class="td-bill-empty">
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
                            <td class="td-bill-datetime">
                                <?= date('d M Y', strtotime($t['created_at'])) ?><br>
                                <span class="time-subtext"><?= date('h:i A', strtotime($t['created_at'])) ?></span>
                            </td>
                            <td>
                                <strong class="invoice-badge-strong">
                                    <?= htmlspecialchars($receiptNo) ?>
                                </strong>
                            </td>
                            <td>
                                <div class="patient-name-cell">
                                    <?= htmlspecialchars((string)$t['patient_name']) ?>
                                </div>
                                <div class="patient-meta-cell">
                                    <?php if (!empty($t['reg_no'])): ?>
                                        <span>Reg: <?= htmlspecialchars((string)$t['reg_no']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($t['mobile'])): ?>
                                        <span>📱 <?= htmlspecialchars((string)$t['mobile']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($t['gender']) || !empty($t['age'])): ?>
                                        <span>(<?= htmlspecialchars(trim(($t['age'] ?? '') . ' ' . ($t['gender'] ?? ''))) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="service-type-cell">
                                    <?= htmlspecialchars($t['service_type'] ?: 'Consultation') ?>
                                </span>
                            </td>
                            <td class="zrx-ta-r text-money">৳<?= number_format($fee, 0) ?></td>
                            <td class="td-money-discount text-money">
                                <?= $disc > 0 ? '৳' . number_format($disc, 0) : '-' ?>
                                <?php if (!empty($t['discount_note'])): ?>
                                    <div class="discount-note-cell"><?= htmlspecialchars((string)$t['discount_note']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="td-money-net text-money">৳<?= number_format($net, 0) ?></td>
                            <td class="td-money-paid text-money">৳<?= number_format($paid, 0) ?></td>
                            <td class="zrx-ta-r">
                                <?php if ($due <= 0): ?>
                                    <span class="badge-status badge-paid">Paid</span>
                                <?php else: ?>
                                    <span class="badge-status badge-due">৳<?= number_format($due, 0) ?> Due</span>
                                <?php endif; ?>
                            </td>
                            <td class="zrx-ta-c">
                                <span class="badge-method"><?= htmlspecialchars((string)($t['payment_method'] ?: 'Cash')) ?></span>
                            </td>
                            <td class="zrx-ta-c no-print">
                                <div class="billing-actions">
                                    <button type="button" class="btn-action-icon btn-edit-payment" title="Collect / Edit Payment" 
                                        data-id="<?= (int)$t['id'] ?>"
                                        data-receipt="<?= htmlspecialchars($receiptNo) ?>"
                                        data-patient="<?= htmlspecialchars((string)$t['patient_name']) ?>"
                                        data-fee="<?= $fee ?>"
                                        data-disc="<?= $disc ?>"
                                        data-disc-note="<?= htmlspecialchars((string)($t['discount_note'] ?? '')) ?>"
                                        data-paid="<?= $paid ?>"
                                        data-method="<?= htmlspecialchars((string)($t['payment_method'] ?: 'Cash')) ?>"
                                        data-notes="<?= htmlspecialchars((string)($t['notes'] ?? '')) ?>">
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

<!-- Modal: Collect / Edit Payment -->
<div class="billing-modal zrx-dn" id="modal-edit-payment" hidden>
    <div class="billing-modal-backdrop" data-close-modal></div>
    <div class="billing-modal-panel" role="dialog" aria-modal="true">
        <div class="billing-modal-header">
            <div>
                <h3 id="edit-modal-title">Collect / Update Payment</h3>
                <span id="edit-modal-subtitle" class="modal-subtitle-text"></span>
            </div>
            <button type="button" class="billing-modal-close" data-close-modal>&times;</button>
        </div>
        <form id="form-edit-payment">
            <input type="hidden" name="payment_id" id="edit-payment-id">
            <div class="billing-modal-body">
                <div class="modal-patient-summary">
                    <div>Gross Consultation Fee: <strong id="edit-display-fee">৳0</strong></div>
                    <div>Net Payable: <strong id="edit-display-net" class="zrx-c-primary">৳0</strong></div>
                </div>

                <div class="billing-form-grid-2col">
                    <div class="billing-form-group">
                        <label>Discount Amount (৳)</label>
                        <input type="number" step="10" min="0" class="billing-form-control" id="edit-discount" name="discount" value="0">
                    </div>
                    <div class="billing-form-group">
                        <label>Discount Note / Reason</label>
                        <input type="text" class="billing-form-control" id="edit-discount-note" name="discount_note" placeholder="e.g. Revisit, Courtesy">
                    </div>
                </div>

                <div class="billing-form-grid-2col">
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

<!-- Modal: New Transaction -->
<div class="billing-modal zrx-dn" id="modal-new-billing" hidden>
    <div class="billing-modal-backdrop" data-close-modal></div>
    <div class="billing-modal-panel billing-modal-md" role="dialog" aria-modal="true">
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

                <div class="billing-form-grid-2col">
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

                <div class="billing-form-grid-2col">
                    <div class="billing-form-group">
                        <label>Discount (৳)</label>
                        <input type="number" step="10" min="0" class="billing-form-control" id="new-discount" name="discount" value="0">
                    </div>
                    <div class="billing-form-group">
                        <label>Discount Reason</label>
                        <input type="text" class="billing-form-control" id="new-discount-note" name="discount_note" placeholder="e.g. Concession, Staff">
                    </div>
                </div>

                <div class="billing-form-grid-2col">
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

<!-- Modal: Printable Invoice / Receipt -->
<div class="billing-modal zrx-dn" id="modal-receipt" hidden>
    <div class="billing-modal-backdrop" data-close-modal></div>
    <div class="billing-modal-panel billing-modal-sm" role="dialog" aria-modal="true">
        <div class="billing-modal-header no-print">
            <h3>Print Money Receipt</h3>
            <div class="modal-header-actions-flex">
                <button type="button" class="btn-billing-primary btn-print-receipt" onclick="window.print()">
                    🖨️ Print
                </button>
                <button type="button" class="billing-modal-close" data-close-modal>&times;</button>
            </div>
        </div>
        <div class="billing-modal-body receipt-modal-body-pad">
            <div class="receipt-sheet" id="printable-receipt-area">
                <div class="receipt-header">
                    <h2 id="rcpt-clinic-name">ZimRx Consultation &amp; Care</h2>
                    <p id="rcpt-doctor-title">Consultant Physician</p>
                    <p class="receipt-official-note">Official Money Receipt</p>
                </div>

                <div class="receipt-meta">
                    <div>
                        <div>Receipt: <strong id="rcpt-invoice-no" class="receipt-invoice-num">INV-0000</strong></div>
                        <div>Date: <span id="rcpt-date">-</span></div>
                    </div>
                    <div class="zrx-ta-r">
                        <div>Patient: <strong id="rcpt-patient-name">-</strong></div>
                        <div id="rcpt-patient-sub" class="receipt-patient-subtext">-</div>
                    </div>
                </div>

                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th>Item Description</th>
                            <th class="zrx-ta-r">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td id="rcpt-service-item">Consultation Fee</td>
                            <td class="zrx-ta-r" id="rcpt-gross-fee">৳0</td>
                        </tr>
                        <tr id="rcpt-discount-row">
                            <td class="zrx-c-warning">Discount <span id="rcpt-disc-note" class="receipt-disc-label-note"></span></td>
                            <td class="receipt-discount-val" id="rcpt-discount">-৳0</td>
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
                        <strong class="receipt-paid-val" id="rcpt-paid-amount">৳0</strong>
                    </div>
                    <div class="receipt-total-row grand">
                        <span>Balance Due:</span>
                        <span id="rcpt-due-amount" class="zrx-c-danger">৳0</span>
                    </div>
                </div>

                <div class="receipt-footer">
                    <div>Thank you for choosing our medical consultation services.</div>
                    <div class="receipt-status-footer">Status: <span id="rcpt-status-text">PAID</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/pages/billings.js?v=<?= filemtime(__DIR__ . '/../assets/js/pages/billings.js') ?>"></script>
