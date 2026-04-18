<?php
require_once '../config/db.php';
requireLogin();
if (!isBranchManager()) { header('Location: /pharmacy/stock/index.php'); exit; }
define('PAGE_TITLE', 'Stock Transfers');
define('PAGE_SUBTITLE', 'Transfer stock between branches');

$branchId = getUserBranchId() ?? 1;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'request') {
        $fromBranch = isSuperAdmin() ? (int)$_POST['from_branch_id'] : $branchId;
        $toBranch   = (int)$_POST['to_branch_id'];
        $medicineId = (int)$_POST['medicine_id'];
        $qty        = (int)$_POST['quantity'];
        $notes      = trim($_POST['notes'] ?? '');

        if (!$fromBranch || !$toBranch || !$medicineId || $qty <= 0) {
            $error = 'Please fill all required fields.';
        } elseif ($fromBranch === $toBranch) {
            $error = 'Source and destination branches cannot be the same.';
        } else {
            // Check source stock
            $src = $pdo->prepare("SELECT s.*, m.name FROM stock s JOIN medicines m ON s.medicine_id=m.id WHERE s.medicine_id=? AND s.branch_id=?");
            $src->execute([$medicineId, $fromBranch]); $src = $src->fetch();
            if (!$src || $src['quantity'] < $qty) {
                $error = 'Insufficient stock in source branch. Available: ' . ($src['quantity'] ?? 0) . ' units.';
            } else {
                $pdo->prepare("INSERT INTO stock_transfers (from_branch_id,to_branch_id,medicine_id,quantity,requested_by,notes) VALUES (?,?,?,?,?,?)")
                    ->execute([$fromBranch, $toBranch, $medicineId, $qty, $_SESSION['user_id'], $notes]);
                $success = "Transfer request submitted: {$src['name']} × $qty from branch #{$fromBranch} → branch #{$toBranch}.";
            }
        }
    }

    if ($action === 'approve' && isSuperAdmin()) {
        $tid = (int)$_POST['transfer_id'];
        $t = $pdo->prepare("SELECT * FROM stock_transfers WHERE id=? AND status='pending'");
        $t->execute([$tid]); $t = $t->fetch();
        if ($t) {
            $src = $pdo->prepare("SELECT * FROM stock WHERE medicine_id=? AND branch_id=? AND quantity >= ?");
            $src->execute([$t['medicine_id'], $t['from_branch_id'], $t['quantity']]);
            if ($src->fetch()) {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE stock SET quantity=quantity-? WHERE medicine_id=? AND branch_id=?")->execute([$t['quantity'],$t['medicine_id'],$t['from_branch_id']]);
                $dest = $pdo->prepare("SELECT id FROM stock WHERE medicine_id=? AND branch_id=?");
                $dest->execute([$t['medicine_id'],$t['to_branch_id']]); $destRow = $dest->fetch();
                if ($destRow) {
                    $pdo->prepare("UPDATE stock SET quantity=quantity+? WHERE id=?")->execute([$t['quantity'],$destRow['id']]);
                } else {
                    $srcStock = $pdo->prepare("SELECT * FROM stock WHERE medicine_id=? AND branch_id=?");
                    $srcStock->execute([$t['medicine_id'],$t['from_branch_id']]); $srcStock = $srcStock->fetch();
                    $pdo->prepare("INSERT INTO stock (medicine_id,branch_id,quantity,buying_price,selling_price,low_stock_threshold) VALUES (?,?,?,?,?,?)")
                        ->execute([$t['medicine_id'],$t['to_branch_id'],$t['quantity'],$srcStock['buying_price']??0,$srcStock['selling_price']??0,$srcStock['low_stock_threshold']??20]);
                }
                $pdo->prepare("UPDATE stock_transfers SET status='completed',approved_by=? WHERE id=?")->execute([$_SESSION['user_id'],$tid]);
                $pdo->commit();
                logActivity($pdo, "Approved stock transfer ID: $tid", 'stock');
                $success = 'Transfer approved and completed.';
            } else { $error = 'Source branch no longer has sufficient stock.'; }
        }
    }

    if ($action === 'reject' && isSuperAdmin()) {
        $tid = (int)$_POST['transfer_id'];
        $pdo->prepare("UPDATE stock_transfers SET status='rejected',approved_by=? WHERE id=? AND status='pending'")->execute([$_SESSION['user_id'],$tid]);
        $success = 'Transfer rejected.';
    }
}

