<?php
require_once '../config/db.php';
requireLogin();
define('PAGE_TITLE', 'Stock Management');
define('PAGE_SUBTITLE', 'Monitor inventory levels');

$filter = $_GET['filter'] ?? '';
$search = trim($_GET['search'] ?? '');
$branchId = (int)($_GET['branch_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];

if (!isSuperAdmin()) {
    $where[] = 's.branch_id = ?';
    $params[] = getUserBranchId();
} elseif ($branchId) {
    $where[] = 's.branch_id = ?';
    $params[] = $branchId;
}

if ($search) {
    $where[] = 'm.name LIKE ?';
    $params[] = "%$search%";
}

if ($filter === 'low') {
    $where[] = 's.quantity <= s.low_stock_threshold AND s.quantity > 0';
} elseif ($filter === 'out') {
    $where[] = 's.quantity = 0';
} elseif ($filter === 'expiring') {
    $where[] = 's.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
}

$whereStr = implode(' AND ', $where);

$total = $pdo->prepare("SELECT COUNT(*) FROM stock s JOIN medicines m ON s.medicine_id=m.id WHERE $whereStr");
$total->execute($params);
$totalCount = $total->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

$stmt = $pdo->prepare("
    SELECT s.*, m.name as medicine_name, m.unit, m.requires_prescription,
           b.name as branch_name, c.name as category_name
    FROM stock s
    JOIN medicines m ON s.medicine_id = m.id
    JOIN branches b ON s.branch_id = b.id
    LEFT JOIN categories c ON m.category_id = c.id
    WHERE $whereStr
    ORDER BY s.quantity ASC, m.name ASC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$stocks = $stmt->fetchAll();

$branches = $pdo->query("SELECT * FROM branches WHERE status='active' ORDER BY name")->fetchAll();

// Summary counts
$branchCond = isSuperAdmin() ? '' : 'AND s.branch_id = ' . (int)getUserBranchId();
$totalItems = $pdo->query("SELECT COUNT(*) FROM stock s WHERE 1=1 $branchCond")->fetchColumn();
$lowCount   = $pdo->query("SELECT COUNT(*) FROM stock s WHERE s.quantity <= s.low_stock_threshold AND s.quantity > 0 $branchCond")->fetchColumn();
$outCount   = $pdo->query("SELECT COUNT(*) FROM stock s WHERE s.quantity = 0 $branchCond")->fetchColumn();
$expCount   = $pdo->query("SELECT COUNT(*) FROM stock s WHERE s.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) $branchCond")->fetchColumn();

require_once '../includes/header.php';
?>

<!-- Quick Filter Cards -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
    <a href="?filter=" style="text-decoration:none;">
        <div class="stat-card blue" style="<?= !$filter ? 'border:2px solid var(--info)' : '' ?>">
            <div class="stat-icon">📦</div>
            <div class="stat-info"><div class="value"><?= $totalItems ?></div><div class="label">Total Items</div></div>
        </div>
    </a>
    <a href="?filter=low" style="text-decoration:none;">
        <div class="stat-card orange" style="<?= $filter=='low' ? 'border:2px solid var(--warning)' : '' ?>">
            <div class="stat-icon">⚠️</div>
            <div class="stat-info"><div class="value"><?= $lowCount ?></div><div class="label">Low Stock</div></div>
        </div>
    </a>
    <a href="?filter=out" style="text-decoration:none;">
        <div class="stat-card red" style="<?= $filter=='out' ? 'border:2px solid var(--danger)' : '' ?>">
            <div class="stat-icon">❌</div>
            <div class="stat-info"><div class="value"><?= $outCount ?></div><div class="label">Out of Stock</div></div>
        </div>
    </a>
    <a href="?filter=expiring" style="text-decoration:none;">
        <div class="stat-card red" style="<?= $filter=='expiring' ? 'border:2px solid var(--danger)' : '' ?>">
            <div class="stat-icon">📅</div>
            <div class="stat-info"><div class="value"><?= $expCount ?></div><div class="label">Expiring Soon</div></div>
        </div>
    </a>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-boxes" style="color:var(--primary)"></i> Stock Inventory</div>
        <?php if (isBranchManager()): ?>
        <a href="/pharmacy/stock/add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Stock</a>
        <?php endif; ?>
    </div>
    <div class="card-body" style="padding-bottom:0;">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-bottom:15px;">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            <div class="search-bar">
                <i class="fas fa-search search-icon"></i>
                <input type="text" name="search" placeholder="Search medicine..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <?php if (isSuperAdmin()): ?>
            <select name="branch_id" class="form-control" style="width:auto;">
                <option value="">All Branches</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $branchId == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="/pharmacy/stock/index.php" class="btn btn-outline">Reset</a>
        </form>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Medicine</th>
                    <th>Category</th>
                    <?php if (isSuperAdmin()): ?><th>Branch</th><?php endif; ?>
                    <th>Stock Level</th>
                    <th>Threshold</th>
                    <th>Buy Price</th>
                    <th>Sell Price</th>
                    <th>Expiry Date</th>
                    <th>Batch</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($stocks)): ?>
                <tr><td colspan="11"><div class="empty-state"><div class="empty-icon">📦</div><p>No stock records found</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($stocks as $i => $s): ?>
                <?php
                $stockStatus = $s['quantity'] == 0 ? 'out' : ($s['quantity'] <= $s['low_stock_threshold'] ? 'low' : 'good');
                $expDays = $s['expiry_date'] ? (strtotime($s['expiry_date']) - time()) / 86400 : 999;
                ?>
                <tr>
                    <td><?= $offset + $i + 1 ?></td>
                    <td>
                        <a href="/pharmacy/medicines/view.php?id=<?= $s['medicine_id'] ?>" style="font-weight:600;color:var(--dark);text-decoration:none;">
                            <?= htmlspecialchars($s['medicine_name']) ?>
                        </a>
                        <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($s['unit']) ?></div>
                    </td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($s['category_name'] ?? '-') ?></span></td>
                    <?php if (isSuperAdmin()): ?><td><?= htmlspecialchars($s['branch_name']) ?></td><?php endif; ?>
                    <td>
                        <?php if ($stockStatus === 'out'): ?>
                            <span class="badge badge-danger">Out of Stock</span>
                        <?php elseif ($stockStatus === 'low'): ?>
                            <div class="stock-level">
                                <span class="qty medium"><?= $s['quantity'] ?> units</span>
                                <div class="progress" style="width:80px;">
                                    <div class="progress-bar orange" style="width:<?= min(100, ($s['quantity']/$s['low_stock_threshold'])*100) ?>%"></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <span class="qty good fw-bold"><?= number_format($s['quantity']) ?> units</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $s['low_stock_threshold'] ?></td>
                    <td><?= formatCurrency($s['buying_price']) ?></td>
                    <td><strong><?= formatCurrency($s['selling_price']) ?></strong></td>
                    <td>
                        <?php if ($s['expiry_date']): ?>
                        <span class="<?= $expDays < 0 ? 'expiry-expired' : ($expDays <= 30 ? 'expiry-soon' : '') ?>">
                            <?= date('d M Y', strtotime($s['expiry_date'])) ?>
                        </span>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <td style="font-size:12px;"><?= htmlspecialchars($s['batch_number'] ?? '-') ?></td>
                    <td>
                        <?php if (isBranchManager()): ?>
                        <a href="/pharmacy/stock/edit.php?id=<?= $s['id'] ?>" class="btn btn-warning btn-sm btn-icon" title="Edit Stock"><i class="fas fa-edit"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>">‹ Prev</a><?php endif; ?>
        <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
        <a href="?page=<?= $p ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>" class="<?= $p==$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?><a href="?page=<?= $page+1 ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>">Next ›</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
