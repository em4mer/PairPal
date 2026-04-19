<?php
// controllers/ProductController.php
require_once __DIR__ . '/../services/ProductRepository.php';
require_once __DIR__ . '/../services/InventoryLogRepository.php';
require_once __DIR__ . '/../services/ActivityLogger.php';
require_once __DIR__ . '/../services/NotificationManager.php';
require_once __DIR__ . '/../models/Product.php';

class ProductController {
    private ProductRepository      $repo;
    private InventoryLogRepository $logRepo;

    private ActivityLogger $actLogger;
    private NotificationManager $notifMgr;

    public function __construct() {
        $this->repo      = new ProductRepository();
        $this->logRepo   = new InventoryLogRepository();
        $this->actLogger = new ActivityLogger();
        $this->notifMgr  = new NotificationManager();
    }

    public function getAll(): array { return $this->repo->getAll(); }

    public function search(string $q = '', string $cat = '', string $sort = '', string $supplier = '', array $popularityMap = []): array {
        return $this->repo->search($q, $cat, $sort, $supplier, $popularityMap);
    }

    public function getCategories(): array { return $this->repo->getCategories(); }
    public function getSuppliers(): array  { return $this->repo->getSuppliers(); }
    public function findById(string $id): ?array { return $this->repo->findById($id); }

    public function create(array $data, array $files = []): array {
        $errors = Product::validate($data);
        if ($errors) return ['success' => false, 'errors' => $errors];

        $imagePath = '';
        if (!empty($files['image']['tmp_name'])) {
            $result = $this->handleImageUpload($files['image']);
            if (!$result['success']) return ['success' => false, 'errors' => [$result['message']]];
            $imagePath = $result['path'];
        }

        $record = [
            'id'                  => $this->repo->generateId(),
            'name'                => trim($data['name']),
            'category'            => trim($data['category']),
            'price'               => (float)$data['price'],
            'stock'               => (int)$data['stock'],
            'description'         => trim($data['description'] ?? ''),
            'supplier'            => trim($data['supplier'] ?? ''),
            'low_stock_threshold' => (int)($data['low_stock_threshold'] ?? 8),
            'image'               => $imagePath,
            'date_added'          => date('Y-m-d'),
        ];

        $ok = $this->repo->save($record);
        if ($ok && (int)$data['stock'] > 0) {
            $this->logRepo->log($record['id'], $record['name'], 'manual_add', (int)$data['stock'], 0, (int)$data['stock'], 'Initial stock on creation');
        }
        if ($ok) {
            $this->actLogger->log(ActivityLogger::TYPE_PRODUCT_CREATE, "Created product: {$record['name']}", "ID: {$record['id']}, Category: {$record['category']}, Price: ₱{$record['price']}");
            $threshold = (int)($data['low_stock_threshold'] ?? 8);
            if ((int)$data['stock'] > 0 && (int)$data['stock'] <= $threshold) {
                $this->notifMgr->pushStock($record['name'], (int)$data['stock']);
            }
        }
        return $ok
            ? ['success' => true, 'message' => 'Product added successfully.']
            : ['success' => false, 'errors' => ['Failed to save product.']];
    }

    public function update(string $id, array $data, array $files = []): array {
        $existing = $this->repo->findById($id);
        if (!$existing) return ['success' => false, 'errors' => ['Product not found.']];
        $errors = Product::validate($data);
        if ($errors) return ['success' => false, 'errors' => $errors];

        $imagePath = $existing['image'] ?? '';
        if (!empty($files['image']['tmp_name'])) {
            $result = $this->handleImageUpload($files['image']);
            if ($result['success']) $imagePath = $result['path'];
        }

        $record = [
            'id'                  => $id,
            'name'                => trim($data['name']),
            'category'            => trim($data['category']),
            'price'               => (float)$data['price'],
            'stock'               => (int)$data['stock'],
            'description'         => trim($data['description'] ?? ''),
            'supplier'            => trim($data['supplier'] ?? ''),
            'low_stock_threshold' => (int)($data['low_stock_threshold'] ?? 8),
            'image'               => $imagePath,
        ];

        $stockBefore = (int)$existing['stock'];
        $stockAfter  = (int)$data['stock'];
        $ok = $this->repo->save($record);
        if ($ok && $stockBefore !== $stockAfter) {
            $diff = $stockAfter - $stockBefore;
            $this->logRepo->log($id, $record['name'], 'manual_update', $diff, $stockBefore, $stockAfter, "Manual stock update");
        }
        if ($ok) {
            $this->actLogger->log(ActivityLogger::TYPE_PRODUCT_UPDATE, "Updated product: {$record['name']}", "Price: ₱{$record['price']}, Stock: {$stockAfter}");
        }
        return $ok
            ? ['success' => true, 'message' => 'Product updated successfully.']
            : ['success' => false, 'errors' => ['Failed to update product.']];
    }

