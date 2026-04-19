<?php
date_default_timezone_set('Asia/Manila'); // UTC+8 Philippine Standard Time

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
ini_set('session.name', 'PAIRPAL_SID');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}

session_start();

require_once __DIR__ . '/services/FileHandler.php';
require_once __DIR__ . '/services/UserRepository.php';
require_once __DIR__ . '/services/CustomerRepository.php';
require_once __DIR__ . '/services/ProductRepository.php';
require_once __DIR__ . '/services/SalesRepository.php';
require_once __DIR__ . '/services/InventoryLogRepository.php';
require_once __DIR__ . '/services/PairPalDataRepository.php';
require_once __DIR__ . '/services/BundleRepository.php';
require_once __DIR__ . '/services/PairPalEngine.php';
require_once __DIR__ . '/services/OrderRepository.php';
require_once __DIR__ . '/services/ReviewRepository.php';
require_once __DIR__ . '/services/PairPalChatService.php';
require_once __DIR__ . '/services/NotificationManager.php';
require_once __DIR__ . '/services/ActivityLogger.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Admin.php';
require_once __DIR__ . '/models/Cashier.php';
require_once __DIR__ . '/models/Customer.php';
require_once __DIR__ . '/models/Product.php';
require_once __DIR__ . '/models/Cart.php';
require_once __DIR__ . '/models/Transaction.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/CustomerAuthController.php';
require_once __DIR__ . '/controllers/ProductController.php';
require_once __DIR__ . '/controllers/CartController.php';
require_once __DIR__ . '/controllers/ReportController.php';
require_once __DIR__ . '/controllers/OrderController.php';

class_alias('PairPalEngine', 'RecommendationEngine');

$auth     = new AuthController();
$custAuth = new CustomerAuthController();
$page     = $_GET['page']  ?? '';
$cpage    = $_GET['cpage'] ?? '';

