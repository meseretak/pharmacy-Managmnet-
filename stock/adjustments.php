<?php
require_once '../config/db.php';
requireLogin();
if (!isBranchManager()) { header('Location: /pharmacy/stock/index.php'); exit; }
define('PAGE_TITLE', 'Stock Adjustments');
define('PAGE_SUBTITLE', 'Record damage, loss, or corrections');

$branchId = getUserBranchId() ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stockId  = (int)$_POST['stock_id'];
    $type     = $_POST['adjustment_type'];
    $change   = (int)$_POST['quantity_change'];
    $reason   = trim($_POST['reason']);
    $validTypes = ['damage','loss','correction','expiry_removal'];
    if (!in_array($type, $validTypes) || $change == 0 || !$reason) {
        $error = 'Please fill all fields correctly.';
    } else {
        $stock = $pdo->prepare("SELECT s.*, m.name FROM stock s JOIN medicines m ON s.medicine_id=m.id WHERE s.id=? AND s.branch_id=?");
        $stock->execute([$stockId, $branchId]);
        $stock = $stock->fetch();
        if (!$stock) { $error = 'Stock item not found.'; }
        else {
            $qBefore = $stock['quantity'];
            $qChange = in_array($type, ['damage','loss','expiry_removal']) ? -abs($change) : $change;
            $qAfter  = max(0, $qBefore + $qChange);
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE stock SET quantity=? WHERE id=?")->execute([$qAfter, $stockId]);
            $pdo->prepare("INSERT INTO stock_adjustments (stock_id,branch_id,user_id,adjustment_type,quantity_before,quantity_change,quantity_after,reason) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$stockId, $branchId, $_SESSION['user_id'], $type, $qBefore, $qChange, $qAfter, $reason]);
            $pdo->commit();
            logActivity($pdo, "Stock adjustment ($type) for {$stock['name']}: $qBefore → $qAfter", 'stock');
            $success = "Stock adjusted successfully. {$stock['name']}: $qBefore → $qAfter units.";
        }
    }
}

// Recent adjustments
$adjustments = $pdo->prepare("
    SELECT sa.*, m.name as medicine_name, u.name as user_name
    FROM stock_adjustments sa
    JOIN stock s ON sa.stock_id=s.id
    JOIN medicines m ON s.medicine_id=m.id
    JOIN users u ON sa.user_id=u.id
    WHERE sa.branch_id=?
    ORDER BY sa.created_at DESC LIMIT 50
");
$adjustments->execute([$branchId]);
$adjustments = $adjustments->fetchAll();

// Stock list for dropdown
$stocks = $pdo->prepare("SELECT s.id, m.name, s.quantity, s.batch_number FROM stock s JOIN medicines m ON s.medicine_id=m.id WHERE s.branch_id=? ORDER BY m.name");
$stocks->execute([$branchId]);
$stocks = $stocks->fetchAll();

require_once '../includes/header.php';
?>
<?php if (!empty($success)): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;">
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-edit" style="color:var(--warning)"></i> New Adjustment</div></div>
    <div class="card-body">
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Medicine / Stock Item</label>
                <select name="stock_id" class="form-control" required>
                    <option value="">-- Select Medicine --</option>
                    <?php foreach ($stocks as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= $s['quantity'] ?> units<?= $s['batch_number'] ? ' · '.$s['batch_number'] : '' ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Adjustment Type</label>
                <select name="adjustment_type" class="form-control" required>
                    <option value="damage">Damage (reduce stock)</option>
                    <option value="loss">Loss / Theft (reduce stock)</option>
                    <option value="expiry_removal">Expiry Removal (reduce stock)</option>
                    <option value="correction">Correction (add stock)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Quantity</label>
                <input type="number" name="quantity_change" class="form-control" min="1" required placeholder="Enter quantity">
            </div>
            <div class="form-group">
                <label class="form-label">Reason / Notes</label>
                <textarea name="reason" class="form-control" rows="3" required placeholder="Explain the reason..."></textarea>
            </div>
            <button type="submit" class="btn btn-warning w-100"><i class="fas fa-save"></i> Save Adjustment</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-history" style="color:var(--info)"></i> Recent Adjustments</div></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Date</th><th>Medicine</th><th>Type</th><th>Before</th><th>Change</th><th>After</th><th>Reason</th><th>By</th></tr></thead>
            <tbody>
                <?php if (empty($adjustments)): ?>
                <tr><td colspan="8"><div class="empty-state" style="padding:20px;"><div class="empty-icon">📋</div><p>No adjustments yet</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($adjustments as $a): ?>
                <tr>
                    <td style="font-size:12px;"><?= date('d M Y H:i', strtotime($a['created_at'])) ?></td>
                    <td><strong><?= htmlspecialchars($a['medicine_name']) ?></strong></td>
                    <td><span class="badge <?= in_array($a['adjustment_type'],['damage','loss','expiry_removal']) ? 'badge-danger' : 'badge-success' ?>"><?= ucfirst(str_replace('_',' ',$a['adjustment_type'])) ?></span></td>
                    <td><?= $a['quantity_before'] ?></td>
                    <td style="color:<?= $a['quantity_change'] < 0 ? 'var(--danger)' : 'var(--secondary)' ?>;font-weight:700;"><?= $a['quantity_change'] > 0 ? '+' : '' ?><?= $a['quantity_change'] ?></td>
                    <td><strong><?= $a['quantity_after'] ?></strong></td>
                    <td style="font-size:12px;max-width:150px;"><?= htmlspecialchars($a['reason']) ?></td>
                    <td style="font-size:12px;"><?= htmlspecialchars($a['user_name']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
<?php require_once '../includes/footer.php'; ?>
