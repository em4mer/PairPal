<?php
// views/products.php
$ctrl         = new ProductController();
$engine       = new PairPalEngine();
$q            = trim($_GET['q'] ?? '');
$cat          = $_GET['cat']      ?? '';
$sort         = $_GET['sort']     ?? '';
$supplier     = $_GET['supplier'] ?? '';
$popMap       = $engine->getProductPopularityMap();
$products     = $ctrl->search($q, $cat, $sort, $supplier, $sort === 'popular_desc' ? $popMap : []);
$categories   = $ctrl->getCategories();
$suppliers    = $ctrl->getSuppliers();
$lowStock     = $engine->getLowStockAlerts();
$lowStockIds  = array_column($lowStock, 'id');

// Pagination
$perPage     = 20;
$totalProds  = count($products);
$totalPages  = max(1, (int)ceil($totalProds / $perPage));
$currentPage = max(1, min($totalPages, (int)($_GET['paged'] ?? 1)));
$offset      = ($currentPage - 1) * $perPage;
$products    = array_slice($products, $offset, $perPage);
?>

<div class="page-controls">
  <form method="GET" class="search-bar" id="searchForm">
    <input type="hidden" name="page" value="products">
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search name, category, supplier…" oninput="debounceSearch()" id="searchInput">
    <select name="cat" onchange="this.form.submit()">
      <option value="">All Categories</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= htmlspecialchars($c) ?>" <?= $cat===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="supplier" onchange="this.form.submit()">
      <option value="">All Suppliers</option>
      <?php foreach ($suppliers as $s): ?>
        <option value="<?= htmlspecialchars($s) ?>" <?= $supplier===$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="sort" onchange="this.form.submit()">
      <option value="">Default sort</option>
      <option value="price_asc"    <?= $sort==='price_asc'?'selected':'' ?>>Price ↑</option>
      <option value="price_desc"   <?= $sort==='price_desc'?'selected':'' ?>>Price ↓</option>
      <option value="stock_asc"    <?= $sort==='stock_asc'?'selected':'' ?>>Stock ↑</option>
      <option value="stock_desc"   <?= $sort==='stock_desc'?'selected':'' ?>>Stock ↓</option>
      <option value="popular_desc" <?= $sort==='popular_desc'?'selected':'' ?>>Most Popular</option>
    </select>
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($q||$cat||$supplier||$sort): ?>
      <a href="index.php?page=products" class="btn btn-ghost">Clear</a>
    <?php endif; ?>
  </form>
  <div class="btn-group">
    <button class="btn btn-ghost btn-sm" onclick="openBulkImport()">⬆ Import JSON</button>
    <button class="btn btn-accent" onclick="openProductModal()">+ Add Product</button>
  </div>
</div>

