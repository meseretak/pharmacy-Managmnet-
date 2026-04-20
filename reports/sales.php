<?php
require_once '../config/db.php';
requireLogin();
define('PAGE_TITLE', 'Sales Report');
define('PAGE_SUBTITLE', 'Revenue & transaction analytics');

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');
$branchId = (int)($_GET['branch_id'] ?? 0);

$branchCond = '';
$params = [$dateFrom, $dateTo];

if (!isSuperAdmin()) {
    $branchCond = 'AND sa.branch_id = ?';
    $params[] = getUserBranchId();
} elseif ($branchId) {
    $branchCond = 'AND sa.branch_id = ?';
    $params[] = $branchId;
}

// Summary
$summary = $pdo->prepare("SELECT COUNT(*) as total_transactions, COALESCE(SUM(total_amount),0) as total_revenue, COALESCE(SUM(discount),0) as total_discount, COALESCE(AVG(total_amount),0) as avg_sale FROM sales sa WHERE DATE(sa.created_at) BETWEEN ? AND ? AND sa.status='completed' $branchCond");
$summary->execute($params);
$summary = $summary->fetch();

// Daily breakdown
$daily = $pdo->prepare("SELECT DATE(sa.created_at) as date, COUNT(*) as transactions, SUM(total_amount) as revenue FROM sales sa WHERE DATE(sa.created_at) BETWEEN ? AND ? AND sa.status='completed' $branchCond GROUP BY DATE(sa.created_at) ORDER BY date ASC");
$daily->execute($params);
$daily = $daily->fetchAll();

// Top medicines
$topMeds = $pdo->prepare("SELECT m.name, SUM(si.quantity) as qty, SUM(si.subtotal) as revenue FROM sale_items si JOIN medicines m ON si.medicine_id=m.id JOIN sales sa ON si.sale_id=sa.id WHERE DATE(sa.created_at) BETWEEN ? AND ? AND sa.status='completed' $branchCond GROUP BY si.medicine_id ORDER BY revenue DESC LIMIT 10");
$topMeds->execute($params);
$topMeds = $topMeds->fetchAll();

// Payment methods
$payments = $pdo->prepare("SELECT payment_method, COUNT(*) as count, SUM(total_amount) as total FROM sales sa WHERE DATE(sa.created_at) BETWEEN ? AND ? AND sa.status='completed' $branchCond GROUP BY payment_method");
$payments->execute($params);
$payments = $payments->fetchAll();

$branches = $pdo->query("SELECT * FROM branches WHERE status='active' ORDER BY name")->fetchAll();

require_once '../includes/header.php';
?>

<!-- Filters -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:15px 22px;">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="margin:0;"><label class="form-label">From Date</label><input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>"></div>
            <div class="form-group" style="margin:0;"><label class="form-label">To Date</label><input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>"></div>
            <?php if (isSuperAdmin()): ?>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Branch</label>
                <select name="branch_id" class="form-control">
                    <option value="">All Branches</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $branchId==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary"><i class="fas fa-chart-bar"></i> Generate Report</button>
            <a href="?date_from=<?= date('Y-m-01') ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-outline">This Month</a>
            <a href="?date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-outline">Today</a>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid-4" style="margin-bottom:20px;">
    <div class="stat-card green"><div class="stat-icon">💰</div><div class="stat-info"><div class="value"><?= formatCurrency($summary['total_revenue']) ?></div><div class="label">Total Revenue</div></div></div>
    <div class="stat-card blue"><div class="stat-icon">🧾</div><div class="stat-info"><div class="value"><?= number_format($summary['total_transactions']) ?></div><div class="label">Transactions</div></div></div>
    <div class="stat-card orange"><div class="stat-icon">📊</div><div class="stat-info"><div class="value"><?= formatCurrency($summary['avg_sale']) ?></div><div class="label">Avg Sale Value</div></div></div>
    <div class="stat-card red"><div class="stat-icon">🏷️</div><div class="stat-info"><div class="value"><?= formatCurrency($summary['total_discount']) ?></div><div class="label">Total Discounts</div></div></div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px;">
    <!-- Daily Chart -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-chart-bar" style="color:var(--primary)"></i> Daily Sales</div></div>
        <div class="card-body"><div class="chart-container"><canvas id="dailyChart"></canvas></div></div>
    </div>

    <!-- Payment Methods -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-credit-card" style="color:var(--info)"></i> Payment Methods</div></div>
        <div class="card-body">
            <div class="chart-container" style="height:200px;"><canvas id="paymentChart"></canvas></div>
            <div style="margin-top:15px;">
                <?php foreach ($payments as $p): ?>
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f0f0f0;">
                    <span><?= ucfirst(str_replace('_',' ',$p['payment_method'])) ?></span>
                    <strong><?= formatCurrency($p['total']) ?> (<?= $p['count'] ?>)</strong>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top Medicines -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-trophy" style="color:var(--warning)"></i> Top Selling Medicines</div></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>#</th><th>Medicine</th><th>Units Sold</th><th>Revenue</th><th>Revenue Share</th></tr></thead>
            <tbody>
                <?php $maxRev = $topMeds[0]['revenue'] ?? 1; ?>
                <?php foreach ($topMeds as $i => $med): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($med['name']) ?></strong></td>
                    <td><?= number_format($med['qty']) ?></td>
                    <td><strong style="color:var(--primary)"><?= formatCurrency($med['revenue']) ?></strong></td>
                    <td style="min-width:150px;">
                        <div class="progress"><div class="progress-bar green" style="width:<?= ($med['revenue']/$maxRev)*100 ?>%"></div></div>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><?= round(($med['revenue']/$summary['total_revenue'])*100, 1) ?>%</div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Detailed transactions
