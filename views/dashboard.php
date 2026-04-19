<?php
$engine      = new PairPalEngine();
$salesRepo   = new SalesRepository();
$productRepo = new ProductRepository();

try {
    $nm       = new NotificationManager();
    $lowStock = $engine->getLowStockAlerts();
    if (!empty($lowStock)) {
        $nm->generateStockAlerts($lowStock);
    }
} catch (\Throwable $e) {}

$totalRevenue = $salesRepo->getTotalRevenue();
$todayRevenue = $salesRepo->getTodayRevenue();
$allSales     = $salesRepo->getAll();
$allProducts  = $productRepo->getAll();
$topProducts  = $salesRepo->getTopProducts(5);
$lowStock     = $engine->getLowStockAlerts();
$insights     = $engine->getPairingInsights();
$insightMsg   = $engine->getInsightMessage();
$slowMovers   = $engine->getSlowMovers(3);
$restock      = $engine->getRestockSuggestions(3);
$bundles      = $engine->getSmartBundles(2);
$todaySales   = $salesRepo->getSalesByDate(date('Y-m-d'));
$recentSales  = array_slice($allSales, 0, 5);
$salesInsights= $engine->getSalesInsights();
?>
<div class="dashboard-grid">

  <div class="insight-banner card-full">
    <span class="insight-icon">✦</span>
    <span class="insight-text"><?= htmlspecialchars($insightMsg) ?></span>
    <a href="index.php?page=intelligence" class="btn btn-ghost btn-sm" style="margin-left:auto;flex-shrink:0">PairPal AI →</a>
  </div>

  <div class="stat-card">
    <div class="stat-label">Total Revenue</div>
    <div class="stat-value">₱<?= number_format($totalRevenue,2) ?></div>
    <div class="stat-sub"><?= count($allSales) ?> transactions all-time</div>
  </div>
  <div class="stat-card accent">
    <div class="stat-label">Today's Revenue</div>
    <div class="stat-value">₱<?= number_format($todayRevenue,2) ?></div>
    <div class="stat-sub"><?= count($todaySales) ?> transactions today</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">This Week</div>
    <div class="stat-value">₱<?= number_format($salesInsights['weekly_revenue'],2) ?></div>
    <div class="stat-sub">Peak day: <?= $salesInsights['peak_day'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Products / Low Stock</div>
    <div class="stat-value"><?= count($allProducts) ?> <span style="font-size:1rem;color:var(--text3)">/ <?= count($lowStock) ?></span></div>
    <div class="stat-sub"><?= count($lowStock) ?> need attention</div>
  </div>

  <div class="card card-half">
    <div class="card-header">
      <span class="card-title">🔥 Top Sellers</span>
      <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="index.php?page=reports" class="btn btn-ghost btn-sm">Report</a>
      <?php endif; ?>
    </div>
    <table class="data-table compact">
      <thead><tr><th>Product</th><th>Units</th><th>Revenue</th></tr></thead>
      <tbody>
      <?php foreach ($topProducts as $i => $tp): ?>
        <tr><td><span class="rank-badge"><?= $i+1 ?></span> <?= htmlspecialchars($tp['name']) ?></td><td><?= $tp['qty'] ?></td><td>₱<?= number_format($tp['revenue'],2) ?></td></tr>
      <?php endforeach; ?>
      <?php if (empty($topProducts)): ?><tr><td colspan="3" class="empty">No sales yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="card card-half">
    <div class="card-header">
      <span class="card-title">⚠️ Low Stock</span>
      <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="index.php?page=inventory" class="btn btn-ghost btn-sm">Manage</a>
      <?php endif; ?>
    </div>
    <?php if (empty($lowStock)): ?>
      <p class="empty-state">All products are well-stocked ✓</p>
    <?php else: ?>
    <table class="data-table compact">
      <thead><tr><th>Product</th><th>Category</th><th>Stock</th></tr></thead>
      <tbody>
      <?php foreach ($lowStock as $p): $crit = $p['stock'] <= max(1,intval(($p['low_stock_threshold']??8)*0.5)); ?>
        <tr class="<?= $crit?'row-danger':'row-warn' ?>">
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td><?= htmlspecialchars($p['category']) ?></td>
          <td><span class="stock-badge <?= $crit?'danger':'warn' ?>"><?= $p['stock'] ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <?php if (!empty($restock) || !empty($slowMovers)): ?>
  <div class="card card-half">
    <div class="card-header"><span class="card-title">🔄 Urgent Restock</span><a href="index.php?page=intelligence" class="btn btn-ghost btn-sm">All</a></div>
    <?php if (empty($restock)): ?><p class="empty-state">No urgent restocks.</p><?php else: ?>
    <table class="data-table compact">
      <thead><tr><th>Product</th><th>Stock</th><th>Urgency</th></tr></thead>
      <tbody>
      <?php foreach ($restock as $item): $p = $item['product']; ?>
        <tr class="<?= $item['urgency']==='critical'?'row-danger':'row-warn' ?>">
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td><?= $p['stock'] ?></td>
          <td><span class="stock-badge <?= $item['urgency']==='critical'?'danger':($item['urgency']==='high'?'warn':'ok') ?>"><?= ucfirst($item['urgency']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <div class="card card-half">
    <div class="card-header"><span class="card-title">🐢 Slow Movers</span><a href="index.php?page=intelligence" class="btn btn-ghost btn-sm">All</a></div>
    <?php if (empty($slowMovers)): ?><p class="empty-state">No slow-moving products.</p><?php else: ?>
    <table class="data-table compact">
      <thead><tr><th>Product</th><th>Stock</th><th>Sales (30d)</th></tr></thead>
      <tbody>
      <?php foreach ($slowMovers as $sm): $p = $sm['product']; ?>
        <tr class="row-warn">
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td><?= $p['stock'] ?></td>
          <td><?= $sm['recent_sales'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($insights)): ?>
  <div class="card card-full">
    <div class="card-header">
      <span class="card-title">✦ PairPal AI — Frequently Bought Together</span>
      <a href="index.php?page=intelligence" class="btn btn-ghost btn-sm">Full Analysis</a>
    </div>
    <div class="pairing-grid">
    <?php foreach ($insights as $ins): ?>
      <div class="pairing-card">
        <div class="pairing-products">
          <span class="pairing-tag"><?= htmlspecialchars($ins['products'][0]['name']) ?></span>
          <span class="pairing-plus">+</span>
          <span class="pairing-tag"><?= htmlspecialchars($ins['products'][1]['name']) ?></span>
        </div>
        <div class="pairing-freq">Paired <?= $ins['frequency'] ?> time<?= $ins['frequency'] > 1 ? 's' : '' ?></div>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($bundles)): ?>
  <div class="card card-full">
    <div class="card-header"><span class="card-title">🎁 Smart Bundle Opportunities</span></div>
    <div class="pairing-grid">
    <?php foreach ($bundles as $b): ?>
      <div class="pairing-card">
        <div class="pairing-products">
          <?php foreach ($b['products'] as $i => $bp): ?>
            <?php if ($i > 0): ?><span class="pairing-plus">+</span><?php endif; ?>
            <span class="pairing-tag"><?= htmlspecialchars($bp['name']) ?></span>
          <?php endforeach; ?>
        </div>
        <div class="pairing-freq">
          Suggest bundle at ₱<?= number_format($b['bundle_price'],2) ?> · saves ₱<?= number_format($b['savings'] ?? $b['discount_amount'] ?? 0, 2) ?>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="card card-full">
    <div class="card-header">
      <span class="card-title">◷ Recent Transactions</span>
      <a href="index.php?page=history" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>ID</th><th>Cashier</th><th>Items</th><th>Discount</th><th>Total</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach ($recentSales as $s): $disc = (float)($s['discount_amount'] ?? 0); ?>
        <tr>
          <td><code><?= htmlspecialchars($s['id']) ?></code></td>
          <td><?= htmlspecialchars($s['cashier_name']) ?></td>
          <td><?= count($s['items']) ?> item<?= count($s['items'])!==1?'s':'' ?></td>
          <td><?= $disc > 0 ? '<span class="discount-pill">−₱'.number_format($disc,2).'</span>' : '<span class="text-muted">—</span>' ?></td>
          <td>₱<?= number_format($s['total'],2) ?></td>
          <td class="text-muted"><?= date('M j, Y g:i a', strtotime($s['date'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($recentSales)): ?><tr><td colspan="6" class="empty">No transactions yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>

</div>
