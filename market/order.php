<?php
/** One order: tracking timeline, items, invoice summary, cancel, reorder. */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$orderNumber = trim((string) ($_GET['number'] ?? $_POST['number'] ?? ''));
$order = q1('SELECT * FROM orders WHERE order_number = ? AND user_id = ?', [$orderNumber, user_id()]);

if (!$order) {
    flash('We could not find that order.', 'error');
    redirect('orders.php');
}

if (is_post()) {
    require_csrf();
    $form = $_POST['form'] ?? '';

    // ------------------------------------------------------------- cancel
    if ($form === 'cancel') {
        if (in_array($order['status'], ['delivered', 'cancelled'], true)) {
            flash('This order can no longer be cancelled.', 'error');
        } elseif (in_array($order['status'], ['shipped', 'out_for_delivery'], true)) {
            flash('This order has already left our warehouse — please refuse it at the door instead.', 'error');
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                // Put the reserved stock back on the shelf.
                foreach (qa('SELECT product_id, qty FROM order_items WHERE order_id = ?', [$order['id']]) as $line) {
                    if ($line['product_id'] !== null) {
                        q('UPDATE products SET stock = stock + ?, sold_count = GREATEST(sold_count - ?, 0) WHERE id = ?',
                          [(int) $line['qty'], (int) $line['qty'], $line['product_id']]);
                    }
                }
                $refund = $order['payment_status'] === 'paid' ? 'refunded' : $order['payment_status'];
                q('UPDATE orders SET status = ?, payment_status = ? WHERE id = ?', ['cancelled', $refund, $order['id']]);
                $pdo->commit();
                flash($refund === 'refunded'
                    ? 'Order cancelled. The refund reaches your account in 3–5 working days (demo).'
                    : 'Order cancelled.');
            } catch (Throwable $ex) {
                $pdo->rollBack();
                flash('We could not cancel the order. Please try again.', 'error');
            }
        }
        redirect('order.php?number=' . urlencode($orderNumber));
    }

    // ------------------------------------------------------------ reorder
    if ($form === 'reorder') {
        $added = 0;
        foreach (qa('SELECT product_id, qty FROM order_items WHERE order_id = ?', [$order['id']]) as $line) {
            if ($line['product_id'] === null) {
                continue;
            }
            [$ok] = cart_add((int) $line['product_id'], (int) $line['qty']);
            $added += $ok ? 1 : 0;
        }
        flash($added > 0 ? $added . ' item(s) added back to your basket.' : 'None of those items are available right now.',
              $added > 0 ? 'success' : 'error');
        redirect($added > 0 ? 'cart.php' : 'order.php?number=' . urlencode($orderNumber));
    }
}

