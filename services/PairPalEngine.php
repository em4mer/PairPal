<?php
require_once __DIR__ . '/SalesRepository.php';
require_once __DIR__ . '/ProductRepository.php';
require_once __DIR__ . '/PairPalDataRepository.php';
require_once __DIR__ . '/BundleRepository.php';

class PairPalEngine {
    private SalesRepository       $salesRepo;
    private ProductRepository     $productRepo;
    private PairPalDataRepository $pairRepo;
    private BundleRepository      $bundleRepo;

    private const BUNDLE_FREQ_MIN      = 2;
    private const BUNDLE_DISCOUNT_PCT  = 8;
    private const CATEGORY_DISCOUNT    = 5;
    private const SLOW_MOVER_DAYS      = 30;
    private const SLOW_MOVER_MAX_SALES = 2;

    public function __construct() {
        $this->salesRepo   = new SalesRepository();
        $this->productRepo = new ProductRepository();
        $this->pairRepo    = new PairPalDataRepository();
        $this->bundleRepo  = new BundleRepository();
    }

    public function detectCartBundle(array $cartProductIds): ?array {
        if (count($cartProductIds) < 2) return null;
        $matched = $this->bundleRepo->findMatchingBundles($cartProductIds);
        return $matched[0] ?? null;
    }

    public function evaluateCartDiscount(array $cartProductIds, float $subtotal): array {
        $bundle = $this->detectCartBundle($cartProductIds);
        if (!$bundle) return ['bundle' => null,'discount_type'=>'none','discount_value'=>0,'discount_amount'=>0,'message'=>'','savings'=>0];
        $dtype  = $bundle['discount_type']  ?? 'percent';
        $dvalue = (float)($bundle['discount_value'] ?? self::BUNDLE_DISCOUNT_PCT);
        $discountAmount = ($dtype === 'percent') ? round($subtotal * ($dvalue/100), 2) : min($dvalue, $subtotal);
        $label   = $dtype === 'percent' ? "{$dvalue}% off" : "₱{$dvalue} off";
        $message = "🎁 Bundle discount applied: \"{$bundle['name']}\" — {$label}";
        return ['bundle'=>$bundle,'discount_type'=>$dtype,'discount_value'=>$dvalue,'discount_amount'=>$discountAmount,'message'=>$message,'savings'=>$discountAmount];
    }

    public function getUpsellPrompts(array $cartProductIds): array {
        $prompts = [];
        foreach ($this->bundleRepo->getActive() as $bundle) {
            $bIds    = $bundle['product_ids'] ?? [];
            $missing = array_diff($bIds, $cartProductIds);
            $present = array_intersect($bIds, $cartProductIds);
            if (count($missing) === 1 && count($present) >= 1) {
                $missingId = array_values($missing)[0];
                $product   = $this->productRepo->findById($missingId);
                if ($product && $product['stock'] > 0) {
                    $dtype  = $bundle['discount_type']  ?? 'percent';
                    $dvalue = $bundle['discount_value'] ?? self::BUNDLE_DISCOUNT_PCT;
                    $label  = $dtype === 'percent' ? "{$dvalue}% off" : "₱{$dvalue} off";
                    $prompts[] = ['product'=>$product,'bundle'=>$bundle,'message'=>"Add \"{$product['name']}\" to unlock \"{$bundle['name']}\" ({$label})!",'discount'=>$label];
                }
            }
        }
        return array_slice($prompts, 0, 3);
    }

