<?php
require_once __DIR__ . '/../../services/FileHandler.php';
require_once __DIR__ . '/../../services/ProductRepository.php';
require_once __DIR__ . '/../../services/SalesRepository.php';
require_once __DIR__ . '/../../services/PairPalDataRepository.php';
require_once __DIR__ . '/../../services/BundleRepository.php';
require_once __DIR__ . '/../../services/OrderRepository.php';
require_once __DIR__ . '/../../services/ReviewRepository.php';
require_once __DIR__ . '/../../services/PairPalEngine.php';
require_once __DIR__ . '/../../services/CustomerRepository.php';
require_once __DIR__ . '/../../models/Cart.php';
require_once __DIR__ . '/../../models/Customer.php';
require_once __DIR__ . '/../../controllers/CartController.php';
require_once __DIR__ . '/../../controllers/CustomerAuthController.php';

$engine      = new PairPalEngine();
$productRepo = new ProductRepository();
$reviewRepo  = new ReviewRepository();
$orderRepo   = new OrderRepository();
$cartCtrl    = new CartController('customer_cart');
$custAuth    = new CustomerAuthController();
$cart        = $cartCtrl->getCart();

$q         = trim($_GET['q']       ?? '');
$cat       = trim($_GET['cat']     ?? '');
$viewProd  = trim($_GET['product'] ?? '');
$trackPage = isset($_GET['track']);
$cpage     = trim($_GET['cpage']   ?? '');

$custSession  = $custAuth->getSession();
$custLoggedIn = $custAuth->isLoggedIn();
$custWishlist = [];
if ($custLoggedIn) {
    $custData = $custAuth->getCustomerData();
    $custWishlist = json_decode($custData['wishlist'] ?? '[]', true) ?: [];
}

$categories = $productRepo->getCategories();
$featured   = $engine->getFeaturedProducts(4);
$trending   = $engine->getTrendingProducts(6);
$bundles    = $engine->getSmartBundles(3);
$products   = $productRepo->search($q, $cat);
$popMap     = $engine->getProductPopularityMap();

$viewProduct    = $viewProd ? $productRepo->findById($viewProd) : null;
$productReviews = $viewProduct ? $reviewRepo->getByProduct($viewProduct['id']) : [];
$ratingSummary  = $viewProduct ? $reviewRepo->getRatingSummary($viewProduct['id']) : null;
$suggestions    = $viewProduct ? $engine->getCartSuggestions([$viewProduct['id']], 4) : [];

$customerOrders = [];
if ($custLoggedIn && in_array($cpage, ['orders','profile'])) {
    $customerOrders = $orderRepo->getByCustomer($custSession['id']);
}

$personalRecs = [];
if ($custLoggedIn) {
    $pastProductIds = [];
    foreach (array_slice($customerOrders ?: $orderRepo->getByCustomer($custSession['id']), 0, 5) as $o) {
        foreach ($o['items'] as $item) $pastProductIds[] = $item['product_id'];
    }
    $personalRecs = !empty($pastProductIds)
        ? $engine->getCartSuggestions(array_unique($pastProductIds), 4)
        : $engine->getFeaturedProducts(4);
}

$pageTitle = match($cpage) {
    'login'    => 'Sign In',
    'register' => 'Create Account',
    'profile'  => 'My Profile',
    'orders'   => 'My Orders',
    'wishlist' => 'My Wishlist',
    default    => 'PairPal Store',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> — PairPal Store</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/store.css">
</head>
<body>

<header class="store-header">
  <div class="store-header-inner">
    <a href="index.php" class="store-brand" id="storeBrand">
      <span class="store-brand-icon" id="brandIcon">◈</span>
      <span class="store-brand-text" id="brandText">PairPal <span class="store-brand-sub">Store</span></span>
    </a>
    <nav class="store-nav">
      <?php foreach (array_slice($categories, 0, 4) as $c): ?>
        <a href="index.php?cat=<?= urlencode($c) ?>" class="<?= $cat===$c?'active':'' ?>"><?= htmlspecialchars($c) ?></a>
      <?php endforeach; ?>
      <a href="index.php#catalog">All Products</a>
    </nav>
    <div class="store-header-right">
      <form class="store-search-form" method="GET" action="index.php">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search…">
        <button type="submit">🔍</button>
      </form>
      <button class="cart-btn" onclick="toggleStoreCart()" aria-label="Open cart">
        🛒 <span class="cart-btn-count" id="cartCount"><?= $cart->getItemCount() ?></span>
      </button>
      <?php if ($custLoggedIn): ?>
        <div class="account-header-btn-wrap">
          <a href="index.php?cpage=profile" class="account-header-btn" title="My Account">
            <span class="account-header-avatar"><?= strtoupper(substr($custSession['name']??'C',0,1)) ?></span>
            <span class="account-header-name"><?= htmlspecialchars(explode(' ',$custSession['name'])[0]) ?></span>
          </a>
        </div>
      <?php else: ?>
        <a href="index.php?cpage=login" class="account-header-btn-plain">Sign In</a>
      <?php endif; ?>
      <a href="index.php?page=login" class="staff-login-btn" title="Staff Login">Staff</a>
    </div>
  </div>
</header>

<div class="store-page-wrap">
<main class="store-main">

<?php
if (in_array($cpage, ['login','register','profile','orders','wishlist'])):

if ($cpage === 'login'): ?>
<div class="store-auth-wrap">
  <div class="store-auth-box">
    <h1 class="store-auth-title">Sign In</h1>
    <p class="store-auth-sub">Access your orders and personalised recommendations.</p>
    <div id="authError" class="store-alert store-alert-error" style="display:none"></div>
    <div id="authSuccess" class="store-alert" style="display:none;background:#e8f5ee;border-color:#c0e4d0;color:#2d7a4f"></div>
    <div class="form-group-store">
      <label>Username or Email</label>
      <input type="text" id="loginUser" placeholder="your username" autocomplete="username">
    </div>
    <div class="form-group-store">
      <label>Password</label>
      <input type="password" id="loginPass" placeholder="••••••••" autocomplete="current-password">
    </div>
    <button class="btn-store-primary btn-full-store" onclick="customerLogin()" id="loginBtn">Sign In</button>
    <div class="store-auth-switch">
      Don't have an account? <a href="index.php?cpage=register">Create one →</a>
    </div>
  </div>
</div>

<?php elseif ($cpage === 'register'): ?>
<div class="store-auth-wrap">
  <div class="store-auth-box">
    <h1 class="store-auth-title">Create Account</h1>
    <p class="store-auth-sub">Join PairPal to track orders and get personalised deals.</p>
    <div id="regError" class="store-alert store-alert-error" style="display:none"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <div class="form-group-store"><label>Full Name *</label><input type="text" id="regName" placeholder="Juan dela Cruz" autocomplete="name"></div>
      <div class="form-group-store"><label>Username *</label><input type="text" id="regUser" placeholder="juandc" autocomplete="username"></div>
    </div>
    <div class="form-group-store"><label>Email *</label><input type="email" id="regEmail" placeholder="juan@email.com" autocomplete="email"></div>
    <div class="form-group-store"><label>Password * <small style="color:var(--store-text3)">(min 6 chars)</small></label><input type="password" id="regPass" placeholder="••••••••" autocomplete="new-password"></div>
    <div class="form-group-store"><label>Address</label><input type="text" id="regAddress" placeholder="Your delivery address" autocomplete="street-address"></div>
    <div class="form-group-store"><label>Contact Number</label><input type="tel" id="regContact" placeholder="09XX XXX XXXX" autocomplete="tel"></div>
    <button class="btn-store-primary btn-full-store" onclick="customerRegister()" id="regBtn">Create Account</button>
    <div class="store-auth-switch">Already have an account? <a href="index.php?cpage=login">Sign in →</a></div>
  </div>
</div>

<?php elseif ($cpage === 'profile' && $custLoggedIn): ?>
<div class="account-layout">
  <?php include __DIR__ . '/partials/account_sidebar.php'; ?>
  <div class="account-main">
    <div class="account-card">
      <div class="account-card-header">Personal Information</div>
      <div class="account-card-body">
        <div id="profileError" class="store-alert store-alert-error" style="display:none"></div>
        <div id="profileSuccess" class="store-alert" style="display:none;background:#e8f5ee;border-color:#c0e4d0;color:#2d7a4f"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem">
          <div class="form-group-store"><label>Full Name</label><input type="text" id="profName" value="<?= htmlspecialchars($custData['name']??'') ?>"></div>
          <div class="form-group-store"><label>Contact</label><input type="text" id="profContact" value="<?= htmlspecialchars($custData['contact']??'') ?>"></div>
        </div>
        <div class="form-group-store" style="margin-bottom:.75rem"><label>Delivery Address</label><input type="text" id="profAddress" value="<?= htmlspecialchars($custData['address']??'') ?>"></div>
        <div class="form-group-store"><label>Email</label><input type="email" value="<?= htmlspecialchars($custData['email']??'') ?>" disabled style="opacity:.6"></div>
        <div style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--store-border)">
          <strong style="font-size:.85rem;color:var(--store-text2)">Change Password (optional)</strong>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-top:.625rem">
            <div class="form-group-store"><label>Current Password</label><input type="password" id="profCurrPass" placeholder="••••••••"></div>
            <div class="form-group-store"><label>New Password</label><input type="password" id="profNewPass" placeholder="••••••••"></div>
          </div>
        </div>
        <button class="btn-store-primary" style="margin-top:1rem" onclick="saveProfile()">Save Changes</button>
      </div>
    </div>

    <?php if (!empty($personalRecs)): ?>
    <div class="account-card">
      <div class="account-card-header">✨ Recommended for You</div>
      <div class="account-card-body">
        <div class="product-row">
          <?php foreach ($personalRecs as $p): ?>
          <div class="store-product-card" onclick="window.location='index.php?product=<?= urlencode($p['id']) ?>'">
            <?php if (!empty($p['image'])): ?><img src="<?= htmlspecialchars($p['image']) ?>" alt="" loading="lazy"><?php else: ?><div class="card-placeholder"><?= strtoupper(substr($p['name'],0,1)) ?></div><?php endif; ?>
            <div class="store-product-cat"><?= htmlspecialchars($p['category']) ?></div>
            <div class="store-product-name"><?= htmlspecialchars($p['name']) ?></div>
            <div class="store-product-price">₱<?= number_format($p['price'],2) ?></div>
            <?php if ($p['stock']>0): ?><button class="btn-add-card" onclick="event.stopPropagation();addToCart('<?= htmlspecialchars($p['id']) ?>')">+ Cart</button><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($cpage === 'orders' && $custLoggedIn): ?>
