<?php
// services/CustomerRepository.php
require_once __DIR__ . '/FileHandler.php';

class CustomerRepository extends FileHandler {
    public function __construct() {
        parent::__construct(__DIR__ . '/../data/customers.json');
    }

    public function getAll(): array { return $this->readAll(); }

    public function findById(string $id): ?array {
        foreach ($this->readAll() as $c) {
            if ($c['id'] === $id) return $c;
        }
        return null;
    }

    public function findByUsername(string $username): ?array {
        foreach ($this->readAll() as $c) {
            if (strtolower($c['username'] ?? '') === strtolower($username)) return $c;
        }
        return null;
    }

    public function findByEmail(string $email): ?array {
        foreach ($this->readAll() as $c) {
            if (strtolower($c['email'] ?? '') === strtolower($email)) return $c;
        }
        return null;
    }

    public function save(array $record): bool {
        $all   = $this->readAll();
        $found = false;
        foreach ($all as &$c) {
            if ($c['id'] === $record['id']) { $c = $record; $found = true; break; }
        }
        if (!$found) $all[] = $record;
        return $this->writeAll($all);
    }

    public function delete(string $id): bool {
        $all = array_filter($this->readAll(), fn($c) => $c['id'] !== $id);
        return $this->writeAll(array_values($all));
    }

    public function updateLastLogin(string $id): void {
        $all = $this->readAll();
        foreach ($all as &$c) {
            if ($c['id'] === $id) { $c['last_login'] = date('c'); break; }
        }
        $this->writeAll($all);
    }

    public function generateId(): string {
        $all = $this->readAll();
        $max = 0;
        foreach ($all as $c) {
            if (preg_match('/cust_(\d+)/', $c['id'] ?? '', $m)) $max = max($max, (int)$m[1]);
        }
        return 'cust_' . str_pad($max + 1, 3, '0', STR_PAD_LEFT);
    }

    public function updateWishlist(string $id, array $productIds): bool {
        $all = $this->readAll();
        foreach ($all as &$c) {
            if ($c['id'] === $id) {
                $c['wishlist'] = json_encode(array_values(array_unique($productIds)));
                return $this->writeAll($all);
            }
        }
        return false;
    }
}
