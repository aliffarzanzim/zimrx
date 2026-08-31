<?php

function admin_value(array $data, string $key): string {
    return trim((string)($data[$key] ?? ''));
}

function admin_all_doctors(PDO $pdo, bool $activeOnly = false): array {
    $sql = "SELECT *, qualifications_en AS qualifications, specialty_en AS specialty, bmdc_no_en AS bmdc_no, id AS doctor_id, display_name AS doctor_name, is_active AS status FROM zimrx_doctors";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY display_name ASC, id ASC";
    return $pdo->query($sql)->fetchAll();
}

function admin_assigned_doctor_ids(PDO $pdo, int $assistantId): array {
    $stmt = $pdo->prepare(
        "SELECT doctor_id
         FROM zimrx_doctor_assistants
         WHERE assistant_user_id = :assistant_id AND is_active = 1"
    );
    $stmt->execute(['assistant_id' => $assistantId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'doctor_id'));
}

function admin_sync_assistant_doctors(PDO $pdo, int $assistantId, array $doctorIds): void {
    $doctorIds = array_values(array_unique(array_filter(array_map('intval', $doctorIds), fn($id) => $id > 0)));
    $pdo->prepare(
        "UPDATE zimrx_doctor_assistants
         SET is_active = 0, updated_at = CURRENT_TIMESTAMP
         WHERE assistant_user_id = :assistant_id"
    )->execute(['assistant_id' => $assistantId]);

    $stmt = $pdo->prepare(
        "INSERT INTO zimrx_doctor_assistants (doctor_id, assistant_user_id, is_active, updated_at)
         VALUES (:doctor_id, :assistant_id, 1, " . DbSql::now() . ")
         " . DbSql::upsert('doctor_id, assistant_user_id', ['is_active', 'updated_at'], ['updated_at' => DbSql::now()])
    );
    foreach ($doctorIds as $doctorId) {
        $stmt->execute(['doctor_id' => $doctorId, 'assistant_id' => $assistantId]);
    }

    if ($doctorIds) {
        $pdo->prepare(
            "UPDATE zimrx_user_accounts
             SET doctor_id = :doctor_id, updated_at = CURRENT_TIMESTAMP
             WHERE id = :assistant_id"
        )->execute(['doctor_id' => $doctorIds[0], 'assistant_id' => $assistantId]);
    }
}

function admin_has_active_admin(PDO $pdo): bool {
    $stmt = $pdo->query("SELECT COUNT(*) FROM zimrx_user_accounts WHERE role = 'admin' AND is_active = 1");
    return (int)$stmt->fetchColumn() > 0;
}

function admin_assistant_rows(PDO $pdo, ?int $doctorId = null): array {
    $where = "u.role = 'assistant'";
    $params = [];
    if ($doctorId !== null) {
        $where .= " AND EXISTS (
            SELECT 1
            FROM zimrx_doctor_assistants da
            WHERE da.assistant_user_id = u.id
              AND da.doctor_id = :doctor_id
              AND da.is_active = 1
        )";
        $params['doctor_id'] = $doctorId;
    }

    $stmt = $pdo->prepare(
        "SELECT
            u.*,
            GROUP_CONCAT(CASE WHEN da.is_active = 1 THEN d.display_name END, ', ') AS assigned_doctors
         FROM zimrx_user_accounts u
         LEFT JOIN zimrx_doctor_assistants da ON da.assistant_user_id = u.id
         LEFT JOIN zimrx_doctors d ON d.id = da.doctor_id
         WHERE $where
         GROUP BY u.id
         ORDER BY u.is_active DESC, u.display_name ASC, u.id ASC"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function admin_upsert_assistant(PDO $pdo, array $data, array $doctorIds): int {
    $id = (int)admin_value($data, 'id');
    $username = admin_value($data, 'username');
    $displayName = admin_value($data, 'display_name');
    $password = admin_value($data, 'password');
    $isActive = admin_value($data, 'is_active') === '0' ? 0 : 1;

    if ($displayName === '') {
        throw new RuntimeException('Assistant name is required.');
    }
    if ($id <= 0 && ($username === '' || $password === '')) {
        throw new RuntimeException('Username and password are required for a new assistant.');
    }

    if ($id > 0) {
        $params = [
            'id' => $id,
            'display_name' => $displayName,
            'is_active' => $isActive,
        ];
        $sql = "UPDATE zimrx_user_accounts
                SET display_name = :display_name,
                    is_active = :is_active,
                    updated_at = CURRENT_TIMESTAMP";
        if ($username !== '') {
            $sql .= ", username = :username";
            $params['username'] = $username;
        }
        if ($password !== '') {
            $sql .= ", password_hash = :password_hash";
            $params['password_hash'] = zimrx_password_hash($password);
        }
        $sql .= " WHERE id = :id AND role = 'assistant'";
        $pdo->prepare($sql)->execute($params);
        admin_sync_assistant_doctors($pdo, $id, $doctorIds);
        return $id;
    }

    $primaryDoctorId = $doctorIds[0] ?? 1;
    $stmt = $pdo->prepare(
        "INSERT INTO zimrx_user_accounts (
            username, password_hash, display_name, role, doctor_id, is_active
        ) VALUES (
            :username, :password_hash, :display_name, 'assistant', :doctor_id, :is_active
        )"
    );
    $stmt->execute([
        'username' => $username,
        'password_hash' => zimrx_password_hash($password),
        'display_name' => $displayName,
        'doctor_id' => $primaryDoctorId,
        'is_active' => $isActive,
    ]);
    $newId = (int)$pdo->lastInsertId();
    admin_sync_assistant_doctors($pdo, $newId, $doctorIds);
    return $newId;
}

