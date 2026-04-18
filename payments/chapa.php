<?php
/**
 * Chapa Payment Gateway - Initialize & Handle
 */
require_once '../config/db.php';
requireLogin();

// ---- CALLBACK from Chapa (GET after payment) ----
if (isset($_GET['tx_ref'])) {
    $txRef = trim($_GET['tx_ref']);

    $txStmt = $pdo->prepare("SELECT * FROM payment_transactions WHERE tx_ref=?");
    $txStmt->execute([$txRef]);
    $tx = $txStmt->fetch();

    if ($tx) {
        $settingStmt = $pdo->prepare("SELECT secret_key FROM payment_settings WHERE gateway='chapa'");
        $settingStmt->execute();
        $setting   = $settingStmt->fetch();
        $secretKey = ($setting && $setting['secret_key']) ? $setting['secret_key'] : CHAPA_TEST_SECRET;

        $verify = chapaVerify($txRef, $secretKey);

        $verifyStatus = '';
        if (is_array($verify) && isset($verify['data']['status'])) {
            $verifyStatus = $verify['data']['status'];
        }

        if (is_array($verify) && isset($verify['status']) && $verify['status'] === 'success' && $verifyStatus === 'success') {
            $pdo->prepare("UPDATE payment_transactions SET status='success', gateway_response=?, verified_at=NOW() WHERE tx_ref=?")
                ->execute([json_encode($verify), $txRef]);

            if ($tx['sale_id']) {
                // Mark sale completed
                $pdo->prepare("UPDATE sales SET status='completed', paid_amount=total_amount, payment_method='chapa' WHERE id=?")
                    ->execute([$tx['sale_id']]);

                // Deduct stock now that payment is confirmed
                $items = $pdo->prepare("SELECT si.medicine_id, si.quantity, sa.branch_id FROM sale_items si JOIN sales sa ON si.sale_id=sa.id WHERE si.sale_id=?");
                $items->execute([$tx['sale_id']]);
                foreach ($items->fetchAll() as $item) {
                    $pdo->prepare("UPDATE stock SET quantity = quantity - ? WHERE medicine_id=? AND branch_id=? AND quantity >= ?")
                        ->execute([$item['quantity'], $item['medicine_id'], $item['branch_id'], $item['quantity']]);
                }
            }

            logActivity($pdo, 'Chapa payment verified: ' . $txRef, 'payments');
            createNotification($pdo, 'payment', 'Payment Confirmed',
                'Chapa payment of ' . formatCurrency($tx['amount']) . ' confirmed. Ref: ' . $txRef,
                $tx['branch_id'], $tx['user_id'],
                '/pharmacy/sales/view.php?id=' . $tx['sale_id']
            );

            header('Location: /pharmacy/sales/view.php?id=' . (int)$tx['sale_id'] . '&payment=success');
            exit;
        } else {
            $pdo->prepare("UPDATE payment_transactions SET status='failed', gateway_response=? WHERE tx_ref=?")
                ->execute([json_encode($verify), $txRef]);
            header('Location: /pharmacy/payments/status.php?tx_ref=' . urlencode($txRef) . '&status=failed');
            exit;
        }
    }

    header('Location: /pharmacy/dashboard.php');
    exit;
}

// ---- INITIALIZE PAYMENT (POST) ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pharmacy/sales/new.php');
    exit;
}

$saleId        = (int)($_POST['sale_id'] ?? 0);
$amount        = (float)($_POST['amount'] ?? 0);
$customerName  = trim($_POST['customer_name'] ?? 'Customer');
$customerPhone = trim($_POST['customer_phone'] ?? '');

// Always use a known-good email — Chapa rejects emails starting with numbers
// or containing unusual characters. Use a fixed valid address.
$customerEmail = 'pharmacy.customer@gmail.com';

// Validate phone — Chapa needs 10 digits starting with 09 or 07
if (!$customerPhone || !preg_match('/^(09|07)\d{8}$/', $customerPhone)) {
    $customerPhone = '0911000000';
}

