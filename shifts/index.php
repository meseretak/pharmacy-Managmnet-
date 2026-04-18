<?php
require_once '../config/db.php';
requireLogin();
define('PAGE_TITLE', 'Shift Management');
define('PAGE_SUBTITLE', 'Open & close daily shifts');

$branchId = getUserBranchId() ?? 1;
$success = $error = '';

// Get current open shift for this user/branch
$openShift = $pdo->prepare("SELECT s.*, u.name as user_name FROM shifts s JOIN users u ON s.user_id=u.id WHERE s.branch_id=? AND s.user_id=? AND s.status='open' ORDER BY s.created_at DESC LIMIT 1");
$openShift->execute([$branchId, $_SESSION['user_id']]);
$openShift = $openShift->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'open_shift') {
        if ($openShift) {
            $error = 'You already have an open shift.';
        } else {
            $openingCash = (float)($_POST['opening_cash'] ?? 0);
            $pdo->prepare("INSERT INTO shifts (branch_id, user_id, shift_date, start_time, opening_cash, status) VALUES (?,?,CURDATE(),CURTIME(),?,'open')")
                ->execute([$branchId, $_SESSION['user_id'], $openingCash]);
            logActivity($pdo, 'Opened shift with ETB ' . $openingCash . ' opening cash', 'shifts');
            $success = 'Shift opened successfully.';
            $openShift = $pdo->prepare("SELECT s.*, u.name as user_name FROM shifts s JOIN users u ON s.user_id=u.id WHERE s.branch_id=? AND s.user_id=? AND s.status='open' ORDER BY s.created_at DESC LIMIT 1");
            $openShift->execute([$branchId, $_SESSION['user_id']]);
            $openShift = $openShift->fetch();
        }
    } elseif ($action === 'close_shift' && $openShift) {
        $closingCash = (float)($_POST['closing_cash'] ?? 0);
        $notes       = trim($_POST['notes'] ?? '');
        // Calculate total sales during this shift
        $totalSales = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE branch_id=? AND user_id=? AND created_at >= ? AND status='completed'");
        $totalSales->execute([$branchId, $_SESSION['user_id'], $openShift['created_at']]);
        $totalSales = $totalSales->fetchColumn();

        $pdo->prepare("UPDATE shifts SET status='closed', end_time=CURTIME(), closing_cash=?, total_sales=?, notes=?, closed_at=NOW() WHERE id=?")
            ->execute([$closingCash, $totalSales, $notes, $openShift['id']]);
        logActivity($pdo, 'Closed shift. Total sales: ETB ' . $totalSales, 'shifts');
        $success = 'Shift closed. Total sales: ' . formatCurrency($totalSales);
        $openShift = null;
    }
}

