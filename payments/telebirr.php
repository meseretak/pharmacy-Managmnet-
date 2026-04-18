<?php
/**
 * Telebirr H5 Web Payment Integration
 * Requires: app_id, app_key, public_key (RSA), notify_url from Ethio Telecom
 * Contact: telebirr@ethiotelecom.et to get credentials
 */
require_once '../config/db.php';
requireLogin();

// ---- NOTIFY CALLBACK (POST from Telebirr server) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['notify'])) {
    $rawBody = file_get_contents('php://input');
    $data    = json_decode($rawBody, true);

    if ($data && isset($data['outTradeNo'])) {
        $txRef = $data['outTradeNo'];
        $txStmt = $pdo->prepare("SELECT * FROM payment_transactions WHERE tx_ref=?");
        $txStmt->execute([$txRef]);
        $tx = $txStmt->fetch();

        if ($tx && isset($data['tradeStatus']) && $data['tradeStatus'] === 'TRADE_SUCCESS') {
            $pdo->prepare("UPDATE payment_transactions SET status='success', gateway_response=?, verified_at=NOW() WHERE tx_ref=?")
                ->execute([json_encode($data), $txRef]);

            if ($tx['sale_id']) {
                $pdo->prepare("UPDATE sales SET status='completed', payment_method='mobile_money' WHERE id=?")->execute([$tx['sale_id']]);
            }

            createNotification($pdo, 'payment', 'Telebirr Payment Confirmed', 'Payment of ' . formatCurrency($tx['amount']) . ' confirmed via Telebirr.', $tx['branch_id'], null, '/pharmacy/sales/view.php?id=' . $tx['sale_id']);
        }
    }
    echo json_encode(['code' => '0', 'msg' => 'success']);
    exit;
}

// ---- RETURN URL (GET after payment) ----
if (isset($_GET['tx_ref'])) {
    $txRef = $_GET['tx_ref'];
    $txStmt = $pdo->prepare("SELECT * FROM payment_transactions WHERE tx_ref=?");
    $txStmt->execute([$txRef]);
    $tx = $txStmt->fetch();

    if ($tx && $tx['status'] === 'success') {
        header('Location: /pharmacy/sales/view.php?id=' . $tx['sale_id'] . '&payment=success');
    } else {
        header('Location: /pharmacy/payments/status.php?tx_ref=' . urlencode($txRef));
    }
    exit;
}

// ---- INITIALIZE PAYMENT (POST) ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pharmacy/sales/new.php');
    exit;
}

$saleId       = (int)($_POST['sale_id'] ?? 0);
$amount       = (float)($_POST['amount'] ?? 0);
$customerName = trim($_POST['customer_name'] ?? 'Customer');
$customerPhone = trim($_POST['customer_phone'] ?? '');

if (!$saleId || $amount <= 0) {
    header('Location: /pharmacy/sales/new.php?error=invalid');
    exit;
}

// Get Telebirr settings
$settingStmt = $pdo->prepare("SELECT * FROM payment_settings WHERE gateway='telebirr'");
$settingStmt->execute();
$setting = $settingStmt->fetch();

if (!$setting || !$setting['is_enabled']) {
    header('Location: /pharmacy/sales/view.php?id=' . $saleId . '&error=telebirr_disabled');
    exit;
}

$extraConfig = json_decode($setting['extra_config'] ?? '{}', true);
$appId       = $extraConfig['app_id'] ?? '';
$appKey      = $setting['secret_key'] ?? '';
$publicKey   = $setting['public_key'] ?? '';
$shortCode   = $extraConfig['short_code'] ?? '';

if (!$appId || !$appKey) {
    header('Location: /pharmacy/sales/view.php?id=' . $saleId . '&error=telebirr_not_configured');
    exit;
}

$txRef     = generateTxRef();
$timestamp = date('YmdHis');
$baseUrl   = 'http://' . $_SERVER['HTTP_HOST'];
$notifyUrl = $baseUrl . '/pharmacy/payments/telebirr.php?notify=1';
$returnUrl = $baseUrl . '/pharmacy/payments/telebirr.php?tx_ref=' . $txRef;

// Build Telebirr payload
$rawRequest = [
    'appId'       => $appId,
    'appKey'      => $appKey,
    'nonce'       => bin2hex(random_bytes(8)),
    'notifyUrl'   => $notifyUrl,
    'outTradeNo'  => $txRef,
    'returnUrl'   => $returnUrl,
    'shortCode'   => $shortCode,
    'subject'     => APP_NAME . ' Payment',
    'timeoutExpress' => '30',
    'timestamp'   => $timestamp,
    'totalAmount' => number_format($amount, 2, '.', ''),
    'receiveName' => APP_NAME,
];

// Sign: sort keys, concat values, SHA256
ksort($rawRequest);
$signStr = implode('', array_values($rawRequest));
$sign    = strtoupper(hash('sha256', $signStr));

// Encrypt ussd payload with RSA public key (Telebirr requirement)
$ussdPayload = json_encode([
    'appId'          => $appId,
    'timestamp'      => $timestamp,
    'nonce'          => $rawRequest['nonce'],
    'outTradeNo'     => $txRef,
    'subject'        => $rawRequest['subject'],
    'totalAmount'    => $rawRequest['totalAmount'],
    'shortCode'      => $shortCode,
    'notifyUrl'      => $notifyUrl,
    'returnUrl'      => $returnUrl,
    'timeoutExpress' => '30',
    'receiveName'    => APP_NAME,
]);

$encryptedUssd = '';
if ($publicKey) {
    $pubKeyResource = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n" . chunk_split($publicKey, 64, "\n") . "-----END PUBLIC KEY-----");
    if ($pubKeyResource) {
        openssl_public_encrypt($ussdPayload, $encryptedUssd, $pubKeyResource);
        $encryptedUssd = base64_encode($encryptedUssd);
    }
}

$apiPayload = [
    'appid'  => $appId,
    'sign'   => $sign,
    'ussd'   => $encryptedUssd ?: base64_encode($ussdPayload),
];

// Determine API URL (test vs live)
$apiUrl = ($setting['is_test_mode'])
    ? 'https://196.188.120.3:38443/payment/v1/merchant/preOrder'
    : 'https://196.188.120.3:38443/payment/v1/merchant/preOrder';

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($apiPayload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);

// Save transaction
$pdo->prepare("INSERT INTO payment_transactions (sale_id, branch_id, user_id, tx_ref, amount, payment_gateway, customer_name, customer_phone, status) VALUES (?,?,?,?,?,'telebirr',?,?,'pending')")
    ->execute([$saleId, getUserBranchId() ?? 1, $_SESSION['user_id'], $txRef, $amount, $customerName, $customerPhone]);

if (isset($result['code']) && $result['code'] === '0' && isset($result['data']['toPayUrl'])) {
    $pdo->prepare("UPDATE payment_transactions SET checkout_url=? WHERE tx_ref=?")
        ->execute([$result['data']['toPayUrl'], $txRef]);
    header('Location: ' . $result['data']['toPayUrl']);
    exit;
} else {
    $pdo->prepare("UPDATE payment_transactions SET status='failed', gateway_response=? WHERE tx_ref=?")
        ->execute([json_encode($result), $txRef]);
    $rawMsg   = $result['msg'] ?? 'Telebirr initialization failed. Ensure credentials are configured.';
    $errorMsg = is_array($rawMsg) ? implode(', ', $rawMsg) : (string)$rawMsg;
    header('Location: /pharmacy/sales/view.php?id=' . $saleId . '&payment_error=' . urlencode($errorMsg));
    exit;
}
