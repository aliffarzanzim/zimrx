<?php
declare(strict_types=1);

namespace ZimRx\Services;

use PDO;
use DateTime;

// Daily patient queues, slot timing calculation, revisit discounts, and follow-up schedules.
final class AppointmentService
{
    public function __construct(private readonly PDO $pdo) {}

    // Convert DD/MM/YYYY to YYYY-MM-DD
    public static function dmyToIso(string $date): string
    {
        $date = trim($date);
        $dt = DateTime::createFromFormat('d/m/Y', $date);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        return $dt instanceof DateTime ? $dt->format('Y-m-d') : date('Y-m-d');
    }

    // Convert YYYY-MM-DD to DD/MM/YYYY
    public static function isoToDmy(string $date): string
    {
        $dt = DateTime::createFromFormat('Y-m-d', trim($date));
        return $dt instanceof DateTime ? $dt->format('d/m/Y') : date('d/m/Y');
    }

    // Load doctor's appointment settings with fallback defaults for timings, fees, and slot duration
    public function getSettings(int $doctorId): array
    {
        $defaults = [
            'default_start_time'   => '14:00',
            'minutes_per_patient'  => 5,
            'blank_slots'          => 3,
            'visit_fee'            => 500.0,
            'revisit_fee'          => 400.0,
            'revisit_validity_days'=> 60,
            'weekday_overrides'    => [],
        ];

        if ($doctorId <= 0) {
            return $defaults;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT settings_json
                 FROM zimrx_appointment_settings
                 WHERE doctor_id = :doctor_id
                 LIMIT 1"
            );
            $stmt->execute(['doctor_id' => $doctorId]);
            $raw = $stmt->fetchColumn();
            if (is_string($raw) && $raw !== '') {
                $stored = json_decode($raw, true);
                if (is_array($stored)) {
                    $defaults = array_merge($defaults, $stored);
                }
            }
        } catch (Throwable $e) {
            error_log("AppointmentService::getSettings warning: " . $e->getMessage());
        }

        $defaults['minutes_per_patient']   = max(1, (int)($defaults['minutes_per_patient'] ?? 5));
        $defaults['blank_slots']            = max(0, (int)($defaults['blank_slots'] ?? 3));
        $defaults['visit_fee']              = max(0.0, (float)($defaults['visit_fee'] ?? 500));
        $defaults['revisit_fee']            = max(0.0, (float)($defaults['revisit_fee'] ?? 400));
        $defaults['revisit_validity_days']  = max(0, (int)($defaults['revisit_validity_days'] ?? 60));
        $defaults['weekday_overrides']      = is_array($defaults['weekday_overrides'] ?? null) ? $defaults['weekday_overrides'] : [];