<?php if (!empty($lowStock)): ?>
<div class="alert-strip">
  <span class="alert-strip-icon">⚠️</span>
  <span><?= count($lowStock) ?> product<?= count($lowStock)!==1?'s':'' ?> need restocking:</span>
  <?php foreach (array_slice($lowStock,0,3) as $p): ?>
    <span class="stock-badge <?= $p['stock']<=max(1,intval(($p['low_stock_threshold']??8)*0.5))?'danger':'warn' ?>"><?= htmlspecialchars($p['name']) ?> (<?= $p['stock'] ?>)</span>
  <?php endforeach; ?>
  <?php if (count($lowStock)>3): ?><span class="text-muted">+<?= count($lowStock)-3 ?> more</span><?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <span class="card-title">Products <span class="count-badge"><?= count($products) ?></span></span>
  </div>
  <div class="table-wrap">
  <table class="data-table" id="productTable">
    <thead>
      <tr>
        <th style="width:40px">#</th>
        <th>Image</th>
        <th>Name</th>
        <th>Category</th>
        <th>Supplier</th>
        <th>Price</th>
        <th>Stock</th>
        <th>Threshold</th>
        <th>Popularity</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($products as $i => $p):
      $isLow = in_array($p['id'], $lowStockIds);
      $isCrit = $isLow && $p['stock'] <= max(1, intval(($p['low_stock_threshold']??8)*0.5));
    ?>
      <tr class="<?= $isCrit?'row-danger':($isLow?'row-warn':'') ?>">
        <td><?= $i+1 ?></td>
        <td class="img-cell">
          <?php if (!empty($p['image'])): ?>
            <img src="<?= htmlspecialchars($p['image']) ?>" class="product-thumb" alt="" loading="lazy">
          <?php else: ?>
            <div class="product-thumb-placeholder"><?= strtoupper(substr($p['name'],0,1)) ?></div>
          <?php endif; ?>
        </td>
        <td>
          <strong><?= htmlspecialchars($p['name']) ?></strong>
          <?php if (!empty($p['description'])): ?><br><small class="text-muted"><?= htmlspecialchars(substr($p['description'],0,50)) ?><?= strlen($p['description'])>50?'…':'' ?></small><?php endif; ?>
        </td>
        <td><span class="category-badge"><?= htmlspecialchars($p['category']) ?></span></td>
        <td class="text-muted"><?= htmlspecialchars($p['supplier'] ?? '—') ?></td>
        <td>₱<?= number_format($p['price'],2) ?></td>
        <td><span class="stock-badge <?= $isCrit?'danger':($isLow?'warn':'ok') ?>"><?= $p['stock'] ?></span></td>
        <td class="text-muted"><?= $p['low_stock_threshold'] ?? 8 ?></td>
        <td>
          <?php $pop = $popMap[$p['id']] ?? 0; ?>
          <?php if ($pop > 0): ?>
            <span class="pop-bar"><span style="width:<?= min(100, $pop*5) ?>%"></span></span>
            <small class="text-muted"><?= $pop ?> sold</small>
          <?php else: ?>
            <small class="text-muted">—</small>
          <?php endif; ?>
        </td>
        <td class="action-cell">
          <button class="btn btn-sm btn-ghost"         onclick='openEditModal(<?= json_encode($p) ?>)'>Edit</button>
          <button class="btn btn-sm btn-accent"        onclick="openStockModal('<?= $p['id'] ?>','<?= htmlspecialchars(addslashes($p['name'])) ?>',<?= $p['stock'] ?>)">Stock</button>
          <button class="btn btn-sm btn-danger"        onclick="deleteProduct('<?= $p['id'] ?>','<?= htmlspecialchars(addslashes($p['name'])) ?>')">Delete</button>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($products)): ?>
      <tr><td colspan="10" class="empty">No products found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination-bar">
  <span class="pag-info">Showing <?= $offset+1 ?>–<?= min($offset+$perPage,$totalProds) ?> of <?= $totalProds ?> products</span>
  <div class="pag-links">
    <?php if ($currentPage > 1): ?>
      <a href="?<?= http_build_query(array_merge($_GET,['paged'=>$currentPage-1])) ?>" class="pag-btn">‹ Prev</a>
    <?php endif; ?>
    <?php for ($pg=max(1,$currentPage-2); $pg<=min($totalPages,$currentPage+2); $pg++): ?>
      <a href="?<?= http_build_query(array_merge($_GET,['paged'=>$pg])) ?>"
         class="pag-btn <?= $pg===$currentPage?'pag-active':'' ?>"><?= $pg ?></a>
    <?php endfor; ?>
    <?php if ($currentPage < $totalPages): ?>
      <a href="?<?= http_build_query(array_merge($_GET,['paged'=>$currentPage+1])) ?>" class="pag-btn">Next ›</a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── Product Modal ──────────────────────────────────────────────────── -->
