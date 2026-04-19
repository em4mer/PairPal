<?php
// controllers/CartController.php
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../services/ProductRepository.php';
require_once __DIR__ . '/../services/SalesRepository.php';
require_once __DIR__ . '/../services/InventoryLogRepository.php';
require_once __DIR__ . '/../services/PairPalEngine.php';
require_once __DIR__ . '/../services/BundleRepository.php';
require_once __DIR__ . '/../services/ActivityLogger.php';

class CartController {
    private ProductRepository      $productRepo;
    private SalesRepository        $salesRepo;
    private InventoryLogRepository $logRepo;
    private PairPalEngine          $engine;
    private string                 $sessionKey;

    private ActivityLogger $actLogger;

    public function __construct(string $sessionKey = 'cart') {
        $this->productRepo = new ProductRepository();
        $this->salesRepo   = new SalesRepository();
        $this->logRepo     = new InventoryLogRepository();
        $this->engine      = new PairPalEngine();
        $this->sessionKey  = $sessionKey;
        $this->actLogger   = new ActivityLogger();
    }

    private function loadCart(): Cart {
        $data = $_SESSION[$this->sessionKey] ?? [];
        return isset($data['items']) ? Cart::fromSession($data) : new Cart($data);
    }

    private function saveCart(Cart $cart): void {
        $_SESSION[$this->sessionKey] = $cart->toSessionArray();
    }

    /** After any cart change, re-evaluate bundle discount and auto-apply it */
    private function applyAutoBundleDiscount(Cart $cart): array {
        $ids    = $cart->getProductIds();
        $result = $this->engine->evaluateCartDiscount($ids, $cart->getSubtotal());
        if ($result['bundle']) {
            $cart->setDiscount($result['discount_type'], $result['discount_value']);
            $cart->setBundleInfo($result['bundle']['name'], $result['message']);
        } else {
            // Clear auto-discount only if it was auto-applied (not manual)
            if ($cart->isAutoBundleDiscount()) {
                $cart->setDiscount('none', 0);
                $cart->setBundleInfo('', '');
            }
        }
        return $result;
    }

    public function addToCart(string $productId, int $qty = 1): array {
        $product = $this->productRepo->findById($productId);
        if (!$product) return ['success' => false, 'message' => 'Product not found.'];
        $cart = $this->loadCart();
        $currentQty = 0;
        foreach ($cart->getItems() as $item) {
            if ($item['product_id'] === $productId) { $currentQty = $item['quantity']; break; }
        }
        if ($currentQty + $qty > $product['stock']) return ['success' => false, 'message' => "Only {$product['stock']} in stock."];
        $cart->addItem($product, $qty);
        $bundleResult = $this->applyAutoBundleDiscount($cart);
        $this->saveCart($cart);
        return ['success' => true, 'message' => 'Added to cart.', 'count' => $cart->getItemCount(), 'bundle_message' => $bundleResult['message'] ?? ''];
    }

    public function updateItem(string $productId, int $qty): array {
        $product = $this->productRepo->findById($productId);
        if (!$product) return ['success' => false, 'message' => 'Product not found.'];
        if ($qty > $product['stock']) return ['success' => false, 'message' => "Only {$product['stock']} in stock."];
        $cart = $this->loadCart();
        $cart->updateQuantity($productId, $qty);
        $this->applyAutoBundleDiscount($cart);
        $this->saveCart($cart);
        return ['success' => true, 'subtotal' => $cart->getSubtotal(), 'discount' => $cart->getDiscountAmount(), 'total' => $cart->getTotal(), 'count' => $cart->getItemCount(), 'bundle_message' => $cart->getBundleMessage()];
    }

    public function removeItem(string $productId): array {
        $cart = $this->loadCart();
        $cart->removeItem($productId);
        $this->applyAutoBundleDiscount($cart);
        $this->saveCart($cart);
        return ['success' => true, 'subtotal' => $cart->getSubtotal(), 'discount' => $cart->getDiscountAmount(), 'total' => $cart->getTotal(), 'count' => $cart->getItemCount(), 'bundle_message' => $cart->getBundleMessage()];
    }

    public function applyDiscount(string $type, float $value): array {
        if (!in_array($type, ['none','percent','fixed'])) return ['success' => false, 'message' => 'Invalid discount type.'];
        $cart = $this->loadCart();
        $cart->setDiscount($type, $value);
        $cart->setBundleInfo('', '');       // manual override clears bundle tag
        $cart->setManualDiscount(true);
        $this->saveCart($cart);
        return ['success' => true, 'subtotal' => $cart->getSubtotal(), 'discount' => $cart->getDiscountAmount(), 'total' => $cart->getTotal()];
    }

    public function getCart(): Cart { return $this->loadCart(); }

    public function getCartState(): array {
        $cart = $this->loadCart();
        return [
            'items'          => $cart->getItems(),
            'subtotal'       => $cart->getSubtotal(),
            'discount_type'  => $cart->getDiscountType(),
            'discount_value' => $cart->getDiscountValue(),
            'discount_amount'=> $cart->getDiscountAmount(),
            'total'          => $cart->getTotal(),
            'count'          => $cart->getItemCount(),
            'bundle_name'    => $cart->getBundleName(),
            'bundle_message' => $cart->getBundleMessage(),
            'upsell_prompts' => $this->engine->getUpsellPrompts($cart->getProductIds()),
            'suggestions'    => $this->engine->getCartSuggestions($cart->getProductIds(), 3),
        ];
    }

    public function checkout(float $amountPaid): array {
        $cart = $this->loadCart();
        if ($cart->isEmpty()) return ['success' => false, 'message' => 'Cart is empty.'];
        if ($amountPaid < $cart->getTotal()) return ['success' => false, 'message' => 'Amount paid is insufficient.'];
        $errors = $cart->validateStock($this->productRepo->getAll());
        if ($errors) return ['success' => false, 'message' => implode(' ', $errors)];

        $txnId = $this->salesRepo->generateId();
        $txn   = Transaction::fromCart($cart, $_SESSION['user_id'], $_SESSION['name'], $amountPaid, $txnId);
        if (!$this->salesRepo->save($txn->toArray())) return ['success' => false, 'message' => 'Failed to save transaction.'];

        foreach ($cart->getItems() as $item) {
            $product = $this->productRepo->findById($item['product_id']);
            if ($product) {
                $before = $product['stock'];
                $this->productRepo->decrementStock($item['product_id'], $item['quantity']);
                $this->logRepo->log($item['product_id'], $item['name'], 'sale', -$item['quantity'], $before, $before - $item['quantity'], "TXN {$txnId}");
            }
        }

        $this->engine->updateAfterTransaction(array_column($cart->getItems(), 'product_id'), $txn->getDate());
        try {
            $this->actLogger->log(ActivityLogger::TYPE_SALE, "Sale completed: ₱" . number_format($cart->getTotal(), 2), count($cart->getItems()) . " items · TXN " . $txnId);
        } catch (\Throwable $e) {}
        $cart->clear();
        $this->saveCart($cart);
        return ['success' => true, 'transaction' => $txn->toArray(), 'message' => 'Checkout successful!'];
    }

    public function clearCart(): void { $this->saveCart(new Cart()); }
}
