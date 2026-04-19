<?php
$cartCtrl    = new CartController();
$productCtrl = new ProductController();
$engine      = new PairPalEngine();
$cart        = $cartCtrl->getCart();
$cartItems   = $cart->getItems();
$q           = trim($_GET['q'] ?? '');
$cat         = $_GET['cat'] ?? '';
$popMap      = $engine->getProductPopularityMap();
$products    = $productCtrl->search($q, $cat);
$categories  = $productCtrl->getCategories();
$suggestions = $engine->getCartSuggestions($cart->getProductIds());
$upsells     = $engine->getUpsellPrompts($cart->getProductIds());
$lowStockIds = array_column($engine->getLowStockAlerts(), 'id');
?>
<div class="pos-layout">

  <!-- ── Left: Product Catalog ──────────────────────────────────── -->
  <div class="pos-catalog">
    <div class="pos-search">
      <input type="text" id="catalogSearch" placeholder="Search by name, category…" value="<?= htmlspecialchars($q) ?>" oninput="debounceCatalog()">
      <select id="catalogCat" onchange="filterCatalog()">
        <option value="">All</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>" <?= $cat===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="product-grid" id="productGrid">
      <?php foreach ($products as $p):
        $pop  = $popMap[$p['id']] ?? 0;
        $isLow = in_array($p['id'], $lowStockIds);
      ?>
      <div class="product-card <?= $p['stock']<=0?'out-of-stock':'' ?> <?= $isLow&&$p['stock']>0?'low-stock-card':'' ?>" onclick="<?= $p['stock']>0?"addToCart('{$p['id']}')"  :'' ?>">
        <?php if ($pop >= 5): ?><div class="product-card-hot">🔥</div><?php endif; ?>
        <?php if (!empty($p['image'])): ?><img src="<?= htmlspecialchars($p['image']) ?>" class="product-card-img" alt=""><?php endif; ?>
        <div class="product-card-meta">
          <span class="product-card-cat"><?= htmlspecialchars($p['category']) ?></span>
          <?php if ($isLow && $p['stock']>0): ?><span class="product-card-low-tag">Low Stock</span><?php endif; ?>
        </div>
        <div class="product-card-name"><?= htmlspecialchars($p['name']) ?></div>
        <div class="product-card-footer">
          <span class="product-card-price">₱<?= number_format($p['price'],2) ?></span>
          <span class="product-card-stock <?= $p['stock']<=0?'danger':($isLow?'warn':'') ?>">
            <?= $p['stock']<=0 ? 'Out of Stock' : "Qty: {$p['stock']}" ?>
          </span>
        </div>
        <?php if ($p['stock']>0): ?><div class="product-card-add">+ Add</div><?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php if (empty($products)): ?><div class="empty-catalog">No products found.</div><?php endif; ?>
    </div>
  </div>

  <!-- ── Middle: AI Panel ───────────────────────────────────────── -->
  <div class="pos-ai-panel" id="aiPanel">
    <div class="ai-panel-header">
      <span class="ai-panel-icon">✦</span>
      <span class="ai-panel-title">PairPal Assistant</span>
    </div>

    <!-- Bundle message -->
    <?php if ($cart->getBundleMessage()): ?>
    <div class="ai-bundle-alert" id="bundleAlert">
      <?= htmlspecialchars($cart->getBundleMessage()) ?>
    </div>
    <?php else: ?>
    <div class="ai-bundle-alert" id="bundleAlert" style="display:none"></div>
    <?php endif; ?>

    <!-- Upsell prompts -->
    <div class="ai-section" id="upsellSection">
      <?php if (!empty($upsells)): ?>
      <div class="ai-section-label">🔓 Unlock a Deal</div>
      <?php foreach ($upsells as $up): ?>
      <div class="upsell-prompt" onclick="addToCart('<?= $up['product']['id'] ?>')">
        <div class="upsell-msg"><?= htmlspecialchars($up['message']) ?></div>
        <div class="upsell-product">
          <span class="upsell-name"><?= htmlspecialchars($up['product']['name']) ?></span>
          <span class="upsell-price">₱<?= number_format($up['product']['price'],2) ?></span>
          <span class="btn btn-sm btn-accent">+ Add</span>
        </div>
      </div>
      <?php endforeach; ?>
      <?php else: ?>
      <div class="ai-section-label" id="upsellLabel" style="display:none">🔓 Unlock a Deal</div>
      <?php endif; ?>
    </div>

    <!-- Suggestions -->
    <div class="ai-section">
      <div class="ai-section-label">💡 May Also Want</div>
      <div class="suggestion-list" id="suggestionList">
        <?php if (!empty($suggestions)): ?>
          <?php foreach ($suggestions as $s): ?>
          <div class="suggestion-item" onclick="addToCart('<?= $s['id'] ?>')">
            <div class="suggestion-info">
              <div class="suggestion-name"><?= htmlspecialchars($s['name']) ?></div>
              <div class="suggestion-reason"><?= htmlspecialchars($s['_reason'] ?? '') ?></div>
            </div>
            <div class="suggestion-price">₱<?= number_format($s['price'],2) ?></div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="ai-empty">Add products to the cart to see suggestions.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Right: Cart Panel ──────────────────────────────────────── -->
  <div class="pos-cart">
    <div class="cart-header">
      <span>Cart</span>
      <button class="btn btn-sm btn-ghost" onclick="clearCart()">Clear</button>
    </div>

    <div class="cart-items" id="cartItems">
      <?php if (empty($cartItems)): ?>
        <div class="cart-empty">Cart is empty.<br>Click a product to add it.</div>
      <?php else: ?>
        <?php foreach ($cartItems as $item):
          $isLow = in_array($item['product_id'], $lowStockIds);
        ?>
        <div class="cart-item <?= $isLow?'cart-item-lowstock':'' ?>" id="ci_<?= $item['product_id'] ?>">
          <div class="cart-item-name">
            <?= htmlspecialchars($item['name']) ?>
            <?php if ($isLow): ?><span class="low-tag">low stock</span><?php endif; ?>
          </div>
          <div class="cart-item-controls">
            <button onclick="updateQty('<?= $item['product_id'] ?>',<?= $item['quantity']-1 ?>)">−</button>
            <input type="number" class="qty-input" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock'] ?>" onchange="updateQty('<?= $item['product_id'] ?>',this.value)">
            <button onclick="updateQty('<?= $item['product_id'] ?>',<?= $item['quantity']+1 ?>)">+</button>
          </div>
          <div class="cart-item-price">₱<?= number_format($item['price']*$item['quantity'],2) ?></div>
          <button class="cart-item-remove" onclick="removeItem('<?= $item['product_id'] ?>')">✕</button>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="cart-summary">
      <!-- Manual discount override -->
      <div class="discount-row">
        <span class="discount-label">Manual Discount</span>
        <div class="discount-controls">
          <select id="discountType" onchange="syncDiscount()">
            <option value="none"    <?= $cart->getDiscountType()==='none'&&!$cart->getBundleName()?'selected':'' ?>>None</option>
            <option value="percent" <?= $cart->getDiscountType()==='percent'&&!$cart->getBundleName()?'selected':'' ?>>%</option>
            <option value="fixed"   <?= $cart->getDiscountType()==='fixed'&&!$cart->getBundleName()?'selected':'' ?>>₱</option>
          </select>
          <input type="number" id="discountValue" min="0" step="0.01" value="<?= $cart->getBundleName()?0:$cart->getDiscountValue() ?>" placeholder="0" oninput="syncDiscount()" <?= $cart->getDiscountType()==='none'||$cart->getBundleName()?'disabled':'' ?>>
        </div>
      </div>

      <div class="cart-total-row"><span>Subtotal</span><span id="cartSubtotal">₱<?= number_format($cart->getSubtotal(),2) ?></span></div>
      <div class="cart-discount-row" id="discountRow" style="<?= $cart->getDiscountAmount()>0?'':'display:none' ?>">
        <span id="discountLabel">
          <?= $cart->getBundleName() ? '🎁 '.$cart->getBundleName() : 'Discount' ?>
        </span>
        <span id="cartDiscount">−₱<?= number_format($cart->getDiscountAmount(),2) ?></span>
      </div>
      <div class="cart-total-row total-final"><span>Total</span><span id="cartTotal">₱<?= number_format($cart->getTotal(),2) ?></span></div>

      <div class="form-group">
        <label>Amount Tendered (₱)</label>
        <input type="number" id="amountPaid" min="0" step="0.01" placeholder="0.00" oninput="calcChange()">
      </div>
      <div class="cart-change-row" id="changeRow" style="display:none">
        <span>Change</span><span id="changeAmount">₱0.00</span>
      </div>
      <button class="btn btn-primary btn-full" onclick="checkout()" id="checkoutBtn" <?= empty($cartItems)?'disabled':'' ?>>
        ⊕ Checkout
      </button>
    </div>
  </div>
