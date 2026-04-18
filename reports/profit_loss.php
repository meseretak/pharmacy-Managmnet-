<?php
require_once '../config/db.php';
requireLogin();
if (!isSuperAdmin()) { header('Location: /pharmacy/dashboard.php'); exit; }
define('PAGE_TITLE', 'Profit & Loss Report');
define('PAGE_SUBTITLE', 'Financial performance overview');

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');
$branchId = (int)($_GET['branch_id'] ?? 0);

$branchFilter = $branchId ? "AND sa.branch_id = $branchId" : '';
$branchFilterP = $branchId ? "AND p.branch_id = $branchId" : '';

// ---- REVENUE ----
$revenue = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM sales sa WHERE DATE(created_at) BETWEEN ? AND ? AND status='completed' $branchFilter");
$revenue->execute([$dateFrom, $dateTo]); $revenue = (float)$revenue->fetchColumn();

$discounts = $pdo->prepare("SELECT COALESCE(SUM(discount),0) FROM sales sa WHERE DATE(created_at) BETWEEN ? AND ? AND status='completed' $branchFilter");
$discounts->execute([$dateFrom, $dateTo]); $discounts = (float)$discounts->fetchColumn();

$refunds = $pdo->prepare("SELECT COALESCE(SUM(refund_amount),0) FROM sale_refunds sr JOIN sales sa ON sr.sale_id=sa.id WHERE DATE(sr.created_at) BETWEEN ? AND ? AND sr.status='approved' $branchFilter");
$refunds->execute([$dateFrom, $dateTo]); $refunds = (float)$refunds->fetchColumn();

$netRevenue = $revenue - $refunds;