    public function getCartSuggestions(array $cartProductIds, int $limit = 4): array {
        if (empty($cartProductIds)) return $this->getBestSellers($limit);
        $pairsMap = $this->pairRepo->getPairsMap();
        $scores   = [];
        foreach ($pairsMap as $pair => $count) {
            [$a,$b] = explode('|', $pair);
            foreach ($cartProductIds as $id) {
                $other = ($a===$id) ? $b : (($b===$id) ? $a : null);
                if ($other && !in_array($other,$cartProductIds)) $scores[$other] = ($scores[$other]??0)+$count;
            }
        }
        arsort($scores);
        $related = [];
        foreach (array_keys($scores) as $pid) {
            $p = $this->productRepo->findById($pid);
            if ($p && $p['stock']>0) { $p['_reason']='Frequently bought together'; $p['_score']=$scores[$pid]; $related[]=$p; if (count($related)>=$limit) break; }
        }
        if (count($related)<$limit) {
            $cats = []; foreach ($cartProductIds as $pid) { $p=$this->productRepo->findById($pid); if ($p) $cats[]=$p['category']; }
            $inR = array_column($related,'id');
            foreach ($this->productRepo->getAll() as $p) {
                if (!in_array($p['id'],$cartProductIds)&&!in_array($p['id'],$inR)&&in_array($p['category'],$cats)&&$p['stock']>0) {
                    $p['_reason']='Same category'; $related[]=$p; if (count($related)>=$limit) break;
                }
            }
        }
        return $related;
    }

    public function regenerateBundles(): int {
        $pairsMap  = $this->pairRepo->getPairsMap();
        $generated = 0;
        foreach ($pairsMap as $pair => $freq) {
            if ($freq < self::BUNDLE_FREQ_MIN) continue;
            [$aId,$bId] = explode('|',$pair);
            $pa = $this->productRepo->findById($aId);
            $pb = $this->productRepo->findById($bId);
            if (!$pa||!$pb) continue;
            $orig = $pa['price']+$pb['price'];
            $pct  = $pa['category']===$pb['category'] ? self::CATEGORY_DISCOUNT : self::BUNDLE_DISCOUNT_PCT;
            $disc = round($orig*$pct/100,2);
            $name = ($pa['category']===$pb['category']) ? "{$pa['category']} Duo — {$pa['name']} + {$pb['name']}" : "{$pa['name']} + {$pb['name']}";
            $this->bundleRepo->upsertByProducts([$aId,$bId],['name'=>$name,'product_ids'=>[$aId,$bId],'product_names'=>[$pa['name'],$pb['name']],'frequency'=>$freq,'original_price'=>$orig,'discount_type'=>'percent','discount_value'=>$pct,'discount_amount'=>$disc,'savings'=>$disc,'bundle_price'=>round($orig-$disc,2),'status'=>'active','auto_generated'=>true]);
            $generated++;
        }
        foreach ($this->getSlowMovers(5) as $sm) {
            $p = $sm['product'];
            $best = $this->getBestSellerInCategory($p['category'],[$p['id']]);
            if (!$best) continue;
            $orig = $p['price']+$best['price']; $disc = round($orig*10/100,2);
            $this->bundleRepo->upsertByProducts([$p['id'],$best['id']],['name'=>"Promo — {$p['name']} + {$best['name']}",'product_ids'=>[$p['id'],$best['id']],'product_names'=>[$p['name'],$best['name']],'frequency'=>1,'original_price'=>$orig,'discount_type'=>'percent','discount_value'=>10,'discount_amount'=>$disc,'savings'=>$disc,'bundle_price'=>round($orig-$disc,2),'status'=>'active','auto_generated'=>true,'promo_type'=>'slow_mover']);
            $generated++;
        }
        return $generated;
    }

    private function getBestSellerInCategory(string $cat, array $excludeIds=[]): ?array {
        foreach ($this->salesRepo->getTopProducts(50) as $t) {
            if (in_array($t['product_id'],$excludeIds)) continue;
            $p = $this->productRepo->findById($t['product_id']);
            if ($p && $p['category']===$cat && $p['stock']>0) return $p;
        }
        foreach ($this->productRepo->getAll() as $p) {
            if ($p['category']===$cat && !in_array($p['id'],$excludeIds) && $p['stock']>0) return $p;
        }
        return null;
    }

