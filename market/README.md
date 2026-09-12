# Market — online grocery store

A full e-commerce storefront for groceries, built with **plain PHP 8 and MySQL** — no
framework, no build step, no Composer. Drop the folder in `htdocs`, import one SQL
file, and the shop runs.

It covers the flow shoppers expect from Amazon or Flipkart: faceted search, a basket
that survives logging in, coupons, addresses, a payment gateway, order tracking, and
an admin back office.

---

## Quick start

**1. Copy the folder** into your web root

```
XAMPP    → C:\xampp\htdocs\market
WAMP     → C:\wamp64\www\market
Linux    → /var/www/html/market
```

**2. Create the database.** Either import `sql/schema.sql` through phpMyAdmin
(*Import → choose file → Go*), or from a terminal:

```bash
mysql -u root -p < sql/schema.sql
```

The script creates the `market_db` database, all fourteen tables, and a seeded
catalogue of 90 products across 12 categories.

> The file starts with `SET NAMES utf8mb4` — leave it in place. Without it the emoji
> used for product artwork import as mojibake.

**3. Point the app at your database.** Open `config/config.php` and set the four
values at the top. XAMPP's defaults are usually right already:

```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'market_db');
define('DB_USER', 'root');
define('DB_PASS', '');        // set your MySQL password here
```

**4. Open it** — `http://localhost/market/`

### Demo accounts

| Role     | Email                | Password    |
| -------- | -------------------- | ----------- |
| Shopper  | `demo@market.test`   | `demo@123`  |
| Admin    | `admin@market.test`  | `admin@123` |

Change both before putting this anywhere public.

---

## What is in the box

### Shopping
- **Home page** with a rotating hero, category tiles, best sellers, biggest
  discounts, top-rated and recently viewed rails
- **Search** with live typeahead (products *and* categories) and keyboard navigation
- **Filters** — category tree, brand (multi-select), price range and quick bands,
  customer rating, discount %, in-stock, organic, vegetarian
- **Sorting** — popularity, price up/down, discount, rating, newest
- **Pagination** with page windowing
- **Product page** — gallery, MRP vs. selling price with the discount worked out,
  stock urgency, delivery estimate, pincode checker, offers, highlights, a full
  specification table, ratings histogram, reviews you can post and edit, plus
  related and "customers also bought" rails

### Account
- Register and sign in with hashed passwords, validation and a "keep me signed in"
  option
- Guest basket that **merges into the account** on login
- Favourites (wishlist) with one-tap toggling and *move all to basket*
- Address book with a default address
- Profile editing and password change
- Order history with search and status filtering

### Basket and checkout
- AJAX add-to-basket, quantity steppers, save-for-later, remove, empty
- Coupons with minimum-order and cap rules, re-validated whenever the basket changes
- Free-delivery threshold with a progress bar, delivery and handling fees
- Three-step checkout: address → delivery slot → payment method
- Stock re-checked at the moment the order is placed

### Payments (demo gateway)
`payment.php` is a self-contained simulation of a hosted gateway — UPI, cards,
net banking, wallet and cash on delivery, each with its own validation. It records
a transaction ID, supports a **deliberate failure path** so you can test retries,
and writes only a masked reference such as `Card ending 1111`. **No card, UPI or
bank credential is ever stored.** Swap this one file for a Razorpay, Stripe or PayU
redirect to go live.

### Orders
- Confirmation page, tracking timeline, printable invoice view
- Cancel an order (returns every item to stock, marks prepaid orders refunded)
- One-click reorder

### Admin (`/admin`)
- Dashboard: revenue, order counts, customers, stock alerts, best sellers,
  order-status breakdown
- Products: search, sort, inline restock, visibility toggle, full create/edit form
- Orders: queue with filters, per-order fulfilment and payment status
- Customers: order counts, lifetime spend, enable/disable access
- Coupons: create, pause, delete

---

## How it is put together

