<?php
// controllers/OrderController.php
require_once __DIR__ . '/../services/OrderRepository.php';
require_once __DIR__ . '/../services/ProductRepository.php';
require_once __DIR__ . '/../services/InventoryLogRepository.php';
require_once __DIR__ . '/../services/PairPalEngine.php';
require_once __DIR__ . '/../services/ActivityLogger.php';
require_once __DIR__ . '/../services/NotificationManager.php';

class OrderController {
    private OrderRepository        $orderRepo;
    private ProductRepository      $productRepo;
    private InventoryLogRepository $logRepo;

    private ActivityLogger      $actLogger;
    private NotificationManager $notifMgr;

    public function __construct() {
        $this->orderRepo   = new OrderRepository();
        $this->productRepo = new ProductRepository();
        $this->logRepo     = new InventoryLogRepository();
        $this->actLogger   = new ActivityLogger();
        $this->notifMgr    = new NotificationManager();
    }

    public function getAll(): array            { return $this->orderRepo->getAll(); }
    public function findById(string $id): ?array { return $this->orderRepo->findById($id); }
    public function getStatusCounts(): array   { return $this->orderRepo->getStatusCounts(); }
    public function getByStatus(string $s): array { return $this->orderRepo->getByStatus($s); }

    public function trackOrder(string $code): ?array {
        $code = strtoupper(trim($code));
        if (empty($code)) return null;
        return $this->orderRepo->findByTrackingCode($code);
    }

    public function placeOrder(
        array  $customerInfo,
        array  $cartItems,
        float  $subtotal,
        float  $discountAmount,
        float  $total,
        string $bundleName = '',
        string $customerId = ''
    ): array {
        $errors = [];

        // Validate customer info
        $name    = trim($customerInfo['name']    ?? '');
        $address = trim($customerInfo['address'] ?? '');
        $contact = trim($customerInfo['contact'] ?? '');
        $email   = trim($customerInfo['email']   ?? '');
        $notes   = trim($customerInfo['notes']   ?? '');

        if (empty($name))                     $errors[] = 'Full name is required.';
        elseif (mb_strlen($name)    > 100)    $errors[] = 'Full name must be 100 characters or fewer.';
        if (!empty($address) && mb_strlen($address) > 300) $errors[] = 'Address must be 300 characters or fewer.';
        if (!empty($notes)   && mb_strlen($notes)   > 500) $errors[] = 'Notes must be 500 characters or fewer.';
        if (empty($address)) $errors[] = 'Delivery address is required.';
        if (empty($contact)) {
            $errors[] = 'Contact number is required.';
        } elseif (!preg_match('/^[0-9+\-\s]{7,15}$/', $contact)) {
            $errors[] = 'Invalid contact number format.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address format.';
        }
        if (empty($cartItems)) {
            $errors[] = 'Cart is empty.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Validate stock availability for each item
        $products    = $this->productRepo->getAll();
        $productMap  = array_column($products, null, 'id');
        $stockErrors = [];

        foreach ($cartItems as $item) {
            $pid  = $item['product_id'] ?? '';
            $qty  = (int)($item['quantity'] ?? 0);
            $p    = $productMap[$pid] ?? null;
            if (!$p) {
                $stockErrors[] = "Product \"{$item['name']}\" no longer exists.";
            } elseif ($qty > $p['stock']) {
                $stockErrors[] = "Insufficient stock for \"{$item['name']}\". Available: {$p['stock']}, requested: {$qty}.";
            }
        }

        if (!empty($stockErrors)) {
            return ['success' => false, 'errors' => $stockErrors];
        }

        // Create order record
        $id       = $this->orderRepo->generateId();
        $tracking = $this->orderRepo->generateTrackingCode();
        $order    = [
            'id'               => $id,
            'tracking_code'    => $tracking,
            'customer_name'    => $name,
            'customer_address' => $address,
            'customer_contact' => $contact,
            'customer_email'   => $email,
            'items'            => array_values($cartItems),
            'subtotal'         => round($subtotal, 2),
            'discount_amount'  => round($discountAmount, 2),
            'bundle_applied'   => $bundleName,
            'total'            => round($total, 2),
            'status'           => 'pending',
            'estimated_days'   => rand(3, 7),
            'notes'            => $notes,
            'customer_id'      => $customerId,
            'created_at'       => date('c'),
            'updated_at'       => date('c'),
            'shipped_at'       => null,
            'delivered_at'     => null,
        ];

        if (!$this->orderRepo->save($order)) {
            return ['success' => false, 'errors' => ['Failed to save order. Please try again.']];
        }

        // Decrement stock and log each item
        foreach ($cartItems as $item) {
            $pid  = $item['product_id'] ?? '';
            $qty  = (int)($item['quantity'] ?? 0);
            $p    = $productMap[$pid] ?? null;
            if ($p && $qty > 0) {
                $before = (int)$p['stock'];
                $this->productRepo->decrementStock($pid, $qty);
                $this->logRepo->log(
                    $pid,
                    $item['name'] ?? $pid,
                    'sale',
                    -$qty,
                    $before,
                    $before - $qty,
                    "Online order {$id}",
                    'customer'
                );
            }
        }

        // Update PairPal intelligence data
        $productIds = array_column($cartItems, 'product_id');
        if (count($productIds) >= 2) {
            (new PairPalEngine())->updateAfterTransaction($productIds, date('c'));
        }

        // Notify admin of new customer order
        $itemCount = count($cartItems);
        $this->notifMgr->push(
            NotificationManager::CAT_ORDER,
            "New Order: #{$id}",
            "New order from {$name} — {$itemCount} item" . ($itemCount !== 1 ? 's' : '') . " · ₱" . number_format($total, 2),
            'index.php?page=orders'
        );

        return [
            'success'       => true,
            'order'         => $order,
            'tracking_code' => $tracking,
            'message'       => 'Order placed successfully!',
        ];
    }

    public function updateStatus(string $id, string $status): array {
        if (empty($id) || empty($status)) {
            return ['success' => false, 'message' => 'Order ID and status are required.'];
        }
        $order = $this->orderRepo->findById($id);
        $ok    = $this->orderRepo->updateStatus($id, $status);
        if ($ok && $order) {
            $this->actLogger->log(ActivityLogger::TYPE_ORDER_STATUS, "Order {$id} → {$status}", "Customer: " . ($order['customer_name'] ?? ''));
            $this->notifMgr->pushOrder($id, $order['customer_name'] ?? 'Unknown', $status);
        }
        return $ok
            ? ['success' => true,  'message' => "Order status updated to '{$status}'."]
            : ['success' => false, 'message' => 'Failed to update status. Order may not exist.'];
    }
}
