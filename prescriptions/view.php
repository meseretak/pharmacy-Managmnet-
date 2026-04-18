<?php
require_once '../config/db.php';
requireLogin();
$id = (int)($_GET['id'] ?? 0);
$p = $pdo->prepare("SELECT p.*, b.name as branch_name, u.name as created_by FROM prescriptions p JOIN branches b ON p.branch_id=b.id JOIN users u ON p.user_id=u.id WHERE p.id=?");
$p->execute([$id]); $p = $p->fetch();
if (!$p) { header('Location: /pharmacy/prescriptions/index.php'); exit; }
if (!isSuperAdmin() && $p['branch_id'] != getUserBranchId()) { header('Location: /pharmacy/prescriptions/index.php'); exit; }

define('PAGE_TITLE', 'Prescription #' . $id);
define('PAGE_SUBTITLE', $p['customer_name']);

$items = $pdo->prepare("SELECT pi.*, m.name as medicine_name, m.unit FROM prescription_items pi JOIN medicines m ON pi.medicine_id=m.id WHERE pi.prescription_id=?");
$items->execute([$id]); $items = $items->fetchAll();

// Handle cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    $pdo->prepare("UPDATE prescriptions SET status='cancelled' WHERE id=?")->execute([$id]);
    header('Location: /pharmacy/prescriptions/index.php');
    exit;
}

require_once '../includes/header.php';
?>
<?php if (isset($_GET['created'])): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> Prescription saved successfully.</div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <a href="/pharmacy/prescriptions/index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
    <?php if ($p['status'] === 'pending'): ?>
    <a href="/pharmacy/sales/new.php?prescription_id=<?= $id ?>" class="btn btn-success"><i class="fas fa-cash-register"></i> Dispense (Go to POS)</a>
    <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this prescription?')">
        <button name="cancel" value="1" class="btn btn-danger"><i class="fas fa-times"></i> Cancel</button>
    </form>
    <?php endif; ?>
    <button onclick="window.print()" class="btn btn-info"><i class="fas fa-print"></i> Print</button>
</div>

<div style="max-width:700px;" id="printArea">
<div class="card">
    <div class="card-body">
        <div style="display:flex;justify-content:space-between;margin-bottom:20px;">
            <div>
                <div style="font-size:20px;font-weight:800;color:var(--primary);">PRESCRIPTION</div>
                <div style="font-size:13px;color:var(--text-muted);"><?= APP_NAME ?> · <?= htmlspecialchars($p['branch_name']) ?></div>
            </div>
            <span class="badge <?= $p['status']=='dispensed'?'badge-success':($p['status']=='pending'?'badge-warning':'badge-danger') ?>" style="font-size:14px;padding:8px 16px;"><?= ucfirst($p['status']) ?></span>
        </div>
        <div class="divider"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
            <div>
                <div style="font-size:11px;text-transform:uppercase;color:var(--text-muted);font-weight:700;margin-bottom:8px;">Patient</div>
                <div style="font-weight:700;font-size:16px;"><?= htmlspecialchars($p['customer_name']) ?></div>
                <?php if ($p['customer_phone']): ?><div style="color:var(--text-muted);"><?= htmlspecialchars($p['customer_phone']) ?></div><?php endif; ?>
            </div>
            <div>
                <div style="font-size:11px;text-transform:uppercase;color:var(--text-muted);font-weight:700;margin-bottom:8px;">Prescribing Doctor</div>
                <div style="font-weight:700;"><?= htmlspecialchars($p['doctor_name'] ?? 'N/A') ?></div>
                <?php if ($p['doctor_license']): ?><div style="font-size:12px;color:var(--text-muted);">License: <?= htmlspecialchars($p['doctor_license']) ?></div><?php endif; ?>
                <?php if ($p['hospital_clinic']): ?><div style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($p['hospital_clinic']) ?></div><?php endif; ?>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
            <div><span style="color:var(--text-muted);font-size:12px;">Issue Date:</span> <strong><?= $p['issue_date'] ? date('d M Y', strtotime($p['issue_date'])) : '-' ?></strong></div>
            <div><span style="color:var(--text-muted);font-size:12px;">Expiry Date:</span> <strong><?= $p['expiry_date'] ? date('d M Y', strtotime($p['expiry_date'])) : '-' ?></strong></div>
        </div>
        <div class="divider"></div>
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr style="background:var(--light);">
                <th style="padding:10px;text-align:left;">#</th>
                <th style="padding:10px;text-align:left;">Medicine</th>
                <th style="padding:10px;text-align:center;">Qty</th>
                <th style="padding:10px;text-align:left;">Dosage</th>
                <th style="padding:10px;text-align:center;">Dispensed</th>
            </tr></thead>
            <tbody>
                <?php foreach ($items as $i => $item): ?>
                <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:10px;"><?= $i+1 ?></td>
                    <td style="padding:10px;"><strong><?= htmlspecialchars($item['medicine_name']) ?></strong> <span style="color:var(--text-muted);font-size:12px;">(<?= $item['unit'] ?>)</span></td>
                    <td style="padding:10px;text-align:center;"><?= $item['quantity'] ?></td>
                    <td style="padding:10px;font-size:13px;"><?= htmlspecialchars($item['dosage'] ?? '-') ?></td>
                    <td style="padding:10px;text-align:center;"><?= $item['dispensed'] ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-warning">No</span>' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($p['notes']): ?>
        <div style="margin-top:15px;padding:12px;background:var(--light);border-radius:8px;font-size:13px;"><strong>Notes:</strong> <?= htmlspecialchars($p['notes']) ?></div>
        <?php endif; ?>
    </div>
</div>
</div>
<style>@media print { .sidebar,.topbar,.main-wrapper>header,.d-flex { display:none !important; } .main-wrapper { margin-left:0 !important; } }</style>
<?php require_once '../includes/footer.php'; ?>