<div id="productModal" class="modal-overlay" style="display:none" onclick="closeProductModal(event)">
  <div class="modal modal-lg" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h2 id="modalTitle">Add Product</h2>
      <button class="modal-close" onclick="closeProductModal()">✕</button>
    </div>
    <form id="productForm" onsubmit="saveProduct(event)" enctype="multipart/form-data">
      <input type="hidden" id="pId"     name="id">
      <input type="hidden" id="pAction" name="action" value="product_create">
      <div class="form-grid form-grid-3">
        <div class="form-group">
          <label>Name *</label>
          <input type="text" id="pName" name="name" required placeholder="Product name">
        </div>
        <div class="form-group">
          <label>Category *</label>
          <input type="text" id="pCategory" name="category" required placeholder="e.g. Beverages" list="categoryList">
          <datalist id="categoryList"><?php foreach ($categories as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?php endforeach; ?></datalist>
        </div>
        <div class="form-group">
          <label>Supplier</label>
          <input type="text" id="pSupplier" name="supplier" placeholder="Supplier name" list="supplierList">
          <datalist id="supplierList"><?php foreach ($suppliers as $s): ?><option value="<?= htmlspecialchars($s) ?>"><?php endforeach; ?></datalist>
        </div>
        <div class="form-group">
          <label>Price (₱) *</label>
          <input type="number" id="pPrice" name="price" required step="0.01" min="0.01" placeholder="0.00">
        </div>
        <div class="form-group">
          <label>Stock *</label>
          <input type="number" id="pStock" name="stock" required min="0" placeholder="0">
        </div>
        <div class="form-group">
          <label>Low Stock Threshold</label>
          <input type="number" id="pThreshold" name="low_stock_threshold" min="1" value="8" placeholder="8">
        </div>
        <div class="form-group form-full">
          <label>Description</label>
          <textarea id="pDesc" name="description" rows="2" placeholder="Optional product description"></textarea>
        </div>
        <div class="form-group form-full">
          <label>Product Image <small class="text-muted">(JPG/PNG/WebP, max 2MB)</small></label>
          <input type="file" name="image" id="pImage" accept="image/jpeg,image/png,image/webp">
          <div id="imagePreview" class="image-preview-wrap" style="display:none">
            <img id="imagePreviewImg" src="" alt="Preview">
          </div>
        </div>
      </div>
      <div id="productFormError" class="alert alert-error" style="display:none"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeProductModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" id="productSaveBtn">Save Product</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Stock Adjust Modal ─────────────────────────────────────────────── -->
<div id="stockModal" class="modal-overlay" style="display:none" onclick="this.style.display='none'">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h2>Adjust Stock — <span id="stockModalName"></span></h2>
      <button class="modal-close" onclick="document.getElementById('stockModal').style.display='none'">✕</button>
    </div>
    <form onsubmit="submitStockAdjust(event)" style="padding:1.5rem;gap:1rem;display:flex;flex-direction:column">
      <input type="hidden" id="stockProductId">
      <div class="form-group">
        <label>Current Stock: <strong id="stockCurrent"></strong></label>
      </div>
      <div class="form-group">
        <label>New Stock Value *</label>
        <input type="number" id="newStockVal" min="0" required placeholder="Enter new stock quantity">
      </div>
      <div class="form-group">
        <label>Reason / Note</label>
        <input type="text" id="stockNote" placeholder="e.g. Received shipment, Damaged goods…">
      </div>
      <div id="stockAdjustError" class="alert alert-error" style="display:none"></div>
      <div class="modal-footer" style="padding:0;border:none">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('stockModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Stock</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Bulk Import Modal ──────────────────────────────────────────────── -->
<div id="bulkModal" class="modal-overlay" style="display:none" onclick="this.style.display='none'">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h2>Bulk Import Products</h2>
      <button class="modal-close" onclick="document.getElementById('bulkModal').style.display='none'">✕</button>
    </div>
    <div style="padding:1.5rem;display:flex;flex-direction:column;gap:1rem">
      <p class="text-muted" style="font-size:.85rem">Upload a JSON array of products. Required fields: <code>name</code>, <code>category</code>, <code>price</code>. Optional: <code>stock</code>, <code>supplier</code>, <code>description</code>, <code>low_stock_threshold</code>.</p>
      <details style="font-size:.8rem">
        <summary class="text-muted" style="cursor:pointer">Sample JSON format</summary>
        <pre class="code-block">[{"name":"Sample Product","category":"Coffee","price":150,"stock":20,"supplier":"My Supplier","low_stock_threshold":5}]</pre>
      </details>
      <div class="form-group">
        <label>JSON File *</label>
        <input type="file" id="importFile" accept=".json,application/json">
      </div>
      <div id="bulkImportError" class="alert alert-error" style="display:none"></div>
      <div class="modal-footer" style="padding:0;border:none">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('bulkModal').style.display='none'">Cancel</button>
        <button class="btn btn-primary" onclick="submitBulkImport()">Import Products</button>
      </div>
    </div>
  </div>
</div>

<script>
let searchTimer;
function debounceSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => document.getElementById('searchForm').submit(), 400);
}

function openProductModal() {
  document.getElementById('modalTitle').textContent = 'Add Product';
  document.getElementById('productForm').reset();
  document.getElementById('pAction').value = 'product_create';
  document.getElementById('pId').value = '';
  document.getElementById('pThreshold').value = 8;
  document.getElementById('imagePreview').style.display = 'none';
  document.getElementById('productFormError').style.display = 'none';
  document.getElementById('productModal').style.display = 'flex';
}

