<?php
// services/ReviewRepository.php
require_once __DIR__ . '/FileHandler.php';

class ReviewRepository extends FileHandler {
    public function __construct() {
        parent::__construct(__DIR__ . '/../data/reviews.json');
    }

    public function getAll(): array { return $this->readAll(); }

    public function findById(string $id): ?array {
        foreach ($this->readAll() as $r) { if ($r['id'] === $id) return $r; }
        return null;
    }

    public function getByProduct(string $productId): array {
        $reviews = array_values(array_filter($this->readAll(), fn($r) => $r['product_id'] === $productId));
        usort($reviews, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
        return $reviews;
    }

    public function getAverageRating(string $productId): float {
        $reviews = $this->getByProduct($productId);
        if (empty($reviews)) return 0.0;
        return round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1);
    }

    public function getRatingSummary(string $productId): array {
        $reviews = $this->getByProduct($productId);
        $dist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($reviews as $r) { $dist[(int)$r['rating']]++; }
        return [
            'count'    => count($reviews),
            'average'  => $this->getAverageRating($productId),
            'distribution' => $dist,
        ];
    }

    public function save(array $record): bool {
        $all   = $this->readAll();
        $found = false;
        foreach ($all as &$r) { if ($r['id'] === $record['id']) { $r = $record; $found = true; break; } }
        if (!$found) $all[] = $record;
        return $this->writeAll($all);
    }

    public function delete(string $id): bool {
        $all = array_filter($this->readAll(), fn($r) => $r['id'] !== $id);
        return $this->writeAll(array_values($all));
    }

    public function generateId(): string { return 'rev_' . uniqid(); }
}
