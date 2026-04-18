<?php
require_once '../config/db.php';
requireLogin();
define('PAGE_TITLE', 'Customers');
define('PAGE_SUBTITLE', 'Manage customer records');

$branchId = getUserBranchId() ?? 1;
$search = trim($_GET['search'] ?? '');

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $name   = trim($_POST['name']);
    $phone  = trim($_POST['phone'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $addr   = trim($_POST['address'] ?? '');
    $notes  = trim($_POST['notes'] ?? '');
    if ($name) {
        if ($id) {
            $pdo->prepare("UPDATE customers SET name=?,phone=?,email=?,address=?,notes=? WHERE id=?")->execute([$name,$phone,$email,$addr,$notes,$id]);
        } else {
            $pdo->prepare("INSERT INTO customers (name,phone,email,address,notes,branch_id) VALUES (?,?,?,?,?,?)")->execute([$name,$phone,$email,$addr,$notes,$branchId]);
        }
        header('Location: /pharmacy/customers/index.php?saved=1'); exit;
    }
}

$where = ['c.status="active"'];
$params = [];
if (!isSuperAdmin()) { $where[] = '(c.branch_id=? OR c.branch_id IS NULL)'; $params[] = $branchId; }
if ($search) { $where[] = '(c.name LIKE ? OR c.phone LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
$whereStr = implode(' AND ', $where);

$customers = $pdo->prepare("
    SELECT c.*, COUNT(s.id) as total_orders, COALESCE(SUM(s.total_amount),0) as total_spent
    FROM customers c
    LEFT JOIN sales s ON s.customer_phone=c.phone AND s.status='completed'
    WHERE $whereStr
    GROUP BY c.id ORDER BY total_spent DESC
");
$customers->execute($params); $customers = $customers->fetchAll();

require_once '../includes/header.php';
?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> Customer saved.</div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;">
<!-- Add/Edit Form -->
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-user-plus" style="color:var(--primary)"></i> Add Customer</div></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="id" value="0">
            <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
            <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
            <div class="form-group"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"></textarea></div>
            <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Save Customer</button>
        </form>
    </div>
</div>

<!-- Customer List -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-users" style="color:var(--info)"></i> Customers (<?= count($customers) ?>)</div>
    </div>
    <div class="card-body" style="padding-bottom:0;">
        <form method="GET" style="display:flex;gap:10px;margin-bottom:15px;">
            <input type="text" name="search" class="form-control" placeholder="Search name or phone..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
            <a href="/pharmacy/customers/index.php" class="btn btn-outline">Reset</a>
        </form>
    </div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Name</th><th>Phone</th><th>Orders</th><th>Total Spent</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($customers)): ?>
                <tr><td colspan="5"><div class="empty-state" style="padding:20px;"><div class="empty-icon">👥</div><p>No customers found</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($customers as $c): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c['name']) ?></strong><?php if ($c['email']): ?><div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($c['email']) ?></div><?php endif; ?></td>
                    <td><?= htmlspecialchars($c['phone'] ?? '-') ?></td>
                    <td><?= $c['total_orders'] ?></td>
                    <td><strong style="color:var(--primary)"><?= formatCurrency($c['total_spent']) ?></strong></td>
                    <td>
                        <a href="/pharmacy/customers/view.php?id=<?= $c['id'] ?>" class="btn btn-info btn-sm">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
<?php require_once '../includes/footer.php'; ?>
