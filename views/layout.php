<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PairPal Sales Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/main.css">
<?php if ($page !== 'login'): ?>
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_generate()) ?>">
<?php endif; ?>
</head>
<body class="<?= $page==='login'?'page-login':'page-app' ?>">

<?php if ($page !== 'login'): ?>
<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <span class="brand-icon">◈</span>
    <span class="brand-text">PairPal</span>
  </div>
  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($_SESSION['name']??'U',0,1)) ?></div>
    <div class="user-info">
      <div class="user-name"><?= htmlspecialchars($_SESSION['name']??'') ?></div>
      <div class="user-role"><?= ucfirst($_SESSION['role']??'') ?></div>
    </div>
  </div>
  <ul class="nav-links">
    <li><a href="index.php?page=dashboard"    class="<?= $page==='dashboard'?'active':'' ?>"><span class="nav-icon">⊞</span> Dashboard</a></li>
    <li><a href="index.php?page=cart"         class="<?= $page==='cart'?'active':'' ?>"><span class="nav-icon">⊕</span> POS / Cart <span class="cart-badge" id="navCartBadge" style="display:none"></span></a></li>
    <?php if (($_SESSION['role']??'')==='admin'): ?>
    <li><a href="index.php?page=products"     class="<?= $page==='products'?'active':'' ?>"><span class="nav-icon">⊟</span> Products</a></li>
    <li><a href="index.php?page=inventory"    class="<?= $page==='inventory'?'active':'' ?>"><span class="nav-icon">◫</span> Inventory</a></li>
    <li><a href="index.php?page=orders"       class="<?= $page==='orders'?'active':'' ?>"><span class="nav-icon">📦</span> Orders</a></li>
    <?php endif; ?>
    <li><a href="index.php?page=history"      class="<?= $page==='history'?'active':'' ?>"><span class="nav-icon">◷</span> Sales History</a></li>
    <?php if (($_SESSION['role']??'')==='admin'): ?>
    <li><a href="index.php?page=reports"      class="<?= $page==='reports'?'active':'' ?>"><span class="nav-icon">◈</span> Reports</a></li>
    <li><a href="index.php?page=intelligence" class="<?= $page==='intelligence'?'active':'' ?>"><span class="nav-icon">✦</span> PairPal AI</a></li>
    <li><a href="index.php?page=bundles"      class="<?= $page==='bundles'?'active':'' ?>"><span class="nav-icon">🎁</span> Bundles</a></li>
    <li><a href="index.php?page=users"        class="<?= $page==='users'?'active':'' ?>"><span class="nav-icon">👥</span> Staff</a></li>
    <li><a href="index.php?page=activity"       class="<?= $page==='activity'?'active':'' ?>"><span class="nav-icon">📋</span> Activity Log</a></li>
    <li><a href="index.php?page=notifications"  class="<?= $page==='notifications'?'active':'' ?>"><span class="nav-icon">🔔</span> Notifications <?php $__nm=new NotificationManager(); $__uc=$__nm->getUnreadCount(); if($__uc>0): ?><span class="notif-nav-badge"><?= $__uc ?></span><?php endif; ?></a></li>
    <?php endif; ?>
    <?php if (($_SESSION['role']??'') === 'admin'): ?>
    <li class="nav-separator"></li>
    <li><a href="index.php?page=store" target="_blank" class="nav-external"><span class="nav-icon">🛒</span> Customer Store ↗</a></li>
    <?php endif; ?>
  </ul>
  <div class="sidebar-footer">
    <button class="btn-logout" onclick="logout()">↩ Sign Out</button>
  </div>
</nav>
<div class="main-content">
  <header class="topbar">
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
    <div class="topbar-title">
      <?php if ($page === 'dashboard'):
        $hour = (int)date('H');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
        $firstName = explode(' ', $_SESSION['name'] ?? 'there')[0];
      ?>
        <?= $greeting ?>, <?= htmlspecialchars($firstName) ?> 👋
      <?php else: ?>
        <?= ucfirst(str_replace('_', ' ', $page)) ?>
      <?php endif; ?>
    </div>
    <div class="topbar-right">
      <span class="topbar-date"><?= date('D, M j Y') ?></span>
      <?php if (($_SESSION['role']??'') === 'admin'): ?>
      <a href="index.php?page=export_backup" class="btn btn-sm btn-ghost" title="Download full data backup" style="font-size:.75rem">⬇ Backup</a>
      <?php endif; ?>
    </div>
  </header>
  <div class="page-body">
<?php endif; ?>

<?php
$adminPages = ['products','inventory','orders','reports','intelligence','bundles'];
switch ($page) {
    case 'login':       include __DIR__.'/login.php'; break;
    case 'dashboard':   include __DIR__.'/dashboard.php'; break;
    case 'products':    $currentUser?->canManageProducts() ? include __DIR__.'/products.php' : print('<div class="alert alert-error">Access denied.</div>'); break;
    case 'inventory':   $currentUser?->canManageProducts() ? include __DIR__.'/inventory.php' : print('<div class="alert alert-error">Access denied.</div>'); break;
    case 'orders':      $currentUser?->canManageProducts() ? include __DIR__.'/orders.php' : print('<div class="alert alert-error">Access denied.</div>'); break;
    case 'cart':        include __DIR__.'/cart.php'; break;
    case 'history':     include __DIR__.'/history.php'; break;
    case 'reports':     $currentUser?->canViewReports() ? include __DIR__.'/reports.php' : print('<div class="alert alert-error">Access denied.</div>'); break;
    case 'intelligence':$currentUser?->canViewReports() ? include __DIR__.'/intelligence.php' : print('<div class="alert alert-error">Access denied.</div>'); break;
    case 'bundles':     $currentUser?->canViewReports() ? include __DIR__.'/bundles.php' : print('<div class="alert alert-error">Access denied.</div>'); break;
    case 'users':         $currentUser?->canManageProducts() ? include __DIR__.'/users.php' : print('<div class="alert alert-error">Access denied.</div>'); break;
    case 'notifications': include __DIR__.'/notifications.php'; break;
    case 'activity':      $currentUser?->canViewReports() ? include __DIR__.'/activity.php' : print('<div class="alert alert-error">Access denied.</div>'); break;
    default:            echo '<div class="alert alert-error">Page not found.</div>';
}
?>

