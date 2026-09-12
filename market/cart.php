<?php
/** Basket: quantities, coupons, saved-for-later, order summary. */

require_once __DIR__ . '/includes/bootstrap.php';

$couponError = null;

if (is_post()) {
    require_csrf();
    $form = $_POST['form'] ?? '';

    if ($form === 'coupon-apply') {
        $subtotal = cart_totals()['subtotal'];
        [$coupon, $couponError] = find_valid_coupon((string) ($_POST['code'] ?? ''), $subtotal);
        if ($coupon) {
            $_SESSION['coupon'] = $coupon['code'];
            flash('Coupon ' . $coupon['code'] . ' applied — you saved ' . money(coupon_discount($coupon, $subtotal)) . '.');
            redirect('cart.php');
        }
    } elseif ($form === 'coupon-remove') {
        unset($_SESSION['coupon']);
        redirect('cart.php');
    } elseif ($form === 'remove') {
        cart_remove((int) ($_POST['product_id'] ?? 0));
        flash('Item removed from your basket.');
        redirect('cart.php');
    } elseif ($form === 'save-for-later') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        if (!is_logged_in()) {
            redirect('login.php?next=' . urlencode('cart.php'));
        }
        if (!in_wishlist($productId)) {
            wishlist_toggle($productId);
        }
        cart_remove($productId);
        flash('Moved to your favourites.');
        redirect('cart.php');
    } elseif ($form === 'clear') {
        cart_clear();
        unset($_SESSION['coupon']);
        flash('Basket emptied.');
        redirect('cart.php');
    }
}

$base    = cart_totals();
$coupon  = active_coupon($base['subtotal']);
$totals  = cart_totals($coupon, $base['items']);
$items   = $totals['items'];
$suggest = products(['sort' => 'popularity', 'in_stock' => 1, 'per_page' => 10]);

$pageTitle = 'Your basket (' . $totals['units'] . ') | ' . STORE_NAME;
require __DIR__ . '/includes/header.php';
?>

