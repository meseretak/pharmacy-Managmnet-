<?php
require_once '../config/db.php';
requireLogin();
define('PAGE_TITLE', 'Stock Report');
define('PAGE_SUBTITLE', 'Inventory valuation & analysis');

$branchId = (int)($_GET['branch_id'] ?? 0);
$branchCond = '';
$params = [];

if (!isSuperAdmin()) {
    $branchCond = 'AND s.branch_id = ?';
    $params[] = getUserBranchId();
} elseif ($branchId) {
    $branchCond = 'AND s.branch_id = ?';
    $params[] = $branchId;
}

// Summary
$totalValue = $pdo->prepare("SELECT COALESCE(SUM(s.quantity * s.selling_price),0) FROM stock s WHERE 1=1 $branchCond");
$totalValue->execute($params); $totalValue = $totalValue->fetchColumn();

$costValue = $pdo->prepare("SELECT COALESCE(SUM(s.quantity * s.buying_price),0) FROM stock s WHERE 1=1 $branchCond");
$costValue->execute($params); $costValue = $costValue->fetchColumn();

$totalItems = $pdo->prepare("SELECT COUNT(*) FROM stock s WHERE 1=1 $branchCond");
$totalItems->execute($params); $totalItems = $totalItems->fetchColumn();

$lowItems = $pdo->prepare("SELECT COUNT(*) FROM stock s WHERE s.quantity <= s.low_stock_threshold AND s.quantity > 0 $branchCond");
$lowItems->execute($params); $lowItems = $lowItems->fetchColumn();

// Stock by category
$byCategory = $pdo->prepare("SELECT c.name, COUNT(s.id) as items, SUM(s.quantity) as total_qty, SUM(s.quantity * s.selling_price) as value FROM stock s JOIN medicines m ON s.medicine_id=m.id LEFT JOIN categories c ON m.category_id=c.id WHERE 1=1 $branchCond GROUP BY c.id ORDER BY value DESC");
$byCategory->execute($params); $byCategory = $byCategory->fetchAll();

// Full stock list
$stockList = $pdo->prepare("SELECT s.*, m.name as medicine_name, m.unit, c.name as category_name, b.name as branch_name FROM stock s JOIN medicines m ON s.medicine_id=m.id LEFT JOIN categories c ON m.category_id=c.id JOIN branches b ON s.branch_id=b.id WHERE 1=1 $branchCond ORDER BY (s.quantity * s.selling_price) DESC");
$stockList->execute($params); $stockList = $stockList->fetchAll();

$branches = $pdo->query("SELECT * FROM branches WHERE status='active' ORDER BY name")->fetchAll();

require_once '../includes/header.php';
?>

<?php if (isSuperAdmin()): ?>
<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:15px 22px;">
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Branch</label>
                <select name="branch_id" class="form-control">
                    <option value="">All Branches</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $branchId==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="grid-4" style="margin-bottom:20px;">
    <div class="stat-card green"><div class="stat-icon">💵</div><div class="stat-info"><div class="value"><?= formatCurrency($totalValue) ?></div><div class="label">Stock Value (Retail)</div></div></div>
    <div class="stat-card blue"><div class="stat-icon">💴</div><div class="stat-info"><div class="value"><?= formatCurrency($costValue) ?></div><div class="label">Stock Value (Cost)</div></div></div>
    <div class="stat-card orange"><div class="stat-icon">📦</div><div class="stat-info"><div class="value"><?= number_format($totalItems) ?></div><div class="label">Total Stock Lines</div></div></div>
    <div class="stat-card red"><div class="stat-icon">⚠️</div><div class="stat-info"><div class="value"><?= $lowItems ?></div><div class="label">Low Stock Items</div></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-chart-pie" style="color:var(--primary)"></i> Stock Value by Category</div></div>
        <div class="card-body"><div class="chart-container"><canvas id="catChart"></canvas></div></div>
    </div>
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-tags" style="color:var(--info)"></i> Category Breakdown</div></div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Category</th><th>Items</th><th>Total Qty</th><th>Value</th></tr></thead>
                <tbody>
                    <?php foreach ($byCategory as $cat): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($cat['name'] ?? 'Uncategorized') ?></strong></td>
                        <td><?= $cat['items'] ?></td>
                        <td><?= number_format($cat['total_qty']) ?></td>
                        <td><strong style="color:var(--primary)"><?= formatCurrency($cat['value']) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-list" style="color:var(--dark)"></i> Full Stock Valuation</div></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Medicine</th><th>Category</th><?php if(isSuperAdmin()):?><th>Branch</th><?php endif;?><th>Qty</th><th>Cost Price</th><th>Sell Price</th><th>Cost Value</th><th>Retail Value</th><th>Margin</th></tr></thead>
            <tbody>
                <?php foreach ($stockList as $s): ?>
                <?php $margin = $s['selling_price'] > 0 ? (($s['selling_price'] - $s['buying_price']) / $s['selling_price']) * 100 : 0; ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['medicine_name']) ?></strong><div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($s['unit']) ?></div></td>
                    <td><?= htmlspecialchars($s['category_name'] ?? '-') ?></td>
                    <?php if(isSuperAdmin()):?><td><?= htmlspecialchars($s['branch_name']) ?></td><?php endif;?>
                    <td><span class="<?= $s['quantity']==0?'text-danger':($s['quantity']<=$s['low_stock_threshold']?'text-warning':'text-success') ?> fw-bold"><?= number_format($s['quantity']) ?></span></td>
                    <td><?= formatCurrency($s['buying_price']) ?></td>
                    <td><?= formatCurrency($s['selling_price']) ?></td>
                    <td><?= formatCurrency($s['quantity'] * $s['buying_price']) ?></td>
                    <td><strong><?= formatCurrency($s['quantity'] * $s['selling_price']) ?></strong></td>
                    <td><span class="badge <?= $margin >= 30 ? 'badge-success' : ($margin >= 15 ? 'badge-warning' : 'badge-danger') ?>"><?= round($margin, 1) ?>%</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
new Chart(document.getElementById('catChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(fn($c) => $c['name'] ?? 'Uncategorized', $byCategory)) ?>,
        datasets: [{ data: <?= json_encode(array_column($byCategory, 'value')) ?>, backgroundColor: ['#1a6b3c','#3498db','#f39c12','#e74c3c','#9b59b6','#1abc9c','#e67e22','#34495e'] }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
});
</script>

<?php require_once '../includes/footer.php'; ?>