<div class="account-layout">
  <?php include __DIR__ . '/partials/account_sidebar.php'; ?>
  <div class="account-main">
    <div class="account-card">
      <div class="account-card-header">My Orders <span class="catalog-count"><?= count($customerOrders) ?></span></div>
      <div class="account-card-body">
        <?php if (empty($customerOrders)): ?>
          <p style="color:var(--store-text3);text-align:center;padding:2rem">No orders yet. <a href="index.php" style="color:var(--store-gold)">Start shopping →</a></p>
        <?php else: ?>
        <div class="order-list">
          <?php foreach ($customerOrders as $o):
            $statusClass = $o['status'] ?? 'pending';
          ?>
          <div class="order-card">
            <div class="order-card-header">
              <div>
                <div class="order-id"><?= htmlspecialchars($o['id']) ?></div>
                <div class="order-tracking">📦 <?= htmlspecialchars($o['tracking_code']??'—') ?></div>
              </div>
              <span class="order-status-badge <?= $statusClass ?>"><?= ucfirst($statusClass) ?></span>
            </div>
            <div class="order-items-preview">
              <?= implode(', ', array_map(fn($i) => htmlspecialchars($i['name']).' ×'.$i['quantity'], array_slice($o['items'],0,3))) ?>
              <?php if (count($o['items'])>3): ?> +<?= count($o['items'])-3 ?> more<?php endif; ?>
            </div>
            <div class="order-footer">
              <span class="order-total">₱<?= number_format($o['total'],2) ?></span>
              <span class="order-date"><?= date('M j, Y', strtotime($o['created_at'])) ?></span>
              <button class="order-expand-btn" onclick="toggleOrderDetail(this)">Details ↓</button>
              <?php if (($o['status']??'') === 'pending'): ?>
              <button class="order-cancel-btn" onclick="cancelOrder('<?= htmlspecialchars($o['id']) ?>')" title="Cancel this order">Cancel Order</button>
              <?php endif; ?>
            </div>
            <div class="order-detail-rows">
              <?php foreach ($o['items'] as $item): ?>
              <div class="order-detail-row"><span><?= htmlspecialchars($item['name']) ?> ×<?= $item['quantity'] ?></span><span>₱<?= number_format($item['subtotal'] ?? ($item['price'] * $item['quantity']), 2) ?></span></div>
              <?php endforeach; ?>
              <?php if (($o['discount_amount']??0)>0): ?>
              <div class="order-detail-row"><span><?= htmlspecialchars($o['bundle_applied']??'Discount') ?></span><span>−₱<?= number_format($o['discount_amount'],2) ?></span></div>
              <?php endif; ?>
              <div class="order-detail-row total-row"><span>Total</span><span>₱<?= number_format($o['total'],2) ?></span></div>
              <div class="order-detail-row"><span>Address</span><span><?= htmlspecialchars($o['customer_address']??'') ?></span></div>
              <div class="order-detail-row"><span>Est. Delivery</span><span><?= $o['estimated_days']??'3–7' ?> business days</span></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="account-card">
      <div class="account-card-header">Track by Code</div>
      <div class="account-card-body">
        <div class="track-form-wrap" style="margin:0">
          <input type="text" id="trackCode" placeholder="e.g. PPABCD1234" class="track-input">
          <button class="btn-store-primary" onclick="trackOrder()">Track</button>
        </div>
        <div id="trackResult" class="track-result" style="display:none;margin-top:1rem"></div>
      </div>
    </div>
  </div>
</div>

<?php elseif ($cpage === 'wishlist' && $custLoggedIn): ?>
<div class="account-layout">
  <?php include __DIR__ . '/partials/account_sidebar.php'; ?>
  <div class="account-main">
    <div class="account-card">
      <div class="account-card-header">My Wishlist <span class="catalog-count"><?= count($custWishlist) ?></span></div>
      <div class="account-card-body">
        <?php if (empty($custWishlist)): ?>
          <p style="color:var(--store-text3);text-align:center;padding:2rem">Your wishlist is empty. Tap ♡ on any product to save it.</p>
        <?php else: ?>
        <div class="catalog-grid">
          <?php foreach ($custWishlist as $wid):
            $wp = $productRepo->findById($wid); if (!$wp) continue; ?>
          <div class="store-product-card" onclick="window.location='index.php?product=<?= urlencode($wp['id']) ?>'">
            <?php if (!empty($wp['image'])): ?><img src="<?= htmlspecialchars($wp['image']) ?>" alt="" loading="lazy"><?php else: ?><div class="card-placeholder"><?= strtoupper(substr($wp['name'],0,1)) ?></div><?php endif; ?>
            <button class="wishlist-btn wished" onclick="event.stopPropagation();toggleWishlist('<?= htmlspecialchars($wp['id']) ?>',this)" title="Remove from wishlist">♥</button>
            <div class="store-product-cat"><?= htmlspecialchars($wp['category']) ?></div>
            <div class="store-product-name"><?= htmlspecialchars($wp['name']) ?></div>
            <div class="store-product-price">₱<?= number_format($wp['price'],2) ?></div>
            <?php if ($wp['stock']>0): ?><button class="btn-add-card" onclick="event.stopPropagation();addToCart('<?= htmlspecialchars($wp['id']) ?>')">+ Cart</button><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php elseif (in_array($cpage,['login','register','profile','orders','wishlist']) && !$custLoggedIn): ?>
<div class="store-auth-wrap">
  <div class="store-auth-box" style="text-align:center;padding:3rem">
    <div style="font-size:2rem;margin-bottom:1rem">🔒</div>
    <h2 style="font-family:var(--font-head);margin-bottom:.5rem">Sign in required</h2>
    <p style="color:var(--store-text2);margin-bottom:1.5rem">Please sign in to access your account.</p>
    <a href="index.php?cpage=login" class="btn-store-primary">Sign In</a>
    &nbsp;
    <a href="index.php?cpage=register" class="btn-store-ghost">Create Account</a>
  </div>
</div>
<?php endif; ?>

<?php else:
?>

<?php if ($trackPage): ?>
<div class="track-page">
  <h1 class="section-title">Track Your Order</h1>
  <p class="track-sub">Enter the tracking code from your order confirmation.</p>
  <div class="track-form-wrap">
    <input type="text" id="trackCode" placeholder="e.g. PPABCD1234" class="track-input">
    <button class="btn-store-primary" onclick="trackOrder()">Track</button>
  </div>
  <div id="trackResult" class="track-result" style="display:none"></div>
</div>

