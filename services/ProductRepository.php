<?php
// services/ProductRepository.php
require_once __DIR__ . '/FileHandler.php';

class ProductRepository extends FileHandler {
    public function __construct() {
        parent::__construct(__DIR__ . '/../data/products.json');
    }

    public function getAll(): array { return $this->readAll(); }

    public function findById(string $id): ?array {
        foreach ($this->readAll() as $p) {
            if ($p['id'] === $id) return $p;
        }
        return null;
    }

    public function idExists(string $id): bool {
        return $this->findById($id) !== null;
    }

    public function save(array $record): bool {
        // Prevent duplicate IDs on create
        $products = $this->readAll();
        $found = false;
        foreach ($products as &$p) {
            if ($p['id'] === $record['id']) {
                $record['updated_at'] = date('c');
                $p = array_merge($p, $record);
                $found = true;
                break;
            }
        }
        if (!$found) {
            // Validate no duplicate
            foreach ($products as $p) {
                if ($p['id'] === $record['id']) return false;
            }
            $record['created_at'] = date('c');
            $record['updated_at'] = date('c');
            $products[] = $record;
        }
        return $this->writeAll($products);
    }

    public function delete(string $id): bool {
        $products = array_filter($this->readAll(), fn($p) => $p['id'] !== $id);
        return $this->writeAll(array_values($products));
    }

    public function search(string $query = '', string $category = '', string $sort = '', string $supplier = '', array $popularityMap = []): array {
        $products = $this->readAll();

        if ($query) {
            $products = array_filter($products, fn($p) =>
                stripos($p['name'],        $query) !== false ||
                stripos($p['description'], $query) !== false ||
                stripos($p['category'],    $query) !== false ||
                stripos($p['supplier'] ?? '', $query) !== false
            );
        }
        if ($category) {
            $products = array_filter($products, fn($p) => $p['category'] === $category);
        }
        if ($supplier) {
            $products = array_filter($products, fn($p) => ($p['supplier'] ?? '') === $supplier);
        }

        $products = array_values($products);

        match ($sort) {
            'price_asc'    => usort($products, fn($a,$b) => $a['price'] <=> $b['price']),
            'price_desc'   => usort($products, fn($a,$b) => $b['price'] <=> $a['price']),
            'stock_asc'    => usort($products, fn($a,$b) => $a['stock'] <=> $b['stock']),
            'stock_desc'   => usort($products, fn($a,$b) => $b['stock'] <=> $a['stock']),
            'popular_desc' => usort($products, fn($a,$b) =>
                ($popularityMap[$b['id']] ?? 0) <=> ($popularityMap[$a['id']] ?? 0)),
            default        => null,
        };

        return $products;
    }

    public function getCategories(): array {
        $cats = array_unique(array_column($this->readAll(), 'category'));
        sort($cats);
        return $cats;
    }

    public function getSuppliers(): array {
        $suppliers = array_filter(array_unique(array_column($this->readAll(), 'supplier')));
        sort($suppliers);
        return array_values($suppliers);
    }

    public function decrementStock(string $id, int $qty): bool {
        $products = $this->readAll();
        foreach ($products as &$p) {
            if ($p['id'] === $id) {
                if ($p['stock'] < $qty) return false;
                $p['stock']     -= $qty;
                $p['updated_at'] = date('c');
                return $this->writeAll($products);
            }
        }
        return false;
    }

    public function adjustStock(string $id, int $newStock): array {
        $products = $this->readAll();
        foreach ($products as &$p) {
            if ($p['id'] === $id) {
                if ($newStock < 0) return ['success' => false, 'message' => 'Stock cannot be negative.'];
                $before         = $p['stock'];
                $p['stock']     = $newStock;
                $p['updated_at'] = date('c');
                $ok = $this->writeAll($products);
                return $ok
                    ? ['success' => true, 'before' => $before, 'after' => $newStock]
                    : ['success' => false, 'message' => 'Write failed.'];
            }
        }
        return ['success' => false, 'message' => 'Product not found.'];
    }

    public function generateId(): string {
        $products = $this->readAll();
        $max = 0;
        foreach ($products as $p) {
            if (preg_match('/prd_(\d+)/', $p['id'], $m)) $max = max($max, (int)$m[1]);
        }
        return 'prd_' . str_pad($max + 1, 3, '0', STR_PAD_LEFT);
    }

    public function importBulk(array $items): array {
        $products = $this->readAll();
        $existingIds = array_column($products, 'id');
        $added = 0; $skipped = 0;
        foreach ($items as $item) {
            if (empty($item['name']) || empty($item['category']) || !isset($item['price'])) { $skipped++; continue; }
            if (!empty($item['id']) && in_array($item['id'], $existingIds)) { $skipped++; continue; }
            $max = 0;
            foreach ($products as $p) {
                if (preg_match('/prd_(\d+)/', $p['id'], $m)) $max = max($max, (int)$m[1]);
            }
            $id = !empty($item['id']) ? $item['id'] : ('prd_' . str_pad($max + 1, 3, '0', STR_PAD_LEFT));
            $record = [
                'id'                  => $id,
                'name'                => trim($item['name']),
                'category'            => trim($item['category']),
                'price'               => (float)$item['price'],
                'stock'               => (int)($item['stock'] ?? 0),
                'description'         => trim($item['description'] ?? ''),
                'supplier'            => trim($item['supplier'] ?? ''),
                'low_stock_threshold' => (int)($item['low_stock_threshold'] ?? 8),
                'image'               => $item['image'] ?? '',
                'date_added'          => $item['date_added'] ?? date('Y-m-d'),
                'created_at'          => date('c'),
                'updated_at'          => date('c'),
            ];
            $products[]    = $record;
            $existingIds[] = $id;
            $added++;
        }
        $ok = $this->writeAll($products);
        return ['success' => $ok, 'added' => $added, 'skipped' => $skipped];
    }

    public function renameCategory(string $oldName, string $newName): int {
        $all     = $this->readAll();
        $changed = 0;
        foreach ($all as &$p) {
            if ($p['category'] === $oldName) {
                $p['category'] = trim($newName);
                $changed++;
            }
        }
        if ($changed > 0) $this->writeAll($all);
        return $changed;
    }

    public function deleteCategory(string $name): int {
        // "Delete" = rename to Uncategorised so no products are orphaned
        return $this->renameCategory($name, 'Uncategorised');
    }

}