<?php
// views/reports.php
$from   = $_GET['from'] ?? '';
$to     = $_GET['to']   ?? '';
$ctrl   = new ReportController();
$sum    = $ctrl->getSummary($from, $to);
$engine = new PairPalEngine();
$pairR  = new PairPalDataRepository();

$salesRepo   = new SalesRepository();
$productRepo = new ProductRepository();
$logRepo     = new InventoryLogRepository();

$slowMovers = $engine->getSlowMovers(5);
$topPairs   = $pairR->getTopPairs(5);
$invLogs    = $logRepo->getAll();

// Chart data — take last 14 days reversed so oldest is left
$dailySlice    = array_slice(array_reverse($sum['daily_summary']), 0, 14);
$dailyDates    = array_column($dailySlice, 'date');
$dailyRevenue  = array_map('floatval', array_column($dailySlice, 'total'));
$dailyCounts   = array_map('intval',   array_column($dailySlice, 'count'));

$monthlySlice  = array_slice(array_reverse($sum['monthly_summary']), 0, 8);
$monthlyLabels = array_column($monthlySlice, 'month');
$monthlyData   = array_map('floatval', array_column($monthlySlice, 'total'));
$monthlyCounts = array_map('intval',   array_column($monthlySlice, 'count'));

$topProds      = array_slice($sum['top_products'], 0, 8);
$topProdNames  = array_map(fn($p) => $p['name'], $topProds);
$topProdQty    = array_map(fn($p) => (int)$p['qty'], $topProds);
$topProdRev    = array_map(fn($p) => (float)$p['revenue'], $topProds);
?>

<div class="reports-header">
  <form method="GET" class="search-bar" style="flex:1">
    <input type="hidden" name="page" value="reports">
    <label class="form-label-inline">From</label>
    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
    <label class="form-label-inline">To</label>
    <input type="date" name="to"   value="<?= htmlspecialchars($to) ?>">
    <button type="submit" class="btn btn-primary">Apply</button>
    <?php if ($from||$to): ?><a href="index.php?page=reports" class="btn btn-ghost">All Time</a><?php endif; ?>
  </form>
  <a href="index.php?page=export_csv<?= $from?"&from={$from}&to={$to}":'' ?>" class="btn btn-accent">⬇ Export CSV</a>
</div>

<!-- KPI cards -->
<div class="report-stats" style="margin-bottom:1.25rem">
  <div class="stat-card">
    <div class="stat-label">Revenue<?= $from?" ({$from} – {$to})":' (All Time)' ?></div>
    <div class="stat-value">₱<?= number_format($sum['total_revenue'],2) ?></div>
    <div class="stat-sub"><?= $sum['total_sales'] ?> transactions</div>
  </div>
  <div class="stat-card accent">
    <div class="stat-label">Today's Revenue</div>
    <div class="stat-value">₱<?= number_format($sum['today_revenue'],2) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Discounts Given</div>
    <div class="stat-value">₱<?= number_format($sum['discount_stats']['total_discounts'],2) ?></div>
    <div class="stat-sub"><?= $sum['discount_stats']['discounted_txns'] ?> discounted orders</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Inventory Events</div>
    <div class="stat-value"><?= count($invLogs) ?></div>
    <div class="stat-sub"><?= count(array_filter($invLogs,fn($l)=>$l['change_type']==='sale')) ?> from sales</div>
  </div>
</div>

