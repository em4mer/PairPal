<?php
// services/SalesRepository.php
require_once __DIR__ . '/FileHandler.php';

class SalesRepository extends FileHandler {
    public function __construct() {
        parent::__construct(__DIR__ . '/../data/sales.json');
    }

    public function getAll(): array {
        $sales = $this->readAll();
        usort($sales, fn($a, $b) => strcmp($b['date'], $a['date']));
        return $sales;
    }

    public function findById(string $id): ?array {
        foreach ($this->readAll() as $s) {
            if ($s['id'] === $id) return $s;
        }
        return null;
    }

    public function save(array $record): bool {
        $sales = $this->readAll();
        $found = false;
        foreach ($sales as &$s) {
            if ($s['id'] === $record['id']) { $s = $record; $found = true; break; }
        }
        if (!$found) $sales[] = $record;
        return $this->writeAll($sales);
    }

    public function delete(string $id): bool {
        $sales = array_filter($this->readAll(), fn($s) => $s['id'] !== $id);
        return $this->writeAll(array_values($sales));
    }

    public function generateId(): string {
        $sales = $this->readAll();
        $max = 0;
        foreach ($sales as $s) {
            if (preg_match('/txn_(\d+)/', $s['id'], $m)) $max = max($max, (int)$m[1]);
        }
        return 'txn_' . str_pad($max + 1, 3, '0', STR_PAD_LEFT);
    }

    public function getSalesByDate(string $date): array {
        return array_values(array_filter($this->readAll(), fn($s) => str_starts_with($s['date'], $date)));
    }

    public function getSalesByDateRange(string $from, string $to): array {
        $filtered = array_values(array_filter($this->readAll(), function($s) use ($from, $to) {
            $d = substr($s['date'], 0, 10);
            return $d >= $from && $d <= $to;
        }));
        usort($filtered, fn($a, $b) => strcmp($b['date'], $a['date']));
        return $filtered;
    }

    public function getDailySummary(string $from = '', string $to = ''): array {
        $summary = [];
        $sales = $from ? $this->getSalesByDateRange($from, $to) : $this->getAll();
        foreach ($sales as $sale) {
            $day = substr($sale['date'], 0, 10);
            if (!isset($summary[$day])) $summary[$day] = ['date' => $day, 'total' => 0, 'count' => 0];
            $summary[$day]['total'] += $sale['total'];
            $summary[$day]['count']++;
        }
        krsort($summary);
        return array_values($summary);
    }

    public function getWeeklySummary(): array {
        $weeks = [];
        foreach ($this->getAll() as $sale) {
            $weekKey = date('Y-W', strtotime($sale['date']));
            if (!isset($weeks[$weekKey])) $weeks[$weekKey] = ['week' => $weekKey, 'total' => 0, 'count' => 0];
            $weeks[$weekKey]['total'] += $sale['total'];
            $weeks[$weekKey]['count']++;
        }
        krsort($weeks);
        return array_slice(array_values($weeks), 0, 12);
    }

    public function getMonthlySummary(): array {
        $months = [];
        foreach ($this->getAll() as $sale) {
            $mKey = substr($sale['date'], 0, 7);
            if (!isset($months[$mKey])) $months[$mKey] = ['month' => $mKey, 'total' => 0, 'count' => 0];
            $months[$mKey]['total'] += $sale['total'];
            $months[$mKey]['count']++;
        }
        krsort($months);
        return array_slice(array_values($months), 0, 12);
    }

    public function getTopProducts(int $limit = 5): array {
        $tally = [];
        foreach ($this->readAll() as $sale) {
            foreach ($sale['items'] as $item) {
                $id = $item['product_id'];
                if (!isset($tally[$id])) $tally[$id] = ['product_id' => $id, 'name' => $item['name'], 'qty' => 0, 'revenue' => 0];
                $tally[$id]['qty']     += $item['quantity'];
                $tally[$id]['revenue'] += $item['subtotal'];
            }
        }
        usort($tally, fn($a, $b) => $b['qty'] <=> $a['qty']);
        return array_slice(array_values($tally), 0, $limit);
    }

    /** @deprecated Use PairPalEngine / PairPalDataRepository instead */
    public function getProductPairings(): array {
        $pairs = [];
        foreach ($this->readAll() as $sale) {
            $ids = array_column($sale['items'], 'product_id');
            sort($ids);
            for ($i = 0; $i < count($ids); $i++) {
                for ($j = $i+1; $j < count($ids); $j++) {
                    $key = $ids[$i] . '|' . $ids[$j];
                    $pairs[$key] = ($pairs[$key] ?? 0) + 1;
                }
            }
        }
        arsort($pairs);
        return $pairs;
    }

    public function getTotalRevenue(): float { return array_sum(array_column($this->readAll(), 'total')); }
    public function getTodayRevenue(): float { return array_sum(array_column($this->getSalesByDate(date('Y-m-d')), 'total')); }

    public function getDiscountStats(): array {
        $total = 0; $count = 0;
        foreach ($this->readAll() as $s) {
            $da = (float)($s['discount_amount'] ?? 0);
            if ($da > 0) { $total += $da; $count++; }
        }
        return ['total_discounts' => $total, 'discounted_txns' => $count];
    }

    public function getByCashier(string $cashierId): array {
        return array_values(array_filter(
            $this->getAll(),  // getAll() already sorts newest-first
            fn($s) => ($s['cashier_id'] ?? '') === $cashierId
        ));
    }

    public function getByCashierAndDateRange(string $cashierId, string $from, string $to): array {
        return array_values(array_filter(
            $this->getSalesByDateRange($from, $to),
            fn($s) => ($s['cashier_id'] ?? '') === $cashierId
        ));
    }

}