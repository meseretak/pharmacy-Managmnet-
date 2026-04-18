<?php
require_once '../config/db.php';
requireLogin();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT sa.*, b.name as branch_name, b.location as branch_location, b.phone as branch_phone, u.name as cashier, r.name as cashier_role FROM sales sa JOIN branches b ON sa.branch_id=b.id JOIN users u ON sa.user_id=u.id JOIN roles r ON u.role_id=r.id WHERE sa.id=?");
$stmt->execute([$id]);
$sale = $stmt->fetch();
if (!$sale) { header('Location: /pharmacy/sales/index.php'); exit; }

if (!isSuperAdmin() && $sale['branch_id'] != getUserBranchId()) {
    header('Location: /pharmacy/sales/index.php'); exit;
}

// ---- CONFIRM PAYMENT (POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    if ($sale['status'] === 'pending') {
        // Deduct stock now
        $items = $pdo->prepare("SELECT medicine_id, quantity FROM sale_items WHERE sale_id=?");
        $items->execute([$id]);
        foreach ($items->fetchAll() as $item) {
            $pdo->prepare("UPDATE stock SET quantity = quantity - ? WHERE medicine_id=? AND branch_id=? AND quantity >= ?")
                ->execute([$item['quantity'], $item['medicine_id'], $sale['branch_id'], $item['quantity']]);
        }
        // Mark completed
        $pdo->prepare("UPDATE sales SET status='completed', paid_amount=total_amount WHERE id=?")->execute([$id]);
        logActivity($pdo, 'Confirmed payment for sale ID: ' . $id, 'sales');
        header('Location: /pharmacy/sales/view.php?id=' . $id . '&confirmed=1');
        exit;
    }
}

define('PAGE_TITLE', 'Invoice ' . $sale['invoice_number']);
define('PAGE_SUBTITLE', 'Sale Details');

$items = $pdo->prepare("SELECT si.*, m.name as medicine_name, m.unit FROM sale_items si JOIN medicines m ON si.medicine_id=m.id WHERE si.sale_id=?");
$items->execute([$id]);
$items = $items->fetchAll();

require_once '../includes/header.php';
?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> Sale completed! Invoice: <strong><?= htmlspecialchars($sale['invoice_number']) ?></strong></div>
<?php endif; ?>
<?php if (isset($_GET['confirmed'])): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> Payment confirmed! Sale is now complete.</div>
<?php endif; ?>
<?php if (isset($_GET['payment']) && $_GET['payment'] === 'success'): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> Online payment confirmed successfully!</div>
<?php endif; ?>
<?php if (isset($_GET['payment_error'])): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Payment Error: <?= htmlspecialchars($_GET['payment_error']) ?></div>
<?php endif; ?>

<?php if ($sale['status'] === 'pending'): ?>
<div style="background:#fff3cd;border:2px solid #f39c12;border-radius:12px;padding:20px 25px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:15px;">
    <div>
        <div style="font-size:16px;font-weight:700;color:#d68910;"><i class="fas fa-clock"></i> Payment Pending</div>
        <div style="font-size:13px;color:#856404;margin-top:4px;">
            This sale is waiting for <strong><?= ucfirst(str_replace('_',' ',$sale['payment_method'])) ?></strong> payment of
            <strong><?= formatCurrency($sale['total_amount']) ?></strong>.
            Stock will be deducted after payment is confirmed.
        </div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php
        $paySettings = [];
        try {
            $ps = $pdo->query("SELECT gateway, is_enabled FROM payment_settings WHERE is_enabled=1");
            while ($r = $ps->fetch()) $paySettings[$r['gateway']] = 1;
        } catch(Exception $e) {}
        ?>
        <?php if ($sale['payment_method'] === 'chapa' && !empty($paySettings['chapa'])): ?>
        <form method="POST" action="/pharmacy/payments/chapa.php" style="display:inline;">
            <input type="hidden" name="sale_id" value="<?= $sale['id'] ?>">
            <input type="hidden" name="amount" value="<?= $sale['total_amount'] ?>">
            <input type="hidden" name="customer_name" value="<?= htmlspecialchars($sale['customer_name']) ?>">
            <input type="hidden" name="customer_phone" value="<?= htmlspecialchars($sale['customer_phone'] ?? '') ?>">
            <button type="submit" class="btn btn-success"><i class="fas fa-credit-card"></i> Pay via Chapa</button>
        </form>
        <?php elseif ($sale['payment_method'] === 'telebirr' && !empty($paySettings['telebirr'])): ?>
        <form method="POST" action="/pharmacy/payments/telebirr.php" style="display:inline;">
            <input type="hidden" name="sale_id" value="<?= $sale['id'] ?>">
            <input type="hidden" name="amount" value="<?= $sale['total_amount'] ?>">
            <input type="hidden" name="customer_name" value="<?= htmlspecialchars($sale['customer_name']) ?>">
            <input type="hidden" name="customer_phone" value="<?= htmlspecialchars($sale['customer_phone'] ?? '') ?>">
            <button type="submit" class="btn btn-warning"><i class="fas fa-mobile-alt"></i> Pay via Telebirr</button>
        </form>
        <?php else: ?>
        <!-- Card / Mobile Money: manual confirmation -->
        <?php
        $pmLabel = ucfirst(str_replace('_', ' ', $sale['payment_method']));
        $pmAmt   = formatCurrency($sale['total_amount']);
        ?>
        <form method="POST" onsubmit="return confirm('Confirm that <?= htmlspecialchars($pmLabel) ?> payment of <?= htmlspecialchars($pmAmt) ?> has been received?')">
            <input type="hidden" name="confirm_payment" value="1">
            <button type="submit" class="btn btn-success" style="font-size:15px;padding:12px 24px;">
                <i class="fas fa-check-circle"></i> Confirm <?= $pmLabel ?> Payment Received
            </button>
        </form>
        <?php endif; ?>
        <a href="/pharmacy/sales/new.php" class="btn btn-outline">Cancel & New Sale</a>
    </div>
