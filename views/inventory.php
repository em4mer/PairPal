<?php
// views/inventory.php
$ctrl        = new ProductController();
$productId   = $_GET['product_id'] ?? '';
$logs        = $productId ? $ctrl->getInventoryLogs($productId) : $ctrl->getInventoryLogs();
$products    = $ctrl->getAll();
$engine      = new PairPalEngine();
$restockList = $engine->getRestockSuggestions(8);

$changeTypeLabels = [
    'sale'          => ['label' => 'Sale',          'cls' => 'type-sale'],
    'manual_add'    => ['label' => 'Manual Add',    'cls' => 'type-add'],
    'manual_remove' => ['label' => 'Manual Remove', 'cls' => 'type-remove'],
    'manual_update' => ['label' => 'Manual Update', 'cls' => 'type-update'],
    'import'        => ['label' => 'Bulk Import',   'cls' => 'type-import'],
];
?>

<div class="inventory-layout">

  <!-- Restock Suggestions -->
  <?php if (!empty($restockList)): ?>
  <div class="card card-full restock-panel">
    <div class="card-header">
      <span class="card-title">🔄 Restock Suggestions — <small class="text-muted">Ranked by urgency × sales velocity</small></span>
    </div>
    <div class="restock-grid">
      <?php foreach ($restockList as $item): $p = $item['product']; ?>
      <div class="restock-card urgency-<?= $item['urgency'] ?>">
        <div class="restock-name"><?= htmlspecialchars($p['name']) ?></div>
        <div class="restock-meta">
          <span class="stock-badge <?= $item['urgency']==='critical'?'danger':($item['urgency']==='high'?'warn':'ok') ?>">
            <?= $item['urgency'] ?>
          </span>
          <span class="text-muted" style="font-size:.78rem"><?= $p['stock'] ?> left · <?= $item['sales_qty'] ?> sold</span>
        </div>
        <div class="restock-supplier text-muted"><?= htmlspecialchars($p['supplier'] ?? 'Unknown supplier') ?></div>
        <button class="btn btn-sm btn-accent" onclick="openStockQuick('<?= $p['id'] ?>','<?= htmlspecialchars(addslashes($p['name'])) ?>',<?= $p['stock'] ?>)">
          + Restock
        </button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Filter + Log Table -->
  <div class="card card-full">
    <div class="card-header">
      <span class="card-title">Inventory Log</span>
      <form method="GET" style="display:flex;gap:.5rem;align-items:center">
        <input type="hidden" name="page" value="inventory">
        <select name="product_id" onchange="this.form.submit()" style="width:220px">
          <option value="">All Products</option>
          <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $productId===$p['id']?'selected':'' ?>><?= htmlspecialchars($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($productId): ?>
          <a href="index.php?page=inventory" class="btn btn-ghost btn-sm">Clear</a>
        <?php endif; ?>
      </form>
    </div>
    <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr><th>Date</th><th>Product</th><th>Change Type</th><th>Qty Changed</th><th>Before</th><th>After</th><th>Note</th></tr>
      </thead>
      <tbody>
      <?php foreach ($logs as $log):
        $tc = $changeTypeLabels[$log['change_type']] ?? ['label' => $log['change_type'], 'cls' => ''];
        $sign = $log['quantity_changed'] >= 0 ? '+' : '';
      ?>
        <tr>
          <td class="text-muted"><?= date('M j, Y g:i a', strtotime($log['date'])) ?></td>
          <td><?= htmlspecialchars($log['product_name']) ?></td>
          <td><span class="type-badge <?= $tc['cls'] ?>"><?= $tc['label'] ?></span></td>
          <td class="<?= $log['quantity_changed'] < 0 ? 'qty-neg' : 'qty-pos' ?>">
            <?= $sign . $log['quantity_changed'] ?>
          </td>
          <td><?= $log['stock_before'] ?></td>
          <td><?= $log['stock_after'] ?></td>
          <td class="text-muted"><?= htmlspecialchars($log['note'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($logs)): ?>
        <tr><td colspan="7" class="empty">No inventory log entries yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>

</div>

<!-- Quick Restock Modal -->
<div id="stockQuickModal" class="modal-overlay" style="display:none" onclick="this.style.display='none'">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h2>Restock — <span id="sqName"></span></h2>
      <button class="modal-close" onclick="document.getElementById('stockQuickModal').style.display='none'">✕</button>
    </div>
    <div style="padding:1.5rem;display:flex;flex-direction:column;gap:1rem">
      <div class="form-group">
        <label>Current Stock: <strong id="sqCurrent"></strong></label>
      </div>
      <div class="form-group">
        <label>New Stock Value *</label>
        <input type="number" id="sqNewStock" min="0" required placeholder="Enter new stock level">
      </div>
      <div class="form-group">
        <label>Note</label>
        <input type="text" id="sqNote" value="Restock received" placeholder="e.g. Received from supplier">
      </div>
      <div id="sqError" class="alert alert-error" style="display:none"></div>
      <div class="modal-footer" style="padding:0;border:none">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('stockQuickModal').style.display='none'">Cancel</button>
        <button class="btn btn-primary" onclick="submitQuickRestock()">Confirm Restock</button>
      </div>
    </div>
  </div>
</div>

<script>
let sqProductId = '';
function openStockQuick(id, name, current) {
  sqProductId = id;
  document.getElementById('sqName').textContent    = name;
  document.getElementById('sqCurrent').textContent = current;
  document.getElementById('sqNewStock').value       = '';
  document.getElementById('sqError').style.display  = 'none';
  document.getElementById('stockQuickModal').style.display = 'flex';
}
async function submitQuickRestock() {
  const stock = document.getElementById('sqNewStock').value;
  const note  = document.getElementById('sqNote').value;
  const errEl = document.getElementById('sqError');
  if (!stock) { errEl.textContent='Enter a new stock value.'; errEl.style.display='block'; return; }
  const fd = new FormData();
  fd.append('action','product_adjust_stock'); fd.append('id', sqProductId);
  fd.append('new_stock', stock); fd.append('note', note);
  const data = await pairpalPost(fd);
  if (data.success) {
    showToast('Stock updated!','success');
    document.getElementById('stockQuickModal').style.display = 'none';
    setTimeout(() => location.reload(), 600);
  } else {
    errEl.textContent = data.message; errEl.style.display = 'block';
  }
}
</script>
