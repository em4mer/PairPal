<?php
// services/ActivityLogger.php
require_once __DIR__ . '/FileHandler.php';

class ActivityLogger extends FileHandler {
    const TYPE_PRODUCT_CREATE  = 'product_create';
    const TYPE_PRODUCT_UPDATE  = 'product_update';
    const TYPE_PRODUCT_DELETE  = 'product_delete';
    const TYPE_STOCK_ADJUST    = 'stock_adjust';
    const TYPE_ORDER_STATUS    = 'order_status';
    const TYPE_SALE            = 'sale';
    const TYPE_LOGIN           = 'login';
    const TYPE_LOGOUT          = 'logout';
    const TYPE_BUNDLE_GENERATE = 'bundle_generate';
    const TYPE_IMPORT          = 'bulk_import';

    public function __construct() {
        parent::__construct(__DIR__ . '/../data/activity_logs.json');
    }

    public function getAll(): array {
        $logs = $this->readAll();
        usort($logs, fn($a,$b) => strcmp($b['created_at'],$a['created_at']));
        return $logs;
    }

    public function findById(string $id): ?array {
        foreach ($this->readAll() as $l) { if ($l['id'] === $id) return $l; }
        return null;
    }

    public function save(array $record): bool {
        $all   = $this->readAll();
        $found = false;
        foreach ($all as &$l) {
            if ($l['id'] === $record['id']) { $l = $record; $found = true; break; }
        }
        if (!$found) $all[] = $record;
        // Keep last 500 entries to prevent unbounded growth
        if (count($all) > 500) {
            usort($all, fn($a,$b) => strcmp($b['created_at'],$a['created_at']));
            $all = array_slice($all, 0, 500);
        }
        return $this->writeAll($all);
    }

    public function delete(string $id): bool {
        $all = array_filter($this->readAll(), fn($l) => $l['id'] !== $id);
        return $this->writeAll(array_values($all));
    }

    public function log(string $type, string $action, string $detail = '', ?string $userId = null, ?string $userName = null): bool {
        // Resolve user from session if not provided
        $uid   = $userId   ?? ($_SESSION['user_id'] ?? 'system');
        $uname = $userName ?? ($_SESSION['name']    ?? 'System');

        return $this->save([
            'id'         => 'act_' . uniqid(),
            'type'       => $type,
            'action'     => $action,
            'detail'     => $detail,
            'user_id'    => $uid,
            'user_name'  => $uname,
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'created_at' => date('c'),
        ]);
    }

    public function getByType(string $type): array {
        return array_values(array_filter($this->getAll(), fn($l) => $l['type'] === $type));
    }

    public function getByUser(string $userId): array {
        return array_values(array_filter($this->getAll(), fn($l) => $l['user_id'] === $userId));
    }

    public function getRecent(int $limit = 50): array {
        return array_slice($this->getAll(), 0, $limit);
    }

    public function generateId(): string { return 'act_' . uniqid(); }
}
