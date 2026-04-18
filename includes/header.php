<?php
if (!defined('PAGE_TITLE')) define('PAGE_TITLE', 'Dashboard');
if (!defined('PAGE_SUBTITLE')) define('PAGE_SUBTITLE', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= PAGE_TITLE ?> - <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="/pharmacy/assets/img/favicon.svg?v=2">
    <link rel="shortcut icon" type="image/svg+xml" href="/pharmacy/assets/img/favicon.svg?v=2">
    <link rel="apple-touch-icon" href="/pharmacy/assets/img/logo.svg">
    <link rel="stylesheet" href="/pharmacy/assets/css/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<?php
// Count low stock for badge
$lowStockCount   = 0;
$outOfStockCount = 0;
try {
    $branchFilter = '';
    if (!isSuperAdmin() && getUserBranchId()) {
        $branchFilter = 'AND s.branch_id = ' . (int)getUserBranchId();
    }
    $stmt = $pdo->query("SELECT COUNT(*) FROM stock s WHERE s.quantity <= s.low_stock_threshold AND s.quantity > 0 $branchFilter");
    $lowStockCount = $stmt->fetchColumn();
    $stmt2 = $pdo->query("SELECT COUNT(*) FROM stock s WHERE s.quantity = 0 $branchFilter");
    $outOfStockCount = $stmt2->fetchColumn();
} catch(Exception $e) {}
?>
<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <img src="/pharmacy/assets/img/logo-white.svg" alt="Logo" width="30" height="30" style="display:block;">
        </div>
        <div class="brand-text">
            <h4><?= APP_NAME ?></h4>
            <span>v<?= APP_VERSION ?></span>
        </div>
    </div>

    <?php if (isSuperAdmin()): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-title">Overview</div>
        <nav class="sidebar-nav">
            <a href="/pharmacy/dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-chart-pie"></i></span> Dashboard
            </a>
            <a href="/pharmacy/branches/index.php" class="<?= strpos($_SERVER['PHP_SELF'], '/branches/') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-building"></i></span> All Branches
            </a>
        </nav>
    </div>
    <?php endif; ?>

    <div class="sidebar-section">
        <div class="sidebar-section-title">Pharmacy</div>
        <nav class="sidebar-nav">
            <a href="/pharmacy/dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard
            </a>
            <a href="/pharmacy/shifts/index.php" class="<?= strpos($_SERVER['PHP_SELF'], '/shifts/') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-user-clock"></i></span> My Shift
            </a>
            <a href="/pharmacy/sales/new.php" class="<?= strpos($_SERVER['PHP_SELF'], '/sales/new') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-cash-register"></i></span> New Sale (POS)
            </a>
            <a href="/pharmacy/sales/index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], '/sales/') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-receipt"></i></span> Sales History
            </a>
            <a href="/pharmacy/customers/index.php" class="<?= strpos($_SERVER['PHP_SELF'], '/customers/') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-users"></i></span> Customers
            </a>
            <a href="/pharmacy/chat/index.php" class="<?= strpos($_SERVER['PHP_SELF'], '/chat/') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-comments"></i></span> Messages
                <?php
                try {
                    $unreadChat = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN cm.created_at > COALESCE(crm.last_read_at,'2000-01-01') AND cm.sender_id != ? THEN 1 ELSE 0 END),0) FROM chat_messages cm JOIN chat_room_members crm ON crm.room_id=cm.room_id AND crm.user_id=? WHERE cm.is_deleted=0");
                    $unreadChat->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
                    $unreadChatCount = (int)$unreadChat->fetchColumn();
                    if ($unreadChatCount > 0) echo '<span class="badge">' . $unreadChatCount . '</span>';
                } catch(Exception $e) {}
                ?>
            </a>
        </nav>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-title">Inventory</div>
        <nav class="sidebar-nav">
            <a href="/pharmacy/medicines/index.php" class="<?= strpos($_SERVER['PHP_SELF'], '/medicines/') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-pills"></i></span> Medicines
            </a>
            <a href="/pharmacy/stock/index.php" class="<?= strpos($_SERVER['PHP_SELF'], '/stock/index') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-boxes"></i></span> Stock
                <?php if ($lowStockCount > 0): ?>
                <span class="badge"><?= $lowStockCount ?></span>
                <?php endif; ?>
            </a>
            <?php if (isBranchManager()): ?>
            <a href="/pharmacy/stock/adjustments.php" class="<?= strpos($_SERVER['PHP_SELF'], '/stock/adjustments') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-sliders-h"></i></span> Adjustments
            </a>
            <a href="/pharmacy/stock/transfers.php" class="<?= strpos($_SERVER['PHP_SELF'], '/stock/transfers') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-exchange-alt"></i></span> Transfers
            </a>
            <?php endif; ?>
            <a href="/pharmacy/prescriptions/index.php" class="<?= strpos($_SERVER['PHP_SELF'], '/prescriptions/') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-file-medical"></i></span> Prescriptions
            </a>
            <a href="/pharmacy/purchases/index.php" class="<?= strpos($_SERVER['PHP_SELF'], '/purchases/') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-truck"></i></span> Purchases
            </a>
            <a href="/pharmacy/suppliers/index.php" class="<?= strpos($_SERVER['PHP_SELF'], '/suppliers/') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-industry"></i></span> Suppliers
            </a>
        </nav>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-title">Reports</div>
        <nav class="sidebar-nav">
            <a href="/pharmacy/reports/daily.php" class="<?= strpos($_SERVER['PHP_SELF'], '/reports/daily') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-calendar-day"></i></span> Daily Report
            </a>
            <a href="/pharmacy/reports/staff.php" class="<?= strpos($_SERVER['PHP_SELF'], '/reports/staff') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-user-tie"></i></span> Staff Report
            </a>
            <a href="/pharmacy/reports/sales.php" class="<?= strpos($_SERVER['PHP_SELF'], '/reports/sales') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-chart-bar"></i></span> Sales Report
            </a>
            <a href="/pharmacy/reports/stock.php" class="<?= strpos($_SERVER['PHP_SELF'], '/reports/stock') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-chart-line"></i></span> Stock Report
            </a>
            <a href="/pharmacy/reports/expiry.php" class="<?= strpos($_SERVER['PHP_SELF'], '/reports/expiry') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-calendar-times"></i></span> Expiry Report
            </a>
            <?php if (isSuperAdmin()): ?>
            <a href="/pharmacy/reports/profit_loss.php" class="<?= strpos($_SERVER['PHP_SELF'], '/reports/profit_loss') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-file-invoice-dollar"></i></span> Profit & Loss
            </a>
            <?php endif; ?>
        </nav>
    </div>

    <?php if (isSuperAdmin()): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-title">Administration</div>
        <nav class="sidebar-nav">
            <a href="/pharmacy/admin/users.php" class="<?= strpos($_SERVER['PHP_SELF'], '/admin/users') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-users"></i></span> Users
            </a>
            <a href="/pharmacy/admin/categories.php" class="<?= strpos($_SERVER['PHP_SELF'], '/admin/categories') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-tags"></i></span> Categories
            </a>
            <a href="/pharmacy/admin/payment_settings.php" class="<?= strpos($_SERVER['PHP_SELF'], '/admin/payment') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-credit-card"></i></span> Payment Settings
            </a>
            <a href="/pharmacy/admin/shop_settings.php" class="<?= strpos($_SERVER['PHP_SELF'], '/admin/shop') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-store"></i></span> Shop Settings
            </a>
            <a href="/pharmacy/admin/settings.php" class="<?= strpos($_SERVER['PHP_SELF'], '/admin/settings') !== false ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-cog"></i></span> Settings
            </a>
        </nav>
    </div>
    <?php endif; ?>

    <div style="padding: 20px; margin-top: auto;">
        <a href="/pharmacy/auth/logout.php" style="display:flex;align-items:center;gap:10px;color:rgba(255,255,255,0.6);text-decoration:none;font-size:13px;padding:10px;border-radius:8px;transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>

