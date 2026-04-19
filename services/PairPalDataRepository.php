<?php
// services/PairPalDataRepository.php
require_once __DIR__ . '/FileHandler.php';

class PairPalDataRepository extends FileHandler {
    public function __construct() {
        parent::__construct(__DIR__ . '/../data/pairpal_data.json');
    }

    public function getAll(): array {
        $data = $this->readAll();
        // stored as array of objects: {pair, count, last_seen}
        return $data;
    }

    public function findById(string $id): ?array {
        foreach ($this->readAll() as $r) {
            if (($r['pair'] ?? '') === $id) return $r;
        }
        return null;
    }

    public function save(array $record): bool {
        $all = $this->readAll();
        $found = false;
        foreach ($all as &$r) {
            if ($r['pair'] === $record['pair']) { $r = $record; $found = true; break; }
        }
        if (!$found) $all[] = $record;
        return $this->writeAll($all);
    }

    public function delete(string $id): bool {
        $all = array_filter($this->readAll(), fn($r) => $r['pair'] !== $id);
        return $this->writeAll(array_values($all));
    }

    /** Rebuild the entire pair frequency table from a sales array */
    public function rebuildFromSales(array $allSales): bool {
        $pairs = [];
        foreach ($allSales as $sale) {
            $ids = array_column($sale['items'], 'product_id');
            sort($ids);
            for ($i = 0; $i < count($ids); $i++) {
                for ($j = $i + 1; $j < count($ids); $j++) {
                    $key = $ids[$i] . '|' . $ids[$j];
                    if (!isset($pairs[$key])) {
                        $pairs[$key] = ['pair' => $key, 'count' => 0, 'last_seen' => ''];
                    }
                    $pairs[$key]['count']++;
                    $pairs[$key]['last_seen'] = $sale['date'] ?? date('c');
                }
            }
        }
        usort($pairs, fn($a, $b) => $b['count'] <=> $a['count']);
        return $this->writeAll(array_values($pairs));
    }

    /** Increment pairs from a single transaction's item list */
    public function incrementFromItems(array $itemProductIds, string $saleDate): bool {
        $all = $this->readAll();
        $map = [];
        foreach ($all as $r) $map[$r['pair']] = $r;

        sort($itemProductIds);
        for ($i = 0; $i < count($itemProductIds); $i++) {
            for ($j = $i + 1; $j < count($itemProductIds); $j++) {
                $key = $itemProductIds[$i] . '|' . $itemProductIds[$j];
                if (!isset($map[$key])) {
                    $map[$key] = ['pair' => $key, 'count' => 0, 'last_seen' => ''];
                }
                $map[$key]['count']++;
                $map[$key]['last_seen'] = $saleDate;
            }
        }
        $result = array_values($map);
        usort($result, fn($a, $b) => $b['count'] <=> $a['count']);
        return $this->writeAll($result);
    }

    public function getTopPairs(int $limit = 10): array {
        $all = $this->getAll();
        usort($all, fn($a, $b) => $b['count'] <=> $a['count']);
        return array_slice($all, 0, $limit);
    }

    public function getPairsMap(): array {
        $map = [];
        foreach ($this->readAll() as $r) {
            $map[$r['pair']] = $r['count'];
        }
        return $map;
    }
}
