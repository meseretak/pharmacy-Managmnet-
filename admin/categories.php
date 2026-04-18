<?php
require_once '../config/db.php';
requireLogin();
if (!isSuperAdmin()) { header('Location: /pharmacy/dashboard.php'); exit; }
define('PAGE_TITLE', 'Categories');
define('PAGE_SUBTITLE', 'Manage medicine categories');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pdo->prepare("INSERT INTO categories (name, description) VALUES (?,?)")
            ->execute([trim($_POST['name']), trim($_POST['description'])]);
    } elseif ($action === 'edit') {
        $pdo->prepare("UPDATE categories SET name=?, description=? WHERE id=?")
            ->execute([trim($_POST['name']), trim($_POST['description']), (int)$_POST['id']]);
    }
    header('Location: /pharmacy/admin/categories.php');
    exit;
}

$categories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM medicines WHERE category_id=c.id) as medicine_count FROM categories c ORDER BY c.name")->fetchAll();

require_once '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-tags" style="color:var(--primary)"></i> Medicine Categories</div>
        <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('show')"><i class="fas fa-plus"></i> Add Category</button>
    </div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>#</th><th>Category Name</th><th>Description</th><th>Medicines</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($categories as $i => $cat): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                    <td style="color:var(--text-muted);"><?= htmlspecialchars($cat['description'] ?? '-') ?></td>
                    <td><span class="badge badge-info"><?= $cat['medicine_count'] ?> medicines</span></td>
                    <td><button class="btn btn-warning btn-sm btn-icon" onclick='openEdit(<?= json_encode($cat) ?>)'><i class="fas fa-edit"></i></button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header"><div class="modal-title">Add Category</div><button class="modal-close" onclick="document.getElementById('addModal').classList.remove('show')">×</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Category Name *</label><input type="text" name="name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('addModal').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header"><div class="modal-title">Edit Category</div><button class="modal-close" onclick="document.getElementById('editModal').classList.remove('show')">×</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="eId">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Category Name *</label><input type="text" name="name" id="eName" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Description</label><textarea name="description" id="eDesc" class="form-control" rows="3"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('editModal').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(cat) {
    document.getElementById('eId').value = cat.id;
    document.getElementById('eName').value = cat.name;
    document.getElementById('eDesc').value = cat.description || '';
    document.getElementById('editModal').classList.add('show');
}
</script>

<?php require_once '../includes/footer.php'; ?>
