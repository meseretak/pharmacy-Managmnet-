<?php
require_once '../config/db.php';
requireLogin();
if (!isSuperAdmin()) { header('Location: /pharmacy/dashboard.php'); exit; }
define('PAGE_TITLE', 'Shop Settings');
define('PAGE_SUBTITLE', 'Configure pharmacy hours, notifications & preferences');

$success = '';
$error   = '';

// Load all settings
$allSettings = [];
$rows = $pdo->query("SELECT setting_key, setting_value FROM shop_settings WHERE branch_id IS NULL")->fetchAll();
foreach ($rows as $r) $allSettings[$r['setting_key']] = $r['setting_value'];

// Load branches for per-branch hours
$branches = $pdo->query("SELECT * FROM branches ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'general';

    if ($action === 'general') {
        $fields = ['shop_name','shop_tagline','currency_symbol','tax_rate','receipt_footer','low_stock_alert_email'];
        foreach ($fields as $f) {
            $val = trim($_POST[$f] ?? '');
            $pdo->prepare("INSERT INTO shop_settings (branch_id, setting_key, setting_value) VALUES (NULL,?,?) ON DUPLICATE KEY UPDATE setting_value=?")
                ->execute([$f, $val, $val]);
        }
        $success = 'General settings saved.';

    } elseif ($action === 'hours') {
        $fields = ['opening_time','closing_time','working_days'];
        foreach ($fields as $f) {
            $val = is_array($_POST[$f] ?? null) ? implode(',', $_POST[$f]) : trim($_POST[$f] ?? '');
            $pdo->prepare("INSERT INTO shop_settings (branch_id, setting_key, setting_value) VALUES (NULL,?,?) ON DUPLICATE KEY UPDATE setting_value=?")
                ->execute([$f, $val, $val]);
        }
        $success = 'Business hours saved.';

    } elseif ($action === 'branch_hours') {
        foreach ($branches as $b) {
            $bid = $b['id'];
            $isOpen = isset($_POST['is_open_'.$bid]) ? 1 : 0;
            $openTime  = $_POST['open_time_'.$bid]  ?? '08:00';
            $closeTime = $_POST['close_time_'.$bid] ?? '20:00';
            $pdo->prepare("UPDATE branches SET is_open=?, opening_time=?, closing_time=? WHERE id=?")
                ->execute([$isOpen, $openTime, $closeTime, $bid]);
        }
        $success = 'Branch hours updated.';

    } elseif ($action === 'notifications') {
        $fields = ['enable_sms_notifications','sms_api_key','enable_email_notifications','smtp_host','smtp_port','smtp_username','smtp_password'];
        foreach ($fields as $f) {
            $val = trim($_POST[$f] ?? '0');
            $pdo->prepare("INSERT INTO shop_settings (branch_id, setting_key, setting_value) VALUES (NULL,?,?) ON DUPLICATE KEY UPDATE setting_value=?")
                ->execute([$f, $val, $val]);
        }
        $success = 'Notification settings saved.';

    } elseif ($action === 'features') {
        $fields = ['enable_barcode_scanner','auto_backup_enabled','backup_frequency'];
        foreach ($fields as $f) {
            $val = trim($_POST[$f] ?? '0');
            $pdo->prepare("INSERT INTO shop_settings (branch_id, setting_key, setting_value) VALUES (NULL,?,?) ON DUPLICATE KEY UPDATE setting_value=?")
                ->execute([$f, $val, $val]);
        }
        $success = 'Feature settings saved.';
    }

    // Reload
    $rows = $pdo->query("SELECT setting_key, setting_value FROM shop_settings WHERE branch_id IS NULL")->fetchAll();
    foreach ($rows as $r) $allSettings[$r['setting_key']] = $r['setting_value'];
    $branches = $pdo->query("SELECT * FROM branches ORDER BY name")->fetchAll();
    logActivity($pdo, 'Updated shop settings: ' . $action, 'settings');
}

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$workingDays = explode(',', $allSettings['working_days'] ?? 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday');

require_once '../includes/header.php';
?>

<?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Tab Navigation -->
<div style="display:flex;gap:5px;margin-bottom:20px;flex-wrap:wrap;">
    <?php
    $tabs = [
        'general'      => ['icon'=>'fa-store','label'=>'General'],
        'hours'        => ['icon'=>'fa-clock','label'=>'Business Hours'],
        'branch_hours' => ['icon'=>'fa-building','label'=>'Branch Status'],
        'notifications'=> ['icon'=>'fa-bell','label'=>'Notifications'],
        'features'     => ['icon'=>'fa-sliders-h','label'=>'Features'],
    ];
    $activeTab = $_GET['tab'] ?? 'general';
    foreach ($tabs as $key => $tab):
    ?>
    <a href="?tab=<?= $key ?>" class="btn <?= $activeTab===$key ? 'btn-primary' : 'btn-outline' ?>">
        <i class="fas <?= $tab['icon'] ?>"></i> <?= $tab['label'] ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- GENERAL SETTINGS -->
<?php if ($activeTab === 'general'): ?>
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-store" style="color:var(--primary)"></i> General Settings</div></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="general">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Pharmacy / Shop Name</label>
                    <input type="text" name="shop_name" class="form-control" value="<?= htmlspecialchars($allSettings['shop_name'] ?? 'PharmaCare Pro') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Tagline</label>
                    <input type="text" name="shop_tagline" class="form-control" value="<?= htmlspecialchars($allSettings['shop_tagline'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Currency Symbol</label>
                    <select name="currency_symbol" class="form-control">
                        <?php foreach (['ETB'=>'ETB - Ethiopian Birr','USD'=>'USD - US Dollar','EUR'=>'EUR - Euro','GBP'=>'GBP - British Pound'] as $code => $label): ?>
                        <option value="<?= $code ?>" <?= ($allSettings['currency_symbol']??'ETB')===$code?'selected':'' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tax Rate (%)</label>
                    <input type="number" name="tax_rate" class="form-control" value="<?= htmlspecialchars($allSettings['tax_rate'] ?? '0') ?>" step="0.01" min="0" max="100">
                    <div class="tooltip-text">Set to 0 to disable tax calculation</div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Receipt Footer Message</label>
                <textarea name="receipt_footer" class="form-control" rows="2"><?= htmlspecialchars($allSettings['receipt_footer'] ?? 'Thank you for your purchase!') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Low Stock Alert Email</label>
                <input type="email" name="low_stock_alert_email" class="form-control" value="<?= htmlspecialchars($allSettings['low_stock_alert_email'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save General Settings</button>
        </form>
    </div>
</div>

<!-- HOURS TAB -->
<?php elseif ($activeTab === 'hours'): ?>
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-clock" style="color:var(--primary)"></i> Default Business Hours</div></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="hours">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Opening Time</label>
                    <input type="time" name="opening_time" class="form-control" value="<?= htmlspecialchars($allSettings['opening_time'] ?? '08:00') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Closing Time</label>
                    <input type="time" name="closing_time" class="form-control" value="<?= htmlspecialchars($allSettings['closing_time'] ?? '20:00') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Working Days</label>
                <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px;">
                    <?php foreach ($days as $day): ?>
                    <label style="display:flex;align-items:center;gap:6px;padding:8px 14px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;<?= in_array($day,$workingDays)?'background:var(--primary-light);border-color:var(--primary);':'' ?>">
                        <input type="checkbox" name="working_days[]" value="<?= $day ?>" <?= in_array($day,$workingDays)?'checked':'' ?> style="width:15px;height:15px;">
                        <span style="font-size:13px;font-weight:500;"><?= $day ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Business Hours</button>
        </form>
    </div>
</div>

<!-- BRANCH STATUS TAB -->
<?php elseif ($activeTab === 'branch_hours'): ?>
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-building" style="color:var(--primary)"></i> Branch Open/Close Status</div>
        <div style="font-size:12px;color:var(--text-muted);">Current server time: <strong><?= date('H:i:s') ?></strong></div>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="branch_hours">
            <div style="display:grid;gap:15px;">
                <?php foreach ($branches as $b): ?>
                <?php
                $now = date('H:i:s');
                $isCurrentlyOpen = $b['is_open'] && $now >= $b['opening_time'] && $now <= $b['closing_time'];
                ?>
                <div style="border:1.5px solid <?= $isCurrentlyOpen ? 'var(--secondary)' : 'var(--border)' ?>;border-radius:10px;padding:18px;background:<?= $isCurrentlyOpen ? 'var(--primary-light)' : '#fff' ?>;">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:15px;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div style="width:12px;height:12px;border-radius:50%;background:<?= $isCurrentlyOpen ? 'var(--secondary)' : 'var(--danger)' ?>;box-shadow:0 0 6px <?= $isCurrentlyOpen ? 'var(--secondary)' : 'var(--danger)' ?>;"></div>
                            <div>
                                <div style="font-weight:700;font-size:15px;"><?= htmlspecialchars($b['name']) ?></div>
                                <div style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($b['location']) ?></div>
                            </div>
                            <span class="badge <?= $isCurrentlyOpen ? 'badge-success' : 'badge-danger' ?>" style="font-size:12px;">
                                <?= $isCurrentlyOpen ? '🟢 OPEN NOW' : '🔴 CLOSED' ?>
                            </span>
                        </div>
                        <div style="display:flex;align-items:center;gap:15px;flex-wrap:wrap;">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" name="is_open_<?= $b['id'] ?>" value="1" <?= $b['is_open'] ? 'checked' : '' ?> style="width:18px;height:18px;cursor:pointer;">
                                <span style="font-weight:600;">Branch Active</span>
                            </label>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <label style="font-size:12px;color:var(--text-muted);">Opens:</label>
                                <input type="time" name="open_time_<?= $b['id'] ?>" class="form-control" style="width:120px;padding:6px 10px;" value="<?= substr($b['opening_time'],0,5) ?>">
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <label style="font-size:12px;color:var(--text-muted);">Closes:</label>
                                <input type="time" name="close_time_<?= $b['id'] ?>" class="form-control" style="width:120px;padding:6px 10px;" value="<?= substr($b['closing_time'],0,5) ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Branch Status</button>
                <div class="tooltip-text" style="margin-top:8px;">Unchecking "Branch Active" will show a "Closed" page to customers and block new sales for that branch.</div>
            </div>
        </form>
    </div>
</div>

<!-- NOTIFICATIONS TAB -->
<?php elseif ($activeTab === 'notifications'): ?>
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-bell" style="color:var(--primary)"></i> Notification Settings</div></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="notifications">
            <div style="background:var(--light);border-radius:8px;padding:15px;margin-bottom:20px;">
                <strong>SMS Notifications</strong>
                <div style="margin-top:12px;display:flex;flex-direction:column;gap:12px;">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                        <input type="checkbox" name="enable_sms_notifications" value="1" <?= !empty($allSettings['enable_sms_notifications']) && $allSettings['enable_sms_notifications']=='1' ? 'checked' : '' ?> style="width:16px;height:16px;">
                        <span>Enable SMS Notifications (for low stock, expiry alerts)</span>
                    </label>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">SMS API Key (e.g. AfricasTalking, Twilio)</label>
                        <input type="text" name="sms_api_key" class="form-control" value="<?= htmlspecialchars($allSettings['sms_api_key'] ?? '') ?>" placeholder="Your SMS gateway API key">
                    </div>
                </div>
            </div>
            <div style="background:var(--light);border-radius:8px;padding:15px;">
                <strong>Email Notifications (SMTP)</strong>
                <div style="margin-top:12px;display:flex;flex-direction:column;gap:12px;">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                        <input type="checkbox" name="enable_email_notifications" value="1" <?= !empty($allSettings['enable_email_notifications']) && $allSettings['enable_email_notifications']=='1' ? 'checked' : '' ?> style="width:16px;height:16px;">
                        <span>Enable Email Notifications</span>
                    </label>
                    <div class="form-row">
                        <div class="form-group" style="margin:0;"><label class="form-label">SMTP Host</label><input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($allSettings['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com"></div>
                        <div class="form-group" style="margin:0;"><label class="form-label">SMTP Port</label><input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars($allSettings['smtp_port'] ?? '587') ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="margin:0;"><label class="form-label">Username</label><input type="text" name="smtp_username" class="form-control" value="<?= htmlspecialchars($allSettings['smtp_username'] ?? '') ?>"></div>
                        <div class="form-group" style="margin:0;"><label class="form-label">Password</label><input type="password" name="smtp_password" class="form-control" value="<?= htmlspecialchars($allSettings['smtp_password'] ?? '') ?>"></div>
                    </div>
                </div>
            </div>
            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Notification Settings</button>
            </div>
        </form>
    </div>
</div>

<!-- FEATURES TAB -->
<?php elseif ($activeTab === 'features'): ?>
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-sliders-h" style="color:var(--primary)"></i> Feature Settings</div></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="features">
            <div style="display:flex;flex-direction:column;gap:18px;">
                <div style="padding:15px;border:1.5px solid var(--border);border-radius:10px;">
                    <label style="display:flex;align-items:center;gap:12px;cursor:pointer;">
                        <input type="checkbox" name="enable_barcode_scanner" value="1" <?= !empty($allSettings['enable_barcode_scanner']) && $allSettings['enable_barcode_scanner']=='1' ? 'checked' : '' ?> style="width:18px;height:18px;">
                        <div>
                            <div style="font-weight:600;">Barcode Scanner Support</div>
                            <div style="font-size:12px;color:var(--text-muted);">Enable barcode input in POS for faster medicine lookup</div>
                        </div>
                    </label>
                </div>
                <div style="padding:15px;border:1.5px solid var(--border);border-radius:10px;">
                    <label style="display:flex;align-items:center;gap:12px;cursor:pointer;">
                        <input type="checkbox" name="auto_backup_enabled" value="1" <?= !empty($allSettings['auto_backup_enabled']) && $allSettings['auto_backup_enabled']=='1' ? 'checked' : '' ?> style="width:18px;height:18px;">
                        <div>
                            <div style="font-weight:600;">Auto Database Backup</div>
                            <div style="font-size:12px;color:var(--text-muted);">Automatically backup the database</div>
                        </div>
                    </label>
                    <div style="margin-top:12px;padding-left:30px;">
                        <label class="form-label">Backup Frequency</label>
                        <select name="backup_frequency" class="form-control" style="width:200px;">
                            <option value="daily" <?= ($allSettings['backup_frequency']??'daily')==='daily'?'selected':'' ?>>Daily</option>
                            <option value="weekly" <?= ($allSettings['backup_frequency']??'')==='weekly'?'selected':'' ?>>Weekly</option>
                            <option value="monthly" <?= ($allSettings['backup_frequency']??'')==='monthly'?'selected':'' ?>>Monthly</option>
                        </select>
                    </div>
                </div>
            </div>
            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Feature Settings</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
