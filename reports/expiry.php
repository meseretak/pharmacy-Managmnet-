<?php
require_once '../config/db.php';
requireLogin();
define('PAGE_TITLE', 'Expiry Report');
define('PAGE_SUBTITLE', 'Track medicine expiry dates');

$days = (int)($_GET['days'] ?? 90);
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

$params[] = $days;

$stocks = $pdo->prepare("
    SELECT s.*, m.name as medicine_name, m.unit, b.name as branch_name,
           DATEDIFF(s.expiry_date, CURDATE()) as days_left
    FROM stock s
    JOIN medicines m ON s.medicine_id = m.id
    JOIN branches b ON s.branch_id = b.id
    WHERE s.expiry_date IS NOT NULL AND s.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY) $branchCond
    ORDER BY s.expiry_date ASC
");
$params2 = [];
if (!isSuperAdmin()) { $params2[] = getUserBranchId(); }
elseif ($branchId) { $params2[] = $branchId; }
$params2[] = $days;
$stocks->execute($params2);
$stocks = $stocks->fetchAll();

$expired = array_filter($stocks, fn($s) => $s['days_left'] < 0);
$expiring7 = array_filter($stocks, fn($s) => $s['days_left'] >= 0 && $s['days_left'] <= 7);
$expiring30 = array_filter($stocks, fn($s) => $s['days_left'] > 7 && $s['days_left'] <= 30);
$expiring90 = array_filter($stocks, fn($s) => $s['days_left'] > 30);

$branches = $pdo->query("SELECT * FROM branches WHERE status='active' ORDER BY name")->fetchAll();

require_once '../includes/header.php';
?>

<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:15px 22px;">
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Show expiring within</label>
                <select name="days" class="form-control">
                    <option value="7" <?= $days==7?'selected':'' ?>>7 days</option>
                    <option value="30" <?= $days==30?'selected':'' ?>>30 days</option>
                    <option value="60" <?= $days==60?'selected':'' ?>>60 days</option>
                    <option value="90" <?= $days==90?'selected':'' ?>>90 days</option>
                    <option value="180" <?= $days==180?'selected':'' ?>>180 days</option>
                </select>
            </div>
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
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
        </form>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
    <div class="stat-card red"><div class="stat-icon">💀</div><div class="stat-info"><div class="value"><?= count($expired) ?></div><div class="label">Already Expired</div></div></div>
    <div class="stat-card red"><div class="stat-icon">🚨</div><div class="stat-info"><div class="value"><?= count($expiring7) ?></div><div class="label">Expiring in 7 Days</div></div></div>
    <div class="stat-card orange"><div class="stat-icon">⚠️</div><div class="stat-info"><div class="value"><?= count($expiring30) ?></div><div class="label">Expiring in 30 Days</div></div></div>
    <div class="stat-card blue"><div class="stat-icon">📅</div><div class="stat-info"><div class="value"><?= count($expiring90) ?></div><div class="label">Expiring in 31-<?= $days ?> Days</div></div></div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-calendar-times" style="color:var(--danger)"></i> Expiry Details (<?= count($stocks) ?> items)</div>
    </div>
    <?php if (empty($stocks)): ?>
    <div class="card-body"><div class="empty-state"><div class="empty-icon">✅</div><p>No medicines expiring within <?= $days ?> days</p></div></div>
    <?php else: ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Medicine</th>
                    <?php if (isSuperAdmin()): ?><th>Branch</th><?php endif; ?>
                    <th>Batch</th>
                    <th>Quantity</th>
                    <th>Expiry Date</th>
                    <th>Days Left</th>
                    <th>Stock Value</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stocks as $s): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['medicine_name']) ?></strong><div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($s['unit']) ?></div></td>
                    <?php if (isSuperAdmin()): ?><td><?= htmlspecialchars($s['branch_name']) ?></td><?php endif; ?>
                    <td style="font-size:12px;"><?= htmlspecialchars($s['batch_number'] ?? '-') ?></td>
                    <td><?= number_format($s['quantity']) ?></td>
                    <td class="<?= $s['days_left'] < 0 ? 'expiry-expired' : ($s['days_left'] <= 30 ? 'expiry-soon' : '') ?>">
                        <?= date('d M Y', strtotime($s['expiry_date'])) ?>
                    </td>
                    <td>
                        <?php if ($s['days_left'] < 0): ?>
                        <span class="badge badge-danger">Expired <?= abs($s['days_left']) ?> days ago</span>
                        <?php elseif ($s['days_left'] == 0): ?>
                        <span class="badge badge-danger">Expires Today</span>
                        <?php elseif ($s['days_left'] <= 7): ?>
                        <span class="badge badge-danger"><?= $s['days_left'] ?> days</span>
                        <?php elseif ($s['days_left'] <= 30): ?>
                        <span class="badge badge-warning"><?= $s['days_left'] ?> days</span>
                        <?php else: ?>
                        <span class="badge badge-info"><?= $s['days_left'] ?> days</span>
                        <?php endif; ?>
                    </td>
                    <td><?= formatCurrency($s['quantity'] * $s['selling_price']) ?></td>
                    <td>
                        <?php if ($s['days_left'] < 0): ?>
                        <span class="badge badge-danger">EXPIRED</span>
                        <?php elseif ($s['days_left'] <= 7): ?>
                        <span class="badge badge-danger">CRITICAL</span>
                        <?php elseif ($s['days_left'] <= 30): ?>
                        <span class="badge badge-warning">WARNING</span>
                        <?php else: ?>
                        <span class="badge badge-info">MONITOR</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
