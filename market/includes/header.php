<?php
/** Site chrome: <head>, top bar, search, account menu, category nav. */

if (!defined('DB_NAME')) {
    require_once __DIR__ . '/bootstrap.php';
}

$pageTitle   = $pageTitle   ?? STORE_NAME . ' — ' . STORE_TAGLINE;
$pageDesc    = $pageDesc    ?? 'Order fruits, vegetables, dairy, staples and household essentials online. Free delivery above ' . money(FREE_DELIVERY_ABOVE) . '.';
$bodyClass   = $bodyClass   ?? '';
$activeNav   = $activeNav   ?? '';
$user        = current_user();
$cartCount   = cart_count();
$wishCount   = wishlist_count();
$navTree     = category_tree();
$searchTerm  = $_GET['q'] ?? '';
?>
<!doctype html>
<html lang="en-IN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDesc) ?>">
<meta name="theme-color" content="#0b5f2c">
<link rel="icon" href="assets/image.php?e=%F0%9F%9B%92&amp;b=0f8a3c&amp;s=64" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=1">
<script>window.MARKET = { csrf: <?= json_encode(csrf_token()) ?>, loggedIn: <?= is_logged_in() ? 'true' : 'false' ?> };</script>
</head>
<body class="<?= e($bodyClass) ?>">

<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
  <div class="topbar">
    <div class="wrap topbar-inner">
      <span class="topbar-item">🚚 Free delivery above <?= money(FREE_DELIVERY_ABOVE) ?></span>
      <span class="topbar-item hide-sm">⏱️ Slot delivery in 90 minutes</span>
      <span class="topbar-spacer"></span>
      <a class="topbar-item" href="offers.php">Today's offers</a>
      <a class="topbar-item hide-sm" href="orders.php">Track order</a>
      <a class="topbar-item hide-sm" href="help.php">Help</a>
    </div>
  </div>

  <div class="headbar">
    <div class="wrap headbar-inner">
      <button class="icon-btn menu-toggle" type="button" aria-label="Open menu" data-drawer-open>
        <span class="burger"></span>
      </button>

      <a class="brand" href="index.php">
        <span class="brand-mark" aria-hidden="true">🛒</span>
        <span class="brand-text">
          <span class="brand-name"><?= e(STORE_NAME) ?></span>
          <span class="brand-tag">Explore <span class="brand-plus">Plus</span> ✦</span>
        </span>
      </a>

      <form class="searchbar" action="products.php" method="get" role="search" autocomplete="off">
        <span class="search-icon" aria-hidden="true">🔍</span>
        <input type="search" name="q" id="site-search" value="<?= e($searchTerm) ?>"
               placeholder="Search for atta, milk, fruits and more…"
               aria-label="Search products" data-suggest>
        <button class="search-go" type="submit">Search</button>
        <div class="suggest" id="suggest-box" hidden></div>
      </form>

      <nav class="head-actions" aria-label="Account">
        <?php if ($user): ?>
          <div class="account-menu" data-menu>
            <button class="head-action" type="button" data-menu-toggle aria-expanded="false">
              <span class="avatar" style="background:<?= e($user['avatar_color']) ?>"><?= e(initials($user['name'])) ?></span>
              <span class="head-action-text hide-md">
                <small>Hello,</small><strong><?= e(explode(' ', $user['name'])[0]) ?></strong>
              </span>
              <span class="caret" aria-hidden="true">▾</span>
            </button>
            <div class="menu-panel" data-menu-panel hidden>
              <a href="account.php">👤 My profile</a>
              <a href="orders.php">📦 My orders</a>
              <a href="wishlist.php">❤️ Favourites <span class="pill"><?= (int) $wishCount ?></span></a>
              <a href="account.php#addresses">📍 Saved addresses</a>
              <a href="offers.php">🎁 Coupons &amp; offers</a>
              <?php if (is_admin()): ?><a href="admin/index.php">⚙️ Admin dashboard</a><?php endif; ?>
              <form method="post" action="logout.php" class="menu-form">
                <?= csrf_field() ?>
                <button type="submit">↪ Log out</button>
              </form>
            </div>
          </div>
        <?php else: ?>
          <a class="head-action login-cta" href="login.php">
            <span class="head-action-icon" aria-hidden="true">👤</span>
            <span class="head-action-text hide-md"><small>Sign in</small><strong>Account</strong></span>
          </a>
        <?php endif; ?>

        <a class="head-action hide-md" href="wishlist.php">
          <span class="head-action-icon" aria-hidden="true">❤️</span>
          <span class="head-action-text"><small>Saved</small><strong>Favourites</strong></span>
          <?php if ($wishCount): ?><span class="badge" data-wish-count><?= (int) $wishCount ?></span><?php endif; ?>
        </a>

        <a class="head-action cart-action" href="cart.php">
          <span class="head-action-icon" aria-hidden="true">🛍️</span>
          <span class="head-action-text hide-md"><small>My</small><strong>Basket</strong></span>
          <span class="badge<?= $cartCount ? '' : ' is-zero' ?>" data-cart-count><?= (int) $cartCount ?></span>
        </a>
      </nav>
    </div>
  </div>

  <nav class="catnav" aria-label="Product categories">
    <div class="wrap catnav-inner">
      <?php foreach ($navTree as $cat): ?>
        <div class="catnav-item<?= $activeNav === $cat['slug'] ? ' is-active' : '' ?>">
          <a href="products.php?category=<?= e($cat['slug']) ?>">
            <span class="catnav-emoji" aria-hidden="true"><?= e($cat['emoji']) ?></span>
            <?= e($cat['name']) ?>
            <?php if ($cat['children']): ?><span class="caret" aria-hidden="true">▾</span><?php endif; ?>
          </a>
          <?php if ($cat['children']): ?>
            <div class="megamenu">
              <div class="megamenu-inner">
                <div class="megamenu-links">
                  <?php foreach ($cat['children'] as $child): ?>
                    <a href="products.php?category=<?= e($child['slug']) ?>"><?= e($child['name']) ?></a>
                  <?php endforeach; ?>
                </div>
                <div class="megamenu-promo" style="background:<?= e($cat['tint']) ?>">
                  <span class="megamenu-emoji" aria-hidden="true"><?= e($cat['emoji']) ?></span>
                  <strong><?= e($cat['name']) ?></strong>
                  <p>Up to 35% off across the range</p>
                  <a class="btn btn-sm" href="products.php?category=<?= e($cat['slug']) ?>&amp;sort=discount">See deals</a>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </nav>
