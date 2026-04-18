<?php
require_once '../config/db.php';
requireLogin();
if (!isBranchManager()) { header('Location: /pharmacy/stock/index.php'); exit; }
define('PAGE_TITLE', 'Add Stock');
define('PAGE_SUBTITLE', 'Add or update stock for a medicine');

$medicines = $pdo->query("SELECT * FROM medicines WHERE status='active' ORDER BY name")->fetchAll();
$branches  = isSuperAdmin()
    ? $pdo->query("SELECT * FROM branches WHERE status='active' ORDER BY name")->fetchAll()
    : $pdo->prepare("SELECT * FROM branches WHERE id=? AND status='active'");

if (!isSuperAdmin()) {
    $branches->execute([getUserBranchId()]);
    $branches = $branches->fetchAll();
}

$errors = [];
$data = ['medicine_id'=>$_GET['medicine_id']??'','branch_id'=>getUserBranchId()??'','quantity'=>'','low_stock_threshold'=>20,'buying_price'=>'','selling_price'=>'','expiry_date'=>'','batch_number'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'medicine_id'         => (int)$_POST['medicine_id'],
        'branch_id'           => isSuperAdmin() ? (int)$_POST['branch_id'] : (int)getUserBranchId(),
        'quantity'            => (int)$_POST['quantity'],
        'low_stock_threshold' => (int)($_POST['low_stock_threshold'] ?? 20),
        'buying_price'        => (float)$_POST['buying_price'],
        'selling_price'       => (float)$_POST['selling_price'],
        'expiry_date'         => $_POST['expiry_date'] ?? '',
        'batch_number'        => trim($_POST['batch_number'] ?? ''),
    ];

    if (!$data['medicine_id']) $errors[] = 'Medicine is required.';
    if (!$data['branch_id'])   $errors[] = 'Branch is required.';
    if ($data['quantity'] < 0) $errors[] = 'Quantity cannot be negative.';
    if ($data['selling_price'] <= 0) $errors[] = 'Selling price must be greater than 0.';

    if (empty($errors)) {
        // Check if stock record exists
        $existing = $pdo->prepare("SELECT id FROM stock WHERE medicine_id=? AND branch_id=? AND batch_number=?");
        $existing->execute([$data['medicine_id'], $data['branch_id'], $data['batch_number']]);
        $existing = $existing->fetch();

        if ($existing) {
            $pdo->prepare("UPDATE stock SET quantity=quantity+?, buying_price=?, selling_price=?, low_stock_threshold=?, expiry_date=?, updated_at=NOW() WHERE id=?")
                ->execute([$data['quantity'], $data['buying_price'], $data['selling_price'], $data['low_stock_threshold'], $data['expiry_date'] ?: null, $existing['id']]);
        } else {
            $pdo->prepare("INSERT INTO stock (medicine_id, branch_id, quantity, low_stock_threshold, buying_price, selling_price, expiry_date, batch_number) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$data['medicine_id'], $data['branch_id'], $data['quantity'], $data['low_stock_threshold'], $data['buying_price'], $data['selling_price'], $data['expiry_date'] ?: null, $data['batch_number']]);
        }
        logActivity($pdo, 'Added stock for medicine ID: ' . $data['medicine_id'], 'stock');
        header('Location: /pharmacy/stock/index.php?added=1');
        exit;
    }
}

require_once '../includes/header.php';
?>

<div style="max-width:700px;">
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-plus-circle" style="color:var(--primary)"></i> Add Stock</div>
            <a href="/pharmacy/stock/index.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Medicine *</label>
                        <select name="medicine_id" class="form-control" required>
                            <option value="">Select Medicine</option>
                            <?php foreach ($medicines as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $data['medicine_id'] == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (isSuperAdmin()): ?>
                    <div class="form-group">
                        <label class="form-label">Branch *</label>
                        <select name="branch_id" class="form-control" required>
                            <option value="">Select Branch</option>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $data['branch_id'] == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Quantity to Add *</label>
                        <input type="number" name="quantity" class="form-control" value="<?= $data['quantity'] ?>" min="0" required>
                        <div class="tooltip-text">If batch already exists, this will be added to current stock.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Low Stock Threshold</label>
                        <input type="number" name="low_stock_threshold" class="form-control" value="<?= $data['low_stock_threshold'] ?>" min="1">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Buying Price (<?= CURRENCY ?>) *</label>
                        <input type="number" name="buying_price" class="form-control" value="<?= $data['buying_price'] ?>" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Selling Price (<?= CURRENCY ?>) *</label>
                        <input type="number" name="selling_price" class="form-control" value="<?= $data['selling_price'] ?>" step="0.01" min="0.01" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control" value="<?= $data['expiry_date'] ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Batch Number</label>
                        <input type="text" name="batch_number" class="form-control" value="<?= htmlspecialchars($data['batch_number']) ?>" placeholder="e.g. BATCH001">
                    </div>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <a href="/pharmacy/stock/index.php" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
