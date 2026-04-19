<?php
// services/PairPalChatService.php
require_once __DIR__ . '/ProductRepository.php';
require_once __DIR__ . '/PairPalEngine.php';
require_once __DIR__ . '/OrderRepository.php';

class PairPalChatService {
    private ProductRepository $productRepo;
    private PairPalEngine     $engine;
    private OrderRepository   $orderRepo;

    private array $intents = [
        'greeting'      => ['hi','hello','hey','good morning','good afternoon','good evening','howdy','sup','what\'s up','yo','hiya','greetings','helo','hellow','hii'],
        'capabilities'  => ['what can you do','help me','what do you know','capabilities','features','how can you help','what are you','who are you','what is pairpal','tell me about yourself'],
        'how_to_order'  => ['how to order','how do i order','place order','how to buy','ordering','steps to buy','purchase process','how do i purchase','buying process','make an order','create order','submit order','how to order?','how do i order?'],
        'delivery'      => ['delivery','deliver','shipping','ship','how long','when will','arrive','arrival','days','estimated','how many days','lead time','dispatch','courier','receive','get my order'],
        'payment'       => ['payment','pay','cash','gcash','bank transfer','card','mode of payment','how to pay','payment method','payment options','do you accept','bayad','magbayad'],
        'tracking'      => ['track','tracking','where is my order','order status','check order','find order','my order','check my order','delivery status','shipment status','track my'],
        'return'        => ['return','refund','exchange','wrong item','damaged','broken','defective','complaint','wrong product','not what i ordered','replacement','money back'],
        'bestseller'    => ['best seller','bestseller','popular','trending','top product','most bought','hot item','most popular','what sells most','top selling','most ordered','what\'s hot','show me best sellers','best sellers','top sellers','what\'s popular'],
        'bundle'        => ['bundle','deal','combo','discount','save','savings','promo','offer','bundle deal','package deal','special offer','what bundles','available bundles','see bundle deals','bundle deals'],
        'categories'    => ['category','categories','types','kinds','what do you sell','what products','what items','product types','product list','all products','your products','browse categories'],
        'search'        => ['looking for','find','search','do you have','got any','got','sell','stock','available','is there','any','have you got'],
        'recommendation'=> ['recommend','suggestion','suggest','what should i buy','what should i get','for me','good product','what to buy','what to get','give me recommendations','i\'m looking','recommend something','recommended for you','you might like'],
        'account'       => ['account','login','sign in','signup','register','profile','my account','create account','log in','sign up','my profile'],
        'wishlist'      => ['wishlist','wish list','save item','save product','favorite','favourites','saved items','heart','liked'],
        'contact'       => ['contact','reach','call','email','support','talk to someone','speak to','customer service','helpdesk','contact us','contact support'],
        'stock'         => ['in stock','available','out of stock','how many','stock level','is it available','quantity'],
        'price'         => ['price','cost','how much','expensive','cheap','affordable','rate','pricing','how much is','magkano'],
        'farewell'      => ['bye','goodbye','thanks','thank you','cheers','see you','later','done','ok thanks','okay bye','take care','that\'s all','that will be all','no more questions'],
        'casual_yes'    => ['yes','yeah','yep','sure','okay','ok','alright','of course','definitely','yup'],
        'casual_no'     => ['no','nope','nah','not really','nevermind','never mind','cancel','skip'],
        'funny'         => ['joke','funny','laugh','haha','lol','are you human','are you a robot','are you ai','are you real'],
        'price_range'   => ['under 100','under 200','under 300','under 500','below 100','below 200','cheap products','affordable products','budget'],
        'new_products'  => ['new','latest','newest','new arrivals','recently added','fresh','new products'],
        'hours'         => ['open','hours','operating hours','business hours','when are you open','store hours','schedule'],
    ];

    public function __construct() {
        $this->productRepo = new ProductRepository();
        $this->engine      = new PairPalEngine();
        $this->orderRepo   = new OrderRepository();
    }

