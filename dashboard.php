<?php
require_once 'config/db.php';
requireLogin();
define('PAGE_TITLE', 'Dashboard');
define('PAGE_SUBTITLE', isSuperAdmin() ? 'All Branches Overview' : 'Branch Overview');

$branchId = getUserBranchId() ?? 1;
$branchFilter = isSuperAdmin() ? '' : "AND s.branch_id = $branchId";
$branchFilterSales = isSuperAdmin() ? '' : "AND branch_id = $branchId";
$branchFilterSalesAlias = isSuperAdmin() ? '' : "AND sa.branch_id = $branchId";

// ---- TODAY STATS ----
$todaySales   = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE DATE(created_at)=CURDATE() AND status='completed' $branchFilterSales")->fetchColumn();
$todayCount   = $pdo->query("SELECT COUNT(*) FROM sales WHERE DATE(created_at)=CURDATE() $branchFilterSales")->fetchColumn();
$todayItems   = $pdo->query("SELECT COALESCE(SUM(si.quantity),0) FROM sale_items si JOIN sales sa ON si.sale_id=sa.id WHERE DATE(sa.created_at)=CURDATE() AND sa.status='completed' $branchFilterSalesAlias")->fetchColumn();
$monthSales   = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE()) AND status='completed' $branchFilterSales")->fetchColumn();
$yesterdaySales = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE DATE(created_at)=DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND status='completed' $branchFilterSales")->fetchColumn();
$salesGrowth  = $yesterdaySales > 0 ? round((($todaySales - $yesterdaySales) / $yesterdaySales) * 100, 1) : 0;

// ---- STOCK STATS ----
$totalStock   = $pdo->query("SELECT COALESCE(SUM(s.quantity),0) FROM stock s WHERE 1=1 $branchFilter")->fetchColumn();
$lowStock     = $pdo->query("SELECT COUNT(*) FROM stock s WHERE s.quantity <= s.low_stock_threshold AND s.quantity > 0 $branchFilter")->fetchColumn();
$outOfStock   = $pdo->query("SELECT COUNT(*) FROM stock s WHERE s.quantity = 0 $branchFilter")->fetchColumn();
$expiring7    = $pdo->query("SELECT COUNT(*) FROM stock s WHERE s.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY) $branchFilter")->fetchColumn();
$expiring30   = $pdo->query("SELECT COUNT(*) FROM stock s WHERE s.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) $branchFilter")->fetchColumn();
$expired      = $pdo->query("SELECT COUNT(*) FROM stock s WHERE s.expiry_date < CURDATE() AND s.quantity > 0 $branchFilter")->fetchColumn();
$totalMeds    = $pdo->query("SELECT COUNT(*) FROM medicines WHERE status='active'")->fetchColumn();
$stockValue   = $pdo->query("SELECT COALESCE(SUM(s.quantity * s.selling_price),0) FROM stock s WHERE 1=1 $branchFilter")->fetchColumn();

// ---- HOURLY SALES TODAY ----
$hourlySales = $pdo->query("SELECT HOUR(created_at) as hr, COUNT(*) as cnt, SUM(total_amount) as rev FROM sales WHERE DATE(created_at)=CURDATE() AND status='completed' $branchFilterSales GROUP BY HOUR(created_at) ORDER BY hr")->fetchAll();

// ---- MONTHLY CHART ----
$monthlySalesData = $pdo->query("SELECT DATE_FORMAT(created_at,'%b') as month, COALESCE(SUM(total_amount),0) as total, COUNT(*) as cnt FROM sales WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND status='completed' $branchFilterSales GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY created_at ASC")->fetchAll();

// ---- TOP MEDICINES TODAY ----
$topMedsToday = $pdo->query("SELECT m.name, SUM(si.quantity) as qty, SUM(si.subtotal) as rev FROM sale_items si JOIN medicines m ON si.medicine_id=m.id JOIN sales sa ON si.sale_id=sa.id WHERE DATE(sa.created_at)=CURDATE() AND sa.status='completed' $branchFilterSalesAlias GROUP BY si.medicine_id ORDER BY qty DESC LIMIT 5")->fetchAll();

