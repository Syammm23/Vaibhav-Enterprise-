<?php
/** Admin: order queue and single-order management. */

$pageTitle = 'Orders';
$adminPage = 'orders';
require __DIR__ . '/header.php';

$flow = ['placed', 'packed', 'shipped', 'out_for_delivery', 'delivered', 'cancelled'];

if (is_post()) {
    require_csrf();
    $orderId = (int) ($_POST['id'] ?? 0);
    $order   = q1('SELECT * FROM orders WHERE id = ?', [$orderId]);

    if (!$order) {
        flash('That order no longer exists.', 'error');
        redirect('orders.php');
    }

    if (($_POST['form'] ?? '') === 'status') {
        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, $flow, true)) {
            flash('Unknown status.', 'error');
            redirect('orders.php?id=' . $orderId);
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Cancelling releases the reserved stock; nothing else touches inventory.
            if ($status === 'cancelled' && $order['status'] !== 'cancelled') {
                foreach (qa('SELECT product_id, qty FROM order_items WHERE order_id = ?', [$orderId]) as $line) {
                    if ($line['product_id'] !== null) {
                        q('UPDATE products SET stock = stock + ?, sold_count = GREATEST(sold_count - ?, 0) WHERE id = ?',
                          [(int) $line['qty'], (int) $line['qty'], $line['product_id']]);
                    }
                }
                $paymentStatus = $order['payment_status'] === 'paid' ? 'refunded' : $order['payment_status'];
                q('UPDATE orders SET status = ?, payment_status = ? WHERE id = ?', [$status, $paymentStatus, $orderId]);
            } else {
                // Cash on delivery settles the moment it is handed over.
                $paymentStatus = ($status === 'delivered' && $order['payment_method'] === 'cod' && $order['payment_status'] === 'pending')
                    ? 'paid' : $order['payment_status'];
                q('UPDATE orders SET status = ?, payment_status = ? WHERE id = ?', [$status, $paymentStatus, $orderId]);
            }
            $pdo->commit();
            flash('Order marked as ' . status_label($status) . '.');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            flash('Could not update that order.', 'error');
        }
        redirect('orders.php?id=' . $orderId);
    }

    if (($_POST['form'] ?? '') === 'payment') {
        $paymentStatus = (string) ($_POST['payment_status'] ?? '');
        if (in_array($paymentStatus, ['pending', 'paid', 'failed', 'refunded'], true)) {
            q('UPDATE orders SET payment_status = ? WHERE id = ?', [$paymentStatus, $orderId]);
            flash('Payment status updated.');
        }
        redirect('orders.php?id=' . $orderId);
    }
}