        return $defaults;
    }

    // Check weekday schedule override for clinic hours or closed days
    public function getDayRule(array $settings, string $date): array
    {
        $dt = DateTime::createFromFormat('Y-m-d', $date) ?: new DateTime();
        $weekday = (string)(int)$dt->format('w');
        $rule = is_array($settings['weekday_overrides'][$weekday] ?? null) ? $settings['weekday_overrides'][$weekday] : [];
        $startTime = trim((string)($rule['start_time'] ?? '')) ?: (string)($settings['default_start_time'] ?? '14:00');

        if (!preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            $startTime = '14:00';
        }

        return [
            'closed'     => !empty($rule['closed']),
            'start_time' => $startTime
        ];
    }

    // Estimate consult start time based on serial number and average consultation length
    public function calculateTime(int $appointmentNo, string $date, array $settings): string
    {
        $rule = $this->getDayRule($settings, $date);
        if ($rule['closed']) {
            return '';
        }

        $position = max(0, $appointmentNo - (int)($settings['blank_slots'] ?? 0) - 1);
        $dt = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $rule['start_time']);
        if (!$dt) {
            return '';
        }

        if ($position > 0) {
            $dt->modify('+' . ($position * (int)($settings['minutes_per_patient'] ?? 5)) . ' minutes');
        }

        return $dt->format('H:i');
    }

    // Next serial number in sequence, respecting doctor's reserved buffer slots
    public function getNextAppointmentNo(int $doctorId, string $date, array $settings): int
    {
        if ($doctorId <= 0) {
            return 1;
        }

        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(MAX(appointment_no), 0) + 1
             FROM zimrx_appointments
             WHERE doctor_id = :doctor_id AND appointment_date = :date"
        );
        $stmt->execute(['doctor_id' => $doctorId, 'date' => $date]);
        return max((int)$stmt->fetchColumn(), (int)($settings['blank_slots'] ?? 0) + 1, 1);
    }

    // Human-friendly label showing past visit date and day count
    public function getLastVisitLabel(?string $lastVisitDate, string $appointmentDate): string
    {
        if (!$lastVisitDate) {
            return '';
        }

        $last = DateTime::createFromFormat('Y-m-d H:i:s', $lastVisitDate)
            ?: DateTime::createFromFormat('Y-m-d', substr($lastVisitDate, 0, 10));
        $current = DateTime::createFromFormat('Y-m-d', $appointmentDate);

        if (!$last || !$current) {
            return '';
        }

        $days = (int)$last->diff($current)->format('%r%a');
        $formatted = $last->format('d/m/Y');
        return $days >= 0 ? $formatted . ' (' . $days . ' Days ago)' : $formatted;
    }

    // Calculate discount if within the doctor's revisit validity window
    public function calculateRevisitDiscount(array $row, array $settings, string $date): float
    {
        if (($row['visit_fee'] ?? '') !== '') {
            return max(0.0, (float)($row['discount'] ?? 0));
        }

        $lastVisitDate = (string)($row['last_visit_date'] ?? '');
        $last = $lastVisitDate !== ''
            ? (DateTime::createFromFormat('Y-m-d H:i:s', $lastVisitDate) ?: DateTime::createFromFormat('Y-m-d', substr($lastVisitDate, 0, 10)))
            : null;
        $current = DateTime::createFromFormat('Y-m-d', $date);

        if ($last && $current) {
            $days = (int)$last->diff($current)->format('%r%a');
            if ($days >= 0 && $days <= (int)($settings['revisit_validity_days'] ?? 60)) {
                return max(0.0, (float)($settings['visit_fee'] ?? 500) - (float)($settings['revisit_fee'] ?? 400));
            }
        }

        return max(0.0, (float)($row['discount'] ?? 0));
    }

    // Daily appointment list with joined patient demographics and last visit history
    public function getDailyQueue(int $doctorId, string $date, array $settings): array
    {
        if ($doctorId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT
                a.id,
                a.doctor_id,
                a.patient_id,
                a.appointment_no,
                a.appointment_date,
                a.appointment_time,
                coalesce(nullif(a.reg_no, ''), p.reg_no, '') AS reg_no,
                coalesce(nullif(a.patient_name, ''), p.full_name, '') AS patient_name,
                coalesce(nullif(a.mobile, ''), p.mobile, '') AS mobile,
                coalesce(nullif(a.address, ''), p.address, '') AS address,
                coalesce(nullif(a.referral_category, ''), 'self') AS referral_category,
                coalesce(a.referral_name, '') AS referral_name,
                a.visit_no,
                a.visit_id,
                a.visit_id AS visit_code,
                a.visit_fee,
                a.discount,
                a.discount_note,
                a.paid_amount,
                a.status,
                a.notes,
                a.bp,
                a.pulse,
                a.temperature,
                a.spo2,
                a.resp_rate,
                (
                    SELECT max(v.visit_date)
                    FROM zimrx_visits v
                    WHERE v.patient_id = a.patient_id
                      AND v.doctor_id = a.doctor_id
                      AND date(v.visit_date) < date(a.appointment_date)
                ) AS last_visit_date
             FROM zimrx_appointments a
             LEFT JOIN zimrx_patients p ON p.id = a.patient_id
             WHERE a.doctor_id = :doctor_id
               AND a.appointment_date = :date
             ORDER BY a.appointment_no ASC, a.id ASC"
        );
        $stmt->execute(['doctor_id' => $doctorId, 'date' => $date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $fee = ($row['visit_fee'] ?? '') !== '' ? (float)$row['visit_fee'] : (float)($settings['visit_fee'] ?? 500);
            $discount = $this->calculateRevisitDiscount($row, $settings, $date);
            $paidAmount = max(0.0, (float)($row['paid_amount'] ?? 0));
            $payable = max(0.0, $fee - $discount);
            $row['visit_fee'] = $fee;
            $row['paid_status'] = ($payable > 0.0 && $paidAmount >= $payable) ? 'Paid' : 'Not Paid';
            $row['last_visit_label'] = $this->getLastVisitLabel($row['last_visit_date'] ?? null, $date);
        }
        unset($row);

        return $rows;
    }

    // Patients with scheduled future follow-up dates
    public function getFollowupPatients(int $doctorId): array
    {
        if ($doctorId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT
                v.id AS visit_id,
                v.patient_id,
                coalesce(nullif(v.patient_reg_no, ''), p.reg_no, '') AS reg_no,
                coalesce(nullif(v.patient_name, ''), p.full_name, '') AS patient_name,
                substr(v.visit_date, 1, 10) AS last_visit_date,
                v.next_visit,
                coalesce(nullif(p.mobile, ''), '') AS mobile,
                coalesce(nullif(p.age, ''), v.age_at_visit, '') AS age,
                coalesce(nullif(p.gender, ''), '') AS gender,
                coalesce(nullif(p.address, ''), '') AS address
             FROM zimrx_visits v
             LEFT JOIN zimrx_patients p ON p.id = v.patient_id
             WHERE v.doctor_id = :doctor_id
               AND v.next_visit IS NOT NULL
               AND trim(v.next_visit) != ''
             ORDER BY v.next_visit ASC, v.id DESC"
        );
        $stmt->execute(['doctor_id' => $doctorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // Tally attendance, revenue, and pending follow-ups for queue badges
    public function calculateStatistics(array $appointments, array $followups): array
    {
        $stats = [
            'total_appointments' => count($appointments),
            'pending_count'      => 0,
            'done_count'         => 0,
            'total_collected'    => 0.0,
            'total_followups'    => count($followups),
            'due_today_count'    => 0,
            'upcoming_count'     => 0,
            'overdue_count'      => 0,
        ];

        foreach ($appointments as $a) {
            if (strtolower((string)($a['status'] ?? '')) === 'done') {
                $stats['done_count']++;
            } else {
                $stats['pending_count']++;
            }
            $stats['total_collected'] += max(0.0, (float)($a['paid_amount'] ?? 0));
        }

        $todayIso = date('Y-m-d');
        foreach ($followups as $f) {
            $nextDate = substr(trim((string)($f['next_visit'] ?? '')), 0, 10);
            if ($nextDate === $todayIso) {
                $stats['due_today_count']++;
            } elseif ($nextDate > $todayIso) {
                $stats['upcoming_count']++;
            } elseif ($nextDate !== '') {
                $stats['overdue_count']++;
            }
        }

        return $stats;
    }
}

if (!class_exists('AppointmentService', false)) {
    class_alias(AppointmentService::class, 'AppointmentService');
}

