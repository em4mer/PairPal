<?php
// controllers/ReportController.php
require_once __DIR__ . '/../services/SalesRepository.php';
require_once __DIR__ . '/../services/ProductRepository.php';
require_once __DIR__ . '/../services/OrderRepository.php';
require_once __DIR__ . '/../services/InventoryLogRepository.php';
require_once __DIR__ . '/../services/PairPalDataRepository.php';
require_once __DIR__ . '/../services/PairPalEngine.php';
require_once __DIR__ . '/../services/BundleRepository.php';
require_once __DIR__ . '/../services/ActivityLogger.php';
require_once __DIR__ . '/../services/CustomerRepository.php';

class ReportController {
    private SalesRepository        $salesRepo;
    private ProductRepository      $productRepo;
    private PairPalEngine          $engine;
    private OrderRepository        $orderRepo;
    private InventoryLogRepository $logRepo;
    private PairPalDataRepository  $pairRepo;

    public function __construct() {
        $this->salesRepo   = new SalesRepository();
        $this->productRepo = new ProductRepository();
        $this->engine      = new PairPalEngine();
        $this->orderRepo   = new OrderRepository();
        $this->logRepo     = new InventoryLogRepository();
        $this->pairRepo    = new PairPalDataRepository();
        $this->bundleRepo  = new BundleRepository();
        $this->actLogger   = new ActivityLogger();
    }

    public function getSummary(string $from = '', string $to = ''): array {
        $sales = $from
            ? $this->salesRepo->getSalesByDateRange($from, $to)
            : $this->salesRepo->getAll();

        $revenue = array_sum(array_column($sales, 'total'));

        $tally = [];
        foreach ($sales as $s) {
            foreach ($s['items'] as $item) {
                $id = $item['product_id'];
                if (!isset($tally[$id])) $tally[$id] = ['product_id' => $id, 'name' => $item['name'], 'qty' => 0, 'revenue' => 0];
                $tally[$id]['qty']     += $item['quantity'];
                $tally[$id]['revenue'] += $item['subtotal'];
            }
        }
        usort($tally, fn($a,$b) => $b['qty'] <=> $a['qty']);

        return [
            'total_revenue'   => $revenue,
            'today_revenue'   => $this->salesRepo->getTodayRevenue(),
            'total_sales'     => count($sales),
            'daily_summary'   => $this->salesRepo->getDailySummary($from, $to),
            'weekly_summary'  => $this->salesRepo->getWeeklySummary(),
            'monthly_summary' => $this->salesRepo->getMonthlySummary(),
            'top_products'    => array_slice($tally, 0, 10),
            'low_stock'       => $this->engine->getLowStockAlerts(),
            'discount_stats'  => $this->salesRepo->getDiscountStats(),
            'date_from'       => $from,
            'date_to'         => $to,
        ];
    }

    public function exportCSV(string $from = '', string $to = ''): void {
        $summary  = $this->getSummary($from, $to);
        $dateTag  = $from ? "{$from}_to_{$to}" : date('Ymd');
        $dateRange = $from ? "$from to $to" : 'All Time';

        // Pull extra data not in getSummary
        $orders    = $from
            ? array_filter($this->orderRepo->getAll(), function($o) use ($from, $to) {
                $d = substr($o['created_at'] ?? '', 0, 10);
                return $d >= $from && $d <= $to;
            })
            : $this->orderRepo->getAll();
        $invLogs   = $this->logRepo->getAll();
        $topPairs  = $this->pairRepo->getTopPairs(10);
        $slowMovers= $this->engine->getSlowMovers(10);
        $allProducts = $this->productRepo->getAll();

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"pairpal_report_{$dateTag}.csv\"");
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel UTF-8

        // ── Header ──────────────────────────────────────────────────────
        fputcsv($out, ['=== PAIRPAL SALES REPORT ===', date('Y-m-d H:i:s')]);
        fputcsv($out, ['Date Range', $dateRange]);
        fputcsv($out, []);

        // ── KPI Summary ─────────────────────────────────────────────────
        fputcsv($out, ['--- KEY METRICS ---']);
        fputcsv($out, ['Metric', 'Value']);
        fputcsv($out, ['Total Revenue',          '₱'.number_format($summary['total_revenue'],2)]);
        fputcsv($out, ["Today's Revenue",         '₱'.number_format($summary['today_revenue'],2)]);
        fputcsv($out, ['Total Transactions (POS)',$summary['total_sales']]);
        fputcsv($out, ['Total Discounts Given',   '₱'.number_format($summary['discount_stats']['total_discounts'],2)]);
        fputcsv($out, ['Discounted Transactions', $summary['discount_stats']['discounted_txns']]);
        fputcsv($out, ['Total Customer Orders',   count($orders)]);
        $orderRev = array_sum(array_column(array_values($orders), 'total'));
        fputcsv($out, ['Customer Order Revenue',  '₱'.number_format($orderRev,2)]);
        fputcsv($out, []);

