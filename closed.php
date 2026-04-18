<?php
require_once 'config/db.php';
$branchId = (int)($_GET['branch'] ?? 1);
$branch = $pdo->prepare("SELECT * FROM branches WHERE id=?");
$branch->execute([$branchId]);
$branch = $branch->fetch();

$shopName = shopSetting('shop_name', 'PharmaCare Pro');
$openTime  = $branch['opening_time'] ?? shopSetting('opening_time', '08:00');
$closeTime = $branch['closing_time'] ?? shopSetting('closing_time', '20:00');
$workDays  = shopSetting('working_days', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Closed - <?= htmlspecialchars($shopName) ?></title>
    <link rel="icon" type="image/svg+xml" href="/pharmacy/assets/img/favicon.svg">
    <link rel="stylesheet" href="/pharmacy/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .closed-card { background: #fff; border-radius: 20px; padding: 50px 40px; max-width: 500px; width: 90%; text-align: center; box-shadow: 0 30px 80px rgba(0,0,0,0.3); }
        .closed-icon { font-size: 80px; margin-bottom: 20px; animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.05)} }
        .clock { font-size: 36px; font-weight: 800; color: var(--primary); margin: 15px 0; font-family: monospace; }
        .hours-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0; }
        .hour-box { background: var(--light); border-radius: 10px; padding: 15px; }
        .hour-box .label { font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; }
        .hour-box .time { font-size: 22px; font-weight: 800; color: var(--primary); margin-top: 5px; }
    </style>
</head>
<body>
<div class="closed-card">
    <div class="closed-icon">
        <img src="/pharmacy/assets/img/logo.svg" alt="PharmaCare Pro" style="width:90px;height:90px;">
    </div>
    <h2 style="font-size:26px;font-weight:800;color:var(--dark);"><?= htmlspecialchars($shopName) ?></h2>
    <?php if ($branch): ?>
    <p style="color:var(--text-muted);font-size:14px;"><?= htmlspecialchars($branch['name']) ?> — <?= htmlspecialchars($branch['location']) ?></p>
    <?php endif; ?>

    <div style="background:var(--danger);color:#fff;border-radius:10px;padding:12px 20px;margin:20px 0;font-size:16px;font-weight:700;">
        🔴 We are currently CLOSED
    </div>

    <div class="clock" id="liveClock">--:--:--</div>

    <div class="hours-grid">
        <div class="hour-box">
            <div class="label">Opens At</div>
            <div class="time"><?= date('h:i A', strtotime($openTime)) ?></div>
        </div>
        <div class="hour-box">
            <div class="label">Closes At</div>
            <div class="time"><?= date('h:i A', strtotime($closeTime)) ?></div>
        </div>
    </div>

    <div style="background:var(--primary-light);border-radius:10px;padding:15px;margin:15px 0;">
        <div style="font-size:12px;color:var(--text-muted);font-weight:700;text-transform:uppercase;margin-bottom:8px;">Working Days</div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center;">
            <?php foreach (explode(',', $workDays) as $day): ?>
            <span style="background:var(--primary);color:#fff;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;"><?= trim($day) ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <p style="color:var(--text-muted);font-size:13px;margin-top:15px;">
        Please visit us during our working hours.<br>
        We look forward to serving you!
    </p>

    <?php if (isLoggedIn()): ?>
    <a href="/pharmacy/dashboard.php" class="btn btn-primary" style="margin-top:15px;justify-content:center;width:100%;">
        <i class="fas fa-tachometer-alt"></i> Go to Dashboard
    </a>
    <?php else: ?>
    <a href="/pharmacy/auth/login.php" class="btn btn-outline" style="margin-top:15px;justify-content:center;width:100%;">
        <i class="fas fa-sign-in-alt"></i> Staff Login
    </a>
    <?php endif; ?>
</div>

<script>
function updateClock() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2,'0');
    const m = String(now.getMinutes()).padStart(2,'0');
    const s = String(now.getSeconds()).padStart(2,'0');
    document.getElementById('liveClock').textContent = h + ':' + m + ':' + s;
}
updateClock();
setInterval(updateClock, 1000);
</script>
</body>
</html>
