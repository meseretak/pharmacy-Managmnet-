<?php
require_once '../config/db.php';
requireLogin();
define('PAGE_TITLE', 'My Profile');
define('PAGE_SUBTITLE', 'Account settings');

$user = $pdo->prepare("SELECT u.*, r.name as role_name, b.name as branch_name FROM users u JOIN roles r ON u.role_id=r.id LEFT JOIN branches b ON u.branch_id=b.id WHERE u.id=?");
$user->execute([$_SESSION['user_id']]);
$user = $user->fetch();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $pdo->prepare("UPDATE users SET name=? WHERE id=?")->execute([$name, $_SESSION['user_id']]);
            $_SESSION['user_name'] = $name;
            $success = 'Profile updated.';
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            $success = 'Password changed successfully.';
        }
    }
    // Refresh user data
    $stmt = $pdo->prepare("SELECT u.*, r.name as role_name, b.name as branch_name FROM users u JOIN roles r ON u.role_id=r.id LEFT JOIN branches b ON u.branch_id=b.id WHERE u.id=?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}

require_once '../includes/header.php';
?>

<?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px;">
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-user" style="color:var(--primary)"></i> Profile Info</div></div>
        <div class="card-body">
            <div style="text-align:center;margin-bottom:20px;">
                <div style="width:80px;height:80px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;margin:0 auto 10px;"><?= strtoupper(substr($user['name'],0,1)) ?></div>
                <div style="font-size:18px;font-weight:700;"><?= htmlspecialchars($user['name']) ?></div>
                <div><span class="badge badge-info"><?= ucfirst(str_replace('_',' ',$user['role_name'])) ?></span></div>
                <?php if ($user['branch_name']): ?>
                <div style="margin-top:5px;font-size:13px;color:var(--text-muted);"><?= htmlspecialchars($user['branch_name']) ?></div>
                <?php endif; ?>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required></div>
                <div class="form-group"><label class="form-label">Email</label><input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled></div>
                <button type="submit" class="btn btn-primary w-100" style="justify-content:center;"><i class="fas fa-save"></i> Update Profile</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-lock" style="color:var(--warning)"></i> Change Password</div></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
                <div class="form-group"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
                <div class="form-group"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required></div>
                <button type="submit" class="btn btn-warning w-100" style="justify-content:center;"><i class="fas fa-key"></i> Change Password</button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
