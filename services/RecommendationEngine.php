<?php
// services/RecommendationEngine.php
require_once __DIR__ . '/SalesRepository.php';
require_once __DIR__ . '/ProductRepository.php';

class RecommendationEngine {
    private SalesRepository $salesRepo;
    private ProductRepository $productRepo;
    private int $lowStockThreshold = 8;

    public function __construct() {
        $this->salesRepo = new SalesRepository();
        $this->productRepo = new ProductRepository();
    }

    public function getRelatedProducts(array $cartProductIds, int $limit = 4): array {
        if (empty($cartProductIds)) return $this->getBestSellers($limit);

        $pairings = $this->salesRepo->getProductPairings();
        $scores = [];

        foreach ($pairings as $pair => $count) {
            [$a, $b] = explode('|', $pair);
            foreach ($cartProductIds as $id) {
                $other = null;
                if ($a === $id) $other = $b;
                elseif ($b === $id) $other = $a;
                if ($other && !in_array($other, $cartProductIds)) {
                    $scores[$other] = ($scores[$other] ?? 0) + $count;
                }
            }
        }

        arsort($scores);
        $related = [];
        foreach (array_keys($scores) as $pid) {
            $p = $this->productRepo->findById($pid);
            if ($p && $p['stock'] > 0) {
                $related[] = $p;
                if (count($related) >= $limit) break;
            }
        }

        if (count($related) < $limit) {
            $cartCategories = [];
            foreach ($cartProductIds as $pid) {
                $p = $this->productRepo->findById($pid);
                if ($p) $cartCategories[] = $p['category'];
            }
            foreach ($this->productRepo->getAll() as $p) {
                if (!in_array($p['id'], $cartProductIds) && in_array($p['category'], $cartCategories) && $p['stock'] > 0) {
                    $alreadyIn = array_filter($related, fn($r) => $r['id'] === $p['id']);
                    if (!$alreadyIn) {
                        $related[] = $p;
                        if (count($related) >= $limit) break;
                    }
                }
            }
        }

        return $related;
    }

    public function getBestSellers(int $limit = 4): array {
        $top = $this->salesRepo->getTopProducts($limit * 2);
        $result = [];
        foreach ($top as $t) {
            $p = $this->productRepo->findById($t['product_id']);
            if ($p && $p['stock'] > 0) {
                $result[] = $p;
                if (count($result) >= $limit) break;
            }
        }
        return $result;
    }

    public function getLowStockAlerts(): array {
        return array_values(array_filter(
            $this->productRepo->getAll(),
            fn($p) => $p['stock'] <= $this->lowStockThreshold
        ));
    }

    public function getPairingInsights(): array {
        $pairings = $this->salesRepo->getProductPairings();
        $insights = [];
        $count = 0;
        foreach ($pairings as $pair => $freq) {
            if ($freq < 2) break;
            [$a, $b] = explode('|', $pair);
            $pa = $this->productRepo->findById($a);
            $pb = $this->productRepo->findById($b);
            if ($pa && $pb) {
                $insights[] = [
                    'message' => "\"{$pa['name']}\" and \"{$pb['name']}\" are frequently bought together",
                    'frequency' => $freq,
                    'products' => [$pa, $pb]
                ];
                if (++$count >= 3) break;
            }
        }
        return $insights;
    }

    public function getInsightMessage(): string {
        $today = date('Y-m-d');
        $todaySales = $this->salesRepo->getSalesByDate($today);
        $low = $this->getLowStockAlerts();
        $top = $this->salesRepo->getTopProducts(1);

        if (!empty($low)) {
            $names = implode(', ', array_column(array_slice($low, 0, 2), 'name'));
            return "⚠️ Stock alert: $names running low. Consider restocking soon.";
        }
        if (!empty($todaySales)) {
            $revenue = array_sum(array_column($todaySales, 'total'));
            return "📈 Today you've made ₱" . number_format($revenue, 2) . " across " . count($todaySales) . " transaction(s). Keep it up!";
        }
        if (!empty($top)) {
            return "🔥 Best seller today: \"{$top[0]['name']}\" — consider featuring it prominently.";
        }
        return "👋 Welcome! Start by adding products or making your first sale.";
    }
}
