<?php
/** Admin dashboard: revenue, orders, stock alerts, recent activity. */

$pageTitle = 'Dashboard';
$adminPage = 'dash';
require __DIR__ . '/header.php';

$revenue    = (float) qv('SELECT COALESCE(SUM(total), 0) FROM orders WHERE status <> "cancelled"');
$revenue30  = (float) qv('SELECT COALESCE(SUM(total), 0) FROM orders WHERE status <> "cancelled" AND placed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
$orderCount = (int) qv('SELECT COUNT(*) FROM orders');
$pending    = (int) qv('SELECT COUNT(*) FROM orders WHERE status IN ("placed","packed")');
$customers  = (int) qv('SELECT COUNT(*) FROM users WHERE role = "customer"');
$productN   = (int) qv('SELECT COUNT(*) FROM products WHERE is_active = 1');
$outOfStock = (int) qv('SELECT COUNT(*) FROM products WHERE stock = 0 AND is_active = 1');
$unpaid     = (int) qv('SELECT COUNT(*) FROM orders WHERE payment_status IN ("pending","failed") AND status <> "cancelled"');

$recentOrders = qa('SELECT o.*, u.name AS customer
                      FROM orders o JOIN users u ON u.id = o.user_id
                  ORDER BY o.placed_at DESC LIMIT 8');

$lowStock = qa('SELECT id, name, unit, stock, emoji, tint, image FROM products
                 WHERE is_active = 1 AND stock <= 10 ORDER BY stock ASC LIMIT 8');

$topProducts = qa('SELECT p.id, p.name, p.emoji, p.tint, p.image, SUM(oi.qty) AS sold, SUM(oi.line_total) AS revenue
                     FROM order_items oi JOIN products p ON p.id = oi.product_id
                     JOIN orders o ON o.id = oi.order_id AND o.status <> "cancelled"
                 GROUP BY p.id ORDER BY sold DESC LIMIT 6');

$byStatus = [];
foreach (qa('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $row) {
    $byStatus[$row['status']] = (int) $row['n'];
}
?>

<div class="admin-head">
  <div>
    <h1>Dashboard</h1>
    <p class="muted tiny">Signed in as <?= e(current_user()['name']) ?> · <?= e(date('l, j M Y')) ?></p>
  </div>
  <div style="display:flex;gap:8px">
    <a class="btn btn-ghost btn-sm" href="products.php?action=new">+ Add product</a>
    <a class="btn btn-sm" href="orders.php">Manage orders</a>
  </div>
</div>

<div class="stat-grid">
  <div class="stat"><small>Total revenue</small><strong><?= money($revenue) ?></strong><span class="delta"><?= money($revenue30) ?> in last 30 days</span></div>
  <div class="stat"><small>Orders</small><strong><?= number_format($orderCount) ?></strong><span class="delta"><?= $pending ?> awaiting dispatch</span></div>
  <div class="stat"><small>Customers</small><strong><?= number_format($customers) ?></strong><span class="delta">registered shoppers</span></div>
  <div class="stat"><small>Active products</small><strong><?= number_format($productN) ?></strong><span class="delta" style="color:<?= $outOfStock ? 'var(--red)' : 'var(--green-700)' ?>"><?= $outOfStock ?> out of stock</span></div>
  <div class="stat"><small>Unpaid orders</small><strong><?= $unpaid ?></strong><span class="delta">pending or failed payment</span></div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:16px;align-items:start" class="admin-grid">
  <div>
    <h2 style="font-size:16px;margin-bottom:10px">Recent orders</h2>
    <div class="table-wrap" style="margin-bottom:22px">
      <table class="data">
        <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Placed</th><th></th></tr></thead>
        <tbody>
          <?php if (!$recentOrders): ?>
            <tr><td colspan="7" class="muted">No orders yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($recentOrders as $o): ?>
            <tr>
              <td><strong><?= e($o['order_number']) ?></strong></td>
              <td><?= e($o['customer']) ?></td>
              <td><?= money($o['total']) ?></td>
              <td><span class="chip <?= $o['payment_status'] === 'paid' ? 'tag-green' : ($o['payment_status'] === 'failed' ? 'tag-red' : 'tag-amber') ?>"><?= e(ucfirst($o['payment_status'])) ?></span></td>
              <td><span class="status-tag st-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
              <td class="muted"><?= e(date('j M, g:i A', strtotime($o['placed_at']))) ?></td>
              <td><a class="btn btn-ghost btn-sm" href="orders.php?id=<?= (int) $o['id'] ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <h2 style="font-size:16px;margin-bottom:10px">Best selling products</h2>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th></th><th>Product</th><th>Units sold</th><th>Revenue</th></tr></thead>
        <tbody>
          <?php if (!$topProducts): ?>
            <tr><td colspan="4" class="muted">No sales recorded yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($topProducts as $p): ?>
            <tr>
              <td><span class="mini-thumb" style="--tile:<?= e($p['tint']) ?>"><?= product_img($p, 34) ?></span></td>
              <td class="wrap-cell"><?= e($p['name']) ?></td>
              <td><?= (int) $p['sold'] ?></td>
              <td><?= money($p['revenue']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <aside>
    <div class="panel panel-pad" style="margin-bottom:16px">
      <h2 style="font-size:15px;margin-bottom:12px">Orders by status</h2>
      <?php
      $totalOrders = max(1, array_sum($byStatus));
      foreach (['placed', 'packed', 'shipped', 'out_for_delivery', 'delivered', 'cancelled'] as $s):
          $n = $byStatus[$s] ?? 0; ?>
        <div class="review-bar" style="grid-template-columns:110px 1fr 34px;margin-bottom:7px">
          <span class="tiny"><?= e(status_label($s)) ?></span>
          <span class="track"><span class="fill" style="width:<?= round($n / $totalOrders * 100) ?>%;background:<?= $s === 'cancelled' ? 'var(--red)' : 'var(--green-500)' ?>"></span></span>
          <span class="tiny muted"><?= $n ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="panel panel-pad">
      <h2 style="font-size:15px;margin-bottom:12px">⚠️ Low stock</h2>
      <?php if (!$lowStock): ?>
        <p class="muted tiny">Everything is comfortably stocked.</p>
      <?php endif; ?>
      <?php foreach ($lowStock as $p): ?>
        <div style="display:flex;gap:10px;align-items:center;padding:8px 0;border-top:1px solid var(--line-2)">
          <span class="mini-thumb" style="--tile:<?= e($p['tint']) ?>"><?= product_img($p, 34) ?></span>
          <span style="flex:1;min-width:0">
            <strong style="font-size:12.5px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($p['name']) ?></strong>
            <small class="muted"><?= e($p['unit']) ?></small>
          </span>
          <span class="chip <?= (int) $p['stock'] === 0 ? 'tag-red' : 'tag-amber' ?>"><?= (int) $p['stock'] ?> left</span>
        </div>
      <?php endforeach; ?>
      <a class="btn btn-ghost btn-block btn-sm" href="products.php?sort=stock" style="margin-top:12px">Manage stock</a>
    </div>
  </aside>
</div>

<?php require __DIR__ . '/footer.php'; ?>