$transactions = $pdo->prepare("
    SELECT sa.id, sa.invoice_number, sa.customer_name, sa.customer_phone, sa.total_amount,
           sa.discount, sa.payment_method, sa.status, sa.created_at,
           b.name as branch_name, u.name as pharmacist_name, r.name as pharmacist_role
    FROM sales sa
    JOIN branches b ON sa.branch_id = b.id
    JOIN users u ON sa.user_id = u.id
    JOIN roles r ON u.role_id = r.id
    WHERE DATE(sa.created_at) BETWEEN ? AND ? AND sa.status='completed' $branchCond
    ORDER BY sa.created_at DESC
    LIMIT 100
");
$transactions->execute($params);
$transactions = $transactions->fetchAll();
?>
<!-- Detailed Transactions -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-list" style="color:var(--info)"></i> Transaction Details</div>
        <button onclick="window.print()" class="btn btn-outline btn-sm"><i class="fas fa-print"></i> Print</button>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Branch</th>
                    <th>Sold By (Pharmacist)</th>
                    <th>Amount</th>
                    <th>Discount</th>
                    <th>Payment</th>
                    <th>Date & Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                <tr><td colspan="8"><div class="empty-state" style="padding:20px;"><div class="empty-icon">📊</div><p>No transactions in this period</p></div></td></tr>
                <?php else: foreach ($transactions as $t): ?>
                <tr>
                    <td><a href="/pharmacy/sales/view.php?id=<?= $t['id'] ?>" style="font-weight:700;color:var(--primary);"><?= htmlspecialchars($t['invoice_number']) ?></a></td>
                    <td>
                        <strong><?= htmlspecialchars($t['customer_name']) ?></strong>
                        <?php if ($t['customer_phone']): ?><div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($t['customer_phone']) ?></div><?php endif; ?>
                    </td>
                    <td>
                        <span style="background:var(--primary-light);color:var(--primary);padding:3px 8px;border-radius:6px;font-size:12px;font-weight:600;">
                            <i class="fas fa-building" style="font-size:10px;"></i> <?= htmlspecialchars($t['branch_name']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:28px;height:28px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;">
                                <?= strtoupper(substr($t['pharmacist_name'],0,1)) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:12.5px;"><?= htmlspecialchars($t['pharmacist_name']) ?></div>
                                <div style="font-size:10px;color:var(--text-muted);"><?= ucfirst(str_replace('_',' ',$t['pharmacist_role'])) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><strong style="color:var(--primary)"><?= formatCurrency($t['total_amount']) ?></strong></td>
                    <td><?= $t['discount'] > 0 ? '<span style="color:var(--danger);">-'.formatCurrency($t['discount']).'</span>' : '-' ?></td>
                    <td><span class="badge badge-info"><?= ucfirst(str_replace('_',' ',$t['payment_method'])) ?></span></td>
                    <td style="font-size:12px;">
                        <strong><?= date('d M Y', strtotime($t['created_at'])) ?></strong>
                        <div style="color:var(--text-muted);"><?= date('H:i', strtotime($t['created_at'])) ?></div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Daily Chart
new Chart(document.getElementById('dailyChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($d) => date('d M', strtotime($d['date'])), $daily)) ?>,
        datasets: [{
            label: 'Revenue',
            data: <?= json_encode(array_column($daily, 'revenue')) ?>,
            backgroundColor: 'rgba(26,107,60,0.7)',
            borderColor: '#1a6b3c',
            borderWidth: 1,
            borderRadius: 5
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '<?= CURRENCY ?> ' + v.toLocaleString() } },
            x: { grid: { display: false } }
        }
    }
});

// Payment Chart
new Chart(document.getElementById('paymentChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(fn($p) => ucfirst(str_replace('_',' ',$p['payment_method'])), $payments)) ?>,
        datasets: [{ data: <?= json_encode(array_column($payments, 'total')) ?>, backgroundColor: ['#1a6b3c','#3498db','#f39c12','#e74c3c'] }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php require_once '../includes/footer.php'; ?>
