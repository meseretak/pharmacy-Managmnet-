<?php
require_once '../config/db.php';
requireLogin();
define('PAGE_TITLE', 'New Prescription');
define('PAGE_SUBTITLE', 'Record a patient prescription');

$branchId = getUserBranchId() ?? 1;
$medicines = $pdo->query("SELECT id, name, unit FROM medicines WHERE status='active' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'customer_name'  => trim($_POST['customer_name']),
        'customer_phone' => trim($_POST['customer_phone'] ?? ''),
        'doctor_name'    => trim($_POST['doctor_name'] ?? ''),
        'doctor_license' => trim($_POST['doctor_license'] ?? ''),
        'hospital'       => trim($_POST['hospital_clinic'] ?? ''),
        'issue_date'     => $_POST['issue_date'] ?: null,
        'expiry_date'    => $_POST['expiry_date'] ?: null,
        'notes'          => trim($_POST['notes'] ?? ''),
    ];
    $items = $_POST['medicine_id'] ?? [];
    $qtys  = $_POST['quantity'] ?? [];
    $dosages = $_POST['dosage'] ?? [];

    if (!$data['customer_name'] || empty($items)) {
        $error = 'Patient name and at least one medicine are required.';
    } else {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO prescriptions (customer_name,customer_phone,doctor_name,doctor_license,hospital_clinic,issue_date,expiry_date,user_id,notes) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$data['customer_name'],$data['customer_phone'],$data['doctor_name'],$data['doctor_license'],$data['hospital'],$data['issue_date'],$data['expiry_date'],$_SESSION['user_id'],$data['notes']]);
        $pid = $pdo->lastInsertId();
        foreach ($items as $k => $medId) {
            if (!$medId) continue;
            $pdo->prepare("INSERT INTO prescription_items (prescription_id,medicine_id,quantity,dosage) VALUES (?,?,?,?)")
                ->execute([$pid,(int)$medId,(int)($qtys[$k]??1),trim($dosages[$k]??'')]);
        }
        $pdo->commit();
        logActivity($pdo, "Created prescription for {$data['customer_name']}", 'prescriptions');
        header('Location: /pharmacy/prescriptions/view.php?id='.$pid.'&created=1');
        exit;
    }
}

require_once '../includes/header.php';
?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div style="max-width:750px;">
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-file-medical" style="color:var(--primary)"></i> New Prescription</div>
        <a href="/pharmacy/prescriptions/index.php" class="btn btn-outline btn-sm">← Back</a>
    </div>
    <div class="card-body">
        <form method="POST" id="rxForm">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Patient Name *</label>
                    <input type="text" name="customer_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Patient Phone</label>
                    <input type="text" name="customer_phone" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Doctor Name</label>
                    <input type="text" name="doctor_name" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Doctor License #</label>
                    <input type="text" name="doctor_license" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Hospital / Clinic</label>
                <input type="text" name="hospital_clinic" class="form-control">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Issue Date</label>
                    <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Expiry Date</label>
                    <input type="date" name="expiry_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Prescribed Medicines</label>
                <div id="rxItems">
                    <div class="rx-item" style="display:grid;grid-template-columns:2fr 1fr 2fr auto;gap:10px;margin-bottom:10px;align-items:end;">
                        <div>
                            <select name="medicine_id[]" class="form-control" required>
                                <option value="">-- Select Medicine --</option>
                                <?php foreach ($medicines as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?> (<?= $m['unit'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div><input type="number" name="quantity[]" class="form-control" min="1" value="1" placeholder="Qty"></div>
                        <div><input type="text" name="dosage[]" class="form-control" placeholder="Dosage instructions"></div>
                        <div><button type="button" onclick="this.closest('.rx-item').remove()" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button></div>
                    </div>
                </div>
                <button type="button" onclick="addRxItem()" class="btn btn-outline btn-sm"><i class="fas fa-plus"></i> Add Medicine</button>
            </div>

            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Prescription</button>
        </form>
    </div>
</div>
</div>

<script>
const medicineOptions = `<?php foreach ($medicines as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?> (<?= $m['unit'] ?>)</option><?php endforeach; ?>`;
function addRxItem() {
    const div = document.createElement('div');
    div.className = 'rx-item';
    div.style = 'display:grid;grid-template-columns:2fr 1fr 2fr auto;gap:10px;margin-bottom:10px;align-items:end;';
    div.innerHTML = `<div><select name="medicine_id[]" class="form-control"><option value="">-- Select Medicine --</option>${medicineOptions}</select></div><div><input type="number" name="quantity[]" class="form-control" min="1" value="1" placeholder="Qty"></div><div><input type="text" name="dosage[]" class="form-control" placeholder="Dosage instructions"></div><div><button type="button" onclick="this.closest('.rx-item').remove()" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button></div>`;
    document.getElementById('rxItems').appendChild(div);
}
</script>
<?php require_once '../includes/footer.php'; ?>
