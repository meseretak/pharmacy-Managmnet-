<?php
require_once 'config/db.php';
requireLogin();
define('PAGE_TITLE', 'Notifications');
define('PAGE_SUBTITLE', 'System alerts and messages');

// Mark all as read
if (isset($_GET['mark_read'])) {
    markNotificationsRead($pdo, $_SESSION['user_id']);
    header('Location: /pharmacy/notifications.php');
    exit;
}

$branchId = $_SESSION['branch_id'] ?? null;
$stmt = $pdo->prepare("
    SELECT * FROM notifications
    WHERE (user_id=? OR user_id IS NULL) AND (branch_id=? OR branch_id IS NULL)
    ORDER BY created_at DESC LIMIT 100
");
$stmt->execute([$_SESSION['user_id'], $branchId]);
$notifications = $stmt->fetchAll();

$unreadCount = count(array_filter($notifications, fn($n) => !$n['is_read']));

require_once 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-bell" style="color:var(--primary)"></i> Notifications <?php if($unreadCount>0):?><span class="badge" style="background:var(--danger);color:#fff;"><?= $unreadCount ?> new</span><?php endif;?></div>
        <?php if ($unreadCount > 0): ?>
        <a href="?mark_read=1" class="btn btn-outline btn-sm"><i class="fas fa-check-double"></i> Mark All Read</a>
        <?php endif; ?>
    </div>
    <?php if (empty($notifications)): ?>
    <div class="card-body"><div class="empty-state"><div class="empty-icon">🔔</div><p>No notifications</p></div></div>
    <?php else: ?>
    <div>
        <?php foreach ($notifications as $n): ?>
        <?php
        $icons = ['low_stock'=>'📦','expiry'=>'📅','sale'=>'💰','system'=>'⚙️','payment'=>'💳'];
        $colors = ['low_stock'=>'badge-warning','expiry'=>'badge-danger','sale'=>'badge-success','system'=>'badge-info','payment'=>'badge-info'];
        ?>
        <div style="display:flex;align-items:flex-start;gap:15px;padding:15px 22px;border-bottom:1px solid #f0f0f0;background:<?= !$n['is_read'] ? 'var(--primary-light)' : '#fff' ?>;">
            <div style="font-size:24px;flex-shrink:0;"><?= $icons[$n['type']] ?? '🔔' ?></div>
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
                    <strong style="font-size:14px;"><?= htmlspecialchars($n['title']) ?></strong>
                    <span class="badge <?= $colors[$n['type']] ?? 'badge-info' ?>"><?= ucfirst($n['type']) ?></span>
                    <?php if (!$n['is_read']): ?><span class="badge badge-success">New</span><?php endif; ?>
                </div>
                <div style="font-size:13px;color:var(--text-muted);"><?= htmlspecialchars($n['message']) ?></div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:5px;"><?= date('d M Y H:i', strtotime($n['created_at'])) ?></div>
            </div>
            <?php if ($n['link']): ?>
            <a href="<?= htmlspecialchars($n['link']) ?>" class="btn btn-outline btn-sm">View</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