// ---- LOW STOCK MEDICINES ----
$lowStockMeds = $pdo->query("SELECT m.name, s.quantity, s.low_stock_threshold, s.selling_price, s.expiry_date, b.name as branch_name FROM stock s JOIN medicines m ON s.medicine_id=m.id JOIN branches b ON s.branch_id=b.id WHERE s.quantity <= s.low_stock_threshold $branchFilter ORDER BY s.quantity ASC LIMIT 8")->fetchAll();

// ---- EXPIRING SOON ----
$expiringSoon = $pdo->query("SELECT m.name, s.expiry_date, s.quantity, s.batch_number, b.name as branch_name, DATEDIFF(s.expiry_date, CURDATE()) as days_left FROM stock s JOIN medicines m ON s.medicine_id=m.id JOIN branches b ON s.branch_id=b.id WHERE s.expiry_date BETWEEN DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND s.quantity > 0 $branchFilter ORDER BY s.expiry_date ASC LIMIT 8")->fetchAll();

// ---- RECENT SALES ----
$recentSales = $pdo->query("SELECT sa.*, b.name as branch_name, u.name as cashier FROM sales sa JOIN branches b ON sa.branch_id=b.id JOIN users u ON sa.user_id=u.id WHERE 1=1 $branchFilterSalesAlias ORDER BY sa.created_at DESC LIMIT 8")->fetchAll();

// ---- BRANCH PERFORMANCE ----
$branchPerformance = [];
if (isSuperAdmin()) {
    $branchPerformance = $pdo->query("SELECT b.id, b.name, b.location, COALESCE(SUM(sa.total_amount),0) as month_sales, COUNT(sa.id) as transactions, (SELECT COUNT(*) FROM stock st WHERE st.branch_id=b.id AND st.quantity <= st.low_stock_threshold) as low_stock_count, (SELECT COUNT(*) FROM stock st WHERE st.branch_id=b.id AND st.quantity=0) as out_count FROM branches b LEFT JOIN sales sa ON sa.branch_id=b.id AND MONTH(sa.created_at)=MONTH(CURDATE()) AND sa.status='completed' WHERE b.status='active' GROUP BY b.id ORDER BY month_sales DESC")->fetchAll();
}

// ---- DAILY REPORT ----
$dailyReport = $pdo->query("SELECT DATE(sa.created_at) as day, COUNT(*) as sales_count, SUM(sa.total_amount) as revenue, SUM(si.quantity) as items_sold FROM sales sa JOIN sale_items si ON si.sale_id=sa.id WHERE sa.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND sa.status='completed' $branchFilterSalesAlias GROUP BY DATE(sa.created_at) ORDER BY day DESC")->fetchAll();

// ---- NOTIFICATIONS ----
$notifications = getUnreadNotifications($pdo, $_SESSION['user_id'], $branchId);
$notifCount = count($notifications);

require_once 'includes/header.php';
?>

<!-- ALERT BANNERS -->
<?php if ($expired > 0): ?>
<div class="alert alert-danger" style="margin-bottom:15px;">
    <i class="fas fa-skull-crossbones"></i>
    <strong><?= $expired ?> medicine batch(es) have EXPIRED</strong> and are still in stock.
    <a href="/pharmacy/reports/expiry.php?days=0" style="color:inherit;font-weight:700;margin-left:10px;">View Now →</a>
</div>
<?php endif; ?>
<?php if ($expiring7 > 0): ?>
<div class="alert alert-warning" style="margin-bottom:15px;">
    <i class="fas fa-exclamation-triangle"></i>
    <strong><?= $expiring7 ?> medicine(s) expire within 7 days.</strong>
    <a href="/pharmacy/reports/expiry.php?days=7" style="color:inherit;font-weight:700;margin-left:10px;">View Now →</a>
</div>
<?php endif; ?>