    public function adjustStock(string $id, int $newStock, string $note = ''): array {
        $product = $this->repo->findById($id);
        if (!$product) return ['success' => false, 'message' => 'Product not found.'];
        $result = $this->repo->adjustStock($id, $newStock);
        if ($result['success']) {
            $diff = $newStock - $result['before'];
            $this->logRepo->log(
                $id, $product['name'],
                $diff >= 0 ? 'manual_add' : 'manual_remove',
                $diff, $result['before'], $newStock,
                $note ?: 'Manual stock adjustment'
            );
            $this->actLogger->log(ActivityLogger::TYPE_STOCK_ADJUST, "Stock adjusted: {$product['name']}", "Before: {$result['before']}, After: {$newStock}, Change: " . ($diff >= 0 ? "+{$diff}" : $diff));
            // Push low stock notification if threshold crossed
            $threshold = (int)($product['low_stock_threshold'] ?? 8);
            if ($newStock <= $threshold && $newStock > 0) {
                $this->notifMgr->pushStock($product['name'], $newStock);
            }
        }
        return $result;
    }

    public function delete(string $id): array {
        $product = $this->repo->findById($id);
        if (!$product) return ['success' => false, 'message' => 'Product not found.'];
        $ok = $this->repo->delete($id);
        if ($ok) {
            $this->actLogger->log(ActivityLogger::TYPE_PRODUCT_DELETE, "Deleted product: {$product['name']}", "ID: {$id}, Category: {$product['category']}");
        }
        return $ok ? ['success' => true, 'message' => 'Product deleted.'] : ['success' => false, 'message' => 'Failed to delete product.'];
    }

    public function bulkImport(array $file): array {
        if (empty($file['tmp_name'])) return ['success' => false, 'message' => 'No file uploaded.'];
        $content = file_get_contents($file['tmp_name']);
        $items   = json_decode($content, true);
        if (!is_array($items)) return ['success' => false, 'message' => 'Invalid JSON format.'];
        $result = $this->repo->importBulk($items);
        // Log initial stock for each imported product
        if ($result['success'] && $result['added'] > 0) {
            foreach ($items as $item) {
                if (!empty($item['name']) && isset($item['stock']) && (int)$item['stock'] > 0) {
                    $stock = (int)$item['stock'];
                    $this->logRepo->log('imported', $item['name'] ?? 'Unknown', 'import', $stock, 0, $stock, 'Bulk import');
                }
            }
        }
        if ($result['success']) {
            $this->actLogger->log(ActivityLogger::TYPE_IMPORT, "Bulk import: {$result['added']} product(s) added", "Updated: {$result['updated']}");
        }
        return array_merge(['success' => true], $result);
    }

    public function getInventoryLogs(string $productId = ''): array {
        return $productId
            ? $this->logRepo->getByProduct($productId)
            : $this->logRepo->getAll();
    }

    private function handleImageUpload(array $fileData): array {
        if ($fileData['size'] > 2 * 1024 * 1024) {
            return ['success' => false, 'message' => 'Image must be under 2MB.'];
        }
        // Verify actual file content — never trust $_FILES['type'] (client-supplied)
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $allowedExts = ['jpg','jpeg','png','webp','gif'];
        if (function_exists('finfo_open')) {
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $fileData['tmp_name']);
            finfo_close($finfo);
        } else {
            // Fallback: getimagesize() reads file headers
            $imgInfo  = @getimagesize($fileData['tmp_name']);
            $mimeType = $imgInfo ? $imgInfo['mime'] : '';
        }
        if (!in_array($mimeType, $allowedMime)) {
            return ['success' => false, 'message' => 'Invalid image. Use JPG, PNG, WebP, or GIF.'];
        }
        $ext = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) {
            return ['success' => false, 'message' => 'Invalid file extension.'];
        }
        $filename = 'img_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest     = __DIR__ . '/../assets/img/products/' . $filename;
        if (!move_uploaded_file($fileData['tmp_name'], $dest)) {
            return ['success' => false, 'message' => 'Failed to save image. Check folder permissions.'];
        }
        return ['success' => true, 'path' => 'assets/img/products/' . $filename];
    }
}