// Recent shifts
$shifts = $pdo->prepare("
    SELECT s.*, u.name as user_name, b.name as branch_name
    FROM shifts s JOIN users u ON s.user_id=u.id JOIN branches b ON s.branch_id=b.id
    WHERE s.branch_id=?
    ORDER BY s.created_at DESC LIMIT 20
");
$shifts->execute([$branchId]);
$shifts = $shifts->fetchAll();

require_once '../includes/header.php';
?>

<?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
    <!-- Current Shift Status -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-user-clock" style="color:var(--primary)"></i> Current Shift</div>
            <div style="font-size:13px;color:var(--text-muted);" id="shiftClock"></div>
        </div>
        <div class="card-body">
            <?php if ($openShift): ?>
            <div style="text-align:center;padding:10px 0;">
                <div style="font-size:50px;margin-bottom:10px;">🟢</div>
                <div style="font-size:18px;font-weight:700;color:var(--secondary);">Shift is OPEN</div>
                <div style="color:var(--text-muted);font-size:13px;margin-top:5px;">Started at <?= date('H:i', strtotime($openShift['created_at'])) ?></div>
            </div>
            <div style="background:var(--light);border-radius:8px;padding:15px;margin:15px 0;">
                <?php
                $shiftSales = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) as rev, COUNT(*) as cnt FROM sales WHERE branch_id=? AND user_id=? AND created_at >= ? AND status='completed'");
                $shiftSales->execute([$branchId, $_SESSION['user_id'], $openShift['created_at']]);
                $shiftSales = $shiftSales->fetch();
                ?>
                <div style="display:flex;justify-content:space-between;padding:6px 0;"><span style="color:var(--text-muted);">Opening Cash</span><strong><?= formatCurrency($openShift['opening_cash']) ?></strong></div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;"><span style="color:var(--text-muted);">Sales This Shift</span><strong style="color:var(--primary)"><?= formatCurrency($shiftSales['rev']) ?></strong></div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;"><span style="color:var(--text-muted);">Transactions</span><strong><?= $shiftSales['cnt'] ?></strong></div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:2px solid var(--border);margin-top:5px;"><span style="font-weight:700;">Expected Cash</span><strong style="color:var(--primary);font-size:16px;"><?= formatCurrency($openShift['opening_cash'] + $shiftSales['rev']) ?></strong></div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="close_shift">
                <div class="form-group">
                    <label class="form-label">Actual Closing Cash (<?= CURRENCY ?>)</label>
                    <input type="number" name="closing_cash" class="form-control" step="0.01" min="0" placeholder="Count your cash drawer" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes (optional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Any notes for this shift..."></textarea>
                </div>
                <button type="submit" class="btn btn-danger w-100" style="justify-content:center;" onclick="return confirm('Close this shift?')">
                    <i class="fas fa-lock"></i> Close Shift
                </button>
            </form>
            <?php else: ?>
            <div style="text-align:center;padding:10px 0;">
                <div style="font-size:50px;margin-bottom:10px;">🔴</div>
                <div style="font-size:18px;font-weight:700;color:var(--danger);">No Open Shift</div>
                <div style="color:var(--text-muted);font-size:13px;margin-top:5px;">Open a shift to start recording sales</div>
            </div>
            <form method="POST" style="margin-top:20px;">
                <input type="hidden" name="action" value="open_shift">
                <div class="form-group">
                    <label class="form-label">Opening Cash (<?= CURRENCY ?>)</label>
                    <input type="number" name="opening_cash" class="form-control" step="0.01" min="0" value="0" placeholder="Cash in drawer at start">
                </div>
                <button type="submit" class="btn btn-success w-100" style="justify-content:center;">
                    <i class="fas fa-unlock"></i> Open Shift
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Today's Summary -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-chart-pie" style="color:var(--info)"></i> Today's Summary</div></div>
        <div class="card-body">
            <?php
            $todayStats = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as rev, COALESCE(SUM(discount),0) as disc FROM sales WHERE branch_id=? AND DATE(created_at)=CURDATE() AND status='completed'");
            $todayStats->execute([$branchId]);
            $todayStats = $todayStats->fetch();

            $todayItems = $pdo->prepare("SELECT COALESCE(SUM(si.quantity),0) FROM sale_items si JOIN sales sa ON si.sale_id=sa.id WHERE sa.branch_id=? AND DATE(sa.created_at)=CURDATE() AND sa.status='completed'");
            $todayItems->execute([$branchId]);
            $todayItems = $todayItems->fetchColumn();

            $topMed = $pdo->prepare("SELECT m.name, SUM(si.quantity) as qty FROM sale_items si JOIN medicines m ON si.medicine_id=m.id JOIN sales sa ON si.sale_id=sa.id WHERE sa.branch_id=? AND DATE(sa.created_at)=CURDATE() AND sa.status='completed' GROUP BY si.medicine_id ORDER BY qty DESC LIMIT 1");
            $topMed->execute([$branchId]);
            $topMed = $topMed->fetch();
            ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div style="background:var(--primary-light);border-radius:10px;padding:15px;text-align:center;">
                    <div style="font-size:24px;font-weight:800;color:var(--primary);"><?= formatCurrency($todayStats['rev']) ?></div>
                    <div style="font-size:12px;color:var(--text-muted);">Today's Revenue</div>
                </div>
                <div style="background:#e8f4fd;border-radius:10px;padding:15px;text-align:center;">
                    <div style="font-size:24px;font-weight:800;color:var(--info);"><?= $todayStats['cnt'] ?></div>
                    <div style="font-size:12px;color:var(--text-muted);">Transactions</div>
                </div>
                <div style="background:#fef9e7;border-radius:10px;padding:15px;text-align:center;">
                    <div style="font-size:24px;font-weight:800;color:var(--warning);"><?= number_format($todayItems) ?></div>
                    <div style="font-size:12px;color:var(--text-muted);">Items Sold</div>
                </div>
                <div style="background:#fdf2f2;border-radius:10px;padding:15px;text-align:center;">
                    <div style="font-size:24px;font-weight:800;color:var(--danger);"><?= formatCurrency($todayStats['disc']) ?></div>
                    <div style="font-size:12px;color:var(--text-muted);">Discounts Given</div>
                </div>
            </div>
            <?php if ($topMed): ?>
            <div style="margin-top:15px;background:var(--light);border-radius:8px;padding:12px;">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Top Medicine Today</div>
                <div style="font-size:15px;font-weight:700;margin-top:4px;">🏆 <?= htmlspecialchars($topMed['name']) ?></div>
                <div style="font-size:12px;color:var(--text-muted);"><?= $topMed['qty'] ?> units sold</div>
            </div>
            <?php endif; ?>
            <div style="margin-top:15px;">
                <a href="/pharmacy/sales/new.php" class="btn btn-primary w-100" style="justify-content:center;"><i class="fas fa-cash-register"></i> Go to POS</a>
            </div>
        </div>
    </div>
</div>

<!-- Shift History -->
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-history" style="color:var(--dark)"></i> Shift History</div></div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr><th>Date</th><th>Staff</th><th>Start</th><th>End</th><th>Opening Cash</th><th>Closing Cash</th><th>Total Sales</th><th>Variance</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php if (empty($shifts)): ?>
                <tr><td colspan="9"><div class="empty-state"><div class="empty-icon">📋</div><p>No shifts recorded yet</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($shifts as $s): ?>
                <?php
                $expected  = $s['opening_cash'] + $s['total_sales'];
                $variance  = $s['status'] === 'closed' ? $s['closing_cash'] - $expected : 0;
                ?>
                <tr>
                    <td><strong><?= date('d M Y', strtotime($s['shift_date'])) ?></strong></td>
                    <td><?= htmlspecialchars($s['user_name']) ?></td>
                    <td><?= substr($s['start_time'],0,5) ?></td>
                    <td><?= $s['end_time'] ? substr($s['end_time'],0,5) : '—' ?></td>
                    <td><?= formatCurrency($s['opening_cash']) ?></td>
                    <td><?= $s['status']==='closed' ? formatCurrency($s['closing_cash']) : '—' ?></td>
                    <td><strong style="color:var(--primary)"><?= formatCurrency($s['total_sales']) ?></strong></td>
                    <td>
                        <?php if ($s['status']==='closed'): ?>
                        <span style="color:<?= $variance >= 0 ? 'var(--secondary)' : 'var(--danger)' ?>;font-weight:700;">
                            <?= $variance >= 0 ? '+' : '' ?><?= formatCurrency($variance) ?>
                        </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><span class="badge <?= $s['status']==='open' ? 'badge-success' : 'badge-secondary' ?>"><?= ucfirst($s['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function updateClock() {
    const now = new Date();
    document.getElementById('shiftClock').textContent = now.toLocaleTimeString();
}
updateClock();
setInterval(updateClock, 1000);
</script>

<?php require_once '../includes/footer.php'; ?>
