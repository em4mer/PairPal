<?php
// views/activity.php
$logger   = new ActivityLogger();
$typeFilter = $_GET['type'] ?? '';
$logs     = $typeFilter ? $logger->getByType($typeFilter) : $logger->getRecent(100);

$typeLabels = [
    ActivityLogger::TYPE_PRODUCT_CREATE  => ['📦 Product Created', 'type-add'],
    ActivityLogger::TYPE_PRODUCT_UPDATE  => ['✏️ Product Updated', 'type-update'],
    ActivityLogger::TYPE_PRODUCT_DELETE  => ['🗑 Product Deleted', 'danger'],
    ActivityLogger::TYPE_STOCK_ADJUST    => ['📊 Stock Adjusted', 'type-import'],
    ActivityLogger::TYPE_ORDER_STATUS    => ['📦 Order Updated', 'type-import'],
    ActivityLogger::TYPE_SALE            => ['💰 Sale Completed', 'type-add'],
    ActivityLogger::TYPE_LOGIN           => ['🔑 Login', 'type-update'],
    ActivityLogger::TYPE_LOGOUT          => ['↩ Logout', 'type-update'],
    ActivityLogger::TYPE_BUNDLE_GENERATE => ['🎁 Bundles Generated', 'type-add'],
    ActivityLogger::TYPE_IMPORT          => ['⬆ Bulk Import', 'type-import'],
];
$allTypes = array_keys($typeLabels);
?>
<div class="page-controls" style="flex-wrap:wrap;gap:.5rem">
  <div class="status-filter-tabs" style="flex-wrap:wrap">
    <a href="index.php?page=activity" class="status-tab <?= !$typeFilter?'active':'' ?>">All</a>
    <?php foreach ($typeLabels as $type => [$label]): ?>
    <a href="index.php?page=activity&type=<?= $type ?>" class="status-tab <?= $typeFilter===$type?'active':'' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Activity Log <span class="count-badge"><?= count($logs) ?></span></span>
    <span class="text-muted" style="font-size:.75rem">Showing most recent 100 entries</span>
  </div>
  <div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Time</th><th>Type</th><th>Action</th><th>Detail</th><th>User</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $log):
      [$label, $color] = $typeLabels[$log['type']] ?? [$log['type'], 'type-update'];
    ?>
      <tr>
        <td class="text-muted" style="white-space:nowrap;font-size:.8rem"><?= date('M j, g:i a', strtotime($log['created_at'])) ?></td>
        <td><span class="type-badge <?= $color ?>"><?= $label ?></span></td>
        <td><?= htmlspecialchars($log['action']) ?></td>
        <td class="text-muted" style="font-size:.82rem;max-width:300px"><?= htmlspecialchars($log['detail']) ?></td>
        <td><?= htmlspecialchars($log['user_name']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($logs)): ?>
      <tr><td colspan="5" class="empty">No activity logged yet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
