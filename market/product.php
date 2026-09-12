<?php
/** Product detail: gallery, offers, delivery check, specs, reviews, related. */

require_once __DIR__ . '/includes/bootstrap.php';

$slug    = trim((string) ($_GET['slug'] ?? ''));
$product = $slug !== '' ? product_by_slug($slug) : null;

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product not found | ' . STORE_NAME;
    require __DIR__ . '/includes/header.php';
    echo '<div class="wrap page"><div class="panel empty"><span class="empty-emoji">🔍</span>'
       . '<h2>We could not find that product</h2><p>It may have been removed or renamed.</p>'
       . '<p style="margin-top:16px"><a class="btn" href="products.php">Browse the store</a></p></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$productId = (int) $product['id'];
remember_view($productId);

// ------------------------------------------------------------ post a review
$reviewError = null;
if (is_post() && ($_POST['form'] ?? '') === 'review') {
    require_csrf();
    if (!is_logged_in()) {
        redirect('login.php?next=' . urlencode('product.php?slug=' . $slug));
    }
    $rating = (int) ($_POST['rating'] ?? 0);
    $title  = trim((string) ($_POST['title'] ?? ''));
    $body   = trim((string) ($_POST['body'] ?? ''));

    if ($rating < 1 || $rating > 5) {
        $reviewError = 'Pick a star rating between 1 and 5.';
    } else {
        q('INSERT INTO reviews (product_id, user_id, rating, title, body) VALUES (?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE rating = VALUES(rating), title = VALUES(title), body = VALUES(body), created_at = NOW()',
          [$productId, user_id(), $rating, $title, $body]);
        refresh_product_rating($productId);
        flash('Thanks — your review is live.');
        redirect('product.php?slug=' . urlencode($slug) . '#reviews');
    }
}

$rating     = rating_of($product);
$off        = discount_pct($product['price'], $product['mrp']);
$inStock    = (int) $product['stock'] > 0;
$saved      = in_wishlist($productId);
$breakdown  = rating_breakdown($productId);
$reviews    = product_reviews($productId);
$myReview   = is_logged_in() ? q1('SELECT * FROM reviews WHERE product_id = ? AND user_id = ?', [$productId, user_id()]) : null;
$related    = products(['category' => $product['category_slug'], 'exclude' => $productId, 'per_page' => 10]);
$alsoBought = products(['sort' => 'popularity', 'exclude' => $productId, 'per_page' => 10]);
$recent     = recently_viewed($productId);
$highlights = array_filter(array_map('trim', explode("\n", (string) $product['highlights'])));
$coupons    = qa('SELECT * FROM coupons WHERE is_active = 1 ORDER BY min_order LIMIT 3');

$pageTitle = $product['name'] . ' — ' . $product['unit'] . ' | ' . STORE_NAME;
$pageDesc  = $product['short_desc'];
require __DIR__ . '/includes/header.php';
?>

