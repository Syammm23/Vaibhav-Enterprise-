<?php
/**
 * MarketPay — the demo payment gateway.
 *
 * Nothing here talks to a real processor. Card details are validated for shape,
 * used to derive a masked label, and then thrown away: only the last four digits
 * (or a UPI handle / bank name) are ever written to the `payments` table.
 * Swap this page for a Razorpay/Stripe/PayU redirect to go live.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$orderNumber = trim((string) ($_GET['order'] ?? $_POST['order'] ?? ''));
$order = q1('SELECT * FROM orders WHERE order_number = ? AND user_id = ?', [$orderNumber, user_id()]);

if (!$order) {
    flash('We could not find that order.', 'error');
    redirect('orders.php');
}
if ($order['payment_status'] === 'paid') {
    redirect('order-success.php?order=' . urlencode($orderNumber));
}
if ($order['status'] === 'cancelled') {
    flash('That order was cancelled, so it can no longer be paid for.', 'error');
    redirect('order.php?number=' . urlencode($orderNumber));
}

$method = $order['payment_method'];
$errors = [];
$banks  = ['State Bank of India', 'HDFC Bank', 'ICICI Bank', 'Axis Bank', 'Kotak Mahindra Bank', 'Punjab National Bank', 'Bank of Baroda'];

if (is_post() && ($_POST['form'] ?? '') === 'pay') {
    require_csrf();

    $detail  = '';
    $outcome = ($_POST['outcome'] ?? 'success') === 'fail' ? 'fail' : 'success';

    // ------------------------------------------------ shape checks only
    if ($method === 'card') {
        $digits = preg_replace('/\D+/', '', (string) ($_POST['card_number'] ?? ''));
        $expiry = trim((string) ($_POST['card_expiry'] ?? ''));
        $cvv    = preg_replace('/\D+/', '', (string) ($_POST['card_cvv'] ?? ''));
        $holder = trim((string) ($_POST['card_name'] ?? ''));

        if (strlen($digits) < 15 || strlen($digits) > 16) $errors['card_number'] = 'Enter a 16-digit card number.';
        if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry)) {
            $errors['card_expiry'] = 'Expiry must look like MM/YY.';
        } elseif (strtotime('01/' . substr($expiry, 0, 2) . '/20' . substr($expiry, 3, 2) . ' +1 month') < time()) {
            $errors['card_expiry'] = 'That card has expired.';
        }
        if (strlen($cvv) < 3 || strlen($cvv) > 4) $errors['card_cvv']  = 'CVV is 3 digits (4 on Amex).';
        if ($holder === '')                       $errors['card_name'] = 'Name on the card is required.';

        // Only the masked tail survives this request.
        $detail = 'Card ending ' . substr($digits, -4);

    } elseif ($method === 'upi') {
        $vpa = trim((string) ($_POST['vpa'] ?? ''));
        if (!preg_match('/^[a-zA-Z0-9._-]{2,}@[a-zA-Z]{2,}$/', $vpa)) {
            $errors['vpa'] = 'Enter a UPI ID like yourname@okbank.';
        }
        $detail = 'UPI ' . $vpa;

    } elseif ($method === 'netbanking') {
        $bank = (string) ($_POST['bank'] ?? '');
        if (!in_array($bank, $banks, true)) {
            $errors['bank'] = 'Pick your bank.';
        }
        $detail = $bank;

    } elseif ($method === 'wallet') {
        $detail = 'Market Wallet · ' . current_user()['email'];
    } else {
        $detail = 'Cash on delivery';
    }

    if (!$errors) {
        $txnId = 'PAY' . date('ymd') . strtoupper(bin2hex(random_bytes(5)));

        if ($outcome === 'success') {
            q('INSERT INTO payments (order_id, txn_id, method, detail, amount, status, message)
               VALUES (?, ?, ?, ?, ?, ?, ?)',
              [$order['id'], $txnId, $method, $detail, $order['total'], 'success', 'Payment authorised by MarketPay (demo)']);
            q('UPDATE orders SET payment_status = ? WHERE id = ?', ['paid', $order['id']]);
            flash('Payment successful — transaction ' . $txnId);
            redirect('order-success.php?order=' . urlencode($orderNumber));
        }

        q('INSERT INTO payments (order_id, txn_id, method, detail, amount, status, message)
           VALUES (?, ?, ?, ?, ?, ?, ?)',
          [$order['id'], $txnId, $method, $detail, $order['total'], 'failed', 'Declined by the issuing bank (simulated)']);
        q('UPDATE orders SET payment_status = ? WHERE id = ?', ['failed', $order['id']]);
        $errors['gateway'] = 'Payment failed — the bank declined this transaction. Your order is saved, so you can try again.';
    }
}

$pageTitle = 'Secure payment | ' . STORE_NAME;
require __DIR__ . '/includes/header.php';
?>

<div class="wrap page">
  <div class="gateway">
    <div class="gateway-head">
      <strong>🔒 MarketPay Secure Checkout</strong>
      <small>Demo gateway · order <?= e($order['order_number']) ?></small>
    </div>

    <div class="gateway-amount">
      <span><?= e(STORE_NAME) ?> Retail Pvt Ltd</span>
      <span><?= money($order['total']) ?></span>
    </div>

    <div class="gateway-body">
      <?php if (isset($errors['gateway'])): ?>
        <div class="alert alert-error"><?= e($errors['gateway']) ?></div>
      <?php endif; ?>

      <p class="muted tiny" style="margin-bottom:16px">
        Paying by <strong><?= e(payment_label($method)) ?></strong> ·
        <a href="order.php?number=<?= e($order['order_number']) ?>">change later</a>
      </p>

      <form method="post" data-once>
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="pay">
        <input type="hidden" name="order" value="<?= e($order['order_number']) ?>">

        <?php if ($method === 'card'): ?>
          <div class="field">
            <label for="card_number">Card number</label>
            <input class="input <?= isset($errors['card_number']) ? 'is-error' : '' ?>" id="card_number" name="card_number"
                   inputmode="numeric" placeholder="4111 1111 1111 1111" data-card-number
                   value="<?= e($_POST['card_number'] ?? '') ?>" autocomplete="off">
            <?php if (isset($errors['card_number'])): ?><p class="error-text"><?= e($errors['card_number']) ?></p><?php endif; ?>
          </div>
          <div class="field">
            <label for="card_name">Name on card</label>
            <input class="input <?= isset($errors['card_name']) ? 'is-error' : '' ?>" id="card_name" name="card_name"
                   value="<?= e($_POST['card_name'] ?? current_user()['name']) ?>" autocomplete="off">
            <?php if (isset($errors['card_name'])): ?><p class="error-text"><?= e($errors['card_name']) ?></p><?php endif; ?>
          </div>
          <div class="field-row">
            <div class="field">
              <label for="card_expiry">Expiry</label>
              <input class="input <?= isset($errors['card_expiry']) ? 'is-error' : '' ?>" id="card_expiry" name="card_expiry"
                     placeholder="MM/YY" maxlength="5" data-card-expiry value="<?= e($_POST['card_expiry'] ?? '') ?>" autocomplete="off">
              <?php if (isset($errors['card_expiry'])): ?><p class="error-text"><?= e($errors['card_expiry']) ?></p><?php endif; ?>
            </div>
            <div class="field">
              <label for="card_cvv">CVV</label>
              <input class="input <?= isset($errors['card_cvv']) ? 'is-error' : '' ?>" id="card_cvv" name="card_cvv"
                     inputmode="numeric" maxlength="4" placeholder="•••" autocomplete="off">
              <?php if (isset($errors['card_cvv'])): ?><p class="error-text"><?= e($errors['card_cvv']) ?></p><?php endif; ?>
            </div>
          </div>
          <div class="demo-box">
            <b>Test card</b> Any 16 digits work — try <code>4111 1111 1111 1111</code>, any future expiry, any CVV.
          </div>

        <?php elseif ($method === 'upi'): ?>
          <div class="field">
            <label for="vpa">UPI ID</label>
            <input class="input <?= isset($errors['vpa']) ? 'is-error' : '' ?>" id="vpa" name="vpa"
                   placeholder="yourname@okbank" value="<?= e($_POST['vpa'] ?? '') ?>" autocomplete="off">
            <?php if (isset($errors['vpa'])): ?><p class="error-text"><?= e($errors['vpa']) ?></p><?php endif; ?>
            <p class="hint">A collect request would normally land in your UPI app.</p>
          </div>
          <div class="demo-box"><b>Test UPI ID</b> <code>demo@okmarket</code></div>

        <?php elseif ($method === 'netbanking'): ?>
          <div class="field">
            <label for="bank">Choose your bank</label>
            <select class="select <?= isset($errors['bank']) ? 'is-error' : '' ?>" id="bank" name="bank">
              <option value="">Select a bank…</option>
              <?php foreach ($banks as $bank): ?>
                <option <?= ($_POST['bank'] ?? '') === $bank ? 'selected' : '' ?>><?= e($bank) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['bank'])): ?><p class="error-text"><?= e($errors['bank']) ?></p><?php endif; ?>
          </div>

        <?php elseif ($method === 'wallet'): ?>
          <div class="alert alert-success">
            👛 Market Wallet balance: <strong><?= money(5000) ?></strong> (demo balance)<br>
            <?= money($order['total']) ?> will be deducted and 5% comes back as cashback.
          </div>
        <?php endif; ?>

        <button class="btn btn-lg btn-block" type="submit" name="outcome" value="success" data-busy="Contacting your bank…">
          Pay <?= money($order['total']) ?> securely
        </button>
        <button class="btn btn-ghost btn-block" type="submit" name="outcome" value="fail" style="margin-top:9px">
          Simulate a failed payment
        </button>
      </form>
    </div>

    <div class="gateway-foot">
      🔒 This is a simulation. No card, UPI or bank credential is transmitted or stored —
      only a masked reference such as “Card ending 1111” is saved against the order.
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
