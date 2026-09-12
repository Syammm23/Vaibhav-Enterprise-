<?php
/**
 * One product tile.
 * Expects $p (product row). Optional: $cardClass.
 */
$rating   = rating_of($p);
$off      = discount_pct($p['price'], $p['mrp']);
$outOfStock = (int) $p['stock'] < 1;
$saved    = in_wishlist((int) $p['id']);
?>
<article class="card<?= $outOfStock ? ' is-out' : '' ?> <?= e($cardClass ?? '') ?>" data-product="<?= (int) $p['id'] ?>">
  <a class="card-media" href="product.php?slug=<?= e($p['slug']) ?>" style="background:<?= e($p['tint']) ?>">
    <span class="card-emoji" aria-hidden="true"><?= e($p['emoji']) ?></span>
    <?php if ($off > 0): ?><span class="card-off"><?= $off ?>% OFF</span><?php endif; ?>
    <?php if ($outOfStock): ?><span class="card-oos">Out of stock</span><?php endif; ?>
    <?php if ((int) $p['is_organic'] === 1): ?><span class="card-organic" title="Certified organic">🌱</span><?php endif; ?>
  </a>

  <button class="card-fav<?= $saved ? ' is-on' : '' ?>" type="button"
          data-wishlist="<?= (int) $p['id'] ?>"
          aria-label="<?= $saved ? 'Remove from favourites' : 'Save to favourites' ?>"
          aria-pressed="<?= $saved ? 'true' : 'false' ?>">♥</button>

  <div class="card-body">
    <?php if (!empty($p['brand_name'])): ?>
      <span class="card-brand"><?= e($p['brand_name']) ?></span>
    <?php endif; ?>
    <h3 class="card-title"><a href="product.php?slug=<?= e($p['slug']) ?>"><?= e($p['name']) ?></a></h3>
    <span class="card-unit"><?= e($p['unit']) ?></span>

    <?php if ((int) $p['rating_count'] > 0): ?>
      <span class="card-rating">
        <span class="rating-chip"><?= number_format($rating, 1) ?> ★</span>
        <small>(<?= compact_number((int) $p['rating_count']) ?>)</small>
      </span>
    <?php else: ?>
      <span class="card-rating"><small class="muted">New arrival</small></span>
    <?php endif; ?>

    <div class="card-price">
      <strong><?= money($p['price']) ?></strong>
      <?php if ($off > 0): ?><s><?= money($p['mrp']) ?></s><?php endif; ?>
    </div>

    <?php if ($outOfStock): ?>
      <button class="btn btn-out btn-block" type="button" disabled>Notify me</button>
    <?php else: ?>
      <button class="btn btn-add btn-block" type="button" data-add-to-cart="<?= (int) $p['id'] ?>">Add to basket</button>
    <?php endif; ?>
  </div>
</article>