</div>

<!-- Receipt Modal -->
<div id="receiptModal" class="modal-overlay" style="display:none">
  <div class="modal receipt-modal"><div class="receipt" id="receiptContent"></div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="printReceipt()">🖨 Print</button>
      <button class="btn btn-primary" onclick="closeReceipt()">Done</button>
    </div>
  </div>
</div>

<script>
let catalogTimer, discountDebounce, aiRefreshTimer;
function debounceCatalog() { clearTimeout(catalogTimer); catalogTimer = setTimeout(filterCatalog, 350); }
function filterCatalog() { window.location=`index.php?page=cart&q=${encodeURIComponent(document.getElementById('catalogSearch').value)}&cat=${encodeURIComponent(document.getElementById('catalogCat').value)}`; }

async function addToCart(id, qty=1) {
  const fd=new FormData(); fd.append('action','cart_add'); fd.append('product_id',id); fd.append('qty',qty);
  const data = await pairpalPost(fd);
  if (data.success) {
    showToast('Added ✓','success');
    if (data.bundle_message) showBundleToast(data.bundle_message);
    setTimeout(()=>location.reload(),300);
  } else showToast(data.message,'error');
}

function showBundleToast(msg) {
  const t = document.getElementById('bundleAlert');
  if (!t) return;
  t.textContent = msg; t.style.display='flex';
  t.classList.add('bundle-flash');
  setTimeout(()=>t.classList.remove('bundle-flash'),1500);
}

