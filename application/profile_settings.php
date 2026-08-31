<?php
require_once 'auth.php';
require_login();
require_once 'db.php';
require_once 'admin_lib.php';

$userId = current_user_id();
$doctorId = current_user_doctor_id();

$flash = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $displayName   = trim((string)($_POST['display_name'] ?? ''));
        $qualifications = trim((string)($_POST['qualifications'] ?? ''));
        $specialty     = trim((string)($_POST['specialty'] ?? ''));
        $bmdcNo        = trim((string)($_POST['bmdc_no'] ?? ''));
        $username      = trim((string)($_POST['username'] ?? ''));
        $newPassword   = trim((string)($_POST['password'] ?? ''));

        if ($displayName === '') {
            throw new Exception('Doctor name is required.');
        }

        // Update doctor profile
        $stmt = $pdo->prepare(
            "UPDATE zimrx_doctors
             SET display_name = :display_name,
                 qualifications = :qualifications,
                 specialty = :specialty,
                 bmdc_no = :bmdc_no,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :doctor_id"
        );
        $stmt->execute([
            'display_name'   => $displayName,
            'qualifications' => $qualifications,
            'specialty'      => $specialty,
            'bmdc_no'        => $bmdcNo,
            'doctor_id'      => $doctorId,
        ]);

        // Update user account details if user exists
        if ($userId > 0) {
            $userSql = "UPDATE zimrx_user_accounts SET display_name = :display_name";
            $userParams = ['display_name' => $displayName, 'user_id' => $userId];

            if ($username !== '') {
                // Check if username is taken by another account
                $check = $pdo->prepare("SELECT id FROM zimrx_user_accounts WHERE username = :username AND id != :user_id LIMIT 1");
                $check->execute(['username' => $username, 'user_id' => $userId]);
                if ($check->fetchColumn()) {
                    throw new Exception('The username "' . $username . '" is already taken.');
                }
                $userSql .= ", username = :username";
                $userParams['username'] = $username;
                $_SESSION['user_name'] = $username;
            }

            if ($newPassword !== '') {
                $userSql .= ", password_hash = :password_hash";
                $userParams['password_hash'] = zimrx_password_hash($newPassword);
            }

            $userSql .= ", updated_at = CURRENT_TIMESTAMP WHERE id = :user_id";
            $pdo->prepare($userSql)->execute($userParams);
        }

        $flash = 'Profile settings updated successfully.';
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

// Fetch current doctor record
$doctorStmt = $pdo->prepare("SELECT * FROM zimrx_doctors WHERE id = :id LIMIT 1");
$doctorStmt->execute(['id' => $doctorId]);
$doctor = $doctorStmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Fetch current user account
$userStmt = $pdo->prepare("SELECT username, display_name FROM zimrx_user_accounts WHERE id = :id LIMIT 1");
$userStmt->execute(['id' => $userId]);
$userAccount = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$page_title = 'ZimRx - Profile Settings';
$extra_css = ['assets/css/admin.css'];
include 'header.php';
?>

<main class="admin-page">
    <section class="admin-hero">
        <div>
            <p class="eyebrow">Doctor Settings</p>
            <h1>Profile Settings</h1>
            <p>Manage your professional details, credentials, and account credentials.</p>
        </div>
    </section>

    <?php if ($flash): ?>
        <div class="admin-flash" style="<?= $flashType === 'error' ? 'background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5;' : '' ?>">
            <?= htmlspecialchars($flash) ?>
        </div>
    <?php endif; ?>

    <section class="admin-layout" style="grid-template-columns: minmax(0, 720px);">
        <div class="admin-panel">
            <h2>Personal &amp; Professional Details</h2>
            <form class="admin-form" method="post" autocomplete="off">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <label>
                        Doctor ID / Code
                        <input type="text" value="<?= htmlspecialchars($doctor['doctor_code'] ?? 'D001') ?>" readonly style="background: #f8fafc; color: #64748b; cursor: not-allowed;">
                    </label>

                    <label>
                        Doctor Full Name <span style="color: #ef4444;">*</span>
                        <input type="text" name="display_name" value="<?= htmlspecialchars($doctor['display_name'] ?? '') ?>" required placeholder="e.g. Dr. John Doe">
                    </label>
                </div>

                <label>
                    Qualifications &amp; Degrees
                    <textarea name="qualifications" rows="2" placeholder="e.g. MBBS, FCPS (Medicine), MD (Cardiology)"><?= htmlspecialchars($doctor['qualifications'] ?? $doctor['qualifications_en'] ?? '') ?></textarea>
                </label>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <label>
                        Specialty / Designation
                        <input type="text" name="specialty" value="<?= htmlspecialchars($doctor['specialty'] ?? $doctor['specialty_en'] ?? '') ?>" placeholder="e.g. Medicine Specialist">
                    </label>

                    <label>
                        BMDC Reg. No.
                        <input type="text" name="bmdc_no" value="<?= htmlspecialchars($doctor['bmdc_no'] ?? $doctor['bmdc_no_en'] ?? '') ?>" placeholder="e.g. A-12345">
                    </label>
                </div>

                <div style="border-top: 1px solid #e2e8f0; margin-top: 1.25rem; padding-top: 1.25rem;">
                    <h3 style="font-size: 1rem; font-weight: 700; color: #0f172a; margin-bottom: 0.85rem;">Account &amp; Security</h3>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <label>
                            Login Username
                            <input type="text" name="username" value="<?= htmlspecialchars($userAccount['username'] ?? '') ?>" placeholder="doctor username">
                        </label>

                        <label>
                            Change Password
                            <input type="password" name="password" placeholder="Leave blank to keep unchanged">
                        </label>
                    </div>
                </div>

                <div style="margin-top: 1.25rem;">
                    <button class="btn btn-primary" type="submit">Save Profile Changes</button>
                </div>
            </form>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
