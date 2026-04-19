<?php
// views/orders.php
$orderCtrl = new OrderController();
$filter    = $_GET['status'] ?? '';
$orders    = $filter ? $orderCtrl->getByStatus($filter) : $orderCtrl->getAll();
$counts    = $orderCtrl->getStatusCounts();
$statuses  = ['pending','processing','shipped','delivered','cancelled'];
$statusColors = ['pending'=>'warn','processing'=>'type-update','shipped'=>'type-import','delivered'=>'type-add','cancelled'=>'danger'];
?>
<div class="page-controls orders-controls">
  <div class="status-filter-tabs">
    <a href="index.php?page=orders" class="status-tab <?= !$filter?'active':'' ?>">All <span class="count-badge"><?= array_sum($counts) ?></span></a>
    <?php foreach ($statuses as $s): ?>
    <a href="index.php?page=orders&status=<?= $s ?>" class="status-tab <?= $filter===$s?'active':'' ?>">
      <?= ucfirst($s) ?> <span class="count-badge"><?= $counts[$s]??0 ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <div class="orders-search-wrap">
    <input type="text" id="ordersSearch" placeholder="Search by name, order ID, or tracking…" oninput="filterOrders(this.value)" class="orders-search-input">
  </div>
</div>

<div class="card">
  <div class="table-wrap">
  <table class="data-table orders-table">
    <thead>
      <tr><th>Order ID</th><th>Tracking</th><th>Customer</th><th>Items</th><th>Bundle</th><th>Total</th><th>Status</th><th>Date</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
      <tr class="order-row" data-id="<?= strtolower($o['id']) ?>" data-name="<?= strtolower(htmlspecialchars($o['customer_name'])) ?>" data-tracking="<?= strtolower($o['tracking_code']??'') ?>">
        <td><code><?= htmlspecialchars($o['id']) ?></code></td>
        <td><code class="tracking-code-small"><?= htmlspecialchars($o['tracking_code']) ?></code></td>
        <td>
          <strong><?= htmlspecialchars($o['customer_name']) ?></strong><br>
          <small class="text-muted"><?= htmlspecialchars($o['customer_contact']) ?></small>
        </td>
        <td><?= count($o['items']) ?> item<?= count($o['items'])!==1?'s':'' ?></td>
        <td>
          <?php if (!empty($o['bundle_applied'])): ?>
            <span class="bundle-tag-small">🎁 <?= htmlspecialchars($o['bundle_applied']) ?></span>
          <?php else: ?>
            <span class="text-muted">—</span>
          <?php endif; ?>
        </td>
        <td>
          ₱<?= number_format($o['total'],2) ?>
          <?php if (($o['discount_amount']??0)>0): ?><br><small class="text-muted">saved ₱<?= number_format($o['discount_amount'],2) ?></small><?php endif; ?>
        </td>
        <td>
          <span class="type-badge <?= $statusColors[$o['status']]??'type-update' ?>"><?= ucfirst($o['status']) ?></span>
        </td>
        <td class="text-muted"><?= date('M j, Y g:i a', strtotime($o['created_at'])) ?></td>
        <td class="action-cell">
          <button class="btn btn-sm btn-ghost" onclick='showOrderDetail(<?= json_encode($o) ?>)'>Details</button>
          <?php if ($o['status'] !== 'delivered' && $o['status'] !== 'cancelled'): ?>
          <div class="status-select-wrap"><select class="status-select" onchange="updateOrderStatus('<?= $o['id'] ?>',this.value)">
            <?php foreach ($statuses as $s): ?>
              <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select></div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($orders)): ?><tr><td colspan="9" class="empty">No orders found.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<div id="orderDetailModal" class="modal-overlay" style="display:none" onclick="this.style.display='none'">
  <div class="modal modal-lg" onclick="event.stopPropagation()">
    <div class="modal-header"><h2>Order Details</h2><button class="modal-close" onclick="document.getElementById('orderDetailModal').style.display='none'">✕</button></div>
    <div id="orderDetailContent" class="detail-content"></div>
  </div>
</div>

<script>
async function updateOrderStatus(id, status) {
  const fd=new FormData(); fd.append('action','order_update_status'); fd.append('id',id); fd.append('status',status);
  const data = await pairpalPost(fd);
  showToast(data.message,data.success?'success':'error');
  if(data.success) setTimeout(()=>location.reload(),800);
}

function filterOrders(q) {
  const term = q.toLowerCase().trim();
  let visibleCount = 0;
  document.querySelectorAll('.order-row').forEach(row => {
    const id       = row.dataset.id       || '';
    const name     = row.dataset.name     || '';
    const tracking = row.dataset.tracking || '';
    const matches  = !term || id.includes(term) || name.includes(term) || tracking.includes(term);
    row.style.display = matches ? '' : 'none';
    if (matches) visibleCount++;
  });
  // Show/hide empty state
  let emptyRow = document.getElementById('ordersEmptyRow');
  if (!emptyRow) {
    emptyRow = document.createElement('tr');
    emptyRow.id = 'ordersEmptyRow';
    emptyRow.innerHTML = '<td colspan="9" class="empty">No orders match your search.</td>';
    document.querySelector('.data-table tbody').appendChild(emptyRow);
  }
  emptyRow.style.display = (visibleCount === 0 && term) ? '' : 'none';
}

function showOrderDetail(o) {
  const discAmt=parseFloat(o.discount_amount||0);
  const itemsHtml=o.items.map(i=>{const sub=i.subtotal!==undefined&&i.subtotal!==null?parseFloat(i.subtotal):(parseFloat(i.price||0)*parseInt(i.quantity||0));return`<tr><td>${i.name}</td><td>₱${parseFloat(i.price||0).toFixed(2)}</td><td>${i.quantity||0}</td><td>₱${isNaN(sub)?'0.00':sub.toFixed(2)}</td></tr>`;}).join('');
  document.getElementById('orderDetailContent').innerHTML=`
    <div class="detail-meta">
      <div><strong>Order ID:</strong> ${o.id}</div>
      <div><strong>Tracking:</strong> <code>${o.tracking_code}</code></div>
      <div><strong>Customer:</strong> ${o.customer_name}</div>
      <div><strong>Contact:</strong> ${o.customer_contact}</div>
      <div><strong>Address:</strong> ${o.customer_address}</div>
      ${o.customer_email?`<div><strong>Email:</strong> ${o.customer_email}</div>`:''}
      ${o.bundle_applied?`<div><strong>Bundle Deal:</strong> 🎁 ${o.bundle_applied}</div>`:''}
      ${o.notes?`<div><strong>Notes:</strong> ${o.notes}</div>`:''}
      <div><strong>Placed:</strong> ${new Date(o.created_at).toLocaleString()}</div>
      <div><strong>Status:</strong> ${o.status}</div>
    </div>
    <table class="data-table" style="margin-top:1rem"><thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead><tbody>${itemsHtml}</tbody></table>
    <div class="detail-totals">
      <div class="detail-row"><span>Subtotal</span><span>₱${parseFloat(o.subtotal||o.total).toFixed(2)}</span></div>
      ${discAmt>0?`<div class="detail-row"><span>Discount${o.bundle_applied?' ('+o.bundle_applied+')':''}</span><span>−₱${discAmt.toFixed(2)}</span></div>`:''}
      <div class="detail-row bold"><span>Total</span><span>₱${parseFloat(o.total).toFixed(2)}</span></div>
    </div>`;
  document.getElementById('orderDetailModal').style.display='flex';
}
</script>