<!-- TOP STAT CARDS -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
    <div class="stat-card green">
        <div class="stat-icon" style="background:#e8f8f0;font-size:22px;">💰</div>
        <div class="stat-info">
            <div class="value" style="font-size:20px;"><?= formatCurrency($todaySales) ?></div>
            <div class="label">Today's Revenue</div>
            <div class="change <?= $salesGrowth >= 0 ? 'up' : 'down' ?>">
                <i class="fas fa-arrow-<?= $salesGrowth >= 0 ? 'up' : 'down' ?>"></i>
                <?= abs($salesGrowth) ?>% vs yesterday
            </div>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon" style="background:#e8f4fd;font-size:22px;">🧾</div>
        <div class="stat-info">
            <div class="value"><?= $todayCount ?></div>
            <div class="label">Today's Transactions</div>
            <div class="change"><?= $todayItems ?> items sold today</div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon" style="background:#fef9e7;font-size:22px;">📦</div>
        <div class="stat-info">
            <div class="value"><?= number_format($totalStock) ?></div>
            <div class="label">Total Stock Units</div>
            <div class="change <?= $lowStock > 0 ? 'down' : 'up' ?>">
                <?= $lowStock ?> low · <?= $outOfStock ?> out of stock
            </div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon" style="background:#e8f8f0;font-size:22px;">📈</div>
        <div class="stat-info">
            <div class="value" style="font-size:20px;"><?= formatCurrency($monthSales) ?></div>
            <div class="label">This Month Revenue</div>
            <div class="change"><?= formatCurrency($stockValue) ?> stock value</div>
        </div>
    </div>
</div>

<!-- SECOND ROW STATS -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
    <a href="/pharmacy/medicines/index.php" style="text-decoration:none;">
        <div class="stat-card blue" style="cursor:pointer;">
            <div class="stat-icon" style="background:#e8f4fd;">💊</div>
            <div class="stat-info"><div class="value"><?= $totalMeds ?></div><div class="label">Active Medicines</div></div>
        </div>
    </a>
    <a href="/pharmacy/reports/expiry.php?days=30" style="text-decoration:none;">
        <div class="stat-card <?= $expiring30 > 0 ? 'orange' : 'green' ?>" style="cursor:pointer;">
            <div class="stat-icon" style="background:<?= $expiring30 > 0 ? '#fef9e7' : '#e8f8f0' ?>;">📅</div>
            <div class="stat-info"><div class="value"><?= $expiring30 ?></div><div class="label">Expiring in 30 Days</div></div>
        </div>
    </a>
    <a href="/pharmacy/reports/expiry.php" style="text-decoration:none;">
        <div class="stat-card <?= $expired > 0 ? 'red' : 'green' ?>" style="cursor:pointer;">
            <div class="stat-icon" style="background:<?= $expired > 0 ? '#fdf2f2' : '#e8f8f0' ?>;">☠️</div>
            <div class="stat-info"><div class="value"><?= $expired ?></div><div class="label">Expired (In Stock)</div></div>
        </div>
    </a>
    <a href="/pharmacy/stock/index.php?filter=low" style="text-decoration:none;">
        <div class="stat-card <?= $lowStock > 0 ? 'red' : 'green' ?>" style="cursor:pointer;">
            <div class="stat-icon" style="background:<?= $lowStock > 0 ? '#fdf2f2' : '#e8f8f0' ?>;">⚠️</div>
            <div class="stat-info"><div class="value"><?= $lowStock ?></div><div class="label">Low Stock Items</div></div>
        </div>
    </a>
</div>

<!-- CHARTS ROW -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px;">
    <!-- Monthly Sales Chart -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-chart-line" style="color:var(--primary)"></i> Monthly Sales Trend</div>
            <a href="/pharmacy/reports/sales.php" class="btn btn-outline btn-sm">Full Report</a>
        </div>
        <div class="card-body"><div class="chart-container"><canvas id="salesChart"></canvas></div></div>
    </div>
    <!-- Today Hourly -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-clock" style="color:var(--info)"></i> Today by Hour</div>
        </div>
        <div class="card-body"><div class="chart-container"><canvas id="hourlyChart"></canvas></div></div>
    </div>
</div>

