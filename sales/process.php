<?php
require_once '../config/db.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pharmacy/sales/new.php');
    exit;
}

$branchId      = getUserBranchId() ?? 1;
$cartData      = json_decode($_POST['cart_data'] ?? '[]', true);
$customerName  = trim($_POST['customer_name'] ?? 'Walk-in Customer') ?: 'Walk-in Customer';
$customerPhone = trim($_POST['customer_phone'] ?? '');
$discount      = max(0, (float)($_POST['discount'] ?? 0));
$paymentMethod = trim($_POST['payment_method'] ?? 'cash');
if (!in_array($paymentMethod, ['cash','card','chapa','telebirr','mobile_money'])) {
    $paymentMethod = 'cash';
}

if (empty($cartData)) {
    header('Location: /pharmacy/sales/new.php?error=empty');
    exit;
}

// Cash and Card complete immediately — only chapa/telebirr go to payment gateway
$onlinePayment = in_array($paymentMethod, ['chapa', 'telebirr']);

try {
    $pdo->beginTransaction();

    $subtotal       = 0;
    $validatedItems = [];

    foreach ($cartData as $item) {
        $medId = (int)$item['id'];
        $qty   = (int)$item['quantity'];

        $stock = $pdo->prepare("SELECT * FROM stock WHERE medicine_id=? AND branch_id=? AND quantity >= ? LIMIT 1");
        $stock->execute([$medId, $branchId, $qty]);
        $stockRow = $stock->fetch();

        if (!$stockRow) {
            $pdo->rollBack();
            header('Location: /pharmacy/sales/new.php?error=stock&med=' . $medId);
            exit;
        }

        $unitPrice    = (float)$stockRow['selling_price'];
        $itemSubtotal = $unitPrice * $qty;
        $subtotal    += $itemSubtotal;

        $validatedItems[] = [
            'medicine_id' => $medId,
            'stock_id'    => $stockRow['id'],
            'quantity'    => $qty,
            'unit_price'  => $unitPrice,
            'subtotal'    => $itemSubtotal,
        ];
    }

    $total         = max(0, $subtotal - $discount);
    $invoiceNumber = generateInvoiceNumber();

    // For online payments: status = pending, paid_amount = 0
    // For cash/card:       status = completed, paid_amount = total
    $saleStatus  = $onlinePayment ? 'pending'   : 'completed';
    $paidAmount  = $onlinePayment ? 0           : $total;

    $stmt = $pdo->prepare("
        INSERT INTO sales
            (invoice_number, branch_id, user_id, customer_name, customer_phone,
             total_amount, discount, paid_amount, payment_method, status)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $invoiceNumber, $branchId, $_SESSION['user_id'],
        $customerName, $customerPhone,
        $total, $discount, $paidAmount, $paymentMethod, $saleStatus
    ]);
    $saleId = $pdo->lastInsertId();

    // Insert sale items
    foreach ($validatedItems as $item) {
        $pdo->prepare("INSERT INTO sale_items (sale_id, medicine_id, quantity, unit_price, subtotal) VALUES (?,?,?,?,?)")
            ->execute([$saleId, $item['medicine_id'], $item['quantity'], $item['unit_price'], $item['subtotal']]);
    }

    // Deduct stock immediately for cash/card
    // For online payments, stock is reserved but deducted only after payment confirmed
    if (!$onlinePayment) {
        foreach ($validatedItems as $item) {
            $pdo->prepare("UPDATE stock SET quantity = quantity - ? WHERE id=?")
                ->execute([$item['quantity'], $item['stock_id']]);
        }
    }

    $pdo->commit();

    // ---- CASH: complete immediately ----
    if (!$onlinePayment) {
        logActivity($pdo, 'Completed sale: ' . $invoiceNumber . ' Total: ' . $total, 'sales');
        header('Location: /pharmacy/sales/view.php?id=' . $saleId . '&success=1');
        exit;
    }

    // ---- NON-CASH: redirect to gateway or show pending invoice ----
    logActivity($pdo, 'Created pending sale: ' . $invoiceNumber . ' via ' . $paymentMethod, 'sales');

    if ($paymentMethod === 'chapa') {
        // Build Chapa payment URL directly here
        $settingStmt = $pdo->prepare("SELECT * FROM payment_settings WHERE gateway='chapa'");
        $settingStmt->execute();
        $setting   = $settingStmt->fetch();
        $secretKey = ($setting && !empty($setting['secret_key'])) ? $setting['secret_key'] : CHAPA_TEST_SECRET;

        $txRef       = 'PHARMA-' . time() . '-' . strtoupper(bin2hex(random_bytes(6)));
        $baseUrl     = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $callbackUrl = $baseUrl . '/pharmacy/payments/chapa.php?tx_ref=' . $txRef;
        $returnUrl   = $baseUrl . '/pharmacy/payments/chapa.php?tx_ref=' . $txRef;

        $nameParts = explode(' ', $customerName, 2);
        $firstName = $nameParts[0] ?: 'Customer';
        $lastName  = $nameParts[1] ?? 'Customer';
        $phone     = ($customerPhone && preg_match('/^(09|07)\d{8}$/', $customerPhone)) ? $customerPhone : '0911000000';

        // Save pending payment transaction
        $pdo->prepare("
            INSERT INTO payment_transactions
                (sale_id, branch_id, user_id, tx_ref, amount, payment_gateway, customer_name, customer_email, customer_phone, status)
            VALUES (?,?,?,?,?,'chapa',?,?,?,'pending')
        ")->execute([$saleId, $branchId, $_SESSION['user_id'], $txRef, $total, $customerName, 'pharmacy.customer@gmail.com', $phone]);

        // Call Chapa API
        $response = chapaInitialize($total, 'pharmacy.customer@gmail.com', $firstName, $lastName, $phone, $txRef, $callbackUrl, $returnUrl, $secretKey);

        if (is_array($response) && isset($response['status']) && $response['status'] === 'success' && !empty($response['data']['checkout_url'])) {
            $pdo->prepare("UPDATE payment_transactions SET checkout_url=? WHERE tx_ref=?")->execute([$response['data']['checkout_url'], $txRef]);
            header('Location: ' . $response['data']['checkout_url']);
            exit;
        }

        // Chapa init failed — cancel sale and go back to POS
        $rawMsg = $response['message'] ?? 'Chapa payment failed. Please try cash.';
        if (is_array($rawMsg)) {
            $parts = [];
            foreach ($rawMsg as $field => $msgs) {
                $parts[] = $field . ': ' . (is_array($msgs) ? implode(', ', $msgs) : $msgs);
            }
            $errorMsg = implode(' | ', $parts);
        } else {
            $errorMsg = (string)$rawMsg;
        }
        $pdo->prepare("UPDATE sales SET status='cancelled' WHERE id=?")->execute([$saleId]);
        header('Location: /pharmacy/sales/new.php?payment_error=' . urlencode($errorMsg));
        exit;
    }

    if ($paymentMethod === 'telebirr') {
        // Redirect to telebirr handler
        header('Location: /pharmacy/payments/telebirr.php?sale_id=' . $saleId . '&amount=' . $total . '&customer_name=' . urlencode($customerName) . '&customer_phone=' . urlencode($customerPhone));
        exit;
    }

    // Card / Mobile Money — go to invoice, pharmacist confirms manually
    header('Location: /pharmacy/sales/view.php?id=' . $saleId . '&pending=1');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: /pharmacy/sales/new.php?error=1');
    exit;
}