<!-- MAIN WRAPPER -->
<div class="main-wrapper">
    <!-- TOPBAR -->
    <header class="topbar">
        <button onclick="document.getElementById('sidebar').classList.toggle('open')" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);display:none;" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="topbar-title">
            <?= PAGE_TITLE ?>
            <?php if (PAGE_SUBTITLE): ?>
            <span><?= PAGE_SUBTITLE ?></span>
            <?php endif; ?>
        </div>
        <div class="topbar-actions">
            <?php
        $notifCount = 0;
        try {
            $nStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE is_read=0 AND (user_id=? OR user_id IS NULL) AND (branch_id=? OR branch_id IS NULL)");
            $nStmt->execute([$_SESSION['user_id'], $_SESSION['branch_id'] ?? null]);
            $notifCount = $nStmt->fetchColumn();
        } catch(Exception $e) {}
        ?>
        <div style="position:relative;">
            <a href="/pharmacy/notifications.php" class="topbar-btn" title="Notifications" style="position:relative;">
                <i class="fas fa-bell"></i>
                <?php if ($notifCount > 0): ?>
                <span style="position:absolute;top:-2px;right:-2px;background:var(--danger);color:#fff;font-size:9px;font-weight:700;padding:1px 5px;border-radius:10px;min-width:16px;text-align:center;"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
                <?php endif; ?>
            </a>
        </div>
            <div class="user-menu" onclick="window.location='/pharmacy/auth/profile.php'">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></div>
                <div class="user-info">
                    <div class="name"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></div>
                    <div class="role"><?= ucfirst(str_replace('_', ' ', $_SESSION['role'] ?? '')) ?></div>
                </div>
            </div>
        </div>
    </header>
    <!-- PAGE CONTENT -->
    <main class="page-content">
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<script>
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('show');
}
document.getElementById('menuToggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
});
</script>
