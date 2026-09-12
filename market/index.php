<?php
/** Home — hero, categories, deals, best sellers, recently viewed. */

require_once __DIR__ . '/includes/bootstrap.php';

$banners      = qa('SELECT * FROM banners WHERE is_active = 1 ORDER BY sort_order');
$topCats      = category_tree();
$bestSellers  = products(['sort' => 'popularity', 'in_stock' => 1, 'per_page' => 12]);
$bigSavers    = products(['sort' => 'discount', 'in_stock' => 1, 'per_page' => 12]);
$freshPicks   = products(['category' => 'fruits-vegetables', 'in_stock' => 1, 'per_page' => 12]);
$newArrivals  = products(['sort' => 'newest', 'per_page' => 6]);
$topRated     = products(['sort' => 'rating', 'rating' => 4.4, 'per_page' => 12]);
$coupons      = qa('SELECT * FROM coupons WHERE is_active = 1 ORDER BY min_order LIMIT 4');
$recent       = recently_viewed();

$catCounts = [];
foreach (qa('SELECT c.parent_id AS pid, COUNT(p.id) AS n
               FROM products p
               JOIN categories c ON c.id = p.category_id
              WHERE p.is_active = 1
           GROUP BY c.parent_id') as $row) {
    $catCounts[(int) $row['pid']] = (int) $row['n'];
}

$pageTitle = STORE_NAME . ' — Online grocery shopping, delivered fresh';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap page">

  <!-- ------------------------------------------------------------- hero -->
  <section class="hero">
    <div class="hero-slides">
      <?php foreach ($banners as $i => $b): ?>
        <div class="hero-slide<?= $i === 0 ? ' is-on' : '' ?>"
             style="background:linear-gradient(120deg, <?= e($b['bg_from']) ?>, <?= e($b['bg_to']) ?>)">
          <div>
            <h1><?= e($b['title']) ?></h1>
            <p><?= e($b['subtitle']) ?></p>
            <a class="btn btn-accent btn-lg" href="<?= e($b['cta_link']) ?>"><?= e($b['cta_text']) ?> →</a>
          </div>
          <?php $shots = banner_products($b['category_slug'] ?? null); ?>
          <div class="hero-shots">
            <?php if ($shots): ?>
              <?php foreach ($shots as $n => $shot): ?>
                <a class="hero-shot hero-shot-<?= $n + 1 ?>" href="product.php?slug=<?= e($shot['slug']) ?>"
                   style="--tile:<?= e($shot['tint']) ?>">
                  <?= product_img($shot, 150, '', $i === 0) ?>
                  <span class="hero-shot-tag"><?= money($shot['price']) ?></span>
                </a>
              <?php endforeach; ?>
            <?php else: ?>
              <span class="hero-emoji" aria-hidden="true"><?= e($b['emoji']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <div class="hero-dots">
        <?php foreach ($banners as $i => $b): ?>
          <button type="button" class="<?= $i === 0 ? 'is-on' : '' ?>" aria-label="Slide <?= $i + 1 ?>"></button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="hero-side">
      <div class="hero-card">
        <span class="hc-emoji" aria-hidden="true">🎁</span>
        <strong>First order? Save <?= money(50) ?></strong>
        <p>Use code <b>MARKET50</b> on any basket above <?= money(499) ?>.</p>
        <a href="offers.php">See all coupons →</a>
      </div>
      <div class="hero-card">
        <span class="hc-emoji" aria-hidden="true">⏱️</span>
        <strong>90-minute slots</strong>
        <p>Pick a delivery window at checkout — morning, evening or express.</p>
        <a href="products.php?sort=newest">Start shopping →</a>
      </div>
    </div>
  </section>

  <!-- trust strip -->
  <div class="trust">
    <div class="trust-item"><span class="trust-emoji">🚚</span><div><strong>Free delivery</strong><small>On orders above <?= money(FREE_DELIVERY_ABOVE) ?></small></div></div>
    <div class="trust-item"><span class="trust-emoji">🥬</span><div><strong>Farm fresh</strong><small>Sourced within 24 hours</small></div></div>
    <div class="trust-item"><span class="trust-emoji">↩️</span><div><strong>Easy returns</strong><small>No-questions refund policy</small></div></div>
    <div class="trust-item"><span class="trust-emoji">🔒</span><div><strong>Secure payments</strong><small>UPI, cards, net banking &amp; COD</small></div></div>
  </div>

  <!-- ------------------------------------------------------- categories -->
  <section class="section">
    <div class="section-head">
      <div><h2>Shop by category</h2><p>Everything for the week, sorted into aisles.</p></div>
      <a class="more" href="products.php">Browse all products →</a>
    </div>
    <div class="cat-strip">
      <?php foreach ($topCats as $cat): ?>
        <?php $shot = category_photo((int) $cat['id']); ?>
        <a class="cat-tile" href="products.php?category=<?= e($cat['slug']) ?>">
          <span class="ct-shot<?= $shot ? ' has-photo' : '' ?>" style="--tile:<?= e($cat['tint']) ?>">
            <?php if ($shot): ?>
              <?= product_img($shot, 76, '', true) ?>
            <?php else: ?>
              <span class="ct-emoji" aria-hidden="true"><?= e($cat['emoji']) ?></span>
            <?php endif; ?>
          </span>
          <span><?= e($cat['name']) ?></span>
          <small><?= (int) ($catCounts[(int) $cat['id']] ?? 0) ?> items</small>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ------------------------------------------------------ best sellers -->
  <section class="section">
    <div class="section-head">
      <div><h2>🔥 Best sellers this week</h2><p>What most Market baskets have in them right now.</p></div>
      <a class="more" href="products.php?sort=popularity">View all →</a>
    </div>
    <div class="rail">
      <?php foreach ($bestSellers as $p) { include __DIR__ . '/includes/product-card.php'; } ?>
    </div>
  </section>

  <!-- ---------------------------------------------------------- coupons -->
  <section class="deal-strip">
    <div>
      <h2>Coupons you can use today</h2>
      <p>Tap a code to copy it, then paste it in your basket.</p>
    </div>
    <div class="deal-codes">
      <?php foreach ($coupons as $c): ?>
        <button class="deal-code" type="button" data-copy="<?= e($c['code']) ?>">
          <?= e($c['code']) ?>
          <small><?= $c['type'] === 'flat' ? money($c['value']) . ' off' : (int) $c['value'] . '% off' ?> above <?= money($c['min_order']) ?></small>
        </button>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ------------------------------------------------------- big savers -->
  <section class="section">
    <div class="section-head">
      <div><h2>💸 Biggest savings</h2><p>The steepest discounts across the store.</p></div>
      <a class="more" href="products.php?sort=discount">View all →</a>
    </div>
    <div class="rail">
      <?php foreach ($bigSavers as $p) { include __DIR__ . '/includes/product-card.php'; } ?>
    </div>
  </section>

  <!-- -------------------------------------------------------- fresh picks -->
  <section class="section">
    <div class="section-head">
      <div><h2>🥦 Fresh today</h2><p>Fruits and vegetables picked yesterday evening.</p></div>
      <a class="more" href="products.php?category=fruits-vegetables">View all →</a>
    </div>
    <div class="rail">
      <?php foreach ($freshPicks as $p) { include __DIR__ . '/includes/product-card.php'; } ?>
    </div>
  </section>

  <!-- --------------------------------------------------------- top rated -->
  <section class="section">
    <div class="section-head">
      <div><h2>⭐ Highest rated</h2><p>Rated 4.4 and above by Market shoppers.</p></div>
      <a class="more" href="products.php?sort=rating">View all →</a>
    </div>
    <div class="rail">
      <?php foreach ($topRated as $p) { include __DIR__ . '/includes/product-card.php'; } ?>
    </div>
  </section>

  <?php if ($recent): ?>
    <section class="section">
      <div class="section-head"><div><h2>👀 Recently viewed</h2></div></div>
      <div class="rail">
        <?php foreach ($recent as $p) { include __DIR__ . '/includes/product-card.php'; } ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- ------------------------------------------------------ new arrivals -->
  <section class="section">
    <div class="section-head">
      <div><h2>🆕 New in store</h2><p>Just added to the Market shelves.</p></div>
      <a class="more" href="products.php?sort=newest">View all →</a>
    </div>
    <div class="grid">
      <?php foreach ($newArrivals as $p) { include __DIR__ . '/includes/product-card.php'; } ?>
    </div>
  </section>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
