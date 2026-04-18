<?php
require_once '../config/db.php';
requireLogin();
if (!isBranchManager()) { header('Location: /pharmacy/stock/index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT s.*, m.name as medicine_name, b.name as branch_name FROM stock s JOIN medicines m ON s.medicine_id=m.id JOIN branches b ON s.branch_id=b.id WHERE s.id=?");
$stmt->execute([$id]);
$stock = $stmt->fetch();
if (!$stock) { header('Location: /pharmacy/stock/index.php'); exit; }

// Branch access check
if (!isSuperAdmin() && $stock['branch_id'] != getUserBranchId()) {
    header('Location: /pharmacy/stock/index.php'); exit;
}

define('PAGE_TITLE', 'Edit Stock');
define('PAGE_SUBTITLE', htmlspecialchars($stock['medicine_name']));

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'quantity'            => (int)$_POST['quantity'],
        'low_stock_threshold' => (int)($_POST['low_stock_threshold'] ?? 20),
        'buying_price'        => (float)$_POST['buying_price'],
        'selling_price'       => (float)$_POST['selling_price'],
        'expiry_date'         => $_POST['expiry_date'] ?? '',
        'batch_number'        => trim($_POST['batch_number'] ?? ''),
    ];

    if ($data['quantity'] < 0) $errors[] = 'Quantity cannot be negative.';
    if ($data['selling_price'] <= 0) $errors[] = 'Selling price must be greater than 0.';

    if (empty($errors)) {
        $pdo->prepare("UPDATE stock SET quantity=?, low_stock_threshold=?, buying_price=?, selling_price=?, expiry_date=?, batch_number=? WHERE id=?")
            ->execute([$data['quantity'], $data['low_stock_threshold'], $data['buying_price'], $data['selling_price'], $data['expiry_date'] ?: null, $data['batch_number'], $id]);
        logActivity($pdo, 'Updated stock ID: ' . $id, 'stock');
        header('Location: /pharmacy/stock/index.php?updated=1');
        exit;
    }
    $stock = array_merge($stock, $data);
}

require_once '../includes/header.php';
?>

<div style="max-width:600px;">
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-edit" style="color:var(--warning)"></i> Edit Stock</div>
            <a href="/pharmacy/stock/index.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <div class="alert alert-info" style="margin-bottom:20px;">
                <i class="fas fa-info-circle"></i>
                <strong><?= htmlspecialchars($stock['medicine_name']) ?></strong> — <?= htmlspecialchars($stock['branch_name']) ?>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Current Quantity *</label>
                        <input type="number" name="quantity" class="form-control" value="<?= $stock['quantity'] ?>" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Low Stock Threshold</label>
                        <input type="number" name="low_stock_threshold" class="form-control" value="<?= $stock['low_stock_threshold'] ?>" min="1">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Buying Price (<?= CURRENCY ?>)</label>
                        <input type="number" name="buying_price" class="form-control" value="<?= $stock['buying_price'] ?>" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Selling Price (<?= CURRENCY ?>) *</label>
                        <input type="number" name="selling_price" class="form-control" value="<?= $stock['selling_price'] ?>" step="0.01" min="0.01" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control" value="<?= $stock['expiry_date'] ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Batch Number</label>
                        <input type="text" name="batch_number" class="form-control" value="<?= htmlspecialchars($stock['batch_number'] ?? '') ?>">
                    </div>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <a href="/pharmacy/stock/index.php" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