    public function getSmartBundles(int $limit=3): array {
        $result = [];
        foreach ($this->bundleRepo->getActive() as $b) {
            $products = array_filter(array_map(fn($pid)=>$this->productRepo->findById($pid), $b['product_ids']??[]), fn($p)=>$p&&$p['stock']>0);
            if (count($products)===count($b['product_ids']??[])) { $result[] = array_merge($b,['products'=>array_values($products)]); if (count($result)>=$limit) break; }
        }
        return $result;
    }
    public function getActiveBundlesForDisplay(int $limit=6): array { return $this->getSmartBundles($limit); }

    public function getBestSellers(int $limit=4): array {
        $top=$this->salesRepo->getTopProducts($limit*2); $result=[];
        foreach ($top as $t) { $p=$this->productRepo->findById($t['product_id']); if ($p&&$p['stock']>0) { $p['_sales_qty']=$t['qty']; $result[]=$p; if (count($result)>=$limit) break; } }
        return $result;
    }

    public function getTrendingProducts(int $limit=6): array {
        $cutoff = date('Y-m-d',strtotime('-14 days'));
        $recent = array_filter($this->salesRepo->getAll(),fn($s)=>substr($s['date'],0,10)>=$cutoff);
        $tally  = [];
        foreach ($recent as $sale) foreach ($sale['items'] as $item) $tally[$item['product_id']]=($tally[$item['product_id']]??0)+$item['quantity'];
        arsort($tally);
        $result=[];
        foreach (array_keys($tally) as $pid) { $p=$this->productRepo->findById($pid); if ($p&&$p['stock']>0) { $p['_trending_qty']=$tally[$pid]; $result[]=$p; if (count($result)>=$limit) break; } }
        return $result;
    }

    public function getFeaturedProducts(int $limit=4): array {
        $best    = $this->getBestSellers($limit*2);
        $slowIds = array_column(array_map(fn($s)=>$s['product'],$this->getSlowMovers(20)),'id');
        return array_slice(array_values(array_filter($best,fn($p)=>!in_array($p['id'],$slowIds))),0,$limit);
    }

    public function getLowStockAlerts(): array {
        return array_values(array_filter($this->productRepo->getAll(),fn($p)=>$p['stock']<=($p['low_stock_threshold']??8)));
    }

    public function getRestockSuggestions(int $limit=5): array {
        $topMap=[];
        foreach ($this->salesRepo->getTopProducts(50) as $t) $topMap[$t['product_id']]=$t['qty'];
        $suggestions=[];
        foreach ($this->productRepo->getAll() as $p) {
            $thr=$p['low_stock_threshold']??8; $sq=$topMap[$p['id']]??0; $score=$sq/($p['stock']+1);
            if ($p['stock']<=$thr*2) $suggestions[]=['product'=>$p,'sales_qty'=>$sq,'score'=>round($score,3),'urgency'=>$p['stock']<=max(1,intval($thr*0.5))?'critical':($p['stock']<=$thr?'high':'medium')];
        }
        usort($suggestions,fn($a,$b)=>$b['score']<=>$a['score']);
        return array_slice($suggestions,0,$limit);
    }

    public function getSlowMovers(int $limit=5): array {
        $cutoff=date('Y-m-d',strtotime('-'.self::SLOW_MOVER_DAYS.' days'));
        $recent=array_filter($this->salesRepo->getAll(),fn($s)=>substr($s['date'],0,10)>=$cutoff);
        $map=[];
        foreach ($recent as $sale) foreach ($sale['items'] as $item) $map[$item['product_id']]=($map[$item['product_id']]??0)+$item['quantity'];
        $slow=[];
        foreach ($this->productRepo->getAll() as $p) {
            $thr=$p['low_stock_threshold']??8; $qty=$map[$p['id']]??0;
            if ($p['stock']>$thr && $qty<=self::SLOW_MOVER_MAX_SALES)
                $slow[]=['product'=>$p,'recent_sales'=>$qty,'days_window'=>self::SLOW_MOVER_DAYS,'suggestion'=>$qty===0?'No sales in 30 days — consider a discount or bundle':'Low movement — try bundling with a popular item'];
        }
        usort($slow,fn($a,$b)=>$a['recent_sales']<=>$b['recent_sales']);
        return array_slice($slow,0,$limit);
    }