<!-- BRANCH PERFORMANCE (Super Admin) -->
<?php if (isSuperAdmin() && !empty($branchPerformance)): ?>
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-building" style="color:var(--info)"></i> Branch Performance — This Month</div>
        <a href="/pharmacy/branches/index.php" class="btn btn-outline btn-sm">Manage Branches</a>
    </div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Branch</th><th>Location</th><th>Monthly Revenue</th><th>Transactions</th><th>Low Stock</th><th>Out of Stock</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($branchPerformance as $b): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($b['name']) ?></strong></td>
                    <td><i class="fas fa-map-marker-alt" style="color:var(--danger)"></i> <?= htmlspecialchars($b['location']) ?></td>
                    <td><strong style="color:var(--primary)"><?= formatCurrency($b['month_sales']) ?></strong></td>
                    <td><?= number_format($b['transactions']) ?></td>
                    <td><?= $b['low_stock_count'] > 0 ? '<span class="badge badge-warning">'.$b['low_stock_count'].' items</span>' : '<span class="badge badge-success">OK</span>' ?></td>
                    <td><?= $b['out_count'] > 0 ? '<span class="badge badge-danger">'.$b['out_count'].' items</span>' : '<span class="badge badge-success">OK</span>' ?></td>
                    <td><a href="/pharmacy/branches/view.php?id=<?= $b['id'] ?>" class="btn btn-info btn-sm">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- DAILY REPORT TABLE -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-calendar-day" style="color:var(--primary)"></i> Daily Sales Summary (Last 7 Days)</div>
            <a href="/pharmacy/reports/daily.php" class="btn btn-outline btn-sm">Full Report</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Date</th><th>Sales</th><th>Items Sold</th><th>Revenue</th></tr></thead>
                <tbody>
                    <?php if (empty($dailyReport)): ?>
                    <tr><td colspan="4"><div class="empty-state" style="padding:20px;"><div class="empty-icon" style="font-size:30px;">📊</div><p>No data yet</p></div></td></tr>
                    <?php else: ?>
                    <?php foreach ($dailyReport as $dr): ?>
                    <tr>
                        <td>
                            <strong><?= date('D, d M', strtotime($dr['day'])) ?></strong>
                            <?php if ($dr['day'] == date('Y-m-d')): ?><span class="badge badge-success" style="margin-left:5px;">Today</span><?php endif; ?>
                        </td>
                        <td><?= $dr['sales_count'] ?></td>
                        <td><?= number_format($dr['items_sold']) ?></td>
                        <td><strong style="color:var(--primary)"><?= formatCurrency($dr['revenue']) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Medicines Today -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-fire" style="color:var(--danger)"></i> Top Selling Today</div>
            <a href="/pharmacy/sales/new.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Sale</a>
        </div>
        <div class="card-body" style="padding:10px;">
            <?php if (empty($topMedsToday)): ?>
            <div class="empty-state"><div class="empty-icon">🛒</div><p>No sales today yet</p></div>
            <?php else: ?>
            <?php foreach ($topMedsToday as $i => $med): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:10px;border-radius:8px;margin-bottom:5px;background:<?= $i==0?'var(--primary-light)':'var(--light)' ?>;">
                <div style="width:30px;height:30px;border-radius:50%;background:<?= ['#1a6b3c','#3498db','#f39c12','#e74c3c','#9b59b6'][$i] ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;"><?= $i+1 ?></div>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($med['name']) ?></div>
                    <div style="font-size:11px;color:var(--text-muted);"><?= number_format($med['qty']) ?> units</div>
                </div>
                <div style="font-weight:700;color:var(--primary);font-size:13px;"><?= formatCurrency($med['rev']) ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- BOTTOM ROW: Low Stock + Expiry + Recent Sales -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">
    <!-- Low Stock -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-exclamation-triangle" style="color:var(--warning)"></i> Low Stock</div>
            <a href="/pharmacy/stock/index.php?filter=low" class="btn btn-warning btn-sm">View All</a>
        </div>
        <?php if (empty($lowStockMeds)): ?>
        <div class="card-body"><div class="empty-state" style="padding:20px;"><div class="empty-icon" style="font-size:30px;">✅</div><p>All stock OK</p></div></div>
        <?php else: ?>
        <div style="padding:5px;">
            <?php foreach ($lowStockMeds as $item): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 12px;border-bottom:1px solid #f0f0f0;">
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($item['name']) ?></div>
                    <?php if (isSuperAdmin()): ?><div style="font-size:10px;color:var(--text-muted);"><?= htmlspecialchars($item['branch_name']) ?></div><?php endif; ?>
                </div>
                <span class="badge <?= $item['quantity']==0?'badge-danger':($item['quantity']<=$item['low_stock_threshold']*0.5?'badge-danger':'badge-warning') ?>">
                    <?= $item['quantity']==0 ? 'OUT' : $item['quantity'].' left' ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Expiry Alerts -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-calendar-times" style="color:var(--danger)"></i> Expiry Alerts</div>
            <a href="/pharmacy/reports/expiry.php" class="btn btn-danger btn-sm">View All</a>
        </div>
        <?php if (empty($expiringSoon)): ?>
        <div class="card-body"><div class="empty-state" style="padding:20px;"><div class="empty-icon" style="font-size:30px;">✅</div><p>No expiry alerts</p></div></div>
        <?php else: ?>
        <div style="padding:5px;">
            <?php foreach ($expiringSoon as $exp): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 12px;border-bottom:1px solid #f0f0f0;">
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($exp['name']) ?></div>
                    <div style="font-size:10px;color:var(--text-muted);"><?= $exp['quantity'] ?> units · <?= htmlspecialchars($exp['batch_number'] ?? '') ?></div>
                </div>
                <span class="badge <?= $exp['days_left'] < 0 ? 'badge-danger' : ($exp['days_left'] <= 7 ? 'badge-danger' : 'badge-warning') ?>">
                    <?= $exp['days_left'] < 0 ? 'EXPIRED' : ($exp['days_left']==0 ? 'Today' : $exp['days_left'].'d') ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Sales -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-receipt" style="color:var(--secondary)"></i> Recent Sales</div>
            <a href="/pharmacy/sales/index.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <?php if (empty($recentSales)): ?>
        <div class="card-body"><div class="empty-state" style="padding:20px;"><div class="empty-icon" style="font-size:30px;">🛒</div><p>No sales yet</p></div></div>
        <?php else: ?>
        <div style="padding:5px;">
            <?php foreach ($recentSales as $sale): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 12px;border-bottom:1px solid #f0f0f0;">
                <div>
                    <a href="/pharmacy/sales/view.php?id=<?= $sale['id'] ?>" style="font-weight:600;font-size:12.5px;color:var(--primary);text-decoration:none;"><?= htmlspecialchars($sale['invoice_number']) ?></a>
                    <div style="font-size:10px;color:var(--text-muted);"><?= date('H:i', strtotime($sale['created_at'])) ?> · <?= htmlspecialchars($sale['cashier']) ?></div>
                </div>
                <strong style="color:var(--primary);font-size:13px;"><?= formatCurrency($sale['total_amount']) ?></strong>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Monthly Sales Chart
