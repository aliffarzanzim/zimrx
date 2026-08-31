<?php
// db_viewer.php - A simple SQLite web-based admin tool.
// WARNING: FOR DEVELOPMENT USE ONLY. DO NOT DEPLOY TO A PUBLIC SERVER.

require_once 'db.php';

$message = isset($_GET['message']) ? urldecode($_GET['message']) : '';
$message_type = isset($_GET['type']) ? $_GET['type'] : '';
$selected_table = isset($_GET['table']) ? $_GET['table'] : null;
$tables = [];
$table_info = [];
$table_data = [];
$pk_column = null;
$sql_results = null;

function redirect_with_message($table, $message, $type, $anchor = 'data-table') {
    $message = urlencode($message);
    header("Location: ?table={$table}&message={$message}&type={$type}#{$anchor}");
    exit();
}

try {
    // --- HANDLE POST ACTIONS (Add, Delete, Update, Run SQL) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'delete_row' && isset($_POST['table'], $_POST['pk_value'], $_POST['pk_column'])) {
            $table = $_POST['table'];
            $stmt = $pdo->prepare("DELETE FROM \"{$table}\" WHERE \"{$_POST['pk_column']}\" = ?");
            $stmt->execute([$_POST['pk_value']]);
            redirect_with_message($table, "Row deleted successfully from '{$table}'.", 'success');
        }

        if ($action === 'add_row' && isset($_POST['table'])) {
            $table = $_POST['table'];
            $columns = array_keys($_POST['columns']);
            $placeholders = array_fill(0, count($columns), '?');
            $values = array_values($_POST['columns']);
            $sql = "INSERT INTO \"$table\" (\"" . implode('", "', $columns) . "\") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            redirect_with_message($table, "New row added successfully to '$table'.", 'success', 'add-form');
        }

        if ($action === 'update_row' && isset($_POST['table'], $_POST['pk_value'], $_POST['pk_column'])) {
            $table = $_POST['table'];
            $set_parts = [];
            $values = [];
            foreach ($_POST['columns'] as $col => $val) {
                $set_parts[] = "\"$col\" = ?";
                $values[] = $val;
            }
            $values[] = $_POST['pk_value']; // Add pk_value for the WHERE clause
            $sql = "UPDATE \"$table\" SET " . implode(', ', $set_parts) . " WHERE \"{$_POST['pk_column']}\" = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            redirect_with_message($table, "Row updated successfully in '$table'.", 'success');
        }

        if ($action === 'run_sql' && isset($_POST['sql_query'])) {
            $sql = trim($_POST['sql_query']);
            if (!empty($sql)) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                if (stripos($sql, 'SELECT') === 0) {
                    $sql_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $message = count($sql_results) . " rows returned.";
                } else {
                    $message = $stmt->rowCount() . " rows affected.";
                }
                $message_type = 'success';
            }
        }
    }

    // --- FETCH DATABASE STRUCTURE AND DATA ---
    $tables = DbSchema::tables($pdo);

    if ($selected_table && in_array($selected_table, $tables)) {
        $table_info = DbSchema::columnInfo($pdo, $selected_table);
        foreach ($table_info as $column) {
            if ($column['pk'] == 1) $pk_column = $column['name'];
        }
        $stmt = $pdo->query("SELECT * FROM \"$selected_table\"");
        $table_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $message = 'Error: ' . $e->getMessage();
    $message_type = 'error';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SQLite DB Viewer</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <style>
        :root { --primary: #2563eb; --bg-main: #f1f5f9; --bg-card: #ffffff; --text-dark: #0f172a; --text-muted: #64748b; --border-color: #cbd5e1; --danger: #dc2626; --success: #16a34a;}
        body { font-family: system-ui, sans-serif; margin: 0; background-color: var(--bg-main); color: var(--text-dark); display: flex; height: 100vh; }
        .sidebar { flex: 0 0 220px; background-color: var(--bg-card); border-right: 1px solid var(--border-color); padding: 1rem; overflow-y: auto; }
        .sidebar h2 { font-size: 1.2rem; margin: 0 0 1rem 0; }
        .sidebar ul { list-style: none; margin: 0; padding: 0; }
        .sidebar a { display: block; padding: 0.5rem 0.75rem; text-decoration: none; color: var(--text-dark); border-radius: 6px; }
        .sidebar a:hover { background-color: var(--bg-main); }
        .sidebar a.active { background-color: var(--primary); color: white; font-weight: 500; }
        .main-content { flex: 1; padding: 2rem; overflow-y: auto; }
        h1, h2, h3 { scroll-margin-top: 1rem; }
        table { border-collapse: collapse; width: 100%; margin-top: 1rem; background-color: var(--bg-card); box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        th, td { border: 1px solid var(--border-color); padding: 0.75rem; text-align: left; vertical-align: middle; }
        th { background-color: #f8fafc; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); }
        .message { padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; }
        .message.success { background-color: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .message.error { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .form-section { background-color: var(--bg-card); padding: 1.5rem; border-radius: 8px; margin-top: 2rem; border: 1px solid var(--border-color); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; }
        .form-group { display: flex; flex-direction: column; }
        label { margin-bottom: 0.25rem; font-size: 0.85rem; font-weight: 500; }
        input[type="text"], textarea { padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px; font: inherit; width: 100%; box-sizing: border-box;}
        .btn { padding: 0.6rem 1rem; border: 1px solid transparent; border-radius: 6px; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.9rem; }
        .btn-primary { background-color: var(--primary); color: white; }
        .btn-success { background-color: var(--success); color: white; }
        .btn-secondary { background-color: #e2e8f0; color: var(--text-dark); }
        .btn-danger { background-color: var(--danger); color: white; }
        .btn-sm { font-size: 0.8rem; padding: 0.25rem 0.5rem; }
        .actions { margin-top: 1rem; display: flex; gap: 0.5rem; }
        .edit-mode { display: none; }
        td .actions { margin-top: 0;}
    </style>
</head>
<body>

<aside class="sidebar">
    <h2>Tables</h2>
    <ul>
        <?php foreach ($tables as $table): ?>
            <li><a href="?table=<?= htmlspecialchars($table) ?>" class="<?= $selected_table === $table ? 'active' : '' ?>"><?= htmlspecialchars($table) ?></a></li>
        <?php endforeach; ?>
    </ul>
</aside>

<main class="main-content">
    <h1>SQLite Database Viewer</h1>
    <?php if ($message): ?><div class="message <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="form-section">
        <h3>Run SQL Command</h3>
        <form method="POST">
            <input type="hidden" name="action" value="run_sql">
            <textarea name="sql_query" rows="4" style="width: 100%;" placeholder="e.g., SELECT * FROM occupations;"><?= isset($_POST['sql_query']) ? htmlspecialchars($_POST['sql_query']) : '' ?></textarea>
            <div class="actions"><button type="submit" class="btn btn-primary">Execute</button></div>
        </form>
        <?php if ($sql_results !== null): /* SQL Results Display Logic */ endif; ?>
    </div>

    <?php if ($selected_table): ?>
        <h2 id="data-table">Table: <strong><?= htmlspecialchars($selected_table) ?></strong></h2>
        <?php if (empty($table_data)): ?>
            <p>This table is empty.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <?php foreach ($table_info as $column): ?><th><?= htmlspecialchars($column['name']) ?><br><small><?= htmlspecialchars($column['type']) ?></small></th><?php endforeach; ?>
                    <?php if ($pk_column): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($table_data as $row): ?>
                <tr>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_row">
                        <input type="hidden" name="table" value="<?= htmlspecialchars($selected_table) ?>">
                        <input type="hidden" name="pk_column" value="<?= htmlspecialchars($pk_column) ?>">
                        <input type="hidden" name="pk_value" value="<?= htmlspecialchars($row[$pk_column]) ?>">
                        <?php foreach ($table_info as $column): ?>
                            <td>
                                <span class="view-mode"><?= htmlspecialchars($row[$column['name']]) ?></span>
                                <input type="text" name="columns[<?= htmlspecialchars($column['name']) ?>]" value="<?= htmlspecialchars($row[$column['name']]) ?>" class="edit-mode" <?= ($column['pk']) ? 'readonly' : '' ?>>
                            </td>
                        <?php endforeach; ?>
                        <?php if ($pk_column): ?>
                        <td>
                            <div class="actions view-mode">
                                <button type="button" class="btn btn-sm btn-secondary edit-btn">Edit</button>
                                <button type="submit" form="delete_form_<?= htmlspecialchars($row[$pk_column]) ?>" class="btn btn-sm btn-danger">Delete</button>
                            </div>
                            <div class="actions edit-mode">
                                <button type="submit" class="btn btn-sm btn-success">Save</button>
                                <button type="button" class="btn btn-sm btn-secondary cancel-btn">Cancel</button>
                            </div>
                        </td>
                        <?php endif; ?>
                    </form>
                    <form method="POST" id="delete_form_<?= htmlspecialchars($row[$pk_column]) ?>" onsubmit="return confirm('Are you sure?');">
                        <input type="hidden" name="action" value="delete_row">
                        <input type="hidden" name="table" value="<?= htmlspecialchars($selected_table) ?>">
                        <input type="hidden" name="pk_column" value="<?= htmlspecialchars($pk_column) ?>">
                        <input type="hidden" name="pk_value" value="<?= htmlspecialchars($row[$pk_column]) ?>">
                    </form>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="form-section" id="add-form">
            <h3>Add New Row to '<?= htmlspecialchars($selected_table) ?>'</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_row">
                <input type="hidden" name="table" value="<?= htmlspecialchars($selected_table) ?>">
                <div class="form-grid">
                    <?php foreach ($table_info as $column): if ($column['pk'] == 1) continue; ?>
                        <div class="form-group">
                            <label for="col_<?= htmlspecialchars($column['name']) ?>"><?= htmlspecialchars($column['name']) ?></label>
                            <input type="text" name="columns[<?= htmlspecialchars($column['name']) ?>]" id="col_<?= htmlspecialchars($column['name']) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="actions"><button type="submit" class="btn btn-primary">Add Row</button></div>
            </form>
        </div>
    <?php else: ?>
        <p>Select a table from the left sidebar to view its data.</p>
    <?php endif; ?>
</main>
<script>
document.addEventListener('DOMContentLoaded', function() {
    function toggleEditMode(row, isEditing) {
        const viewElements = row.querySelectorAll('.view-mode');
        const editElements = row.querySelectorAll('.edit-mode');
        
        viewElements.forEach(el => el.style.display = isEditing ? 'none' : '');
        editElements.forEach(el => el.style.display = isEditing ? 'block' : 'none');
    }

    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            toggleEditMode(row, true);
        });
    });

    document.querySelectorAll('.cancel-btn').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            toggleEditMode(row, false);
            // Optional: reset input values to original if needed
        });
    });
});
</script>
</body>
</html>