</header>

<!-- mobile drawer -->
<div class="drawer" id="drawer" hidden>
  <div class="drawer-backdrop" data-drawer-close></div>
  <aside class="drawer-panel" aria-label="Menu">
    <div class="drawer-head">
      <?php if ($user): ?>
        <span class="avatar lg" style="background:<?= e($user['avatar_color']) ?>"><?= e(initials($user['name'])) ?></span>
        <div><strong><?= e($user['name']) ?></strong><small><?= e($user['email']) ?></small></div>
      <?php else: ?>
        <div><strong>Welcome to <?= e(STORE_NAME) ?></strong><small>Sign in for faster checkout</small></div>
      <?php endif; ?>
      <button class="icon-btn" type="button" data-drawer-close aria-label="Close menu">✕</button>
    </div>
    <nav class="drawer-nav">
      <a href="index.php">🏠 Home</a>
      <a href="products.php">🧺 All products</a>
      <a href="offers.php">🎁 Offers</a>
      <a href="cart.php">🛍️ Basket (<?= (int) $cartCount ?>)</a>
      <a href="wishlist.php">❤️ Favourites</a>
      <a href="orders.php">📦 Orders</a>
      <a href="account.php">👤 Account</a>
      <?php if (is_admin()): ?><a href="admin/index.php">⚙️ Admin</a><?php endif; ?>
    </nav>
    <p class="drawer-label">Shop by category</p>
    <nav class="drawer-nav">
      <?php foreach ($navTree as $cat): ?>
        <a href="products.php?category=<?= e($cat['slug']) ?>"><?= e($cat['emoji']) ?> <?= e($cat['name']) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php if ($user): ?>
      <form method="post" action="logout.php" class="drawer-logout"><?= csrf_field() ?><button class="btn btn-ghost btn-block">Log out</button></form>
    <?php else: ?>
      <a class="btn btn-block" href="login.php">Sign in</a>
    <?php endif; ?>
  </aside>
</div>

<div class="toasts" id="toasts" aria-live="polite"></div>

<?php foreach (take_flashes() as $flashMessage): ?>
  <script>document.addEventListener('DOMContentLoaded',function(){window.toast(<?= json_encode($flashMessage['message']) ?>,<?= json_encode($flashMessage['type']) ?>);});</script>
<?php endforeach; ?>

<main id="main">