const salesLabels = <?= json_encode(array_column($monthlySalesData, 'month')) ?>;
const salesData   = <?= json_encode(array_column($monthlySalesData, 'total')) ?>;
const salesCounts = <?= json_encode(array_column($monthlySalesData, 'cnt')) ?>;

new Chart(document.getElementById('salesChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: salesLabels.length ? salesLabels : ['No Data'],
        datasets: [
            {
                label: 'Revenue (<?= CURRENCY ?>)',
                data: salesData.length ? salesData : [0],
                backgroundColor: 'rgba(26,107,60,0.75)',
                borderColor: '#1a6b3c',
                borderWidth: 1,
                borderRadius: 6,
                yAxisID: 'y'
            },
            {
                label: 'Transactions',
                data: salesCounts.length ? salesCounts : [0],
                type: 'line',
                borderColor: '#3498db',
                backgroundColor: 'rgba(52,152,219,0.1)',
                borderWidth: 2,
                fill: false,
                tension: 0.4,
                pointRadius: 4,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: {
            y: { beginAtZero: true, position: 'left', ticks: { callback: v => '<?= CURRENCY ?> ' + v.toLocaleString() }, grid: { color: '#f0f0f0' } },
            y1: { beginAtZero: true, position: 'right', grid: { display: false } },
            x: { grid: { display: false } }
        }
    }
});

// Hourly Chart
const hours = Array.from({length:24}, (_,i) => i + ':00');
const hourlyRevData = new Array(24).fill(0);
<?php foreach ($hourlySales as $h): ?>
hourlyRevData[<?= $h['hr'] ?>] = <?= $h['rev'] ?>;
<?php endforeach; ?>

new Chart(document.getElementById('hourlyChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: hours,
        datasets: [{
            label: 'Revenue',
            data: hourlyRevData,
            backgroundColor: hourlyRevData.map(v => v > 0 ? 'rgba(26,107,60,0.7)' : 'rgba(200,200,200,0.3)'),
            borderRadius: 4
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => v > 0 ? '<?= CURRENCY ?> ' + v : '' }, grid: { color: '#f0f0f0' } },
            x: { grid: { display: false }, ticks: { maxRotation: 45, font: { size: 9 } } }
        }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
