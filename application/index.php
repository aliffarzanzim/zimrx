<?php
require_once 'auth.php';
require_once 'db.php';

// ── First-launch gate ─────────────────────────────────────────────────────
if (zimrx_db_table_exists($pdo, 'zimrx_app_config')) {
    $stmt = $pdo->query("SELECT config_key, config_value FROM zimrx_app_config");
    $appConfig = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];
} else {
    $appConfig = [];
}

if (($appConfig['setup_complete'] ?? '0') !== '1') {
    header('Location: first_launch.php');
    exit();
}

// ── Auto-login (solo doctor, autologin enabled) ────────────────────────────
if (!is_logged_in()
    && empty($_GET['logout'])
    && ($appConfig['practice_type'] ?? '') === 'solo'
    && ($appConfig['auto_login'] ?? '0') === '1'
) {
    $user = $pdo->query(
        "SELECT id, display_name FROM zimrx_user_accounts WHERE role = 'doctor' AND doctor_id = 1 AND is_active = 1 LIMIT 1"
    )->fetch();
    if ($user) {
        $_SESSION['user_id']   = (int)$user['id'];
        $_SESSION['user_role'] = 'doctor';
        $_SESSION['user_name'] = $user['display_name'];
        $_SESSION['doctor_id'] = 1;
        header('Location: prescription.php');
        exit();
    }
}

$redirect = !empty($_REQUEST['redirect']) ? trim((string)$_REQUEST['redirect']) : '';
if ($redirect !== '' && (str_starts_with($redirect, 'http://') || str_starts_with($redirect, 'https://') || str_starts_with($redirect, '//'))) {
    $redirect = '';
}

// If already logged in, redirect to appropriate page
if (is_logged_in()) {
    if ($redirect !== '') {
        header("Location: " . $redirect);
        exit();
    }
    header("Location: " . (current_user_role() === 'admin' ? 'admin.php' : (current_user_role() === 'assistant' ? 'appointments.php' : 'prescription.php')));
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare(
        "SELECT id, username, password_hash, display_name, role, doctor_id
         FROM zimrx_user_accounts
         WHERE username = :username AND is_active = 1
         LIMIT 1"
    );
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && hash_equals($user['password_hash'], zimrx_password_hash($password))) {
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_role'] = strtolower($user['role']);
        $_SESSION['user_name'] = $user['display_name'];
        $_SESSION['doctor_id'] = max(1, (int)($user['doctor_id'] ?? 1));
        if ($redirect !== '') {
            header("Location: " . $redirect);
            exit();
        }
        header("Location: " . ($_SESSION['user_role'] === 'admin' ? 'admin.php' : ($_SESSION['user_role'] === 'assistant' ? 'appointments.php' : 'prescription.php')));
        exit();
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZimRx - Physician Login</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/global.css">
    <style>
        html {
            min-height: 100%;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
        }

        .login-card {
            background: var(--bg-card, #ffffff);
            padding: 2rem 4rem 1.75rem;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            width: 100%;
            max-width: 540px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .login-header {
            margin-bottom: 1rem;
        }

        .login-header svg {
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .login-header p {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .form-group {
            text-align: left;
            margin-bottom: 1rem;
            display: block;
        }

        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.35rem;
        }

        .login-input {
            width: 100%;
            height: 42px;
            padding: 0 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            background: #f8fafc;
            color: var(--text-dark);
            transition: var(--transition);
        }

        .login-input:focus {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
            outline: none;
        }

        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
            border: 1px solid #fecaca;
        }

        .btn-login {
            width: 100%;
            height: 44px;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-top: 0.25rem;
        }

        .login-footer {
            margin-top: 1.25rem;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
            </svg>
            <h1>ZimRx Login</h1>
            <p>Please enter your credentials</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?php if ($redirect !== ''): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="login-input" placeholder="doctor" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="login-input" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary btn-login">Sign In</button>
        </form>

        <div style="margin-top: 1.15rem; margin-bottom: 0.25rem;">
            <a href="forgot_password.php" style="font-size: 0.85rem; color: var(--primary); text-decoration: none; font-weight: 500;">Forgot Password?</a>
        </div>

        <div class="login-footer">
            Powered by ZimRx EMR System
        </div>
    </div>

</body>
</html>
