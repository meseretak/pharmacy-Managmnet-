<?php
require_once '../config/db.php';
requireLogin();
if (!isSuperAdmin()) { header('Location: /pharmacy/dashboard.php'); exit; }
define('PAGE_TITLE', 'User Management');
define('PAGE_SUBTITLE', 'Manage system users');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name      = trim($_POST['name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = $_POST['password'] ?? '';
        $roleId    = (int)$_POST['role_id'];
        $branchId  = $_POST['branch_id'] ? (int)$_POST['branch_id'] : null;

        if (!$name || !$email || !$password) {
            $error = 'Name, email and password are required.';
        } else {
            $exists = $pdo->prepare("SELECT id FROM users WHERE email=?");
            $exists->execute([$email]);
            if ($exists->fetch()) {
                $error = 'Email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (name, email, password, role_id, branch_id) VALUES (?,?,?,?,?)")
                    ->execute([$name, $email, $hash, $roleId, $branchId]);
                $success = 'User added successfully.';
                logActivity($pdo, 'Added user: ' . $email, 'users');
            }
        }
    } elseif ($action === 'toggle') {
        $uid = (int)$_POST['user_id'];
        $pdo->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id=?")->execute([$uid]);
        $success = 'User status updated.';
    } elseif ($action === 'reset_password') {
        $uid = (int)$_POST['user_id'];
        $newPwd = $_POST['new_password'] ?? '';
        if (strlen($newPwd) >= 6) {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($newPwd, PASSWORD_DEFAULT), $uid]);
            $success = 'Password reset successfully.';
        } else {
            $error = 'Password must be at least 6 characters.';
        }
    }
    header('Location: /pharmacy/admin/users.php?' . ($success ? 'success=1' : 'error=1'));
    exit;
}

if (isset($_GET['success'])) $success = 'Operation completed successfully.';
if (isset($_GET['error'])) $error = 'An error occurred.';

$users = $pdo->query("SELECT u.*, r.name as role_name, b.name as branch_name FROM users u JOIN roles r ON u.role_id=r.id LEFT JOIN branches b ON u.branch_id=b.id ORDER BY u.name")->fetchAll();
$roles = $pdo->query("SELECT * FROM roles")->fetchAll();
$branches = $pdo->query("SELECT * FROM branches WHERE status='active' ORDER BY name")->fetchAll();

require_once '../includes/header.php';
?>

<?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-users" style="color:var(--primary)"></i> System Users</div>
        <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('show')"><i class="fas fa-plus"></i> Add User</button>
    </div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Branch</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($users as $i => $u): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:34px;height:34px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;"><?= strtoupper(substr($u['name'],0,1)) ?></div>
                            <strong><?= htmlspecialchars($u['name']) ?></strong>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge badge-info"><?= ucfirst(str_replace('_',' ',$u['role_name'])) ?></span></td>
                    <td><?= $u['branch_name'] ? htmlspecialchars($u['branch_name']) : '<span class="badge badge-success">All Branches</span>' ?></td>
                    <td><span class="badge <?= $u['status']=='active'?'badge-success':'badge-secondary' ?>"><?= ucfirst($u['status']) ?></span></td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <div style="display:flex;gap:5px;">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn <?= $u['status']=='active'?'btn-warning':'btn-success' ?> btn-sm btn-icon" title="<?= $u['status']=='active'?'Deactivate':'Activate' ?>">
                                    <i class="fas fa-<?= $u['status']=='active'?'ban':'check' ?>"></i>
                                </button>
                            </form>
                            <button class="btn btn-info btn-sm btn-icon" title="Reset Password" onclick="openResetModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name']) ?>')"><i class="fas fa-key"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">Add New User</div><button class="modal-close" onclick="document.getElementById('addModal').classList.remove('show')">×</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
                </div>
                <div class="form-group"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required minlength="6"></div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Role *</label>
                        <select name="role_id" class="form-control" required onchange="toggleBranch(this.value)">
                            <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= ucfirst(str_replace('_',' ',$r['name'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="branchField">
                        <label class="form-label">Branch</label>
                        <select name="branch_id" class="form-control">
                            <option value="">All Branches (Super Admin)</option>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('addModal').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add User</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="resetModal">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header"><div class="modal-title">Reset Password</div><button class="modal-close" onclick="document.getElementById('resetModal').classList.remove('show')">×</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetUserId">
            <div class="modal-body">
                <p style="margin-bottom:15px;">Reset password for: <strong id="resetUserName"></strong></p>
                <div class="form-group"><label class="form-label">New Password *</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('resetModal').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fas fa-key"></i> Reset Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResetModal(id, name) {
    document.getElementById('resetUserId').value = id;
    document.getElementById('resetUserName').textContent = name;
    document.getElementById('resetModal').classList.add('show');
}
function toggleBranch(roleId) {
    // role 1 = super_admin, no branch needed
}
</script>

<?php require_once '../includes/footer.php'; ?>
