<?php
// views/intelligence.php
$engine  = new PairPalEngine();
$salesR  = new SalesRepository();
$prodR   = new ProductRepository();
$pairR   = new PairPalDataRepository();

$insights    = $engine->getSalesInsights();
$bundles     = $engine->getSmartBundles(5);
$slowMovers  = $engine->getSlowMovers(8);
$restockList = $engine->getRestockSuggestions(8);
$bestSellers = $engine->getBestSellers(6);
$topPairs    = $pairR->getTopPairs(8);
?>

<div class="intelligence-layout">

  <!-- ── Rebuild Button ─────────────────────────────────────────────── -->
  <div class="intel-topbar">
    <div>
      <h2 class="intel-title">✦ PairPal Intelligence</h2>
      <p class="intel-sub text-muted">Rule-based analytics from your sales history. No external APIs.</p>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="rebuildPairPal()">⟳ Rebuild AI Data</button>
  </div>

  <!-- ── Sales Insights Panel ─────────────────────────────────────── -->
  <div class="intel-section-grid">

    <!-- Peak Sales Day -->
    <div class="intel-card">
      <div class="intel-card-label">Peak Sales Day</div>
      <div class="intel-card-value"><?= $insights['peak_day'] ?></div>
      <div class="intel-card-sub"><?= $insights['peak_day_count'] ?> transactions on this day</div>
    </div>
    <div class="intel-card accent">
      <div class="intel-card-label">This Week Revenue</div>
      <div class="intel-card-value">₱<?= number_format($insights['weekly_revenue'],2) ?></div>
    </div>
    <div class="intel-card">
      <div class="intel-card-label">This Month Revenue</div>
      <div class="intel-card-value">₱<?= number_format($insights['monthly_revenue'],2) ?></div>
    </div>

    <!-- Day-of-week bar chart -->
    <div class="intel-card card-span-3">
      <div class="intel-card-label">Sales by Day of Week</div>
      <div class="dow-chart">
        <?php
        $maxCount = max(1, max(array_column($insights['day_totals'], 'count')));
        foreach ($insights['day_totals'] as $d):
          $h = max(4, round(($d['count'] / $maxCount) * 80));
        ?>
        <div class="dow-bar-wrap">
          <div class="dow-count"><?= $d['count'] ?></div>
          <div class="dow-bar" style="height:<?= $h ?>px" title="<?= $d['name'] ?>: <?= $d['count'] ?> txns"></div>
          <div class="dow-label"><?= $d['name'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- ── Top Products & Top Pairs ───────────────────────────────────── -->
  <div class="intel-two-col">

    <div class="card">
      <div class="card-header"><span class="card-title">🔥 Top Selling Products</span></div>
      <table class="data-table compact">
        <thead><tr><th>Rank</th><th>Product</th><th>Units Sold</th><th>Revenue</th></tr></thead>
        <tbody>
        <?php foreach ($insights['top_products'] as $i => $tp): ?>
          <tr>
            <td><span class="rank-badge"><?= $i+1 ?></span></td>
            <td><?= htmlspecialchars($tp['name']) ?></td>
            <td><?= $tp['qty'] ?></td>
            <td>₱<?= number_format($tp['revenue'],2) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($insights['top_products'])): ?><tr><td colspan="4" class="empty">No data yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">⊕ Most Common Product Pairs</span></div>
      <table class="data-table compact">
        <thead><tr><th>Product A</th><th>Product B</th><th>Times Paired</th></tr></thead>
        <tbody>
        <?php foreach ($topPairs as $pair):
          [$aId, $bId] = explode('|', $pair['pair']);
          $pa = $prodR->findById($aId);
          $pb = $prodR->findById($bId);
          if (!$pa || !$pb) continue;
        ?>
          <tr>
            <td><?= htmlspecialchars($pa['name']) ?></td>
            <td><?= htmlspecialchars($pb['name']) ?></td>
            <td><span class="rank-badge"><?= $pair['count'] ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($topPairs)): ?>
          <tr><td colspan="3" class="empty">No pair data yet. <button class="btn btn-sm btn-ghost" onclick="rebuildPairPal()">Build Now</button></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Smart Bundles ──────────────────────────────────────────────── -->
  <?php if (!empty($bundles)): ?>
  <div class="card">
    <div class="card-header">
      <span class="card-title">🎁 Detected Smart Bundles</span>
      <span class="text-muted" style="font-size:.78rem">Products frequently bought together — suggest as bundle deals</span>
    </div>
    <div class="bundle-table-grid">
      <?php foreach ($bundles as $b): ?>
      <div class="bundle-row-card">
        <div class="bundle-row-products">
          <?php foreach ($b['products'] as $bp): ?>
            <div class="bundle-row-item">
              <span class="bundle-tag"><?= htmlspecialchars($bp['name']) ?></span>
              <span class="text-muted" style="font-size:.75rem">₱<?= number_format($bp['price'],2) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="bundle-row-meta">
          <span class="text-muted">Paired <?= $b['frequency'] ?>×</span>
          <span>Original: ₱<?= number_format($b['original_price'],2) ?></span>
          <span class="bundle-suggest">Bundle price: ₱<?= number_format($b['bundle_price'],2) ?></span>
          <span class="green-text">Save ₱<?= number_format($b['savings'] ?? $b['discount_amount'] ?? 0, 2) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Slow Movers ────────────────────────────────────────────────── -->
  <?php if (!empty($slowMovers)): ?>
  <div class="card">
    <div class="card-header">
      <span class="card-title">🐢 Slow-Moving Products</span>
      <span class="text-muted" style="font-size:.78rem">Low sales in past 30 days with adequate stock</span>
    </div>
    <table class="data-table compact">
      <thead><tr><th>Product</th><th>Category</th><th>Stock</th><th>Sales (30d)</th><th>Suggestion</th></tr></thead>
      <tbody>
      <?php foreach ($slowMovers as $sm): $p = $sm['product']; ?>
        <tr class="row-warn">
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td><span class="category-badge"><?= htmlspecialchars($p['category']) ?></span></td>
          <td><?= $p['stock'] ?></td>
          <td><?= $sm['recent_sales'] ?></td>
          <td class="text-muted" style="font-size:.8rem"><?= htmlspecialchars($sm['suggestion']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- ── Restock Suggestions ────────────────────────────────────────── -->
  <?php if (!empty($restockList)): ?>
  <div class="card">
    <div class="card-header">
      <span class="card-title">🔄 Restock Recommendations</span>
      <span class="text-muted" style="font-size:.78rem">Low stock + high sales velocity = restock now</span>
    </div>
    <table class="data-table compact">
      <thead><tr><th>Product</th><th>Supplier</th><th>Stock</th><th>Threshold</th><th>Units Sold</th><th>Urgency</th></tr></thead>
      <tbody>
      <?php foreach ($restockList as $item): $p = $item['product']; ?>
        <tr class="<?= $item['urgency']==='critical'?'row-danger':'row-warn' ?>">
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td class="text-muted"><?= htmlspecialchars($p['supplier'] ?? '—') ?></td>
          <td><?= $p['stock'] ?></td>
          <td><?= $p['low_stock_threshold'] ?? 8 ?></td>
          <td><?= $item['sales_qty'] ?></td>
          <td><span class="stock-badge <?= $item['urgency']==='critical'?'danger':($item['urgency']==='high'?'warn':'ok') ?>"><?= ucfirst($item['urgency']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>

<script>
async function rebuildPairPal() {
  if (!confirm('Rebuild PairPal AI data from all sales history? This may take a moment.')) return;
  const fd = new FormData(); fd.append('action','pairpal_rebuild');
  const data = await pairpalPost(fd);
  showToast(data.message, data.success ? 'success' : 'error');
  if (data.success) setTimeout(() => location.reload(), 800);
}
</script>
