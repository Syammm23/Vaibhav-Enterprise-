<?php
/** Thank-you page shown right after an order is placed. */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$orderNumber = trim((string) ($_GET['order'] ?? ''));
$order = q1('SELECT * FROM orders WHERE order_number = ? AND user_id = ?', [$orderNumber, user_id()]);

if (!$order) {
    flash('We could not find that order.', 'error');
    redirect('orders.php');
}

$items   = qa('SELECT * FROM order_items WHERE order_id = ?', [$order['id']]);
$payment = q1('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$order['id']]);
$suggest = products(['sort' => 'popularity', 'in_stock' => 1, 'per_page' => 10]);

$pageTitle = 'Order ' . $order['order_number'] . ' confirmed | ' . STORE_NAME;
require __DIR__ . '/includes/header.php';
?>

<div class="wrap page">
  <div class="steps">
    <span class="step is-done"><span class="dot">✓</span> Basket</span>
    <span class="step-line"></span>
    <span class="step is-done"><span class="dot">✓</span> Address &amp; payment</span>
    <span class="step-line"></span>
    <span class="step is-on"><span class="dot">3</span> Confirmation</span>
  </div>

  <div class="panel panel-pad center" style="padding:40px 24px">
    <span style="font-size:60px">🎉</span>
    <h1 style="font-size:24px;margin:10px 0 6px">Order confirmed!</h1>
    <p class="muted">
      Thanks <?= e(explode(' ', current_user()['name'])[0]) ?> — we are packing
      <strong><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?></strong> for you.
    </p>

    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin:18px 0">
      <span class="chip tag-green">Order <?= e($order['order_number']) ?></span>
      <span class="chip">🚚 <?= e($order['slot']) ?></span>
      <span class="chip <?= $order['payment_status'] === 'paid' ? 'tag-green' : ($order['payment_status'] === 'failed' ? 'tag-red' : 'tag-amber') ?>">
        <?= e(payment_label($order['payment_method'])) ?> · <?= e(ucfirst($order['payment_status'])) ?>
      </span>
    </div>

    <?php if ($order['payment_status'] === 'failed'): ?>
      <div class="alert alert-error" style="max-width:520px;margin:0 auto 14px;text-align:left">
        The payment did not go through. Your order is on hold — retry the payment to confirm it.
      </div>
      <a class="btn btn-lg" href="payment.php?order=<?= e($order['order_number']) ?>">Retry payment</a>
    <?php elseif ($order['payment_method'] === 'cod'): ?>
      <div class="alert alert-info" style="max-width:520px;margin:0 auto 14px;text-align:left">
        Please keep <strong><?= money($order['total']) ?></strong> ready for the delivery partner.
      </div>
    <?php endif; ?>

    <?php if ($payment && $payment['status'] === 'success'): ?>
      <p class="tiny muted">Transaction ID <?= e($payment['txn_id']) ?> · <?= e($payment['detail']) ?></p>
    <?php endif; ?>

    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:20px">
      <a class="btn" href="order.php?number=<?= e($order['order_number']) ?>">Track this order</a>
      <a class="btn btn-ghost" href="products.php">Continue shopping</a>
    </div>
  </div>

  <div class="two-col" style="margin-top:20px">
    <div class="panel">
      <h3 style="font-size:12px;text-transform:uppercase;letter-spacing:.07em;color:var(--ink-3);padding:14px 18px;border-bottom:1px solid var(--line);margin:0">
        What is on its way
      </h3>
      <?php foreach ($items as $item): ?>
        <div class="cart-line" style="padding:14px 18px">
          <span class="cart-thumb" style="background:<?= e($item['tint']) ?>;width:64px;height:64px;font-size:30px"><?= e($item['emoji']) ?></span>
          <div class="cart-info">
            <h3><?= e($item['name']) ?></h3>
            <span class="muted tiny"><?= e($item['unit']) ?> · Qty <?= (int) $item['qty'] ?></span>
          </div>
          <div class="cart-right"><strong><?= money($item['line_total']) ?></strong></div>
        </div>
      <?php endforeach; ?>
    </div>

    <aside class="panel summary">
      <h3>Delivering to</h3>
      <div style="padding:14px 18px;font-size:13px">
        <strong><?= e($order['ship_name']) ?></strong><br>
        <?= e($order['ship_line1']) ?><?= $order['ship_line2'] ? ', ' . e($order['ship_line2']) : '' ?><br>
        <?= e($order['ship_city']) ?>, <?= e($order['ship_state']) ?> — <?= e($order['ship_pincode']) ?><br>
        📞 <?= e($order['ship_phone']) ?>
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
      <div class="sum-total"><span>Paid / payable</span><span><?= money($order['total']) ?></span></div>
    </aside>
  </div>

  <section class="section">
    <div class="section-head"><div><h2>You might also need</h2></div></div>
    <div class="rail"><?php foreach ($suggest as $p) { include __DIR__ . '/includes/product-card.php'; } ?></div>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
