### Requirements
- PHP 8.0+ with `bcrypt` support (standard in PHP 7.4+)
- Web server: Apache, Nginx, or PHP built-in server

### Setup Steps

1. **Deploy** — Place the `pairpal/` folder in your web root (e.g. `htdocs/`, `www/`, or `public_html/`)

2. **Fix permissions** — Run setup.php: *this is optional*
   ```
   # Browser:  http://localhost/pairpal/setup.php
   # Terminal: php setup.php
   ```
   This sets correct permissions on the `data/` directory so PHP can read/write JSON files.

3. **Access the system:**

   | URL | Description |
   |-----|-------------|
   | `http://localhost/pairpal/` | Customer storefront |
   | `http://localhost/pairpal/index.php?page=login` | Staff login |
   | `http://localhost/pairpal/index.php?page=dashboard` | Admin/cashier dashboard |

### Built-in Server (quick test)
```bash
cd pairpal
php -S localhost:8000
# Visit http://localhost:8000/
```

---

## Demo Credentials

| Role | Username | Password |
|------|----------|----------|
| Admin | `admin` | `password` |
| Cashier | `cashier` | `password` |

---

## System Structure

```
pairpal/
├── index.php                   # Main router (POST handler runs FIRST)
├── setup.php                   # One-time setup helper (delete after use)
│
├── controllers/
│   ├── AuthController.php      # Login, logout, session management
│   ├── CartController.php      # Cart (cashier + customer), auto bundle discounts
│   ├── OrderController.php     # Customer order placement + admin management
│   ├── ProductController.php   # CRUD, bulk import, image upload, stock adjust
│   └── ReportController.php    # Sales summaries, CSV export
│
├── models/
│   ├── User.php (abstract)     # Base user with role-based methods
│   ├── Admin.php               # Admin extends User
│   ├── Cashier.php             # Cashier extends User
│   ├── Cart.php                # Cart with bundle/discount support
│   ├── Product.php             # Product model with validation
│   └── Transaction.php         # Transaction with full discount fields
│
├── services/
│   ├── FileHandler.php         # Abstract file I/O with locking + error logging
│   ├── UserRepository.php      # users.json CRUD
│   ├── ProductRepository.php   # products.json CRUD + search + bulk import
│   ├── SalesRepository.php     # sales.json CRUD + analytics queries
│   ├── OrderRepository.php     # orders.json CRUD + status tracking
│   ├── BundleRepository.php    # bundles.json CRUD + findMatchingBundles()
│   ├── InventoryLogRepository.php  # inventory_logs.json
│   ├── PairPalDataRepository.php   # pairpal_data.json pair frequencies
│   ├── ReviewRepository.php    # reviews.json product reviews
│   └── PairPalEngine.php       # AI engine: bundles, suggestions, upsells
│
├── views/
│   ├── layout.php              # Admin/cashier shell with sidebar
│   ├── login.php               # Login form with robust error handling
│   ├── dashboard.php           # Stats, alerts, insights
│   ├── cart.php                # Cashier POS with live AI panel
│   ├── products.php            # Product management (admin)
│   ├── inventory.php           # Stock logs + restock panel (admin)
│   ├── history.php             # Sales history with date range filter
│   ├── orders.php              # Customer order management (admin)
│   ├── reports.php             # Reports + CSV export (admin)
│   ├── intelligence.php        # PairPal AI insights page (admin)
│   ├── bundles.php             # Bundle management (admin)
│   └── customer/
│       └── store.php           # Public customer storefront
│
├── data/                       # JSON storage (must be writable)
│   ├── users.json
│   ├── products.json
│   ├── sales.json
│   ├── orders.json
│   ├── bundles.json
│   ├── pairpal_data.json
│   ├── inventory_logs.json
│   └── reviews.json
│
└── assets/
    ├── css/main.css            # Admin/cashier styles (dark editorial)
    ├── css/store.css           # Customer storefront styles (clean/warm)
    ├── js/main.js              # Admin JS utilities
    └── img/products/           # Uploaded product images (must be writable)
```

---

## Features by Role

### Customer (public, no login)
- Browse product catalog with search & category filter
- View product detail pages with reviews
- Featured products, trending items, bundle deals sections
- Slide-out cart with real-time bundle discount detection
- Checkout with name/address/contact — order saved to `orders.json`
- Order tracking via tracking code
- Submit product reviews (1–5 stars)

### Cashier
- Fast POS interface with product search
- Live PairPal AI panel: upsell prompts + complementary item suggestions (VERY limited for the cashier role)
- Auto-applied bundle discounts with visual flash notification
- Manual discount override (% or fixed)
- Checkout with receipt generation
- View sales history

### Admin (all cashier features plus)
- Full product CRUD with image upload
- Bulk product import via JSON file
- Manual stock adjustments with inventory logging
- Inventory logs per product
- Customer order management with status updates
- Full reports with date-range filter + CSV export
- PairPal AI intelligence page
- Bundle management (enable/disable/delete auto-generated bundles)

---

## PairPal Intelligence

All AI logic is **rule-based** — no external APIs.

- **Auto bundle detection** — checks active bundles against cart contents; auto-applies discount
- **Upsell prompts** — detects which bundle is 1 item away from completion
- **Complementary suggestions** — co-purchase frequency ranking + category fallback  
- **Dynamic bundle generation** — rebuilds `bundles.json` from `pairpal_data.json` after each sale
- **Slow mover detection** — products with ≤2 sales in 30 days, adequate stock
- **Restock suggestions** — scored by `sales_qty / (stock + 1)` urgency

---

## Known Deployment Notes

- **Permissions** — Run `setup.php` first. The `data/` dir needs `0775` and JSON files need `0664`
- **HTTPS** — Recommended for production; update session cookie params in `AuthController`
- **Image uploads** — Max 2MB per image; JPG/PNG/WebP only; stored in `assets/img/products/`
- **Session** — Uses PHP file sessions; for multi-server setups, configure a shared session handler
