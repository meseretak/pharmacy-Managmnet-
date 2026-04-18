<?php
require_once '../config/db.php';
requireLogin();
define('PAGE_TITLE', 'Payment Status');
define('PAGE_SUBTITLE', 'Transaction verification');

$txRef = $_GET['tx_ref'] ?? '';
$tx = null;
if ($txRef) {
    $stmt = $pdo->prepare("SELECT pt.*, s.invoice_number FROM payment_transactions pt LEFT JOIN sales s ON pt.sale_id=s.id WHERE pt.tx_ref=?");
    $stmt->execute([$txRef]);
    $tx = $stmt->fetch();
}

require_once '../includes/header.php';
?>

<div style="max-width:550px;margin:0 auto;">
    <div class="card">
        <div class="card-body" style="text-align:center;padding:40px;">
            <?php if (!$tx): ?>
            <div style="font-size:60px;margin-bottom:20px;">❓</div>
            <h3>Transaction Not Found</h3>
            <p style="color:var(--text-muted);">The transaction reference was not found.</p>
            <?php elseif ($tx['status'] === 'success'): ?>
            <div style="font-size:60px;margin-bottom:20px;">✅</div>
            <h3 style="color:var(--secondary);">Payment Successful!</h3>
            <p style="color:var(--text-muted);">Your payment has been confirmed.</p>
            <div style="background:var(--light);border-radius:10px;padding:20px;margin:20px 0;text-align:left;">
                <div style="display:flex;justify-content:space-between;padding:6px 0;"><span style="color:var(--text-muted);">Transaction Ref</span><strong><?= htmlspecialchars($tx['tx_ref']) ?></strong></div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;"><span style="color:var(--text-muted);">Amount</span><strong style="color:var(--primary)"><?= formatCurrency($tx['amount']) ?></strong></div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;"><span style="color:var(--text-muted);">Gateway</span><strong><?= ucfirst($tx['payment_gateway']) ?></strong></div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;"><span style="color:var(--text-muted);">Invoice</span><strong><?= htmlspecialchars($tx['invoice_number'] ?? 'N/A') ?></strong></div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;"><span style="color:var(--text-muted);">Verified At</span><strong><?= $tx['verified_at'] ? date('d M Y H:i', strtotime($tx['verified_at'])) : 'N/A' ?></strong></div>
            </div>
            <?php if ($tx['sale_id']): ?>
            <a href="/pharmacy/sales/view.php?id=<?= $tx['sale_id'] ?>" class="btn btn-primary"><i class="fas fa-receipt"></i> View Invoice</a>
            <?php endif; ?>
            <?php elseif ($tx['status'] === 'failed'): ?>
            <div style="font-size:60px;margin-bottom:20px;">❌</div>
            <h3 style="color:var(--danger);">Payment Failed</h3>
            <p style="color:var(--text-muted);">The payment could not be processed.</p>
            <?php if ($tx['sale_id']): ?>
            <a href="/pharmacy/sales/view.php?id=<?= $tx['sale_id'] ?>" class="btn btn-outline" style="margin-right:10px;">Back to Sale</a>
            <?php endif; ?>
            <a href="/pharmacy/sales/new.php" class="btn btn-primary">Try Again</a>
            <?php else: ?>
            <div style="font-size:60px;margin-bottom:20px;">⏳</div>
            <h3 style="color:var(--warning);">Payment Pending</h3>
            <p style="color:var(--text-muted);">Waiting for payment confirmation...</p>
            <div style="margin:20px 0;">
                <div style="font-size:13px;color:var(--text-muted);">Ref: <strong><?= htmlspecialchars($tx['tx_ref']) ?></strong></div>
                <div style="font-size:13px;color:var(--text-muted);">Amount: <strong><?= formatCurrency($tx['amount']) ?></strong></div>
            </div>
            <button onclick="location.reload()" class="btn btn-warning"><i class="fas fa-sync"></i> Check Status</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