        // ── Daily Summary ────────────────────────────────────────────────
        fputcsv($out, ['--- DAILY SALES SUMMARY ---']);
        fputcsv($out, ['Date','Transactions','Revenue']);
        foreach ($summary['daily_summary'] as $r) {
            fputcsv($out, [$r['date'], $r['count'], number_format($r['total'],2)]);
        }
        fputcsv($out, []);

        // ── Monthly Summary ──────────────────────────────────────────────
        fputcsv($out, ['--- MONTHLY SUMMARY ---']);
        fputcsv($out, ['Month','Transactions','Revenue']);
        foreach ($summary['monthly_summary'] as $r) {
            fputcsv($out, [$r['month'], $r['count'], number_format($r['total'],2)]);
        }
        fputcsv($out, []);

        // ── Top Products ─────────────────────────────────────────────────
        fputcsv($out, ['--- TOP SELLING PRODUCTS (POS) ---']);
        fputcsv($out, ['Rank','Product','Units Sold','Revenue']);
        foreach ($summary['top_products'] as $i => $r) {
            fputcsv($out, [$i+1, $r['name'], $r['qty'], number_format($r['revenue'],2)]);
        }
        fputcsv($out, []);

        // ── Full Transaction Log ─────────────────────────────────────────
        $allSales = $from
            ? $this->salesRepo->getSalesByDateRange($from, $to)
            : $this->salesRepo->getAll();
        fputcsv($out, ['--- FULL TRANSACTION LOG (POS) ---']);
        fputcsv($out, ['Transaction ID','Cashier','Items','Subtotal','Discount','Total','Amount Paid','Change','Date']);
        foreach ($allSales as $s) {
            fputcsv($out, [
                $s['id'],
                $s['cashier_name'] ?? '',
                count($s['items']),
                number_format($s['subtotal'] ?? $s['total'], 2),
                number_format($s['discount_amount'] ?? 0, 2),
                number_format($s['total'], 2),
                number_format($s['amount_paid'] ?? 0, 2),
                number_format($s['change'] ?? 0, 2),
                $s['date'] ?? '',
            ]);
        }
        fputcsv($out, []);

        // ── Customer Orders ──────────────────────────────────────────────
        fputcsv($out, ['--- CUSTOMER ORDERS ---']);
        fputcsv($out, ['Order ID','Tracking','Customer','Contact','Address','Items','Subtotal','Discount','Bundle','Total','Status','Date']);
        foreach ($orders as $o) {
            fputcsv($out, [
                $o['id'],
                $o['tracking_code']     ?? '',
                $o['customer_name']     ?? '',
                $o['customer_contact']  ?? '',
                $o['customer_address']  ?? '',
                count($o['items']),
                number_format($o['subtotal']       ?? $o['total'], 2),
                number_format($o['discount_amount'] ?? 0, 2),
                $o['bundle_applied'] ?? '',
                number_format($o['total'], 2),
                $o['status'] ?? '',
                substr($o['created_at'] ?? '', 0, 10),
            ]);
        }
        fputcsv($out, []);

        // ── Product Inventory Snapshot ───────────────────────────────────
        fputcsv($out, ['--- CURRENT INVENTORY SNAPSHOT ---']);
        fputcsv($out, ['Product','Category','Supplier','Price','Stock','Low Stock Threshold','Status']);
        foreach ($allProducts as $p) {
            $thr    = $p['low_stock_threshold'] ?? 8;
            $status = $p['stock'] <= 0 ? 'Out of Stock' : ($p['stock'] <= $thr ? 'Low Stock' : 'In Stock');
            fputcsv($out, [
                $p['name'], $p['category'], $p['supplier'] ?? '', number_format($p['price'],2),
                $p['stock'], $thr, $status,
            ]);
        }
        fputcsv($out, []);

        // ── Low Stock ────────────────────────────────────────────────────
        fputcsv($out, ['--- LOW STOCK ALERTS ---']);
        fputcsv($out, ['Product','Category','Supplier','Stock','Threshold','Status']);
        foreach ($summary['low_stock'] as $p) {
            $thr  = $p['low_stock_threshold'] ?? 8;
            $crit = $p['stock'] <= max(1, intval($thr * 0.5)) ? 'Critical' : 'Low';
            fputcsv($out, [$p['name'], $p['category'], $p['supplier'] ?? '', $p['stock'], $thr, $crit]);
        }
        fputcsv($out, []);