$branches = $pdo->query("SELECT * FROM branches WHERE status='active' ORDER BY name")->fetchAll();

// Medicines with stock — for super admin show all, for branch manager show own branch
if (isSuperAdmin()) {
    $medicines = $pdo->query("SELECT DISTINCT m.id, m.name FROM medicines m JOIN stock s ON s.medicine_id=m.id WHERE s.quantity>0 ORDER BY m.name")->fetchAll();
} else {
    $medicines = $pdo->prepare("SELECT m.id, m.name, s.quantity FROM medicines m JOIN stock s ON s.medicine_id=m.id WHERE s.branch_id=? AND s.quantity>0 ORDER BY m.name");
    $medicines->execute([$branchId]); $medicines = $medicines->fetchAll();
}

// Stock levels per medicine per branch (for JS)
$stockLevels = $pdo->query("SELECT s.medicine_id, s.branch_id, s.quantity, b.name as branch_name FROM stock s JOIN branches b ON s.branch_id=b.id WHERE s.quantity>0")->fetchAll();
$stockMap = [];
foreach ($stockLevels as $sl) {
    $stockMap[$sl['medicine_id']][$sl['branch_id']] = ['qty' => $sl['quantity'], 'branch' => $sl['branch_name']];
}

$transfers = $pdo->query("
    SELECT t.*, m.name as medicine_name, fb.name as from_branch, tb.name as to_branch,
           u.name as requested_by_name, a.name as approved_by_name
    FROM stock_transfers t
    JOIN medicines m ON t.medicine_id=m.id
    JOIN branches fb ON t.from_branch_id=fb.id
    JOIN branches tb ON t.to_branch_id=tb.id
    JOIN users u ON t.requested_by=u.id
    LEFT JOIN users a ON t.approved_by=a.id
    ORDER BY t.created_at DESC LIMIT 50
")->fetchAll();

require_once '../includes/header.php';
?>
<?php if (!empty($success)): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;">
<!-- Request Form -->
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-exchange-alt" style="color:var(--info)"></i> Request Transfer</div></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="request">

            <?php if (isSuperAdmin()): ?>
            <div class="form-group">
                <label class="form-label">Transfer FROM Branch *</label>
                <select name="from_branch_id" id="fromBranch" class="form-control" required onchange="updateMedicineStock()">
                    <option value="">-- Select Source Branch --</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <div class="form-group">
                <label class="form-label">Transfer FROM</label>
                <div style="padding:10px;background:var(--light);border-radius:8px;font-weight:600;">
                    <?php
                    $myBranch = $pdo->prepare("SELECT name FROM branches WHERE id=?");
                    $myBranch->execute([$branchId]); $myBranch = $myBranch->fetch();
                    echo htmlspecialchars($myBranch['name'] ?? 'Your Branch');
                    ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">Transfer TO Branch *</label>
                <select name="to_branch_id" id="toBranch" class="form-control" required>
                    <option value="">-- Select Destination Branch --</option>
                    <?php foreach ($branches as $b): if (!isSuperAdmin() && $b['id'] == $branchId) continue; ?>
                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Medicine *</label>
                <select name="medicine_id" id="medicineSelect" class="form-control" required onchange="showAvailableStock()">
                    <option value="">-- Select Medicine --</option>
                    <?php foreach ($medicines as $m): ?>
                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?><?= isset($m['quantity']) ? ' ('.$m['quantity'].' units)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="stockInfo" style="margin-top:6px;font-size:12px;color:var(--text-muted);"></div>
            </div>

            <div class="form-group">
                <label class="form-label">Quantity *</label>
                <input type="number" name="quantity" id="qtyInput" class="form-control" min="1" required>
                <div id="maxQtyHint" style="font-size:11px;color:var(--text-muted);margin-top:4px;"></div>
            </div>

            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Optional reason or notes..."></textarea>
            </div>

            <button type="submit" class="btn btn-info w-100"><i class="fas fa-paper-plane"></i> Submit Transfer Request</button>
        </form>
    </div>
</div>

<!-- Transfer History -->
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-list" style="color:var(--primary)"></i> Transfer History</div></div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Medicine</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Qty</th>
                    <th>Status</th>
                    <th>Requested By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transfers)): ?>
                <tr><td colspan="8"><div class="empty-state" style="padding:20px;"><div class="empty-icon">🔄</div><p>No transfers yet</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($transfers as $t): ?>
                <tr>
                    <td style="font-size:12px;"><?= date('d M Y H:i', strtotime($t['created_at'])) ?></td>
                    <td><strong><?= htmlspecialchars($t['medicine_name']) ?></strong></td>
                    <td>
                        <span style="background:#fef9e7;color:#d68910;padding:3px 8px;border-radius:5px;font-size:12px;font-weight:600;">
                            <i class="fas fa-arrow-right"></i> <?= htmlspecialchars($t['from_branch']) ?>
                        </span>
                    </td>
                    <td>
                        <span style="background:#e8f8f0;color:#1a6b3c;padding:3px 8px;border-radius:5px;font-size:12px;font-weight:600;">
                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($t['to_branch']) ?>
                        </span>
                    </td>
                    <td><strong><?= $t['quantity'] ?></strong></td>
                    <td>
                        <span class="badge <?= $t['status']=='completed'?'badge-success':($t['status']=='pending'?'badge-warning':($t['status']=='approved'?'badge-info':'badge-danger')) ?>">
                            <?= ucfirst($t['status']) ?>
                        </span>
                    </td>
                    <td style="font-size:12px;"><?= htmlspecialchars($t['requested_by_name']) ?></td>
                    <td>
                        <?php if ($t['status'] === 'pending' && isSuperAdmin()): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="transfer_id" value="<?= $t['id'] ?>">
                            <button class="btn btn-success btn-sm" title="Approve" onclick="return confirm('Approve and execute this transfer?')"><i class="fas fa-check"></i></button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="transfer_id" value="<?= $t['id'] ?>">
                            <button class="btn btn-danger btn-sm" title="Reject" onclick="return confirm('Reject this transfer?')"><i class="fas fa-times"></i></button>
                        </form>
                        <?php elseif ($t['status'] === 'completed'): ?>
                        <span style="font-size:11px;color:var(--text-muted);">By <?= htmlspecialchars($t['approved_by_name'] ?? '-') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<script>
const stockMap = <?= json_encode($stockMap) ?>;

function updateMedicineStock() {
    showAvailableStock();
}

function showAvailableStock() {
    const medId = document.getElementById('medicineSelect').value;
    const fromBranchEl = document.getElementById('fromBranch');
    const fromBranch = fromBranchEl ? fromBranchEl.value : '<?= $branchId ?>';
    const infoEl = document.getElementById('stockInfo');
    const hintEl = document.getElementById('maxQtyHint');
    const qtyInput = document.getElementById('qtyInput');

    if (medId && fromBranch && stockMap[medId] && stockMap[medId][fromBranch]) {
        const qty = stockMap[medId][fromBranch].qty;
        infoEl.innerHTML = `<i class="fas fa-box"></i> Available in source branch: <strong>${qty} units</strong>`;
        hintEl.textContent = `Max: ${qty} units`;
        qtyInput.max = qty;
    } else if (medId && fromBranch) {
        infoEl.innerHTML = `<span style="color:var(--danger)"><i class="fas fa-exclamation-triangle"></i> No stock in selected source branch</span>`;
        hintEl.textContent = '';
    } else {
        infoEl.textContent = '';
        hintEl.textContent = '';
    }
}
</script>
<?php require_once '../includes/footer.php'; ?>
