<?php
// models/Product.php

class Product {
    private string $id;
    private string $name;
    private string $category;
    private float  $price;
    private int    $stock;
    private string $description;
    private string $supplier;
    private int    $lowStockThreshold;
    private string $image;
    private string $dateAdded;

    public function __construct(array $data) {
        $this->id                = $data['id']                  ?? '';
        $this->name              = $data['name']                ?? '';
        $this->category          = $data['category']            ?? '';
        $this->price             = (float)($data['price']       ?? 0);
        $this->stock             = (int)($data['stock']         ?? 0);
        $this->description       = $data['description']         ?? '';
        $this->supplier          = $data['supplier']            ?? '';
        $this->lowStockThreshold = (int)($data['low_stock_threshold'] ?? 8);
        $this->image             = $data['image']               ?? '';
        $this->dateAdded         = $data['date_added']          ?? date('Y-m-d');
    }

    public function getId(): string             { return $this->id; }
    public function getName(): string           { return $this->name; }
    public function getCategory(): string       { return $this->category; }
    public function getPrice(): float           { return $this->price; }
    public function getStock(): int             { return $this->stock; }
    public function getDescription(): string    { return $this->description; }
    public function getSupplier(): string       { return $this->supplier; }
    public function getLowStockThreshold(): int { return $this->lowStockThreshold; }
    public function getImage(): string          { return $this->image; }
    public function isInStock(): bool           { return $this->stock > 0; }
    public function isLowStock(): bool          { return $this->stock <= $this->lowStockThreshold; }

    public function toArray(): array {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'category'            => $this->category,
            'price'               => $this->price,
            'stock'               => $this->stock,
            'description'         => $this->description,
            'supplier'            => $this->supplier,
            'low_stock_threshold' => $this->lowStockThreshold,
            'image'               => $this->image,
            'date_added'          => $this->dateAdded,
        ];
    }

    const MAX_NAME_LEN        = 120;
    const MAX_CATEGORY_LEN    = 60;
    const MAX_DESCRIPTION_LEN = 500;
    const MAX_SUPPLIER_LEN    = 120;

    public static function validate(array $data): array {
        $errors = [];
        $name     = trim($data['name']     ?? '');
        $category = trim($data['category'] ?? '');
        $desc     = trim($data['description'] ?? '');
        $supplier = trim($data['supplier'] ?? '');

        if (empty($name))                              $errors[] = 'Product name is required.';
        elseif (mb_strlen($name) > self::MAX_NAME_LEN) $errors[] = 'Product name must be ' . self::MAX_NAME_LEN . ' characters or fewer.';

        if (empty($category))                                $errors[] = 'Category is required.';
        elseif (mb_strlen($category) > self::MAX_CATEGORY_LEN) $errors[] = 'Category must be ' . self::MAX_CATEGORY_LEN . ' characters or fewer.';

        if (!is_numeric($data['price'] ?? '') || (float)$data['price'] <= 0)
            $errors[] = 'Price must be a positive number.';
        if (!is_numeric($data['stock'] ?? '') || (int)$data['stock'] < 0)
            $errors[] = 'Stock must be 0 or more.';

        if (mb_strlen($desc)     > self::MAX_DESCRIPTION_LEN) $errors[] = 'Description must be ' . self::MAX_DESCRIPTION_LEN . ' characters or fewer.';
        if (mb_strlen($supplier) > self::MAX_SUPPLIER_LEN)    $errors[] = 'Supplier name must be ' . self::MAX_SUPPLIER_LEN . ' characters or fewer.';

        return $errors;
    }
}
