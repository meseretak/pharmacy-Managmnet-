<?php
require_once '../config/db.php';
requireLogin();
if (!isSuperAdmin()) { header('Location: /pharmacy/dashboard.php'); exit; }
define('PAGE_TITLE', 'Branch Management');
define('PAGE_SUBTITLE', 'Manage all pharmacy branches');

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pdo->prepare("INSERT INTO branches (name, location, phone, email) VALUES (?,?,?,?)")
            ->execute([trim($_POST['name']), trim($_POST['location']), trim($_POST['phone']), trim($_POST['email'])]);
        logActivity($pdo, 'Added branch: ' . $_POST['name'], 'branches');
    } elseif ($action === 'edit') {
        $pdo->prepare("UPDATE branches SET name=?, location=?, phone=?, email=?, status=? WHERE id=?")
            ->execute([trim($_POST['name']), trim($_POST['location']), trim($_POST['phone']), trim($_POST['email']), $_POST['status'], (int)$_POST['id']]);
        logActivity($pdo, 'Updated branch ID: ' . $_POST['id'], 'branches');
    }
    header('Location: /pharmacy/branches/index.php');
    exit;
}

$branches = $pdo->query("
    SELECT b.*,
           (SELECT COUNT(*) FROM users WHERE branch_id=b.id AND status='active') as staff_count,
           (SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE branch_id=b.id AND MONTH(created_at)=MONTH(CURDATE()) AND status='completed') as month_sales,
           (SELECT COUNT(*) FROM stock WHERE branch_id=b.id AND quantity <= low_stock_threshold) as low_stock
    FROM branches b
    ORDER BY b.name
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-building" style="color:var(--primary)"></i> All Branches</div>
        <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('show')">
            <i class="fas fa-plus"></i> Add Branch
        </button>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Branch Name</th>
                    <th>Location</th>
                    <th>Phone</th>
                    <th>Staff</th>
                    <th>This Month Sales</th>
                    <th>Low Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($branches as $i => $b): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($b['name']) ?></strong></td>
                    <td><i class="fas fa-map-marker-alt" style="color:var(--danger)"></i> <?= htmlspecialchars($b['location']) ?></td>
                    <td><?= htmlspecialchars($b['phone']) ?></td>
                    <td><span class="badge badge-info"><?= $b['staff_count'] ?> staff</span></td>
                    <td><strong style="color:var(--primary)"><?= formatCurrency($b['month_sales']) ?></strong></td>
                    <td>
                        <?php if ($b['low_stock'] > 0): ?>
                        <span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> <?= $b['low_stock'] ?></span>
                        <?php else: ?>
                        <span class="badge badge-success"><i class="fas fa-check"></i> OK</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $b['status']=='active' ? 'badge-success' : 'badge-secondary' ?>"><?= ucfirst($b['status']) ?></span></td>
                    <td>
                        <div style="display:flex;gap:5px;">
                            <a href="/pharmacy/branches/view.php?id=<?= $b['id'] ?>" class="btn btn-info btn-sm btn-icon" title="View"><i class="fas fa-eye"></i></a>
                            <button class="btn btn-warning btn-sm btn-icon" title="Edit" onclick='openEditModal(<?= json_encode($b) ?>)'><i class="fas fa-edit"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-plus-circle"></i> Add New Branch</div>
            <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('show')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Branch Name *</label><input type="text" name="name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Location *</label><input type="text" name="location" class="form-control" required></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('addModal').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Branch</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-edit"></i> Edit Branch</div>
            <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('show')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editId">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Branch Name *</label><input type="text" name="name" id="editName" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Location</label><input type="text" name="location" id="editLocation" class="form-control"></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" id="editPhone" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" id="editEmail" class="form-control"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="editStatus" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('editModal').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Branch</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(branch) {
    document.getElementById('editId').value = branch.id;
    document.getElementById('editName').value = branch.name;
    document.getElementById('editLocation').value = branch.location || '';
    document.getElementById('editPhone').value = branch.phone || '';
    document.getElementById('editEmail').value = branch.email || '';
    document.getElementById('editStatus').value = branch.status;
    document.getElementById('editModal').classList.add('show');
}
</script>

<?php require_once '../includes/footer.php'; ?>
