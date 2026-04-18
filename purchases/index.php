<?php
require_once '../config/db.php';
requireLogin();
define('PAGE_TITLE', 'Purchase Orders');
define('PAGE_SUBTITLE', 'Stock restocking & supplier orders');

$branchFilter = isSuperAdmin() ? '' : 'AND p.branch_id = ' . (int)getUserBranchId();

$purchases = $pdo->query("
    SELECT p.*, b.name as branch_name, s.name as supplier_name, u.name as created_by
    FROM purchases p
    JOIN branches b ON p.branch_id = b.id
    JOIN suppliers s ON p.supplier_id = s.id
    JOIN users u ON p.user_id = u.id
    WHERE 1=1 $branchFilter
    ORDER BY p.created_at DESC
    LIMIT 50
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-truck" style="color:var(--primary)"></i> Purchase Orders</div>
        <?php if (isBranchManager()): ?>
        <a href="/pharmacy/purchases/add.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Purchase</a>
        <?php endif; ?>
    </div>
    <?php if (empty($purchases)): ?>
    <div class="card-body"><div class="empty-state"><div class="empty-icon">🚚</div><p>No purchase orders yet</p></div></div>
    <?php else: ?>
    <div class="table-responsive">
        <table>
            <thead><tr><th>#</th><th>Invoice Ref</th><th>Supplier</th><?php if(isSuperAdmin()):?><th>Branch</th><?php endif;?><th>Total</th><th>Status</th><th>Created By</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($purchases as $i => $p): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($p['invoice_ref'] ?? 'N/A') ?></strong></td>
                    <td><?= htmlspecialchars($p['supplier_name']) ?></td>
                    <?php if(isSuperAdmin()):?><td><?= htmlspecialchars($p['branch_name']) ?></td><?php endif;?>
                    <td><strong><?= formatCurrency($p['total_amount']) ?></strong></td>
                    <td><span class="badge <?= $p['status']=='received'?'badge-success':($p['status']=='pending'?'badge-warning':'badge-danger') ?>"><?= ucfirst($p['status']) ?></span></td>
                    <td><?= htmlspecialchars($p['created_by']) ?></td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                    <td><a href="/pharmacy/purchases/view.php?id=<?= $p['id'] ?>" class="btn btn-info btn-sm btn-icon"><i class="fas fa-eye"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
