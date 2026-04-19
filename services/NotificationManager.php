<?php
// services/NotificationManager.php
require_once __DIR__ . '/FileHandler.php';

class NotificationManager extends FileHandler {
    // Category constants
    const CAT_STOCK   = 'stock';
    const CAT_ORDER   = 'order';
    const CAT_SYSTEM  = 'system';
    const CAT_INSIGHT = 'insight';
    const CAT_BUNDLE  = 'bundle';

    public function __construct() {
        parent::__construct(__DIR__ . '/../data/notifications.json');
    }

    public function getAll(): array {
        $n = $this->readAll();
        usort($n, fn($a,$b) => strcmp($b['created_at'],$a['created_at']));
        return $n;
    }

    public function getUnread(): array {
        return array_values(array_filter($this->getAll(), fn($n) => !($n['read'] ?? false)));
    }

    public function getUnreadCount(): int {
        return count($this->getUnread());
    }

    public function getByCategory(string $category): array {
        return array_values(array_filter($this->getAll(), fn($n) => $n['category'] === $category));
    }

    public function findById(string $id): ?array {
        foreach ($this->readAll() as $n) { if ($n['id'] === $id) return $n; }
        return null;
    }

    public function save(array $record): bool {
        $all   = $this->readAll();
        $found = false;
        foreach ($all as &$n) {
            if ($n['id'] === $record['id']) { $n = $record; $found = true; break; }
        }
        if (!$found) $all[] = $record;
        return $this->writeAll($all);
    }

    public function delete(string $id): bool {
        $all = array_filter($this->readAll(), fn($n) => $n['id'] !== $id);
        return $this->writeAll(array_values($all));
    }

    public function markRead(string $id): bool {
        $all = $this->readAll();
        foreach ($all as &$n) {
            if ($n['id'] === $id) { $n['read'] = true; $n['read_at'] = date('c'); return $this->writeAll($all); }
        }
        return false;
    }

    public function markAllRead(): bool {
        $all = $this->readAll();
        foreach ($all as &$n) { $n['read'] = true; $n['read_at'] = date('c'); }
        return $this->writeAll($all);
    }

    /** Create and persist a notification */
    public function push(string $category, string $title, string $message, string $link = '', array $meta = []): array {
        $id     = 'notif_' . uniqid();
        $record = [
            'id'         => $id,
            'category'   => $category,
            'title'      => $title,
            'message'    => $message,
            'link'       => $link,
            'meta'       => $meta,
            'read'       => false,
            'read_at'    => null,
            'created_at' => date('c'),
        ];
        $this->save($record);
        return $record;
    }

    /** Convenience wrappers */
    public function pushStock(string $productName, int $stock, string $link = ''): array {
        $urgency = $stock <= 3 ? '🚨 Critical' : '⚠️ Low Stock';
        return $this->push(self::CAT_STOCK, "{$urgency}: {$productName}", "{$productName} has only {$stock} unit" . ($stock!==1?'s':'') . " remaining.", $link ?: 'index.php?page=inventory');
    }

    public function pushOrder(string $orderId, string $customerName, string $status, string $link = ''): array {
        $label = ucfirst($status);
        return $this->push(self::CAT_ORDER, "Order {$label}: #{$orderId}", "Order from {$customerName} is now {$status}.", $link ?: 'index.php?page=orders');
    }

    public function pushInsight(string $message, string $link = ''): array {
        return $this->push(self::CAT_INSIGHT, '✦ PairPal Insight', $message, $link ?: 'index.php?page=intelligence');
    }

    public function pushBundle(string $bundleName, string $link = ''): array {
        return $this->push(self::CAT_BUNDLE, '🎁 Bundle Opportunity', "Smart bundle detected: {$bundleName}", $link ?: 'index.php?page=bundles');
    }

    public function pushSystem(string $title, string $message, string $link = ''): array {
        return $this->push(self::CAT_SYSTEM, $title, $message, $link);
    }

    /** Auto-generate stock alerts for all low-stock products */
    public function generateStockAlerts(array $lowStockProducts): void {
        // Only push if not already notified today for same product
        $today = date('Y-m-d');
        $existing = array_filter($this->readAll(), function($n) use ($today) {
            return $n['category'] === self::CAT_STOCK
                && substr($n['created_at'], 0, 10) === $today;
        });
        $notifiedNames = array_column(array_values($existing), 'title');
        foreach ($lowStockProducts as $p) {
            $title = ($p['stock'] <= 3 ? '🚨 Critical' : '⚠️ Low Stock') . ': ' . $p['name'];
            if (!in_array($title, $notifiedNames)) {
                $this->pushStock($p['name'], $p['stock']);
            }
        }
    }

    public function generateId(): string { return 'notif_' . uniqid(); }
}
