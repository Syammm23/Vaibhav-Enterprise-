<?php
/** Favourites. */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

if (is_post()) {
    require_csrf();
    if (($_POST['form'] ?? '') === 'move-all') {
        $moved = 0;
        foreach (wishlist_ids() as $id) {
            [$ok] = cart_add($id, 1);
            if ($ok) {
                q('DELETE FROM wishlist_items WHERE user_id = ? AND product_id = ?', [user_id(), $id]);
                $moved++;
            }
        }
        flash($moved > 0 ? $moved . ' item(s) moved to your basket.' : 'Nothing could be moved — those items are out of stock.',
              $moved > 0 ? 'success' : 'error');
        redirect('wishlist.php');
    }
}

$items = qa(
    'SELECT p.*, b.name AS brand_name, w.added_at
       FROM wishlist_items w
       JOIN products p ON p.id = w.product_id
  LEFT JOIN brands b   ON b.id = p.brand_id
      WHERE w.user_id = ? AND p.is_active = 1
   ORDER BY w.added_at DESC',
    [user_id()]
);

$pageTitle = 'Your favourites | ' . STORE_NAME;
require __DIR__ . '/includes/header.php';
?>

<div class="wrap page">
  <nav class="breadcrumb"><a href="index.php">Home</a> <span class="sep">›</span> <span>Favourites</span></nav>

  <div class="row-between" style="margin-bottom:16px">
    <div>
      <h1 style="font-size:22px">❤️ Your favourites</h1>
      <p class="muted tiny"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?> saved</p>
    </div>
    <?php if ($items): ?>
      <form method="post"><?= csrf_field() ?>
        <input type="hidden" name="form" value="move-all">
        <button class="btn" type="submit">Move all to basket</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!$items): ?>
    <div class="panel empty">
      <span class="empty-emoji">💚</span>
      <h2>No favourites yet</h2>
      <p>Tap the heart on any product to keep it here for later.</p>
      <p style="margin-top:18px"><a class="btn btn-lg" href="products.php">Find something you like</a></p>
    </div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($items as $p) { include __DIR__ . '/includes/product-card.php'; } ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