<?php elseif ($viewProduct): ?>
<div class="product-detail">
  <a href="index.php<?= $cat?'?cat='.urlencode($cat):'' ?>" class="back-link">← Back</a>
  <div class="product-detail-grid">
    <div class="product-detail-img-wrap">
      <?php if (!empty($viewProduct['image'])): ?>
        <img src="<?= htmlspecialchars($viewProduct['image']) ?>" alt="<?= htmlspecialchars($viewProduct['name']) ?>">
      <?php else: ?>
        <div class="product-detail-placeholder"><?= strtoupper(substr($viewProduct['name'],0,1)) ?></div>
      <?php endif; ?>
    </div>
    <div class="product-detail-info">
      <div class="product-detail-cat"><?= htmlspecialchars($viewProduct['category']) ?></div>
      <h1 class="product-detail-name"><?= htmlspecialchars($viewProduct['name']) ?></h1>
      <?php if ($ratingSummary && $ratingSummary['count']>0): ?>
      <div class="rating-display">
        <span class="stars"><?= str_repeat('★',(int)round($ratingSummary['average'])).str_repeat('☆',5-(int)round($ratingSummary['average'])) ?></span>
        <span class="rating-text"><?= $ratingSummary['average'] ?>/5 (<?= $ratingSummary['count'] ?> review<?= $ratingSummary['count']!==1?'s':'' ?>)</span>
      </div>
      <?php endif; ?>
      <div class="product-detail-price">₱<?= number_format($viewProduct['price'],2) ?></div>
      <?php if (!empty($viewProduct['description'])): ?><div class="product-detail-desc"><?= htmlspecialchars($viewProduct['description']) ?></div><?php endif; ?>
      <div class="product-detail-meta">
        <div><strong>Category:</strong> <?= htmlspecialchars($viewProduct['category']) ?></div>
        <?php if (!empty($viewProduct['supplier'])): ?><div><strong>Supplier:</strong> <?= htmlspecialchars($viewProduct['supplier']) ?></div><?php endif; ?>
      </div>
      <?php
        $threshold  = $viewProduct['low_stock_threshold'] ?? 8;
        $stock      = $viewProduct['stock'];
        $stockClass = $stock<=0?'out':($stock<=$threshold?'low':'in');
        $stockText  = $stock<=0?'✗ Out of Stock':($stock<=$threshold?"⚠ Only {$stock} left!":"✓ In Stock ({$stock} available)");
      ?>
      <div class="stock-status <?= $stockClass ?>"><?= $stockText ?></div>
      <?php if ($stock>0): ?>
      <div class="qty-row">
        <label>Qty:</label>
        <input type="number" id="detailQty" value="1" min="1" max="<?= $stock ?>" class="qty-field">
        <button class="btn-store-primary" onclick="addToCart('<?= htmlspecialchars($viewProduct['id']) ?>',parseInt(document.getElementById('detailQty').value||1))">Add to Cart</button>
        <?php if ($custLoggedIn): ?>
        <button class="wishlist-btn <?= in_array($viewProduct['id'],$custWishlist)?'wished':'' ?>" onclick="toggleWishlist('<?= htmlspecialchars($viewProduct['id']) ?>',this)" title="Add to wishlist" style="position:static;width:38px;height:38px;border-radius:var(--store-radius)">
          <?= in_array($viewProduct['id'],$custWishlist)?'♥':'♡' ?>
        </button>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="delivery-estimate">🚚 Estimated delivery: 3–7 business days</div>
    </div>
  </div>
  <?php if (!empty($suggestions)): ?>
  <div class="section-block">
    <h2 class="section-title">Frequently Bought Together</h2>
    <div class="product-row">
      <?php foreach ($suggestions as $s): ?>
      <div class="store-product-card" onclick="window.location='index.php?product=<?= urlencode($s['id']) ?>'">
        <?php if (!empty($s['image'])): ?><img src="<?= htmlspecialchars($s['image']) ?>" alt="" loading="lazy"><?php else: ?><div class="card-placeholder"><?= strtoupper(substr($s['name'],0,1)) ?></div><?php endif; ?>
        <div class="store-product-cat"><?= htmlspecialchars($s['category']) ?></div>
        <div class="store-product-name"><?= htmlspecialchars($s['name']) ?></div>
        <div class="store-product-price">₱<?= number_format($s['price'],2) ?></div>
        <?php if ($s['stock']>0): ?><button class="btn-add-card" onclick="event.stopPropagation();addToCart('<?= htmlspecialchars($s['id']) ?>')">+ Cart</button><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  <!-- Reviews -->
  <div class="section-block">
    <h2 class="section-title">Customer Reviews</h2>
    <?php if (empty($productReviews)): ?><p class="text-muted-store">No reviews yet. Be the first!</p>
    <?php else: ?>
    <div class="review-list">
      <?php foreach ($productReviews as $rev): ?>
      <div class="review-card">
        <div class="review-header">
          <strong><?= htmlspecialchars($rev['author']) ?></strong>
          <span class="review-stars"><?= str_repeat('★',(int)$rev['rating']).str_repeat('☆',5-(int)$rev['rating']) ?></span>
          <span class="review-date text-muted-store"><?= date('M j, Y',strtotime($rev['date'])) ?></span>
        </div>
        <?php if (!empty($rev['comment'])): ?><p class="review-comment"><?= htmlspecialchars($rev['comment']) ?></p><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="review-form">
      <h3>Write a Review</h3>
      <div class="form-group-store"><label>Your Name</label><input type="text" id="revAuthor" placeholder="Anonymous" value="<?= htmlspecialchars($custSession['name']??'') ?>"></div>
      <div class="form-group-store"><label>Rating *</label>
        <div class="star-picker" id="starPicker"><?php for($i=1;$i<=5;$i++): ?><span class="star" onclick="setRating(<?=$i?>)" data-v="<?=$i?>">☆</span><?php endfor; ?></div>
        <input type="hidden" id="revRating" value="0">
      </div>
      <div class="form-group-store"><label>Comment</label><textarea id="revComment" rows="3" placeholder="Share your experience…"></textarea></div>
      <button class="btn-store-primary" onclick="submitReview('<?= htmlspecialchars($viewProduct['id']) ?>')">Submit Review</button>
      <div id="reviewMsg" style="margin-top:.5rem;font-size:.85rem;display:none"></div>
    </div>
  </div>
</div>

<?php else: ?>

<?php if (!$q&&!$cat): ?>
<div class="store-hero">
  <div class="store-hero-text">
    <div class="store-hero-eyebrow">◈ PairPal Store</div>
    <h1>Premium goods,<br>perfectly paired.</h1>
    <p>Smart bundle deals curated by PairPal Intelligence — just for you.</p>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
      <a href="#catalog" class="btn-store-primary">Browse Products</a>
      <?php if (!$custLoggedIn): ?><a href="index.php?cpage=register" class="btn-store-ghost" style="color:#fff;border-color:rgba(255,255,255,.3)">Create Account</a><?php else: ?><a href="index.php?cpage=orders" class="btn-store-ghost" style="color:#fff;border-color:rgba(255,255,255,.3)">My Orders</a><?php endif; ?>
    </div>
  </div>
  <div class="store-hero-art">
    <div class="hero-slice hero-slice-1"><img src="assets/img/hero_coffee.svg"  alt="Premium Coffee" loading="lazy"></div>
    <div class="hero-slice hero-slice-2"><img src="assets/img/hero_tumbler.svg" alt="Reusable Tumbler" loading="lazy"></div>
    <div class="hero-slice hero-slice-3"><img src="assets/img/hero_matcha.svg"  alt="Matcha Set" loading="lazy"></div>
  </div>
</div>

<?php if (!empty($personalRecs) && $custLoggedIn): ?>
<div class="section-block">
  <div class="section-header"><h2 class="section-title">✨ Recommended for You</h2></div>
  <div class="product-row">
    <?php foreach ($personalRecs as $p): ?>
    <div class="store-product-card" onclick="window.location='index.php?product=<?= urlencode($p['id']) ?>'">
      <?php if (!empty($p['image'])): ?><img src="<?= htmlspecialchars($p['image']) ?>" alt="" loading="lazy"><?php else: ?><div class="card-placeholder"><?= strtoupper(substr($p['name'],0,1)) ?></div><?php endif; ?>
      <div class="store-product-cat"><?= htmlspecialchars($p['category']) ?></div>
      <div class="store-product-name"><?= htmlspecialchars($p['name']) ?></div>
      <div class="store-product-price">₱<?= number_format($p['price'],2) ?></div>
      <?php if ($p['stock']>0): ?><button class="btn-add-card" onclick="event.stopPropagation();addToCart('<?= htmlspecialchars($p['id']) ?>')">+ Cart</button><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($featured)): ?>
<div class="section-block">
  <div class="section-header"><h2 class="section-title">⭐ Featured Products</h2></div>
  <div class="featured-grid">
    <?php foreach ($featured as $p): ?>
    <div class="featured-card" onclick="window.location='index.php?product=<?= urlencode($p['id']) ?>'">
      <?php if (!empty($p['image'])): ?><img src="<?= htmlspecialchars($p['image']) ?>" alt="" loading="lazy"><?php else: ?><div class="featured-placeholder"><?= strtoupper(substr($p['name'],0,1)) ?></div><?php endif; ?>
      <div class="featured-info">
        <div class="store-product-cat"><?= htmlspecialchars($p['category']) ?></div>
        <div class="featured-name"><?= htmlspecialchars($p['name']) ?></div>
        <div class="featured-price">₱<?= number_format($p['price'],2) ?></div>
        <?php if ($p['stock']>0): ?><button class="btn-add-card" onclick="event.stopPropagation();addToCart('<?= htmlspecialchars($p['id']) ?>')">Add to Cart</button><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($bundles)): ?>