if (!$saleId || $amount <= 0) {
    header('Location: /pharmacy/sales/new.php?error=invalid');
    exit;
}

// Load Chapa settings from DB
$settingStmt = $pdo->prepare("SELECT * FROM payment_settings WHERE gateway='chapa'");
$settingStmt->execute();
$setting = $settingStmt->fetch();

if (!$setting || !$setting['is_enabled']) {
    header('Location: /pharmacy/sales/view.php?id=' . $saleId . '&payment_error=' . urlencode('Chapa payment is not enabled. Go to Admin → Payment Settings.'));
    exit;
}

$secretKey = ($setting['secret_key'] && trim($setting['secret_key']) !== '') ? trim($setting['secret_key']) : CHAPA_TEST_SECRET;

$txRef     = 'PHARMA-' . time() . '-' . strtoupper(bin2hex(random_bytes(6)));
$nameParts = explode(' ', $customerName, 2);
$firstName = !empty($nameParts[0]) ? $nameParts[0] : 'Customer';
$lastName  = !empty($nameParts[1]) ? $nameParts[1] : 'Customer';

$baseUrl     = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$callbackUrl = $baseUrl . '/pharmacy/payments/chapa.php?tx_ref=' . $txRef;
$returnUrl   = $baseUrl . '/pharmacy/payments/chapa.php?tx_ref=' . $txRef;

// Cancel any previous pending transaction for this sale to avoid duplicate tx_ref conflicts
$pdo->prepare("UPDATE payment_transactions SET status='cancelled' WHERE sale_id=? AND status='pending' AND payment_gateway='chapa'")
    ->execute([$saleId]);

// Save pending transaction record
$pdo->prepare("
    INSERT INTO payment_transactions
        (sale_id, branch_id, user_id, tx_ref, amount, payment_gateway, customer_name, customer_email, customer_phone, status)
    VALUES (?,?,?,?,?,'chapa',?,?,?,'pending')
")->execute([
    $saleId,
    getUserBranchId() ?? 1,
    $_SESSION['user_id'],
    $txRef,
    $amount,
    $customerName,
    $customerEmail,
    $customerPhone
]);

// Call Chapa API
$response = chapaInitialize(
    $amount,
    $customerEmail,
    $firstName,
    $lastName,
    $customerPhone,
    $txRef,
    $callbackUrl,
    $returnUrl,
    $secretKey
);

// Handle null response (curl failed / no internet)
if (!is_array($response)) {
    $pdo->prepare("UPDATE payment_transactions SET status='failed', gateway_response=? WHERE tx_ref=?")
        ->execute(['curl_failed_or_no_response', $txRef]);
    header('Location: /pharmacy/sales/view.php?id=' . $saleId . '&payment_error=' . urlencode('Could not connect to Chapa. Check internet connection.'));
    exit;
}

// Success — redirect to Chapa checkout
if (isset($response['status']) && $response['status'] === 'success' && !empty($response['data']['checkout_url'])) {
    $pdo->prepare("UPDATE payment_transactions SET checkout_url=? WHERE tx_ref=?")
        ->execute([$response['data']['checkout_url'], $txRef]);

    header('Location: ' . $response['data']['checkout_url']);
    exit;
}

// Failed — build readable error message
$pdo->prepare("UPDATE payment_transactions SET status='failed', gateway_response=? WHERE tx_ref=?")
    ->execute([json_encode($response), $txRef]);

$rawMsg = $response['message'] ?? 'Payment initialization failed.';
if (is_array($rawMsg)) {
    $parts = array();
    foreach ($rawMsg as $field => $msgs) {
        if (is_array($msgs)) {
            $parts[] = $field . ': ' . implode(', ', $msgs);
        } else {
            $parts[] = (string)$msgs;
        }
    }
    $errorMsg = implode(' | ', $parts);
} else {
    $errorMsg = (string)$rawMsg;
}

header('Location: /pharmacy/sales/view.php?id=' . $saleId . '&payment_error=' . urlencode($errorMsg));
exit;
