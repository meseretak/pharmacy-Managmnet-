<?php
require_once '../config/db.php';
requireLogin();
define('PAGE_TITLE', 'Prescriptions');
define('PAGE_SUBTITLE', 'Manage patient prescriptions');

$branchId = getUserBranchId() ?? 1;
$branchFilter = isSuperAdmin() ? '' : 'AND p.branch_id = ' . $branchId;
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

$where = ['1=1'];
$params = [];
if ($search) { $where[] = '(p.customer_name LIKE ? OR p.doctor_name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status) { $where[] = 'p.status = ?'; $params[] = $status; }
$whereStr = implode(' AND ', $where);

$prescriptions = $pdo->prepare("
    SELECT p.*, 'N/A' as branch_name, 'N/A' as created_by
    FROM prescriptions p
    WHERE $whereStr ORDER BY p.created_at DESC LIMIT 100
");
$prescriptions->execute($params);
$prescriptions = $prescriptions->fetchAll();

require_once '../includes/header.php';
?>
<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <a href="/pharmacy/prescriptions/add.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Prescription</a>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-file-medical" style="color:var(--primary)"></i> Prescriptions</div>
    </div>
    <div class="card-body" style="padding-bottom:0;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px;">
            <input type="text" name="search" class="form-control" placeholder="Search patient or doctor..." value="<?= htmlspecialchars($search) ?>" style="max-width:250px;">
            <select name="status" class="form-control" style="width:auto;">
                <option value="">All Status</option>
                <option value="pending" <?= $status=='pending'?'selected':'' ?>>Pending</option>
                <option value="dispensed" <?= $status=='dispensed'?'selected':'' ?>>Dispensed</option>
                <option value="expired" <?= $status=='expired'?'selected':'' ?>>Expired</option>
                <option value="cancelled" <?= $status=='cancelled'?'selected':'' ?>>Cancelled</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
            <a href="/pharmacy/prescriptions/index.php" class="btn btn-outline">Reset</a>
        </form>
    </div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>#</th><th>Patient</th><th>Doctor</th><th>Hospital</th><th>Issue Date</th><th>Expiry</th><th>Status</th><th>Branch</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($prescriptions)): ?>
                <tr><td colspan="9"><div class="empty-state"><div class="empty-icon">📋</div><p>No prescriptions found</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($prescriptions as $i => $p): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($p['customer_name']) ?></strong><div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($p['customer_phone'] ?? '') ?></div></td>
                    <td><?= htmlspecialchars($p['doctor_name'] ?? '-') ?></td>
                    <td style="font-size:12px;"><?= htmlspecialchars($p['hospital_clinic'] ?? '-') ?></td>
                    <td><?= $p['issue_date'] ? date('d M Y', strtotime($p['issue_date'])) : '-' ?></td>
                    <td><?= $p['expiry_date'] ? date('d M Y', strtotime($p['expiry_date'])) : '-' ?></td>
                    <td><span class="badge <?= $p['status']=='dispensed'?'badge-success':($p['status']=='pending'?'badge-warning':($p['status']=='expired'?'badge-danger':'badge-info')) ?>"><?= ucfirst($p['status']) ?></span></td>
                    <td style="font-size:12px;"><?= htmlspecialchars($p['branch_name']) ?></td>
                    <td>
                        <a href="/pharmacy/prescriptions/view.php?id=<?= $p['id'] ?>" class="btn btn-info btn-sm">View</a>
                        <?php if ($p['status'] === 'pending'): ?>
                        <a href="/pharmacy/sales/new.php?prescription_id=<?= $p['id'] ?>" class="btn btn-success btn-sm">Dispense</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
