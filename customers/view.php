<?php
require_once '../config/db.php';
requireLogin();
$id = (int)($_GET['id'] ?? 0);
$customer = $pdo->prepare("SELECT * FROM customers WHERE id=?");
$customer->execute([$id]); $customer = $customer->fetch();
if (!$customer) { header('Location: /pharmacy/customers/index.php'); exit; }

define('PAGE_TITLE', htmlspecialchars($customer['name']));
define('PAGE_SUBTITLE', 'Customer Profile');

// Sales history by phone
$sales = $pdo->prepare("
    SELECT sa.*, b.name as branch_name, u.name as cashier
    FROM sales sa
    JOIN branches b ON sa.branch_id=b.id
    JOIN users u ON sa.user_id=u.id
    WHERE sa.customer_phone=? AND sa.status='completed'
    ORDER BY sa.created_at DESC LIMIT 50
");
$sales->execute([$customer['phone']]); $sales = $sales->fetchAll();

$totalSpent = array_sum(array_column($sales, 'total_amount'));
$totalOrders = count($sales);

// Handle edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $pdo->prepare("UPDATE customers SET name=?,phone=?,email=?,address=?,notes=? WHERE id=?")
        ->execute([trim($_POST['name']),trim($_POST['phone']),trim($_POST['email']),trim($_POST['address']),trim($_POST['notes']),$id]);
    header('Location: /pharmacy/customers/view.php?id='.$id.'&saved=1'); exit;
}

require_once '../includes/header.php';
?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> Customer updated.</div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;">
    <a href="/pharmacy/customers/index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;">
<!-- Profile Card -->
<div>
    <div class="card" style="margin-bottom:15px;">
        <div class="card-body" style="text-align:center;padding:30px 20px;">
            <div style="width:70px;height:70px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;margin:0 auto 15px;"><?= strtoupper(substr($customer['name'],0,1)) ?></div>
            <div style="font-size:18px;font-weight:700;"><?= htmlspecialchars($customer['name']) ?></div>
            <?php if ($customer['phone']): ?><div style="color:var(--text-muted);margin-top:4px;"><i class="fas fa-phone"></i> <?= htmlspecialchars($customer['phone']) ?></div><?php endif; ?>
            <?php if ($customer['email']): ?><div style="color:var(--text-muted);font-size:13px;"><i class="fas fa-envelope"></i> <?= htmlspecialchars($customer['email']) ?></div><?php endif; ?>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;border-top:1px solid var(--border);">
            <div style="padding:15px;text-align:center;border-right:1px solid var(--border);">
                <div style="font-size:20px;font-weight:700;color:var(--primary);"><?= $totalOrders ?></div>
                <div style="font-size:12px;color:var(--text-muted);">Orders</div>
            </div>
            <div style="padding:15px;text-align:center;">
                <div style="font-size:16px;font-weight:700;color:var(--primary);"><?= formatCurrency($totalSpent) ?></div>
                <div style="font-size:12px;color:var(--text-muted);">Total Spent</div>
            </div>
        </div>
    </div>

    <!-- Edit Form -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-edit"></i> Edit</div></div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group"><label class="form-label">Name</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($customer['name']) ?>" required></div>
                <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>"></div>
                <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($customer['email'] ?? '') ?>"></div>
                <div class="form-group"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea></div>
                <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($customer['notes'] ?? '') ?></textarea></div>
                <button type="submit" name="update" value="1" class="btn btn-primary w-100"><i class="fas fa-save"></i> Update</button>
            </form>
        </div>
    </div>
</div>

<!-- Purchase History -->
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-history" style="color:var(--info)"></i> Purchase History</div></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Invoice</th><th>Date</th><th>Branch</th><th>Amount</th><th>Payment</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($sales)): ?>
                <tr><td colspan="6"><div class="empty-state" style="padding:20px;"><div class="empty-icon">🛒</div><p>No purchases yet</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($sales as $s): ?>
                <tr>
                    <td><strong style="color:var(--primary);"><?= htmlspecialchars($s['invoice_number']) ?></strong></td>
                    <td><?= date('d M Y H:i', strtotime($s['created_at'])) ?></td>
                    <td><?= htmlspecialchars($s['branch_name']) ?></td>
                    <td><strong><?= formatCurrency($s['total_amount']) ?></strong></td>
                    <td><span class="badge badge-info"><?= ucfirst(str_replace('_',' ',$s['payment_method'])) ?></span></td>
                    <td><a href="/pharmacy/sales/view.php?id=<?= $s['id'] ?>" class="btn btn-outline btn-sm">View</a></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
<?php require_once '../includes/footer.php'; ?>
