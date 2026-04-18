<?php
require_once '../config/db.php';
requireLogin();
define('PAGE_TITLE', 'Medicines');
define('PAGE_SUBTITLE', 'Manage medicine catalog');

$search = trim($_GET['search'] ?? '');
$category = (int)($_GET['category'] ?? 0);
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = '(m.name LIKE ? OR m.generic_name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($category) {
    $where[] = 'm.category_id = ?';
    $params[] = $category;
}
if ($status) {
    $where[] = 'm.status = ?';
    $params[] = $status;
}

$whereStr = implode(' AND ', $where);

$total = $pdo->prepare("SELECT COUNT(*) FROM medicines m WHERE $whereStr");
$total->execute($params);
$totalCount = $total->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

$stmt = $pdo->prepare("
    SELECT m.*, c.name as category_name, s.name as supplier_name
    FROM medicines m
    LEFT JOIN categories c ON m.category_id = c.id
    LEFT JOIN suppliers s ON m.supplier_id = s.id
    WHERE $whereStr
    ORDER BY m.name ASC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$medicines = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && isBranchManager()) {
    $pdo->prepare("UPDATE medicines SET status='inactive' WHERE id=?")->execute([(int)$_POST['delete_id']]);
    logActivity($pdo, 'Deactivated medicine ID: ' . $_POST['delete_id'], 'medicines');
    header('Location: /pharmacy/medicines/index.php');
    exit;
}

require_once '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-pills" style="color:var(--primary)"></i> Medicine Catalog</div>
        <?php if (isBranchManager()): ?>
        <a href="/pharmacy/medicines/add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Medicine</a>
        <?php endif; ?>
    </div>
    <div class="card-body" style="padding-bottom:0;">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-bottom:15px;">
            <div class="search-bar">
                <i class="fas fa-search search-icon"></i>
                <input type="text" name="search" placeholder="Search medicines..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <select name="category" class="form-control" style="width:auto;">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-control" style="width:auto;">
                <option value="">All Status</option>
                <option value="active" <?= $status == 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status == 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="/pharmacy/medicines/index.php" class="btn btn-outline">Reset</a>
        </form>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Medicine Name</th>
                    <th>Generic Name</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th>Supplier</th>
                    <th>Prescription</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($medicines)): ?>
                <tr><td colspan="9"><div class="empty-state"><div class="empty-icon">💊</div><p>No medicines found</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($medicines as $i => $med): ?>
                <tr>
                    <td><?= $offset + $i + 1 ?></td>
                    <td>
                        <strong><?= htmlspecialchars($med['name']) ?></strong>
                    </td>
                    <td style="color:var(--text-muted)"><?= htmlspecialchars($med['generic_name'] ?? '-') ?></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($med['category_name'] ?? '-') ?></span></td>
                    <td><?= htmlspecialchars($med['unit']) ?></td>
                    <td><?= htmlspecialchars($med['supplier_name'] ?? '-') ?></td>
                    <td>
                        <?php if ($med['requires_prescription']): ?>
                        <span class="badge badge-warning"><i class="fas fa-prescription"></i> Required</span>
                        <?php else: ?>
                        <span class="badge badge-success">OTC</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $med['status'] == 'active' ? 'badge-success' : 'badge-secondary' ?>">
                            <?= ucfirst($med['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:5px;">
                            <a href="/pharmacy/medicines/view.php?id=<?= $med['id'] ?>" class="btn btn-info btn-sm btn-icon" title="View"><i class="fas fa-eye"></i></a>
                            <?php if (isBranchManager()): ?>
                            <a href="/pharmacy/medicines/edit.php?id=<?= $med['id'] ?>" class="btn btn-warning btn-sm btn-icon" title="Edit"><i class="fas fa-edit"></i></a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Deactivate this medicine?')">
                                <input type="hidden" name="delete_id" value="<?= $med['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Deactivate"><i class="fas fa-ban"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>&status=<?= $status ?>">‹ Prev</a><?php endif; ?>
        <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
        <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>&status=<?= $status ?>" class="<?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?><a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>&status=<?= $status ?>">Next ›</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
