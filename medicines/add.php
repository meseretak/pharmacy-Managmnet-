<?php
require_once '../config/db.php';
requireLogin();
if (!isBranchManager()) { header('Location: /pharmacy/medicines/index.php'); exit; }
define('PAGE_TITLE', 'Add Medicine');
define('PAGE_SUBTITLE', 'Add new medicine to catalog');

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$suppliers  = $pdo->query("SELECT * FROM suppliers WHERE status='active' ORDER BY name")->fetchAll();

$errors = [];
$data = ['name'=>'','generic_name'=>'','category_id'=>'','supplier_id'=>'','unit'=>'Tablet','description'=>'','requires_prescription'=>0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name'                  => trim($_POST['name'] ?? ''),
        'generic_name'          => trim($_POST['generic_name'] ?? ''),
        'category_id'           => (int)($_POST['category_id'] ?? 0),
        'supplier_id'           => (int)($_POST['supplier_id'] ?? 0),
        'unit'                  => trim($_POST['unit'] ?? 'Tablet'),
        'description'           => trim($_POST['description'] ?? ''),
        'requires_prescription' => isset($_POST['requires_prescription']) ? 1 : 0,
    ];

    if (!$data['name']) $errors[] = 'Medicine name is required.';
    if (!$data['category_id']) $errors[] = 'Category is required.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO medicines (name, generic_name, category_id, supplier_id, unit, description, requires_prescription) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$data['name'], $data['generic_name'], $data['category_id'], $data['supplier_id'] ?: null, $data['unit'], $data['description'], $data['requires_prescription']]);
        $newId = $pdo->lastInsertId();
        logActivity($pdo, 'Added medicine: ' . $data['name'], 'medicines');
        header('Location: /pharmacy/medicines/view.php?id=' . $newId . '&added=1');
        exit;
    }
}

require_once '../includes/header.php';
?>

<div style="max-width:750px;">
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-plus-circle" style="color:var(--primary)"></i> New Medicine</div>
            <a href="/pharmacy/medicines/index.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Medicine Name *</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($data['name']) ?>" placeholder="e.g. Amoxicillin 500mg" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Generic Name</label>
                        <input type="text" name="generic_name" class="form-control" value="<?= htmlspecialchars($data['generic_name']) ?>" placeholder="e.g. Amoxicillin">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category_id" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $data['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-control">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['id'] ?>" <?= $data['supplier_id'] == $sup['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sup['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Unit</label>
                        <select name="unit" class="form-control">
                            <?php foreach (['Tablet','Capsule','Syrup (ml)','Injection (ml)','Cream (g)','Drops','Sachet','Inhaler','Patch','Suppository'] as $u): ?>
                            <option value="<?= $u ?>" <?= $data['unit'] == $u ? 'selected' : '' ?>><?= $u ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex;align-items:center;gap:10px;padding-top:28px;">
                        <input type="checkbox" name="requires_prescription" id="rx" value="1" <?= $data['requires_prescription'] ? 'checked' : '' ?> style="width:18px;height:18px;cursor:pointer;">
                        <label for="rx" class="form-label" style="margin:0;cursor:pointer;">Requires Prescription (Rx)</label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Medicine description, usage, side effects..."><?= htmlspecialchars($data['description']) ?></textarea>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <a href="/pharmacy/medicines/index.php" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Medicine</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
