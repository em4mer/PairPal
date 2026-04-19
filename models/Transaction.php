<?php
// models/Transaction.php

class Transaction {
    private string $id;
    private string $cashierId;
    private string $cashierName;
    private array  $items;
    private float  $subtotal;
    private string $discountType;
    private float  $discountValue;
    private float  $discountAmount;
    private string $bundleName;
    private float  $total;
    private float  $amountPaid;
    private float  $change;
    private string $date;
    private string $status;

    public function __construct(array $data) {
        $this->id             = $data['id']             ?? '';
        $this->cashierId      = $data['cashier_id']     ?? '';
        $this->cashierName    = $data['cashier_name']   ?? '';
        $this->items          = $data['items']          ?? [];
        $this->subtotal       = (float)($data['subtotal']       ?? $data['total'] ?? 0);
        $this->discountType   = $data['discount_type']  ?? 'none';
        $this->discountValue  = (float)($data['discount_value']  ?? 0);
        $this->discountAmount = (float)($data['discount_amount'] ?? 0);
        $this->bundleName     = $data['bundle_name']    ?? '';
        $this->total          = (float)($data['total']          ?? 0);
        $this->amountPaid     = (float)($data['amount_paid']    ?? 0);
        $this->change         = (float)($data['change']         ?? 0);
        $this->date           = $data['date']           ?? date('c');
        $this->status         = $data['status']         ?? 'completed';
    }

    public function getId(): string            { return $this->id; }
    public function getCashierId(): string     { return $this->cashierId; }
    public function getCashierName(): string   { return $this->cashierName; }
    public function getItems(): array          { return $this->items; }
    public function getSubtotal(): float       { return $this->subtotal; }
    public function getDiscountType(): string  { return $this->discountType; }
    public function getDiscountValue(): float  { return $this->discountValue; }
    public function getDiscountAmount(): float { return $this->discountAmount; }
    public function getBundleName(): string    { return $this->bundleName; }
    public function getTotal(): float          { return $this->total; }
    public function getAmountPaid(): float     { return $this->amountPaid; }
    public function getChange(): float         { return $this->change; }
    public function getDate(): string          { return $this->date; }
    public function getStatus(): string        { return $this->status; }

    public function toArray(): array {
        return [
            'id'              => $this->id,
            'cashier_id'      => $this->cashierId,
            'cashier_name'    => $this->cashierName,
            'items'           => $this->items,
            'subtotal'        => $this->subtotal,
            'discount_type'   => $this->discountType,
            'discount_value'  => $this->discountValue,
            'discount_amount' => $this->discountAmount,
            'bundle_name'     => $this->bundleName,
            'total'           => $this->total,
            'amount_paid'     => $this->amountPaid,
            'change'          => $this->change,
            'date'            => $this->date,
            'status'          => $this->status,
        ];
    }

    public static function fromCart(Cart $cart, string $cashierId, string $cashierName, float $amountPaid, string $id): self {
        $items = [];
        foreach ($cart->getItems() as $item) {
            $items[] = [
                'product_id' => $item['product_id'],
                'name'       => $item['name'],
                'price'      => $item['price'],
                'quantity'   => $item['quantity'],
                'subtotal'   => $item['price'] * $item['quantity'],
            ];
        }
        $sub   = $cart->getSubtotal();
        $disc  = $cart->getDiscountAmount();
        $total = $cart->getTotal();
        return new self([
            'id'              => $id,
            'cashier_id'      => $cashierId,
            'cashier_name'    => $cashierName,
            'items'           => $items,
            'subtotal'        => $sub,
            'discount_type'   => $cart->getDiscountType(),
            'discount_value'  => $cart->getDiscountValue(),
            'discount_amount' => $disc,
            'bundle_name'     => $cart->getBundleName(),
            'total'           => $total,
            'amount_paid'     => $amountPaid,
            'change'          => $amountPaid - $total,
            'date'            => date('c'),
            'status'          => 'completed',
        ]);
    }
}
