<?php
// services/OrderRepository.php
require_once __DIR__ . '/FileHandler.php';

class OrderRepository extends FileHandler {
    public function __construct() {
        parent::__construct(__DIR__ . '/../data/orders.json');
    }

    public function getAll(): array {
        $orders = $this->readAll();
        usort($orders, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return $orders;
    }

    public function findById(string $id): ?array {
        foreach ($this->readAll() as $o) {
            if ($o['id'] === $id) return $o;
        }
        return null;
    }

    public function findByTrackingCode(string $code): ?array {
        foreach ($this->readAll() as $o) {
            if (($o['tracking_code'] ?? '') === strtoupper($code)) return $o;
        }
        return null;
    }

    public function save(array $record): bool {
        $all   = $this->readAll();
        $found = false;
        foreach ($all as &$o) {
            if ($o['id'] === $record['id']) { $o = $record; $found = true; break; }
        }
        if (!$found) $all[] = $record;
        return $this->writeAll($all);
    }

    public function delete(string $id): bool {
        $all = array_filter($this->readAll(), fn($o) => $o['id'] !== $id);
        return $this->writeAll(array_values($all));
    }

    public function updateStatus(string $id, string $status): bool {
        $all = $this->readAll();
        $statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        if (!in_array($status, $statuses)) return false;
        foreach ($all as &$o) {
            if ($o['id'] === $id) {
                $o['status']     = $status;
                $o['updated_at'] = date('c');
                if ($status === 'shipped')   $o['shipped_at']   = date('c');
                if ($status === 'delivered') $o['delivered_at'] = date('c');
                return $this->writeAll($all);
            }
        }
        return false;
    }

    public function generateId(): string {
        $all = $this->readAll();
        $max = 0;
        foreach ($all as $o) {
            if (preg_match('/ord_(\d+)/', $o['id'] ?? '', $m)) $max = max($max, (int)$m[1]);
        }
        return 'ord_' . str_pad($max + 1, 3, '0', STR_PAD_LEFT);
    }

    public function generateTrackingCode(): string {
        return 'PP' . strtoupper(substr(md5(uniqid()), 0, 8));
    }

    public function getByStatus(string $status): array {
        return array_values(array_filter($this->getAll(), fn($o) => $o['status'] === $status));
    }

    public function getStatusCounts(): array {
        $counts = ['pending' => 0, 'processing' => 0, 'shipped' => 0, 'delivered' => 0, 'cancelled' => 0];
        foreach ($this->readAll() as $o) {
            $s = $o['status'] ?? 'pending';
            if (isset($counts[$s])) $counts[$s]++;
        }
        return $counts;
    }

    public function getByCustomer(string $customerId, int $limit = 0): array {
        $orders = array_values(array_filter(
            $this->getAll(),
            fn($o) => ($o['customer_id'] ?? '') === $customerId
        ));
        return $limit > 0 ? array_slice($orders, 0, $limit) : $orders;
    }

}