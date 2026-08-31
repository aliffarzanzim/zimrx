<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';
require_once 'admin_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $doctorIds = $_POST['doctor_ids'] ?? [];
        admin_upsert_assistant($pdo, $_POST, is_array($doctorIds) ? $doctorIds : []);
        admin_set_flash('Assistant saved.');
        header('Location: admin_assistants.php');
        exit;
    } catch (Throwable $e) {
        admin_set_flash($e->getMessage());
        header('Location: admin_assistants.php');
        exit;
    }
}

$doctors = admin_all_doctors($pdo, true);
$assistants = admin_assistant_rows($pdo);
$page_title = 'ZimRx - Manage Assistants';
$extra_css = ['assets/css/admin.css'];
include 'header.php';
$flash = admin_flash();
?>

<main class="admin-page">
    <section class="admin-hero">
        <div>
            <p class="eyebrow">Manage Assistants</p>
            <h1>Assistant accounts and doctor assignment</h1>
            <p>One assistant can be assigned to one doctor or multiple doctors.</p>
        </div>
    </section>

    <?php if ($flash): ?><div class="admin-flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

    <section class="admin-layout">
        <div class="admin-panel">
            <h2>Add / Update Assistant</h2>
            <form class="admin-form" method="post">
                <label>Existing Assistant ID <input name="id" placeholder="Blank for new"></label>
                <label>Username <input name="username" placeholder="assistant username"></label>
                <label>Assistant Name <input name="display_name" required placeholder="Assistant name"></label>
                <label>Password <input name="password" type="password" placeholder="Required only for new/change"></label>
                <label>Status
                    <select name="is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </label>
                <div>
                    <div class="admin-muted" style="margin-bottom: .35rem; font-weight: 800;">Assigned Doctors</div>
                    <div class="doctor-check-grid">
                        <?php foreach ($doctors as $doctor): ?>
                        <label>
                            <input type="checkbox" name="doctor_ids[]" value="<?= (int)$doctor['id'] ?>">
                            <?= htmlspecialchars((string)$doctor['display_name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button class="btn btn-primary" type="submit">Save Assistant</button>
            </form>
        </div>

        <div class="admin-panel">
            <h2>Assistants</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Assistant</th>
                        <th>Username</th>
                        <th>Assigned Doctors</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assistants as $assistant): ?>
                    <tr>
                        <td><?= (int)$assistant['id'] ?></td>
                        <td><strong><?= htmlspecialchars((string)$assistant['display_name']) ?></strong></td>
                        <td><?= htmlspecialchars((string)$assistant['username']) ?></td>
                        <td><?= htmlspecialchars((string)($assistant['assigned_doctors'] ?: 'Not assigned')) ?></td>
                        <td><span class="admin-pill <?= (int)$assistant['is_active'] ? '' : 'off' ?>"><?= (int)$assistant['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
