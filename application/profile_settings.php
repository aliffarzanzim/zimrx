<?php
declare(strict_types=1);

// Doctor profile settings: update professional credentials, BMDC registration, login username, and password.

require_once 'auth.php';
require_login();
require_once 'db.php';
require_once __DIR__ . '/lib/admin_lib.php';

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
$extra_css = ['assets/css/pages/admin.css'];
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
        <div class="admin-flash<?= $flashType === 'error' ? ' error' : '' ?>">
            <?= htmlspecialchars($flash) ?>
        </div>
    <?php endif; ?>

    <section class="admin-layout" class="admin-layout admin-layout-single">
        <div class="admin-panel">
            <h2>Personal &amp; Professional Details</h2>
            <form class="admin-form" method="post" autocomplete="off">
                
                <div class="admin-grid-2col">
                    <label>
                        Doctor ID / Code
                        <input type="text" value="<?= htmlspecialchars($doctor['doctor_code'] ?? 'D001') ?>" readonly class="admin-input-disabled">
                    </label>

                    <label>
                        Doctor Full Name <span class="admin-text-danger">*</span>
                        <input type="text" name="display_name" value="<?= htmlspecialchars($doctor['display_name'] ?? '') ?>" required placeholder="e.g. Dr. John Doe">
                    </label>
                </div>

                <label>
                    Qualifications &amp; Degrees
                    <textarea name="qualifications" rows="2" placeholder="e.g. MBBS, FCPS (Medicine), MD (Cardiology)"><?= htmlspecialchars($doctor['qualifications'] ?? $doctor['qualifications_en'] ?? '') ?></textarea>
                </label>

                <div class="admin-grid-2col">
                    <label>
                        Specialty / Designation
                        <input type="text" name="specialty" value="<?= htmlspecialchars($doctor['specialty'] ?? $doctor['specialty_en'] ?? '') ?>" placeholder="e.g. Medicine Specialist">
                    </label>

                    <label>
                        BMDC Reg. No.
                        <input type="text" name="bmdc_no" value="<?= htmlspecialchars($doctor['bmdc_no'] ?? $doctor['bmdc_no_en'] ?? '') ?>" placeholder="e.g. A-12345">
                    </label>
                </div>

                <div class="admin-section-divider">
                    <h3 class="admin-section-title">Account &amp; Security</h3>
                    
                    <div class="admin-grid-2col">
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

                <div class="btn btn-primary admin-mt-20">
                    <button class="btn btn-primary" type="submit">Save Profile Changes</button>
                </div>
            </form>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
