<?php
require_once '../config/db.php';
requireLogin();
if (!isSuperAdmin()) { header('Location: /pharmacy/dashboard.php'); exit; }
define('PAGE_TITLE', 'System Settings');
define('PAGE_SUBTITLE', 'Application configuration');

require_once '../includes/header.php';
?>

<div style="max-width:700px;">
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-cog" style="color:var(--primary)"></i> System Information</div></div>
        <div class="card-body">
            <table style="width:100%;">
                <tr><td style="padding:10px 0;color:var(--text-muted);width:40%;">Application Name</td><td><strong><?= APP_NAME ?></strong></td></tr>
                <tr><td style="padding:10px 0;color:var(--text-muted);">Version</td><td><?= APP_VERSION ?></td></tr>
                <tr><td style="padding:10px 0;color:var(--text-muted);">Currency</td><td><?= CURRENCY ?></td></tr>
                <tr><td style="padding:10px 0;color:var(--text-muted);">Database</td><td><?= DB_NAME ?> @ <?= DB_HOST ?>:<?= DB_PORT ?></td></tr>
                <tr><td style="padding:10px 0;color:var(--text-muted);">PHP Version</td><td><?= PHP_VERSION ?></td></tr>
                <tr><td style="padding:10px 0;color:var(--text-muted);">Server Time</td><td><?= date('d M Y H:i:s') ?></td></tr>
            </table>
        </div>
    </div>

    <div class="card mt-2">
        <div class="card-header"><div class="card-title"><i class="fas fa-database" style="color:var(--info)"></i> Database Stats</div></div>
        <div class="card-body">
            <?php
            $stats = [
                'Users'      => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
                'Branches'   => $pdo->query("SELECT COUNT(*) FROM branches")->fetchColumn(),
                'Medicines'  => $pdo->query("SELECT COUNT(*) FROM medicines")->fetchColumn(),
                'Stock Lines'=> $pdo->query("SELECT COUNT(*) FROM stock")->fetchColumn(),
                'Total Sales'=> $pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn(),
                'Suppliers'  => $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn(),
            ];
            ?>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:15px;">
                <?php foreach ($stats as $label => $count): ?>
                <div style="text-align:center;padding:15px;background:var(--light);border-radius:8px;">
                    <div style="font-size:24px;font-weight:700;color:var(--primary);"><?= number_format($count) ?></div>
                    <div style="font-size:12px;color:var(--text-muted);"><?= $label ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card mt-2">
        <div class="card-header"><div class="card-title"><i class="fas fa-history" style="color:var(--warning)"></i> Recent Activity Log</div></div>
        <div class="table-responsive">
            <?php $logs = $pdo->query("SELECT al.*, u.name as user_name FROM activity_log al LEFT JOIN users u ON al.user_id=u.id ORDER BY al.created_at DESC LIMIT 20")->fetchAll(); ?>
            <table>
                <thead><tr><th>User</th><th>Action</th><th>Module</th><th>IP</th><th>Time</th></tr></thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['user_name'] ?? 'System') ?></td>
                        <td><?= htmlspecialchars($log['action']) ?></td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($log['module']) ?></span></td>
                        <td style="font-size:12px;"><?= htmlspecialchars($log['ip_address']) ?></td>
                        <td style="font-size:12px;color:var(--text-muted);"><?= date('d M H:i', strtotime($log['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
