<?php
// views/bundles.php
$bundleRepo  = new BundleRepository();
$engine      = new PairPalEngine();
$productRepo = new ProductRepository();
$allBundles  = $bundleRepo->getAll();
$activeCnt   = count(array_filter($allBundles, fn($b) => ($b['status']??'active') === 'active'));
?>
<div class="page-controls">
  <div class="topbar-stat">
    <strong><?= count($allBundles) ?></strong> bundles total &nbsp;·&nbsp; <strong><?= $activeCnt ?></strong> active
  </div>
  <button class="btn btn-accent" onclick="rebuildBundles()">⟳ Rebuild from Sales Data</button>
</div>

<?php if (empty($allBundles)): ?>
<div class="card" style="text-align:center;padding:3rem">
  <p class="text-muted">No bundles yet. Click "Rebuild from Sales Data" to generate bundles automatically from purchase history.</p>
  <button class="btn btn-primary" style="margin-top:1rem" onclick="rebuildBundles()">Generate Bundles</button>
</div>
<?php else: ?>

<div class="bundles-admin-grid">
  <?php foreach ($allBundles as $b): 
    $isActive = ($b['status']??'active') === 'active';
    $isPromo  = ($b['promo_type']??'') === 'slow_mover';
  ?>
  <div class="bundle-admin-card <?= $isActive?'':'bundle-inactive' ?> <?= $isPromo?'bundle-promo':'' ?>">
    <div class="bundle-admin-header">
      <span class="bundle-admin-name" id="bname_<?= $b['id'] ?>"><?= htmlspecialchars($b['name'] ?? 'Unnamed Bundle') ?></span>
      <input type="text" class="bundle-name-input" id="bnameinput_<?= $b['id'] ?>"
             value="<?= htmlspecialchars($b['name'] ?? '') ?>" maxlength="80" style="display:none"
             onkeydown="if(event.key==='Enter')saveBundleName('<?= $b['id'] ?>');if(event.key==='Escape')cancelBundleNameEdit('<?= $b['id'] ?>')">
      <?php if ($isPromo): ?><span class="promo-tag">🐢 Slow Mover Promo</span><?php endif; ?>
      <?php if ($b['auto_generated']??false): ?><span class="auto-tag">Auto</span><?php endif; ?>
      <span class="type-badge <?= $isActive?'type-add':'danger' ?>"><?= $isActive?'Active':'Disabled' ?></span>
      <button class="btn btn-sm btn-ghost" id="bnamebtn_<?= $b['id'] ?>"
              onclick="editBundleName('<?= $b['id'] ?>')" title="Rename bundle" style="padding:2px 6px;font-size:.72rem">✏</button>
      <button class="btn btn-sm btn-primary" id="bnamesavebtn_<?= $b['id'] ?>"
              onclick="saveBundleName('<?= $b['id'] ?>')" style="display:none;padding:2px 8px;font-size:.72rem">Save</button>
      <button class="btn btn-sm btn-ghost" id="bnamecancelbtn_<?= $b['id'] ?>"
              onclick="cancelBundleNameEdit('<?= $b['id'] ?>')" style="display:none;padding:2px 6px;font-size:.72rem">✕</button>
    </div>
    <div class="bundle-admin-products">
      <?php foreach ($b['product_ids']??[] as $pid): $p=$productRepo->findById($pid); ?>
        <span class="bundle-tag"><?= htmlspecialchars($p?$p['name']:$pid) ?></span>
      <?php endforeach; ?>
    </div>
    <div class="bundle-admin-meta">
      <div>Frequency: <strong><?= $b['frequency']??0 ?>×</strong></div>
      <div>Original: <strong>₱<?= number_format($b['original_price']??0,2) ?></strong></div>
      <div>Discount: <strong><?= $b['discount_type']==='percent'?($b['discount_value']??0).'%':'₱'.($b['discount_value']??0) ?></strong></div>
      <div>Bundle Price: <strong class="green-text">₱<?= number_format($b['bundle_price']??0,2) ?></strong></div>
      <div>Saves: <strong class="green-text">₱<?= number_format($b['discount_amount']??0,2) ?></strong></div>
    </div>
    <div class="bundle-admin-actions">
      <?php if ($isActive): ?>
        <button class="btn btn-sm btn-ghost" onclick="setBundleStatus('<?= $b['id'] ?>','disabled')">Disable</button>
      <?php else: ?>
        <button class="btn btn-sm btn-accent" onclick="setBundleStatus('<?= $b['id'] ?>','active')">Enable</button>
      <?php endif; ?>
      <button class="btn btn-sm btn-danger" onclick="deleteBundle('<?= $b['id'] ?>','<?= htmlspecialchars(addslashes($b['name']??'')) ?>')">Delete</button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
async function rebuildBundles() {
  if(!confirm('Rebuild all bundles from sales data? Existing auto-bundles will be updated.')) return;
  const fd=new FormData(); fd.append('action','pairpal_rebuild');
  const data = await pairpalPost(fd);
  showToast(data.message,data.success?'success':'error');
  if(data.success) setTimeout(()=>location.reload(),800);
}

async function setBundleStatus(id, status) {
  const fd=new FormData(); fd.append('action','bundle_set_status'); fd.append('id',id); fd.append('status',status);
  const data = await pairpalPost(fd);
  showToast(status==='active'?'Bundle enabled.':'Bundle disabled.',data.success?'success':'error');
  if(data.success) setTimeout(()=>location.reload(),600);
}

async function deleteBundle(id, name) {
  if(!confirm(`Delete bundle "${name}"?`)) return;
  const fd=new FormData(); fd.append('action','bundle_delete'); fd.append('id',id);
  const data = await pairpalPost(fd);
  showToast(data.message,data.success?'success':'error');
  if(data.success) setTimeout(()=>location.reload(),600);
}

function editBundleName(id) {
  document.getElementById('bname_'        + id).style.display = 'none';
  document.getElementById('bnameinput_'   + id).style.display = 'inline-block';
  document.getElementById('bnamebtn_'     + id).style.display = 'none';
  document.getElementById('bnamesavebtn_' + id).style.display = 'inline-block';
  document.getElementById('bnamecancelbtn_'+id).style.display = 'inline-block';
  document.getElementById('bnameinput_'   + id).focus();
  document.getElementById('bnameinput_'   + id).select();
}

function cancelBundleNameEdit(id) {
  document.getElementById('bname_'         + id).style.display = '';
  document.getElementById('bnameinput_'    + id).style.display = 'none';
  document.getElementById('bnamebtn_'      + id).style.display = '';
  document.getElementById('bnamesavebtn_'  + id).style.display = 'none';
  document.getElementById('bnamecancelbtn_'+ id).style.display = 'none';
}

async function saveBundleName(id) {
  const newName = document.getElementById('bnameinput_' + id).value.trim();
  if (!newName) { showToast('Name cannot be empty.','error'); return; }
  const fd = new FormData();
  fd.append('action','bundle_rename'); fd.append('id',id); fd.append('name',newName);
  const data = await pairpalPost(fd);
  if (data.success) {
    document.getElementById('bname_' + id).textContent = newName;
    cancelBundleNameEdit(id);
    showToast('Bundle renamed.','success');
  } else {
    showToast(data.message||'Failed.','error');
  }
}
</script>
