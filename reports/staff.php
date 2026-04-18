<?php
require_once '../config/db.php';
requireLogin();
define('PAGE_TITLE', 'Staff Sales Report');
define('PAGE_SUBTITLE', 'Sales breakdown by pharmacist / cashier');

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');
$branchId = (int)($_GET['branch_id'] ?? 0);
$staffId  = (int)($_GET['staff_id']  ?? 0);

// Branch restriction
$branchCond = '';
$params     = [$dateFrom, $dateTo];
if (!isSuperAdmin()) {
    $branchCond = 'AND sa.branch_id = ?';
    $params[]   = (int)getUserBranchId();
} elseif ($branchId) {
    $branchCond = 'AND sa.branch_id = ?';
    $params[]   = $branchId;
}

$staffCond = '';
if ($staffId) {
    $staffCond = 'AND sa.user_id = ?';
    $params[]  = $staffId;
}

// ---- STAFF SUMMARY ----
$staffSummary = $pdo->prepare("
    SELECT
        u.id,
        u.name                  AS staff_name,
        r.name                  AS role,
        b.name                  AS branch_name,
        COUNT(DISTINCT sa.id)   AS total_sales,
        SUM(si.quantity)        AS total_items,
        SUM(sa.total_amount)    AS total_revenue,
        SUM(sa.discount)        AS total_discount,
        AVG(sa.total_amount)    AS avg_sale,
        MAX(sa.total_amount)    AS max_sale,
        MIN(sa.total_amount)    AS min_sale,
        COUNT(DISTINCT DATE(sa.created_at)) AS active_days
    FROM sales sa
    JOIN users u       ON sa.user_id   = u.id
    JOIN roles r       ON u.role_id    = r.id
    JOIN branches b    ON sa.branch_id = b.id
    JOIN sale_items si ON si.sale_id   = sa.id
    WHERE DATE(sa.created_at) BETWEEN ? AND ?
      AND sa.status = 'completed'
      $branchCond $staffCond
    GROUP BY sa.user_id
    ORDER BY total_revenue DESC
");
$staffSummary->execute($params);
$staffSummary = $staffSummary->fetchAll();

// ---- DAILY BREAKDOWN per staff (for selected staff) ----
$staffDailyData = [];
if ($staffId) {
    $sdParams = [$dateFrom, $dateTo, $staffId];
    $sdBranchCond = '';
    if (!isSuperAdmin()) { $sdBranchCond = 'AND sa.branch_id = ?'; $sdParams[] = (int)getUserBranchId(); }
    elseif ($branchId)   { $sdBranchCond = 'AND sa.branch_id = ?'; $sdParams[] = $branchId; }

    $sdStmt = $pdo->prepare("
        SELECT DATE(sa.created_at) AS day,
               COUNT(DISTINCT sa.id) AS sales,
               SUM(si.quantity) AS items,
               SUM(sa.total_amount) AS revenue
        FROM sales sa
        JOIN sale_items si ON si.sale_id = sa.id
        WHERE DATE(sa.created_at) BETWEEN ? AND ?
          AND sa.user_id = ? AND sa.status = 'completed'
          $sdBranchCond
        GROUP BY DATE(sa.created_at)
        ORDER BY day ASC
    ");
    $sdStmt->execute($sdParams);
    $staffDailyData = $sdStmt->fetchAll();
}

// ---- TOP MEDICINES per staff ----
$staffTopMeds = [];
if ($staffId) {
    $tmParams = [$dateFrom, $dateTo, $staffId];
    $tmBranchCond = '';
    if (!isSuperAdmin()) { $tmBranchCond = 'AND sa.branch_id = ?'; $tmParams[] = (int)getUserBranchId(); }
    elseif ($branchId)   { $tmBranchCond = 'AND sa.branch_id = ?'; $tmParams[] = $branchId; }

    $tmStmt = $pdo->prepare("
        SELECT m.name, SUM(si.quantity) AS qty, SUM(si.subtotal) AS revenue
        FROM sale_items si
        JOIN medicines m ON si.medicine_id = m.id
        JOIN sales sa ON si.sale_id = sa.id
        WHERE DATE(sa.created_at) BETWEEN ? AND ?
          AND sa.user_id = ? AND sa.status = 'completed'
          $tmBranchCond
        GROUP BY si.medicine_id
        ORDER BY qty DESC LIMIT 10
    ");
    $tmStmt->execute($tmParams);
    $staffTopMeds = $tmStmt->fetchAll();
}

// ---- RECENT SALES for selected staff ----
$staffRecentSales = [];
if ($staffId) {
    $rsParams = [$staffId, $dateFrom, $dateTo];
    $rsBranchCond = '';
    if (!isSuperAdmin()) { $rsBranchCond = 'AND sa.branch_id = ?'; $rsParams[] = (int)getUserBranchId(); }
    elseif ($branchId)   { $rsBranchCond = 'AND sa.branch_id = ?'; $rsParams[] = $branchId; }

    $rsStmt = $pdo->prepare("
        SELECT sa.*, b.name AS branch_name
        FROM sales sa JOIN branches b ON sa.branch_id = b.id
        WHERE sa.user_id = ?
          AND DATE(sa.created_at) BETWEEN ? AND ?
          AND sa.status = 'completed'
          $rsBranchCond
        ORDER BY sa.created_at DESC LIMIT 15
    ");
    $rsStmt->execute($rsParams);
    $staffRecentSales = $rsStmt->fetchAll();
}

$totals = [
    'sales'   => array_sum(array_column($staffSummary, 'total_sales')),
    'items'   => array_sum(array_column($staffSummary, 'total_items')),
    'revenue' => array_sum(array_column($staffSummary, 'total_revenue')),
    'discount'=> array_sum(array_column($staffSummary, 'total_discount')),
];

$branches  = $pdo->query("SELECT * FROM branches WHERE status='active' ORDER BY name")->fetchAll();
$allStaff  = $pdo->query("SELECT u.id, u.name FROM users u WHERE u.status='active' ORDER BY u.name")->fetchAll();

require_once '../includes/header.php';
?>

<!-- ===== FILTERS ===== -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:15px 22px;">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="margin:0;"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>"></div>
            <div class="form-group" style="margin:0;"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>"></div>
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
            <div class="form-group" style="margin:0;">
                <label class="form-label">Staff Member</label>
                <select name="staff_id" class="form-control">
                    <option value="">All Staff</option>
                    <?php foreach ($allStaff as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $staffId==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Generate</button>
            <a href="?date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-outline">Today</a>
            <a href="?date_from=<?= date('Y-m-01') ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-outline">This Month</a>
            <button type="button" onclick="window.print()" class="btn btn-outline"><i class="fas fa-print"></i> Print</button>
        </form>
    </div>
</div>

<!-- ===== SUMMARY CARDS ===== -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
    <div class="stat-card green"><div class="stat-icon">💰</div><div class="stat-info"><div class="value" style="font-size:18px;"><?= formatCurrency($totals['revenue']) ?></div><div class="label">Total Revenue</div></div></div>
    <div class="stat-card blue"><div class="stat-icon">🧾</div><div class="stat-info"><div class="value"><?= number_format($totals['sales']) ?></div><div class="label">Transactions</div></div></div>
    <div class="stat-card orange"><div class="stat-icon">💊</div><div class="stat-info"><div class="value"><?= number_format($totals['items']) ?></div><div class="label">Items Sold</div></div></div>
    <div class="stat-card red"><div class="stat-icon">👥</div><div class="stat-info"><div class="value"><?= count($staffSummary) ?></div><div class="label">Active Staff</div></div></div>
</div>

<!-- ===== STAFF LEADERBOARD ===== -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-trophy" style="color:var(--warning)"></i> Staff Sales Leaderboard</div>
    </div>
    <?php if (empty($staffSummary)): ?>
    <div class="card-body"><div class="empty-state"><div class="empty-icon">👤</div><p>No sales data for this period</p></div></div>
    <?php else: ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Staff Name</th>
                    <th>Role</th>
                    <?php if (isSuperAdmin()): ?><th>Branch</th><?php endif; ?>
                    <th>Transactions</th>
                    <th>Items Sold</th>
                    <th>Revenue</th>
                    <th>Discounts</th>
                    <th>Avg Sale</th>
                    <th>Active Days</th>
                    <th>Revenue Share</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php $medals = ['🥇','🥈','🥉']; ?>
                <?php foreach ($staffSummary as $i => $s): ?>
                <?php $share = $totals['revenue'] > 0 ? round(($s['total_revenue']/$totals['revenue'])*100,1) : 0; ?>
                <tr style="<?= $staffId==$s['id'] ? 'background:var(--primary-light);' : '' ?>">
                    <td style="font-size:18px;"><?= $medals[$i] ?? ($i+1) ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:34px;height:34px;border-radius:50%;background:<?= ['#1a6b3c','#3498db','#f39c12','#e74c3c','#9b59b6'][$i%5] ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">
                                <?= strtoupper(substr($s['staff_name'],0,1)) ?>
                            </div>
                            <strong><?= htmlspecialchars($s['staff_name']) ?></strong>
                        </div>
                    </td>
                    <td><span class="badge badge-info"><?= ucfirst(str_replace('_',' ',$s['role'])) ?></span></td>
                    <?php if (isSuperAdmin()): ?><td><?= htmlspecialchars($s['branch_name']) ?></td><?php endif; ?>
                    <td><?= number_format($s['total_sales']) ?></td>
                    <td><?= number_format($s['total_items']) ?></td>
                    <td><strong style="color:var(--primary)"><?= formatCurrency($s['total_revenue']) ?></strong></td>
                    <td style="color:var(--danger);"><?= formatCurrency($s['total_discount']) ?></td>
                    <td><?= formatCurrency($s['avg_sale']) ?></td>
                    <td><?= $s['active_days'] ?> days</td>
                    <td style="min-width:120px;">
                        <div style="font-size:11px;margin-bottom:2px;"><?= $share ?>%</div>
                        <div class="progress">
                            <div class="progress-bar" style="width:<?= $share ?>%;background:<?= ['#1a6b3c','#3498db','#f39c12','#e74c3c','#9b59b6'][$i%5] ?>"></div>
                        </div>
                    </td>
                    <td>
                        <a href="?date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&branch_id=<?= $branchId ?>&staff_id=<?= $s['id'] ?>"
                           class="btn btn-info btn-sm btn-icon" title="View detail">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ===== SELECTED STAFF DETAIL ===== -->
<?php if ($staffId && !empty($staffSummary)): ?>
<?php $selectedStaff = $staffSummary[0]; ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

    <!-- Daily Activity -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-calendar" style="color:var(--primary)"></i> Daily Activity — <?= htmlspecialchars($selectedStaff['staff_name']) ?></div>
        </div>
        <?php if (empty($staffDailyData)): ?>
        <div class="card-body"><div class="empty-state"><div class="empty-icon">📅</div><p>No data</p></div></div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Date</th><th>Sales</th><th>Items</th><th>Revenue</th></tr></thead>
                <tbody>
                    <?php foreach ($staffDailyData as $d): ?>
                    <tr>
                        <td><?= date('D, d M Y', strtotime($d['day'])) ?></td>
                        <td><?= $d['sales'] ?></td>
                        <td><?= number_format($d['items']) ?></td>
                        <td><strong style="color:var(--primary)"><?= formatCurrency($d['revenue']) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Top Medicines -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-pills" style="color:var(--info)"></i> Top Medicines Sold</div>
        </div>
        <?php if (empty($staffTopMeds)): ?>
        <div class="card-body"><div class="empty-state"><div class="empty-icon">💊</div><p>No data</p></div></div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead><tr><th>#</th><th>Medicine</th><th>Qty</th><th>Revenue</th></tr></thead>
                <tbody>
                    <?php foreach ($staffTopMeds as $i => $m): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
                        <td><?= number_format($m['qty']) ?></td>
                        <td style="color:var(--primary);font-weight:700;"><?= formatCurrency($m['revenue']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Sales by this staff -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-receipt" style="color:var(--secondary)"></i> Recent Sales by <?= htmlspecialchars($selectedStaff['staff_name']) ?></div>
    </div>
    <?php if (empty($staffRecentSales)): ?>
    <div class="card-body"><div class="empty-state"><div class="empty-icon">🧾</div><p>No sales</p></div></div>
    <?php else: ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <?php if (isSuperAdmin()): ?><th>Branch</th><?php endif; ?>
                    <th>Amount</th>
                    <th>Discount</th>
                    <th>Payment</th>
                    <th>Date & Time</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staffRecentSales as $sale): ?>
                <tr>
                    <td><a href="/pharmacy/sales/view.php?id=<?= $sale['id'] ?>" style="color:var(--primary);font-weight:600;"><?= htmlspecialchars($sale['invoice_number']) ?></a></td>
                    <td><?= htmlspecialchars($sale['customer_name']) ?></td>
                    <?php if (isSuperAdmin()): ?><td><?= htmlspecialchars($sale['branch_name']) ?></td><?php endif; ?>
                    <td><strong><?= formatCurrency($sale['total_amount']) ?></strong></td>
                    <td style="color:var(--danger);"><?= $sale['discount'] > 0 ? formatCurrency($sale['discount']) : '—' ?></td>
                    <td><span class="badge badge-secondary"><?= ucfirst(str_replace('_',' ',$sale['payment_method'])) ?></span></td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= date('d M Y H:i', strtotime($sale['created_at'])) ?></td>
                    <td><a href="/pharmacy/sales/view.php?id=<?= $sale['id'] ?>" class="btn btn-info btn-sm btn-icon"><i class="fas fa-eye"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Staff Performance Chart -->
<?php if (!empty($staffDailyData)): ?>
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-chart-line" style="color:var(--primary)"></i> Performance Chart — <?= htmlspecialchars($selectedStaff['staff_name']) ?></div></div>
    <div class="card-body"><div class="chart-container"><canvas id="staffChart"></canvas></div></div>
</div>
<script>
new Chart(document.getElementById('staffChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($d) => date('d M', strtotime($d['day'])), $staffDailyData)) ?>,
        datasets: [{
            label: 'Revenue (<?= CURRENCY ?>)',
            data: <?= json_encode(array_map(fn($d) => round($d['revenue'],2), $staffDailyData)) ?>,
            borderColor: '#1a6b3c',
            backgroundColor: 'rgba(26,107,60,0.1)',
            borderWidth: 2.5,
            fill: true,
            tension: 0.4,
            pointRadius: 5,
            pointBackgroundColor: '#1a6b3c'
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '<?= CURRENCY ?> ' + v.toLocaleString() }, grid: { color: '#f0f0f0' } },
            x: { grid: { display: false } }
        }
    }
});
</script>
<?php endif; ?>
<?php endif; ?>

<style>
@media print {
    .sidebar, .topbar, .main-wrapper > header, .card:first-child { display: none !important; }
    .main-wrapper { margin-left: 0 !important; }
    .page-content { padding: 0 !important; }
}
</style>

<?php require_once '../includes/footer.php'; ?>