async function updateQty(id,qty) {
  qty=parseInt(qty); if(qty<=0){removeItem(id);return;}
  const fd=new FormData(); fd.append('action','cart_update'); fd.append('product_id',id); fd.append('qty',qty);
  const data = await pairpalPost(fd);
  if(data.success) { refreshTotals(data); setTimeout(()=>location.reload(),100); }
  else showToast(data.message,'error');
}

async function removeItem(id) {
  const fd=new FormData(); fd.append('action','cart_remove'); fd.append('product_id',id);
  const data = await pairpalPost(fd);
  if(data.success) setTimeout(()=>location.reload(),100);
}

async function clearCart() {
  if(!confirm('Clear cart?')) return;
  const items=document.querySelectorAll('.cart-item');
  for(const item of items){const id=item.id.replace('ci_',''); const fd=new FormData(); fd.append('action','cart_remove'); fd.append('product_id',id); await pairpalPost(fd);}
  location.reload();
}

let discTimer;
function syncDiscount() {
  const type=document.getElementById('discountType').value;
  const valEl=document.getElementById('discountValue');
  valEl.disabled=(type==='none');
  if(type==='none') valEl.value=0;
  clearTimeout(discTimer); discTimer=setTimeout(applyDiscount,500);
}

async function applyDiscount() {
  const type=document.getElementById('discountType').value, value=document.getElementById('discountValue').value||0;
  const fd=new FormData(); fd.append('action','cart_discount'); fd.append('type',type); fd.append('value',value);
  const data = await pairpalPost(fd);
  if(data.success) refreshTotals(data);
}

function refreshTotals(data) {
  const sub=document.getElementById('cartSubtotal'), disc=document.getElementById('cartDiscount'), tot=document.getElementById('cartTotal');
  if(sub) sub.textContent='₱'+fmtNum(data.subtotal);
  if(disc) disc.textContent='−₱'+fmtNum(data.discount);
  if(tot) tot.textContent='₱'+fmtNum(data.total);
  const dr=document.getElementById('discountRow');
  if(dr) dr.style.display=data.discount>0?'flex':'none';
  calcChange();
}

function calcChange() {
  const paid=parseFloat(document.getElementById('amountPaid').value)||0;
  const total=parseFloat((document.getElementById('cartTotal').textContent||'0').replace('₱','').replace(/,/g,''));
  const cr=document.getElementById('changeRow');
  if(paid>=total&&total>0){cr.style.display='flex'; document.getElementById('changeAmount').textContent='₱'+fmtNum(paid-total);}
  else cr.style.display='none';
}