    public function respond(string $message, array $context = []): array {
        if (empty(trim($message))) return $this->replyFallback('');

        $msg    = strtolower(trim($message));
        $intent = $this->detectIntent($msg);

        return match ($intent) {
            'greeting'      => $this->replyGreeting($context),
            'capabilities'  => $this->replyCapabilities(),
            'how_to_order'  => $this->replyHowToOrder(),
            'delivery'      => $this->replyDelivery(),
            'payment'       => $this->replyPayment(),
            'tracking'      => $this->replyTracking($context),
            'return'        => $this->replyReturn(),
            'bestseller'    => $this->replyBestSellers(),
            'bundle'        => $this->replyBundles(),
            'categories'    => $this->replyCategories(),
            'search'        => $this->replySearch($msg),
            'recommendation'=> $this->replyRecommendation($context),
            'account'       => $this->replyAccount($context),
            'wishlist'      => $this->replyWishlist($context),
            'contact'       => $this->replyContact(),
            'stock'         => $this->replyStock($msg),
            'price'         => $this->replyPrice($msg),
            'farewell'      => $this->replyFarewell(),
            'casual_yes'    => $this->replyCasualYes($context),
            'casual_no'     => $this->replyCasualNo(),
            'funny'         => $this->replyFunny(),
            'price_range'   => $this->replyPriceRange($msg),
            'new_products'  => $this->replyNewProducts(),
            'hours'         => $this->replyHours(),
            default         => $this->replyFallback($msg),
        };
    }

