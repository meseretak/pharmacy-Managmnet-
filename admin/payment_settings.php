<?php
require_once '../config/db.php';
requireLogin();
if (!isSuperAdmin()) { header('Location: /pharmacy/dashboard.php'); exit; }
define('PAGE_TITLE', 'Payment Settings');
define('PAGE_SUBTITLE', 'Configure Ethiopian payment gateways');

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gateway   = $_POST['gateway'] ?? '';
    $enabled   = isset($_POST['is_enabled']) ? 1 : 0;
    $testMode  = isset($_POST['is_test_mode']) ? 1 : 0;
    $publicKey = trim($_POST['public_key'] ?? '');
    $secretKey = trim($_POST['secret_key'] ?? '');
    $extra     = [];

    if ($gateway === 'telebirr') {
        $extra = [
            'app_id'     => trim($_POST['app_id'] ?? ''),
            'short_code' => trim($_POST['short_code'] ?? ''),
        ];
    }

    $pdo->prepare("UPDATE payment_settings SET is_enabled=?, is_test_mode=?, public_key=?, secret_key=?, extra_config=? WHERE gateway=?")
        ->execute([$enabled, $testMode, $publicKey, $secretKey, json_encode($extra), $gateway]);

    logActivity($pdo, 'Updated payment settings for: ' . $gateway, 'payments');
    $success = ucfirst($gateway) . ' settings updated successfully.';
}

$settings = [];
$rows = $pdo->query("SELECT * FROM payment_settings")->fetchAll();
foreach ($rows as $r) {
    $settings[$r['gateway']] = $r;
    $settings[$r['gateway']]['extra'] = json_decode($r['extra_config'] ?? '{}', true);
}

$chapa    = $settings['chapa']    ?? [];
$telebirr = $settings['telebirr'] ?? [];

require_once '../includes/header.php';
?>