function csrf_generate(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): bool {
    $submitted = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';
    return !empty($expected) && hash_equals($expected, $submitted);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        echo json_encode($auth->login($_POST['username'] ?? '', $_POST['password'] ?? ''));
        exit;
    }
    if ($action === 'logout') {
        $auth->logout();
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'customer_register') { echo json_encode($custAuth->register($_POST)); exit; }
    if ($action === 'customer_login') {
        $result = $custAuth->login($_POST['username'] ?? '', $_POST['password'] ?? '');
        echo json_encode($result);
        exit;
    }
    if ($action === 'customer_logout') { $custAuth->logout(); echo json_encode(['success' => true]); exit; }

    if ($action === 'customer_update_profile') {
        if (!$custAuth->isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'Not logged in.']); exit; }
        echo json_encode($custAuth->updateProfile($custAuth->getSession()['id'], $_POST));
        exit;
    }
    if ($action === 'customer_toggle_wishlist') {
        if (!$custAuth->isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'Login to use wishlist.']); exit; }
        echo json_encode($custAuth->toggleWishlist($custAuth->getSession()['id'], $_POST['product_id'] ?? ''));
        exit;
    }

    if ($action === 'customer_cart_add') {
        $ctrl = new CartController('customer_cart');
        echo json_encode($ctrl->addToCart($_POST['product_id'] ?? '', (int)($_POST['qty'] ?? 1)));
        exit;
    }
    if ($action === 'customer_cart_update') {
        $ctrl = new CartController('customer_cart');
        echo json_encode($ctrl->updateItem($_POST['product_id'] ?? '', (int)($_POST['qty'] ?? 1)));
        exit;
    }
    if ($action === 'customer_cart_remove') {
        $ctrl = new CartController('customer_cart');
        echo json_encode($ctrl->removeItem($_POST['product_id'] ?? ''));
        exit;
    }
    if ($action === 'customer_cart_state') {
        $ctrl = new CartController('customer_cart');
        echo json_encode(['success' => true, 'state' => $ctrl->getCartState()]);
        exit;
    }
    if ($action === 'customer_place_order') {
        if (!$custAuth->isLoggedIn()) {
            echo json_encode(['success'=>false,'errors'=>['Please sign in to place an order.'],'redirect'=>'index.php?cpage=login']);
            exit;
        }
        $ctrl = new CartController('customer_cart');
        $cart = $ctrl->getCart();
        if ($cart->isEmpty()) { echo json_encode(['success' => false, 'errors' => ['Cart is empty.']]); exit; }
        $sess      = $custAuth->getSession();
        $orderCtrl = new OrderController();
        $result    = $orderCtrl->placeOrder(
            ['name' => $_POST['name'] ?? '', 'address' => $_POST['address'] ?? '', 'contact' => $_POST['contact'] ?? '', 'email' => $_POST['email'] ?? '', 'notes' => $_POST['notes'] ?? ''],
            $cart->getItems(), $cart->getSubtotal(), $cart->getDiscountAmount(), $cart->getTotal(), $cart->getBundleName(),
            $sess['id'] ?? ''
        );
        if ($result['success']) $ctrl->clearCart();
        echo json_encode($result);
        exit;
    }
    if ($action === 'customer_cancel_order') {
        $custSess   = $custAuth->getSession();
        $orderId    = trim($_POST['order_id'] ?? '');
        if (!$orderId) { echo json_encode(['success'=>false,'message'=>'Order ID required.']); exit; }
        $oRepo2     = new OrderRepository();
        $order2     = $oRepo2->findById($orderId);
        if (!$order2) { echo json_encode(['success'=>false,'message'=>'Order not found.']); exit; }
        $isOwner    = !empty($custSess['id']) && ($order2['customer_id']??'') === $custSess['id'];
        if (!$isOwner) { echo json_encode(['success'=>false,'message'=>'You can only cancel your own orders.']); exit; }
        if (($order2['status']??'') !== 'pending') { echo json_encode(['success'=>false,'message'=>'Only pending orders can be cancelled.']); exit; }
        $pRepo2     = new ProductRepository();
        $lRepo2     = new InventoryLogRepository();
        foreach ($order2['items'] as $item) {
            $p2 = $pRepo2->findById($item['product_id'] ?? '');
            if ($p2) {
                $before2 = (int)$p2['stock'];
                $qty2    = (int)($item['quantity'] ?? 0);
                $pRepo2->adjustStock($item['product_id'], $before2 + $qty2);
                $lRepo2->log($item['product_id'], $item['name']??'', 'manual_add', $qty2, $before2, $before2+$qty2, "Customer cancelled order {$orderId}", $custSess['id']??'customer');
            }
        }
        $ctrlResult = (new OrderController())->updateStatus($orderId, 'cancelled');
        echo json_encode($ctrlResult['success']
            ? ['success'=>true, 'message'=>'Order cancelled. Stock has been restored.']
            : ['success'=>false,'message'=>'Failed to cancel order.']);
        exit;
    }
    if ($action === 'track_order') {
        $order = (new OrderController())->trackOrder($_POST['tracking_code'] ?? '');
        echo json_encode($order ? ['success' => true, 'order' => $order] : ['success' => false, 'message' => 'Order not found.']);
        exit;
    }
    if ($action === 'submit_review') {
        $revRepo = new ReviewRepository();
        $rating  = (int)($_POST['rating'] ?? 0);
        $pid     = trim($_POST['product_id'] ?? '');
        if ($rating < 1 || $rating > 5) { echo json_encode(['success' => false, 'message' => 'Rating must be 1–5.']); exit; }
        if (!$pid)                       { echo json_encode(['success' => false, 'message' => 'Product required.']); exit; }
        $ok = $revRepo->save(['id' => $revRepo->generateId(), 'product_id' => $pid, 'author' => htmlspecialchars(trim($_POST['author'] ?? 'Anonymous')), 'rating' => $rating, 'comment' => htmlspecialchars(trim($_POST['comment'] ?? '')), 'date' => date('c')]);
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Review submitted!' : 'Failed.']);
        exit;
    }

    if ($action === 'chatbot_message') {
        $chat      = new PairPalChatService();
        $sess      = $custAuth->getSession();
        $cartCtrl  = new CartController('customer_cart');
        $cart      = $cartCtrl->getCart();
        $recentIds = [];
        if (!empty($sess['id'])) {
            foreach ((new OrderRepository())->getByCustomer($sess['id'], 5) as $o) {
                foreach ($o['items'] as $item) $recentIds[] = $item['product_id'];
            }
        }
        $response = $chat->respond($_POST['message'] ?? '', [
            'customer_id'        => $sess['id']        ?? '',
            'customer_name'      => $sess['name']       ?? '',
            'cart_ids'           => $cart->getProductIds(),
            'recent_product_ids' => array_unique($recentIds),
        ]);
        echo json_encode(['success' => true, 'response' => $response]);
        exit;
    }

    $auth->requireLogin();

    if (!csrf_verify()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing security token. Please refresh the page.']);
        exit;
    }

    if ($action === 'cart_add')      { $ctrl = new CartController(); echo json_encode($ctrl->addToCart($_POST['product_id'] ?? '', (int)($_POST['qty'] ?? 1))); exit; }
    if ($action === 'cart_update')   { $ctrl = new CartController(); echo json_encode($ctrl->updateItem($_POST['product_id'] ?? '', (int)($_POST['qty'] ?? 1))); exit; }
    if ($action === 'cart_remove')   { $ctrl = new CartController(); echo json_encode($ctrl->removeItem($_POST['product_id'] ?? '')); exit; }
    if ($action === 'cart_discount') { $ctrl = new CartController(); echo json_encode($ctrl->applyDiscount($_POST['type'] ?? 'none', (float)($_POST['value'] ?? 0))); exit; }
    if ($action === 'cart_state')    { $ctrl = new CartController(); echo json_encode(['success' => true, 'state' => $ctrl->getCartState()]); exit; }
    if ($action === 'checkout')      { $ctrl = new CartController(); echo json_encode($ctrl->checkout((float)($_POST['amount_paid'] ?? 0))); exit; }

    if ($action === 'product_create')       { $auth->requireAdmin(); echo json_encode((new ProductController())->create($_POST, $_FILES)); exit; }
    if ($action === 'product_update')       { $auth->requireAdmin(); echo json_encode((new ProductController())->update($_POST['id'] ?? '', $_POST, $_FILES)); exit; }
    if ($action === 'product_delete')       { $auth->requireAdmin(); echo json_encode((new ProductController())->delete($_POST['id'] ?? '')); exit; }
    if ($action === 'product_adjust_stock') { $auth->requireAdmin(); echo json_encode((new ProductController())->adjustStock($_POST['id'] ?? '', (int)($_POST['new_stock'] ?? 0), $_POST['note'] ?? '')); exit; }
    if ($action === 'product_bulk_import')  { $auth->requireAdmin(); echo json_encode((new ProductController())->bulkImport($_FILES['import_file'] ?? [])); exit; }

    if ($action === 'order_update_status') { $auth->requireAdmin(); echo json_encode((new OrderController())->updateStatus($_POST['id'] ?? '', $_POST['status'] ?? '')); exit; }

    if ($action === 'bundle_set_status') { $auth->requireAdmin(); echo json_encode(['success' => (new BundleRepository())->setStatus($_POST['id'] ?? '', $_POST['status'] ?? 'active')]); exit; }
    if ($action === 'bundle_delete')     { $auth->requireAdmin(); $ok = (new BundleRepository())->delete($_POST['id'] ?? ''); echo json_encode(['success' => $ok, 'message' => $ok ? 'Bundle deleted.' : 'Failed.']); exit; }
    if ($action === 'bundle_rename') {
        $auth->requireAdmin();
        $repo    = new BundleRepository();
        $id      = trim($_POST['id']   ?? '');
        $newName = trim($_POST['name'] ?? '');
        if (!$id || !$newName)        { echo json_encode(['success'=>false,'message'=>'ID and name required.']); exit; }
        if (mb_strlen($newName) > 80) { echo json_encode(['success'=>false,'message'=>'Name must be 80 chars or fewer.']); exit; }
        $bundle = $repo->findById($id);
        if (!$bundle)                 { echo json_encode(['success'=>false,'message'=>'Bundle not found.']); exit; }
        $bundle['name'] = $newName;
        $ok = $repo->save($bundle);
        echo json_encode(['success'=>$ok, 'message'=>$ok?'Bundle renamed.':'Failed.']);
        exit;
    }

    if ($action === 'user_create') {
        $auth->requireAdmin();
        $repo     = new UserRepository();
        $username = trim($_POST['username'] ?? '');
        $name     = trim($_POST['name']     ?? '');
        $email    = trim($_POST['email']    ?? '');
        $role     = in_array($_POST['role']??'', ['admin','user']) ? $_POST['role'] : 'user';
        $password = $_POST['password'] ?? '';
        if (!$username || !$name)              { echo json_encode(['success'=>false,'message'=>'Name and username are required.']); exit; }
        if (mb_strlen($username) > 40)         { echo json_encode(['success'=>false,'message'=>'Username must be 40 characters or fewer.']); exit; }
        if (mb_strlen($name)     > 80)         { echo json_encode(['success'=>false,'message'=>'Name must be 80 characters or fewer.']); exit; }
        if (strlen($password) < 6)             { echo json_encode(['success'=>false,'message'=>'Password must be at least 6 characters.']); exit; }
        if ($repo->findByUsername($username))  { echo json_encode(['success'=>false,'message'=>'That username is already taken.']); exit; }
        $max = 0;
        foreach ($repo->getAll() as $u) { if (preg_match('/usr_(\d+)/', $u['id']??'', $m)) $max = max($max,(int)$m[1]); }
        $id  = 'usr_' . str_pad($max+1, 3, '0', STR_PAD_LEFT);
        $ok  = $repo->save(['id'=>$id,'username'=>$username,'password'=>password_hash($password,PASSWORD_BCRYPT),'name'=>$name,'email'=>$email,'role'=>$role,'created_at'=>date('c'),'last_login'=>null]);
        if ($ok) (new ActivityLogger())->log(ActivityLogger::TYPE_LOGIN, "Staff account created: {$username}", "Role: {$role}");
        echo json_encode(['success'=>$ok,'message'=>$ok?'Account created successfully.':'Failed to create account.']);
        exit;
    }
    if ($action === 'user_update') {
        $auth->requireAdmin();
        $repo = new UserRepository();
        $id   = trim($_POST['id'] ?? '');
        $user = $repo->findById($id);
        if (!$user) { echo json_encode(['success'=>false,'message'=>'Account not found.']); exit; }
        $username = trim($_POST['username'] ?? $user['username']);
        $name     = trim($_POST['name']     ?? $user['name']);
        $email    = trim($_POST['email']    ?? $user['email'] ?? '');
        $role     = in_array($_POST['role']??'', ['admin','user']) ? $_POST['role'] : $user['role'];
        $password = $_POST['password'] ?? '';
        if (mb_strlen($username) > 40) { echo json_encode(['success'=>false,'message'=>'Username must be 40 characters or fewer.']); exit; }
        if (mb_strlen($name)     > 80) { echo json_encode(['success'=>false,'message'=>'Name must be 80 characters or fewer.']); exit; }
        $existing = $repo->findByUsername($username);
        if ($existing && $existing['id'] !== $id) { echo json_encode(['success'=>false,'message'=>'That username is already taken.']); exit; }
        $user['username'] = $username;
        $user['name']     = $name;
        $user['email']    = $email;
        $user['role']     = $role;
        if ($password !== '') {
            if (strlen($password) < 6) { echo json_encode(['success'=>false,'message'=>'Password must be at least 6 characters.']); exit; }
            $user['password'] = password_hash($password, PASSWORD_BCRYPT);
        }
        $ok = $repo->save($user);
        echo json_encode(['success'=>$ok,'message'=>$ok?'Account updated.':'Failed to update.']);
        exit;
    }
    if ($action === 'user_delete') {
        $auth->requireAdmin();
        $repo = new UserRepository();
        $id   = trim($_POST['id'] ?? '');
        if ($id === ($_SESSION['user_id']??'')) { echo json_encode(['success'=>false,'message'=>'You cannot delete your own account.']); exit; }
        if (!$repo->findById($id))              { echo json_encode(['success'=>false,'message'=>'Account not found.']); exit; }
        $ok = $repo->delete($id);
        echo json_encode(['success'=>$ok,'message'=>$ok?'Account deleted.':'Failed.']);
        exit;
    }

    if ($action === 'category_rename') {
        $auth->requireAdmin();
        $repo    = new ProductRepository();
        $oldName = trim($_POST['old_name'] ?? '');
        $newName = trim($_POST['new_name'] ?? '');
        if (!$oldName || !$newName)              { echo json_encode(['success'=>false,'message'=>'Both old and new names are required.']); exit; }
        if (mb_strlen($newName) > 60)            { echo json_encode(['success'=>false,'message'=>'Category name must be 60 characters or fewer.']); exit; }
        $count = $repo->renameCategory($oldName, $newName);
        (new ActivityLogger())->log(ActivityLogger::TYPE_PRODUCT_UPDATE, "Category renamed: '{$oldName}' → '{$newName}'", "{$count} product(s) updated");
        echo json_encode(['success'=>true,'count'=>$count,'message'=>"Category renamed. {$count} product(s) updated."]);
        exit;
    }

    if ($action === 'pairpal_rebuild') {
        $auth->requireAdmin();
        $pairRepo = new PairPalDataRepository();
        $pairRepo->rebuildFromSales((new SalesRepository())->getAll());
        $count = (new PairPalEngine())->regenerateBundles();
        (new ActivityLogger())->log(ActivityLogger::TYPE_BUNDLE_GENERATE, "PairPal rebuilt: {$count} bundle(s) generated", "Triggered by: " . ($_SESSION['name'] ?? 'admin'));
        echo json_encode(['success' => true, 'message' => "Rebuilt. {$count} bundles generated."]);
        exit;
    }

    if ($action === 'notif_mark_read')      { echo json_encode(['success' => (new NotificationManager())->markRead($_POST['id'] ?? '')]); exit; }
    if ($action === 'notif_mark_all_read')  { echo json_encode(['success' => (new NotificationManager())->markAllRead()]); exit; }
    if ($action === 'notif_delete')         { $auth->requireAdmin(); echo json_encode(['success' => (new NotificationManager())->delete($_POST['id'] ?? '')]); exit; }
    if ($action === 'notif_get_unread_count') { echo json_encode(['success' => true, 'count' => (new NotificationManager())->getUnreadCount()]); exit; }

    if ($action === 'dismiss_login_notification') { $_SESSION['login_notification_dismissed'] = true; echo json_encode(['success' => true]); exit; }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

