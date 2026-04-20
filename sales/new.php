<?php
require_once '../config/db.php';
requireLogin();
define('PAGE_TITLE', 'New Sale (POS)');
define('PAGE_SUBTITLE', 'Point of Sale System');

$branchId = getUserBranchId() ?? 1;
$taxRate = (float)(shopSetting('tax_rate', 0));

// Get categories for filter
$categories = $pdo->query("SELECT DISTINCT c.id, c.name FROM categories c JOIN medicines m ON m.category_id=c.id JOIN stock s ON s.medicine_id=m.id WHERE m.status='active' AND s.branch_id=$branchId AND s.quantity>0 ORDER BY c.name")->fetchAll();

// Get medicines with category info
$medicines = $pdo->prepare("
    SELECT m.id, m.name, m.unit, m.requires_prescription, m.generic_name, m.description,
           s.quantity, s.selling_price, s.batch_number, s.expiry_date,
           c.name as category_name, c.id as category_id
    FROM medicines m
    JOIN stock s ON m.id = s.medicine_id
    LEFT JOIN categories c ON m.category_id = c.id
    WHERE m.status='active' AND s.branch_id=? AND s.quantity > 0
    ORDER BY m.name ASC
");
$medicines->execute([$branchId]);
$medicines = $medicines->fetchAll();

// Medicine icons based on category/name keywords
function getMedIcon($name, $category) {
    $n = strtolower($name . ' ' . $category);
    if (str_contains($n,'antibiotic') || str_contains($n,'amoxicillin') || str_contains($n,'cipro') || str_contains($n,'doxy')) return ['🦠','#e74c3c'];
    if (str_contains($n,'pain') || str_contains($n,'paracetamol') || str_contains($n,'ibuprofen') || str_contains($n,'analgesic')) return ['🩹','#e67e22'];
    if (str_contains($n,'vitamin') || str_contains($n,'supplement') || str_contains($n,'zinc') || str_contains($n,'folic')) return ['💊','#27ae60'];
    if (str_contains($n,'diabetes') || str_contains($n,'metformin') || str_contains($n,'insulin')) return ['🩸','#8e44ad'];
    if (str_contains($n,'heart') || str_contains($n,'cardio') || str_contains($n,'amlodipine') || str_contains($n,'losartan') || str_contains($n,'atorvastatin')) return ['❤️','#e74c3c'];
    if (str_contains($n,'inhaler') || str_contains($n,'respiratory') || str_contains($n,'salbutamol') || str_contains($n,'asthma')) return ['🫁','#3498db'];
    if (str_contains($n,'antifungal') || str_contains($n,'fluconazole')) return ['🍄','#f39c12'];
    if (str_contains($n,'parasite') || str_contains($n,'albendazole') || str_contains($n,'metronidazole')) return ['🔬','#16a085'];
    if (str_contains($n,'stomach') || str_contains($n,'omeprazole') || str_contains($n,'gastro') || str_contains($n,'ors')) return ['🫃','#2980b9'];
    if (str_contains($n,'skin') || str_contains($n,'cream') || str_contains($n,'derma') || str_contains($n,'hydrocortisone')) return ['🧴','#e91e63'];
    if (str_contains($n,'allergy') || str_contains($n,'cetirizine') || str_contains($n,'antihistamine')) return ['🤧','#9b59b6'];
    if (str_contains($n,'syrup') || str_contains($n,'liquid')) return ['🧪','#1abc9c'];
    if (str_contains($n,'injection') || str_contains($n,'vaccine')) return ['💉','#e74c3c'];
    return ['💊','#1a6b3c'];
}

require_once '../includes/header.php';
?>

<?php if (isset($_GET['payment_error'])): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_GET['payment_error']) ?></div>
<?php endif; ?>