function fmtNum(n){return parseFloat(n).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});}

async function checkout() {
  const paid=parseFloat(document.getElementById('amountPaid').value);
  if(!paid||paid<=0){showToast('Enter amount tendered.','error');return;}
  const total=parseFloat((document.getElementById('cartTotal').textContent).replace('₱','').replace(/,/g,''));
  if(paid<total){showToast('Amount is insufficient.','error');return;}
  if(!confirm('Confirm checkout?')) return;
  document.getElementById('checkoutBtn').disabled=true;
  const fd=new FormData(); fd.append('action','checkout'); fd.append('amount_paid',paid);
  const data = await pairpalPost(fd);
  if(data.success) showReceipt(data.transaction,paid);
  else{showToast(data.message,'error'); document.getElementById('checkoutBtn').disabled=false;}
}

function showReceipt(txn,paid){
  const discAmt=parseFloat(txn.discount_amount||0);
  const bundleName=txn.bundle_name||'';
  let discHtml='';
  if(discAmt>0){const label=bundleName?`🎁 ${bundleName}`:txn.discount_type==='percent'?`${txn.discount_value}% off`:`₱${txn.discount_value} off`; discHtml=`<div class="receipt-row"><span>Discount (${label})</span><span>−₱${discAmt.toFixed(2)}</span></div>`;}
  document.getElementById('receiptContent').innerHTML=`
    <div class="receipt-header"><div class="receipt-brand">◈ PairPal</div><div class="receipt-sub">Sales Manager</div><div class="receipt-id">TXN: ${txn.id}</div><div class="receipt-date">${new Date(txn.date).toLocaleString()}</div><div class="receipt-cashier">Cashier: ${txn.cashier_name}</div></div>
    <hr class="receipt-divider">
    <div class="receipt-items">${txn.items.map(i=>`<div class="receipt-item"><span>${i.name} ×${i.quantity}</span><span>₱${parseFloat(i.subtotal).toFixed(2)}</span></div>`).join('')}</div>
    <hr class="receipt-divider">
    <div class="receipt-totals">
      <div class="receipt-row"><span>Subtotal</span><span>₱${parseFloat(txn.subtotal||txn.total).toFixed(2)}</span></div>
      ${discHtml}
      <div class="receipt-row bold-row"><span>Total</span><span>₱${parseFloat(txn.total).toFixed(2)}</span></div>
      <div class="receipt-row"><span>Paid</span><span>₱${parseFloat(paid).toFixed(2)}</span></div>
      <div class="receipt-row change-row"><span>Change</span><span>₱${(paid-txn.total).toFixed(2)}</span></div>
    </div>
    ${discAmt>0?`<div class="receipt-savings">You saved ₱${discAmt.toFixed(2)}!</div>`:''}
    <div class="receipt-footer">Thank you for shopping!<br>◈ PairPal Sales Manager</div>`;
  document.getElementById('receiptModal').style.display='flex';
}

function closeReceipt(){
    document.getElementById('receiptModal').style.display='none';
    const old = document.getElementById('print-receipt');
    if (old) old.remove();
    location.reload();
}

function printReceipt() {
    // Clone receipt content into a top-level div so @media print can show it
    // regardless of whether the modal overlay is display:none
    const old = document.getElementById('print-receipt');
    if (old) old.remove();
    const src = document.getElementById('receiptContent');
    if (!src) return;
    const div = document.createElement('div');
    div.id = 'print-receipt';
    div.innerHTML = src.innerHTML;
    document.body.appendChild(div);
    window.print();
    // Clean up after printing dialog closes
    setTimeout(() => { const el = document.getElementById('print-receipt'); if(el) el.remove(); }, 1000);
}
// ── Keyboard shortcuts ────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && document.activeElement.id === 'amountPaid') {
        e.preventDefault();
        if (!document.getElementById('checkoutBtn').disabled) checkout();
    }
    if (e.key === 'Escape') { document.getElementById('amountPaid').blur(); }
    if (e.key === 'F2') { e.preventDefault(); document.getElementById('catalogSearch').focus(); document.getElementById('catalogSearch').select(); }
});
</script>