<div class="wrap page">

  <nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="index.php">Home</a> <span class="sep">›</span>
    <a href="products.php">Products</a> <span class="sep">›</span>
    <a href="products.php?category=<?= e($product['category_slug']) ?>"><?= e($product['category_name']) ?></a>
    <span class="sep">›</span> <span><?= e($product['name']) ?></span>
  </nav>

  <div class="pdp">

    <!-- ------------------------------------------------------- gallery -->
    <div class="pdp-media">
      <div class="pdp-stage" style="background:<?= e($product['tint']) ?>">
        <span class="pdp-emoji" aria-hidden="true"><?= e($product['emoji']) ?></span>
        <?php if ($off > 0): ?><span class="card-off"><?= $off ?>% OFF</span><?php endif; ?>
        <?php if (!$inStock): ?><span class="card-oos">Currently out of stock</span><?php endif; ?>
      </div>

      <div class="pdp-thumbs">
        <?php
        // One product photo per SKU in a demo catalogue, so the extra thumbs are
        // tint variations of the same artwork rather than fake alternate shots.
        $tints = [$product['tint'], '#ffffff', '#f4f5f7', '#eef3ff'];
        foreach ($tints as $i => $tint): ?>
          <button class="pdp-thumb<?= $i === 0 ? ' is-on' : '' ?>" type="button"
                  style="background:<?= e($tint) ?>"
                  data-pdp-thumb data-tint="<?= e($tint) ?>" data-emoji="<?= e($product['emoji']) ?>"
                  aria-label="View <?= $i + 1 ?>"><?= e($product['emoji']) ?></button>
        <?php endforeach; ?>
      </div>

      <div class="pdp-buy pdp-actions">
        <?php if ($inStock): ?>
          <div class="qty" data-max="<?= min(MAX_QTY_PER_ITEM, (int) $product['stock']) ?>" style="display:none"><output>1</output></div>
          <button class="btn btn-add btn-lg" type="button" data-add-to-cart="<?= $productId ?>">🛍️ Add to basket</button>
          <a class="btn btn-accent btn-lg" href="checkout.php?buy=<?= $productId ?>">⚡ Buy now</a>
        <?php else: ?>
          <button class="btn btn-out btn-lg" type="button" disabled>Out of stock</button>
          <button class="btn btn-ghost btn-lg" type="button" data-wishlist="<?= $productId ?>">Notify me</button>
        <?php endif; ?>
      </div>
    </div>

    <!-- --------------------------------------------------------- info -->
    <div>
      <?php if ($product['brand_name']): ?>
        <a class="pdp-brand" href="products.php?brand[]=<?= e($product['brand_slug']) ?>"><?= e($product['brand_name']) ?></a>
      <?php endif; ?>
      <h1 class="pdp-title"><?= e($product['name']) ?></h1>

      <div class="pdp-meta">
        <?php if ((int) $product['rating_count'] > 0): ?>
          <span class="rating-chip<?= $rating < 3.5 ? ' is-low' : '' ?>"><?= number_format($rating, 1) ?> ★</span>
          <a class="muted tiny" href="#reviews"><?= compact_number((int) $product['rating_count']) ?> ratings</a>
        <?php else: ?>
          <span class="chip">Be the first to review</span>
        <?php endif; ?>
        <span class="muted tiny">·</span>
        <span class="muted tiny"><?= compact_number((int) $product['sold_count']) ?> sold</span>
        <?php if ((int) $product['is_organic'] === 1): ?><span class="chip tag-green">🌱 Organic</span><?php endif; ?>
        <span class="chip <?= (int) $product['is_veg'] === 1 ? 'tag-green' : 'tag-red' ?>">
          <?= (int) $product['is_veg'] === 1 ? '🟢 Veg' : '🔴 Contains egg' ?>
        </span>
      </div>

      <div class="pdp-price">
        <span class="now"><?= money($product['price']) ?></span>
        <?php if ($off > 0): ?>
          <s><?= money($product['mrp']) ?></s>
          <span class="off"><?= $off ?>% off</span>
        <?php endif; ?>
      </div>
      <p class="pdp-tax">Inclusive of all taxes · <?= e($product['unit']) ?></p>

      <?php if ($inStock && (int) $product['stock'] <= 12): ?>
        <p class="chip tag-amber">🔥 Only <?= (int) $product['stock'] ?> left in stock</p>
      <?php endif; ?>

      <div class="pdp-facts">
        <div class="fact"><span class="fact-emoji">🚚</span><strong>Free delivery</strong><small>above <?= money(FREE_DELIVERY_ABOVE) ?></small></div>
        <div class="fact"><span class="fact-emoji">⏱️</span><strong><?= e(delivery_estimate()) ?></strong><small>if ordered now</small></div>
        <div class="fact"><span class="fact-emoji">↩️</span><strong>7-day returns</strong><small>no questions asked</small></div>
        <div class="fact"><span class="fact-emoji">✅</span><strong>Quality checked</strong><small>before dispatch</small></div>
      </div>

      <div class="panel panel-pad">
        <h3 style="font-size:13px;margin-bottom:10px">Available offers</h3>
        <ul class="offer-list">
          <?php foreach ($coupons as $c): ?>
            <li>
              <span class="oi">🏷️</span>
              <span>
                <b><?= $c['type'] === 'flat' ? money($c['value']) . ' off' : (int) $c['value'] . '% off' ?></b>
                on orders above <?= money($c['min_order']) ?> with code
                <button class="chip tag-green" type="button" data-copy="<?= e($c['code']) ?>"><?= e($c['code']) ?> 📋</button>
              </span>
            </li>
          <?php endforeach; ?>
          <li><span class="oi">🏦</span><span><b>5% cashback</b> on Market Wallet payments (demo)</span></li>
        </ul>
      </div>

      <div class="panel panel-pad" style="margin-top:14px">
        <h3 style="font-size:13px;margin-bottom:10px">Delivery to your pincode</h3>
        <form class="pincode" data-pincode data-slot="<?= e(delivery_estimate()) ?>">
          <input class="input" type="text" inputmode="numeric" maxlength="6" placeholder="Enter 6-digit pincode" aria-label="Pincode">
          <button class="btn btn-ghost" type="submit">Check</button>
        </form>
        <p class="pincode-result muted tiny" style="margin-top:8px">We deliver to 1,400+ pincodes across India.</p>
      </div>

      <?php if ($highlights): ?>
        <div class="panel panel-pad" style="margin-top:14px">
          <h3 style="font-size:13px;margin-bottom:10px">Highlights</h3>
          <ul class="offer-list">
            <?php foreach ($highlights as $h): ?>
              <li><span class="oi">•</span><span><?= e($h) ?></span></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ---------------------------------------------------- detail tabs -->
  <section class="section panel panel-pad" id="details">
    <div class="tabs" data-tabs>
      <button class="is-on" type="button" data-tab="desc">Description</button>
      <button type="button" data-tab="specs">Product details</button>
      <button type="button" data-tab="revs">Ratings &amp; reviews (<?= (int) $product['rating_count'] ?>)</button>
    </div>

    <div class="tab-panel" data-tab-panel="desc">
      <?php foreach (preg_split('/\n{2,}/', (string) $product['description']) as $para): ?>
        <p style="max-width:75ch;color:var(--ink-2)"><?= e(trim($para)) ?></p>
      <?php endforeach; ?>
    </div>

    <div class="tab-panel" data-tab-panel="specs" hidden>
      <table class="spec-table">
        <tr><th>Product name</th><td><?= e($product['name']) ?></td></tr>
        <tr><th>Brand</th><td><?= e($product['brand_name'] ?? '—') ?></td></tr>
        <tr><th>Category</th><td><?= e($product['category_name']) ?></td></tr>
        <tr><th>Pack size</th><td><?= e($product['unit']) ?></td></tr>
        <tr><th>SKU</th><td><?= e($product['sku']) ?></td></tr>
        <tr><th>MRP</th><td><?= money($product['mrp']) ?> (inclusive of all taxes)</td></tr>
        <tr><th>Selling price</th><td><?= money($product['price']) ?></td></tr>
        <tr><th>Food type</th><td><?= (int) $product['is_veg'] === 1 ? 'Vegetarian' : 'Contains egg' ?></td></tr>
        <tr><th>Organic</th><td><?= (int) $product['is_organic'] === 1 ? 'Yes, certified' : 'No' ?></td></tr>
        <tr><th>Availability</th><td><?= $inStock ? (int) $product['stock'] . ' units in stock' : 'Out of stock' ?></td></tr>
        <tr><th>Country of origin</th><td>India</td></tr>
        <tr><th>Seller</th><td><?= e(STORE_NAME) ?> Retail Pvt Ltd</td></tr>
      </table>
    </div>

    <div class="tab-panel" data-tab-panel="revs" hidden>
      <div id="reviews">
        <div class="review-summary">
          <div class="review-score">
            <div class="big"><?= (int) $product['rating_count'] > 0 ? number_format($rating, 1) : '—' ?></div>
            <?= stars($rating, 'lg') ?>
            <p class="muted tiny" style="margin-top:6px"><?= compact_number((int) $product['rating_count']) ?> ratings</p>
          </div>
          <div class="review-bars">
            <?php $totalRatings = max(1, array_sum($breakdown)); ?>
            <?php foreach ([5, 4, 3, 2, 1] as $starLevel): ?>
              <div class="review-bar">
                <span><?= $starLevel ?> ★</span>
                <span class="track"><span class="fill" style="width:<?= round($breakdown[$starLevel] / $totalRatings * 100) ?>%"></span></span>
                <span class="muted"><?= $breakdown[$starLevel] ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <hr>

        <?php if ($reviewError): ?><div class="alert alert-error"><?= e($reviewError) ?></div><?php endif; ?>

        <?php if (is_logged_in()): ?>
          <form method="post" class="panel panel-pad" style="background:var(--green-50);border:0">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="review">
            <h3 style="font-size:14px;margin-bottom:10px"><?= $myReview ? 'Update your review' : 'Rate this product' ?></h3>
            <div class="field">
              <div class="star-picker">
                <?php foreach ([5, 4, 3, 2, 1] as $starValue): ?>
                  <input type="radio" id="star<?= $starValue ?>" name="rating" value="<?= $starValue ?>"
                         <?= (int) ($myReview['rating'] ?? 0) === $starValue ? 'checked' : '' ?>>
                  <label for="star<?= $starValue ?>" title="<?= $starValue ?> stars">★</label>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="field">
              <input class="input" type="text" name="title" maxlength="140" placeholder="Sum it up in a few words"
                     value="<?= e($myReview['title'] ?? '') ?>">
            </div>
            <div class="field">
              <textarea class="input" name="body" rows="3" placeholder="What did you like or dislike?"><?= e($myReview['body'] ?? '') ?></textarea>
            </div>
            <button class="btn" type="submit"><?= $myReview ? 'Update review' : 'Post review' ?></button>
          </form>
        <?php else: ?>
          <div class="alert alert-info">
            <a href="login.php?next=<?= urlencode('product.php?slug=' . $slug) ?>"><strong>Log in</strong></a> to rate this product.
          </div>
        <?php endif; ?>

        <div style="margin-top:18px">
          <?php if (!$reviews): ?>
            <p class="muted">No written reviews yet.</p>
          <?php endif; ?>
          <?php foreach ($reviews as $r): ?>
            <div class="review">
              <span class="avatar" style="background:<?= e($r['avatar_color']) ?>"><?= e(initials($r['user_name'])) ?></span>
              <div class="review-body">
                <div class="review-head">
                  <span class="rating-chip"><?= (int) $r['rating'] ?> ★</span>
                  <strong><?= e($r['title'] ?: 'Verified purchase') ?></strong>
                  <span class="muted tiny">· <?= e($r['user_name']) ?> · <?= e(time_ago($r['created_at'])) ?></span>
                </div>
                <?php if ($r['body']): ?><p><?= e($r['body']) ?></p><?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <?php if ($related): ?>
    <section class="section">
      <div class="section-head">
        <div><h2>Similar in <?= e($product['category_name']) ?></h2></div>
        <a class="more" href="products.php?category=<?= e($product['category_slug']) ?>">View all →</a>
      </div>
      <div class="rail"><?php foreach ($related as $p) { include __DIR__ . '/includes/product-card.php'; } ?></div>
    </section>
  <?php endif; ?>

  <section class="section">
    <div class="section-head"><div><h2>Customers also bought</h2></div></div>
    <div class="rail"><?php foreach ($alsoBought as $p) { include __DIR__ . '/includes/product-card.php'; } ?></div>
  </section>

  <?php if ($recent): ?>
    <section class="section">
      <div class="section-head"><div><h2>Recently viewed</h2></div></div>
      <div class="rail"><?php foreach ($recent as $p) { include __DIR__ . '/includes/product-card.php'; } ?></div>
    </section>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