<div class="section-block bundle-deals-section" id="bundle-deals">
  <div class="section-header"><div><h2 class="section-title">🎁 Bundle Deals</h2><p class="section-sub">Automatically detected by PairPal Intelligence</p></div></div>
  <div class="bundle-deals-grid">
    <?php foreach ($bundles as $b): ?>
    <div class="bundle-deal-card">
      <div class="bundle-deal-badge"><?= $b['discount_type']==='percent'?$b['discount_value'].'% OFF':'₱'.$b['discount_value'].' OFF' ?></div>
      <div class="bundle-deal-products">
        <?php foreach ($b['products'] as $i=>$bp): ?>
          <?php if ($i>0): ?><span class="bundle-deal-plus">+</span><?php endif; ?>
          <div class="bundle-deal-item">
            <?php if (!empty($bp['image'])): ?><img src="<?= htmlspecialchars($bp['image']) ?>" alt="" loading="lazy"><?php else: ?><div class="bundle-deal-img-ph"><?= strtoupper(substr($bp['name'],0,1)) ?></div><?php endif; ?>
            <div class="bundle-deal-item-name"><?= htmlspecialchars($bp['name']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="bundle-deal-pricing">
        <span class="bundle-deal-orig">₱<?= number_format($b['original_price'],2) ?></span>
        <span class="bundle-deal-now">₱<?= number_format($b['bundle_price'],2) ?></span>
        <span class="bundle-deal-save">Save ₱<?= number_format($b['savings']??$b['discount_amount']??0,2) ?></span>
      </div>
      <button class="btn-store-primary" onclick="addBundle(<?= htmlspecialchars(json_encode(array_column($b['products'],'id'))) ?>)">Add Bundle to Cart</button>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($trending)): ?>
<div class="section-block">
  <div class="section-header"><h2 class="section-title">🔥 Trending Now</h2></div>
  <div class="product-row">
    <?php foreach ($trending as $p): ?>
    <div class="store-product-card" onclick="window.location='index.php?product=<?= urlencode($p['id']) ?>'">
      <?php if (!empty($p['image'])): ?><img src="<?= htmlspecialchars($p['image']) ?>" alt="" loading="lazy"><?php else: ?><div class="card-placeholder"><?= strtoupper(substr($p['name'],0,1)) ?></div><?php endif; ?>
      <div class="hot-tag">🔥 Trending</div>
      <div class="store-product-cat"><?= htmlspecialchars($p['category']) ?></div>
      <div class="store-product-name"><?= htmlspecialchars($p['name']) ?></div>
      <div class="store-product-price">₱<?= number_format($p['price'],2) ?></div>
      <?php if ($p['stock']>0): ?><button class="btn-add-card" onclick="event.stopPropagation();addToCart('<?= htmlspecialchars($p['id']) ?>')">+ Cart</button><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Recently Viewed (client-side, sessionStorage) -->
<div class="section-block" id="recentlyViewedSection" style="display:none">
  <div class="section-header"><h2 class="section-title">👁 Recently Viewed</h2></div>
  <div class="product-row" id="recentlyViewedRow"></div>
</div>

<div class="section-block" id="catalog">
  <div class="section-header">
    <h2 class="section-title"><?= $cat?htmlspecialchars($cat):($q?'Results for "'.htmlspecialchars($q).'"':'All Products') ?><span class="catalog-count"><?= count($products) ?></span></h2>
    <form method="GET" action="index.php" class="catalog-filter-form">
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search…">
      <select name="cat">
        <option value="">All Categories</option>
        <?php foreach ($categories as $c): ?><option value="<?= htmlspecialchars($c) ?>" <?= $cat===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option><?php endforeach; ?>
      </select>
      <button type="submit" class="btn-store-secondary">Filter</button>
      <?php if ($q||$cat): ?><a href="index.php" class="btn-store-ghost">Clear</a><?php endif; ?>
    </form>
  </div>
  <div class="catalog-grid">
    <?php foreach ($products as $p): $threshold=$p['low_stock_threshold']??8; ?>
    <div class="store-product-card" onclick="window.location='index.php?product=<?= urlencode($p['id']) ?>'">
      <?php if (!empty($p['image'])): ?><img src="<?= htmlspecialchars($p['image']) ?>" alt="" loading="lazy"><?php else: ?><div class="card-placeholder"><?= strtoupper(substr($p['name'],0,1)) ?></div><?php endif; ?>
      <?php if (($popMap[$p['id']]??0)>=5): ?><div class="hot-tag">🔥 Hot</div><?php endif; ?>
      <?php if ($custLoggedIn): ?>
      <button class="wishlist-btn <?= in_array($p['id'],$custWishlist)?'wished':'' ?>" onclick="event.stopPropagation();toggleWishlist('<?= htmlspecialchars($p['id']) ?>',this)" title="Wishlist">
        <?= in_array($p['id'],$custWishlist)?'♥':'♡' ?>
      </button>
      <?php endif; ?>
      <div class="store-product-cat"><?= htmlspecialchars($p['category']) ?></div>
      <div class="store-product-name"><?= htmlspecialchars($p['name']) ?></div>
      <div class="store-product-stock <?= $p['stock']<=0?'out':($p['stock']<=$threshold?'low':'in') ?>">
        <?= $p['stock']<=0?'Out of Stock':($p['stock']<=$threshold?"Only {$p['stock']} left":'In Stock') ?>
      </div>
      <div class="store-product-price">₱<?= number_format($p['price'],2) ?></div>
      <?php if ($p['stock']>0): ?>
      <div class="card-qty-row" onclick="event.stopPropagation()">
        <button class="card-qty-btn" onclick="event.stopPropagation();cardQtyChange('<?= htmlspecialchars($p['id']) ?>',-1)">−</button>
        <span class="card-qty-val" id="cqty_<?= htmlspecialchars($p['id']) ?>">1</span>
        <button class="card-qty-btn" onclick="event.stopPropagation();cardQtyChange('<?= htmlspecialchars($p['id']) ?>',1,<?= $p['stock'] ?>)">+</button>
        <button class="btn-add-card card-add-btn" onclick="event.stopPropagation();addCardToCart('<?= htmlspecialchars($p['id']) ?>')">+ Cart</button>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (empty($products)): ?><div class="empty-catalog-store">No products found. <a href="index.php">View all →</a></div><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

</main>

<footer class="store-footer">
  <div class="store-footer-inner">
    <span>◈ PairPal Store</span>
    <div class="store-footer-links">
      <a href="index.php?track">Track Order</a>
      <?php if ($custLoggedIn): ?>
        <a href="index.php?cpage=profile">My Account</a>
        <a href="#" onclick="customerLogout();return false">Sign Out</a>
      <?php else: ?>
        <a href="index.php?cpage=login">Sign In</a>
        <a href="index.php?cpage=register">Register</a>
      <?php endif; ?>
      <a href="index.php?page=login">Staff Login</a>
    </div>
  </div>
</footer>
</div><!-- /.store-page-wrap -->

<!-- Cart Drawer -->
<div class="store-cart-overlay" id="cartOverlay" onclick="toggleStoreCart()"></div>
<div class="store-cart-drawer" id="cartDrawer">
  <div class="cart-drawer-header"><h2>Your Cart</h2><button onclick="toggleStoreCart()">✕</button></div>
  <div class="cart-drawer-items" id="cartDrawerItems"><?= renderCartDrawerItems($cart) ?></div>
  <div class="cart-drawer-footer" id="cartDrawerFooter"><?= renderCartDrawerFooter($cart) ?></div>
</div>

<!-- Checkout Modal -->
<div class="store-modal-overlay" id="checkoutModalOverlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="store-modal" onclick="event.stopPropagation()">
    <div class="store-modal-header"><h2>Checkout</h2><button onclick="document.getElementById('checkoutModalOverlay').style.display='none'">✕</button></div>
    <div class="store-modal-body">
      <div class="checkout-summary" id="checkoutSummary"><?= renderCheckoutSummary($cart) ?></div>
      <div class="checkout-form">
        <div class="form-group-store"><label>Full Name *</label><input type="text" id="co_name" value="<?= htmlspecialchars($custSession['name']??'') ?>" placeholder="Juan dela Cruz" autocomplete="name"></div>
        <div class="form-group-store"><label>Delivery Address *</label><textarea id="co_address" rows="2" placeholder="House No., Street, Barangay, City" autocomplete="street-address"><?= htmlspecialchars($custData['address']??'') ?></textarea></div>
        <div class="form-group-store"><label>Contact Number *</label><input type="tel" id="co_contact" value="<?= htmlspecialchars($custData['contact']??'') ?>" placeholder="09XX XXX XXXX" autocomplete="tel"></div>
        <div class="form-group-store"><label>Email (optional)</label><input type="email" id="co_email" value="<?= htmlspecialchars($custSession['email']??'') ?>" placeholder="juan@email.com" autocomplete="email"></div>
        <div class="form-group-store"><label>Notes</label><input type="text" id="co_notes" placeholder="e.g. Leave at gate"></div>
        <div class="delivery-note">🚚 Estimated delivery: 3–7 business days</div>
      </div>
      <div id="checkoutError" class="store-alert store-alert-error" style="display:none"></div>
    </div>
    <div class="store-modal-footer">
      <button class="btn-store-ghost" onclick="document.getElementById('checkoutModalOverlay').style.display='none'">Cancel</button>
      <button class="btn-store-primary" onclick="placeOrder()" id="placeOrderBtn">Place Order</button>
    </div>
  </div>