// ------------------------------------------------------- single order view
$viewId = (int) ($_GET['id'] ?? 0);
if ($viewId > 0) {
    $order = q1('SELECT o.*, u.name AS customer, u.email, u.phone AS user_phone
                   FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?', [$viewId]);
    if (!$order) {
        flash('Order not found.', 'error');
        redirect('orders.php');
    }
    $items    = qa('SELECT * FROM order_items WHERE order_id = ?', [$viewId]);
    $payments = qa('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC', [$viewId]);
    ?>

    <div class="admin-head">
      <div>
        <h1>Order <?= e($order['order_number']) ?></h1>
        <p class="muted tiny">
          <?= e($order['customer']) ?> · <?= e($order['email']) ?> ·
          placed <?= e(date('j M Y, g:i A', strtotime($order['placed_at']))) ?>
        </p>
      </div>
      <a class="btn btn-ghost btn-sm" href="orders.php">← Back to queue</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 330px;gap:16px;align-items:start" class="admin-grid">
      <div>
        <div class="table-wrap" style="margin-bottom:16px">
          <table class="data">
            <thead><tr><th></th><th>Item</th><th>Unit price</th><th>Qty</th><th>Total</th></tr></thead>
            <tbody>
              <?php foreach ($items as $item): ?>
                <tr>
                  <td><span class="mini-thumb" style="background:<?= e($item['tint']) ?>"><?= e($item['emoji']) ?></span></td>
                  <td class="wrap-cell"><?= e($item['name']) ?><br><small class="muted"><?= e($item['unit']) ?></small></td>
                  <td><?= money($item['price']) ?></td>
                  <td><?= (int) $item['qty'] ?></td>
                  <td><strong><?= money($item['line_total']) ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="panel panel-pad">
          <h2 style="font-size:15px;margin-bottom:12px">Payment attempts</h2>
          <?php if (!$payments): ?><p class="muted tiny">No attempts recorded.</p><?php endif; ?>
          <?php if ($payments): ?>
            <table class="spec-table">
              <tr><th>Transaction</th><th>Detail</th><th>Amount</th><th>Status</th></tr>
              <?php foreach ($payments as $pay): ?>
                <tr>
                  <td><?= e($pay['txn_id']) ?><br><small class="muted"><?= e(date('j M, g:i A', strtotime($pay['created_at']))) ?></small></td>
                  <td><?= e($pay['detail']) ?></td>
                  <td><?= money($pay['amount']) ?></td>
                  <td><span class="chip <?= $pay['status'] === 'success' ? 'tag-green' : ($pay['status'] === 'failed' ? 'tag-red' : 'tag-amber') ?>"><?= e(ucfirst($pay['status'])) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </table>
          <?php endif; ?>
        </div>
      </div>

      <aside>
        <div class="panel panel-pad" style="margin-bottom:16px">
          <h2 style="font-size:15px;margin-bottom:12px">Fulfilment</h2>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="status">
            <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
            <div class="field">
              <label for="status">Order status</label>
              <select class="select" id="status" name="status">
                <?php foreach ($flow as $s): ?>
                  <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
                <?php endforeach; ?>
              </select>
              <p class="hint">Cancelling returns every item to stock.</p>
            </div>
            <button class="btn btn-block" type="submit">Update status</button>
          </form>

          <form method="post" style="margin-top:14px;padding-top:14px;border-top:1px solid var(--line)">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="payment">
            <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
            <div class="field">
              <label for="payment_status">Payment status</label>
              <select class="select" id="payment_status" name="payment_status">
                <?php foreach (['pending', 'paid', 'failed', 'refunded'] as $s): ?>
                  <option value="<?= $s ?>" <?= $order['payment_status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button class="btn btn-ghost btn-block" type="submit">Update payment</button>
          </form>
        </div>

        <div class="panel panel-pad" style="margin-bottom:16px">
          <h2 style="font-size:15px;margin-bottom:10px">Shipping to</h2>
          <p style="font-size:13px;margin:0">
            <strong><?= e($order['ship_name']) ?></strong><br>
            <?= e($order['ship_line1']) ?><?= $order['ship_line2'] ? ', ' . e($order['ship_line2']) : '' ?><br>
            <?= e($order['ship_city']) ?>, <?= e($order['ship_state']) ?> — <?= e($order['ship_pincode']) ?><br>
            📞 <?= e($order['ship_phone']) ?><br>
            <small class="muted">🚚 <?= e($order['slot']) ?></small>
          </p>
        </div>

        <div class="panel panel-pad">
          <h2 style="font-size:15px;margin-bottom:10px">Totals</h2>
          <div class="sum-rows" style="padding:0">
            <div class="sum-row"><span>Subtotal</span><b><?= money($order['subtotal']) ?></b></div>
            <?php if ((float) $order['discount'] > 0): ?>
              <div class="sum-row is-off"><span>Coupon <?= e($order['coupon_code']) ?></span><b>− <?= money($order['discount']) ?></b></div>
            <?php endif; ?>
            <div class="sum-row"><span>Delivery</span><b><?= money($order['delivery_fee']) ?></b></div>
            <div class="sum-row"><span>Handling</span><b><?= money($order['handling_fee']) ?></b></div>
            <div class="sum-row" style="border-top:1px dashed var(--line);padding-top:9px"><span><strong>Total</strong></span><b><?= money($order['total']) ?></b></div>
            <div class="sum-row"><span>Method</span><b><?= e(payment_label($order['payment_method'])) ?></b></div>
          </div>
        </div>
      </aside>
    </div>

    <?php require __DIR__ . '/footer.php'; exit;
}

// ------------------------------------------------------------- order queue
$statusFilter = (string) ($_GET['status'] ?? '');
$term         = trim((string) ($_GET['q'] ?? ''));

$where  = ['1 = 1'];
$params = [];
if (in_array($statusFilter, $flow, true)) {
    $where[]  = 'o.status = ?';
    $params[] = $statusFilter;
}
if ($term !== '') {
    $where[]  = '(o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    array_push($params, '%' . $term . '%', '%' . $term . '%', '%' . $term . '%');
}

$orders = qa('SELECT o.*, u.name AS customer, u.email,
                     (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
                FROM orders o JOIN users u ON u.id = o.user_id
               WHERE ' . implode(' AND ', $where) . '
            ORDER BY o.placed_at DESC LIMIT 100', $params);
?>

<div class="admin-head">
  <div>
    <h1>Orders</h1>
    <p class="muted tiny"><?= count($orders) ?> shown<?= count($orders) === 100 ? ' (latest 100)' : '' ?></p>
  </div>
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap">
    <input class="input" type="search" name="q" value="<?= e($term) ?>" placeholder="Order number, name or email">
    <select class="select" name="status" onchange="this.form.submit()" style="width:auto">
      <option value="">All statuses</option>
      <?php foreach ($flow as $s): ?>
        <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-ghost" type="submit">Filter</button>
  </form>
</div>

<div class="table-wrap">
  <table class="data">
    <thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Placed</th><th></th></tr></thead>
    <tbody>
      <?php if (!$orders): ?><tr><td colspan="8" class="muted">No orders match that filter.</td></tr><?php endif; ?>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td><strong><?= e($o['order_number']) ?></strong></td>
          <td class="wrap-cell"><?= e($o['customer']) ?><br><small class="muted"><?= e($o['email']) ?></small></td>
          <td><?= (int) $o['item_count'] ?></td>
          <td><?= money($o['total']) ?></td>
          <td>
            <span class="chip <?= $o['payment_status'] === 'paid' ? 'tag-green' : ($o['payment_status'] === 'failed' ? 'tag-red' : 'tag-amber') ?>">
              <?= e(ucfirst($o['payment_status'])) ?>
            </span><br><small class="muted"><?= e(payment_label($o['payment_method'])) ?></small>
          </td>
          <td><span class="status-tag st-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
          <td class="muted"><?= e(date('j M, g:i A', strtotime($o['placed_at']))) ?></td>
          <td><a class="btn btn-ghost btn-sm" href="orders.php?id=<?= (int) $o['id'] ?>">Manage</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/footer.php'; ?>