if ($page === 'export_csv') { $auth->requireAdmin(); (new ReportController())->exportCSV($_GET['from'] ?? '', $_GET['to'] ?? ''); exit; }

if ($page === 'export_backup') {
    $auth->requireAdmin();
    $dataDir    = __DIR__ . '/data';
    $files      = glob($dataDir . '/*.json');
    $backupName = 'pairpal_backup_' . date('Y-m-d_His');

    if (class_exists('ZipArchive')) {
        $tmpZip = sys_get_temp_dir() . '/' . $backupName . '.zip';
        $zip    = new ZipArchive();
        if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($files as $file) {
                $zip->addFile($file, 'data/' . basename($file));
            }
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $backupName . '.zip"');
            header('Content-Length: ' . filesize($tmpZip));
            header('Cache-Control: no-store');
            readfile($tmpZip);
            unlink($tmpZip);
            exit;
        }
    }

    function _pairpal_zip_build(array $files): string {
        $localHeaders = '';
        $centralDir   = '';
        $offset       = 0;
        $numEntries   = 0;

        foreach ($files as [$name, $content]) {
            $crc    = crc32($content);
            $usize  = strlen($content);

            $deflated = function_exists('gzdeflate') ? gzdeflate($content, 6) : false;
            if ($deflated !== false) {
                $data   = $deflated;
                $method = 8;
            } else {
                $data   = $content;
                $method = 0;
            }
            $csize = strlen($data);

            $dosTime = ((date('H') << 11) | (date('i') << 5) | (date('s') >> 1));
            $dosDate = (((date('Y') - 1980) << 9) | (date('n') << 5) | date('j'));
            $dt      = $dosDate << 16 | $dosTime;

            $fnameLen = strlen($name);

            $local = pack('VvvvVVVVvv',
                0x04034b50,
                20,
                0,
                $method,
                $dt,
                $crc,
                $csize,
                $usize,
                $fnameLen,
                0
            ) . $name . $data;

            $central = pack('VvvvvVVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                $method,
                $dt,
                $crc,
                $csize,
                $usize,
                $fnameLen,
                0,
                0,
                0,
                0,
                0,
                $offset
            ) . $name;

            $localHeaders .= $local;
            $centralDir   .= $central;
            $offset       += strlen($local);
            $numEntries++;
        }

        $cdSize  = strlen($centralDir);
        $eocd    = pack('VvvvvVVv',
            0x06054b50,
            0,
            0,
            $numEntries,
            $numEntries,
            $cdSize,
            $offset,
            0
        );

        return $localHeaders . $centralDir . $eocd;
    }

    $entries = [];
    foreach ($files as $file) {
        $entries[] = ['data/' . basename($file), file_get_contents($file)];
    }
    $zipBytes = _pairpal_zip_build($entries);

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $backupName . '.zip"');
    header('Content-Length: ' . strlen($zipBytes));
    header('Cache-Control: no-store');
    echo $zipBytes;
    exit;
}

if ($page === '' || $page === 'store') {
    include __DIR__ . '/views/customer/store.php';
    exit;
}

if ($page === 'login' && $auth->isLoggedIn()) { header('Location: index.php?page=dashboard'); exit; }
if ($page !== 'login') { $auth->requireLogin(); }

csrf_generate();

$currentUser = $auth->getCurrentUser();
include __DIR__ . '/views/layout.php';