    private function detectIntent(string $msg): string {
        // Longest keyword match wins to prevent short words hijacking
        $bestIntent  = 'unknown';
        $bestLength  = 0;
        foreach ($this->intents as $intent => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($msg, $kw) && strlen($kw) > $bestLength) {
                    $bestIntent = $intent;
                    $bestLength = strlen($kw);
                }
            }
        }
        // Fallback: product-search heuristic
        if ($bestIntent === 'unknown' && preg_match('/\b(any|have|got|do you|sell|stock|available)\b/', $msg)) {
            return 'search';
        }
        return $bestIntent;
    }

    // ─── Handlers ────────────────────────────────────────────────────────────

    private function replyGreeting(array $ctx): array {
        $greetings = [
            "Hello%s! 👋 I'm PairPal, your shopping assistant. How can I help today?",
            "Hey%s! Great to see you. 😊 What can I help you find?",
            "Hi%s! Welcome to PairPal Store. What brings you here today?",
        ];
        $name = !empty($ctx['customer_name']) ? ', ' . explode(' ', $ctx['customer_name'])[0] : '';
        $text = sprintf($greetings[array_rand($greetings)], $name);
        return $this->msg($text, [
            ['label'=>'🛍 Browse Products',   'href'=>'index.php'],
            ['label'=>'🔥 Best Sellers',       'action'=>'chat_bestseller'],
            ['label'=>'🎁 Bundle Deals',        'action'=>'chat_bundle'],
            ['label'=>'❓ How to Order',        'action'=>'chat_how_to_order'],
            ['label'=>'📦 Browse Categories',  'action'=>'chat_categories'],
        ]);
    }

    private function replyCapabilities(): array {
        return $this->msg(
            "I'm PairPal, your AI shopping assistant! Here's what I can help with:\n\n" .
            "🛍 **Shopping** — find products, browse categories\n" .
            "📦 **Orders** — how to order, track your delivery\n" .
            "💳 **Payment** — accepted payment methods\n" .
            "🎁 **Bundles** — find bundle deals and save\n" .
            "🔥 **Trending** — what's popular right now\n" .
            "✨ **Recommendations** — personalised picks for you\n" .
            "🔄 **Returns** — return and refund policy\n\n" .
            "Just ask me anything — I'll do my best! 😊",
            [
                ['label'=>'🛍 Start Browsing', 'href'=>'index.php'],
                ['label'=>'🔥 What\'s Hot',    'action'=>'chat_bestseller'],
            ]
        );
    }

    private function replyHowToOrder(): array {
        return $this->msg(
            "Ordering is quick and easy! Here's how:\n\n" .
            "1️⃣ **Browse** — find a product you like\n" .
            "2️⃣ **Add to Cart** — tap the **\"+ Cart\"** button\n" .
            "3️⃣ **Review** — open your cart 🛒 (top right)\n" .
            "4️⃣ **Checkout** — tap **\"Proceed to Checkout\"**\n" .
            "5️⃣ **Fill in Details** — name, address, contact\n" .
            "6️⃣ **Place Order** — done! ✅\n\n" .
            "You'll receive a **tracking code** immediately to follow your order.",
            [
                ['label'=>'🛍 Start Shopping',     'href'=>'index.php'],
                ['label'=>'📦 Track an Order',     'href'=>'index.php?track'],
                ['label'=>'💳 Payment Methods',    'action'=>'chat_payment'],
            ]
        );
    }

    private function replyDelivery(): array {
        return $this->msg(
            "🚚 **Delivery Information**\n\n" .
            "• **Estimated time:** 3–7 business days\n" .
            "• Orders are processed **within 24 hours** of confirmation\n" .
            "• You'll get a **tracking code** right after placing your order\n" .
            "• Delivery covers **nationwide** 🇵🇭\n" .
            "• Business days: Monday to Saturday\n\n" .
            "Want to track an existing order?",
            [
                ['label'=>'📦 Track My Order', 'href'=>'index.php?track'],
                ['label'=>'📋 My Orders',       'href'=>'index.php?cpage=orders'],
            ]
        );
    }

    private function replyPayment(): array {
        return $this->msg(
            "💳 **Payment Methods We Accept:**\n\n" .
            "• 💵 **Cash on Delivery (COD)** — pay when you receive\n" .
            "• 📱 **GCash** — fast and convenient\n" .
            "• 🏦 **Bank Transfer** — details provided after order\n\n" .
            "Payment details will be confirmed once your order is processed. No hidden fees!",
            [
                ['label'=>'🛍 Shop Now',     'href'=>'index.php'],
                ['label'=>'❓ How to Order', 'action'=>'chat_how_to_order'],
            ]
        );
    }

    private function replyTracking(array $ctx): array {
        if (!empty($ctx['customer_id'])) {
            return $this->msg(
                "📦 You can view all your orders — including tracking codes and status updates — right from your profile.\n\n" .
                "Each order shows:\n• Current status\n• Tracking code\n• Estimated delivery\n• Order details",
                [
                    ['label'=>'📋 My Orders',  'href'=>'index.php?cpage=orders'],
                    ['label'=>'🔍 Track Order','href'=>'index.php?track'],
                ]
            );
        }
        return $this->msg(
            "📦 To track your order you'll need your **tracking code** (e.g. PPABCD1234) from your order confirmation.\n\n" .
            "💡 **Tip:** Create an account to track all orders without a code!",
            [
                ['label'=>'🔍 Track Order',      'href'=>'index.php?track'],
                ['label'=>'👤 Create Account',   'href'=>'index.php?cpage=register'],
                ['label'=>'🔑 Sign In',          'href'=>'index.php?cpage=login'],
            ]
        );
    }

    private function replyReturn(): array {
        return $this->msg(
            "🔄 **Returns & Exchanges Policy:**\n\n" .
            "• Contact us **within 3 days** of receiving your order\n" .
            "• Item must be in **original, unused condition**\n" .
            "• We'll arrange a **replacement or full refund**\n\n" .
            "For damaged or incorrect items, please take a photo and contact us with:\n" .
            "📋 Your order ID and a brief description of the issue.\n\n" .
            "We'll make it right! 💪",
            [['label'=>'📬 Contact Us', 'action'=>'chat_contact']]
        );
    }

    private function replyBestSellers(): array {
        $best = $this->engine->getBestSellers(5);
        if (empty($best)) {
            return $this->msg("We're just getting started on our best seller rankings — check back soon! In the meantime, browse all products. 🌟", [
                ['label'=>'🛍 Browse All', 'href'=>'index.php']
            ]);
        }
        $list    = implode("\n", array_map(fn($p,$i) => ($i+1).". **{$p['name']}** — ₱".number_format($p['price'],2), $best, array_keys($best)));
        $actions = array_map(fn($p) => ['label'=>$p['name'],'href'=>"index.php?product={$p['id']}"], array_slice($best,0,3));
        return $this->msg("🔥 **Top Sellers Right Now:**\n\n{$list}\n\nTap any item to view it:", $actions);
    }

    private function replyBundles(): array {
        $bundles = $this->engine->getSmartBundles(4);
        if (empty($bundles)) {
            return $this->msg("Bundle deals are being generated from purchase data! Check back soon. In the meantime, our products pair great together. 🎁", [
                ['label'=>'🔥 Best Sellers', 'action'=>'chat_bestseller']
            ]);
        }
        $text = "🎁 **Current Bundle Deals:**\n\n";
        foreach ($bundles as $b) {
            $names = implode(' + ', $b['product_names'] ?? []);
            $save  = number_format($b['discount_amount']??$b['savings']??0, 2);
            $price = number_format($b['bundle_price']??0, 2);
            $pct   = $b['discount_value']??0;
            $text .= "• **{$names}**\n  → ₱{$price} · Save ₱{$save} ({$pct}% off)\n\n";
        }
        return $this->msg($text, [
            ['label'=>'🎁 See All Bundles', 'href'=>'index.php#bundle-deals'],
            ['label'=>'🛍 Browse Products', 'href'=>'index.php'],
        ]);
    }

    private function replyCategories(): array {
        $cats = $this->productRepo->getCategories();
        if (empty($cats)) return $this->msg("We're adding products soon — stay tuned! 🌟", []);
        $text    = "📦 **We carry products in these categories:**\n\n" . implode(" · ", $cats) . "\n\nTap a category below to explore:";
        $actions = array_map(fn($c) => ['label'=>$c,'href'=>"index.php?cat=".urlencode($c)], array_slice($cats,0,5));
        return $this->msg($text, $actions);
    }

    private function replySearch(string $msg): array {
        $stopwords = ['do','you','have','any','got','sell','stock','a','an','the','for','some','is','are','i','need','want','looking','find','search','available','there','please','can','could'];
        $words     = array_filter(explode(' ', $msg), fn($w) => strlen($w)>2 && !in_array($w,$stopwords));
        $query     = implode(' ', $words);
        if (empty($query)) return $this->replyCategories();

        $results = $this->productRepo->search($query);
        if (empty($results)) {
            return $this->msg(
                "Hmm, I couldn't find anything matching **\"{$query}\"**. It might not be in stock right now.\n\nWould you like to browse by category instead?",
                [
                    ['label'=>'📦 Browse Categories', 'action'=>'chat_categories'],
                    ['label'=>'🔥 Best Sellers',       'action'=>'chat_bestseller'],
                ]
            );
        }
        $shown = array_slice($results, 0, 4);
        $count = count($results);
        $text  = "🔍 Found **{$count}** product" . ($count!==1?'s':'') . " matching **\"{$query}\":**\n\n";
        foreach ($shown as $p) {
            $stock = $p['stock']>0 ? "In Stock ({$p['stock']} left)" : "Out of Stock";
            $text .= "• **{$p['name']}** — ₱".number_format($p['price'],2)." · {$stock}\n";
        }
        if ($count > 4) $text .= "\n...and " . ($count-4) . " more.";
        $actions = array_map(fn($p) => ['label'=>$p['name'],'href'=>"index.php?product={$p['id']}"], $shown);
        return $this->msg($text, $actions);
    }

    private function replyRecommendation(array $ctx): array {
        $seedIds = array_merge($ctx['cart_ids']??[], $ctx['recent_product_ids']??[]);
        $recs    = !empty($seedIds) ? $this->engine->getCartSuggestions($seedIds,4) : $this->engine->getFeaturedProducts(4);
        if (empty($recs)) {
            return $this->msg("Browse our products and I'll learn what you like! The more you explore, the better my recommendations get. 🌟", [
                ['label'=>'🛍 Browse Now','href'=>'index.php']
            ]);
        }
        $text    = "✨ **You might love these:**\n\n";
        foreach ($recs as $p) $text .= "• **{$p['name']}** — ₱".number_format($p['price'],2)."\n";
        $actions = array_map(fn($p) => ['label'=>$p['name'],'href'=>"index.php?product={$p['id']}"], array_slice($recs,0,3));
        return $this->msg($text, $actions);
    }

    private function replyAccount(array $ctx): array {
        if (!empty($ctx['customer_id'])) {
            return $this->msg(
                "You're signed in! 🎉 Your account gives you access to:\n\n• 📋 Order history & tracking\n• ♥ Wishlist\n• ✨ Personalised recommendations\n• 🚀 Faster checkout (address saved!)",
                [
                    ['label'=>'👤 My Profile', 'href'=>'index.php?cpage=profile'],
                    ['label'=>'📋 My Orders',  'href'=>'index.php?cpage=orders'],
                    ['label'=>'♥ Wishlist',    'href'=>'index.php?cpage=wishlist'],
                ]
            );
        }
        return $this->msg(
            "👤 **Create a free account to:**\n\n• Track all your orders easily\n• Save products to your wishlist\n• Get personalised recommendations\n• Faster checkout next time\n\nAlready have one? Sign in below! 😊",
            [
                ['label'=>'✏ Create Account', 'href'=>'index.php?cpage=register'],
                ['label'=>'🔑 Sign In',        'href'=>'index.php?cpage=login'],
            ]
        );
    }

    private function replyWishlist(array $ctx): array {
        if (!empty($ctx['customer_id'])) {
            return $this->msg("♥ You can save products by tapping the **♡ heart icon** on any product card or product page. View them all in your wishlist!", [
                ['label'=>'♥ My Wishlist', 'href'=>'index.php?cpage=wishlist'],
            ]);
        }
        return $this->msg("Want to save items? ♥\n\nCreate an account to use the wishlist feature — your saved items will be there whenever you're ready to buy!", [
            ['label'=>'✏ Create Account', 'href'=>'index.php?cpage=register'],
        ]);
    }

    private function replyContact(): array {
        return $this->msg(
            "📬 **Get in Touch:**\n\n" .
            "• 📧 **Email:** hello@pairpal.store\n" .
            "• 📞 **Phone:** Available during business hours\n" .
            "• 🕐 **Hours:** Monday–Saturday, 9AM–6PM\n\n" .
            "For order concerns, please include your **Order ID** in your message — it helps us help you faster! 💪",
            []
        );
    }

    private function replyHours(): array {
        return $this->msg(
            "🕐 **Store Hours:**\n\n" .
            "• **Monday – Saturday:** 9:00 AM – 6:00 PM\n" .
            "• **Sunday:** Closed\n\n" .
            "Orders placed outside business hours are processed the next business day. Online ordering is available 24/7! 🌐",
            [['label'=>'🛍 Order Now', 'href'=>'index.php']]
        );
    }

    private function replyStock(string $msg): array {
        // Try to extract a product name from the message
        $results = $this->productRepo->search($msg);
        if (!empty($results)) {
            $p     = $results[0];
            $stock = $p['stock'];
            $thr   = $p['low_stock_threshold'] ?? 8;
            if ($stock <= 0)   $status = "❌ **Out of stock** right now.";
            elseif ($stock<=$thr) $status = "⚠️ **Limited stock** — only {$stock} left!";
            else                   $status = "✅ **In stock** — {$stock} units available.";
            return $this->msg("**{$p['name']}** — {$status}\n₱".number_format($p['price'],2), [
                ['label'=>"View {$p['name']}", 'href'=>"index.php?product={$p['id']}"],
            ]);
        }
        return $this->msg("I can check stock for any specific product! Just tell me the name of what you're looking for. 🔍", [
            ['label'=>'📦 Browse All Products', 'href'=>'index.php'],
        ]);
    }

    private function replyPrice(string $msg): array {
        $results = $this->productRepo->search($msg);
        if (!empty($results) && count($results) <= 3) {
            $text = "💰 **Price Info:**\n\n";
            foreach ($results as $p) $text .= "• **{$p['name']}** → ₱".number_format($p['price'],2)."\n";
            $actions = array_map(fn($p) => ['label'=>$p['name'],'href'=>"index.php?product={$p['id']}"], $results);
            return $this->msg($text, $actions);
        }
        // General price info
        $all     = $this->productRepo->getAll();
        $inStock = array_filter($all, fn($p) => $p['stock']>0);
        $prices  = array_column(array_values($inStock), 'price');
        if (!empty($prices)) {
            $min = number_format(min($prices),2);
            $max = number_format(max($prices),2);
            return $this->msg("💰 Our products range from **₱{$min}** to **₱{$max}**.\n\nLooking for something in a specific price range?", [
                ['label'=>'Under ₱200',      'action'=>'Under 200'],
                ['label'=>'Under ₱500',      'action'=>'Under 500'],
                ['label'=>'🛍 Browse All',   'href'=>'index.php'],
            ]);
        }
        return $this->replyFallback($msg);
    }

    private function replyPriceRange(string $msg): array {
        $limit = 500;
        if (preg_match('/\b(100)\b/', $msg))      $limit = 100;
        elseif (preg_match('/\b(200)\b/', $msg))   $limit = 200;
        elseif (preg_match('/\b(300)\b/', $msg))   $limit = 300;
        elseif (preg_match('/\b(500)\b/', $msg))   $limit = 500;

        $all     = $this->productRepo->getAll();
        $matches = array_values(array_filter($all, fn($p) => $p['price']<=$limit && $p['stock']>0));
        usort($matches, fn($a,$b) => $a['price']<=>$b['price']);
        $shown   = array_slice($matches,0,5);

        if (empty($shown)) return $this->msg("No products found under ₱{$limit} right now. Check back soon!", [['label'=>'🛍 Browse All','href'=>'index.php']]);
        $text    = "💰 **Products under ₱{$limit}:**\n\n";
        foreach ($shown as $p) $text .= "• **{$p['name']}** — ₱".number_format($p['price'],2)."\n";
        $actions = array_map(fn($p) => ['label'=>$p['name'],'href'=>"index.php?product={$p['id']}"], $shown);
        return $this->msg($text, $actions);
    }

    private function replyNewProducts(): array {
        $all  = $this->productRepo->getAll();
        usort($all, fn($a,$b) => strcmp($b['date_added']??$b['created_at']??'', $a['date_added']??$a['created_at']??''));
        $new  = array_slice(array_filter($all, fn($p) => $p['stock']>0), 0, 5);
        if (empty($new)) return $this->msg("New products are on the way! Browse what's currently available. 🌟", [['label'=>'🛍 Browse All','href'=>'index.php']]);
        $text    = "🆕 **Recently Added Products:**\n\n";
        foreach ($new as $p) $text .= "• **{$p['name']}** — ₱".number_format($p['price'],2)."\n";
        $actions = array_map(fn($p) => ['label'=>$p['name'],'href'=>"index.php?product={$p['id']}"], array_slice($new,0,3));
        return $this->msg($text, $actions);
    }

    private function replyFarewell(): array {
        $msgs = [
            "Thanks for chatting! Happy shopping! 🛍 Come back anytime.",
            "See you! Don't miss our bundle deals. 🎁",
            "Goodbye! Hope to help you again soon. ◈",
            "Take care! Your order is just a few taps away when you're ready. 😊",
        ];
        return $this->msg($msgs[array_rand($msgs)], [['label'=>'🛍 Continue Shopping','href'=>'index.php']]);
    }

    private function replyCasualYes(array $ctx): array {
        return $this->msg("Great! 😊 What would you like to explore?", [
            ['label'=>'🛍 Browse Products',  'href'=>'index.php'],
            ['label'=>'🔥 Best Sellers',      'action'=>'chat_bestseller'],
            ['label'=>'🎁 Bundle Deals',      'action'=>'chat_bundle'],
        ]);
    }

    private function replyCasualNo(): array {
        return $this->msg("No problem! I'm here whenever you need me. Is there anything else I can help with? 😊", []);
    }

    private function replyFunny(): array {
        $replies = [
            "I'm PairPal — an AI assistant, not quite human but pretty close! 🤖✨ I'm powered by PairPal Intelligence and here to make your shopping easier.",
            "Am I human? Hmm... I browse products but never buy them, so probably not. 😄 But I'm the next best thing — always available, never on break!",
            "I'm 100% robot, 0% tired! ⚡ Ask me anything about our products.",
        ];
        return $this->msg($replies[array_rand($replies)], [
            ['label'=>'So what can you do?', 'action'=>'chat_capabilities'],
        ]);
    }

    private function replyFallback(string $msg): array {
        // Try a product search as last resort before showing generic fallback
        if (!empty(trim($msg))) {
            $results = $this->productRepo->search($msg);
            if (!empty($results)) {
                return $this->replySearch($msg);
            }
        }
        $suggestions = [
            "🤔 I'm not sure about that. Here's what I can help with:",
            "Hmm, that's a bit outside my expertise! Here's what I'm good at:",
            "I didn't quite catch that. Try one of these:",
        ];
        return $this->msg($suggestions[array_rand($suggestions)], [
            ['label'=>'❓ How to Order',       'action'=>'chat_how_to_order'],
            ['label'=>'🔥 Best Sellers',        'action'=>'chat_bestseller'],
            ['label'=>'🎁 Bundle Deals',         'action'=>'chat_bundle'],
            ['label'=>'📦 Browse Categories',   'action'=>'chat_categories'],
            ['label'=>'📞 Contact Support',     'action'=>'chat_contact'],
        ]);
    }

    private function msg(string $text, array $actions = []): array {
        return ['text'=>$text, 'actions'=>$actions, 'timestamp'=>date('c')];
    }
}
