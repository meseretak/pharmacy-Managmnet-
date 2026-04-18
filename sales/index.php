<?php
require_once '../config/db.php';
requireLogin();
if (!defined('PAGE_TITLE'))    define('PAGE_TITLE', 'Sales History');
if (!defined('PAGE_SUBTITLE')) define('PAGE_SUBTITLE', 'All transactions');

$search   = trim($_GET['search'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';
$branchId = (int)($_GET['branch_id'] ?? 0);
$staffId  = (int)($_GET['staff_id']  ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];
if (!isSuperAdmin()) { $where[] = 'sa.branch_id = ?'; $params[] = (int)getUserBranchId(); }
elseif ($branchId)   { $where[] = 'sa.branch_id = ?'; $params[] = $branchId; }
if ($staffId) { $where[] = 'sa.user_id = ?'; $params[] = $staffId; }
if ($search) {
    $where[] = '(sa.invoice_number LIKE ? OR sa.customer_name LIKE ? OR u.name LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($dateFrom) { $where[] = 'DATE(sa.created_at) >= ?'; $params[] = $dateFrom; }
if ($dateTo)   { $where[] = 'DATE(sa.created_at) <= ?'; $params[] = $dateTo; }
$whereStr = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM sales sa JOIN users u ON sa.user_id=u.id WHERE $whereStr");
$countStmt->execute($params); $totalCount = $countStmt->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

$stmt = $pdo->prepare("SELECT sa.*, b.name AS branch_name, u.name AS cashier, u.id AS cashier_id, r.name AS cashier_role FROM sales sa JOIN branches b ON sa.branch_id=b.id JOIN users u ON sa.user_id=u.id JOIN roles r ON u.role_id=r.id WHERE $whereStr ORDER BY sa.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params); $sales = $stmt->fetchAll();

$revStmt = $pdo->prepare("SELECT COALESCE(SUM(sa.total_amount),0) FROM sales sa JOIN users u ON sa.user_id=u.id WHERE $whereStr AND sa.status='completed'");
$revStmt->execute($params); $totalRevenue = $revStmt->fetchColumn();

$itemStmt = $pdo->prepare("SELECT COALESCE(SUM(si.quantity),0) FROM sale_items si JOIN sales sa ON si.sale_id=sa.id JOIN users u ON sa.user_id=u.id WHERE $whereStr AND sa.status='completed'");
$itemStmt->execute($params); $totalItems = $itemStmt->fetchColumn();

$branches = $pdo->query("SELECT * FROM branches WHERE status='active' ORDER BY name")->fetchAll();
$bid4staff = isSuperAdmin() ? ($branchId ?: null) : getUserBranchId();
try {
    if ($bid4staff) { $sfS = $pdo->prepare("SELECT u.id,u.name FROM users u WHERE u.branch_id=? AND u.status='active' ORDER BY u.name"); $sfS->execute([$bid4staff]); }
    else { $sfS = $pdo->query("SELECT u.id,u.name FROM users u WHERE u.status='active' ORDER BY u.name"); }
    $staffList = $sfS->fetchAll();
} catch(Exception $e) { $staffList = []; }

require_once '../includes/header.php';
?>
<div class="card" style="margin-bottom:20px;"><div class="card-body" style="padding:15px 22px;">
<form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
<div class="search-bar"><i class="fas fa-search search-icon"></i><input type="text" name="search" placeholder="Invoice, customer or staff..." value="<?= htmlspecialchars($search) ?>"></div>
<div class="form-group" style="margin:0;"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>"></div>
<div class="form-group" style="margin:0;"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>"></div>
<?php if (isSuperAdmin()): ?><div class="form-group" style="margin:0;"><label class="form-label">Branch</label><select name="branch_id" class="form-control"><option value="">All Branches</option><?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= $branchId==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
<div class="form-group" style="margin:0;"><label class="form-label">Sold By</label><select name="staff_id" class="form-control"><option value="">All Staff</option><?php foreach ($staffList as $s): ?><option value="<?= $s['id'] ?>" <?= $staffId==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select></div>
<button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
<a href="/pharmacy/sales/index.php" class="btn btn-outline">Reset</a>
</form></div></div>

<div class="grid-3" style="margin-bottom:20px;">
<div class="stat-card green"><div class="stat-icon" style="background:#e8f8f0;">??</div><div class="stat-info"><div class="value" style="font-size:20px;"><?= formatCurrency($totalRevenue) ?></div><div class="label">Total Revenue</div></div></div>
<div class="stat-card blue"><div class="stat-icon" style="background:#e8f4fd;">??</div><div class="stat-info"><div class="value"><?= number_format($totalCount) ?></div><div class="label">Transactions</div></div></div>
<div class="stat-card orange"><div class="stat-icon" style="background:#fef9e7;">??</div><div class="stat-info"><div class="value"><?= number_format($totalItems) ?></div><div class="label">Items Sold</div></div></div>
</div>

<div class="card">
<div class="card-header">
<div class="card-title"><i class="fas fa-receipt" style="color:var(--primary)"></i> Sales List (<?= number_format($totalCount) ?>)</div>
<div style="display:flex;gap:10px;"><a href="/pharmacy/reports/staff.php?date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&branch_id=<?= $branchId ?>" class="btn btn-outline btn-sm"><i class="fas fa-chart-bar"></i> Staff Report</a><a href="/pharmacy/sales/new.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Sale</a></div>
</div>
<div class="table-responsive"><table>
<thead><tr><th>Invoice</th><th>Customer</th><?php if(isSuperAdmin()):?><th>Branch</th><?php endif;?><th>Sold By</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
<tbody>
<?php if(empty($sales)):?><tr><td colspan="10"><div class="empty-state"><div class="empty-icon">??</div><p>No sales found</p></div></td></tr>
<?php else: foreach($sales as $sale):
$ic=$pdo->prepare("SELECT COUNT(*) FROM sale_items WHERE sale_id=?"); $ic->execute([$sale['id']]); $ic=$ic->fetchColumn();
$pm=trim($sale['payment_method']??'');
$pmColors=['cash'=>'badge-success','card'=>'badge-info','chapa'=>'badge-success','telebirr'=>'badge-warning','mobile_money'=>'badge-secondary'];
$pmClass=$pm?($pmColors[$pm]??'badge-secondary'):'badge-secondary';
$pmLabel=$pm?ucfirst(str_replace('_',' ',$pm)):'—';
?>
<tr>
<td><a href="/pharmacy/sales/view.php?id=<?= $sale['id'] ?>" style="color:var(--primary);font-weight:600;"><?= htmlspecialchars($sale['invoice_number']) ?></a></td>
<td><?= htmlspecialchars($sale['customer_name']) ?></td>
<?php if(isSuperAdmin()):?><td><span class="badge badge-info"><?= htmlspecialchars($sale['branch_name']) ?></span></td><?php endif;?>
<td><div style="display:flex;align-items:center;gap:8px;"><div style="width:28px;height:28px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;flex-shrink:0;"><?= strtoupper(substr($sale['cashier'],0,1)) ?></div><div><div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($sale['cashier']) ?></div><div style="font-size:10px;color:var(--text-muted);"><?= ucfirst(str_replace('_',' ',$sale['cashier_role'])) ?></div></div></div></td>
<td><?= $ic ?> item<?= $ic!=1?'s':'' ?></td>
<td><strong><?= formatCurrency($sale['total_amount']) ?></strong></td>
<td><span class="badge <?= $pmClass ?>"><?= $pmLabel ?></span></td>
<td><span class="badge <?= $sale['status']=='completed'?'badge-success':($sale['status']=='refunded'?'badge-danger':'badge-warning') ?>"><?= ucfirst($sale['status']) ?></span></td>
<td style="font-size:12px;color:var(--text-muted);"><?= date('d M Y H:i',strtotime($sale['created_at'])) ?></td>
<td><a href="/pharmacy/sales/view.php?id=<?= $sale['id'] ?>" class="btn btn-info btn-sm btn-icon"><i class="fas fa-eye"></i></a></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div>
<?php if($totalPages>1):?>
<div class="pagination">
<?php if($page>1):?><a href="?page=<?=$page-1?>&search=<?=urlencode($search)?>&date_from=<?=$dateFrom?>&date_to=<?=$dateTo?>&branch_id=<?=$branchId?>&staff_id=<?=$staffId?>">‹ Prev</a><?php endif;?>
<?php for($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++):?><a href="?page=<?=$p?>&search=<?=urlencode($search)?>&date_from=<?=$dateFrom?>&date_to=<?=$dateTo?>&branch_id=<?=$branchId?>&staff_id=<?=$staffId?>" class="<?=$p==$page?'active':''?>"><?=$p?></a><?php endfor;?>
<?php if($page<$totalPages):?><a href="?page=<?=$page+1?>&search=<?=urlencode($search)?>&date_from=<?=$dateFrom?>&date_to=<?=$dateTo?>&branch_id=<?=$branchId?>&staff_id=<?=$staffId?>">Next ›</a><?php endif;?>
</div>
<?php endif;?>
</div>
<?php require_once '../includes/footer.php'; ?>
