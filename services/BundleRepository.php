<?php
// services/BundleRepository.php
require_once __DIR__ . '/FileHandler.php';

class BundleRepository extends FileHandler {
    public function __construct() {
        parent::__construct(__DIR__ . '/../data/bundles.json');
    }

    public function getAll(): array {
        $bundles = $this->readAll();
        usort($bundles, fn($a, $b) => ($b['frequency'] ?? 0) <=> ($a['frequency'] ?? 0));
        return $bundles;
    }

    public function getActive(): array {
        return array_values(array_filter(
            $this->getAll(),
            fn($b) => ($b['status'] ?? 'active') === 'active'
        ));
    }

    public function findById(string $id): ?array {
        foreach ($this->readAll() as $b) {
            if (($b['id'] ?? '') === $id) return $b;
        }
        return null;
    }

    public function findMatchingBundles(array $cartProductIds): array {
        $matched = [];
        foreach ($this->getActive() as $b) {
            $bIds       = $b['product_ids'] ?? [];
            $allPresent = !empty($bIds) && count(array_diff($bIds, $cartProductIds)) === 0;
            if ($allPresent) $matched[] = $b;
        }
        usort($matched, fn($a, $b) => ($b['discount_amount'] ?? 0) <=> ($a['discount_amount'] ?? 0));
        return $matched;
    }

    public function save(array $record): bool {
        $all   = $this->readAll();
        $found = false;
        foreach ($all as &$b) {
            if (($b['id'] ?? '') === $record['id']) {
                $b     = $record;
                $found = true;
                break;
            }
        }
        if (!$found) $all[] = $record;
        return $this->writeAll($all);
    }

    public function delete(string $id): bool {
        $all = array_filter($this->readAll(), fn($b) => ($b['id'] ?? '') !== $id);
        return $this->writeAll(array_values($all));
    }

    public function setStatus(string $id, string $status): bool {
        $all = $this->readAll();
        foreach ($all as &$b) {
            if (($b['id'] ?? '') === $id) {
                $b['status']     = $status;
                $b['updated_at'] = date('c');
                return $this->writeAll($all);
            }
        }
        return false;
    }

    public function generateId(): string {
        $all = $this->readAll();
        $max = 0;
        foreach ($all as $b) {
            if (preg_match('/bnd_(\d+)/', $b['id'] ?? '', $m)) {
                $max = max($max, (int)$m[1]);
            }
        }
        return 'bnd_' . str_pad($max + 1, 3, '0', STR_PAD_LEFT);
    }

    public function upsertByProducts(array $productIds, array $fields): bool {
        sort($productIds);
        $all   = $this->readAll();
        $found = false;

        foreach ($all as &$b) {
            $bIds = $b['product_ids'] ?? [];
            sort($bIds);
            if ($bIds === $productIds) {
                // Preserve ID and created_at, update everything else
                $b = array_merge($b, $fields, [
                    'id'          => $b['id'],
                    'product_ids' => $productIds,
                    'created_at'  => $b['created_at'] ?? date('c'),
                    'updated_at'  => date('c'),
                ]);
                $found = true;
                break;
            }
        }
        unset($b);

        if (!$found) {
            // Generate a fresh ID based on current state of $all (before appending)
            $max = 0;
            foreach ($all as $b) {
                if (preg_match('/bnd_(\d+)/', $b['id'] ?? '', $m)) {
                    $max = max($max, (int)$m[1]);
                }
            }
            $newId = 'bnd_' . str_pad($max + 1, 3, '0', STR_PAD_LEFT);

            $all[] = array_merge([
                'status'     => 'active',
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ], $fields, [
                'id'          => $newId,
                'product_ids' => $productIds,
            ]);
        }

        return $this->writeAll($all);
    }
}
