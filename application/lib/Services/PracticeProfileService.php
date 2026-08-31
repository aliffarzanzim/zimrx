<?php
declare(strict_types=1);

namespace ZimRx\Services;

use PDO;
use RuntimeException;

final class PracticeProfileService
{
    public function __construct(private readonly PDO $pdo) {}

    public function availableForCurrentUser(): array
    {
        $role = strtolower((string)\current_user_role());
        $doctorRole = in_array($role, ['admin', 'superadmin'], true) ? 'admin' : $role;
        $doctors = \zimrx_doctor_options_for_user(
            $this->pdo,
            (int)\current_user_id(),
            $doctorRole,
            (int)\current_user_doctor_id()
        );
        $doctorIds = array_values(array_unique(array_filter(
            array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $doctors),
            static fn(int $id): bool => $id > 0
        )));
        if ($doctorIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($doctorIds as $index => $doctorId) {
            $key = 'doctor_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $doctorId;
        }

        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.profile_uuid, p.doctor_id, p.profile_name, p.address, p.phone, p.prescription_footer_note,
                    p.is_active, p.is_default, p.share_patient_particulars, p.share_emr,
                    p.share_prescriptions, d.display_name AS doctor_name
             FROM zimrx_doctor_profiles p
             JOIN zimrx_doctors d ON d.id = p.doctor_id
             WHERE p.is_active = 1 AND p.is_deleted = 0 AND d.is_active = 1
               AND p.doctor_id IN (" . implode(', ', $placeholders) . ")
             ORDER BY d.display_name ASC, p.is_default DESC, p.profile_name ASC, p.id ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function managedByCurrentUser(): array
    {
        $role = strtolower((string)\current_user_role());
        $doctorRole = in_array($role, ['admin', 'superadmin'], true) ? 'admin' : $role;
        $doctors = \zimrx_doctor_options_for_user(
            $this->pdo,
            (int)\current_user_id(),
            $doctorRole,
            (int)\current_user_doctor_id()
        );
        $doctorIds = array_values(array_unique(array_filter(
            array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $doctors),
            static fn(int $id): bool => $id > 0
        )));
        if ($doctorIds === []) {
            return [];
        }
        $params = [];
        $placeholders = [];
        foreach ($doctorIds as $index => $id) {
            $key = 'doctor_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.profile_uuid, p.doctor_id, p.profile_name, p.address, p.phone, p.prescription_footer_note,
                    p.is_active, p.is_default, p.share_patient_particulars, p.share_emr,
                    p.share_prescriptions, d.display_name AS doctor_name
             FROM zimrx_doctor_profiles p
             JOIN zimrx_doctors d ON d.id = p.doctor_id
             WHERE p.is_deleted = 0 AND d.is_active = 1
               AND p.doctor_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY d.display_name ASC, p.is_active DESC, p.is_default DESC, p.profile_name ASC, p.id ASC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function activeForCurrentUser(): ?array
    {
        $profiles = $this->availableForCurrentUser();
        if ($profiles === []) {
            return null;
        }

        $requestedId = (int)($_SESSION['active_practice_profile_id'] ?? 0);
        foreach ($profiles as $profile) {
            if ((int)$profile['id'] === $requestedId) {
                return $this->remember($profile);
            }
        }

        $requestedDoctorId = (int)($_SESSION['active_profile_doctor_id'] ?? $_SESSION['active_doctor_id'] ?? 0);
        foreach ($profiles as $profile) {
            if ((int)$profile['doctor_id'] === $requestedDoctorId && (int)$profile['is_default'] === 1) {
                return $this->remember($profile);
            }
        }

        foreach ($profiles as $profile) {
            if ((int)$profile['is_default'] === 1) {
                return $this->remember($profile);
            }
        }

        return $this->remember($profiles[0]);
    }

    public function activate(int $profileId): array
    {
        if ($profileId <= 0) {
            throw new RuntimeException('Choose a valid practice profile.');
        }
        foreach ($this->availableForCurrentUser() as $profile) {
            if ((int)$profile['id'] === $profileId) {
                return $this->remember($profile);
            }
        }
        throw new RuntimeException('You do not have access to that practice profile.');
    }

    public function reactivate(int $profileId, int $doctorId): void
    {
        $this->assertDoctorManageAccess($doctorId);
        $managed = $this->managedByCurrentUser();
        $found = false;
        foreach ($managed as $profile) {
            if ((int)$profile['id'] === $profileId && (int)$profile['doctor_id'] === $doctorId && (int)$profile['is_active'] === 0) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new RuntimeException('Inactive practice profile was not found.');
        }

        $this->pdo->beginTransaction();
        try {
            $default = $this->pdo->prepare('SELECT COUNT(*) FROM zimrx_doctor_profiles WHERE doctor_id = :doctor_id AND is_active = 1 AND is_deleted = 0 AND is_default = 1');
            $default->execute(['doctor_id' => $doctorId]);
            $makeDefault = (int)$default->fetchColumn() === 0 ? 1 : 0;
            $update = $this->pdo->prepare(
                'UPDATE zimrx_doctor_profiles SET is_active = 1, is_default = :is_default, archived_at = NULL, updated_at = ' . \DbSql::now() . '
                 WHERE id = :id AND doctor_id = :doctor_id AND is_active = 0 AND is_deleted = 0'
            );
            $update->execute(['is_default' => $makeDefault, 'id' => $profileId, 'doctor_id' => $doctorId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Practice profile could not be reactivated.');
            }
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function saveSettings(int $profileId, int $doctorId, string $key, array $settings): void
    {
        if ($profileId <= 0 || $doctorId <= 0 || !in_array($key, ['appointment', 'prescription_print'], true)) {
            throw new RuntimeException('Invalid practice profile settings target.');
        }

        $stmt = $this->pdo->prepare(
            'SELECT settings_json FROM zimrx_doctor_profiles
             WHERE id = :profile_id AND doctor_id = :doctor_id AND is_active = 1 AND is_deleted = 0 LIMIT 1'
        );
        $stmt->execute(['profile_id' => $profileId, 'doctor_id' => $doctorId]);
        $decoded = json_decode((string)($stmt->fetchColumn() ?: '{}'), true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $decoded[$key] = $settings;

        $update = $this->pdo->prepare(
            'UPDATE zimrx_doctor_profiles
             SET settings_json = :settings_json, updated_at = ' . \DbSql::now() . '
             WHERE id = :profile_id AND doctor_id = :doctor_id AND is_active = 1 AND is_deleted = 0'
        );
        $update->execute([
            'settings_json' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'profile_id' => $profileId,
            'doctor_id' => $doctorId,
        ]);
        if ($update->rowCount() !== 1) {
            $check = $this->pdo->prepare(
                'SELECT 1 FROM zimrx_doctor_profiles
                 WHERE id = :profile_id AND doctor_id = :doctor_id AND is_active = 1 AND is_deleted = 0 LIMIT 1'
            );
            $check->execute(['profile_id' => $profileId, 'doctor_id' => $doctorId]);
            if ($check->fetchColumn() === false) {
                throw new RuntimeException('Practice profile was not found or is inactive.');
            }
        }
    }

    public function settings(int $profileId, int $doctorId, string $key): ?array
    {
        if ($profileId <= 0 || $doctorId <= 0 || !in_array($key, ['appointment', 'prescription_print'], true)) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT settings_json FROM zimrx_doctor_profiles
             WHERE id = :profile_id AND doctor_id = :doctor_id AND is_active = 1 AND is_deleted = 0 LIMIT 1'
        );
        $stmt->execute(['profile_id' => $profileId, 'doctor_id' => $doctorId]);
        $decoded = json_decode((string)($stmt->fetchColumn() ?: '{}'), true);
        $settings = is_array($decoded) ? ($decoded[$key] ?? null) : null;
        return is_array($settings) ? $settings : null;
    }

    public function save(int $profileId, int $doctorId, array $input): int
    {
        $role = strtolower((string)\current_user_role());
        if (!in_array($role, ['doctor', 'admin', 'superadmin'], true)) {
            throw new RuntimeException('Only doctors and administrators can manage practice profiles.');
        }

        $name = trim((string)($input['profile_name'] ?? ''));
        $address = trim((string)($input['address'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $footerNote = trim((string)($input['prescription_footer_note'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120 || mb_strlen($address) > 500 || mb_strlen($phone) > 80 || mb_strlen($footerNote) > 5000) {
            throw new RuntimeException('Profile name or contact details are invalid.');
        }

        $ownerDoctorId = $role === 'doctor' ? (int)\current_user_doctor_id() : $doctorId;
        $this->assertDoctorManageAccess($ownerDoctorId);

        $shareParticulars = self::flag($input['share_patient_particulars'] ?? false);
        $shareEmr = self::flag($input['share_emr'] ?? false);
        $sharePrescriptions = self::flag($input['share_prescriptions'] ?? false);

        if ($profileId > 0) {
            $previousStmt = $this->pdo->prepare(
                'SELECT profile_name, address, phone FROM zimrx_doctor_profiles
                 WHERE id = :id AND doctor_id = :doctor_id AND is_active = 1 AND is_deleted = 0 LIMIT 1'
            );
            $previousStmt->execute(['id' => $profileId, 'doctor_id' => $ownerDoctorId]);
            $previous = $previousStmt->fetch(PDO::FETCH_ASSOC);
            if (!$previous) {
                throw new RuntimeException('Practice profile was not found or is inactive.');
            }

            $this->pdo->beginTransaction();
            try {
            $stmt = $this->pdo->prepare(
                "UPDATE zimrx_doctor_profiles
                 SET profile_name = :name, address = :address, phone = :phone, prescription_footer_note = :footer_note,
                     share_patient_particulars = :share_particulars,
                     share_emr = :share_emr, share_prescriptions = :share_prescriptions,
                     updated_at = " . \DbSql::now() . "
                 WHERE id = :id AND doctor_id = :doctor_id AND is_active = 1 AND is_deleted = 0"
            );
            $stmt->execute([
                'name' => $name,
                'address' => $address,
                'phone' => $phone,
                'footer_note' => $footerNote,
                'share_particulars' => $shareParticulars,
                'share_emr' => $shareEmr,
                'share_prescriptions' => $sharePrescriptions,
                'id' => $profileId,
                'doctor_id' => $ownerDoctorId,
            ]);
            if ($stmt->rowCount() !== 1) {
                $check = $this->pdo->prepare(
                    'SELECT 1 FROM zimrx_doctor_profiles WHERE id = :id AND doctor_id = :doctor_id AND is_active = 1 AND is_deleted = 0 LIMIT 1'
                );
                $check->execute(['id' => $profileId, 'doctor_id' => $ownerDoctorId]);
                if ($check->fetchColumn() === false) {
                    throw new RuntimeException('Practice profile was not found or is inactive.');
                }
            }
            $printSettings = $this->settings($profileId, $ownerDoctorId, 'prescription_print') ?? [];
            $header = is_array($printSettings['header_settings'] ?? null) ? $printSettings['header_settings'] : [];
            foreach ([
                'chamber_name' => ['profile_name', $name],
                'chamber_address' => ['address', $address],
                'chamber_phone' => ['phone', $phone],
            ] as $key => [$oldKey, $newValue]) {
                $currentValue = trim((string)($header[$key] ?? ''));
                if ($currentValue === '' || $currentValue === (string)$previous[$oldKey]) {
                    $header[$key] = $newValue;
                }
            }
            $printSettings['header_settings'] = $header;
            $this->saveSettings($profileId, $ownerDoctorId, 'prescription_print', $printSettings);
            $this->pdo->commit();
            return $profileId;
            } catch (\Throwable $error) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $error;
            }
        }

        $this->pdo->beginTransaction();
        try {
        $stmt = $this->pdo->prepare(
            "INSERT INTO zimrx_doctor_profiles
                (profile_uuid, doctor_id, profile_name, address, phone, prescription_footer_note,
                 share_patient_particulars, share_emr, share_prescriptions,
                 is_active, is_default, settings_json, created_at, updated_at)
             VALUES (:uuid, :doctor_id, :name, :address, :phone, :footer_note,
                     :share_particulars, :share_emr, :share_prescriptions,
                     1, 0, '{}', " . \DbSql::now() . ', ' . \DbSql::now() . ')'
        );
        $stmt->execute([
            'uuid' => self::uuid(),
            'doctor_id' => $ownerDoctorId,
            'name' => $name,
            'address' => $address,
            'phone' => $phone,
            'footer_note' => $footerNote,
            'share_particulars' => $shareParticulars,
            'share_emr' => $shareEmr,
            'share_prescriptions' => $sharePrescriptions,
        ]);
        $profileId = (int)$this->pdo->lastInsertId();
        $printSettings = [
            'header_settings' => [
                'doctor_name' => (string)\current_user_name(),
                'chamber_name' => $name,
                'chamber_address' => $address,
                'chamber_phone' => $phone,
            ],
            'layout_settings' => [],
        ];
        $this->saveSettings($profileId, $ownerDoctorId, 'prescription_print', $printSettings);
        $this->pdo->commit();
        return $profileId;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function archive(int $profileId, int $doctorId): void
    {
        $role = strtolower((string)\current_user_role());
        $ownerDoctorId = $role === 'doctor' ? (int)\current_user_doctor_id() : $doctorId;
        $this->assertDoctorManageAccess($ownerDoctorId);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
            "SELECT is_default FROM zimrx_doctor_profiles
                 WHERE id = :id AND doctor_id = :doctor_id AND is_active = 1 AND is_deleted = 0"
            );
            $stmt->execute(['id' => $profileId, 'doctor_id' => $ownerDoctorId]);
            $isDefault = $stmt->fetchColumn();
            if ($isDefault === false) {
                throw new RuntimeException('Practice profile was not found or is already inactive.');
            }

            $next = null;
            if ((int)$isDefault === 1) {
                $find = $this->pdo->prepare(
                    "SELECT id FROM zimrx_doctor_profiles
                     WHERE doctor_id = :doctor_id AND is_active = 1 AND is_deleted = 0 AND id <> :id
                     ORDER BY id ASC LIMIT 1"
                );
                $find->execute(['doctor_id' => $ownerDoctorId, 'id' => $profileId]);
                $next = $find->fetchColumn();
                if ($next === false) {
                    throw new RuntimeException('Create another active profile before archiving the default profile.');
                }
                $this->pdo->prepare(
                    "UPDATE zimrx_doctor_profiles SET is_default = 1, updated_at = " . \DbSql::now() . "
                     WHERE id = :id AND doctor_id = :doctor_id AND is_deleted = 0"
                )->execute(['id' => (int)$next, 'doctor_id' => $ownerDoctorId]);
            }

            $this->pdo->prepare(
                "UPDATE zimrx_doctor_profiles
                 SET is_active = 0, is_default = 0, archived_at = " . \DbSql::now() . ", updated_at = " . \DbSql::now() . "
                 WHERE id = :id AND doctor_id = :doctor_id AND is_deleted = 0"
            )->execute(['id' => $profileId, 'doctor_id' => $ownerDoctorId]);
            $this->pdo->commit();

            if ((int)($_SESSION['active_practice_profile_id'] ?? 0) === $profileId) {
                unset($_SESSION['active_practice_profile_id'], $_SESSION['active_profile_doctor_id']);
            }
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    private function assertDoctorManageAccess(int $doctorId): void
    {
        if ($doctorId <= 0) {
            throw new RuntimeException('A valid doctor account is required.');
        }
        $role = strtolower((string)\current_user_role());
        $doctorRole = in_array($role, ['admin', 'superadmin'], true) ? 'admin' : $role;
        $options = \zimrx_doctor_options_for_user(
            $this->pdo,
            (int)\current_user_id(),
            $doctorRole,
            (int)\current_user_doctor_id()
        );
        $allowedDoctorIds = array_map(static fn(array $option): int => (int)($option['id'] ?? 0), $options);
        if (!in_array($doctorId, $allowedDoctorIds, true)) {
            throw new RuntimeException('You do not have permission to manage this doctor\'s profiles.');
        }
    }

    private function remember(array $profile): array
    {
        $_SESSION['active_practice_profile_id'] = (int)$profile['id'];
        $_SESSION['active_profile_doctor_id'] = (int)$profile['doctor_id'];
        if (in_array(strtolower((string)\current_user_role()), ['admin', 'superadmin', 'assistant'], true)) {
            $_SESSION['active_doctor_id'] = (int)$profile['doctor_id'];
        }
        return $profile;
    }

    private static function flag(mixed $value): int
    {
        return in_array($value, [true, 1, '1', 'on', 'yes'], true) ? 1 : 0;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
