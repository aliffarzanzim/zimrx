<?php
require_once 'auth.php';
require_login();
require_once 'db.php';
require_once 'admin_lib.php';

if (current_user_role() !== 'doctor') {
    header('Location: index.php');
    exit;
}

$doctorId = current_user_doctor_id();
$adminExists = admin_has_active_admin($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $action = admin_value($_POST, 'action') ?: 'save';
        $assistantId = (int)admin_value($_POST, 'id');

        if ($action === 'disassign' && $assistantId > 0) {
            $pdo->prepare(
                "UPDATE zimrx_doctor_assistants
                 SET is_active = 0, updated_at = CURRENT_TIMESTAMP
                 WHERE assistant_user_id = :assistant_id AND doctor_id = :doctor_id"
            )->execute(['assistant_id' => $assistantId, 'doctor_id' => $doctorId]);
            admin_set_flash('Assistant disassigned from your chamber.');
        } elseif ($action === 'deactivate' && $assistantId > 0 && !$adminExists) {
            $pdo->prepare(
                "UPDATE zimrx_user_accounts
                 SET is_active = 0, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :assistant_id AND role = 'assistant'"
            )->execute(['assistant_id' => $assistantId]);
            admin_set_flash('Assistant deactivated.');
        } else {
            if ($adminExists) {
                $_POST['is_active'] = '1';
            }
            admin_upsert_assistant($pdo, $_POST, [$doctorId]);
            admin_set_flash('Assistant saved and assigned to you.');
        }

        header('Location: doctor_assistants.php');
        exit;
    } catch (Throwable $e) {
        admin_set_flash($e->getMessage());
        header('Location: doctor_assistants.php');
        exit;
    }
}

$assistants = admin_assistant_rows($pdo, $doctorId);
$page_title = 'ZimRx - Manage Assistants';
$extra_css = ['assets/css/admin.css'];
include 'header.php';
$flash = admin_flash();
?>

<main class="admin-page">
    <section class="admin-hero">
        <div>
            <p class="eyebrow">Doctor Desk</p>
            <h1>Manage Assistants</h1>
            <p><?= $adminExists ? 'Admin exists, so you can assign or disassign assistants from your chamber.' : 'Solo mode: you can create, edit, deactivate, or disassign your assistants here.' ?></p>
        </div>
    </section>

    <?php if ($flash): ?><div class="admin-flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

    <section class="admin-layout">
        <div class="admin-panel">
            <h2>Add / Update Assistant</h2>
            <form class="admin-form" method="post">
                <input type="hidden" name="action" value="save">
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
                <button class="btn btn-primary" type="submit">Save Assistant</button>
            </form>
        </div>

        <div class="admin-panel">
            <h2>Your Assistants</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Assistant</th>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assistants as $assistant): ?>
                    <tr>
                        <td><?= (int)$assistant['id'] ?></td>
                        <td><strong><?= htmlspecialchars((string)$assistant['display_name']) ?></strong></td>
                        <td><?= htmlspecialchars((string)$assistant['username']) ?></td>
                        <td><span class="admin-pill <?= (int)$assistant['is_active'] ? '' : 'off' ?>"><?= (int)$assistant['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <form method="post" class="admin-actions">
                                <input type="hidden" name="id" value="<?= (int)$assistant['id'] ?>">
                                <button class="btn btn-secondary btn-sm" name="action" value="disassign" type="submit">Disassign</button>
                                <?php if (!$adminExists): ?>
                                <button class="btn btn-outline btn-sm" name="action" value="deactivate" type="submit">Deactivate</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$assistants): ?>
                    <tr><td colspan="5" class="admin-muted">No assistant assigned yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
