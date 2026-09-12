<?php
/** Order history with search and status filter. */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$statusFilter = (string) ($_GET['status'] ?? '');
$term         = trim((string) ($_GET['find'] ?? ''));

$where  = ['o.user_id = ?'];
$params = [user_id()];

if (in_array($statusFilter, ['placed', 'packed', 'shipped', 'out_for_delivery', 'delivered', 'cancelled'], true)) {
    $where[]  = 'o.status = ?';
    $params[] = $statusFilter;
}
if ($term !== '') {
    $where[]  = '(o.order_number LIKE ? OR EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.id AND oi.name LIKE ?))';
    $params[] = '%' . $term . '%';
    $params[] = '%' . $term . '%';
}

$orders = qa(
    'SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
       FROM orders o WHERE ' . implode(' AND ', $where) . ' ORDER BY o.placed_at DESC',
    $params
);

$pageTitle = 'Your orders | ' . STORE_NAME;
require __DIR__ . '/includes/header.php';
?>

<div class="wrap page">
  <nav class="breadcrumb"><a href="index.php">Home</a> <span class="sep">›</span> <span>Orders</span></nav>

  <div class="toolbar">
    <div>
      <h1>📦 Your orders</h1>
      <span class="count"><?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?></span>
    </div>
    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap">
      <input class="input" type="search" name="find" value="<?= e($term) ?>" placeholder="Search orders or products" style="min-width:200px">
      <select class="select" name="status" onchange="this.form.submit()" style="width:auto">
        <option value="">All statuses</option>
        <?php foreach (['placed', 'packed', 'shipped', 'out_for_delivery', 'delivered', 'cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-ghost" type="submit">Search</button>
    </form>
  </div>

  <?php if (!$orders): ?>
    <div class="panel empty">
      <span class="empty-emoji">📦</span>
      <h2><?= $term !== '' || $statusFilter !== '' ? 'No orders match that' : 'No orders yet' ?></h2>
      <p><?= $term !== '' || $statusFilter !== '' ? 'Try clearing the filters.' : 'Once you place an order it will show up here with live tracking.' ?></p>
      <p style="margin-top:18px"><a class="btn btn-lg" href="products.php">Start shopping</a></p>
    </div>
  <?php else: ?>
    <?php foreach ($orders as $o): ?>
      <?php $lineItems = qa('SELECT emoji, tint, name, image, product_id AS id FROM order_items WHERE order_id = ? LIMIT 5', [$o['id']]); ?>
      <div class="order-card">
        <div class="order-head">
          <div>
            <strong><?= e($o['order_number']) ?></strong>
            <span class="muted"> · placed <?= e(date('j M Y, g:i A', strtotime($o['placed_at']))) ?></span>
          </div>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <span class="status-tag st-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span>
            <span class="chip <?= $o['payment_status'] === 'paid' ? 'tag-green' : ($o['payment_status'] === 'failed' ? 'tag-red' : 'tag-amber') ?>">
              <?= e(ucfirst($o['payment_status'])) ?>
            </span>
          </div>
        </div>
        <div class="order-body">
          <div class="order-thumbs">
            <?php foreach ($lineItems as $li): ?>
              <span style="--tile:<?= e($li['tint']) ?>" title="<?= e($li['name']) ?>"><?= product_img($li, 46) ?></span>
            <?php endforeach; ?>
          </div>
          <div style="flex:1;min-width:160px">
            <strong><?= (int) $o['item_count'] ?> item<?= (int) $o['item_count'] === 1 ? '' : 's' ?> · <?= money($o['total']) ?></strong>
            <p class="muted tiny" style="margin:2px 0 0">
              <?= e(payment_label($o['payment_method'])) ?> · <?= e($o['slot']) ?>
            </p>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn btn-ghost btn-sm" href="order.php?number=<?= e($o['order_number']) ?>">View details</a>
            <?php if ($o['payment_status'] === 'failed'): ?>
              <a class="btn btn-sm" href="payment.php?order=<?= e($o['order_number']) ?>">Retry payment</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