// ---- COST OF GOODS SOLD ----
$cogs = $pdo->prepare("
    SELECT COALESCE(SUM(si.quantity * s.buying_price), 0)
    FROM sale_items si
    JOIN sales sa ON si.sale_id = sa.id
    JOIN stock s ON s.medicine_id = si.medicine_id AND s.branch_id = sa.branch_id
    WHERE DATE(sa.created_at) BETWEEN ? AND ? AND sa.status='completed' $branchFilter
");
$cogs->execute([$dateFrom, $dateTo]); $cogs = (float)$cogs->fetchColumn();

// ---- PURCHASE COSTS ----
$purchaseCost = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM purchases p WHERE DATE(created_at) BETWEEN ? AND ? AND status='received' $branchFilterP");
$purchaseCost->execute([$dateFrom, $dateTo]); $purchaseCost = (float)$purchaseCost->fetchColumn();

$grossProfit = $netRevenue - $cogs;
$grossMargin = $netRevenue > 0 ? round(($grossProfit / $netRevenue) * 100, 1) : 0;

// ---- MONTHLY BREAKDOWN ----
$monthly = $pdo->prepare("
    SELECT
        DATE_FORMAT(sa.created_at,'%Y-%m') as month,
        DATE_FORMAT(sa.created_at,'%b %Y') as month_label,
        COUNT(*) as transactions,
        SUM(sa.total_amount) as revenue,
        SUM(sa.discount) as discounts,
        COALESCE(SUM(si.quantity * s.buying_price),0) as cost
    FROM sales sa
    JOIN sale_items si ON si.sale_id=sa.id
    JOIN stock s ON s.medicine_id=si.medicine_id AND s.branch_id=sa.branch_id
    WHERE DATE(sa.created_at) BETWEEN ? AND ? AND sa.status='completed' $branchFilter
    GROUP BY DATE_FORMAT(sa.created_at,'%Y-%m')
    ORDER BY month ASC
");
$monthly->execute([$dateFrom, $dateTo]); $monthly = $monthly->fetchAll();

// ---- TOP PROFITABLE MEDICINES ----
$topMeds = $pdo->prepare("
    SELECT m.name,
        SUM(si.quantity) as qty_sold,
        SUM(si.subtotal) as revenue,
        SUM(si.quantity * s.buying_price) as cost,
        SUM(si.subtotal) - SUM(si.quantity * s.buying_price) as profit
    FROM sale_items si
    JOIN medicines m ON si.medicine_id=m.id
    JOIN sales sa ON si.sale_id=sa.id
    JOIN stock s ON s.medicine_id=si.medicine_id AND s.branch_id=sa.branch_id
    WHERE DATE(sa.created_at) BETWEEN ? AND ? AND sa.status='completed' $branchFilter
    GROUP BY si.medicine_id
    ORDER BY profit DESC LIMIT 15
");
$topMeds->execute([$dateFrom, $dateTo]); $topMeds = $topMeds->fetchAll();

// ---- BRANCH BREAKDOWN ----
$branchBreakdown = $pdo->prepare("
    SELECT b.name as branch_name,
        COUNT(sa.id) as transactions,
        SUM(sa.total_amount) as revenue,
        COALESCE(SUM(si.quantity * s.buying_price),0) as cost,
        SUM(sa.total_amount) - COALESCE(SUM(si.quantity * s.buying_price),0) as profit
    FROM sales sa
    JOIN branches b ON sa.branch_id=b.id
    JOIN sale_items si ON si.sale_id=sa.id
    JOIN stock s ON s.medicine_id=si.medicine_id AND s.branch_id=sa.branch_id
    WHERE DATE(sa.created_at) BETWEEN ? AND ? AND sa.status='completed'
    GROUP BY sa.branch_id ORDER BY profit DESC
");
$branchBreakdown->execute([$dateFrom, $dateTo]); $branchBreakdown = $branchBreakdown->fetchAll();

$branches = $pdo->query("SELECT * FROM branches WHERE status='active' ORDER BY name")->fetchAll();

require_once '../includes/header.php';
?>

<!-- Filters -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">From</label>
                <input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">To</label>
                <input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Branch</label>
                <select name="branch_id" class="form-control">
                    <option value="">All Branches</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $branchId==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
            <button type="button" onclick="window.print()" class="btn btn-info"><i class="fas fa-print"></i> Print</button>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid-4" style="margin-bottom:20px;">
    <div class="stat-card green">
        <div class="stat-icon" style="background:#e8f8f0;font-size:22px;">💰</div>
        <div class="stat-info">
            <div class="value" style="font-size:18px;"><?= formatCurrency($netRevenue) ?></div>
            <div class="label">Net Revenue</div>
            <div class="change"><?= formatCurrency($discounts) ?> discounts · <?= formatCurrency($refunds) ?> refunds</div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon" style="background:#fef9e7;font-size:22px;">📦</div>
        <div class="stat-info">
            <div class="value" style="font-size:18px;"><?= formatCurrency($cogs) ?></div>
            <div class="label">Cost of Goods Sold</div>
            <div class="change"><?= formatCurrency($purchaseCost) ?> in purchases</div>
        </div>
    </div>
    <div class="stat-card <?= $grossProfit >= 0 ? 'green' : 'red' ?>">
        <div class="stat-icon" style="background:<?= $grossProfit >= 0 ? '#e8f8f0' : '#fdf2f2' ?>;font-size:22px;"><?= $grossProfit >= 0 ? '📈' : '📉' ?></div>
        <div class="stat-info">
            <div class="value" style="font-size:18px;color:<?= $grossProfit >= 0 ? 'var(--primary)' : 'var(--danger)' ?>"><?= formatCurrency($grossProfit) ?></div>
            <div class="label">Gross Profit</div>
            <div class="change"><?= $grossMargin ?>% margin</div>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon" style="background:#e8f4fd;font-size:22px;">📊</div>
        <div class="stat-info">
            <div class="value"><?= $grossMargin ?>%</div>
            <div class="label">Gross Margin</div>
            <div class="change"><?= date('d M', strtotime($dateFrom)) ?> – <?= date('d M Y', strtotime($dateTo)) ?></div>
        </div>
    </div>
</div>

<!-- P&L Statement -->
<div class="grid-2" style="margin-bottom:20px;">
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-file-invoice-dollar" style="color:var(--primary)"></i> P&L Statement</div></div>
    <div class="card-body">
        <table style="width:100%;border-collapse:collapse;">
            <tr style="border-bottom:1px solid var(--border);"><td style="padding:10px;color:var(--text-muted);">Gross Revenue</td><td style="padding:10px;text-align:right;font-weight:600;"><?= formatCurrency($revenue) ?></td></tr>
            <tr style="border-bottom:1px solid var(--border);"><td style="padding:10px;color:var(--text-muted);">Less: Discounts</td><td style="padding:10px;text-align:right;color:var(--danger);">- <?= formatCurrency($discounts) ?></td></tr>
            <tr style="border-bottom:2px solid var(--border);"><td style="padding:10px;color:var(--text-muted);">Less: Refunds</td><td style="padding:10px;text-align:right;color:var(--danger);">- <?= formatCurrency($refunds) ?></td></tr>
            <tr style="border-bottom:1px solid var(--border);background:var(--light);"><td style="padding:10px;font-weight:700;">Net Revenue</td><td style="padding:10px;text-align:right;font-weight:700;"><?= formatCurrency($netRevenue) ?></td></tr>
            <tr style="border-bottom:2px solid var(--border);"><td style="padding:10px;color:var(--text-muted);">Less: Cost of Goods Sold</td><td style="padding:10px;text-align:right;color:var(--danger);">- <?= formatCurrency($cogs) ?></td></tr>
            <tr style="background:<?= $grossProfit >= 0 ? 'var(--primary-light)' : '#fdf2f2' ?>;"><td style="padding:12px;font-weight:800;font-size:16px;">Gross Profit</td><td style="padding:12px;text-align:right;font-weight:800;font-size:16px;color:<?= $grossProfit >= 0 ? 'var(--primary)' : 'var(--danger)' ?>"><?= formatCurrency($grossProfit) ?></td></tr>
        </table>
    </div>
</div>

<!-- Branch Breakdown -->
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-building" style="color:var(--info)"></i> Branch Performance</div></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Branch</th><th>Revenue</th><th>Cost</th><th>Profit</th><th>Margin</th></tr></thead>
            <tbody>
                <?php foreach ($branchBreakdown as $b): $m = $b['revenue'] > 0 ? round(($b['profit']/$b['revenue'])*100,1) : 0; ?>
                <tr>
                    <td><strong><?= htmlspecialchars($b['branch_name']) ?></strong></td>
                    <td><?= formatCurrency($b['revenue']) ?></td>
                    <td><?= formatCurrency($b['cost']) ?></td>
                    <td style="color:<?= $b['profit']>=0?'var(--primary)':'var(--danger)' ?>;font-weight:700;"><?= formatCurrency($b['profit']) ?></td>
                    <td><span class="badge <?= $m>=20?'badge-success':($m>=10?'badge-warning':'badge-danger') ?>"><?= $m ?>%</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<!-- Monthly Trend -->
<?php if (!empty($monthly)): ?>
<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><div class="card-title"><i class="fas fa-chart-bar" style="color:var(--primary)"></i> Monthly P&L Trend</div></div>
    <div class="card-body"><div class="chart-container"><canvas id="plChart"></canvas></div></div>
</div>
<?php endif; ?>

<!-- Top Profitable Medicines -->
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-trophy" style="color:var(--warning)"></i> Top Profitable Medicines</div></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>#</th><th>Medicine</th><th>Qty Sold</th><th>Revenue</th><th>Cost</th><th>Profit</th><th>Margin</th></tr></thead>
            <tbody>
                <?php foreach ($topMeds as $i => $m): $margin = $m['revenue'] > 0 ? round(($m['profit']/$m['revenue'])*100,1) : 0; ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
                    <td><?= number_format($m['qty_sold']) ?></td>
                    <td><?= formatCurrency($m['revenue']) ?></td>
                    <td><?= formatCurrency($m['cost']) ?></td>
                    <td style="color:<?= $m['profit']>=0?'var(--primary)':'var(--danger)' ?>;font-weight:700;"><?= formatCurrency($m['profit']) ?></td>
                    <td><span class="badge <?= $margin>=30?'badge-success':($margin>=15?'badge-warning':'badge-danger') ?>"><?= $margin ?>%</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($monthly)): ?>
<script>
const plLabels = <?= json_encode(array_column($monthly,'month_label')) ?>;
const plRevenue = <?= json_encode(array_column($monthly,'revenue')) ?>;
const plCost = <?= json_encode(array_column($monthly,'cost')) ?>;
const plProfit = plRevenue.map((r,i) => r - plCost[i]);
new Chart(document.getElementById('plChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: plLabels,
        datasets: [
            { label: 'Revenue', data: plRevenue, backgroundColor: 'rgba(52,152,219,0.7)', borderRadius: 4 },
            { label: 'Cost', data: plCost, backgroundColor: 'rgba(231,76,60,0.7)', borderRadius: 4 },
            { label: 'Profit', data: plProfit, backgroundColor: 'rgba(26,107,60,0.85)', borderRadius: 4 }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '<?= CURRENCY ?> ' + v.toLocaleString() } },
            x: { grid: { display: false } }
        }
    }
});
</script>
<?php endif; ?>
<style>@media print { .sidebar,.topbar,.main-wrapper>header,.card:first-child { display:none !important; } .main-wrapper { margin-left:0 !important; } }</style>
<?php require_once '../includes/footer.php'; ?>