<div class="reports-grid">

  <!-- ── Daily Revenue Chart + Table ──────────────────────────── -->
  <?php if (!empty($dailyDates)): ?>
  <div class="card card-full">
    <div class="card-header"><span class="card-title">📈 Daily Revenue — Last <?= count($dailyDates) ?> Days</span></div>
    <div class="report-chart-row">
      <div class="report-chart-wrap" style="flex:1;min-width:0">
        <canvas id="dailyRevenueChart" class="report-canvas"></canvas>
      </div>
      <div class="report-chart-table" style="width:260px;flex-shrink:0">
        <table class="data-table compact">
          <thead><tr><th>Date</th><th>Txns</th><th>Revenue</th></tr></thead>
          <tbody>
          <?php foreach (array_reverse($dailySlice) as $r): ?>
            <tr><td style="font-size:.78rem"><?= $r['date'] ?></td><td><?= $r['count'] ?></td><td>₱<?= number_format($r['total'],2) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Monthly Revenue Chart + Table ─────────────────────────── -->
  <div class="card card-half">
    <div class="card-header"><span class="card-title">📅 Monthly Revenue</span></div>
    <?php if (!empty($monthlyLabels)): ?>
    <div class="report-chart-wrap" style="padding:.75rem 1rem .25rem">
      <canvas id="monthlyChart" class="report-canvas"></canvas>
    </div>
    <?php endif; ?>
    <table class="data-table compact" style="margin-top:.25rem">
      <thead><tr><th>Month</th><th>Transactions</th><th>Revenue</th></tr></thead>
      <tbody>
      <?php foreach ($sum['monthly_summary'] as $r): ?>
        <tr><td><?= $r['month'] ?></td><td><?= $r['count'] ?></td><td>₱<?= number_format($r['total'],2) ?></td></tr>
      <?php endforeach; ?>
      <?php if (empty($sum['monthly_summary'])): ?><tr><td colspan="3" class="empty">No data.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ── Top Sellers Chart + Table ─────────────────────────────── -->
  <div class="card card-half">
    <div class="card-header"><span class="card-title">🔥 Top Selling Products</span></div>
    <?php if (!empty($topProds)): ?>
    <div class="report-chart-wrap" style="padding:.75rem 1rem .25rem">
      <canvas id="topProductsChart" class="report-canvas"></canvas>
    </div>
    <?php endif; ?>
    <table class="data-table compact" style="margin-top:.25rem">
      <thead><tr><th>Rank</th><th>Product</th><th>Units</th><th>Revenue</th></tr></thead>
      <tbody>
      <?php foreach ($topProds as $i=>$tp): ?>
        <tr><td><span class="rank-badge"><?= $i+1 ?></span></td><td><?= htmlspecialchars($tp['name']) ?></td><td><?= $tp['qty'] ?></td><td>₱<?= number_format($tp['revenue'],2) ?></td></tr>
      <?php endforeach; ?>
      <?php if (empty($topProds)): ?><tr><td colspan="4" class="empty">No sales yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ── PairPal Pairs ──────────────────────────────────────────── -->
  <?php if (!empty($topPairs)): ?>
  <div class="card card-half">
    <div class="card-header"><span class="card-title">✦ Most Common Pairs</span></div>
    <table class="data-table compact">
      <thead><tr><th>Product A</th><th>Product B</th><th>Times Paired</th></tr></thead>
      <tbody>
      <?php foreach ($topPairs as $pair):
        [$aId,$bId] = explode('|', $pair['pair']);
        $pa = $productRepo->findById($aId);
        $pb = $productRepo->findById($bId);
        if (!$pa || !$pb) continue;
      ?>
        <tr>
          <td><?= htmlspecialchars($pa['name']) ?></td>
          <td><?= htmlspecialchars($pb['name']) ?></td>
          <td><span class="rank-badge"><?= $pair['count'] ?>×</span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- ── Slow Movers ───────────────────────────────────────────── -->
  <?php if (!empty($slowMovers)): ?>
  <div class="card card-half">
    <div class="card-header"><span class="card-title">🐢 Slow-Moving Products</span></div>
    <table class="data-table compact">
      <thead><tr><th>Product</th><th>Category</th><th>Stock</th><th>Sales (30d)</th><th>Suggestion</th></tr></thead>
      <tbody>
      <?php foreach ($slowMovers as $sm): $p=$sm['product']; ?>
        <tr class="row-warn">
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td><span class="category-badge"><?= htmlspecialchars($p['category']) ?></span></td>
          <td><?= $p['stock'] ?></td>
          <td><?= $sm['recent_sales'] ?></td>
          <td style="font-size:.75rem;color:var(--text3)"><?= $sm['recent_sales']===0?'Discount/Bundle':'Promote' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- ── Low Stock ─────────────────────────────────────────────── -->
  <div class="card card-half">
    <div class="card-header"><span class="card-title">⚠️ Low Stock Inventory</span></div>
    <?php if (empty($sum['low_stock'])): ?>
      <p class="empty-state">All products are well-stocked ✓</p>
    <?php else: ?>
    <table class="data-table compact">
      <thead><tr><th>Product</th><th>Supplier</th><th>Stock</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($sum['low_stock'] as $p): $thr=$p['low_stock_threshold']??8; $crit=$p['stock']<=max(1,intval($thr*0.5)); ?>
        <tr class="<?= $crit?'row-danger':'row-warn' ?>">
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td class="text-muted"><?= htmlspecialchars($p['supplier']??'—') ?></td>
          <td><?= $p['stock'] ?></td>
          <td><span class="stock-badge <?= $crit?'danger':'warn' ?>"><?= $crit?'Critical':'Low' ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- ── Inventory Movement ────────────────────────────────────── -->
  <?php if (!empty($invLogs)): ?>
  <div class="card card-full">
    <div class="card-header"><span class="card-title">📊 Recent Inventory Movement</span></div>
    <div class="table-wrap" style="max-height:300px;overflow-y:auto">
    <table class="data-table compact">
      <thead><tr><th>Date</th><th>Product</th><th>Type</th><th>Change</th><th>After</th><th>Note</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($invLogs,0,30) as $log):
        // Schema uses 'date' key — fall back gracefully
        $logDate = $log['date'] ?? $log['created_at'] ?? null;
        $logTs   = $logDate ? strtotime($logDate) : 0;
        $logFmt  = $logTs > 0 ? date('M j, g:i a', $logTs) : '—';
      ?>
        <tr>
          <td class="text-muted" style="font-size:.78rem;white-space:nowrap"><?= $logFmt ?></td>
          <td><?= htmlspecialchars($log['product_name'] ?? '—') ?></td>
          <td><span class="type-badge <?= ($log['change_type']??'')==='sale'?'type-sale':(($log['quantity_changed']??0)>=0?'type-add':'type-remove') ?>"><?= ucfirst(str_replace('_',' ',$log['change_type']??'')) ?></span></td>
          <td class="<?= ($log['quantity_changed']??0)<0?'qty-neg':'qty-pos' ?>"><?= (($log['quantity_changed']??0)>=0?'+':'').($log['quantity_changed']??0) ?></td>
          <td><?= $log['stock_after'] ?? '—' ?></td>
          <td class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($log['note']??'') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /.reports-grid -->