```
market/
├── config/
│   ├── config.php        database credentials, fees, feature constants
│   └── database.php      PDO handle + q() / q1() / qa() / qv() query helpers
├── includes/
│   ├── bootstrap.php     session + everything below, loaded by every page
│   ├── functions.php     escaping, money, CSRF, flash messages, URL building
│   ├── auth.php          register, login, roles, guards
│   ├── cart.php          basket, coupons, pricing, wishlist, recently viewed
│   ├── catalog.php       category tree, the filtered product query, reviews
│   ├── header.php  footer.php  product-card.php
├── api/                  JSON endpoints: cart.php, wishlist.php, suggest.php
├── admin/                back office (own layout, admin-only)
├── assets/
│   ├── css/style.css     one stylesheet, sectioned and commented
│   ├── js/app.js         one script, no dependencies
│   └── image.php         draws product artwork as SVG from an emoji + tint
├── sql/schema.sql        schema + seed data
└── index.php  products.php  product.php  cart.php  checkout.php
    payment.php  order.php  orders.php  order-success.php
    account.php  wishlist.php  offers.php  help.php
    login.php  register.php  logout.php
```

**Product images are generated, not shipped.** `assets/image.php?e=🍎&b=e6f7ec` returns
an SVG tile. That keeps the repository free of binary assets and means a new product
gets artwork the moment you pick an emoji in the admin form. To use real photographs,
add an `image_path` column and change `product_image()` in `includes/functions.php`.

### Database

Fourteen tables with foreign keys throughout: `users`, `addresses`, `categories`
(self-referencing for subcategories), `brands`, `products`, `cart_items`,
`wishlist_items`, `coupons`, `orders`, `order_items`, `payments`, `reviews`,
`banners`.

Two decisions worth knowing about:

- **Orders snapshot their data.** Shipping address, item names and prices are copied
  into `orders` / `order_items` when the order is placed, so later edits to an address
  or a price never rewrite history.
- **Stock is held at order time**, not at payment time, and returned when an order is
  cancelled. That keeps a slow payment from overselling the last unit.

### Security

- Every query is a prepared statement; nothing user-supplied is concatenated into SQL
- Every output goes through `e()` (`htmlspecialchars`)
- Every POST carries a CSRF token, checked by `require_csrf()`
- Passwords use `password_hash()` with automatic rehashing on algorithm changes
- Session ID regenerated on login; session cookie is `HttpOnly` + `SameSite=Lax`
- Orders, addresses and payments are always scoped to the signed-in user
- Admin pages sit behind `require_admin()`

Set `DEBUG` to `false` in `config/config.php` on any public server — it controls
whether database errors are printed to the page.

---

## Configuration you may want to change

All in `config/config.php`:

| Constant               | Default | What it does                          |
| ---------------------- | ------- | ------------------------------------- |
| `STORE_NAME`           | Market  | Shown in the header, footer, titles   |
| `FREE_DELIVERY_ABOVE`  | 499     | Order value that waives delivery      |
| `DELIVERY_FEE`         | 39      | Charged below that threshold          |
| `HANDLING_FEE`         | 9       | Small-basket charge, waived with above|
| `MAX_QTY_PER_ITEM`     | 10      | Per-line quantity cap                 |
| `PRODUCTS_PER_PAGE`    | 24      | Listing page size                     |
| `CURRENCY`             | ₹       | Symbol used by `money()`              |

`.htaccess` adds optional pretty URLs (`/p/<slug>`, `/c/<slug>`), blocks the SQL dump
and config from being fetched over HTTP, and sets a few security headers. The app
works without it.

---

## Requirements

- PHP 8.0 or newer with `pdo_mysql` (tested on PHP 8.4)
- MySQL 5.7+ or MariaDB 10.3+ (tested on MariaDB 10.11)
- Any web server — Apache, Nginx, or `php -S localhost:8000` for a quick look

## Note

This is a demonstration storefront. Payments are simulated end to end and no real
transaction takes place. It is independent of the Vaibhav Enterprise site in the
root of this repository and shares nothing with it.