<style>
.pos-wrapper { display:grid; grid-template-columns:1fr 380px; gap:16px; height:calc(100vh - 130px); }
.pos-left-panel { display:flex; flex-direction:column; gap:12px; overflow:hidden; }
.pos-search-bar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.pos-search-input { flex:1; min-width:200px; padding:10px 16px 10px 40px; border:2px solid var(--border); border-radius:10px; font-size:14px; outline:none; transition:border 0.2s; }
.pos-search-input:focus { border-color:var(--primary); }
.pos-cat-filters { display:flex; gap:8px; flex-wrap:wrap; }
.cat-btn { padding:6px 14px; border-radius:20px; border:2px solid var(--border); background:#fff; font-size:12px; font-weight:600; cursor:pointer; transition:all 0.2s; white-space:nowrap; }
.cat-btn:hover, .cat-btn.active { background:var(--primary); color:#fff; border-color:var(--primary); }
.med-grid-wrap { flex:1; overflow-y:auto; padding-right:4px; }
.med-grid-wrap::-webkit-scrollbar { width:5px; }
.med-grid-wrap::-webkit-scrollbar-thumb { background:#ddd; border-radius:4px; }
.med-grid-new { display:grid; grid-template-columns:repeat(auto-fill,minmax(155px,1fr)); gap:12px; }
.med-card-new {
    background:#fff; border-radius:14px; padding:16px 12px 12px;
    border:2px solid var(--border); cursor:pointer;
    transition:all 0.2s; text-align:center; position:relative;
    box-shadow:0 2px 8px rgba(0,0,0,0.04);
}
.med-card-new:hover { border-color:var(--primary); transform:translateY(-3px); box-shadow:0 8px 20px rgba(26,107,60,0.15); }
.med-card-new.expired { opacity:0.5; cursor:not-allowed; filter:grayscale(0.5); }
.med-card-new.low-stock { border-color:#f39c12; }
.med-icon-wrap { width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:26px; margin:0 auto 10px; }
.med-card-name { font-weight:700; font-size:12.5px; color:var(--dark); margin-bottom:3px; line-height:1.3; }
.med-card-generic { font-size:10px; color:var(--text-muted); margin-bottom:6px; }
.med-card-price { font-size:15px; font-weight:800; color:var(--primary); margin-bottom:4px; }
.med-card-stock { font-size:11px; color:var(--text-muted); }
.med-card-badges { display:flex; gap:4px; justify-content:center; flex-wrap:wrap; margin-top:6px; }
.med-badge { font-size:9px; padding:2px 7px; border-radius:10px; font-weight:700; }
.med-badge-rx { background:#fff3cd; color:#856404; }
.med-badge-exp { background:#fdf2f2; color:#c0392b; }
.med-badge-warn { background:#fff3cd; color:#d68910; }
.med-badge-low { background:#fef9e7; color:#d68910; }
.add-btn-overlay { position:absolute; bottom:8px; right:8px; width:28px; height:28px; border-radius:50%; background:var(--primary); color:#fff; border:none; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center; opacity:0; transition:opacity 0.2s; }
.med-card-new:hover .add-btn-overlay { opacity:1; }
/* Pagination */
.pos-pagination { display:flex; align-items:center; justify-content:space-between; padding:8px 0; }
.pos-page-info { font-size:12px; color:var(--text-muted); }
.pos-page-btns { display:flex; gap:6px; }
.pos-page-btn { padding:5px 12px; border:1px solid var(--border); border-radius:6px; background:#fff; cursor:pointer; font-size:12px; transition:all 0.15s; }
.pos-page-btn:hover, .pos-page-btn.active { background:var(--primary); color:#fff; border-color:var(--primary); }
.pos-page-btn:disabled { opacity:0.4; cursor:not-allowed; }
/* Cart */
.pos-cart-panel { background:#fff; border-radius:14px; box-shadow:0 4px 20px rgba(0,0,0,0.08); display:flex; flex-direction:column; overflow:hidden; border:1px solid var(--border); }
.cart-header { background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:#fff; padding:14px 16px; font-weight:700; font-size:15px; display:flex; align-items:center; justify-content:space-between; }
.cart-count { background:rgba(255,255,255,0.25); padding:2px 10px; border-radius:12px; font-size:12px; }
.cart-items-wrap { flex:1; overflow-y:auto; padding:10px; }
.cart-items-wrap::-webkit-scrollbar { width:4px; }
.cart-item-new { display:flex; align-items:center; gap:10px; padding:10px; border-radius:10px; border:1px solid var(--border); margin-bottom:8px; background:var(--light); transition:all 0.15s; }
.cart-item-new:hover { border-color:var(--primary); background:var(--primary-light); }
.cart-item-icon { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.cart-item-info { flex:1; min-width:0; }
.cart-item-name-new { font-weight:700; font-size:12.5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.cart-item-price-new { font-size:11px; color:var(--text-muted); margin-top:2px; }
.cart-qty-ctrl { display:flex; align-items:center; gap:4px; }
.qty-btn { width:24px; height:24px; border-radius:6px; border:1px solid var(--border); background:#fff; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center; font-weight:700; transition:all 0.15s; }
.qty-btn:hover { background:var(--primary); color:#fff; border-color:var(--primary); }
.qty-val { width:32px; text-align:center; font-size:13px; font-weight:700; border:1px solid var(--border); border-radius:6px; padding:2px; }
.cart-footer { padding:14px; border-top:1px solid var(--border); background:#fafafa; }
.total-row { display:flex; justify-content:space-between; padding:4px 0; font-size:13px; }
.total-row.grand { font-size:18px; font-weight:800; color:var(--primary); border-top:2px solid var(--border); padding-top:10px; margin-top:6px; }
@media (max-width:768px) { .pos-wrapper { grid-template-columns:1fr; height:auto; } }
</style>

<div class="pos-wrapper">
<!-- LEFT: Medicine Grid -->
<div class="pos-left-panel">
    <!-- Search + AI Filter -->
    <div class="pos-search-bar">
        <div style="position:relative;flex:1;min-width:200px;">
            <i class="fas fa-search" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
            <input type="text" id="searchMedicine" class="pos-search-input" placeholder="🔍 Search by name, generic, category..." oninput="filterMedicines()">
        </div>
        <button onclick="clearSearch()" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</button>
    </div>

    <!-- Category Filters -->
    <div class="pos-cat-filters">
        <button class="cat-btn active" onclick="filterByCategory('all', this)">🏥 All</button>
        <?php foreach ($categories as $cat): ?>
        <button class="cat-btn" onclick="filterByCategory('<?= $cat['id'] ?>', this)" data-cat="<?= $cat['id'] ?>">
            <?= htmlspecialchars($cat['name']) ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- Medicine Grid -->
    <div class="med-grid-wrap">
        <div class="med-grid-new" id="medicineGrid">
            <?php foreach ($medicines as $med):
                $expDays = $med['expiry_date'] ? (strtotime($med['expiry_date']) - time()) / 86400 : 999;
                [$icon, $iconBg] = getMedIcon($med['name'], $med['category_name'] ?? '');
                $isExpired = $expDays < 0;
                $isLow = $med['quantity'] <= 10;
            ?>
            <div class="med-card-new <?= $isExpired ? 'expired' : ($isLow ? 'low-stock' : '') ?>"
                 data-name="<?= strtolower(htmlspecialchars($med['name'].' '.$med['generic_name'].' '.$med['category_name'])) ?>"
                 data-cat="<?= $med['category_id'] ?>"
                 onclick='<?= $isExpired ? "alert('This medicine has EXPIRED and cannot be sold.')" : "addToCart(".json_encode($med).")" ?>'>
                <div class="med-icon-wrap" style="background:<?= $iconBg ?>22;"><?= $icon ?></div>
                <div class="med-card-name"><?= htmlspecialchars($med['name']) ?></div>
                <?php if ($med['generic_name']): ?>
                <div class="med-card-generic"><?= htmlspecialchars($med['generic_name']) ?></div>
                <?php endif; ?>
                <div class="med-card-price"><?= formatCurrency($med['selling_price']) ?></div>
                <div class="med-card-stock"><i class="fas fa-box" style="font-size:10px;"></i> <?= $med['quantity'] ?> <?= htmlspecialchars($med['unit']) ?></div>
                <div class="med-card-badges">
                    <?php if ($med['requires_prescription']): ?><span class="med-badge med-badge-rx">Rx</span><?php endif; ?>
                    <?php if ($isExpired): ?><span class="med-badge med-badge-exp">EXPIRED</span>
                    <?php elseif ($expDays <= 7): ?><span class="med-badge med-badge-exp">Exp <?= round($expDays) ?>d</span>
                    <?php elseif ($expDays <= 30): ?><span class="med-badge med-badge-warn">Exp <?= round($expDays) ?>d</span><?php endif; ?>
                    <?php if ($isLow && !$isExpired): ?><span class="med-badge med-badge-low">Low Stock</span><?php endif; ?>
                </div>
                <?php if (!$isExpired): ?><button class="add-btn-overlay" onclick="event.stopPropagation();addToCart(<?= json_encode($med) ?>)"><i class="fas fa-plus"></i></button><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div id="noResults" style="display:none;text-align:center;padding:40px;color:var(--text-muted);">
            <div style="font-size:48px;margin-bottom:10px;">🔍</div>
            <p>No medicines found. Try a different search.</p>
        </div>
    </div>

    <!-- Pagination -->
    <div class="pos-pagination">
        <div class="pos-page-info" id="pageInfo"></div>
        <div class="pos-page-btns" id="pageBtns"></div>
    </div>
</div>

<!-- RIGHT: Cart -->
<div class="pos-cart-panel">
    <div class="cart-header">
        <span><i class="fas fa-shopping-cart"></i> Cart</span>
        <span class="cart-count" id="cartCount">0 items</span>
    </div>
    <div class="cart-items-wrap" id="cartItems">
        <div style="text-align:center;padding:40px 20px;color:var(--text-muted);">
            <div style="font-size:48px;margin-bottom:10px;">🛒</div>
            <p>Cart is empty</p>
            <p style="font-size:12px;">Click a medicine to add it</p>
        </div>
    </div>
    <div class="cart-footer">
        <form method="POST" action="/pharmacy/sales/process.php" id="saleForm">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;">
                <div class="form-group" style="margin:0;">
                    <label class="form-label" style="font-size:11px;">Customer Name</label>
                    <input type="text" name="customer_name" class="form-control" style="font-size:12px;padding:7px 10px;" value="Walk-in Customer">
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label" style="font-size:11px;">Phone</label>
                    <input type="text" name="customer_phone" class="form-control" style="font-size:12px;padding:7px 10px;" placeholder="Optional">
                </div>
            </div>
            <div class="form-group" style="margin-bottom:10px;">
                <label class="form-label" style="font-size:11px;">Discount (<?= CURRENCY ?>)</label>
                <input type="number" name="discount" id="discountInput" class="form-control" style="font-size:12px;padding:7px 10px;" value="0" step="0.01" min="0" oninput="updateTotals()">
            </div>
            <!-- Payment Methods -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:10px;">
                <?php foreach ([['cash','💵','Cash'],['card','💳','Card'],['chapa','🟢','Chapa'],['telebirr','📱','Telebirr']] as [$val,$emoji,$label]): ?>
                <label style="display:flex;align-items:center;gap:6px;padding:8px 10px;border:2px solid var(--border);border-radius:8px;cursor:pointer;font-size:12px;transition:all 0.15s;" id="pm_<?= $val ?>">
                    <input type="radio" name="payment_method_radio" value="<?= $val ?>" <?= $val==='cash'?'checked':'' ?> style="accent-color:var(--primary);">
                    <span><?= $emoji ?> <?= $label ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="cart_data" id="cartData">
            <input type="hidden" name="payment_method" id="paymentMethodHidden" value="cash">
            <input type="hidden" name="tax_rate" value="<?= $taxRate ?>">
            <div class="total-row"><span>Subtotal:</span><span id="subtotalDisplay"><?= formatCurrency(0) ?></span></div>
            <div class="total-row"><span>Discount:</span><span id="discountDisplay" style="color:var(--danger);"><?= formatCurrency(0) ?></span></div>
            <?php if ($taxRate > 0): ?>
            <div class="total-row"><span>Tax (<?= $taxRate ?>%):</span><span id="taxDisplay"><?= formatCurrency(0) ?></span></div>
            <?php endif; ?>
            <div class="total-row grand"><span>Total:</span><span id="totalDisplay"><?= formatCurrency(0) ?></span></div>
            <button type="submit" class="btn btn-success w-100 mt-2" style="justify-content:center;padding:13px;font-size:14px;border-radius:10px;" id="checkoutBtn" disabled>
                <i class="fas fa-check-circle"></i> Complete Sale
            </button>
            <button type="button" class="btn btn-outline w-100 mt-1" style="justify-content:center;font-size:12px;" onclick="clearCart()">
                <i class="fas fa-trash"></i> Clear Cart
            </button>
        </form>
    </div>
</div>
</div>

<script>
// ===== POS SYSTEM =====
let cart = [];
let allMeds = [];
let filteredMeds = [];
let currentPage = 1;
const perPage = 12;
let activeCategory = 'all';
const taxRate = <?= $taxRate ?>;

// Medicine icons map from PHP
const medIcons = {};
<?php foreach ($medicines as $m):
    [$icon] = getMedIcon($m['name'], $m['category_name'] ?? '');
?>
medIcons[<?= $m['id'] ?>] = '<?= $icon ?>';
<?php endforeach; ?>

// Collect all medicine cards
document.addEventListener('DOMContentLoaded', function() {
    allMeds = Array.from(document.querySelectorAll('.med-card-new'));
    filteredMeds = [...allMeds];
    renderPage();
    // Payment method highlight
    document.querySelectorAll('input[name="payment_method_radio"]').forEach(r => {
        r.addEventListener('change', function() {
            document.getElementById('paymentMethodHidden').value = this.value;
            document.querySelectorAll('[id^="pm_"]').forEach(el => {
                el.style.borderColor = 'var(--border)';
                el.style.background = '#fff';
            });
            const sel = document.getElementById('pm_' + this.value);
            if (sel) { sel.style.borderColor = 'var(--primary)'; sel.style.background = 'var(--primary-light)'; }
        });
    });
    // Highlight cash by default
    const cashEl = document.getElementById('pm_cash');
    if (cashEl) { cashEl.style.borderColor = 'var(--primary)'; cashEl.style.background = 'var(--primary-light)'; }
});

function renderPage() {
    const start = (currentPage - 1) * perPage;
    const end = start + perPage;
    allMeds.forEach(c => c.style.display = 'none');
    const visible = filteredMeds.slice(start, end);
    visible.forEach(c => c.style.display = 'block');
    document.getElementById('noResults').style.display = filteredMeds.length === 0 ? 'block' : 'none';
    renderPagination();
}

function renderPagination() {
    const total = filteredMeds.length;
    const totalPages = Math.ceil(total / perPage);
    const start = Math.min((currentPage - 1) * perPage + 1, total);
    const end = Math.min(currentPage * perPage, total);
    document.getElementById('pageInfo').textContent = total > 0 ? `Showing ${start}–${end} of ${total} medicines` : '';
    const btns = document.getElementById('pageBtns');
    btns.innerHTML = '';
    if (totalPages <= 1) return;
    // Prev
    const prev = document.createElement('button');
    prev.className = 'pos-page-btn'; prev.textContent = '‹';
    prev.disabled = currentPage === 1;
    prev.onclick = () => { currentPage--; renderPage(); };
    btns.appendChild(prev);
    // Pages
    for (let p = Math.max(1, currentPage-2); p <= Math.min(totalPages, currentPage+2); p++) {
        const btn = document.createElement('button');
        btn.className = 'pos-page-btn' + (p === currentPage ? ' active' : '');
        btn.textContent = p;
        btn.onclick = (pp => () => { currentPage = pp; renderPage(); })(p);
        btns.appendChild(btn);
    }
    // Next
    const next = document.createElement('button');
    next.className = 'pos-page-btn'; next.textContent = '›';
    next.disabled = currentPage === totalPages;
    next.onclick = () => { currentPage++; renderPage(); };
    btns.appendChild(next);
}

function filterMedicines() {
    const q = document.getElementById('searchMedicine').value.toLowerCase().trim();
    applyFilters(q, activeCategory);
}

function filterByCategory(catId, btn) {
    activeCategory = catId;
    document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const q = document.getElementById('searchMedicine').value.toLowerCase().trim();
    applyFilters(q, catId);
}

function applyFilters(q, catId) {
    filteredMeds = allMeds.filter(card => {
        const nameMatch = !q || card.getAttribute('data-name').includes(q);
        const catMatch = catId === 'all' || card.getAttribute('data-cat') == catId;
        return nameMatch && catMatch;
    });
    currentPage = 1;
    renderPage();
}

function clearSearch() {
    document.getElementById('searchMedicine').value = '';
    activeCategory = 'all';
    document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('.cat-btn').classList.add('active');
    filteredMeds = [...allMeds];
    currentPage = 1;
    renderPage();
}

// ===== CART =====
function addToCart(medicine) {
    const expDays = medicine.expiry_date ? Math.floor((new Date(medicine.expiry_date) - new Date()) / 86400000) : 999;
    if (expDays <= 30 && expDays >= 0) {
        if (!confirm('⚠️ ' + medicine.name + ' expires in ' + expDays + ' day(s).\n\nAdd anyway?')) return;
    }
    const existing = cart.find(i => i.id === medicine.id);
    if (existing) {
        if (existing.quantity < medicine.quantity) { existing.quantity++; }
        else { alert('Stock limit reached (' + medicine.quantity + ')'); return; }
    } else {
        cart.push({ id: medicine.id, name: medicine.name, price: parseFloat(medicine.selling_price), quantity: 1, maxQty: medicine.quantity, unit: medicine.unit, icon: medIcons[medicine.id] || '💊' });
    }
    renderCart();
    // Flash the card
    const card = document.querySelector(`[data-name="${medicine.name.toLowerCase()}"]`);
    if (card) { card.style.borderColor = 'var(--primary)'; setTimeout(() => card.style.borderColor = '', 600); }
}

function removeFromCart(id) {
    cart = cart.filter(i => i.id !== id);
    renderCart();
}

function changeQty(id, delta) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    const newQty = item.quantity + delta;
    if (newQty <= 0) { removeFromCart(id); return; }
    if (newQty > item.maxQty) { alert('Max stock: ' + item.maxQty); return; }
    item.quantity = newQty;
    renderCart();
}

function renderCart() {
    const container = document.getElementById('cartItems');
    document.getElementById('cartCount').textContent = cart.length + ' item' + (cart.length !== 1 ? 's' : '');
    if (cart.length === 0) {
        container.innerHTML = '<div style="text-align:center;padding:40px 20px;color:var(--text-muted);"><div style="font-size:48px;margin-bottom:10px;">🛒</div><p>Cart is empty</p><p style="font-size:12px;">Click a medicine to add it</p></div>';
        document.getElementById('checkoutBtn').disabled = true;
    } else {
        container.innerHTML = cart.map(item => `
            <div class="cart-item-new">
                <div class="cart-item-icon" style="background:#f0f9f4;">${item.icon}</div>
                <div class="cart-item-info">
                    <div class="cart-item-name-new">${item.name}</div>
                    <div class="cart-item-price-new">${formatCurrency(item.price)} × ${item.quantity} = <strong>${formatCurrency(item.price * item.quantity)}</strong></div>
                </div>
                <div class="cart-qty-ctrl">
                    <button class="qty-btn" onclick="changeQty(${item.id},-1)">−</button>
                    <span class="qty-val">${item.quantity}</span>
                    <button class="qty-btn" onclick="changeQty(${item.id},1)">+</button>
                    <button class="qty-btn" onclick="removeFromCart(${item.id})" style="background:#fdf2f2;color:var(--danger);border-color:#fdf2f2;margin-left:2px;">✕</button>
                </div>
            </div>
        `).join('');
        document.getElementById('checkoutBtn').disabled = false;
    }
    updateTotals();
}

function updateTotals() {
    const subtotal = cart.reduce((s, i) => s + i.price * i.quantity, 0);
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const taxable = Math.max(0, subtotal - discount);
    const tax = taxRate > 0 ? taxable * (taxRate / 100) : 0;
    const total = taxable + tax;
    document.getElementById('subtotalDisplay').textContent = formatCurrency(subtotal);
    document.getElementById('discountDisplay').textContent = '- ' + formatCurrency(discount);
    if (document.getElementById('taxDisplay')) document.getElementById('taxDisplay').textContent = formatCurrency(tax);
    document.getElementById('totalDisplay').textContent = formatCurrency(total);
    document.getElementById('cartData').value = JSON.stringify(cart);
}

function clearCart() {
    if (cart.length && confirm('Clear all items?')) { cart = []; renderCart(); }
}

function formatCurrency(amount) {
    return '<?= CURRENCY ?> ' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

document.getElementById('saleForm').addEventListener('submit', function(e) {
    if (cart.length === 0) { e.preventDefault(); alert('Cart is empty.'); }
});
</script>
<?php require_once '../includes/footer.php'; ?>