    public function getPairingInsights(int $limit=5): array {
        $topPairs=$this->pairRepo->getTopPairs($limit*2); $insights=[];
        foreach ($topPairs as $pair) {
            if ($pair['count']<2) break;
            [$aId,$bId]=explode('|',$pair['pair']);
            $pa=$this->productRepo->findById($aId); $pb=$this->productRepo->findById($bId);
            if ($pa&&$pb) { $insights[]=['message'=>"\"{$pa['name']}\" and \"{$pb['name']}\" are frequently bought together",'frequency'=>$pair['count'],'products'=>[$pa,$pb]]; if (count($insights)>=$limit) break; }
        }
        return $insights;
    }

    public function getSalesInsights(): array {
        $allSales=$this->salesRepo->getAll(); $dayTotals=array_fill(0,7,['total'=>0,'count'=>0]);
        $dayNames=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        foreach ($allSales as $s) { $dow=(int)date('w',strtotime($s['date'])); $dayTotals[$dow]['total']+=$s['total']; $dayTotals[$dow]['count']++; }
        $peakIdx=0; $peakCount=0;
        foreach ($dayTotals as $i=>$d) { if ($d['count']>$peakCount) { $peakCount=$d['count']; $peakIdx=$i; } }
        $wStart=date('Y-m-d',strtotime('last monday')); $mStart=date('Y-m-01'); $wt=$mt=0;
        foreach ($allSales as $s) { $d=substr($s['date'],0,10); if ($d>=$wStart) $wt+=$s['total']; if ($d>=$mStart) $mt+=$s['total']; }
        return ['top_products'=>$this->salesRepo->getTopProducts(5),'top_pairs'=>$this->pairRepo->getTopPairs(5),'peak_day'=>$dayNames[$peakIdx],'peak_day_count'=>$peakCount,'day_totals'=>array_map(fn($i,$d)=>array_merge($d,['name'=>$dayNames[$i]]),array_keys($dayTotals),$dayTotals),'weekly_revenue'=>$wt,'monthly_revenue'=>$mt,'slow_movers'=>$this->getSlowMovers(3),'restock'=>$this->getRestockSuggestions(3)];
    }

    public function getInsightMessage(): string {
        $low=$this->getLowStockAlerts(); $slow=$this->getSlowMovers(1); $top=$this->salesRepo->getTopProducts(1);
        $todaySales=$this->salesRepo->getSalesByDate(date('Y-m-d'));
        if (!empty($low)) { $crit=array_filter($low,fn($p)=>$p['stock']<=max(1,intval(($p['low_stock_threshold']??8)*0.5))); if (!empty($crit)) { $p=array_values($crit)[0]; return "🚨 Critical: \"{$p['name']}\" has only {$p['stock']} left — restock now!"; } return "⚠️ Stock alert: ".implode(', ',array_column(array_slice($low,0,2),'name'))." running low."; }
        if (!empty($slow)) return "🐢 \"{$slow[0]['product']['name']}\" is slow-moving — consider a discount or bundle.";
        if (!empty($todaySales)) { $r=array_sum(array_column($todaySales,'total')); return "📈 Today: ₱".number_format($r,2)." across ".count($todaySales)." transaction(s). Keep it up!"; }
        if (!empty($top)) return "🔥 Best seller: \"{$top[0]['name']}\" — {$top[0]['qty']} units sold.";
        return "👋 Welcome to PairPal!";
    }

    public function getProductPopularityMap(): array {
        $map=[];
        foreach ($this->salesRepo->getTopProducts(100) as $t) $map[$t['product_id']]=$t['qty'];
        return $map;
    }

    public function updateAfterTransaction(array $itemProductIds, string $saleDate): void {
        $this->pairRepo->incrementFromItems($itemProductIds,$saleDate);
        if (count($itemProductIds)>=2) $this->regenerateBundles();
    }

    public function getRelatedProducts(array $cartProductIds, int $limit=4): array { return $this->getCartSuggestions($cartProductIds,$limit); }
}