function admin_upsert_doctor(PDO $pdo, array $data): int {
    $id = (int)admin_value($data, 'id');
    $displayName = admin_value($data, 'display_name');
    $doctorCode = admin_value($data, 'doctor_code');
    $qualifications = admin_value($data, 'qualifications');
    $specialty = admin_value($data, 'specialty');
    $bmdcNo = admin_value($data, 'bmdc_no');
    $username = admin_value($data, 'username');
    $password = admin_value($data, 'password');
    $isActive = admin_value($data, 'is_active') === '0' ? 0 : 1;

    if ($displayName === '') {
        throw new RuntimeException('Doctor name is required.');
    }

    if ($id > 0) {
        $pdo->prepare(
            "UPDATE zimrx_doctors
             SET doctor_code = nullif(:doctor_code, ''),
                 display_name = :display_name,
                 full_name_en = :display_name,
                 qualifications_en = :qualifications,
                 specialty_en = :specialty,
                 bmdc_no_en = :bmdc_no,
                 is_active = :is_active,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        )->execute([
            'id' => $id,
            'doctor_code' => $doctorCode,
            'display_name' => $displayName,
            'qualifications' => $qualifications,
            'specialty' => $specialty,
            'bmdc_no' => $bmdcNo,
            'is_active' => $isActive,
        ]);
    } else {
        $pdo->prepare(
            "INSERT INTO zimrx_doctors (
                doctor_code, display_name, full_name_en, qualifications_en, specialty_en, bmdc_no_en, is_active
            ) VALUES (
                nullif(:doctor_code, ''), :display_name, :display_name, :qualifications, :specialty, :bmdc_no, :is_active
            )"
        )->execute([
            'doctor_code' => $doctorCode,
            'display_name' => $displayName,
            'qualifications' => $qualifications,
            'specialty' => $specialty,
            'bmdc_no' => $bmdcNo,
            'is_active' => $isActive,
        ]);
        $id = (int)$pdo->lastInsertId();
    }

    if ($username !== '') {
        $existing = $pdo->prepare("SELECT id FROM zimrx_user_accounts WHERE role = 'doctor' AND doctor_id = :doctor_id LIMIT 1");
        $existing->execute(['doctor_id' => $id]);
        $userId = (int)$existing->fetchColumn();
        if ($userId > 0) {
            $params = [
                'id' => $userId,
                'username' => $username,
                'display_name' => $displayName,
                'is_active' => $isActive,
            ];
            $sql = "UPDATE zimrx_user_accounts
                    SET username = :username,
                        display_name = :display_name,
                        is_active = :is_active,
                        updated_at = CURRENT_TIMESTAMP";
            if ($password !== '') {
                $sql .= ", password_hash = :password_hash";
                $params['password_hash'] = zimrx_password_hash($password);
            }
            $sql .= " WHERE id = :id";
            $pdo->prepare($sql)->execute($params);
        } elseif ($password !== '') {
            $pdo->prepare(
                "INSERT INTO zimrx_user_accounts (
                    username, password_hash, display_name, role, doctor_id, is_active
                ) VALUES (
                    :username, :password_hash, :display_name, 'doctor', :doctor_id, :is_active
                )"
            )->execute([
                'username' => $username,
                'password_hash' => zimrx_password_hash($password),
                'display_name' => $displayName,
                'doctor_id' => $id,
                'is_active' => $isActive,
            ]);
        }
    }

    return $id;
}

function admin_flash(): string {
    $message = $_SESSION['admin_flash'] ?? '';
    unset($_SESSION['admin_flash']);
    return (string)$message;
}

function admin_set_flash(string $message): void {
    $_SESSION['admin_flash'] = $message;
}