<div class="wrap page">
  <nav class="breadcrumb"><a href="index.php">Home</a> <span class="sep">›</span> <span>Basket</span></nav>

  <?php if (!$items): ?>
    <div class="panel empty">
      <span class="empty-emoji">🛍️</span>
      <h2>Your basket is empty</h2>
      <p>Add a few essentials and they will show up here. Groceries you add stay in your basket even after you close the tab.</p>
      <p style="margin-top:18px"><a class="btn btn-lg" href="products.php">Start shopping</a></p>
    </div>

    <section class="section">
      <div class="section-head"><div><h2>Popular right now</h2></div></div>
      <div class="rail"><?php foreach ($suggest as $p) { include __DIR__ . '/includes/product-card.php'; } ?></div>
    </section>

  <?php else: ?>

    <div class="steps">
      <span class="step is-on"><span class="dot">1</span> Basket</span>
      <span class="step-line"></span>
      <span class="step"><span class="dot">2</span> Address</span>
      <span class="step-line"></span>
      <span class="step"><span class="dot">3</span> Payment</span>
    </div>

    <div class="two-col">
      <div class="panel">
        <div class="row-between" style="padding:14px 18px;border-bottom:1px solid var(--line)">
          <strong><?= $totals['lines'] ?> item<?= $totals['lines'] === 1 ? '' : 's' ?> in your basket</strong>
          <form method="post" onsubmit="return confirm('Empty your whole basket?')">
            <?= csrf_field() ?><input type="hidden" name="form" value="clear">
            <button class="link-btn" type="submit">Empty basket</button>
          </form>
        </div>

        <?php foreach ($items as $item): ?>
          <?php $lineTotal = (float) $item['price'] * (int) $item['qty']; ?>
          <div class="cart-line">
            <a class="cart-thumb" href="product.php?slug=<?= e($item['slug']) ?>" style="background:<?= e($item['tint']) ?>">
              <span aria-hidden="true"><?= e($item['emoji']) ?></span>
            </a>

            <div class="cart-info">
              <h3><a href="product.php?slug=<?= e($item['slug']) ?>"><?= e($item['name']) ?></a></h3>
              <span class="muted tiny"><?= e($item['unit']) ?></span>
              <?php if ((int) $item['stock'] <= 5): ?>
                <p class="tiny" style="color:var(--orange);font-weight:700;margin:4px 0 0">Only <?= (int) $item['stock'] ?> left</p>
              <?php endif; ?>
              <p class="tiny muted" style="margin:4px 0 0">🚚 <?= e(delivery_estimate()) ?></p>

              <div class="cart-actions">
                <div class="qty" data-product-id="<?= (int) $item['id'] ?>" data-max="<?= min(MAX_QTY_PER_ITEM, (int) $item['stock']) ?>">
                  <button type="button" data-qty-step="-1" aria-label="Reduce quantity">−</button>
                  <output><?= (int) $item['qty'] ?></output>
                  <button type="button" data-qty-step="1" aria-label="Increase quantity">+</button>
                </div>
                <form method="post"><?= csrf_field() ?>
                  <input type="hidden" name="form" value="save-for-later">
                  <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                  <button class="link-btn save" type="submit">♥ Save for later</button>
                </form>
                <form method="post"><?= csrf_field() ?>
                  <input type="hidden" name="form" value="remove">
                  <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                  <button class="link-btn" type="submit">Remove</button>
                </form>
              </div>
            </div>

            <div class="cart-right">
              <strong><?= money($lineTotal) ?></strong>
              <?php if (discount_pct($item['price'], $item['mrp']) > 0): ?>
                <s><?= money((float) $item['mrp'] * (int) $item['qty']) ?></s>
                <span class="tiny" style="color:var(--green-700);font-weight:700">
                  <?= discount_pct($item['price'], $item['mrp']) ?>% off
                </span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ------------------------------------------------ order summary -->
      <aside class="panel summary">
        <h3>Price details</h3>

        <?php if ($coupon): ?>
          <div class="coupon-on">
            <span>🏷️ <?= e($coupon['code']) ?> applied</span>
            <form method="post"><?= csrf_field() ?>
              <input type="hidden" name="form" value="coupon-remove">
              <button class="link-btn" type="submit">Remove</button>
            </form>
          </div>
        <?php else: ?>
          <form class="coupon-box" method="post">
            <?= csrf_field() ?><input type="hidden" name="form" value="coupon-apply">
            <input class="input" type="text" name="code" placeholder="Coupon code" aria-label="Coupon code" required>
            <button class="btn btn-ghost" type="submit">Apply</button>
          </form>
          <?php if ($couponError): ?>
            <p class="error-text" style="padding:0 18px 10px"><?= e($couponError) ?></p>
          <?php endif; ?>
          <p class="tiny muted" style="padding:0 18px 10px">
            Try <button class="link-btn save" type="button" data-copy="MARKET50">MARKET50</button> or
            <button class="link-btn save" type="button" data-copy="FRESH10">FRESH10</button> ·
            <a href="offers.php">all offers</a>
          </p>
        <?php endif; ?>

        <div class="sum-rows">
          <div class="sum-row"><span>Price (<?= $totals['units'] ?> item<?= $totals['units'] === 1 ? '' : 's' ?>)</span><b><?= money($totals['mrp_total']) ?></b></div>
          <?php if ($totals['product_saving'] > 0): ?>
            <div class="sum-row is-off"><span>Product discount</span><b>− <?= money($totals['product_saving']) ?></b></div>
          <?php endif; ?>
          <?php if ($totals['discount'] > 0): ?>
            <div class="sum-row is-off"><span>Coupon (<?= e($totals['coupon_code']) ?>)</span><b>− <?= money($totals['discount']) ?></b></div>
          <?php endif; ?>
          <div class="sum-row <?= $totals['free_delivery'] ? 'is-free' : '' ?>">
            <span>Delivery fee</span>
            <b><?= $totals['free_delivery'] ? 'FREE' : money($totals['delivery_fee']) ?></b>
          </div>
          <?php if ($totals['handling_fee'] > 0): ?>
            <div class="sum-row"><span>Handling charge</span><b><?= money($totals['handling_fee']) ?></b></div>
          <?php endif; ?>
        </div>

        <div class="sum-total"><span>Total payable</span><span><?= money($totals['total']) ?></span></div>

        <?php if ($totals['total_saving'] > 0): ?>
          <div class="sum-saving">🎉 You save <?= money($totals['total_saving']) ?> on this order</div>
        <?php endif; ?>

        <?php if (!$totals['free_delivery']): ?>
          <div class="freebar">
            Add <b><?= money($totals['away_from_free']) ?></b> more for free delivery
            <span class="track"><span class="fill" style="width:<?= min(100, round(($totals['subtotal'] / FREE_DELIVERY_ABOVE) * 100)) ?>%"></span></span>
          </div>
        <?php endif; ?>

        <a class="btn btn-accent btn-lg" href="checkout.php">Proceed to checkout →</a>
      </aside>
    </div>

    <section class="section">
      <div class="section-head"><div><h2>Frequently bought together</h2></div></div>
      <div class="rail"><?php foreach ($suggest as $p) { include __DIR__ . '/includes/product-card.php'; } ?></div>
    </section>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