</div>

<!-- Order Success Modal -->
<div class="store-modal-overlay" id="successModalOverlay" style="display:none">
  <div class="store-modal success-modal">
    <div class="success-icon">✓</div>
    <h2>Order Placed!</h2>
    <p>Your tracking code:</p>
    <div class="tracking-code" id="displayTrackingCode"></div>
    <div id="successOrderId" style="font-size:.8rem;color:var(--store-text3);margin-top:.25rem"></div>
    <!-- Order summary injected by JS after checkout -->
    <div id="successOrderSummary" style="width:100%;margin-top:1rem;text-align:left;display:none">
      <div style="font-size:.78rem;color:var(--store-text3);font-weight:600;margin-bottom:.375rem">ORDER SUMMARY</div>
      <div id="successItemsList" style="display:flex;flex-direction:column;gap:.25rem;font-size:.82rem"></div>
      <div id="successDelivery" style="font-size:.8rem;color:var(--store-text3);margin-top:.625rem"></div>
    </div>
    <p class="text-muted-store" style="margin-top:.75rem">Save your tracking code to follow your delivery.</p>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;justify-content:center;margin-top:1.25rem">
      <?php if ($custLoggedIn): ?>
      <a href="index.php?cpage=orders" class="btn-store-primary" style="display:inline-block">View My Orders</a>
      <?php endif; ?>
      <button class="btn-store-ghost" onclick="location.href='index.php'">Continue Shopping</button>
    </div>
  </div>
</div>

<!-- Chatbot Widget -->
<button class="chat-bubble-btn" id="chatBubble" onclick="toggleChat()" title="PairPal Assistant">
  💬
  <span class="chat-unread" id="chatUnread">1</span>
</button>
<div class="chat-panel" id="chatPanel">
  <div class="chat-header">
    <span class="chat-header-icon">◈</span>
    <div><div class="chat-header-title">PairPal Assistant</div><div class="chat-header-sub">Ask me anything</div></div>
    <button class="chat-clear-btn" onclick="clearChatHistory()" title="Clear chat">🗑</button>
    <button class="chat-close-btn" onclick="toggleChat()">✕</button>
  </div>
  <div class="chat-messages" id="chatMessages">
    <div class="chat-msg bot">
      <div class="chat-msg-avatar">◈</div>
      <div>
        <div class="chat-bubble">Hi<?= $custLoggedIn?' '.htmlspecialchars(explode(' ',$custSession['name'])[0]):'!' ?>! 👋 I'm PairPal. How can I help you today?</div>
        <div class="chat-actions">
          <button class="chat-action-btn" onclick="sendQuickChat('How do I order?')">How to Order</button>
          <button class="chat-action-btn" onclick="sendQuickChat('Show me best sellers')">Best Sellers</button>
          <button class="chat-action-btn" onclick="sendQuickChat('Bundle deals')">Bundle Deals</button>
        </div>
      </div>
    </div>
  </div>
  <div class="chat-input-row">
    <input type="text" class="chat-input" id="chatInput" placeholder="Type a message…" onkeydown="if(event.key==='Enter')sendChat()">
    <button class="chat-send-btn" onclick="sendChat()">➤</button>
  </div>
</div>

<!-- Toast -->
<div class="store-toast" id="storeToast"></div>

<?php
function renderCartDrawerItems(Cart $cart): string {
    if ($cart->isEmpty()) {
        global $custLoggedIn;
        if (!$custLoggedIn) {
            return '<div class="cart-drawer-empty" style="text-align:center;padding:1.5rem">
                <div style="font-size:1.5rem;margin-bottom:.5rem">🔒</div>
                <div style="font-weight:600;margin-bottom:.5rem">Sign in to shop</div>
                <div style="font-size:.82rem;color:var(--store-text3);margin-bottom:1rem">Create an account to add items and place orders.</div>
                <a href="index.php?cpage=login" class="btn-store-primary" style="display:block;text-align:center">Sign In</a>
                <a href="index.php?cpage=register" class="btn-store-ghost" style="display:block;text-align:center;margin-top:.5rem">Create Account</a>
            </div>';
        }
        return '<div class="cart-drawer-empty">Your cart is empty.</div>';
    }
    $html = '';
    if ($cart->getBundleMessage()) $html .= '<div class="cart-bundle-notice">'.htmlspecialchars($cart->getBundleMessage()).'</div>';
    foreach ($cart->getItems() as $item) {
        $html .= '<div class="cart-drawer-item" data-id="'.htmlspecialchars($item['product_id']).'">';
        $html .= '<div class="cdi-info"><div class="cdi-name">'.htmlspecialchars($item['name']).'</div><div class="cdi-price">₱'.number_format($item['price'],2).' × '.$item['quantity'].'</div></div>';
        $html .= '<div class="cdi-controls"><button onclick="customerUpdateQty(\''.htmlspecialchars($item['product_id']).'\','.(($item['quantity']-1)).')">−</button><span class="cdi-qty">'.$item['quantity'].'</span><button onclick="customerUpdateQty(\''.htmlspecialchars($item['product_id']).'\','.(($item['quantity']+1)).')">+</button></div>';
        $html .= '<div class="cdi-subtotal">₱'.number_format($item['price']*$item['quantity'],2).'</div></div>';
    }
    return $html;
}
function renderCartDrawerFooter(Cart $cart): string {
    if ($cart->isEmpty()) return '';
    $html = '';
    if ($cart->getDiscountAmount()>0) {
        $html .= '<div class="cart-drawer-row"><span>Subtotal</span><span>₱'.number_format($cart->getSubtotal(),2).'</span></div>';
        $label = $cart->getBundleName() ? '🎁 '.htmlspecialchars($cart->getBundleName()) : 'Discount';
        $html .= '<div class="cart-drawer-row discount-row-store"><span>'.$label.'</span><span>−₱'.number_format($cart->getDiscountAmount(),2).'</span></div>';
    }
    $html .= '<div class="cart-drawer-row total-row"><span>Total</span><span>₱'.number_format($cart->getTotal(),2).'</span></div>';
    $html .= '<button class="btn-store-primary btn-full-store" onclick="openCheckoutModal()">Proceed to Checkout →</button>';
    return $html;
}
function renderCheckoutSummary(Cart $cart): string {
    if ($cart->isEmpty()) return '<p style="color:var(--store-text3);font-size:.875rem">Cart is empty.</p>';
    $html = '';
    foreach ($cart->getItems() as $item) $html .= '<div class="checkout-item"><span>'.htmlspecialchars($item['name']).' ×'.$item['quantity'].'</span><span>₱'.number_format($item['price']*$item['quantity'],2).'</span></div>';
    if ($cart->getDiscountAmount()>0) {
        $label = $cart->getBundleName() ? '🎁 '.htmlspecialchars($cart->getBundleName()) : 'Discount';
        $html .= '<div class="checkout-item discount-line"><span>'.$label.'</span><span>−₱'.number_format($cart->getDiscountAmount(),2).'</span></div>';
    }
    $html .= '<div class="checkout-total"><span>Total</span><span>₱'.number_format($cart->getTotal(),2).'</span></div>';
    return $html;
}
?>

<script>
const CUST_LOGGED_IN = <?= $custLoggedIn ? 'true' : 'false' ?>;

// ── Store cart ────────────────────────────────────────────────
function toggleStoreCart() {
    const d=document.getElementById('cartDrawer'),o=document.getElementById('cartOverlay');
    const open=d.classList.toggle('open'); o.style.display=open?'block':'none'; document.body.style.overflow=open?'hidden':'';
}
function openCheckoutModal() {
    if (!CUST_LOGGED_IN) {
        storeToast('Please sign in to checkout.', 'error');
        setTimeout(() => location.href = 'index.php?cpage=login', 900);
        return;
    }
    document.getElementById('checkoutModalOverlay').style.display='flex';
}

