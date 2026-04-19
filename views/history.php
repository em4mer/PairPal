<?php
// views/history.php
$salesRepo   = new SalesRepository();
$from        = $_GET['from'] ?? '';
$to          = $_GET['to']   ?? '';
$isAdmin     = ($_SESSION['role'] ?? '') === 'admin';
$cashierId   = $_SESSION['user_id'] ?? '';

// Cashiers only see their own sales; admins see all
if ($isAdmin) {
    $allSales = ($from && $to)
        ? $salesRepo->getSalesByDateRange($from, $to)
        : $salesRepo->getAll();
} else {
    $allSales = ($from && $to)
        ? $salesRepo->getByCashierAndDateRange($cashierId, $from, $to)
        : $salesRepo->getByCashier($cashierId);
}

$totalRev  = array_sum(array_column($allSales, 'total'));
$totalDisc = array_sum(array_column(array_map(fn($s) => ['d' => $s['discount_amount'] ?? 0], $allSales), 'd'));

// Pagination
$perPage      = 30;
$totalSalesN  = count($allSales);
$totalPages   = max(1, (int)ceil($totalSalesN / $perPage));
$currentPage  = max(1, min($totalPages, (int)($_GET['paged'] ?? 1)));
$offset       = ($currentPage - 1) * $perPage;
$pagedSales   = array_slice($allSales, $offset, $perPage);
?>
<div class="page-controls">
  <form method="GET" class="search-bar">
    <input type="hidden" name="page" value="history">
    <label class="form-label-inline">From</label>
    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
    <label class="form-label-inline">To</label>
    <input type="date" name="to"   value="<?= htmlspecialchars($to) ?>">
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if ($from || $to): ?>
      <a href="index.php?page=history" class="btn btn-ghost">Clear</a>
    <?php endif; ?>
  </form>
  <div class="topbar-stat">
    <strong><?= count($allSales) ?></strong> transactions &nbsp;·&nbsp;
    Total: <strong>₱<?= number_format($totalRev,2) ?></strong>
    <?php if ($totalDisc > 0): ?>&nbsp;·&nbsp; Discounts: <strong>₱<?= number_format($totalDisc,2) ?></strong><?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
  <table class="data-table" id="historyTable">
    <thead>
      <tr><th>ID</th><th>Cashier</th><th>Items</th><th>Subtotal</th><th>Discount</th><th>Total</th><th>Paid</th><th>Change</th><th>Date</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($pagedSales as $s): ?>
      <tr>
        <td><code><?= htmlspecialchars($s['id']) ?></code></td>
        <td><?= htmlspecialchars($s['cashier_name']) ?></td>
        <td><?= count($s['items']) ?></td>
        <td>₱<?= number_format($s['subtotal'] ?? $s['total'], 2) ?></td>
        <td>
          <?php $disc = (float)($s['discount_amount'] ?? 0); ?>
          <?php if ($disc > 0): ?>
            <span class="discount-pill">
              <?= $s['discount_type'] === 'percent' ? $s['discount_value'].'%' : '₱'.$s['discount_value'] ?>
              · −₱<?= number_format($disc,2) ?>
            </span>
          <?php else: ?>
            <span class="text-muted">—</span>
          <?php endif; ?>
        </td>
        <td><strong>₱<?= number_format($s['total'],2) ?></strong></td>
        <td>₱<?= number_format($s['amount_paid'],2) ?></td>
        <td>₱<?= number_format($s['change'],2) ?></td>
        <td class="text-muted"><?= date('M j, Y g:i a', strtotime($s['date'])) ?></td>
        <td><button class="btn btn-sm btn-ghost" onclick='showDetail(<?= json_encode($s) ?>)'>Details</button></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($pagedSales)): ?>
      <tr><td colspan="10" class="empty">No transactions found for this range.</td></tr>
    <?php endif; ?>
    </tbody>
    <?php if (!empty($allSales)): ?>
    <tfoot>
      <tr style="font-weight:600;border-top:2px solid var(--border2)">
        <td colspan="4" style="padding:.625rem .75rem;color:var(--text3);font-size:.82rem">
          <?= $totalSalesN ?> transactions total
          <?php if ($totalPages > 1): ?> · Page <?= $currentPage ?> of <?= $totalPages ?><?php endif; ?>
        </td>
        <td colspan="2" style="padding:.625rem .75rem;text-align:right;color:var(--text)">
          ₱<?= number_format($totalRev,2) ?>
        </td>
        <?php if ($totalDisc > 0): ?>
        <td style="padding:.625rem .75rem;color:var(--text3);font-size:.82rem">
          −₱<?= number_format($totalDisc,2) ?> disc.
        </td>
        <?php else: ?>
        <td></td>
        <?php endif; ?>
        <td colspan="3"></td>
      </tr>
    </tfoot>
    <?php endif; ?>
  </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination-bar">
  <span class="pag-info">Showing <?= $offset+1 ?>–<?= min($offset+$perPage,$totalSalesN) ?> of <?= $totalSalesN ?> transactions</span>
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

<!-- Detail Modal -->
<div id="detailModal" class="modal-overlay" style="display:none" onclick="this.style.display='none'">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h2>Transaction Details</h2>
      <button class="modal-close" onclick="document.getElementById('detailModal').style.display='none'">✕</button>
    </div>
    <div id="detailContent" class="detail-content"></div>
  </div>
</div>

<script>
function showDetail(txn) {
  const discAmt = parseFloat(txn.discount_amount || 0);
  let discHtml = '';
  if (discAmt > 0) {
    const label = txn.discount_type === 'percent' ? `${txn.discount_value}% off` : `₱${txn.discount_value} fixed`;
    discHtml = `<div class="detail-row"><span>Discount (${label})</span><span>−₱${discAmt.toFixed(2)}</span></div>`;
  }
  document.getElementById('detailContent').innerHTML = `
    <div class="detail-meta">
      <div><strong>ID:</strong> ${txn.id}</div>
      <div><strong>Cashier:</strong> ${txn.cashier_name}</div>
      <div><strong>Date:</strong> ${new Date(txn.date).toLocaleString()}</div>
      <div><strong>Status:</strong> ${txn.status}</div>
    </div>
    <table class="data-table" style="margin-top:1rem">
      <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
      <tbody>
        ${txn.items.map(i => `<tr><td>${i.name}</td><td>₱${parseFloat(i.price).toFixed(2)}</td><td>${i.quantity}</td><td>₱${parseFloat(i.subtotal).toFixed(2)}</td></tr>`).join('')}
      </tbody>
    </table>
    <div class="detail-totals">
      <div class="detail-row"><span>Subtotal</span><span>₱${parseFloat(txn.subtotal||txn.total).toFixed(2)}</span></div>
      ${discHtml}
      <div class="detail-row bold"><span>Total</span><span>₱${parseFloat(txn.total).toFixed(2)}</span></div>
      <div class="detail-row"><span>Amount Paid</span><span>₱${parseFloat(txn.amount_paid).toFixed(2)}</span></div>
      <div class="detail-row bold"><span>Change</span><span>₱${parseFloat(txn.change).toFixed(2)}</span></div>
    </div>`;
  document.getElementById('detailModal').style.display = 'flex';
}
</script>
