<?php
// services/InventoryLogRepository.php
require_once __DIR__ . '/FileHandler.php';

class InventoryLogRepository extends FileHandler {
    public function __construct() {
        parent::__construct(__DIR__ . '/../data/inventory_logs.json');
    }

    public function getAll(): array {
        $logs = $this->readAll();
        usort($logs, fn($a, $b) => strcmp($b['date'], $a['date']));
        return $logs;
    }

    public function findById(string $id): ?array {
        foreach ($this->readAll() as $l) {
            if ($l['id'] === $id) return $l;
        }
        return null;
    }

    public function save(array $record): bool {
        $logs = $this->readAll();
        $found = false;
        foreach ($logs as &$l) {
            if ($l['id'] === $record['id']) { $l = $record; $found = true; break; }
        }
        if (!$found) $logs[] = $record;
        return $this->writeAll($logs);
    }

    public function delete(string $id): bool {
        $logs = array_filter($this->readAll(), fn($l) => $l['id'] !== $id);
        return $this->writeAll(array_values($logs));
    }

    public function log(string $productId, string $productName, string $changeType, int $quantityChanged, int $stockBefore, int $stockAfter, string $note = '', string $userId = ''): bool {
        $id = 'log_' . uniqid();
        return $this->save([
            'id'               => $id,
            'product_id'       => $productId,
            'product_name'     => $productName,
            'change_type'      => $changeType,   // sale | manual_add | manual_remove | import
            'quantity_changed' => $quantityChanged,
            'stock_before'     => $stockBefore,
            'stock_after'      => $stockAfter,
            'note'             => $note,
            'user_id'          => $userId ?: ($_SESSION['user_id'] ?? ''),
            'date'             => date('c'),
        ]);
    }

    public function getByProduct(string $productId): array {
        return array_values(array_filter($this->readAll(), fn($l) => $l['product_id'] === $productId));
    }

    public function generateId(): string { return 'log_' . uniqid(); }
}