async function addToCart(id,qty=1) {
    if (!CUST_LOGGED_IN) {
        storeToast('Please sign in to add items to your cart.', 'error');
        setTimeout(() => location.href = 'index.php?cpage=login', 900);
        return;
    }
    const fd=new FormData(); fd.append('action','customer_cart_add'); fd.append('product_id',id); fd.append('qty',qty);
    try { const res=await fetch('index.php',{method:'POST',body:fd}); const data=await res.json();
        if(data.success){ storeToast('Added to cart ✓','success'); if(data.bundle_message) storeToast(data.bundle_message,'bundle'); await refreshCart(); }
        else storeToast(data.message||'Could not add item.','error');
    } catch(e){ storeToast('Network error.','error'); }
}
async function addBundle(ids){ for(const id of ids) await addToCart(id,1); }
async function customerUpdateQty(id,qty) {
    if(qty<=0){await customerRemove(id);return;}
    const fd=new FormData(); fd.append('action','customer_cart_update'); fd.append('product_id',id); fd.append('qty',qty);
    try { const res=await fetch('index.php',{method:'POST',body:fd}); const data=await res.json(); if(data.success) await refreshCart(); else storeToast(data.message,'error'); } catch(e){}
}
async function customerRemove(id) {
    const fd=new FormData(); fd.append('action','customer_cart_remove'); fd.append('product_id',id);
    try { const res=await fetch('index.php',{method:'POST',body:fd}); const data=await res.json(); if(data.success) await refreshCart(); } catch(e){}
}
async function refreshCart() {
    const fd=new FormData(); fd.append('action','customer_cart_state');
    try { const res=await fetch('index.php',{method:'POST',body:fd}); const data=await res.json(); if(!data.success) return;
        const s=data.state; document.getElementById('cartCount').textContent=s.count||0;
        const itemsEl=document.getElementById('cartDrawerItems'), footerEl=document.getElementById('cartDrawerFooter');
        if(s.count===0){ itemsEl.innerHTML='<div class="cart-drawer-empty">Your cart is empty.</div>'; footerEl.innerHTML=''; }
        else {
            let ih=s.bundle_message?`<div class="cart-bundle-notice">${escHtml(s.bundle_message)}</div>`:'';
            s.items.forEach(i=>{ ih+=`<div class="cart-drawer-item" data-id="${i.product_id}"><div class="cdi-info"><div class="cdi-name">${escHtml(i.name)}</div><div class="cdi-price">₱${fmtNum(i.price)} × ${i.quantity}</div></div><div class="cdi-controls"><button onclick="customerUpdateQty('${i.product_id}',${i.quantity-1})">−</button><span class="cdi-qty">${i.quantity}</span><button onclick="customerUpdateQty('${i.product_id}',${i.quantity+1})">+</button></div><div class="cdi-subtotal">₱${fmtNum(i.price*i.quantity)}</div></div>`; });
            itemsEl.innerHTML=ih;
            let fh=s.discount_amount>0?`<div class="cart-drawer-row"><span>Subtotal</span><span>₱${fmtNum(s.subtotal)}</span></div><div class="cart-drawer-row discount-row-store"><span>${s.bundle_name?'🎁 '+escHtml(s.bundle_name):'Discount'}</span><span>−₱${fmtNum(s.discount_amount)}</span></div>`:'';
            fh+=`<div class="cart-drawer-row total-row"><span>Total</span><span>₱${fmtNum(s.total)}</span></div><button class="btn-store-primary btn-full-store" onclick="openCheckoutModal()">Proceed to Checkout →</button>`;
            footerEl.innerHTML=fh;
            const sumEl=document.getElementById('checkoutSummary');
            if(sumEl){ let sh=''; s.items.forEach(i=>{ sh+=`<div class="checkout-item"><span>${escHtml(i.name)} ×${i.quantity}</span><span>₱${fmtNum(i.price*i.quantity)}</span></div>`; }); if(s.discount_amount>0) sh+=`<div class="checkout-item discount-line"><span>${s.bundle_name?'🎁 '+escHtml(s.bundle_name):'Discount'}</span><span>−₱${fmtNum(s.discount_amount)}</span></div>`; sh+=`<div class="checkout-total"><span>Total</span><span>₱${fmtNum(s.total)}</span></div>`; sumEl.innerHTML=sh; }
        }
    } catch(e){}
}

// ── Checkout ──────────────────────────────────────────────────
async function placeOrder() {
    const name=document.getElementById('co_name').value.trim(), address=document.getElementById('co_address').value.trim(), contact=document.getElementById('co_contact').value.trim(), email=document.getElementById('co_email').value.trim(), notes=document.getElementById('co_notes').value.trim();
    const errEl=document.getElementById('checkoutError'), btn=document.getElementById('placeOrderBtn');
    errEl.style.display='none';
    if(!name){errEl.textContent='Please enter your full name.';errEl.style.display='block';return;}
    if(!address){errEl.textContent='Please enter your delivery address.';errEl.style.display='block';return;}
    if(!contact){errEl.textContent='Please enter your contact number.';errEl.style.display='block';return;}
    btn.disabled=true; btn.textContent='Placing Order…';
    const fd=new FormData(); fd.append('action','customer_place_order'); fd.append('name',name); fd.append('address',address); fd.append('contact',contact); fd.append('email',email); fd.append('notes',notes);
    try { const res=await fetch('index.php',{method:'POST',body:fd}); const data=await res.json();
        if(data.success){
            document.getElementById('checkoutModalOverlay').style.display='none';
            document.getElementById('displayTrackingCode').textContent=data.tracking_code;
            document.getElementById('successOrderId').textContent='Order ID: '+data.order.id;
            // Populate order summary
            const items = data.order.items||[];
            const disc  = parseFloat(data.order.discount_amount||0);
            let ih = items.map(i=>`<div style="display:flex;justify-content:space-between"><span>${escHtml(i.name)} ×${i.quantity}</span><span>₱${fmtNum(i.price*i.quantity)}</span></div>`).join('');
            if(disc>0) ih += `<div style="display:flex;justify-content:space-between;color:var(--store-green)"><span>${data.order.bundle_applied?'🎁 '+escHtml(data.order.bundle_applied):'Discount'}</span><span>−₱${fmtNum(disc)}</span></div>`;
            ih += `<div style="display:flex;justify-content:space-between;font-family:var(--font-head);font-weight:800;border-top:1px solid var(--store-border);padding-top:.375rem;margin-top:.25rem"><span>Total</span><span>₱${fmtNum(data.order.total)}</span></div>`;
            document.getElementById('successItemsList').innerHTML=ih;
            document.getElementById('successDelivery').textContent='📍 Deliver to: '+escHtml(data.order.customer_address)+' · Est. '+( data.order.estimated_days||'3–7')+' business days';
            document.getElementById('successOrderSummary').style.display='block';
            document.getElementById('successModalOverlay').style.display='flex';
            document.getElementById('cartCount').textContent='0';
        }
        else { errEl.textContent=(data.errors||[data.message]).join(' '); errEl.style.display='block'; }
    } catch(e){ errEl.textContent='Network error. Try again.'; errEl.style.display='block'; }
    finally { btn.disabled=false; btn.textContent='Place Order'; }
}

// ── Order Tracking ────────────────────────────────────────────
async function trackOrder() {
    const code=document.getElementById('trackCode').value.trim(); const result=document.getElementById('trackResult');
    if(!code){storeToast('Enter a tracking code.','error');return;}
    result.style.display='block'; result.innerHTML='<div style="color:var(--store-text3);padding:1rem">Searching…</div>';
    const fd=new FormData(); fd.append('action','track_order'); fd.append('tracking_code',code);
    try { const res=await fetch('index.php',{method:'POST',body:fd}); const data=await res.json();
        if(!data.success){result.innerHTML='<div class="track-not-found">Order not found. Check your tracking code.</div>';return;}
        const o=data.order, steps=['pending','processing','shipped','delivered'], idx=steps.indexOf(o.status);
        const sh=steps.map((s,i)=>`<div class="track-step ${i<=idx?'done':''}">${s.charAt(0).toUpperCase()+s.slice(1)}</div>${i<steps.length-1?'<div class="track-step-arrow">→</div>':''}`).join('');
        result.innerHTML=`<div class="track-card"><div class="track-card-id">${escHtml(o.id)} <span class="track-code-badge">${escHtml(o.tracking_code)}</span></div><div class="track-steps">${sh}</div><div class="track-info"><div><strong>Customer:</strong> ${escHtml(o.customer_name)}</div><div><strong>Total:</strong> ₱${fmtNum(o.total)}</div><div><strong>Est. Delivery:</strong> ${o.estimated_days||'3–7'} business days</div>${o.shipped_at?`<div><strong>Shipped:</strong> ${new Date(o.shipped_at).toLocaleDateString()}</div>`:''}</div></div>`;
    } catch(e){ result.innerHTML='<div class="track-not-found">Network error. Try again.</div>'; }
}
function toggleOrderDetail(btn){ const rows=btn.closest('.order-card').querySelector('.order-detail-rows'); rows.classList.toggle('open'); btn.textContent=rows.classList.contains('open')?'Hide ↑':'Details ↓'; }

