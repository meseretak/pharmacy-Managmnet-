<?php
require_once '../config/db.php';
requireLogin();
if (!isBranchManager()) { header('Location: /pharmacy/medicines/index.php'); exit; }
define('PAGE_TITLE', 'Edit Medicine');
define('PAGE_SUBTITLE', 'Update medicine information');

$id = (int)($_GET['id'] ?? 0);
$medicine = $pdo->prepare("SELECT * FROM medicines WHERE id=?");
$medicine->execute([$id]);
$medicine = $medicine->fetch();
if (!$medicine) { header('Location: /pharmacy/medicines/index.php'); exit; }

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$suppliers  = $pdo->query("SELECT * FROM suppliers WHERE status='active' ORDER BY name")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name'                  => trim($_POST['name'] ?? ''),
        'generic_name'          => trim($_POST['generic_name'] ?? ''),
        'category_id'           => (int)($_POST['category_id'] ?? 0),
        'supplier_id'           => (int)($_POST['supplier_id'] ?? 0),
        'unit'                  => trim($_POST['unit'] ?? 'Tablet'),
        'description'           => trim($_POST['description'] ?? ''),
        'requires_prescription' => isset($_POST['requires_prescription']) ? 1 : 0,
        'status'                => $_POST['status'] ?? 'active',
    ];

    if (!$data['name']) $errors[] = 'Medicine name is required.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE medicines SET name=?, generic_name=?, category_id=?, supplier_id=?, unit=?, description=?, requires_prescription=?, status=? WHERE id=?");
        $stmt->execute([$data['name'], $data['generic_name'], $data['category_id'], $data['supplier_id'] ?: null, $data['unit'], $data['description'], $data['requires_prescription'], $data['status'], $id]);
        logActivity($pdo, 'Updated medicine: ' . $data['name'], 'medicines');
        header('Location: /pharmacy/medicines/view.php?id=' . $id . '&updated=1');
        exit;
    }
    $medicine = array_merge($medicine, $data);
}

require_once '../includes/header.php';
?>

<div style="max-width:750px;">
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-edit" style="color:var(--warning)"></i> Edit Medicine</div>
            <a href="/pharmacy/medicines/index.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Medicine Name *</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($medicine['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Generic Name</label>
                        <input type="text" name="generic_name" class="form-control" value="<?= htmlspecialchars($medicine['generic_name'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-control">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $medicine['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-control">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['id'] ?>" <?= $medicine['supplier_id'] == $sup['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sup['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Unit</label>
                        <select name="unit" class="form-control">
                            <?php foreach (['Tablet','Capsule','Syrup (ml)','Injection (ml)','Cream (g)','Drops','Sachet','Inhaler','Patch','Suppository'] as $u): ?>
                            <option value="<?= $u ?>" <?= $medicine['unit'] == $u ? 'selected' : '' ?>><?= $u ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="active" <?= $medicine['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $medicine['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:10px;">
                    <input type="checkbox" name="requires_prescription" id="rx" value="1" <?= $medicine['requires_prescription'] ? 'checked' : '' ?> style="width:18px;height:18px;cursor:pointer;">
                    <label for="rx" class="form-label" style="margin:0;cursor:pointer;">Requires Prescription (Rx)</label>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($medicine['description'] ?? '') ?></textarea>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <a href="/pharmacy/medicines/index.php" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Medicine</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