</div>
<?php endif; ?>

<div style="display:flex;gap:15px;margin-bottom:20px;flex-wrap:wrap;">
    <a href="/pharmacy/sales/new.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Sale</a>
    <a href="/pharmacy/sales/index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Sales</a>
    <button onclick="window.print()" class="btn btn-info"><i class="fas fa-print"></i> Print Invoice</button>
</div>

<div style="max-width:750px;" id="printArea">
    <div class="card">
        <div class="card-body">
            <!-- Invoice Header -->
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:25px;">
                <div>
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">
                        <img src="/pharmacy/assets/img/logo.svg" alt="Logo" style="width:48px;height:48px;">
                        <div style="font-size:22px;font-weight:800;color:var(--primary);"><?= APP_NAME ?></div>
                    </div>
                    <div style="color:var(--text-muted);font-size:13px;"><?= htmlspecialchars($sale['branch_name']) ?></div>
                    <div style="color:var(--text-muted);font-size:12px;"><?= htmlspecialchars($sale['branch_location']) ?></div>
                    <div style="color:var(--text-muted);font-size:12px;"><?= htmlspecialchars($sale['branch_phone']) ?></div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:20px;font-weight:700;color:var(--dark);">INVOICE</div>
                    <div style="font-size:14px;color:var(--primary);font-weight:600;"><?= htmlspecialchars($sale['invoice_number']) ?></div>
                    <div style="font-size:12px;color:var(--text-muted);"><?= date('d M Y H:i', strtotime($sale['created_at'])) ?></div>
                    <div style="margin-top:8px;">
                        <span class="badge <?= $sale['status']=='completed' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($sale['status']) ?></span>
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Customer & Cashier -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                <div>
                    <div style="font-size:11px;text-transform:uppercase;color:var(--text-muted);font-weight:700;margin-bottom:5px;">Bill To</div>
                    <div style="font-weight:600;"><?= htmlspecialchars($sale['customer_name']) ?></div>
                    <?php if ($sale['customer_phone']): ?>
                    <div style="font-size:13px;color:var(--text-muted);"><?= htmlspecialchars($sale['customer_phone']) ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <div style="font-size:11px;text-transform:uppercase;color:var(--text-muted);font-weight:700;margin-bottom:5px;">Served By</div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;">
                            <?= strtoupper(substr($sale['cashier'],0,1)) ?>
                        </div>
                        <div>
                            <div style="font-weight:700;"><?= htmlspecialchars($sale['cashier']) ?></div>
                            <div style="font-size:12px;color:var(--text-muted);">Payment: <?= ucfirst(str_replace('_',' ', $sale['payment_method'] ?: 'cash')) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
                <thead>
                    <tr style="background:var(--light);">
                        <th style="padding:10px;text-align:left;font-size:12px;color:var(--text-muted);">#</th>
                        <th style="padding:10px;text-align:left;font-size:12px;color:var(--text-muted);">Medicine</th>
                        <th style="padding:10px;text-align:center;font-size:12px;color:var(--text-muted);">Qty</th>
                        <th style="padding:10px;text-align:right;font-size:12px;color:var(--text-muted);">Unit Price</th>
                        <th style="padding:10px;text-align:right;font-size:12px;color:var(--text-muted);">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $item): ?>
                    <tr style="border-bottom:1px solid #f0f0f0;">
                        <td style="padding:12px 10px;"><?= $i+1 ?></td>
                        <td style="padding:12px 10px;">
                            <strong><?= htmlspecialchars($item['medicine_name']) ?></strong>
                            <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($item['unit']) ?></div>
                        </td>
                        <td style="padding:12px 10px;text-align:center;"><?= $item['quantity'] ?></td>
                        <td style="padding:12px 10px;text-align:right;"><?= formatCurrency($item['unit_price']) ?></td>
                        <td style="padding:12px 10px;text-align:right;font-weight:600;"><?= formatCurrency($item['subtotal']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Totals -->
            <div style="display:flex;justify-content:flex-end;">
                <div style="min-width:250px;">
                    <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:14px;">
                        <span style="color:var(--text-muted);">Subtotal:</span>
                        <span><?= formatCurrency($sale['total_amount'] + $sale['discount']) ?></span>
                    </div>
                    <?php if ($sale['discount'] > 0): ?>
                    <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:14px;">
                        <span style="color:var(--text-muted);">Discount:</span>
                        <span style="color:var(--danger);">- <?= formatCurrency($sale['discount']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div style="display:flex;justify-content:space-between;padding:10px 0;font-size:18px;font-weight:700;border-top:2px solid var(--border);color:var(--primary);">
                        <span>Total:</span>
                        <span><?= formatCurrency($sale['total_amount']) ?></span>
                    </div>
                </div>
            </div>

            <div class="divider"></div>
            <div style="text-align:center;color:var(--text-muted);font-size:12px;">
                Thank you for your purchase! • <?= APP_NAME ?> • <?= date('Y') ?>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .topbar, .main-wrapper > header, .d-flex.gap-15, .alert { display: none !important; }
    .main-wrapper { margin-left: 0 !important; }
    .page-content { padding: 0 !important; }
    #printArea { max-width: 100% !important; }
}
</style>

<?php require_once '../includes/footer.php'; ?>
