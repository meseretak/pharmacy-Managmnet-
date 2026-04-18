<?php
require_once '../config/db.php';
requireLogin();
if (!isSuperAdmin()) { header('Location: /pharmacy/dashboard.php'); exit; }
$id = (int)($_GET['id'] ?? 0);
$branch = $pdo->prepare("SELECT * FROM branches WHERE id=?");
$branch->execute([$id]);
$branch = $branch->fetch();
if (!$branch) { header('Location: /pharmacy/branches/index.php'); exit; }

define('PAGE_TITLE', htmlspecialchars($branch['name']));
define('PAGE_SUBTITLE', 'Branch Details & Performance');

// Branch stats
$monthSales = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE branch_id=? AND MONTH(created_at)=MONTH(CURDATE()) AND status='completed'");
$monthSales->execute([$id]); $monthSales = $monthSales->fetchColumn();

$todaySales = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE branch_id=? AND DATE(created_at)=CURDATE() AND status='completed'");
$todaySales->execute([$id]); $todaySales = $todaySales->fetchColumn();

$totalStock = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM stock WHERE branch_id=?");
$totalStock->execute([$id]); $totalStock = $totalStock->fetchColumn();

$lowStock = $pdo->prepare("SELECT COUNT(*) FROM stock WHERE branch_id=? AND quantity <= low_stock_threshold");
$lowStock->execute([$id]); $lowStock = $lowStock->fetchColumn();

$staff = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id=r.id WHERE u.branch_id=? ORDER BY u.name");
$staff->execute([$id]); $staff = $staff->fetchAll();

$recentSales = $pdo->prepare("SELECT sa.*, u.name as cashier FROM sales sa JOIN users u ON sa.user_id=u.id WHERE sa.branch_id=? ORDER BY sa.created_at DESC LIMIT 10");
$recentSales->execute([$id]); $recentSales = $recentSales->fetchAll();

$stockList = $pdo->prepare("SELECT s.*, m.name as medicine_name, m.unit FROM stock s JOIN medicines m ON s.medicine_id=m.id WHERE s.branch_id=? ORDER BY s.quantity ASC LIMIT 15");
$stockList->execute([$id]); $stockList = $stockList->fetchAll();

require_once '../includes/header.php';
?>

<a href="/pharmacy/branches/index.php" class="btn btn-outline btn-sm mb-2"><i class="fas fa-arrow-left"></i> Back to Branches</a>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin:15px 0 20px;">
    <div class="stat-card green"><div class="stat-icon">💰</div><div class="stat-info"><div class="value"><?= formatCurrency($todaySales) ?></div><div class="label">Today's Sales</div></div></div>
    <div class="stat-card blue"><div class="stat-icon">📈</div><div class="stat-info"><div class="value"><?= formatCurrency($monthSales) ?></div><div class="label">This Month</div></div></div>
    <div class="stat-card blue"><div class="stat-icon">📦</div><div class="stat-info"><div class="value"><?= number_format($totalStock) ?></div><div class="label">Total Stock</div></div></div>
    <div class="stat-card orange"><div class="stat-icon">⚠️</div><div class="stat-info"><div class="value"><?= $lowStock ?></div><div class="label">Low Stock Items</div></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <!-- Branch Info -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-building" style="color:var(--primary)"></i> Branch Information</div></div>
        <div class="card-body">
            <table style="width:100%;">
                <tr><td style="padding:8px 0;color:var(--text-muted);width:40%;">Name</td><td><strong><?= htmlspecialchars($branch['name']) ?></strong></td></tr>
                <tr><td style="padding:8px 0;color:var(--text-muted);">Location</td><td><?= htmlspecialchars($branch['location']) ?></td></tr>
                <tr><td style="padding:8px 0;color:var(--text-muted);">Phone</td><td><?= htmlspecialchars($branch['phone']) ?></td></tr>
                <tr><td style="padding:8px 0;color:var(--text-muted);">Email</td><td><?= htmlspecialchars($branch['email']) ?></td></tr>
                <tr><td style="padding:8px 0;color:var(--text-muted);">Status</td><td><span class="badge <?= $branch['status']=='active'?'badge-success':'badge-secondary' ?>"><?= ucfirst($branch['status']) ?></span></td></tr>
                <tr><td style="padding:8px 0;color:var(--text-muted);">Created</td><td><?= date('d M Y', strtotime($branch['created_at'])) ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Staff -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-users" style="color:var(--info)"></i> Staff (<?= count($staff) ?>)</div></div>
        <?php if (empty($staff)): ?>
        <div class="card-body"><div class="empty-state"><div class="empty-icon">👥</div><p>No staff assigned</p></div></div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Name</th><th>Role</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($staff as $s): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($s['name']) ?></strong><div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($s['email']) ?></div></td>
                        <td><span class="badge badge-info"><?= ucfirst(str_replace('_',' ',$s['role_name'])) ?></span></td>
                        <td><span class="badge <?= $s['status']=='active'?'badge-success':'badge-secondary' ?>"><?= ucfirst($s['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">
    <!-- Stock -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-boxes" style="color:var(--warning)"></i> Stock Overview</div><a href="/pharmacy/stock/index.php?branch_id=<?= $id ?>" class="btn btn-outline btn-sm">View All</a></div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Medicine</th><th>Qty</th><th>Price</th></tr></thead>
                <tbody>
                    <?php foreach ($stockList as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['medicine_name']) ?></td>
                        <td><span class="<?= $s['quantity']==0?'text-danger':($s['quantity']<=$s['low_stock_threshold']?'text-warning':'text-success') ?> fw-bold"><?= $s['quantity'] ?></span></td>
                        <td><?= formatCurrency($s['selling_price']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Sales -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-receipt" style="color:var(--secondary)"></i> Recent Sales</div><a href="/pharmacy/sales/index.php?branch_id=<?= $id ?>" class="btn btn-outline btn-sm">View All</a></div>
        <?php if (empty($recentSales)): ?>
        <div class="card-body"><div class="empty-state"><div class="empty-icon">🧾</div><p>No sales yet</p></div></div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Invoice</th><th>Amount</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ($recentSales as $sale): ?>
                    <tr>
                        <td><a href="/pharmacy/sales/view.php?id=<?= $sale['id'] ?>" style="color:var(--primary);"><?= htmlspecialchars($sale['invoice_number']) ?></a></td>
                        <td><strong><?= formatCurrency($sale['total_amount']) ?></strong></td>
                        <td style="font-size:12px;color:var(--text-muted);"><?= date('d M H:i', strtotime($sale['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