function openEditModal(p) {
  document.getElementById('modalTitle').textContent = 'Edit Product';
  document.getElementById('pAction').value = 'product_update';
  document.getElementById('pId').value       = p.id;
  document.getElementById('pName').value     = p.name;
  document.getElementById('pCategory').value = p.category;
  document.getElementById('pSupplier').value = p.supplier || '';
  document.getElementById('pPrice').value    = p.price;
  document.getElementById('pStock').value    = p.stock;
  document.getElementById('pThreshold').value= p.low_stock_threshold || 8;
  document.getElementById('pDesc').value     = p.description || '';
  document.getElementById('productFormError').style.display = 'none';
  if (p.image) {
    document.getElementById('imagePreviewImg').src = p.image;
    document.getElementById('imagePreview').style.display = 'block';
  } else {
    document.getElementById('imagePreview').style.display = 'none';
  }
  document.getElementById('productModal').style.display = 'flex';
}

document.getElementById('pImage')?.addEventListener('change', function() {
  const f = this.files[0];
  if (!f) return;
  const url = URL.createObjectURL(f);
  document.getElementById('imagePreviewImg').src = url;
  document.getElementById('imagePreview').style.display = 'block';
});

function closeProductModal(e) {
  if (!e || e.target === document.getElementById('productModal')) {
    document.getElementById('productModal').style.display = 'none';
  }
}

async function saveProduct(e) {
  e.preventDefault();
  const btn = document.getElementById('productSaveBtn');
  const errEl = document.getElementById('productFormError');
  btn.disabled = true; btn.textContent = 'Saving…';
  errEl.style.display = 'none';
  const fd = new FormData(e.target);
  const data = await pairpalPost(fd);
  btn.disabled = false; btn.textContent = 'Save Product';
  if (data.success) {
    showToast(data.message, 'success');
    closeProductModal();
    setTimeout(() => location.reload(), 600);
  } else {
    errEl.textContent = (data.errors || [data.message]).join(' ');
    errEl.style.display = 'block';
  }
}

function openStockModal(id, name, current) {
  document.getElementById('stockProductId').value = id;
  document.getElementById('stockModalName').textContent = name;
  document.getElementById('stockCurrent').textContent   = current;
  document.getElementById('newStockVal').value = current;
  document.getElementById('stockNote').value   = '';
  document.getElementById('stockAdjustError').style.display = 'none';
  document.getElementById('stockModal').style.display = 'flex';
}

async function submitStockAdjust(e) {
  e.preventDefault();
  const id    = document.getElementById('stockProductId').value;
  const stock = document.getElementById('newStockVal').value;
  const note  = document.getElementById('stockNote').value;
  const errEl = document.getElementById('stockAdjustError');
  errEl.style.display = 'none';
  const fd = new FormData();
  fd.append('action','product_adjust_stock'); fd.append('id',id);
  fd.append('new_stock', stock); fd.append('note', note);
  const data = await pairpalPost(fd);
  if (data.success) {
    showToast('Stock updated successfully.','success');
    document.getElementById('stockModal').style.display='none';
    setTimeout(() => location.reload(), 600);
  } else {
    errEl.textContent = data.message;
    errEl.style.display = 'block';
  }
}

function openBulkImport() { document.getElementById('bulkModal').style.display = 'flex'; }

async function submitBulkImport() {
  const fileInput = document.getElementById('importFile');
  const errEl = document.getElementById('bulkImportError');
  errEl.style.display = 'none';
  if (!fileInput.files[0]) { errEl.textContent = 'Please select a JSON file.'; errEl.style.display = 'block'; return; }
  const fd = new FormData();
  fd.append('action','product_bulk_import');
  fd.append('import_file', fileInput.files[0]);
  const data = await pairpalPost(fd);
  if (data.success) {
    showToast(`Import done: ${data.added} added, ${data.skipped} skipped.`, 'success');
    document.getElementById('bulkModal').style.display = 'none';
    setTimeout(() => location.reload(), 800);
  } else {
    errEl.textContent = data.message;
    errEl.style.display = 'block';
  }
}

async function deleteProduct(id, name) {
  if (!confirm(`Delete "${name}"? This cannot be undone.`)) return;
  const fd = new FormData();
  fd.append('action','product_delete'); fd.append('id',id);
  const data = await pairpalPost(fd);
  showToast(data.message, data.success ? 'success' : 'error');
  if (data.success) setTimeout(() => location.reload(), 600);
}
</script>
