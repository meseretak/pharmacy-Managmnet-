<?php
require_once '../config/db.php';
requireLogin();
define('PAGE_TITLE', 'New Sale (POS)');
define('PAGE_SUBTITLE', 'Point of Sale System');

$branchId = getUserBranchId() ?? 1;

// Get available medicines with stock
$medicines = $pdo->prepare("
    SELECT m.id, m.name, m.unit, m.requires_prescription, s.quantity, s.selling_price, s.batch_number, s.expiry_date
    FROM medicines m
    JOIN stock s ON m.id = s.medicine_id
    WHERE m.status='active' AND s.branch_id=? AND s.quantity > 0
    ORDER BY m.name ASC
");
$medicines->execute([$branchId]);
$medicines = $medicines->fetchAll();

require_once '../includes/header.php';
?>

<?php if (isset($_GET['payment_error'])): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Payment Error: <?= htmlspecialchars($_GET['payment_error']) ?></div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'empty'): ?>
<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Cart is empty. Please add medicines first.</div>
<?php endif; ?>

<div class="pos-grid">
    <!-- LEFT: Medicine Selection -->
    <div class="pos-left">
        <div class="card">
            <div class="card-header">
                <div class="search-bar" style="max-width:100%;">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchMedicine" placeholder="Search medicines..." onkeyup="filterMedicines()">
                </div>
            </div>
            <div class="card-body">
                <div class="medicine-grid" id="medicineGrid">
                    <?php if (empty($medicines)): ?>
                    <div class="empty-state" style="grid-column:1/-1;"><div class="empty-icon">💊</div><p>No medicines in stock</p></div>
                    <?php else: ?>
                    <?php foreach ($medicines as $med): ?>
                    <?php
                    // Expiry check
                    $expDays = $med['expiry_date'] ? (strtotime($med['expiry_date']) - time()) / 86400 : 999;
                    $expClass = '';
                    $expLabel = '';
                    if ($expDays < 0) { $expClass = 'out-of-stock'; $expLabel = '⚠️ EXPIRED'; }
                    elseif ($expDays <= 7) { $expClass = ''; $expLabel = '🔴 Exp: '.date('d M', strtotime($med['expiry_date'])); }
                    elseif ($expDays <= 30) { $expClass = ''; $expLabel = '🟡 Exp: '.date('d M', strtotime($med['expiry_date'])); }
                    ?>
                    <div class="medicine-card <?= $expClass ?>"
                         data-name="<?= strtolower(htmlspecialchars($med['name'])) ?>"
                         data-expdays="<?= round($expDays) ?>"
                         onclick='<?= $expDays < 0 ? "alert(\"This medicine has EXPIRED and cannot be sold.\")" : "addToCart(".json_encode($med).")" ?>'>
                        <div class="med-name"><?= htmlspecialchars($med['name']) ?></div>
                        <div class="med-price"><?= formatCurrency($med['selling_price']) ?></div>
                        <div class="med-stock">Stock: <?= $med['quantity'] ?> <?= htmlspecialchars($med['unit']) ?></div>
                        <?php if ($med['requires_prescription']): ?>
                        <div style="margin-top:4px;"><span class="badge badge-warning" style="font-size:9px;">Rx Required</span></div>
                        <?php endif; ?>
                        <?php if ($expLabel): ?>
                        <div style="margin-top:4px;font-size:10px;font-weight:700;color:<?= $expDays < 0 ? 'var(--danger)' : ($expDays <= 7 ? 'var(--danger)' : 'var(--warning)') ?>;"><?= $expLabel ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: Cart -->
    <div class="pos-right">
        <div class="pos-cart-header">
            <i class="fas fa-shopping-cart"></i> Shopping Cart
        </div>
        <div class="pos-cart-items" id="cartItems">
            <div class="empty-state"><div class="empty-icon">🛒</div><p>Cart is empty</p></div>
        </div>
        <div class="pos-cart-footer">
            <form method="POST" action="/pharmacy/sales/process.php" id="saleForm">
                <div class="form-group">
                    <label class="form-label">Customer Name</label>
                    <input type="text" name="customer_name" class="form-control" placeholder="Walk-in Customer" value="Walk-in Customer">
                </div>
                <div class="form-group">
                    <label class="form-label">Customer Phone</label>
                    <input type="text" name="customer_phone" class="form-control" placeholder="Optional">
                </div>
                <div class="form-group">
                    <label class="form-label">Discount (<?= CURRENCY ?>)</label>
                    <input type="number" name="discount" id="discountInput" class="form-control" value="0" step="0.01" min="0" onchange="updateTotals()">
                </div>
                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <div style="display:flex;flex-direction:column;gap:8px;margin-top:6px;">
                        <label id="pm_cash" style="display:flex;align-items:center;gap:12px;padding:12px 15px;border:2px solid var(--secondary);border-radius:10px;cursor:pointer;background:var(--primary-light);">
                            <input type="radio" name="payment_method_radio" value="cash" checked style="width:18px;height:18px;accent-color:var(--primary);">
                            <div>
                                <div style="font-weight:700;font-size:14px;">💵 Cash</div>
                                <div style="font-size:11px;color:var(--text-muted);">Completes sale immediately</div>
                            </div>
                        </label>
                        <label id="pm_card" style="display:flex;align-items:center;gap:12px;padding:12px 15px;border:2px solid var(--border);border-radius:10px;cursor:pointer;background:#fff;">
                            <input type="radio" name="payment_method_radio" value="card" style="width:18px;height:18px;accent-color:var(--primary);">
                            <div>
                                <div style="font-weight:700;font-size:14px;">💳 Card</div>
                                <div style="font-size:11px;color:var(--text-muted);">POS machine / debit / credit card</div>
                            </div>
                        </label>
                        <label id="pm_chapa" style="display:flex;align-items:center;gap:12px;padding:12px 15px;border:2px solid var(--border);border-radius:10px;cursor:pointer;background:#fff;">
                            <input type="radio" name="payment_method_radio" value="chapa" style="width:18px;height:18px;accent-color:var(--primary);">
                            <div>
                                <div style="font-weight:700;font-size:14px;">🟢 Chapa</div>
                                <div style="font-size:11px;color:var(--text-muted);">Telebirr · CBE Birr · Banks · Cards online</div>
                            </div>
                        </label>
                        <label id="pm_telebirr" style="display:flex;align-items:center;gap:12px;padding:12px 15px;border:2px solid var(--border);border-radius:10px;cursor:pointer;background:#fff;">
                            <input type="radio" name="payment_method_radio" value="telebirr" style="width:18px;height:18px;accent-color:var(--primary);">
                            <div>
                                <div style="font-weight:700;font-size:14px;">📱 Telebirr</div>
                                <div style="font-size:11px;color:var(--text-muted);">Direct Telebirr payment</div>
                            </div>
                        </label>
                    </div>
                    <div id="paymentNote" style="margin-top:8px;font-size:12px;color:var(--text-muted);padding:8px 12px;background:var(--light);border-radius:6px;">
                        💵 Cash: sale completes immediately after clicking Complete Sale.
                    </div>
                </div>
                <input type="hidden" name="cart_data" id="cartData">
                <input type="hidden" name="payment_method" id="paymentMethodHidden" value="cash">
                <div class="divider"></div>
                <div class="pos-total-row"><span>Subtotal:</span><span id="subtotalDisplay"><?= formatCurrency(0) ?></span></div>
                <div class="pos-total-row"><span>Discount:</span><span id="discountDisplay"><?= formatCurrency(0) ?></span></div>
                <div class="pos-total-row grand"><span>Total:</span><span id="totalDisplay"><?= formatCurrency(0) ?></span></div>
                <button type="submit" class="btn btn-success w-100 mt-2" style="justify-content:center;padding:14px;font-size:15px;" id="checkoutBtn" disabled>
                    <i class="fas fa-check-circle"></i> Complete Sale
                </button>
                <button type="button" class="btn btn-danger w-100 mt-1" style="justify-content:center;" onclick="clearCart()">
                    <i class="fas fa-trash"></i> Clear Cart
                </button>
            </form>
        </div>
    </div>
