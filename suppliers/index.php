<?php
require_once '../config/db.php';
requireLogin();
define('PAGE_TITLE', 'Suppliers');
define('PAGE_SUBTITLE', 'Manage medicine suppliers');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isBranchManager()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pdo->prepare("INSERT INTO suppliers (name, contact_person, phone, email, address, country) VALUES (?,?,?,?,?,?)")
            ->execute([trim($_POST['name']), trim($_POST['contact_person']), trim($_POST['phone']), trim($_POST['email']), trim($_POST['address']), trim($_POST['country'])]);
    } elseif ($action === 'edit') {
        $pdo->prepare("UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=?, country=?, status=? WHERE id=?")
            ->execute([trim($_POST['name']), trim($_POST['contact_person']), trim($_POST['phone']), trim($_POST['email']), trim($_POST['address']), trim($_POST['country']), $_POST['status'], (int)$_POST['id']]);
    }
    header('Location: /pharmacy/suppliers/index.php');
    exit;
}

$suppliers = $pdo->query("SELECT s.*, (SELECT COUNT(*) FROM medicines WHERE supplier_id=s.id) as medicine_count FROM suppliers s ORDER BY s.name")->fetchAll();

require_once '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-industry" style="color:var(--primary)"></i> Suppliers (<?= count($suppliers) ?>)</div>
        <?php if (isBranchManager()): ?>
        <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('show')"><i class="fas fa-plus"></i> Add Supplier</button>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr><th>#</th><th>Supplier Name</th><th>Contact Person</th><th>Phone</th><th>Email</th><th>Country</th><th>Medicines</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($suppliers as $i => $s): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                    <td><?= htmlspecialchars($s['contact_person'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($s['phone'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($s['email'] ?? '-') ?></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($s['country']) ?></span></td>
                    <td><?= $s['medicine_count'] ?></td>
                    <td><span class="badge <?= $s['status']=='active'?'badge-success':'badge-secondary' ?>"><?= ucfirst($s['status']) ?></span></td>
                    <td>
                        <?php if (isBranchManager()): ?>
                        <button class="btn btn-warning btn-sm btn-icon" onclick='openEdit(<?= json_encode($s) ?>)'><i class="fas fa-edit"></i></button>
                        <?php endif; ?>
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
        <div class="modal-header"><div class="modal-title">Add Supplier</div><button class="modal-close" onclick="document.getElementById('addModal').classList.remove('show')">×</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Supplier Name *</label><input type="text" name="name" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Contact Person</label><input type="text" name="contact_person" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Country</label><input type="text" name="country" class="form-control" value="Ethiopia"></div>
                    <div class="form-group"><label class="form-label">Address</label><input type="text" name="address" class="form-control"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('addModal').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">Edit Supplier</div><button class="modal-close" onclick="document.getElementById('editModal').classList.remove('show')">×</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="eId">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Supplier Name *</label><input type="text" name="name" id="eName" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Contact Person</label><input type="text" name="contact_person" id="eContact" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" id="ePhone" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" id="eEmail" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Country</label><input type="text" name="country" id="eCountry" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Status</label><select name="status" id="eStatus" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                </div>
                <div class="form-group"><label class="form-label">Address</label><input type="text" name="address" id="eAddress" class="form-control"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('editModal').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(s) {
    document.getElementById('eId').value = s.id;
    document.getElementById('eName').value = s.name;
    document.getElementById('eContact').value = s.contact_person || '';
    document.getElementById('ePhone').value = s.phone || '';
    document.getElementById('eEmail').value = s.email || '';
    document.getElementById('eCountry').value = s.country || '';
    document.getElementById('eAddress').value = s.address || '';
    document.getElementById('eStatus').value = s.status;
    document.getElementById('editModal').classList.add('show');
}
</script>

<?php require_once '../includes/footer.php'; ?>