<?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

    <!-- CHAPA -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <span style="font-size:20px;">🟢</span> Chapa Payment Gateway
                <?php if (!empty($chapa['is_enabled'])): ?>
                <span class="badge badge-success">Active</span>
                <?php else: ?>
                <span class="badge badge-secondary">Inactive</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div style="background:var(--primary-light);border-radius:8px;padding:12px;margin-bottom:18px;font-size:13px;">
                <strong>Chapa</strong> is Ethiopia's leading payment gateway supporting Telebirr, CBE Birr, Awash Bank, Dashen Bank, and more.<br>
                <a href="https://dashboard.chapa.co" target="_blank" style="color:var(--primary);">Sign up at dashboard.chapa.co →</a>
            </div>
            <form method="POST">
                <input type="hidden" name="gateway" value="chapa">
                <div style="display:flex;gap:20px;margin-bottom:15px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_enabled" value="1" <?= !empty($chapa['is_enabled']) ? 'checked' : '' ?> style="width:16px;height:16px;">
                        <span class="form-label" style="margin:0;">Enable Chapa</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_test_mode" value="1" <?= !isset($chapa['is_test_mode']) || $chapa['is_test_mode'] ? 'checked' : '' ?> style="width:16px;height:16px;">
                        <span class="form-label" style="margin:0;">Test Mode (Sandbox)</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label">Public Key (CHAPUBK-...)</label>
                    <input type="text" name="public_key" class="form-control" value="<?= htmlspecialchars($chapa['public_key'] ?? '') ?>" placeholder="CHAPUBK-TEST-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                </div>
                <div class="form-group">
                    <label class="form-label">Secret Key (CHASECK-...)</label>
                    <input type="password" name="secret_key" class="form-control" value="<?= htmlspecialchars($chapa['secret_key'] ?? '') ?>" placeholder="CHASECK-TEST-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                    <div class="tooltip-text">Keep this secret. Never share it publicly.</div>
                </div>
                <div style="background:var(--light);border-radius:8px;padding:12px;margin-bottom:15px;font-size:12px;color:var(--text-muted);">
                    <strong>Supported Payment Methods via Chapa:</strong><br>
                    Telebirr · CBE Birr · Awash Bank · Dashen Bank · Abyssinia Bank · Amhara Bank · Visa/Mastercard
                </div>
                <button type="submit" class="btn btn-primary w-100" style="justify-content:center;"><i class="fas fa-save"></i> Save Chapa Settings</button>
            </form>
        </div>
    </div>

    <!-- TELEBIRR DIRECT -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <span style="font-size:20px;">📱</span> Telebirr Direct Integration
                <?php if (!empty($telebirr['is_enabled'])): ?>
                <span class="badge badge-success">Active</span>
                <?php else: ?>
                <span class="badge badge-secondary">Inactive</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div style="background:#fff3cd;border-radius:8px;padding:12px;margin-bottom:18px;font-size:13px;">
                <strong>⚠️ Direct Telebirr API</strong> requires official registration with Ethio Telecom.<br>
                Contact: <strong>telebirr@ethiotelecom.et</strong> or visit <strong>telebirr.com</strong><br>
                You need: App ID, App Key, RSA Public Key, Short Code.<br>
                <em>Alternatively, use Chapa above which includes Telebirr support.</em>
            </div>
            <form method="POST">
                <input type="hidden" name="gateway" value="telebirr">
                <div style="display:flex;gap:20px;margin-bottom:15px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_enabled" value="1" <?= !empty($telebirr['is_enabled']) ? 'checked' : '' ?> style="width:16px;height:16px;">
                        <span class="form-label" style="margin:0;">Enable Telebirr Direct</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_test_mode" value="1" <?= !isset($telebirr['is_test_mode']) || $telebirr['is_test_mode'] ? 'checked' : '' ?> style="width:16px;height:16px;">
                        <span class="form-label" style="margin:0;">Test Mode</span>
                    </label>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">App ID</label>
                        <input type="text" name="app_id" class="form-control" value="<?= htmlspecialchars($telebirr['extra']['app_id'] ?? '') ?>" placeholder="From Ethio Telecom">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Short Code</label>
                        <input type="text" name="short_code" class="form-control" value="<?= htmlspecialchars($telebirr['extra']['short_code'] ?? '') ?>" placeholder="Merchant short code">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">App Key (Secret)</label>
                    <input type="password" name="secret_key" class="form-control" value="<?= htmlspecialchars($telebirr['secret_key'] ?? '') ?>" placeholder="App Key from Ethio Telecom">
                </div>
                <div class="form-group">
                    <label class="form-label">RSA Public Key</label>
                    <textarea name="public_key" class="form-control" rows="3" placeholder="Paste RSA public key here (without headers)"><?= htmlspecialchars($telebirr['public_key'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary w-100" style="justify-content:center;"><i class="fas fa-save"></i> Save Telebirr Settings</button>
            </form>
        </div>
    </div>
</div>

<!-- Transaction History -->
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-history" style="color:var(--info)"></i> Recent Payment Transactions</div>
    </div>
    <?php
    $txList = $pdo->query("SELECT pt.*, s.invoice_number, u.name as user_name FROM payment_transactions pt LEFT JOIN sales s ON pt.sale_id=s.id LEFT JOIN users u ON pt.user_id=u.id ORDER BY pt.created_at DESC LIMIT 20")->fetchAll();
    ?>
    <?php if (empty($txList)): ?>
    <div class="card-body"><div class="empty-state"><div class="empty-icon">💳</div><p>No payment transactions yet</p></div></div>
    <?php else: ?>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Tx Ref</th><th>Gateway</th><th>Amount</th><th>Customer</th><th>Invoice</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach ($txList as $tx): ?>
                <tr>
                    <td style="font-size:11px;font-family:monospace;"><?= htmlspecialchars(substr($tx['tx_ref'],0,20)) ?>...</td>
                    <td><span class="badge badge-info"><?= ucfirst($tx['payment_gateway']) ?></span></td>
                    <td><strong><?= formatCurrency($tx['amount']) ?></strong></td>
                    <td><?= htmlspecialchars($tx['customer_name']) ?></td>
                    <td><?= $tx['invoice_number'] ? '<a href="/pharmacy/sales/view.php?id='.$tx['sale_id'].'" style="color:var(--primary);">'.htmlspecialchars($tx['invoice_number']).'</a>' : '-' ?></td>
                    <td>
                        <span class="badge <?= $tx['status']==='success'?'badge-success':($tx['status']==='pending'?'badge-warning':'badge-danger') ?>">
                            <?= ucfirst($tx['status']) ?>
                        </span>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= date('d M Y H:i', strtotime($tx['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
