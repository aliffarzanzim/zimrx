<?php
declare(strict_types=1);

// Billings controller: validates date filters, reconciles appointment fees, and loads ledger transactions.

$page_title = "Billings & Financials - ZimRx";

require_once __DIR__ . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/Services/BillingService.php';
require_once ZIMRX_BASE_DIR . '/lib/Services/PracticeProfileService.php';

$pdo = DbConnections::userdata();
$activePracticeProfile = (new \ZimRx\Services\PracticeProfileService($pdo))->activeForCurrentUser();
$doctorId = (int)($activePracticeProfile['doctor_id'] ?? 0);
$profileId = (int)($activePracticeProfile['id'] ?? 0);
if ($doctorId <= 0 || $profileId <= 0) {
    http_response_code(403);
    exit('An active practice profile is required to access billing records.');
}

$billingService = new BillingService($pdo);

// Atomically reconcile any unbilled appointment fees within a transactional boundary
try {
    $billingService->reconcileMissingAppointmentPayments($doctorId, $profileId);
} catch (Throwable $e) {
    error_log("BillingService::reconcileMissingAppointmentPayments failed: " . $e->getMessage());
}

// Resolve temporal filters and criteria
$dateRange = $billingService->resolveDateRange(
    trim((string)($_GET['preset'] ?? 'this_month')),
    trim((string)($_GET['from_date'] ?? '')),
    trim((string)($_GET['to_date'] ?? ''))
);

$preset = $dateRange['preset'];
$fromDate = $dateRange['from'];
$toDate = $dateRange['to'];
$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$methodFilter = trim((string)($_GET['method'] ?? 'all'));
$searchQuery = trim((string)($_GET['q'] ?? ''));

$criteria = [
    'from'   => $fromDate,
    'to'     => $toDate,
    'status' => $statusFilter,
    'method' => $methodFilter,
    'q'      => $searchQuery,
];

// Query domain metrics and transaction logs
$summaryMetrics = $billingService->getSummaryMetrics($doctorId, $profileId);
$todayMetrics = $summaryMetrics['today'];
$monthMetrics = $summaryMetrics['month'];
$dueMetrics = $summaryMetrics['due'];

$filteredResult = $billingService->getFilteredTransactions($doctorId, $criteria, $profileId);
$transactions = $filteredResult['transactions'];
$filteredCollected = $filteredResult['aggregates']['collected'];
$filteredInvoiced = $filteredResult['aggregates']['invoiced'];
$filteredDiscount = $filteredResult['aggregates']['discount'];
$filteredDue = $filteredResult['aggregates']['due'];

// Render View
require_once __DIR__ . '/header.php';
require_once ZIMRX_BASE_DIR . '/views/billings_view.php';
require_once __DIR__ . '/footer.php';
