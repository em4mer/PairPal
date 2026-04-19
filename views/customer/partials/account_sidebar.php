<?php
// views/customer/partials/account_sidebar.php
// $cpage, $custSession must be set by store.php
$sess = $custSession ?? [];
?>
<div class="account-sidebar">
  <div class="account-avatar"><?= strtoupper(substr($sess['name']??'C',0,1)) ?></div>
  <div class="account-username"><?= htmlspecialchars($sess['name']??'') ?></div>
  <div class="account-email"><?= htmlspecialchars($sess['email']??'') ?></div>
  <nav class="account-nav">
    <a href="index.php?cpage=profile"  class="<?= $cpage==='profile' ?'active':'' ?>"><span class="nav-icon">👤</span> Profile</a>
    <a href="index.php?cpage=orders"   class="<?= $cpage==='orders'  ?'active':'' ?>"><span class="nav-icon">📦</span> My Orders</a>
    <a href="index.php?cpage=wishlist" class="<?= $cpage==='wishlist'?'active':'' ?>"><span class="nav-icon">♥</span> Wishlist</a>
    <a href="index.php" class=""><span class="nav-icon">🛍</span> Shop</a>
    <a href="#" onclick="customerLogout();return false"><span class="nav-icon">↩</span> Sign Out</a>
  </nav>
</div>