        // ── Slow Movers ──────────────────────────────────────────────────
        fputcsv($out, ['--- SLOW-MOVING PRODUCTS (≤2 sales in 30 days) ---']);
        fputcsv($out, ['Product','Category','Stock','Sales (30d)','Suggestion']);
        foreach ($slowMovers as $sm) {
            $p = $sm['product'];
            fputcsv($out, [$p['name'], $p['category'], $p['stock'], $sm['recent_sales'],
                $sm['recent_sales'] === 0 ? 'Bundle or Discount' : 'Promote']);
        }
        fputcsv($out, []);

        // ── PairPal Product Pairs ────────────────────────────────────────
        fputcsv($out, ['--- PAIRPAL: TOP PRODUCT PAIRS ---']);
        fputcsv($out, ['Product A','Product B','Times Paired Together']);
        foreach ($topPairs as $pair) {
            [$aId, $bId] = explode('|', $pair['pair']);
            $pa = $this->productRepo->findById($aId);
            $pb = $this->productRepo->findById($bId);
            if ($pa && $pb) {
                fputcsv($out, [$pa['name'], $pb['name'], $pair['count']]);
            }
        }
        fputcsv($out, []);

        // ── Inventory Movement Log ───────────────────────────────────────
        fputcsv($out, ['--- INVENTORY MOVEMENT LOG ---']);
        fputcsv($out, ['Date','Product','Change Type','Qty Changed','Stock Before','Stock After','Note','User']);
        foreach (array_slice($invLogs, 0, 200) as $log) {
            fputcsv($out, [
                $log['date']              ?? $log['created_at'] ?? '',
                $log['product_name']      ?? '',
                $log['change_type']       ?? '',
                $log['quantity_changed']  ?? 0,
                $log['stock_before']      ?? '',
                $log['stock_after']       ?? '',
                $log['note']              ?? '',
                $log['user_id']           ?? '',
            ]);
        }

        // ── Bundle Performance ───────────────────────────────────────────
        $bundles = $this->bundleRepo->getAll();
        fputcsv($out, []);
        fputcsv($out, ['--- BUNDLE PERFORMANCE ---']);
        fputcsv($out, ['Bundle Name','Products','Discount','Original Price','Bundle Price','Savings','Frequency','Status']);
        foreach ($bundles as $b) {
            $productNames = implode(' + ', $b['product_names'] ?? $b['product_ids'] ?? []);
            $discount = ($b['discount_type'] ?? '') === 'percent'
                ? ($b['discount_value'] ?? 0) . '%'
                : '₱' . ($b['discount_value'] ?? 0);
            fputcsv($out, [
                $b['name']           ?? '',
                $productNames,
                $discount,
                number_format($b['original_price'] ?? 0, 2),
                number_format($b['bundle_price']   ?? 0, 2),
                number_format($b['discount_amount'] ?? $b['savings'] ?? 0, 2),
                $b['frequency']      ?? 0,
                $b['status']         ?? 'active',
            ]);
        }

        // ── Activity Log ─────────────────────────────────────────────────
        $activityLogs = $this->actLogger->getRecent(200);
        fputcsv($out, []);
        fputcsv($out, ['--- ACTIVITY LOG (last 200 entries) ---']);
        fputcsv($out, ['Date/Time','Type','Action','Detail','User']);
        foreach ($activityLogs as $log) {
            fputcsv($out, [
                $log['created_at'] ?? '',
                $log['type']       ?? '',
                $log['action']     ?? '',
                $log['detail']     ?? '',
                $log['user_name']  ?? '',
            ]);
        }

        // ── Customer Summary ─────────────────────────────────────────────

        fputcsv($out, []);
        fputcsv($out, ['--- CUSTOMER SUMMARY ---']);
        fputcsv($out, ['Customer ID','Name','Email','Contact','Registered','Last Login','Total Orders','Total Spent']);
        $custFile = __DIR__ . '/../data/customers.json';
        $customers = is_readable($custFile) ? json_decode(file_get_contents($custFile), true) ?? [] : [];
        foreach ($customers as $cust) {
            $custOrders  = $this->orderRepo->getByCustomer($cust['id']);
            $totalSpent  = array_sum(array_column($custOrders, 'total'));
            fputcsv($out, [
                $cust['id'],
                $cust['name']        ?? '',
                $cust['email']       ?? '',
                $cust['contact']     ?? '',
                substr($cust['created_at'] ?? '', 0, 10),
                substr($cust['last_login']  ?? 'Never', 0, 10),
                count($custOrders),
                number_format($totalSpent, 2),
            ]);
        }

        fclose($out);
        exit;
    }
}
