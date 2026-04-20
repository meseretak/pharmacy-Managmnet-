<?php
require_once '../config/db.php';
requireLogin();
define('PAGE_TITLE', 'Sales History');
define('PAGE_SUBTITLE', 'All transactions');

$branchId = getUserBranchId() ?? 1;
$search   = trim($_GET['search'] ?? '');
$status   = $_GET['status'] ?? '';
$method   = $_GET['method'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if (!isSuperAdmin()) { $where[] = 'sa.branch_id = ?'; $params[] = $branchId; }
if ($search) { $where[] = '(sa.invoice_number LIKE ? OR sa.customer_name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status) { $where[] = 'sa.status = ?'; $params[] = $status; }
if ($method) { $where[] = 'sa.payment_method = ?'; $params[] = $method; }
if ($dateFrom) { $where[] = 'DATE(sa.created_at) >= ?'; $params[] = $dateFrom; }
if ($dateTo)   { $where[] = 'DATE(sa.created_at) <= ?'; $params[] = $dateTo; }

$whereStr = implode(' AND ', $where);

$total = $pdo->prepare("SELECT COUNT(*) FROM sales sa WHERE $whereStr");
$total->execute($params); $totalCount = $total->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

$stmt = $pdo->prepare("
    SELECT sa.*, b.name as branch_name, u.name as cashier
    FROM sales sa
    JOIN branches b ON sa.branch_id = b.id
    JOIN users u ON sa.user_id = u.id
    WHERE $whereStr
    ORDER BY sa.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params); $sales = $stmt->fetchAll();

// Summary stats
$summary = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as rev, COALESCE(SUM(discount),0) as disc FROM sales sa WHERE $whereStr AND sa.status='completed'");
$summary->execute($params); $summary = $summary->fetch();

$branches = isSuperAdmin() ? $pdo->query("SELECT * FROM branches WHERE status='active' ORDER BY name")->fetchAll() : [];

require_once '../includes/header.php';
?>

<div class="grid-3" style="margin-bottom:20px;">
    <div class="stat-card green"><div class="stat-icon">💰</div><div class="stat-info"><div class="value"><?= formatCurrency($summary['rev']) ?></div><div class="label">Total Revenue</div></div></div>
    <div class="stat-card blue"><div class="stat-icon">🧾</div><div class="stat-info"><div class="value"><?= number_format($summary['cnt']) ?></div><div class="label">Transactions</div></div></div>
    <div class="stat-card orange"><div class="stat-icon">🏷️</div><div class="stat-info"><div class="value"><?= formatCurrency($summary['disc']) ?></div><div class="label">Total Discounts</div></div></div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-receipt" style="color:var(--primary)"></i> Sales History</div>
        <a href="/pharmacy/sales/new.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Sale</a>
    </div>
    <div class="card-body" style="padding-bottom:0;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px;align-items:flex-end;">
            <div class="search-bar">
                <i class="fas fa-search search-icon"></i>
                <input type="text" name="search" placeholder="Invoice or customer..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <input type="date" name="date_from" class="form-control" style="width:auto;" value="<?= $dateFrom ?>">
            <input type="date" name="date_to" class="form-control" style="width:auto;" value="<?= $dateTo ?>">
            <select name="status" class="form-control" style="width:auto;">
                <option value="">All Status</option>
                <option value="completed" <?= $status=='completed'?'selected':'' ?>>Completed</option>
                <option value="pending" <?= $status=='pending'?'selected':'' ?>>Pending</option>
                <option value="refunded" <?= $status=='refunded'?'selected':'' ?>>Refunded</option>
                <option value="cancelled" <?= $status=='cancelled'?'selected':'' ?>>Cancelled</option>
            </select>
            <select name="method" class="form-control" style="width:auto;">
                <option value="">All Methods</option>
                <option value="cash" <?= $method=='cash'?'selected':'' ?>>Cash</option>
                <option value="card" <?= $method=='card'?'selected':'' ?>>Card</option>
                <option value="mobile_money" <?= $method=='mobile_money'?'selected':'' ?>>Mobile Money</option>
            </select>
            <?php if (isSuperAdmin()): ?>
            <select name="branch_id" class="form-control" style="width:auto;">
                <option value="">All Branches</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="/pharmacy/sales/index.php" class="btn btn-outline">Reset</a>
        </form>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <?php if (isSuperAdmin()): ?><th>Branch</th><?php endif; ?>
                    <th>Cashier</th>
                    <th>Amount</th>
                    <th>Discount</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sales)): ?>
                <tr><td colspan="10"><div class="empty-state"><div class="empty-icon">🧾</div><p>No sales found</p></div></td></tr>
                <?php else: foreach ($sales as $s): ?>
                <tr>
                    <td><a href="/pharmacy/sales/view.php?id=<?= $s['id'] ?>" style="font-weight:700;color:var(--primary);"><?= htmlspecialchars($s['invoice_number']) ?></a></td>
                    <td><?= htmlspecialchars($s['customer_name']) ?><?php if ($s['customer_phone']): ?><div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($s['customer_phone']) ?></div><?php endif; ?></td>
                    <?php if (isSuperAdmin()): ?><td style="font-size:12px;"><?= htmlspecialchars($s['branch_name']) ?></td><?php endif; ?>
                    <td style="font-size:12px;"><?= htmlspecialchars($s['cashier']) ?></td>
                    <td><strong style="color:var(--primary)"><?= formatCurrency($s['total_amount']) ?></strong></td>
                    <td><?= $s['discount'] > 0 ? '<span style="color:var(--danger);">-'.formatCurrency($s['discount']).'</span>' : '-' ?></td>
                    <td><span class="badge badge-info"><?= ucfirst(str_replace('_',' ',$s['payment_method'])) ?></span></td>
                    <td><span class="badge <?= $s['status']=='completed'?'badge-success':($s['status']=='pending'?'badge-warning':($s['status']=='refunded'?'badge-info':'badge-danger')) ?>"><?= ucfirst($s['status']) ?></span></td>
                    <td style="font-size:12px;"><?= date('d M Y H:i', strtotime($s['created_at'])) ?></td>
                    <td>
                        <a href="/pharmacy/sales/view.php?id=<?= $s['id'] ?>" class="btn btn-info btn-sm">View</a>
                        <?php if ($s['status']==='completed' && isBranchManager()): ?>
                        <a href="/pharmacy/sales/refund.php?id=<?= $s['id'] ?>" class="btn btn-danger btn-sm">Refund</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&method=<?= $method ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">‹ Prev</a><?php endif; ?>
        <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
        <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&method=<?= $method ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="<?= $p==$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?><a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&method=<?= $method ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">Next ›</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>
