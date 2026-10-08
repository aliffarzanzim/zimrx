<?php
declare(strict_types=1);

namespace ZimRx\Services;

use PDO;

// Consultation invoicing, receipt generation, payment status reconciliation, and financial reports.
final class BillingService
{
    public function __construct(private readonly PDO $pdo) {}

    // Backfill missing payment ledger entries for booked appointments with fees or payments
    public function reconcileMissingAppointmentPayments(int $doctorId): int
    {
        $doctorId = max(1, $doctorId);

        $stmt = $this->pdo->prepare(
            "SELECT a.id, a.patient_id, a.doctor_id, a.visit_fee, a.discount, a.discount_note, a.paid_amount, a.appointment_date
             FROM zimrx_appointments a
             LEFT JOIN zimrx_payments p ON p.appointment_id = a.id
             WHERE p.id IS NULL 
               AND a.doctor_id = :doc
               AND (a.visit_fee > 0 OR a.paid_amount > 0)"
        );
        $stmt->execute(['doc' => $doctorId]);
        $unbilled = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($unbilled)) {
            return 0;
        }

        $syncedCount = 0;
        $this->pdo->beginTransaction();

        try {
            $insertStmt = $this->pdo->prepare(
                "INSERT OR IGNORE INTO zimrx_payments 
                 (appointment_id, patient_id, doctor_id, visit_fee, discount, discount_note, paid_amount, payment_method, payment_status, service_type, created_at, updated_at)
                 VALUES (:aid, :pid, :doc, :fee, :disc, :dnote, :paid, 'Cash', :status, 'Consultation', :c_at, :u_at)"
            );

            $updateReceiptStmt = $this->pdo->prepare(
                "UPDATE zimrx_payments SET receipt_no = :rno WHERE id = :id"
            );

            foreach ($unbilled as $row) {
                $fee = (float)($row['visit_fee'] ?? 0);
                $discount = (float)($row['discount'] ?? 0);
                $paid = (float)($row['paid_amount'] ?? 0);
                $net = max(0.0, $fee - $discount);
                $status = ($net > 0.0 && $paid >= $net) ? 'paid' : ($paid > 0.0 ? 'partial' : 'due');
                $createdAt = !empty($row['appointment_date']) 
                    ? $row['appointment_date'] . ' 10:00:00' 
                    : date('Y-m-d H:i:s');

                $insertStmt->execute([
                    'aid'    => (int)$row['id'],
                    'pid'    => (int)$row['patient_id'],
                    'doc'    => (int)($row['doctor_id'] ?: $doctorId),
                    'fee'    => $fee,
                    'disc'   => $discount,
                    'dnote'  => (string)($row['discount_note'] ?? ''),
                    'paid'   => $paid,
                    'status' => $status,
                    'c_at'   => $createdAt,
                    'u_at'   => $createdAt,
                ]);

                if ($insertStmt->rowCount() > 0) {
                    $newId = (int)$this->pdo->lastInsertId();
                    $receiptNo = sprintf('INV-%s-%04d', substr($createdAt, 0, 4), $newId);
                    $updateReceiptStmt->execute([
                        'rno' => $receiptNo,
                        'id'  => $newId,
                    ]);
                    $syncedCount++;
                }
            }

            $this->pdo->commit();
            return $syncedCount;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    // Convert date range presets (today, yesterday, this_month, etc.) into ISO date boundaries
    public function resolveDateRange(string $preset, string $fromDate = '', string $toDate = ''): array
    {
        $today = date('Y-m-d');
        $resolvedPreset = $preset;

        if ($preset === 'today') {
            $from = $today;
            $to = $today;
        } elseif ($preset === 'yesterday') {
            $from = date('Y-m-d', strtotime('-1 day'));
            $to = date('Y-m-d', strtotime('-1 day'));
        } elseif ($preset === 'this_week') {
            $from = date('Y-m-d', strtotime('monday this week'));
            $to = $today;
        } elseif ($preset === 'this_month') {
            $from = date('Y-m-01');
            $to = $today;
        } elseif ($preset === 'last_month') {
            $from = date('Y-m-01', strtotime('first day of last month'));
            $to = date('Y-m-t', strtotime('last day of last month'));
        } elseif ($fromDate !== '' || $toDate !== '') {
            $from = $fromDate !== '' ? $fromDate : date('Y-m-01');
            $to = $toDate !== '' ? $toDate : $today;
            $resolvedPreset = 'custom';
        } else {
            $from = date('Y-m-01');
            $to = $today;
            $resolvedPreset = 'this_month';
        }

        return ['from' => $from, 'to' => $to, 'preset' => $resolvedPreset];
    }

    // Key financial metrics: today's collection, monthly totals, and outstanding dues
    public function getSummaryMetrics(int $doctorId): array
    {
        $today = date('Y-m-d');

        $todayStmt = $this->pdo->prepare(
            "SELECT 
                COALESCE(SUM(paid_amount), 0) AS today_collected,
                COALESCE(SUM(visit_fee), 0) AS today_invoiced,
                COALESCE(SUM(discount), 0) AS today_discount,
                COUNT(*) AS today_count
             FROM zimrx_payments 
             WHERE doctor_id = :doc AND date(created_at) = :today"
        );
        $todayStmt->execute(['doc' => $doctorId, 'today' => $today]);
        $todayMetrics = $todayStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $monthStmt = $this->pdo->prepare(
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
        $monthMetrics = $monthStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $dueStmt = $this->pdo->prepare(
            "SELECT 
                COALESCE(SUM(MAX(0, (visit_fee - discount) - paid_amount)), 0) AS total_due,
                COUNT(CASE WHEN (visit_fee - discount) > paid_amount THEN 1 END) AS due_count
             FROM zimrx_payments 
             WHERE doctor_id = :doc"
        );
        $dueStmt->execute(['doc' => $doctorId]);
        $dueMetrics = $dueStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'today' => $todayMetrics,
            'month' => $monthMetrics,
            'due'   => $dueMetrics,
        ];
    }

    // Filter transactions by date range, payment status, payment method, or search query
    public function getFilteredTransactions(int $doctorId, array $criteria): array
    {
        $where = ["p.doctor_id = :doc"];
        $params = ['doc' => $doctorId];

        $from = trim((string)($criteria['from'] ?? ''));
        $to = trim((string)($criteria['to'] ?? ''));
        $status = trim((string)($criteria['status'] ?? 'all'));
        $method = trim((string)($criteria['method'] ?? 'all'));
        $search = trim((string)($criteria['q'] ?? ''));

        if ($from !== '') {
            $where[] = "date(p.created_at) >= :from_date";
            $params['from_date'] = $from;
        }
        if ($to !== '') {
            $where[] = "date(p.created_at) <= :to_date";
            $params['to_date'] = $to;
        }

        if ($status !== '' && $status !== 'all') {
            if ($status === 'paid') {
                $where[] = "p.payment_status = 'paid'";
            } elseif ($status === 'due') {
                $where[] = "(p.payment_status = 'due' OR p.payment_status = 'partial')";
            } elseif ($status === 'discounted') {
                $where[] = "p.discount > 0";
            }
        }

        if ($method !== '' && $method !== 'all') {
            $where[] = "p.payment_method LIKE :method";
            $params['method'] = "%{$method}%";
        }

        if ($search !== '') {
            $where[] = "(pat.full_name LIKE :sq OR pat.mobile LIKE :sq OR pat.reg_no LIKE :sq OR p.receipt_no LIKE :sq OR app.patient_name LIKE :sq)";
            $params['sq'] = "%{$search}%";
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $this->pdo->prepare(
            "SELECT 
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
              ORDER BY p.id DESC"
        );
        $stmt->execute($params);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $aggregates = [
            'collected' => 0.0,
            'invoiced'  => 0.0,
            'discount'  => 0.0,
            'due'       => 0.0,
        ];

        foreach ($transactions as $t) {
            $vf = (float)($t['visit_fee'] ?? 0);
            $dc = (float)($t['discount'] ?? 0);
            $pd = (float)($t['paid_amount'] ?? 0);
            $net = max(0.0, $vf - $dc);
            $due = max(0.0, $net - $pd);

            $aggregates['collected'] += $pd;
            $aggregates['invoiced']  += $vf;
            $aggregates['discount']  += $dc;
            $aggregates['due']       += $due;
        }

        return [
            'transactions' => $transactions,
            'aggregates'   => $aggregates,
        ];
    }
}

if (!class_exists('BillingService', false)) {
    class_alias(BillingService::class, 'BillingService');
}

