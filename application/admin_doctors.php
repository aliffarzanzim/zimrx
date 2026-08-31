<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';
require_once 'admin_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        admin_upsert_doctor($pdo, $_POST);
        admin_set_flash('Doctor saved.');
        header('Location: admin_doctors.php');
        exit;
    } catch (Throwable $e) {
        admin_set_flash($e->getMessage());
        header('Location: admin_doctors.php');
        exit;
    }
}

$doctors = admin_all_doctors($pdo);
$page_title = 'ZimRx - Manage Doctors';
$extra_css = ['assets/css/admin.css'];
include 'header.php';
$flash = admin_flash();
?>

<main class="admin-page">
    <section class="admin-hero">
        <div>
            <p class="eyebrow">Manage Doctors</p>
            <h1>Doctor accounts and profiles</h1>
            <p>Create doctors now; later each one can run isolated SQLite or central shared database mode.</p>
        </div>
    </section>

    <?php if ($flash): ?><div class="admin-flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

    <section class="admin-layout">
        <div class="admin-panel">
            <h2>Add / Update Doctor</h2>
            <form class="admin-form" method="post">
                <label>Existing Doctor ID <input name="id" placeholder="Blank for new"></label>
                <label>Doctor Code <input name="doctor_code" placeholder="D001"></label>
                <label>Doctor Name <input name="display_name" required placeholder="Dr. Name"></label>
                <label>Qualifications <textarea name="qualifications"></textarea></label>
                <label>Specialty <input name="specialty"></label>
                <label>BMDC No <input name="bmdc_no"></label>
                <label>Login Username <input name="username" placeholder="doctor username"></label>
                <label>Password <input name="password" type="password" placeholder="Required only for new login/change"></label>
                <label>Status
                    <select name="is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </label>
                <button class="btn btn-primary" type="submit">Save Doctor</button>
            </form>
        </div>

        <div class="admin-panel">
            <h2>Doctors</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Specialty</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doctors as $doctor): ?>
                    <tr>
                        <td><?= (int)$doctor['id'] ?></td>
                        <td><?= htmlspecialchars((string)$doctor['doctor_code']) ?></td>
                        <td>
                            <strong><?= htmlspecialchars((string)$doctor['display_name']) ?></strong>
                            <?php if (!empty($doctor['qualifications'])): ?>
                            <div class="admin-muted"><?= htmlspecialchars((string)$doctor['qualifications']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars((string)$doctor['specialty']) ?></td>
                        <td><span class="admin-pill <?= (int)$doctor['is_active'] ? '' : 'off' ?>"><?= (int)$doctor['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
