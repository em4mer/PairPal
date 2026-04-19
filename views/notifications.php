<?php
// views/notifications.php
$nm         = new NotificationManager();
$filter     = $_GET['cat'] ?? '';
$all        = $filter ? $nm->getByCategory($filter) : $nm->getAll();
$unread     = $nm->getUnreadCount();
$categories = [
    NotificationManager::CAT_STOCK   => '⚠️ Stock',
    NotificationManager::CAT_ORDER   => '📦 Orders',
    NotificationManager::CAT_SYSTEM  => '⚙️ System',
    NotificationManager::CAT_INSIGHT => '✦ Insights',
    NotificationManager::CAT_BUNDLE  => '🎁 Bundles',
];
$catColors  = [
    'stock'   => 'warn',
    'order'   => 'type-import',
    'system'  => 'type-update',
    'insight' => 'type-import',
    'bundle'  => 'type-add',
];
?>
<div class="page-controls" style="flex-wrap:wrap;gap:.5rem">
  <div class="status-filter-tabs">
    <a href="index.php?page=notifications" class="status-tab <?= !$filter?'active':'' ?>">All <span class="count-badge"><?= count($nm->getAll()) ?></span></a>
    <?php foreach ($categories as $key => $label): ?>
    <a href="index.php?page=notifications&cat=<?= $key ?>" class="status-tab <?= $filter===$key?'active':'' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
  <div style="display:flex;gap:.5rem;margin-left:auto">
    <?php if ($unread > 0): ?>
    <button class="btn btn-sm btn-ghost" onclick="markAllRead()">✓ Mark All Read</button>
    <?php endif; ?>
  </div>
</div>

<?php if (empty($all)): ?>
<div class="card" style="text-align:center;padding:3rem">
  <div style="font-size:2.5rem;margin-bottom:1rem">🔔</div>
  <p class="text-muted">No notifications yet. They'll appear here when activity occurs.</p>
</div>
<?php else: ?>
<div class="notif-center-list">
  <?php foreach ($all as $n):
    $isUnread = !($n['read'] ?? false);
    $catLabel = $categories[$n['category']] ?? '📌';
    $catColor = $catColors[$n['category']] ?? 'type-update';
    $timeAgo  = notifTimeAgo($n['created_at']);
  ?>
  <div class="notif-center-item <?= $isUnread?'notif-unread':'' ?>" id="notif_<?= $n['id'] ?>">
    <div class="notif-center-dot <?= $isUnread?'dot-active':'' ?>"></div>
    <div class="notif-center-body">
      <div class="notif-center-header">
        <span class="type-badge <?= $catColor ?>"><?= $catLabel ?></span>
        <span class="notif-center-title"><?= htmlspecialchars($n['title']) ?></span>
        <span class="notif-center-time text-muted"><?= $timeAgo ?></span>
      </div>
      <div class="notif-center-msg"><?= htmlspecialchars($n['message']) ?></div>
    </div>
    <div class="notif-center-actions">
      <?php if (!empty($n['link'])): ?>
      <a href="<?= htmlspecialchars($n['link']) ?>" class="btn btn-sm btn-ghost" onclick="markRead('<?= $n['id'] ?>')">View →</a>
      <?php endif; ?>
      <?php if ($isUnread): ?>
      <button class="btn btn-sm btn-ghost" onclick="markRead('<?= $n['id'] ?>')">✓</button>
      <?php endif; ?>
      <?php if ($_SESSION['role']==='admin'): ?>
      <button class="btn btn-sm btn-danger" onclick="deleteNotif('<?= $n['id'] ?>')">✕</button>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
function notifTimeAgo(string $dateStr): string {
    $diff = time() - strtotime($dateStr);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff/60).'m ago';
    if ($diff < 86400)  return floor($diff/3600).'h ago';
    if ($diff < 604800) return floor($diff/86400).'d ago';
    return date('M j', strtotime($dateStr));
}
?>

<script>
async function markRead(id) {
    const fd = new FormData(); fd.append('action','notif_mark_read'); fd.append('id',id);
    const data = await pairpalPost(fd);
    if (data.success) {
        const el = document.getElementById('notif_'+id);
        if (el) { el.classList.remove('notif-unread'); el.querySelector('.notif-center-dot')?.classList.remove('dot-active'); }
        updateBellCount();
    }
}
async function markAllRead() {
    const fd = new FormData(); fd.append('action','notif_mark_all_read');
    const data = await pairpalPost(fd);
    showToast('All notifications marked as read.','success');
    setTimeout(()=>location.reload(),600);
}
async function deleteNotif(id) {
    const fd = new FormData(); fd.append('action','notif_delete'); fd.append('id',id);
    const data = await pairpalPost(fd);
    if (data.success) { const el = document.getElementById('notif_'+id); if(el) el.remove(); }
}
async function updateBellCount() {
    const fd = new FormData(); fd.append('action','notif_get_unread_count');
    const data = await pairpalPost(fd);
    const badge = document.querySelector('.notif-nav-badge');
    if (badge) { badge.textContent = data.count; if(data.count===0) badge.style.display='none'; }
}
</script>