</div>

<script>
let cart = [];

function addToCart(medicine) {
    // Warn if expiring soon
    const expDays = medicine.expiry_date ? Math.floor((new Date(medicine.expiry_date) - new Date()) / 86400000) : 999;
    if (expDays <= 30 && expDays >= 0) {
        const ok = confirm('⚠️ WARNING: ' + medicine.name + ' expires in ' + expDays + ' day(s) on ' + medicine.expiry_date + '.\n\nDo you still want to add it to the cart?');
        if (!ok) return;
    }
    const existing = cart.find(item => item.id === medicine.id);
    if (existing) {
        if (existing.quantity < medicine.quantity) {
            existing.quantity++;
        } else {
            alert('Cannot add more. Stock limit reached.');
            return;
        }
    } else {
        cart.push({
            id: medicine.id,
            name: medicine.name,
            price: parseFloat(medicine.selling_price),
            quantity: 1,
            maxQty: medicine.quantity,
            unit: medicine.unit
        });
    }
    renderCart();
}

function removeFromCart(id) {
    cart = cart.filter(item => item.id !== id);
    renderCart();
}

function updateQuantity(id, qty) {
    const item = cart.find(i => i.id === id);
    if (item) {
        qty = parseInt(qty);
        if (qty <= 0) {
            removeFromCart(id);
        } else if (qty <= item.maxQty) {
            item.quantity = qty;
            renderCart();
        } else {
            alert('Quantity exceeds available stock (' + item.maxQty + ')');
            renderCart();
        }
    }
}

