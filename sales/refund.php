<?php
require_once '../config/db.php';
requireLogin();
if (!isBranchManager()) { header('Location: /pharmacy/sales/index.php'); exit; }

$saleId = (int)($_GET['id'] ?? 0);
$sale = $pdo->prepare("SELECT sa.*, b.name as branch_name, u.name as cashier FROM sales sa JOIN branches b ON sa.branch_id=b.id JOIN users u ON sa.user_id=u.id WHERE sa.id=?");
$sale->execute([$saleId]); $sale = $sale->fetch();
if (!$sale || $sale['status'] !== 'completed') { header('Location: /pharmacy/sales/index.php'); exit; }
if (!isSuperAdmin() && $sale['branch_id'] != getUserBranchId()) { header('Location: /pharmacy/sales/index.php'); exit; }

// Check if already refunded
$existing = $pdo->prepare("SELECT id FROM sale_refunds WHERE sale_id=? AND status='approved'");
$existing->execute([$saleId]);
if ($existing->fetch()) { header('Location: /pharmacy/sales/view.php?id='.$saleId.'&msg=already_refunded'); exit; }

$items = $pdo->prepare("SELECT si.*, m.name as medicine_name, m.unit FROM sale_items si JOIN medicines m ON si.medicine_id=m.id WHERE si.sale_id=?");
$items->execute([$saleId]); $items = $items->fetchAll();

define('PAGE_TITLE', 'Process Refund');
define('PAGE_SUBTITLE', 'Invoice ' . $sale['invoice_number']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason      = trim($_POST['reason'] ?? '');
    $refundType  = $_POST['refund_type'] ?? 'full';
    $restock     = (int)($_POST['restock'] ?? 1);
    $selectedItems = $_POST['items'] ?? [];

    if (!$reason) { $error = 'Please provide a reason for the refund.'; }
    elseif (empty($selectedItems)) { $error = 'Please select at least one item to refund.'; }
    else {
        $pdo->beginTransaction();
        $refundAmount = 0;
        $refundId = null;

        // Calculate refund amount
        foreach ($items as $item) {
            if (isset($selectedItems[$item['id']])) {
                $qty = min((int)$selectedItems[$item['id']], $item['quantity']);
                $refundAmount += $qty * $item['unit_price'];
            }
        }

        $refundType = $refundAmount >= $sale['total_amount'] ? 'full' : 'partial';

        $pdo->prepare("INSERT INTO sale_refunds (sale_id,branch_id,user_id,refund_amount,reason,refund_type,restock) VALUES (?,?,?,?,?,?,?)")
            ->execute([$saleId, $sale['branch_id'], $_SESSION['user_id'], $refundAmount, $reason, $refundType, $restock]);
        $refundId = $pdo->lastInsertId();

        foreach ($items as $item) {
            if (isset($selectedItems[$item['id']])) {
                $qty = min((int)$selectedItems[$item['id']], $item['quantity']);
                $subtotal = $qty * $item['unit_price'];
                $pdo->prepare("INSERT INTO sale_refund_items (refund_id,sale_item_id,medicine_id,quantity,unit_price,subtotal) VALUES (?,?,?,?,?,?)")
                    ->execute([$refundId, $item['id'], $item['medicine_id'], $qty, $item['unit_price'], $subtotal]);
                // Restock
                if ($restock) {
                    $pdo->prepare("UPDATE stock SET quantity=quantity+? WHERE medicine_id=? AND branch_id=?")->execute([$qty, $item['medicine_id'], $sale['branch_id']]);
                }
            }
        }

        // Update sale status
        $newStatus = $refundType === 'full' ? 'refunded' : 'completed';
        $pdo->prepare("UPDATE sales SET status=? WHERE id=?")->execute([$newStatus, $saleId]);
        $pdo->commit();
        logActivity($pdo, "Refund processed for sale {$sale['invoice_number']}: " . formatCurrency($refundAmount), 'sales');
        header('Location: /pharmacy/sales/view.php?id='.$saleId.'&refunded=1');
        exit;
    }
}

require_once '../includes/header.php';
?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div style="max-width:700px;">
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-undo" style="color:var(--danger)"></i> Process Refund — <?= htmlspecialchars($sale['invoice_number']) ?></div>
        <a href="/pharmacy/sales/view.php?id=<?= $saleId ?>" class="btn btn-outline btn-sm">← Back</a>
    </div>
    <div class="card-body">
        <div style="background:var(--light);border-radius:8px;padding:15px;margin-bottom:20px;font-size:13px;">
            <strong>Customer:</strong> <?= htmlspecialchars($sale['customer_name']) ?> &nbsp;|&nbsp;
            <strong>Total:</strong> <?= formatCurrency($sale['total_amount']) ?> &nbsp;|&nbsp;
            <strong>Date:</strong> <?= date('d M Y', strtotime($sale['created_at'])) ?>
        </div>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Select Items to Refund</label>
                <table style="width:100%;border-collapse:collapse;">
                    <thead><tr style="background:var(--light);">
                        <th style="padding:8px;text-align:left;">Medicine</th>
                        <th style="padding:8px;text-align:center;">Sold Qty</th>
                        <th style="padding:8px;text-align:center;">Refund Qty</th>
                        <th style="padding:8px;text-align:right;">Unit Price</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr style="border-bottom:1px solid var(--border);">
                            <td style="padding:10px;"><?= htmlspecialchars($item['medicine_name']) ?></td>
                            <td style="padding:10px;text-align:center;"><?= $item['quantity'] ?></td>
                            <td style="padding:10px;text-align:center;">
                                <input type="number" name="items[<?= $item['id'] ?>]" min="0" max="<?= $item['quantity'] ?>" value="<?= $item['quantity'] ?>" class="form-control" style="width:70px;margin:0 auto;">
                            </td>
                            <td style="padding:10px;text-align:right;"><?= formatCurrency($item['unit_price']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-group">
                <label class="form-label">Reason for Refund</label>
                <textarea name="reason" class="form-control" rows="3" required placeholder="Explain why this refund is being processed..."></textarea>
            </div>

            <div class="form-group">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                    <input type="checkbox" name="restock" value="1" checked style="width:18px;height:18px;">
                    <span>Return items to stock inventory</span>
                </label>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Process this refund? This cannot be undone.')">
                    <i class="fas fa-undo"></i> Process Refund
                </button>
                <a href="/pharmacy/sales/view.php?id=<?= $saleId ?>" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
<?php require_once '../includes/footer.php'; ?>
