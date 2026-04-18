<?php
require_once '../config/db.php';

if (isLoggedIn()) {
    header('Location: /pharmacy/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email = ? AND u.status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role']      = $user['role_name'];
            $_SESSION['branch_id'] = $user['branch_id'];
            logActivity($pdo, 'User logged in', 'auth');
            header('Location: /pharmacy/dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PharmaCare Pro</title>
    <link rel="icon" type="image/svg+xml" href="/pharmacy/assets/img/favicon.svg">
    <link rel="shortcut icon" href="/pharmacy/assets/img/favicon.svg">
    <link rel="stylesheet" href="/pharmacy/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <div class="logo-icon">
                <img src="/pharmacy/assets/img/logo.svg" alt="PharmaCare Pro" style="width:52px;height:52px;">
            </div>
            <h2>PharmaCare Pro</h2>
            <p>International Pharmacy Management System</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div style="position:relative;">
                    <i class="fas fa-envelope" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                    <input type="email" name="email" class="form-control" style="padding-left:38px;" placeholder="Enter your email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <div style="position:relative;">
                    <i class="fas fa-lock" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                    <input type="password" name="password" id="passwordInput" class="form-control" style="padding-left:38px;padding-right:40px;" placeholder="Enter your password" required>
                    <button type="button" onclick="togglePwd()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100" style="justify-content:center;padding:12px;">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>

        <div style="margin-top:25px;padding:15px;background:var(--light);border-radius:8px;font-size:12px;color:var(--text-muted);">
            <strong>Demo Credentials:</strong><br>
            Super Admin: admin@pharmacy.com / password<br>
            Branch Manager: manager1@pharmacy.com / password<br>
            Pharmacist: pharma1@pharmacy.com / password
        </div>
    </div>
</div>
<script>
function togglePwd() {
    const input = document.getElementById('passwordInput');
    const icon = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}
</script>
</body>
</html>