function renderCart() {
    const container = document.getElementById('cartItems');
    if (cart.length === 0) {
        container.innerHTML = '<div class="empty-state"><div class="empty-icon">🛒</div><p>Cart is empty</p></div>';
        document.getElementById('checkoutBtn').disabled = true;
    } else {
        container.innerHTML = cart.map(item => `
            <div class="cart-item">
                <div style="flex:1;">
                    <div class="cart-item-name">${item.name}</div>
                    <div class="cart-item-price">${formatCurrency(item.price)} × 
                        <input type="number" value="${item.quantity}" min="1" max="${item.maxQty}" 
                               onchange="updateQuantity(${item.id}, this.value)"
                               style="width:50px;padding:2px 5px;border:1px solid var(--border);border-radius:4px;text-align:center;">
                        = ${formatCurrency(item.price * item.quantity)}
                    </div>
                </div>
                <button type="button" class="btn btn-danger btn-sm btn-icon" onclick="removeFromCart(${item.id})" title="Remove">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');
        document.getElementById('checkoutBtn').disabled = false;
    }
    updateTotals();
}

function updateTotals() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const total = Math.max(0, subtotal - discount);

    document.getElementById('subtotalDisplay').textContent = formatCurrency(subtotal);
    document.getElementById('discountDisplay').textContent = formatCurrency(discount);
    document.getElementById('totalDisplay').textContent = formatCurrency(total);
    document.getElementById('cartData').value = JSON.stringify(cart);
}

function clearCart() {
    if (confirm('Clear all items from cart?')) {
        cart = [];
        renderCart();
    }
}

function filterMedicines() {
    const search = document.getElementById('searchMedicine').value.toLowerCase();
    document.querySelectorAll('.medicine-card').forEach(card => {
        const name = card.getAttribute('data-name');
        card.style.display = name.includes(search) ? 'block' : 'none';
    });
}

function formatCurrency(amount) {
    return '<?= CURRENCY ?> ' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

document.getElementById('saleForm').addEventListener('submit', function(e) {
    if (cart.length === 0) {
        e.preventDefault();
        alert('Cart is empty. Please add medicines first.');
    }
});

// Payment method radio — sync to hidden field
document.querySelectorAll('input[name="payment_method_radio"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        var val = this.value;
        document.getElementById('paymentMethodHidden').value = val;

        // Reset all borders
        ['pm_cash','pm_card','pm_chapa','pm_telebirr'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) { el.style.borderColor = 'var(--border)'; el.style.background = '#fff'; }
        });
        // Highlight selected
        var selected = document.getElementById('pm_' + val);
        if (selected) { selected.style.borderColor = 'var(--primary)'; selected.style.background = 'var(--primary-light)'; }

        var note = document.getElementById('paymentNote');
        var btn  = document.getElementById('checkoutBtn');
        if (val === 'cash') {
            note.textContent = '💵 Cash: sale completes immediately.';
            note.style.color = 'var(--text-muted)';
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Complete Sale — Cash';
            btn.style.background = 'var(--secondary)';
        } else if (val === 'card') {
            note.textContent = '💳 Card: sale completes immediately after swiping card.';
            note.style.color = 'var(--info)';
            btn.innerHTML = '<i class="fas fa-credit-card"></i> Complete Sale — Card';
            btn.style.background = '#2980b9';
        } else if (val === 'chapa') {
            note.innerHTML = '🟢 Chapa: you will be redirected to Chapa checkout. Sale completes after payment confirmed.';
            note.style.color = 'var(--primary)';
            btn.innerHTML = '<i class="fas fa-external-link-alt"></i> Pay with Chapa →';
            btn.style.background = '#1a6b3c';
        } else if (val === 'telebirr') {
            note.innerHTML = '📱 Telebirr: you will be redirected to Telebirr. Sale completes after payment confirmed.';
            note.style.color = '#e67e22';
            btn.innerHTML = '<i class="fas fa-mobile-alt"></i> Pay with Telebirr →';
            btn.style.background = '#e67e22';
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
