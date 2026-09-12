<?php
/** Coupons and deal collections. */

require_once __DIR__ . '/includes/bootstrap.php';

$coupons  = qa('SELECT * FROM coupons WHERE is_active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE()) ORDER BY min_order');
$deals    = products(['sort' => 'discount', 'discount' => 25, 'in_stock' => 1, 'per_page' => 12]);
$under99  = products(['max_price' => 99, 'in_stock' => 1, 'sort' => 'popularity', 'per_page' => 12]);
$organic  = products(['organic' => 1, 'per_page' => 12]);

$pageTitle = 'Offers &amp; coupons | ' . STORE_NAME;
require __DIR__ . '/includes/header.php';
?>

<div class="wrap page">
  <nav class="breadcrumb"><a href="index.php">Home</a> <span class="sep">›</span> <span>Offers</span></nav>

  <div class="deal-strip" style="margin-top:0">
    <div>
      <h2>🎁 Coupons &amp; offers</h2>
      <p>Apply any of these in your basket before you pay.</p>
    </div>
  </div>

  <section class="section">
    <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr))">
      <?php foreach ($coupons as $c): ?>
        <div class="panel panel-pad">
          <div class="row-between" style="align-items:flex-start">
            <div>
              <span class="chip tag-green" style="font-size:15px;font-weight:800;letter-spacing:.06em"><?= e($c['code']) ?></span>
              <p style="margin:10px 0 4px;font-weight:700">
                <?= $c['type'] === 'flat' ? money($c['value']) . ' off' : (int) $c['value'] . '% off' ?>
                <?php if ($c['max_discount'] !== null): ?>
                  <span class="muted tiny">(up to <?= money($c['max_discount']) ?>)</span>
                <?php endif; ?>
              </p>
              <p class="muted tiny" style="margin:0"><?= e($c['description']) ?></p>
            </div>
            <span style="font-size:26px">🏷️</span>
          </div>
          <ul class="offer-list" style="margin-top:12px">
            <li><span class="oi">•</span><span>Minimum order <?= money($c['min_order']) ?></span></li>
            <li><span class="oi">•</span><span>
              <?= $c['expires_at'] ? 'Valid till ' . date('j M Y', strtotime($c['expires_at'])) : 'No expiry date' ?>
            </span></li>
            <?php if ($c['usage_limit'] !== null): ?>
              <li><span class="oi">•</span><span><?= max(0, (int) $c['usage_limit'] - (int) $c['used_count']) ?> uses left</span></li>
            <?php endif; ?>
          </ul>
          <button class="btn btn-ghost btn-block" type="button" data-copy="<?= e($c['code']) ?>" style="margin-top:12px">Copy code</button>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="section">
    <div class="section-head">
      <div><h2>💸 25% off or more</h2><p>The deepest cuts in the store today.</p></div>
      <a class="more" href="products.php?discount=25&amp;sort=discount">View all →</a>
    </div>
    <div class="rail"><?php foreach ($deals as $p) { include __DIR__ . '/includes/product-card.php'; } ?></div>
  </section>

  <section class="section">
    <div class="section-head">
      <div><h2>🪙 Everything under <?= money(99) ?></h2><p>Small basket, big haul.</p></div>
      <a class="more" href="products.php?max_price=99">View all →</a>
    </div>
    <div class="rail"><?php foreach ($under99 as $p) { include __DIR__ . '/includes/product-card.php'; } ?></div>
  </section>

  <section class="section">
    <div class="section-head">
      <div><h2>🌱 Certified organic</h2><p>Grown without synthetic pesticides.</p></div>
      <a class="more" href="products.php?organic=1">View all →</a>
    </div>
    <div class="rail"><?php foreach ($organic as $p) { include __DIR__ . '/includes/product-card.php'; } ?></div>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
