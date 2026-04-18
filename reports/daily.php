<?php
require_once '../config/db.php';
requireLogin();

if (!defined('PAGE_TITLE')) define('PAGE_TITLE', 'Daily Sales Report');
if (!defined('PAGE_SUBTITLE')) define('PAGE_SUBTITLE', 'Per-day medicine sales, cost & profit summary');

$dateFrom     = (isset($_GET['date_from']) && $_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
$dateTo       = (isset($_GET['date_to'])   && $_GET['date_to'])   ? $_GET['date_to']   : date('Y-m-d');
$branchId     = (int)($_GET['branch_id'] ?? 0);
$selectedDate = (isset($_GET['date'])      && $_GET['date'])      ? $_GET['date']      : date('Y-m-d');
$staffId      = (int)($_GET['staff_id'] ?? 0);

$myBranchId = getUserBranchId();
$branchCond = '';
$params     = [$dateFrom, $dateTo];

if (!isSuperAdmin()) {
    if ($myBranchId) {
        $branchCond = 'AND sa.branch_id = ?';
        $params[]   = (int)$myBranchId;
    }
} elseif ($branchId > 0) {
    $branchCond = 'AND sa.branch_id = ?';
    $params[]   = $branchId;
}

$staffCond = '';
if ($staffId > 0) {
    $staffCond = 'AND sa.user_id = ?';
    $params[]  = $staffId;
}

$daily = [];
try {
    $sql = "
        SELECT
            DATE(sa.created_at)                                          AS sale_date,
            COUNT(DISTINCT sa.id)                                        AS total_sales,
            COALESCE(SUM(si.quantity), 0)                                AS total_items,
            COALESCE(SUM(sa.total_amount), 0)                            AS total_revenue,
            COALESCE(SUM(sa.discount), 0)                                AS total_discount,
            COALESCE(AVG(sa.total_amount), 0)                            AS avg_sale,
            COALESCE(SUM(si.quantity * COALESCE(st.buying_price, 0)), 0) AS total_cost
        FROM sales sa
        JOIN sale_items si ON si.sale_id = sa.id
        LEFT JOIN (
            SELECT medicine_id, branch_id, MIN(buying_price) AS buying_price
            FROM stock
            GROUP BY medicine_id, branch_id
        ) st ON st.medicine_id = si.medicine_id AND st.branch_id = sa.branch_id
        WHERE DATE(sa.created_at) BETWEEN ? AND ?
          AND sa.status = 'completed'
          $branchCond $staffCond
        GROUP BY DATE(sa.created_at)
        ORDER BY sale_date DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $daily = $stmt->fetchAll();
} catch (Exception $e) { $daily = []; }
$dayMeds = [];
try {
    $medParams = [$selectedDate];
    $medBranchCond = '';
    $medStaffCond  = '';
    if (!isSuperAdmin() && $myBranchId) {
        $medBranchCond = 'AND sa.branch_id = ?';
        $medParams[]   = (int)$myBranchId;
    } elseif ($branchId > 0) {
        $medBranchCond = 'AND sa.branch_id = ?';
        $medParams[]   = $branchId;
    }
    if ($staffId > 0) {
        $medStaffCond = 'AND sa.user_id = ?';
        $medParams[]  = $staffId;
    }
    $stmt = $pdo->prepare("
        SELECT m.name, m.unit,
               COALESCE(c.name, 'Uncategorized') AS category,
               SUM(si.quantity)   AS qty_sold,
               si.unit_price      AS sell_price,
               SUM(si.subtotal)   AS revenue,
               COUNT(DISTINCT sa.id) AS in_transactions
        FROM sale_items si
        JOIN medicines m  ON si.medicine_id = m.id
        LEFT JOIN categories c ON m.category_id = c.id
        JOIN sales sa ON si.sale_id = sa.id
        WHERE DATE(sa.created_at) = ?
          AND sa.status = 'completed'
          $medBranchCond $medStaffCond
        GROUP BY si.medicine_id, si.unit_price
        ORDER BY qty_sold DESC
    ");
    $stmt->execute($medParams);
    $dayMeds = $stmt->fetchAll();
} catch (Exception $e) { $dayMeds = []; }

$staffBreakdown = [];
try {
    $sbParams = [$selectedDate];
    $sbBranchCond = '';
    if (!isSuperAdmin() && $myBranchId) {
        $sbBranchCond = 'AND sa.branch_id = ?';
        $sbParams[]   = (int)$myBranchId;
    } elseif ($branchId > 0) {
        $sbBranchCond = 'AND sa.branch_id = ?';
        $sbParams[]   = $branchId;
    }
    $stmt = $pdo->prepare("
        SELECT u.id, u.name AS staff_name, r.name AS role,
               COUNT(DISTINCT sa.id)             AS sales_count,
               COALESCE(SUM(si.quantity), 0)     AS items_sold,
               COALESCE(SUM(sa.total_amount), 0) AS revenue,
               COALESCE(SUM(sa.discount), 0)     AS discounts
        FROM sales sa
        JOIN users u       ON sa.user_id  = u.id
        JOIN roles r       ON u.role_id   = r.id
        JOIN sale_items si ON si.sale_id  = sa.id
        WHERE DATE(sa.created_at) = ?
          AND sa.status = 'completed'
          $sbBranchCond
        GROUP BY sa.user_id
        ORDER BY revenue DESC
    ");
    $stmt->execute($sbParams);
    $staffBreakdown = $stmt->fetchAll();
} catch (Exception $e) { $staffBreakdown = []; }

$totals = [
    'sales'    => array_sum(array_column($daily, 'total_sales')),
    'items'    => array_sum(array_column($daily, 'total_items')),
    'revenue'  => array_sum(array_column($daily, 'total_revenue')),
    'discount' => array_sum(array_column($daily, 'total_discount')),
    'cost'     => array_sum(array_column($daily, 'total_cost')),
];
$grossProfit = $totals['revenue'] - $totals['cost'];

$branches  = [];
$staffList = [];
try { $branches = $pdo->query("SELECT * FROM branches WHERE status='active' ORDER BY name")->fetchAll(); } catch (Exception $e) {}
try {
    if (isSuperAdmin() || isBranchManager()) {
        if (!isSuperAdmin() && $myBranchId) {
            $ss = $pdo->prepare("SELECT u.id, u.name FROM users u WHERE u.branch_id=? AND u.status='active' ORDER BY u.name");
            $ss->execute([$myBranchId]);
        } else {
            $ss = $pdo->query("SELECT u.id, u.name FROM users u WHERE u.status='active' ORDER BY u.name");
        }
        $staffList = $ss->fetchAll();
    }
} catch (Exception $e) {}

require_once '../includes/header.php';
?>
<!-- FILTERS -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-body" style="padding:15px 22px;">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
      <div class="form-group" style="margin:0;"><label class="form-label">From Date</label><input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>"></div>
      <div class="form-group" style="margin:0;"><label class="form-label">To Date</label><input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>"></div>
      <?php if (isSuperAdmin()): ?>
      <div class="form-group" style="margin:0;">
        <label class="form-label">Branch</label>
        <select name="branch_id" class="form-control">
          <option value="">All Branches</option>
          <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $branchId == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <?php if (!empty($staffList)): ?>
      <div class="form-group" style="margin:0;">
        <label class="form-label">Staff / Pharmacist</label>
        <select name="staff_id" class="form-control">
          <option value="">All Staff</option>
          <?php foreach ($staffList as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $staffId == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">
      <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Generate</button>
      <a href="?date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d') ?>&date=<?= date('Y-m-d') ?>" class="btn btn-outline">Today</a>
      <a href="?date_from=<?= date('Y-m-01') ?>&date_to=<?= date('Y-m-d') ?>&date=<?= date('Y-m-d') ?>" class="btn btn-outline">This Month</a>
      <button type="button" onclick="window.print()" class="btn btn-outline"><i class="fas fa-print"></i> Print</button>
    </form>
  </div>
</div>

<!-- 5 STAT CARDS -->
<div class="grid-4" style="margin-bottom:20px;">
  <div class="stat-card green">
    <div class="stat-icon" style="background:#e8f8f0;">&#x1F4B0;</div>
    <div class="stat-info"><div class="value" style="font-size:18px;"><?= formatCurrency($totals['revenue']) ?></div><div class="label">Total Revenue</div></div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon" style="background:#e8f4fd;">&#x1F9FE;</div>
    <div class="stat-info"><div class="value"><?= number_format($totals['sales']) ?></div><div class="label">Transactions</div></div>
  </div>
  <div class="stat-card orange">
    <div class="stat-icon" style="background:#fef9e7;">&#x1F48A;</div>
    <div class="stat-info"><div class="value"><?= number_format($totals['items']) ?></div><div class="label">Items Sold</div></div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon" style="background:#fdf2f2;">&#x1F3F7;&#xFE0F;</div>
    <div class="stat-info"><div class="value" style="font-size:18px;"><?= formatCurrency($totals['discount']) ?></div><div class="label">Discounts Given</div></div>
  </div>
  <div class="stat-card <?= $grossProfit >= 0 ? 'green' : 'red' ?>">
    <div class="stat-icon" style="background:<?= $grossProfit >= 0 ? '#e8f8f0' : '#fdf2f2' ?>;">&#x1F4C8;</div>
    <div class="stat-info">
      <div class="value" style="font-size:18px;color:<?= $grossProfit >= 0 ? 'var(--secondary)' : 'var(--danger)' ?>;"><?= formatCurrency($grossProfit) ?></div>
      <div class="label">Est. Gross Profit</div>
    </div>
  </div>
</div>
<!-- DAILY TABLE + MEDICINE BREAKDOWN -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

  <!-- Daily Breakdown -->
  <div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-calendar-day" style="color:var(--primary)"></i> Daily Breakdown</div></div>
    <div class="table-responsive">
      <table>
        <thead><tr><th>Date</th><th>Txns</th><th>Items</th><th>Revenue</th><th>Cost</th><th>Profit</th><th>Detail</th></tr></thead>
        <tbody>
          <?php if (empty($daily)): ?>
          <tr><td colspan="7"><div class="empty-state" style="padding:30px;"><div class="empty-icon" style="font-size:36px;">&#x1F4CA;</div><p>No sales data for this period</p></div></td></tr>
          <?php else: ?>
          <?php foreach ($daily as $d): ?>
          <?php $rowProfit = $d['total_revenue'] - $d['total_cost']; ?>
          <tr style="<?= $d['sale_date'] == $selectedDate ? 'background:var(--primary-light);font-weight:600;' : '' ?>">
            <td>
              <?= date('D d M', strtotime($d['sale_date'])) ?>
              <?php if ($d['sale_date'] == date('Y-m-d')): ?><span class="badge badge-success" style="font-size:9px;">Today</span><?php endif; ?>
            </td>
            <td><?= $d['total_sales'] ?></td>
            <td><?= number_format($d['total_items']) ?></td>
            <td style="color:var(--primary);font-weight:700;"><?= formatCurrency($d['total_revenue']) ?></td>
            <td style="color:var(--text-muted);font-size:12px;"><?= formatCurrency($d['total_cost']) ?></td>
            <td style="color:<?= $rowProfit >= 0 ? 'var(--secondary)' : 'var(--danger)' ?>;font-weight:700;"><?= formatCurrency($rowProfit) ?></td>
            <td>
              <a href="?date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&date=<?= $d['sale_date'] ?>&branch_id=<?= $branchId ?>&staff_id=<?= $staffId ?>"
                 class="btn btn-info btn-sm btn-icon" title="View detail"><i class="fas fa-eye"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
          <tr style="background:var(--light);font-weight:700;border-top:2px solid var(--border);">
            <td>TOTAL</td><td><?= $totals['sales'] ?></td><td><?= number_format($totals['items']) ?></td>
            <td style="color:var(--primary)"><?= formatCurrency($totals['revenue']) ?></td>
            <td style="color:var(--text-muted)"><?= formatCurrency($totals['cost']) ?></td>
            <td style="color:<?= $grossProfit >= 0 ? 'var(--secondary)' : 'var(--danger)' ?>"><?= formatCurrency($grossProfit) ?></td>
            <td></td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Medicine Breakdown -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-pills" style="color:var(--info)"></i> Medicines Sold &mdash; <?= date('d M Y', strtotime($selectedDate)) ?></div>
    </div>
    <?php if (empty($dayMeds)): ?>
    <div class="card-body">
      <div class="empty-state" style="padding:30px;">
        <div class="empty-icon" style="font-size:36px;">&#x1F48A;</div>
        <p>No sales on this date.<br>Click <i class="fas fa-eye"></i> on a row to load a date.</p>
      </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table>
        <thead><tr><th>#</th><th>Medicine</th><th>Qty</th><th>Price</th><th>Revenue</th><th>Share</th></tr></thead>
        <tbody>
          <?php $grandRev = array_sum(array_column($dayMeds, 'revenue')); ?>
          <?php foreach ($dayMeds as $i => $m): ?>
          <?php $pct = $grandRev > 0 ? round(($m['revenue'] / $grandRev) * 100, 1) : 0; ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td>
              <strong><?= htmlspecialchars($m['name']) ?></strong>
              <div style="font-size:10px;color:var(--text-muted);"><?= htmlspecialchars($m['category']) ?> &middot; <?= htmlspecialchars($m['unit']) ?></div>
            </td>
            <td><strong><?= number_format($m['qty_sold']) ?></strong></td>
            <td style="font-size:12px;"><?= formatCurrency($m['sell_price']) ?></td>
            <td style="color:var(--primary);font-weight:700;"><?= formatCurrency($m['revenue']) ?></td>
            <td style="min-width:80px;">
              <div style="font-size:11px;color:var(--text-muted);margin-bottom:2px;"><?= $pct ?>%</div>
              <div class="progress"><div class="progress-bar green" style="width:<?= $pct ?>%"></div></div>
            </td>
          </tr>
          <?php endforeach; ?>
          <tr style="background:var(--light);font-weight:700;">
            <td colspan="2">TOTAL</td>
            <td><?= number_format(array_sum(array_column($dayMeds, 'qty_sold'))) ?></td>
            <td>&mdash;</td>
            <td style="color:var(--primary)"><?= formatCurrency($grandRev) ?></td>
            <td>100%</td>
          </tr>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<!-- STAFF BREAKDOWN -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <div class="card-title"><i class="fas fa-user-tie" style="color:var(--warning)"></i> Sales by Staff &mdash; <?= date('d M Y', strtotime($selectedDate)) ?></div>
    <a href="/pharmacy/reports/staff.php?date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&branch_id=<?= $branchId ?>" class="btn btn-outline btn-sm"><i class="fas fa-chart-bar"></i> Full Staff Report</a>
  </div>
  <?php if (empty($staffBreakdown)): ?>
  <div class="card-body">
    <div class="empty-state" style="padding:20px;">
      <div class="empty-icon" style="font-size:30px;">&#x1F464;</div>
      <p>No staff sales data for <?= date('d M Y', strtotime($selectedDate)) ?></p>
    </div>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table>
      <thead>
        <tr><th>#</th><th>Staff Name</th><th>Role</th><th>Transactions</th><th>Items Sold</th><th>Revenue</th><th>Discounts</th><th>Avg Sale</th><th>Revenue Share</th></tr>
      </thead>
      <tbody>
        <?php $totalStaffRev = array_sum(array_column($staffBreakdown, 'revenue')); ?>
        <?php $colors = ['#1a6b3c', '#3498db', '#f39c12', '#e74c3c', '#9b59b6']; ?>
        <?php foreach ($staffBreakdown as $i => $s): ?>
        <?php $share = $totalStaffRev > 0 ? round(($s['revenue'] / $totalStaffRev) * 100, 1) : 0; ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="width:32px;height:32px;border-radius:50%;background:<?= $colors[$i % 5] ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">
                <?= strtoupper(substr($s['staff_name'], 0, 1)) ?>
              </div>
              <strong><?= htmlspecialchars($s['staff_name']) ?></strong>
            </div>
          </td>
          <td><span class="badge badge-info"><?= ucfirst(str_replace('_', ' ', $s['role'])) ?></span></td>
          <td><?= $s['sales_count'] ?></td>
          <td><?= number_format($s['items_sold']) ?></td>
          <td><strong style="color:var(--primary)"><?= formatCurrency($s['revenue']) ?></strong></td>
          <td style="color:var(--danger);"><?= formatCurrency($s['discounts']) ?></td>
          <td><?= $s['sales_count'] > 0 ? formatCurrency($s['revenue'] / $s['sales_count']) : '&mdash;' ?></td>
          <td style="min-width:120px;">
            <div style="font-size:11px;margin-bottom:2px;"><?= $share ?>%</div>
            <div class="progress"><div class="progress-bar" style="width:<?= $share ?>%;background:<?= $colors[$i % 5] ?>"></div></div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<!-- CHART.JS BAR CHART -->
<?php if (!empty($daily)): ?>
<div class="card" style="margin-bottom:20px;">
  <div class="card-header"><div class="card-title"><i class="fas fa-chart-bar" style="color:var(--primary)"></i> Revenue, Profit &amp; Items Chart</div></div>
  <div class="card-body"><div class="chart-container"><canvas id="dailyChart"></canvas></div></div>
</div>
<script>
(function() {
    var rawData = <?= json_encode(array_reverse($daily)) ?>;
    var labels  = rawData.map(function($d) {
        var parts = $d.sale_date.split('-');
        var dt = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
    });
    var revenue = rawData.map(function($d) { return parseFloat($d.total_revenue) || 0; });
    var profit  = rawData.map(function($d) { return (parseFloat($d.total_revenue) || 0) - (parseFloat($d.total_cost) || 0); });
    var items   = rawData.map(function($d) { return parseInt($d.total_items) || 0; });

    new Chart(document.getElementById('dailyChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Revenue (<?= CURRENCY ?>)',
                    data: revenue,
                    backgroundColor: 'rgba(26,107,60,0.75)',
                    borderColor: '#1a6b3c',
                    borderWidth: 1,
                    borderRadius: 5,
                    yAxisID: 'y'
                },
                {
                    label: 'Est. Profit (<?= CURRENCY ?>)',
                    data: profit,
                    backgroundColor: 'rgba(46,204,113,0.5)',
                    borderColor: '#2ecc71',
                    borderWidth: 1,
                    borderRadius: 5,
                    yAxisID: 'y'
                },
                {
                    label: 'Items Sold',
                    data: items,
                    type: 'line',
                    borderColor: '#f39c12',
                    backgroundColor: 'rgba(243,156,18,0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4,
                    pointRadius: 4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: {
                    beginAtZero: true,
                    position: 'left',
                    ticks: { callback: function(v) { return '<?= CURRENCY ?> ' + v.toLocaleString(); } },
                    grid: { color: '#f0f0f0' }
                },
                y1: { beginAtZero: true, position: 'right', grid: { display: false } },
                x:  { grid: { display: false } }
            }
        }
    });
})();
</script>
<?php endif; ?>

<style>
@media print {
    .sidebar, .topbar, .main-wrapper > header, .card:first-child { display: none !important; }
    .main-wrapper { margin-left: 0 !important; }
    .page-content { padding: 0 !important; }
}
</style>

<?php require_once '../includes/footer.php'; ?>