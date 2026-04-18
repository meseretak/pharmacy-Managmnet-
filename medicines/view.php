<?php
require_once '../config/db.php';
requireLogin();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT m.*, c.name as category_name, s.name as supplier_name, s.country as supplier_country FROM medicines m LEFT JOIN categories c ON m.category_id=c.id LEFT JOIN suppliers s ON m.supplier_id=s.id WHERE m.id=?");
$stmt->execute([$id]);
$med = $stmt->fetch();
if (!$med) { header('Location: /pharmacy/medicines/index.php'); exit; }

define('PAGE_TITLE', htmlspecialchars($med['name']));
define('PAGE_SUBTITLE', 'Medicine Details');

// Stock across branches
$branchFilter = isSuperAdmin() ? '' : 'AND s.branch_id = ' . (int)getUserBranchId();
$stocks = $pdo->prepare("SELECT s.*, b.name as branch_name FROM stock s JOIN branches b ON s.branch_id=b.id WHERE s.medicine_id=? $branchFilter ORDER BY b.name");
$stocks->execute([$id]);
$stocks = $stocks->fetchAll();

// Sales history — include discount, sold by, and sale_id
$branchHistFilter = isSuperAdmin() ? '' : 'AND sa.branch_id = ' . (int)getUserBranchId();
$salesHistory = $pdo->prepare("
    SELECT
        si.quantity,
        si.unit_price,
        si.subtotal,
        sa.id        AS sale_id,
        sa.invoice_number,
        sa.discount,
        sa.total_amount,
        sa.payment_method,
        sa.created_at,
        b.name       AS branch_name,
        u.name       AS sold_by
    FROM sale_items si
    JOIN sales sa   ON si.sale_id    = sa.id
    JOIN branches b ON sa.branch_id  = b.id
    JOIN users u    ON sa.user_id    = u.id
    WHERE si.medicine_id = ?
      AND sa.status = 'completed'
      $branchHistFilter
    ORDER BY sa.created_at DESC
    LIMIT 15
");
$salesHistory->execute([$id]);
$salesHistory = $salesHistory->fetchAll();

require_once '../includes/header.php';
?>

<?php if (isset($_GET['added'])): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> Medicine added successfully.</div>
<?php elseif (isset($_GET['updated'])): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> Medicine updated successfully.</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-pills" style="color:var(--primary)"></i> Medicine Info</div>
                <div style="display:flex;gap:8px;">
                    <?php if (isBranchManager()): ?>
                    <a href="/pharmacy/medicines/edit.php?id=<?= $id ?>" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i> Edit</a>
                    <?php endif; ?>
                    <a href="/pharmacy/medicines/index.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
                </div>
            </div>
            <div class="card-body">
                <table style="width:100%;">
                    <tr><td style="padding:8px 0;color:var(--text-muted);width:40%;">Brand Name</td><td><strong><?= htmlspecialchars($med['name']) ?></strong></td></tr>
                    <tr><td style="padding:8px 0;color:var(--text-muted);">Generic Name</td><td><?= htmlspecialchars($med['generic_name'] ?? '-') ?></td></tr>
                    <tr><td style="padding:8px 0;color:var(--text-muted);">Category</td><td><span class="badge badge-info"><?= htmlspecialchars($med['category_name'] ?? '-') ?></span></td></tr>
                    <tr><td style="padding:8px 0;color:var(--text-muted);">Unit</td><td><?= htmlspecialchars($med['unit']) ?></td></tr>
                    <tr><td style="padding:8px 0;color:var(--text-muted);">Supplier</td><td><?= htmlspecialchars($med['supplier_name'] ?? '-') ?> <?= $med['supplier_country'] ? '(' . htmlspecialchars($med['supplier_country']) . ')' : '' ?></td></tr>
                    <tr><td style="padding:8px 0;color:var(--text-muted);">Prescription</td><td><?= $med['requires_prescription'] ? '<span class="badge badge-warning">Required (Rx)</span>' : '<span class="badge badge-success">OTC</span>' ?></td></tr>
                    <tr><td style="padding:8px 0;color:var(--text-muted);">Status</td><td><span class="badge <?= $med['status']=='active' ? 'badge-success' : 'badge-secondary' ?>"><?= ucfirst($med['status']) ?></span></td></tr>
                    <?php if ($med['description']): ?>
                    <tr><td style="padding:8px 0;color:var(--text-muted);vertical-align:top;">Description</td><td style="font-size:13px;"><?= nl2br(htmlspecialchars($med['description'])) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-boxes" style="color:var(--info)"></i> Stock by Branch</div>
                <?php if (isBranchManager()): ?>
                <a href="/pharmacy/stock/add.php?medicine_id=<?= $id ?>" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Add Stock</a>
                <?php endif; ?>
            </div>
            <?php if (empty($stocks)): ?>
            <div class="card-body"><div class="empty-state"><div class="empty-icon">📦</div><p>No stock records</p></div></div>
            <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Branch</th><th>Qty</th><th>Buy Price</th><th>Sell Price</th><th>Expiry</th><th>Batch</th></tr></thead>
                    <tbody>
                        <?php foreach ($stocks as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['branch_name']) ?></td>
                            <td>
                                <span class="fw-bold <?= $s['quantity'] == 0 ? 'text-danger' : ($s['quantity'] <= $s['low_stock_threshold'] ? 'text-warning' : 'text-success') ?>">
                                    <?= $s['quantity'] ?>
                                </span>
                            </td>
                            <td><?= formatCurrency($s['buying_price']) ?></td>
                            <td><strong><?= formatCurrency($s['selling_price']) ?></strong></td>
                            <td>
                                <?php
                                $expDays = (strtotime($s['expiry_date']) - time()) / 86400;
                                $expClass = $expDays < 0 ? 'expiry-expired' : ($expDays <= 30 ? 'expiry-soon' : '');
                                ?>
                                <span class="<?= $expClass ?>"><?= date('d M Y', strtotime($s['expiry_date'])) ?></span>
                            </td>
                            <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($s['batch_number'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Sales History -->
<div class="card mt-2">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-history" style="color:var(--secondary)"></i> Recent Sales History</div>
    </div>
    <?php if (empty($salesHistory)): ?>
    <div class="card-body"><div class="empty-state"><div class="empty-icon">📋</div><p>No sales history</p></div></div>
    <?php else: ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Branch</th>
                    <th>Sold By</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Subtotal</th>
                    <th>Discount</th>
                    <th>Net Paid</th>
                    <th>Payment</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($salesHistory as $sh): ?>
                <tr>
                    <td>
                        <a href="/pharmacy/sales/view.php?id=<?= $sh['sale_id'] ?>" style="color:var(--primary);font-weight:600;">
                            <?= htmlspecialchars($sh['invoice_number']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($sh['branch_name']) ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:7px;">
                            <div style="width:26px;height:26px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;">
                                <?= strtoupper(substr($sh['sold_by'],0,1)) ?>
                            </div>
                            <span style="font-size:13px;"><?= htmlspecialchars($sh['sold_by']) ?></span>
                        </div>
                    </td>
                    <td><strong><?= $sh['quantity'] ?></strong></td>
                    <td><?= formatCurrency($sh['unit_price']) ?></td>
                    <td><?= formatCurrency($sh['subtotal']) ?></td>
                    <td>
                        <?php if ($sh['discount'] > 0): ?>
                        <span style="color:var(--danger);font-weight:600;">- <?= formatCurrency($sh['discount']) ?></span>
                        <?php else: ?>
                        <span style="color:var(--text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                    <td><strong style="color:var(--primary)"><?= formatCurrency($sh['total_amount']) ?></strong></td>
                    <td>
                        <?php
                        $pm = trim($sh['payment_method'] ?? '');
                        $pmColors = [
                            'cash'         => 'background:#e8f8f0;color:#1a6b3c;',
                            'card'         => 'background:#e8f4fd;color:#1a5276;',
                            'chapa'        => 'background:#e8f8f0;color:#1a6b3c;',
                            'telebirr'     => 'background:#fef9e7;color:#d68910;',
                            'mobile_money' => 'background:#f0e6ff;color:#6c3483;',
                        ];
                        $pmStyle = $pm ? ($pmColors[$pm] ?? 'background:#f0f0f0;color:#555;') : 'background:#f0f0f0;color:#aaa;';
                        $pmLabel = $pm ? ucfirst(str_replace('_', ' ', $pm)) : '—';
                        ?>
                        <span style="<?= $pmStyle ?> padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">
                            <?= htmlspecialchars($pmLabel) ?>
                        </span>
                    </td>
                    <td style="color:var(--text-muted);font-size:12px;white-space:nowrap;">
                        <?= date('d M Y H:i', strtotime($sh['created_at'])) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