// ── Customer Auth ─────────────────────────────────────────────
async function customerLogin() {
    const user=document.getElementById('loginUser').value.trim(), pass=document.getElementById('loginPass').value;
    const errEl=document.getElementById('authError'), succEl=document.getElementById('authSuccess'), btn=document.getElementById('loginBtn');
    errEl.style.display='none'; succEl.style.display='none';
    if(!user||!pass){errEl.textContent='Enter username and password.';errEl.style.display='block';return;}
    btn.disabled=true; btn.textContent='Signing in…';
    const fd=new FormData(); fd.append('action','customer_login'); fd.append('username',user); fd.append('password',pass);
    try { const res=await fetch('index.php',{method:'POST',body:fd}); const data=await res.json();
        if(data.success){ succEl.textContent='Welcome back, '+data.name+'! Redirecting…'; succEl.style.display='block'; setTimeout(()=>location.href='index.php?cpage=profile',600); }
        else { errEl.textContent=data.message||'Login failed.'; errEl.style.display='block'; btn.disabled=false; btn.textContent='Sign In'; document.getElementById('loginPass').value=''; }
    } catch(e){ errEl.textContent='Network error.'; errEl.style.display='block'; btn.disabled=false; btn.textContent='Sign In'; }
}
async function customerRegister() {
    const name=document.getElementById('regName').value.trim(), user=document.getElementById('regUser').value.trim(), email=document.getElementById('regEmail').value.trim(), pass=document.getElementById('regPass').value, addr=document.getElementById('regAddress').value.trim(), contact=document.getElementById('regContact').value.trim();
    const errEl=document.getElementById('regError'), btn=document.getElementById('regBtn');
    errEl.style.display='none'; btn.disabled=true; btn.textContent='Creating…';
    const fd=new FormData(); fd.append('action','customer_register'); fd.append('name',name); fd.append('username',user); fd.append('email',email); fd.append('password',pass); fd.append('address',addr); fd.append('contact',contact);
    try { const res=await fetch('index.php',{method:'POST',body:fd}); const data=await res.json();
        if(data.success) location.href='index.php?cpage=profile';
        else { errEl.textContent=(data.errors||[data.message]).join(' '); errEl.style.display='block'; btn.disabled=false; btn.textContent='Create Account'; }
    } catch(e){ errEl.textContent='Network error.'; errEl.style.display='block'; btn.disabled=false; btn.textContent='Create Account'; }
}
async function customerLogout() {
    const fd=new FormData(); fd.append('action','customer_logout');
    await fetch('index.php',{method:'POST',body:fd}); location.href='index.php';
}
async function saveProfile() {
    const fd=new FormData(); fd.append('action','customer_update_profile'); fd.append('name',document.getElementById('profName').value); fd.append('address',document.getElementById('profAddress').value); fd.append('contact',document.getElementById('profContact').value); fd.append('current_password',document.getElementById('profCurrPass').value); fd.append('new_password',document.getElementById('profNewPass').value);
    const res=await fetch('index.php',{method:'POST',body:fd}); const data=await res.json();
    const sEl=document.getElementById('profileSuccess'), eEl=document.getElementById('profileError');
    if(data.success){ sEl.textContent=data.message; sEl.style.display='block'; eEl.style.display='none'; setTimeout(()=>sEl.style.display='none',3000); }
    else { eEl.textContent=data.message; eEl.style.display='block'; }
}

// ── Wishlist ──────────────────────────────────────────────────
async function toggleWishlist(productId, btn) {
    if(!CUST_LOGGED_IN){ storeToast('Sign in to use your wishlist.','error'); setTimeout(()=>location.href='index.php?cpage=login',800); return; }
    const fd=new FormData(); fd.append('action','customer_toggle_wishlist'); fd.append('product_id',productId);
    const res=await fetch('index.php',{method:'POST',body:fd}); const data=await res.json();
    if(data.success){ btn.classList.toggle('wished',data.action==='added'); btn.textContent=data.action==='added'?'♥':'♡'; storeToast(data.action==='added'?'Added to wishlist ♥':'Removed from wishlist','success'); }
    else storeToast(data.message,'error');
}

// ── Reviews ───────────────────────────────────────────────────
function setRating(val){ document.getElementById('revRating').value=val; document.querySelectorAll('#starPicker .star').forEach((s,i)=>{s.textContent=i<val?'★':'☆';}); }
async function submitReview(pid) {
    const rating=parseInt(document.getElementById('revRating').value), author=document.getElementById('revAuthor').value||'Anonymous', comment=document.getElementById('revComment').value, msgEl=document.getElementById('reviewMsg');
    if(!rating){showRevMsg('Please select a star rating.','error');return;}
    const fd=new FormData(); fd.append('action','submit_review'); fd.append('product_id',pid); fd.append('author',author); fd.append('rating',rating); fd.append('comment',comment);
    const res=await fetch('index.php',{method:'POST',body:fd}); const data=await res.json();
    showRevMsg(data.message,data.success?'success':'error'); if(data.success) setTimeout(()=>location.reload(),1200);
}
function showRevMsg(msg,type){ const el=document.getElementById('reviewMsg'); if(!el)return; el.textContent=msg; el.style.display='block'; el.style.color=type==='success'?'var(--store-green)':'var(--store-red)'; }

// ── Chatbot with localStorage persistence ────────────────────
const CHAT_KEY       = 'pairpal_chat_history';
const CHAT_STATE_KEY = 'pairpal_chat_open';
let chatOpen = false;

// ── Persistence helpers ───────────────────────────────────────
function saveChatHistory() {
    try {
        const msgs = document.getElementById('chatMessages');
        if (!msgs) return;
        // Store serialised message objects (text + role + actions)
        const stored = JSON.parse(sessionStorage.getItem(CHAT_KEY) || '[]');
        // We only append new messages — rebuild from DOM on save
        const nodes = msgs.querySelectorAll('.chat-msg:not(.chat-msg-typing)');
        const history = [];
        nodes.forEach(node => {
            const isUser = node.classList.contains('user');
            const bubble = node.querySelector('.chat-bubble');
            if (!bubble) return;
            const actions = [];
            node.querySelectorAll('.chat-action-btn').forEach(btn => {
                actions.push({ label: btn.textContent, href: btn.href || null, isBtn: !btn.href });
            });
            history.push({ role: isUser ? 'user' : 'bot', html: bubble.innerHTML, actions });
        });
        sessionStorage.setItem(CHAT_KEY, JSON.stringify(history));
    } catch(e) {}
}

function loadChatHistory() {
    try {
        const history = JSON.parse(sessionStorage.getItem(CHAT_KEY) || '[]');
        if (!history.length) return;
        const msgs = document.getElementById('chatMessages');
        if (!msgs) return;
        // Clear default greeting then restore history
        msgs.innerHTML = '';
        history.forEach(entry => {
            const div = document.createElement('div');
            div.className = 'chat-msg ' + entry.role;
            let actionsHtml = '';
            if (entry.actions && entry.actions.length) {
                actionsHtml = '<div class="chat-actions">' + entry.actions.map(a => {
                    if (a.href && a.href !== 'null')
                        return `<a class="chat-action-btn" href="${escHtml(a.href)}">${escHtml(a.label)}</a>`;
                    return `<button class="chat-action-btn" onclick="sendQuickChat('${escHtml(a.label)}')">${escHtml(a.label)}</button>`;
                }).join('') + '</div>';
            }
            if (entry.role === 'user') {
                div.innerHTML = `<div class="chat-bubble">${entry.html}</div>`;
            } else {
                div.innerHTML = `<div class="chat-msg-avatar">◈</div><div><div class="chat-bubble">${entry.html}</div>${actionsHtml}</div>`;
            }
            msgs.appendChild(div);
        });
        msgs.scrollTop = msgs.scrollHeight;
    } catch(e) {}
}

function clearChatHistory() {
    sessionStorage.removeItem(CHAT_KEY);
    sessionStorage.removeItem(CHAT_STATE_KEY);
    const msgs = document.getElementById('chatMessages');
    if (msgs) msgs.innerHTML = `<div class="chat-msg bot"><div class="chat-msg-avatar">◈</div><div><div class="chat-bubble">Chat cleared! 👋 How can I help you?</div><div class="chat-actions"><button class="chat-action-btn" onclick="sendQuickChat('Show me best sellers')">Best Sellers</button><button class="chat-action-btn" onclick="sendQuickChat('Bundle deals')">Bundle Deals</button></div></div></div>`;
}

// ── Core chat functions ───────────────────────────────────────
function toggleChat(){
    chatOpen = !chatOpen;
    document.getElementById('chatPanel').classList.toggle('open', chatOpen);
    document.getElementById('chatBubble').classList.toggle('chat-active', chatOpen);
    document.getElementById('chatUnread').style.display = 'none';
    if (chatOpen) {
        loadChatHistory();
        document.getElementById('chatInput').focus();
    }
    try { sessionStorage.setItem(CHAT_STATE_KEY, chatOpen ? '1' : '0'); } catch(e) {}
}

function sendQuickChat(msg) {
    if (!chatOpen) toggleChat();
    document.getElementById('chatInput').value = msg;
    sendChat();
}