<?php if ($page !== 'login'): ?>
  </div>
</div>
<?php endif; ?>

<div id="toast" class="toast"></div>

<button id="backToTop" class="back-to-top" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Back to top" aria-label="Back to top">↑</button>
<script>
(function(){
    const btn = document.getElementById('backToTop');
    if (!btn) return;
    window.addEventListener('scroll', function(){
        btn.classList.toggle('btt-visible', window.scrollY > 300);
    }, {passive:true});
}());
</script>
<div id="modal-overlay" class="modal-overlay" onclick="closeModal()" style="display:none"></div>
<div id="modal" class="modal" style="display:none"></div>
<script src="assets/js/main.js"></script>

<?php if ($auth->isLoggedIn()): ?>
<?php
$_notifDismissed = !empty($_SESSION['login_notification_dismissed']);
if (!$_notifDismissed):
$_notifEngine = new PairPalEngine();
$_lowStock    = $_notifEngine->getLowStockAlerts();
$_insightMsg  = $_notifEngine->getInsightMessage();
$_restock     = $_notifEngine->getRestockSuggestions(3);
// Mark dismissed immediately on first render - link clicks will work because flag is already set
$_SESSION['login_notification_dismissed'] = true;
?>

<div id="pairpalNotifToast" class="pairpal-notif-toast" onclick="openBriefingPanel()" title="PairPal Briefing">
  <span class="pairpal-notif-icon">✦</span>
  <div class="pairpal-notif-content">
    <div class="pairpal-notif-title">PairPal Briefing</div>
    <div class="pairpal-notif-msg" id="notifToastMsg">
      <?php if (!empty($_lowStock)): ?>
        ⚠️ <?= count($_lowStock) ?> product<?= count($_lowStock)!==1?'s':'' ?> need restocking
      <?php else: ?>
        <?= htmlspecialchars(mb_substr($_insightMsg, 0, 60)) . (mb_strlen($_insightMsg)>60?'…':'') ?>
      <?php endif; ?>
    </div>
  </div>
  <button class="pairpal-notif-dismiss" onclick="event.stopPropagation();closeBriefingToast()" aria-label="Dismiss">✕</button>
</div>

<div id="briefingOverlay" class="login-notif-overlay" style="display:none" onclick="if(event.target===this)closeBriefingPanel()">
  <div class="login-notif-modal" onclick="event.stopPropagation()">
    <div class="login-notif-header">
      <span class="login-notif-icon">✦</span>
      <h2>PairPal Briefing</h2>
      <button class="login-notif-close" onclick="closeBriefingPanel()" aria-label="Close">✕</button>
    </div>
    <div class="login-notif-body">
      <div class="notif-insight-banner"><?= htmlspecialchars($_insightMsg) ?></div>
      <?php if (!empty($_lowStock)): ?>
      <div class="notif-section">
        <div class="notif-section-title">⚠️ Low Stock Alerts</div>
        <?php foreach (array_slice($_lowStock, 0, 4) as $p): $crit = $p['stock'] <= max(1, intval(($p['low_stock_threshold']??8)*0.5)); ?>
        <div class="notif-item <?= $crit?'notif-item-crit':'notif-item-warn' ?>">
          <span class="notif-item-name"><?= htmlspecialchars($p['name']) ?></span>
          <span class="notif-item-badge"><?= $p['stock'] ?> left</span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if (!empty($_restock)): ?>
      <div class="notif-section">
        <div class="notif-section-title">🔄 Urgent Restocks</div>
        <?php foreach ($_restock as $r): ?>
        <div class="notif-item">
          <span class="notif-item-name"><?= htmlspecialchars($r['product']['name']) ?></span>
          <span class="notif-item-meta"><?= $r['sales_qty'] ?> sold · <?= $r['product']['stock'] ?> left</span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="login-notif-footer">
      <?php if (($_SESSION['role']??'')==='admin'): ?>
      <a href="index.php?page=inventory"     class="btn btn-sm btn-accent">📦 Restock Now</a>
      <a href="index.php?page=intelligence"  class="btn btn-sm btn-ghost">✦ Full Insights</a>
      <?php endif; ?>
      <button class="btn btn-sm btn-primary" onclick="closeBriefingPanel()">Got it</button>
    </div>
  </div>
</div>
<script>

setTimeout(function() {
    const t = document.getElementById('pairpalNotifToast');
    if (t) t.classList.add('pairpal-notif-visible');
}, 1200);

function openBriefingPanel() {
    closeBriefingToast();
    document.getElementById('briefingOverlay').style.display = 'flex';
}
function closeBriefingPanel() {
    document.getElementById('briefingOverlay').style.display = 'none';
}
function closeBriefingToast() {
    const t = document.getElementById('pairpalNotifToast');
    if (t) { t.classList.remove('pairpal-notif-visible'); setTimeout(() => t.remove(), 400); }
}
</script>
<?php endif; ?>
<?php endif; ?>
</body>