$items    = qa('SELECT oi.*, p.slug
                  FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id
                 WHERE oi.order_id = ?', [$order['id']]);
$payments = qa('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC', [$order['id']]);

// Delivery progress. A cancelled order stops wherever it was.
$flow      = ['placed', 'packed', 'shipped', 'out_for_delivery', 'delivered'];
$reached   = array_search($order['status'], $flow, true);
$cancelled = $order['status'] === 'cancelled';

$notes = [
    'placed'           => 'We have received your order and payment details.',
    'packed'           => 'Your items are picked and packed at the warehouse.',
    'shipped'          => 'Handed over to the delivery partner.',
    'out_for_delivery' => 'Arriving in your slot: ' . $order['slot'],
    'delivered'        => 'Delivered. We hope everything looked good.',
];

$canCancel = !in_array($order['status'], ['delivered', 'cancelled', 'shipped', 'out_for_delivery'], true);

$pageTitle = 'Order ' . $order['order_number'] . ' | ' . STORE_NAME;
require __DIR__ . '/includes/header.php';
?>

<div class="wrap page">
  <nav class="breadcrumb">
    <a href="index.php">Home</a> <span class="sep">›</span>
    <a href="orders.php">Orders</a> <span class="sep">›</span> <span><?= e($order['order_number']) ?></span>
  </nav>

  <div class="row-between" style="margin-bottom:16px">
    <div>
      <h1 style="font-size:22px">Order <?= e($order['order_number']) ?></h1>
      <p class="muted tiny">Placed <?= e(date('j M Y, g:i A', strtotime($order['placed_at']))) ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap" class="no-print">
      <form method="post"><?= csrf_field() ?>
        <input type="hidden" name="form" value="reorder">
        <input type="hidden" name="number" value="<?= e($order['order_number']) ?>">
        <button class="btn btn-ghost btn-sm" type="submit">🔁 Reorder</button>
      </form>
      <button class="btn btn-ghost btn-sm" type="button" onclick="window.print()">🖨️ Invoice</button>
      <?php if ($order['payment_status'] === 'failed'): ?>
        <a class="btn btn-sm" href="payment.php?order=<?= e($order['order_number']) ?>">Retry payment</a>
      <?php endif; ?>
      <?php if ($canCancel): ?>
        <form method="post" onsubmit="return confirm('Cancel this order?')">
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="cancel">
          <input type="hidden" name="number" value="<?= e($order['order_number']) ?>">
          <button class="btn btn-danger btn-sm" type="submit">Cancel order</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="two-col">
    <div>
      <!-- --------------------------------------------------- tracking -->
      <section class="panel panel-pad" style="margin-bottom:16px">
        <h2 style="font-size:15px;margin-bottom:14px">Tracking</h2>

        <?php if ($cancelled): ?>
          <div class="alert alert-error">
            This order was cancelled<?= $order['payment_status'] === 'refunded' ? ' and the amount has been refunded (demo)' : '' ?>.
          </div>
        <?php endif; ?>

        <div class="timeline">
          <?php foreach ($flow as $i => $stage): ?>
            <?php $done = !$cancelled && $reached !== false && $i <= $reached; ?>
            <div class="tl <?= $done ? 'is-done' : '' ?>">
              <div class="tl-mark">
                <span class="tl-dot"></span>
                <?php if ($i < count($flow) - 1): ?><span class="tl-line"></span><?php endif; ?>
              </div>
              <div class="tl-body">
                <strong><?= e(status_label($stage)) ?></strong>
                <small><?= $done ? e($notes[$stage]) : 'Pending' ?></small>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- ------------------------------------------------------ items -->
      <section class="panel" style="margin-bottom:16px">
        <h2 style="font-size:12px;text-transform:uppercase;letter-spacing:.07em;color:var(--ink-3);padding:14px 18px;border-bottom:1px solid var(--line);margin:0">
          <?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?>
        </h2>
        <?php foreach ($items as $item): ?>
          <div class="cart-line" style="padding:14px 18px">
            <span class="cart-thumb" style="--tile:<?= e($item['tint']) ?>;width:64px;height:64px"><?= product_img($item, 64) ?></span>
            <div class="cart-info">
              <h3>
                <?php if ($item['slug']): ?>
                  <a href="product.php?slug=<?= e($item['slug']) ?>"><?= e($item['name']) ?></a>
                <?php else: ?>
                  <?= e($item['name']) ?>
                <?php endif; ?>
              </h3>
              <span class="muted tiny"><?= e($item['unit']) ?> · <?= money($item['price']) ?> × <?= (int) $item['qty'] ?></span>
            </div>
            <div class="cart-right"><strong><?= money($item['line_total']) ?></strong></div>
          </div>
        <?php endforeach; ?>
      </section>

      <!-- --------------------------------------------------- payments -->
      <section class="panel panel-pad">
        <h2 style="font-size:15px;margin-bottom:12px">Payment</h2>
        <p class="muted tiny" style="margin-bottom:12px">
          <?= e(payment_label($order['payment_method'])) ?> ·
          <span class="chip <?= $order['payment_status'] === 'paid' ? 'tag-green' : ($order['payment_status'] === 'failed' ? 'tag-red' : 'tag-amber') ?>">
            <?= e(ucfirst($order['payment_status'])) ?>
          </span>
        </p>
        <?php if ($payments): ?>
          <table class="spec-table">
            <tr><th>Transaction</th><th>Method</th><th>Status</th></tr>
            <?php foreach ($payments as $pay): ?>
              <tr>
                <td><?= e($pay['txn_id']) ?><br><small class="muted"><?= e(date('j M, g:i A', strtotime($pay['created_at']))) ?></small></td>
                <td><?= e($pay['detail']) ?></td>
                <td>
                  <span class="chip <?= $pay['status'] === 'success' ? 'tag-green' : ($pay['status'] === 'failed' ? 'tag-red' : 'tag-amber') ?>">
                    <?= e(ucfirst($pay['status'])) ?>
                  </span>
                  <br><small class="muted"><?= e($pay['message']) ?></small>
                </td>
              </tr>
            <?php endforeach; ?>
          </table>
        <?php else: ?>
          <p class="muted">No payment attempts recorded yet.</p>
        <?php endif; ?>
      </section>
    </div>

    <!-- ------------------------------------------------------- summary -->
    <aside class="panel summary">
      <h3>Delivery address</h3>
      <div style="padding:14px 18px;font-size:13px">
        <strong><?= e($order['ship_name']) ?></strong><br>
        <?= e($order['ship_line1']) ?><?= $order['ship_line2'] ? ', ' . e($order['ship_line2']) : '' ?><br>
        <?= e($order['ship_city']) ?>, <?= e($order['ship_state']) ?> — <?= e($order['ship_pincode']) ?><br>
        📞 <?= e($order['ship_phone']) ?>
        <p class="muted tiny" style="margin-top:8px">🚚 <?= e($order['slot']) ?></p>
      </div>

      <div class="sum-rows" style="border-top:1px solid var(--line)">
        <div class="sum-row"><span>Subtotal</span><b><?= money($order['subtotal']) ?></b></div>
        <?php if ((float) $order['discount'] > 0): ?>
          <div class="sum-row is-off"><span>Coupon <?= e($order['coupon_code']) ?></span><b>− <?= money($order['discount']) ?></b></div>
        <?php endif; ?>
        <div class="sum-row <?= (float) $order['delivery_fee'] === 0.0 ? 'is-free' : '' ?>">
          <span>Delivery</span><b><?= (float) $order['delivery_fee'] === 0.0 ? 'FREE' : money($order['delivery_fee']) ?></b>
        </div>
        <?php if ((float) $order['handling_fee'] > 0): ?>
          <div class="sum-row"><span>Handling</span><b><?= money($order['handling_fee']) ?></b></div>
        <?php endif; ?>
      </div>
      <div class="sum-total"><span>Order total</span><span><?= money($order['total']) ?></span></div>
      <p class="tiny muted" style="padding:14px 18px 18px;margin:0">
        Need help with this order? <a href="help.php">Contact support</a>.
      </p>
    </aside>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
