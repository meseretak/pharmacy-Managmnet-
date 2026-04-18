<?php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3301');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pharmacy_mgmt');
define('APP_NAME', 'PharmaCare Pro');
define('APP_VERSION', '1.0.0');
define('CURRENCY', 'ETB');
define('LOW_STOCK_DEFAULT', 20);

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}

session_start();

// Load shop settings into global array
$GLOBALS['shop'] = [];
try {
    $__s = $pdo->query("SELECT setting_key, setting_value FROM shop_settings WHERE branch_id IS NULL");
    foreach ($__s->fetchAll() as $row) {
        $GLOBALS['shop'][$row['setting_key']] = $row['setting_value'];
    }
} catch(Exception $e) {}

function shopSetting($key, $default = '') {
    return $GLOBALS['shop'][$key] ?? $default;
}

function isShopOpen($branchId = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT is_open, opening_time, closing_time FROM branches WHERE id=?");
        $stmt->execute([$branchId ?? 1]);
        $b = $stmt->fetch();
        if (!$b || !$b['is_open']) return false;
        $now = date('H:i:s');
        return $now >= $b['opening_time'] && $now <= $b['closing_time'];
    } catch(Exception $e) { return true; }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /pharmacy/auth/login.php');
        exit;
    }
}

function isSuperAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
}

function isBranchManager() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['super_admin', 'branch_manager']);
}

function getUserBranchId() {
    return $_SESSION['branch_id'] ?? null;
}

function logActivity($pdo, $action, $module) {
    if (!isset($_SESSION['user_id'])) return;
    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, module, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $action, $module, $_SERVER['REMOTE_ADDR'] ?? '']);
}

function formatCurrency($amount) {
    return CURRENCY . ' ' . number_format($amount, 2);
}

function generateInvoiceNumber() {
    return 'INV-' . strtoupper(uniqid());
}

function generateTxRef() {
    return 'TX-' . strtoupper(bin2hex(random_bytes(8)));
}

function createNotification($pdo, $type, $title, $message, $branchId = null, $userId = null, $link = '') {
    try {
        $pdo->prepare("INSERT INTO notifications (user_id, branch_id, type, title, message, link) VALUES (?,?,?,?,?,?)")
            ->execute([$userId, $branchId, $type, $title, $message, $link]);
    } catch(Exception $e) {}
}

function getUnreadNotifications($pdo, $userId, $branchId) {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications
        WHERE is_read=0 AND (user_id=? OR user_id IS NULL) AND (branch_id=? OR branch_id IS NULL)
        ORDER BY created_at DESC LIMIT 20
    ");
    $stmt->execute([$userId, $branchId]);
    return $stmt->fetchAll();
}

function markNotificationsRead($pdo, $userId) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? OR user_id IS NULL")->execute([$userId]);
}

function checkAndCreateAlerts($pdo, $branchId = null) {
    $branchCond = $branchId ? "AND s.branch_id = $branchId" : '';
    // Low stock alerts
    $lowStock = $pdo->query("SELECT m.name, s.quantity, s.branch_id FROM stock s JOIN medicines m ON s.medicine_id=m.id WHERE s.quantity <= s.low_stock_threshold AND s.quantity > 0 $branchCond LIMIT 20")->fetchAll();
    foreach ($lowStock as $item) {
        createNotification($pdo, 'low_stock', 'Low Stock Alert', $item['name'] . ' has only ' . $item['quantity'] . ' units left.', $item['branch_id'], null, '/pharmacy/stock/index.php?filter=low');
    }
    // Expiry alerts (within 30 days)
    $expiring = $pdo->query("SELECT m.name, s.expiry_date, s.branch_id FROM stock s JOIN medicines m ON s.medicine_id=m.id WHERE s.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) $branchCond LIMIT 20")->fetchAll();
    foreach ($expiring as $item) {
        createNotification($pdo, 'expiry', 'Expiry Warning', $item['name'] . ' expires on ' . date('d M Y', strtotime($item['expiry_date'])), $item['branch_id'], null, '/pharmacy/reports/expiry.php');
    }
}

// Chapa API
define('CHAPA_BASE_URL', 'https://api.chapa.co/v1');
define('CHAPA_TEST_SECRET', getenv('CHAPA_SECRET_KEY') ?: '');

function chapaInitialize($amount, $email, $firstName, $lastName, $phone, $txRef, $callbackUrl, $returnUrl, $secretKey = null) {
    $key = $secretKey ?: CHAPA_TEST_SECRET;
    $data = [
        'amount'        => number_format((float)$amount, 2, '.', ''),
        'currency'      => 'ETB',
        'email'         => $email,
        'first_name'    => $firstName,
        'last_name'     => $lastName,
        'phone_number'  => $phone ?: '0911000000',
        'tx_ref'        => $txRef,
        'callback_url'  => $callbackUrl,
        'return_url'    => $returnUrl,
        'customization' => ['title' => APP_NAME, 'description' => 'Pharmacy Payment']
    ];
    $ch = curl_init(CHAPA_BASE_URL . '/transaction/initialize');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    if ($curlErr || !$response) return null;
    return json_decode($response, true);
}

function chapaVerify($txRef, $secretKey = null) {
    $key = $secretKey ?: CHAPA_TEST_SECRET;
    $ch = curl_init(CHAPA_BASE_URL . '/transaction/verify/' . urlencode($txRef));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    if ($curlErr || !$response) return null;
    return json_decode($response, true);
}
