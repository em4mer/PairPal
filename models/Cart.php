<?php
// models/Cart.php

class Cart {
    private array  $items          = [];
    private string $discountType   = 'none';
    private float  $discountValue  = 0.0;
    private string $bundleName     = '';
    private string $bundleMessage  = '';
    private bool   $manualDiscount = false;

    public function __construct(array $items = [], string $discountType = 'none', float $discountValue = 0.0, string $bundleName = '', string $bundleMessage = '', bool $manualDiscount = false) {
        $this->items          = $items;
        $this->discountType   = $discountType;
        $this->discountValue  = $discountValue;
        $this->bundleName     = $bundleName;
        $this->bundleMessage  = $bundleMessage;
        $this->manualDiscount = $manualDiscount;
    }

    public function addItem(array $product, int $qty = 1): void {
        $id = $product['id'];
        if (isset($this->items[$id])) $this->items[$id]['quantity'] += $qty;
        else $this->items[$id] = ['product_id'=>$id,'name'=>$product['name'],'price'=>(float)$product['price'],'quantity'=>$qty,'stock'=>(int)$product['stock'],'category'=>$product['category']??''];
    }

    public function updateQuantity(string $productId, int $qty): void {
        if (!isset($this->items[$productId])) return;
        if ($qty <= 0) $this->removeItem($productId); else $this->items[$productId]['quantity'] = $qty;
    }

    public function removeItem(string $productId): void { unset($this->items[$productId]); }

    public function clear(): void { $this->items=[]; $this->discountType='none'; $this->discountValue=0.0; $this->bundleName=''; $this->bundleMessage=''; $this->manualDiscount=false; }

    public function setDiscount(string $type, float $value): void {
        $this->discountType  = in_array($type,['none','percent','fixed']) ? $type : 'none';
        $this->discountValue = max(0,$value);
    }

    public function setBundleInfo(string $name, string $message): void { $this->bundleName=$name; $this->bundleMessage=$message; }
    public function setManualDiscount(bool $v): void { $this->manualDiscount=$v; }
    public function isAutoBundleDiscount(): bool { return !empty($this->bundleName) && !$this->manualDiscount; }

    public function getItems(): array { return array_values($this->items); }

    public function getSubtotal(): float {
        $total = 0.0;
        foreach ($this->items as $item) $total += $item['price'] * $item['quantity'];
        return $total;
    }

    public function getDiscountAmount(): float {
        $sub = $this->getSubtotal();
        if ($this->discountType === 'percent') return round($sub * ($this->discountValue / 100), 2);
        if ($this->discountType === 'fixed')   return min($this->discountValue, $sub);
        return 0.0;
    }

    public function getTotal(): float            { return max(0, $this->getSubtotal() - $this->getDiscountAmount()); }
    public function getDiscountType(): string    { return $this->discountType; }
    public function getDiscountValue(): float    { return $this->discountValue; }
    public function getItemCount(): int          { return array_sum(array_column($this->items,'quantity')); }
    public function isEmpty(): bool              { return empty($this->items); }
    public function hasItem(string $id): bool    { return isset($this->items[$id]); }
    public function getProductIds(): array       { return array_keys($this->items); }
    public function getBundleName(): string      { return $this->bundleName; }
    public function getBundleMessage(): string   { return $this->bundleMessage; }

    public function validateStock(array $products): array {
        $errors = [];
        foreach ($this->items as $id => $item) {
            foreach ($products as $p) {
                if ($p['id']===$id && $item['quantity']>$p['stock']) $errors[]="Insufficient stock for \"{$item['name']}\". Available: {$p['stock']}.";
            }
        }
        return $errors;
    }

    public function toSessionArray(): array {
        return ['items'=>$this->items,'discountType'=>$this->discountType,'discountValue'=>$this->discountValue,'bundleName'=>$this->bundleName,'bundleMessage'=>$this->bundleMessage,'manualDiscount'=>$this->manualDiscount];
    }

    public static function fromSession(array $data): self {
        return new self($data['items']??[],$data['discountType']??'none',(float)($data['discountValue']??0),$data['bundleName']??'',$data['bundleMessage']??'',(bool)($data['manualDiscount']??false));
    }
}
