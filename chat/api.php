<?php
require_once '../config/db.php';
requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userId = (int)$_SESSION['user_id'];
$myBranchId = getUserBranchId();

// Update online status
try {
    $pdo->prepare("INSERT INTO user_online_status (user_id, is_online, last_seen) VALUES (?,1,NOW()) ON DUPLICATE KEY UPDATE is_online=1, last_seen=NOW()")
        ->execute([$userId]);
} catch(Exception $e) {}

// ---- GET ROOMS ----
if ($action === 'get_rooms') {
    $rooms = $pdo->prepare("
        SELECT cr.id, cr.name, cr.type, cr.branch_id,
               (SELECT cm2.message FROM chat_messages cm2 WHERE cm2.room_id=cr.id AND cm2.is_deleted=0 ORDER BY cm2.created_at DESC LIMIT 1) AS last_message,
               (SELECT cm2.created_at FROM chat_messages cm2 WHERE cm2.room_id=cr.id AND cm2.is_deleted=0 ORDER BY cm2.created_at DESC LIMIT 1) AS last_message_time,
               (SELECT u2.name FROM chat_messages cm2 JOIN users u2 ON cm2.sender_id=u2.id WHERE cm2.room_id=cr.id AND cm2.is_deleted=0 ORDER BY cm2.created_at DESC LIMIT 1) AS last_sender,
               (SELECT COUNT(*) FROM chat_messages cm2 WHERE cm2.room_id=cr.id AND cm2.is_deleted=0 AND cm2.created_at > COALESCE(crm.last_read_at,'2000-01-01') AND cm2.sender_id != ?) AS unread_count
        FROM chat_rooms cr
        JOIN chat_room_members crm ON crm.room_id=cr.id AND crm.user_id=?
        ORDER BY last_message_time DESC, cr.id ASC
    ");
    $rooms->execute([$userId, $userId]);
    echo json_encode(['success' => true, 'rooms' => $rooms->fetchAll()]);
    exit;
}

// ---- GET MESSAGES ----
if ($action === 'get_messages') {
    $roomId = (int)($_GET['room_id'] ?? 0);
    $since  = $_GET['since'] ?? '2000-01-01 00:00:00';

    // Check membership
    $member = $pdo->prepare("SELECT id FROM chat_room_members WHERE room_id=? AND user_id=?");
    $member->execute([$roomId, $userId]);
    if (!$member->fetch()) { echo json_encode(['success'=>false,'error'=>'Not a member']); exit; }

    $msgs = $pdo->prepare("
        SELECT cm.id, cm.message, cm.msg_type, cm.created_at, cm.sender_id,
               cm.file_path, cm.file_name, cm.file_size,
               u.name AS sender_name, u.role_id,
               r.name AS sender_role
        FROM chat_messages cm
        JOIN users u ON cm.sender_id=u.id
        JOIN roles r ON u.role_id=r.id
        WHERE cm.room_id=? AND cm.is_deleted=0 AND cm.created_at > ?
        ORDER BY cm.created_at ASC
        LIMIT 100
    ");
    $msgs->execute([$roomId, $since]);
    $messages = $msgs->fetchAll();

    // Mark as read
    $pdo->prepare("UPDATE chat_room_members SET last_read_at=NOW() WHERE room_id=? AND user_id=?")->execute([$roomId, $userId]);

    // Get room info
    $room = $pdo->prepare("SELECT cr.*, (SELECT COUNT(*) FROM chat_room_members WHERE room_id=cr.id) AS member_count FROM chat_rooms cr WHERE cr.id=?");
    $room->execute([$roomId]);
    $room = $room->fetch();

    echo json_encode(['success' => true, 'messages' => $messages, 'room' => $room]);
    exit;
}

// ---- SEND MESSAGE ----
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $roomId  = (int)($_POST['room_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if (!$roomId) { echo json_encode(['success'=>false,'error'=>'Missing room']); exit; }

    $member = $pdo->prepare("SELECT id FROM chat_room_members WHERE room_id=? AND user_id=?");
    $member->execute([$roomId, $userId]);
    if (!$member->fetch()) { echo json_encode(['success'=>false,'error'=>'Not a member']); exit; }

    $filePath = null; $fileName = null; $fileSize = null; $fileType = 'text'; $msgType = 'text';

    // Handle file upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $maxSize   = 5 * 1024 * 1024; // 5MB limit
        $allowedTypes = ['image/jpeg','image/png','image/gif','image/webp','application/pdf','text/plain'];
        $uploadedSize = $_FILES['attachment']['size'];
        $uploadedMime = mime_content_type($_FILES['attachment']['tmp_name']);

        if ($uploadedSize > $maxSize) {
            echo json_encode(['success'=>false,'error'=>'File too large. Maximum size is 5MB.']); exit;
        }
        if (!in_array($uploadedMime, $allowedTypes)) {
            echo json_encode(['success'=>false,'error'=>'File type not allowed. Use images or PDF.']); exit;
        }

        $ext      = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $safeName = time() . '_' . $userId . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $safeName)) {
            $filePath  = '/pharmacy/chat/uploads/' . $safeName;
            $fileName  = htmlspecialchars($_FILES['attachment']['name']);
            $fileSize  = $uploadedSize;
            $fileType  = strpos($uploadedMime, 'image/') === 0 ? 'image' : 'file';
            $msgType   = $fileType;
            if (!$message) $message = $fileName; // use filename as message if no text
        } else {
            echo json_encode(['success'=>false,'error'=>'Upload failed.']); exit;
        }
    }

    if (!$message && !$filePath) { echo json_encode(['success'=>false,'error'=>'Empty message']); exit; }

    $pdo->prepare("INSERT INTO chat_messages (room_id, sender_id, message, msg_type, file_path, file_name, file_size, file_type) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$roomId, $userId, $message, $msgType, $filePath, $fileName, $fileSize, $fileType]);
    $msgId = $pdo->lastInsertId();

    $msg = $pdo->prepare("SELECT cm.*, u.name AS sender_name, r.name AS sender_role FROM chat_messages cm JOIN users u ON cm.sender_id=u.id JOIN roles r ON u.role_id=r.id WHERE cm.id=?");
    $msg->execute([$msgId]);
    echo json_encode(['success' => true, 'message' => $msg->fetch()]);
    exit;
}

// ---- GET USERS (for new chat / search) ----
if ($action === 'get_users') {
    $search = trim($_GET['search'] ?? '');
    $params = ['%' . $search . '%', $userId];
    $branchCond = '';
    if (!isSuperAdmin() && $myBranchId) {
        $branchCond = 'AND (u.branch_id=? OR u.branch_id IS NULL)';
        $params[] = $myBranchId;
    }
    $users = $pdo->prepare("
        SELECT u.id, u.name, r.name AS role, u.branch_id, b.name AS branch_name,
               COALESCE(uos.is_online, 0) AS is_online,
               COALESCE(uos.last_seen, '2000-01-01') AS last_seen
        FROM users u
        JOIN roles r ON u.role_id=r.id
        LEFT JOIN branches b ON u.branch_id=b.id
        LEFT JOIN user_online_status uos ON uos.user_id=u.id
        WHERE u.status='active' AND u.name LIKE ? AND u.id != ? $branchCond
        ORDER BY uos.is_online DESC, u.name ASC
        LIMIT 20
    ");
    $users->execute($params);
    echo json_encode(['success' => true, 'users' => $users->fetchAll()]);
    exit;
}

// ---- START DIRECT CHAT ----
if ($action === 'start_direct' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetId = (int)($_POST['target_user_id'] ?? 0);
    if (!$targetId) { echo json_encode(['success'=>false,'error'=>'Invalid user']); exit; }

    $target = $pdo->prepare("SELECT name FROM users WHERE id=?");
    $target->execute([$targetId]);
    $target = $target->fetch();
    if (!$target) { echo json_encode(['success'=>false,'error'=>'User not found']); exit; }

    // Check if direct room already exists between these two users
    $existing = $pdo->prepare("
        SELECT cr.id FROM chat_rooms cr
        JOIN chat_room_members m1 ON m1.room_id=cr.id AND m1.user_id=?
        JOIN chat_room_members m2 ON m2.room_id=cr.id AND m2.user_id=?
        WHERE cr.type='direct'
        LIMIT 1
    ");
    $existing->execute([$userId, $targetId]);
    $existing = $existing->fetch();

    if ($existing) {
        echo json_encode(['success' => true, 'room_id' => $existing['id']]);
        exit;
    }

    // Create new direct room
    $myName = $_SESSION['user_name'];
    $pdo->prepare("INSERT INTO chat_rooms (name, type, created_by) VALUES (?,?,?)")->execute([$myName . ' & ' . $target['name'], 'direct', $userId]);
    $roomId = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO chat_room_members (room_id, user_id) VALUES (?,?),(?,?)")->execute([$roomId, $userId, $roomId, $targetId]);

    echo json_encode(['success' => true, 'room_id' => $roomId]);
    exit;
}

// ---- CREATE GROUP ----
if ($action === 'create_group' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $members = json_decode($_POST['members'] ?? '[]', true);
    if (!$name) { echo json_encode(['success'=>false,'error'=>'Name required']); exit; }

    $pdo->prepare("INSERT INTO chat_rooms (name, type, created_by) VALUES (?,?,?)")->execute([$name, 'group', $userId]);
    $roomId = $pdo->lastInsertId();

    // Add creator + members
    $allMembers = array_unique(array_merge([$userId], array_map('intval', $members)));
    foreach ($allMembers as $mid) {
        $pdo->prepare("INSERT IGNORE INTO chat_room_members (room_id, user_id) VALUES (?,?)")->execute([$roomId, $mid]);
    }

    echo json_encode(['success' => true, 'room_id' => $roomId]);
    exit;
}

// ---- GET ONLINE USERS ----
if ($action === 'online_users') {
    $pdo->prepare("UPDATE user_online_status SET is_online=0 WHERE last_seen < DATE_SUB(NOW(), INTERVAL 2 MINUTE)")->execute();
    $online = $pdo->prepare("
        SELECT u.id, u.name, r.name AS role, uos.last_seen
        FROM user_online_status uos
        JOIN users u ON uos.user_id=u.id
        JOIN roles r ON u.role_id=r.id
        WHERE uos.is_online=1 AND u.id != ?
        ORDER BY u.name
    ");
    $online->execute([$userId]);
    echo json_encode(['success' => true, 'users' => $online->fetchAll()]);
    exit;
}

// ---- DELETE MESSAGE ----
if ($action === 'delete_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $msgId = (int)($_POST['msg_id'] ?? 0);
    $check = $pdo->prepare("SELECT sender_id FROM chat_messages WHERE id=?");
    $check->execute([$msgId]);
    $msg = $check->fetch();
    if ($msg && ($msg['sender_id'] == $userId || isSuperAdmin())) {
        $pdo->prepare("UPDATE chat_messages SET is_deleted=1 WHERE id=?")->execute([$msgId]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Not allowed']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