async function sendChat() {
    const input = document.getElementById('chatInput');
    const msg   = input.value.trim();
    if (!msg) return;
    input.value = '';
    appendChatMsg(msg, 'user');
    const typing = showTyping();
    const fd = new FormData();
    fd.append('action', 'chatbot_message');
    fd.append('message', msg);
    try {
        const res  = await fetch('index.php', { method: 'POST', body: fd });
        const data = await res.json();
        removeTyping(typing);
        if (data.success) appendBotMsg(data.response);
        else appendBotMsg({ text: 'Sorry, I had trouble with that. Try again!', actions: [] });
    } catch(e) {
        removeTyping(typing);
        appendBotMsg({ text: 'Connection issue. Please try again.', actions: [] });
    }
}

function appendChatMsg(text, role) {
    const msgs = document.getElementById('chatMessages');
    const div  = document.createElement('div');
    div.className = `chat-msg ${role}`;
    if (role === 'user') div.innerHTML = `<div class="chat-bubble">${escHtml(text)}</div>`;
    else                 div.innerHTML = `<div class="chat-msg-avatar">◈</div><div><div class="chat-bubble">${escHtml(text)}</div></div>`;
    msgs.appendChild(div);
    msgs.scrollTop = msgs.scrollHeight;
    saveChatHistory();
}

function appendBotMsg(response) {
    const msgs     = document.getElementById('chatMessages');
    const div      = document.createElement('div');
    div.className  = 'chat-msg bot';
    const rendered = renderMarkdown(response.text || '');
    let actionsHtml = '';
    if (response.actions && response.actions.length) {
        actionsHtml = '<div class="chat-actions">' + response.actions.map(a => {
            if (a.href) return `<a class="chat-action-btn" href="${escHtml(a.href)}">${escHtml(a.label)}</a>`;
            if (a.action === 'scroll_bundles') return `<button class="chat-action-btn" onclick="chatScrollTo('bundle-deals')">${escHtml(a.label)}</button>`;
            if (a.action === 'show_trending')  return `<button class="chat-action-btn" onclick="chatScrollTo('catalog')">${escHtml(a.label)}</button>`;
            // Default: send label as message (intent detection handles it)
            return `<button class="chat-action-btn" onclick="sendQuickChat('${escHtml(a.label)}')">${escHtml(a.label)}</button>`;
        }).join('') + '</div>';
    }
    div.innerHTML = `<div class="chat-msg-avatar">◈</div><div><div class="chat-bubble">${rendered}</div>${actionsHtml}</div>`;
    msgs.appendChild(div);
    msgs.scrollTop = msgs.scrollHeight;
    saveChatHistory();
}

function showTyping() {
    const msgs = document.getElementById('chatMessages');
    const div  = document.createElement('div');
    div.className = 'chat-msg bot chat-msg-typing';
    div.innerHTML = '<div class="chat-msg-avatar">◈</div><div class="chat-typing visible"><span></span><span></span><span></span></div>';
    msgs.appendChild(div);
    msgs.scrollTop = msgs.scrollHeight;
    return div;
}
function removeTyping(el) { if (el && el.parentNode) el.parentNode.removeChild(el); }

function renderMarkdown(text) {
    return escHtml(text)
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>');
}

// Restore state on page load
document.addEventListener('DOMContentLoaded', function() {
    try {
        const wasOpen = sessionStorage.getItem(CHAT_STATE_KEY) === '1';
        if (wasOpen) {
            // Restore open state silently
            chatOpen = true;
            document.getElementById('chatPanel').classList.add('open');
            document.getElementById('chatBubble').classList.add('chat-active');
            document.getElementById('chatUnread').style.display = 'none';
            loadChatHistory();
        }
    } catch(e) {}
});

// Show unread badge on load (only if not previously seen)
setTimeout(() => {
    const u = document.getElementById('chatUnread');
    if (u && !chatOpen) {
        try {
            const hasPrev = sessionStorage.getItem(CHAT_KEY);
            if (!hasPrev) u.style.display = 'block'; // only show on first visit
        } catch(e) { u.style.display = 'block'; }
    }
}, 1500);

// ── Order cancellation ───────────────────────────────────────────
async function cancelOrder(orderId) {
    if (!confirm('Cancel this order? This cannot be undone.')) return;
    const fd = new FormData();
    fd.append('action', 'customer_cancel_order');
    fd.append('order_id', orderId);
    try {
        const res  = await fetch('index.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            storeToast('Order cancelled.', 'success');
            setTimeout(() => location.reload(), 700);
        } else {
            storeToast(data.message || 'Could not cancel order.', 'error');
        }
    } catch(e) { storeToast('Network error.', 'error'); }
}

// ── Catalog card qty picker ───────────────────────────────────────
function cardQtyChange(id, delta, maxStock = 999) {
    const el  = document.getElementById('cqty_' + id);
    if (!el) return;
    let val = parseInt(el.textContent || '1') + delta;
    if (val < 1) val = 1;
    if (val > maxStock) val = maxStock;
    el.textContent = val;
}
async function addCardToCart(id) {
    const el  = document.getElementById('cqty_' + id);
    const qty = el ? parseInt(el.textContent || '1') : 1;
    await addToCart(id, qty);
    if (el) el.textContent = '1'; // reset after adding
}

// ── Recently viewed ───────────────────────────────────────────────
const RV_KEY      = 'pairpal_recent_viewed';
const RV_MAX      = 6;
const RV_PRODUCTS = <?= json_encode(array_column(
    array_map(fn($p) => [
        'id'       => $p['id'],
        'name'     => $p['name'],
        'category' => $p['category'],
        'price'    => $p['price'],
        'stock'    => $p['stock'] > 0 ? 1 : 0,  // only in/out-of-stock, not exact count
        'image'    => $p['image'] ?? '',
    ], $productRepo->getAll()),
    null, 'id'
)) ?>;

function trackView(productId) {
    try {
        let viewed = JSON.parse(sessionStorage.getItem(RV_KEY) || '[]');
        viewed = viewed.filter(id => id !== productId);
        viewed.unshift(productId);
        if (viewed.length > RV_MAX) viewed = viewed.slice(0, RV_MAX);
        sessionStorage.setItem(RV_KEY, JSON.stringify(viewed));
    } catch(e) {}
}

function renderRecentlyViewed() {
    try {
        const viewed = JSON.parse(sessionStorage.getItem(RV_KEY) || '[]');
        const section = document.getElementById('recentlyViewedSection');
        const row     = document.getElementById('recentlyViewedRow');
        if (!section || !row || viewed.length < 2) return;

        const html = viewed.map(id => {
            const p = RV_PRODUCTS[id];
            if (!p || p.stock <= 0) return '';
            const img = p.image
                ? `<img src="${escHtml(p.image)}" alt="" loading="lazy">`
                : `<div class="card-placeholder">${escHtml(p.name.charAt(0).toUpperCase())}</div>`;
            const addBtn = p.stock > 0 ? `<button class="btn-add-card" onclick="event.stopPropagation();addToCart('${escHtml(id)}')">+ Cart</button>` : '';
            return `<div class="store-product-card" onclick="window.location='index.php?product=${encodeURIComponent(id)}'">
                ${img}
                <div class="store-product-cat">${escHtml(p.category)}</div>
                <div class="store-product-name">${escHtml(p.name)}</div>
                <div class="store-product-price">₱${parseFloat(p.price).toLocaleString('en-PH',{minimumFractionDigits:2})}</div>
                ${addBtn}
            </div>`;
        }).join('');

        if (!html.trim()) return;
        row.innerHTML = html;
        section.style.display = '';
    } catch(e) {}
}

// Track current product view (if on a product detail page)
<?php if ($viewProduct): ?>
trackView('<?= htmlspecialchars($viewProduct['id']) ?>');
<?php endif; ?>

// Render on page load
document.addEventListener('DOMContentLoaded', renderRecentlyViewed);

// ── Chat scroll helper ───────────────────────────────────────────
function chatScrollTo(anchorId) {
    // Close chat panel, then scroll to section
    if (chatOpen) toggleChat();
    const el = document.getElementById(anchorId);
    if (el) {
        setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'start' }), 350);
    }
}

// ── Scroll brand collapse ─────────────────────────────────────
(function(){
    const THRESHOLD=60, brandText=document.getElementById('brandText'), brandIcon=document.getElementById('brandIcon');
    if(!brandText||!brandIcon)return;
    let ticking=false;
    function updateBrand(){ const scrolled=window.scrollY>THRESHOLD; brandText.classList.toggle('brand-text-hidden',scrolled); brandIcon.classList.toggle('brand-icon-solo',scrolled); ticking=false; }
    window.addEventListener('scroll',function(){ if(!ticking){ requestAnimationFrame(updateBrand); ticking=true; }},{passive:true});
}());

// ── Utilities ────────────────────────────────────────────────
let toastTimer; function storeToast(msg,type='info'){ const t=document.getElementById('storeToast'); t.textContent=msg; t.className=`store-toast show ${type}`; clearTimeout(toastTimer); toastTimer=setTimeout(()=>t.classList.remove('show'),3400); }
function fmtNum(n){ return parseFloat(n).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function escHtml(str){ const d=document.createElement('div'); d.textContent=String(str||''); return d.innerHTML; }
</script>
</body>
</html>