<style>
/* Chart layout helpers scoped to reports page */
.report-chart-row  { display:flex; gap:0; align-items:stretch; }
.report-chart-wrap { position:relative; width:100%; }
.report-canvas     { display:block; width:100% !important; }
.report-chart-table { border-left:1px solid var(--border); overflow-y:auto; max-height:300px; }
</style>

<script>
// ── Crisp HiDPI canvas charts ─────────────────────────────────────────────
(function() {

    function getDevicePixelRatio() { return window.devicePixelRatio || 1; }

    function setupCanvas(canvas, w, h) {
        const dpr    = getDevicePixelRatio();
        canvas.width  = w * dpr;
        canvas.height = h * dpr;
        canvas.style.width  = w + 'px';
        canvas.style.height = h + 'px';
        const ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);
        return ctx;
    }

    function niceMax(val) {
        if (val <= 0) return 100;
        const mag = Math.pow(10, Math.floor(Math.log10(val)));
        return Math.ceil(val / mag) * mag;
    }

    function formatPeso(v) {
        if (v >= 1000000) return '₱' + (v/1000000).toFixed(1) + 'M';
        if (v >= 1000)    return '₱' + (v/1000).toFixed(0) + 'k';
        return '₱' + v.toFixed(0);
    }

    // ── Line chart (daily revenue) ────────────────────────────────
    function drawLine(canvasId, labels, values, color) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const wrap = canvas.parentElement;
        const W    = wrap.clientWidth  || 600;
        const H    = 200;
        const ctx  = setupCanvas(canvas, W, H);

        const PAD  = { top:28, right:20, bottom:42, left:72 };
        const cw   = W - PAD.left - PAD.right;
        const ch   = H - PAD.top  - PAD.bottom;
        const max  = niceMax(Math.max(...values, 1));
        const n    = values.length;
        const step = n > 1 ? cw / (n - 1) : cw;

        // Background grid
        const GRID_LINES = 5;
        for (let i = 0; i <= GRID_LINES; i++) {
            const y   = PAD.top + (ch / GRID_LINES) * i;
            const val = max - (max / GRID_LINES) * i;

            ctx.strokeStyle = 'rgba(255,255,255,.07)';
            ctx.lineWidth   = 1;
            ctx.beginPath(); ctx.moveTo(PAD.left, y); ctx.lineTo(W - PAD.right, y); ctx.stroke();

            ctx.fillStyle  = 'rgba(255,255,255,.35)';
            ctx.font       = '11px "DM Sans", sans-serif';
            ctx.textAlign  = 'right';
            ctx.textBaseline = 'middle';
            ctx.fillText(formatPeso(val), PAD.left - 8, y);
        }

        // Area gradient
        const grad = ctx.createLinearGradient(0, PAD.top, 0, PAD.top + ch);
        grad.addColorStop(0,   color.replace('1)', '0.28)'));
        grad.addColorStop(1,   color.replace('1)', '0.02)'));

        ctx.beginPath();
        values.forEach((v, i) => {
            const x = PAD.left + step * i;
            const y = PAD.top + ch - (v / max) * ch;
            i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });
        const ex = PAD.left + step * (n - 1);
        ctx.lineTo(ex, PAD.top + ch);
        ctx.lineTo(PAD.left, PAD.top + ch);
        ctx.closePath();
        ctx.fillStyle = grad;
        ctx.fill();

        // Line
        ctx.beginPath();
        ctx.strokeStyle = color; ctx.lineWidth = 2.5; ctx.lineJoin = 'round';
        values.forEach((v, i) => {
            const x = PAD.left + step * i;
            const y = PAD.top + ch - (v / max) * ch;
            i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });
        ctx.stroke();

        // Dots + value labels
        values.forEach((v, i) => {
            const x = PAD.left + step * i;
            const y = PAD.top + ch - (v / max) * ch;

            ctx.beginPath();
            ctx.arc(x, y, 4, 0, Math.PI * 2);
            ctx.fillStyle = color; ctx.fill();
            ctx.strokeStyle = 'rgba(0,0,0,.4)'; ctx.lineWidth = 1.5;
            ctx.stroke();

            // Value above dot (skip if crowded)
            if (n <= 10 || i % 2 === 0) {
                ctx.fillStyle    = 'rgba(255,255,255,.75)';
                ctx.font         = '10px "DM Sans", sans-serif';
                ctx.textAlign    = 'center';
                ctx.textBaseline = 'bottom';
                ctx.fillText(formatPeso(v), x, y - 7);
            }
        });

        // X labels — smart spacing so they never overlap
        const labelEvery = Math.max(1, Math.ceil(n / 7));
        ctx.fillStyle    = 'rgba(255,255,255,.4)';
        ctx.font         = '10px "DM Sans", sans-serif';
        ctx.textAlign    = 'center';
        ctx.textBaseline = 'top';
        labels.forEach((l, i) => {
            if (i % labelEvery === 0 || i === n - 1) {
                const x = PAD.left + step * i;
                // Trim label: show MM/DD
                const parts = l.split('-');
                const short = parts.length === 3 ? parts[1]+'/'+parts[2] : l;
                ctx.fillText(short, x, H - PAD.bottom + 6);
            }
        });
    }

    // ── Bar chart ────────────────────────────────────────────────
    function drawBars(canvasId, labels, values, color, formatFn) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const wrap = canvas.parentElement;
        const W    = wrap.clientWidth || 500;
        const H    = 200;
        const ctx  = setupCanvas(canvas, W, H);

        const PAD  = { top:32, right:16, bottom:52, left:64 };
        const cw   = W - PAD.left - PAD.right;
        const ch   = H - PAD.top  - PAD.bottom;
        const max  = niceMax(Math.max(...values, 1));
        const n    = values.length;
        const colW = cw / n;
        const barW = Math.max(4, colW * 0.6);

        // Grid
        for (let i = 0; i <= 4; i++) {
            const y   = PAD.top + (ch / 4) * i;
            const val = max - (max / 4) * i;
            ctx.strokeStyle = 'rgba(255,255,255,.07)';
            ctx.lineWidth   = 1;
            ctx.beginPath(); ctx.moveTo(PAD.left, y); ctx.lineTo(W - PAD.right, y); ctx.stroke();

            ctx.fillStyle    = 'rgba(255,255,255,.35)';
            ctx.font         = '11px "DM Sans", sans-serif';
            ctx.textAlign    = 'right';
            ctx.textBaseline = 'middle';
            ctx.fillText((formatFn || formatPeso)(val), PAD.left - 6, y);
        }

        values.forEach((v, i) => {
            const barH = Math.max(2, (v / max) * ch);
            const x    = PAD.left + colW * i + (colW - barW) / 2;
            const y    = PAD.top + ch - barH;

            // Bar with rounded top
            ctx.beginPath();
            ctx.fillStyle = color.replace('1)', '0.8)');
            if (ctx.roundRect) ctx.roundRect(x, y, barW, barH, [3, 3, 0, 0]);
            else ctx.rect(x, y, barW, barH);
            ctx.fill();

            // Value above bar
            ctx.fillStyle    = 'rgba(255,255,255,.75)';
            ctx.font         = '10px "DM Sans", sans-serif';
            ctx.textAlign    = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillText((formatFn || formatPeso)(v), x + barW / 2, y - 3);

            // X label — truncate and rotate for long names
            const maxLabelW = colW - 4;
            ctx.save();
            ctx.translate(x + barW / 2, H - PAD.bottom + 8);
            if (labels[i].length > 8 && n > 4) ctx.rotate(-Math.PI / 5);
            ctx.fillStyle    = 'rgba(255,255,255,.4)';
            ctx.font         = '9px "DM Sans", sans-serif';
            ctx.textAlign    = 'center';
            ctx.textBaseline = 'top';
            // Truncate label
            let lbl = labels[i];
            if (lbl.length > 10) lbl = lbl.slice(0, 9) + '…';
            ctx.fillText(lbl, 0, 0);
            ctx.restore();
        });
    }

    // ── Render all charts after layout settles ────────────────────
    requestAnimationFrame(function() {
        const GOLD  = 'rgba(240,192,64,1)';
        const BLUE  = 'rgba(80,144,240,1)';
        const GREEN = 'rgba(64,212,160,1)';

        drawLine('dailyRevenueChart',
            <?= json_encode($dailyDates) ?>,
            <?= json_encode($dailyRevenue) ?>,
            GOLD
        );
        drawBars('monthlyChart',
            <?= json_encode($monthlyLabels) ?>,
            <?= json_encode($monthlyData) ?>,
            BLUE
        );
        drawBars('topProductsChart',
            <?= json_encode($topProdNames) ?>,
            <?= json_encode($topProdQty) ?>,
            GREEN,
            function(v){ return v % 1 === 0 ? v + ' units' : v.toFixed(1); }
        );
    });

}());
</script>
