<?php
// models/Customer.php
require_once __DIR__ . '/User.php';

class Customer extends User {
    private string $address;
    private string $contact;
    private string $wishlist; // JSON-encoded array of product IDs

    public function __construct(array $data) {
        parent::__construct($data);
        $this->address  = $data['address']  ?? '';
        $this->contact  = $data['contact']  ?? '';
        $this->wishlist = $data['wishlist']  ?? '[]';
    }

    // Polymorphism — customers have no staff access
    public function canManageProducts(): bool   { return false; }
    public function canManageUsers(): bool      { return false; }
    public function canViewReports(): bool      { return false; }
    public function getDashboardTitle(): string { return 'My Account'; }

    public function getAddress(): string  { return $this->address; }
    public function getContact(): string  { return $this->contact; }

    public function getWishlist(): array {
        $w = json_decode($this->wishlist, true);
        return is_array($w) ? $w : [];
    }

    public function toArray(): array {
        return array_merge(parent::toArray(), [
            'address'  => $this->address,
            'contact'  => $this->contact,
            'wishlist' => $this->wishlist,
        ]);
    }

    public static function validate(array $data, bool $isNew = true): array {
        $errors = [];
        if (empty(trim($data['name']     ?? ''))) $errors[] = 'Full name is required.';
        if (empty(trim($data['email']    ?? ''))) $errors[] = 'Email is required.';
        if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
        if ($isNew) {
            if (empty(trim($data['username'] ?? ''))) $errors[] = 'Username is required.';
            if (strlen($data['password'] ?? '') < 6)  $errors[] = 'Password must be at least 6 characters.';
        }
        return $errors;
    }
}
