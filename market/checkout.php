<?php
/** Checkout: delivery address, slot, payment method, order creation. */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

// "Buy now" from a product page drops the item in the basket first.
if (isset($_GET['buy'])) {
    [$ok, $message] = cart_add((int) $_GET['buy'], 1);
    if (!$ok) {
        flash($message, 'error');
        redirect(safe_back('products.php'));
    }
    redirect('checkout.php');
}

$userId    = user_id();
$user      = current_user();
$addresses = qa('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC', [$userId]);

$base   = cart_totals();
if (!$base['items']) {
    flash('Your basket is empty.', 'error');
    redirect('cart.php');
}
$coupon = active_coupon($base['subtotal']);
$totals = cart_totals($coupon, $base['items']);

$slots = [
    'Today, 4 PM – 7 PM',
    'Today, 7 PM – 10 PM',
    'Tomorrow, 7 AM – 10 AM',
    'Tomorrow, 11 AM – 2 PM',
];

$errors = [];

if (is_post()) {
    require_csrf();

    // ------------------------------------------------------- new address
    if (($_POST['form'] ?? '') === 'address') {
        $address = [
            'label'     => in_array($_POST['label'] ?? '', ['Home', 'Work', 'Other'], true) ? $_POST['label'] : 'Home',
            'full_name' => trim((string) ($_POST['full_name'] ?? '')),
            'phone'     => preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? '')),
            'line1'     => trim((string) ($_POST['line1'] ?? '')),
            'line2'     => trim((string) ($_POST['line2'] ?? '')),
            'landmark'  => trim((string) ($_POST['landmark'] ?? '')),
            'city'      => trim((string) ($_POST['city'] ?? '')),
            'state'     => trim((string) ($_POST['state'] ?? '')),
            'pincode'   => preg_replace('/\D+/', '', (string) ($_POST['pincode'] ?? '')),
        ];

        if (mb_strlen($address['full_name']) < 2)      $errors['full_name'] = 'Who should we hand the order to?';
        if (strlen($address['phone']) !== 10)          $errors['phone']     = 'Enter a 10-digit mobile number.';
        if (mb_strlen($address['line1']) < 5)          $errors['line1']     = 'Flat, house or building — at least 5 characters.';
        if ($address['city'] === '')                   $errors['city']      = 'City is required.';
        if ($address['state'] === '')                  $errors['state']     = 'State is required.';
        if (strlen($address['pincode']) !== 6)         $errors['pincode']   = 'Enter a 6-digit pincode.';

        if (!$errors) {
            $isFirst = count($addresses) === 0;
            q('INSERT INTO addresses (user_id, label, full_name, phone, line1, line2, landmark, city, state, pincode, is_default)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
              [$userId, $address['label'], $address['full_name'], $address['phone'], $address['line1'],
               $address['line2'] ?: null, $address['landmark'] ?: null, $address['city'], $address['state'],
               $address['pincode'], $isFirst ? 1 : 0]);
            flash('Address saved.');
            redirect('checkout.php');
        }
    }

    // --------------------------------------------------------- place order
    if (($_POST['form'] ?? '') === 'place') {
        $addressId = (int) ($_POST['address_id'] ?? 0);
        $method    = (string) ($_POST['payment_method'] ?? 'cod');
        $slot      = (string) ($_POST['slot'] ?? $slots[0]);

        $shipTo = q1('SELECT * FROM addresses WHERE id = ? AND user_id = ?', [$addressId, $userId]);

        if (!$shipTo) {
            $errors['address_id'] = 'Choose a delivery address.';
        }
        if (!in_array($method, ['card', 'upi', 'netbanking', 'wallet', 'cod'], true)) {
            $errors['payment_method'] = 'Choose how you would like to pay.';
        }
        if (!in_array($slot, $slots, true)) {
            $slot = $slots[0];
        }

        // Re-check stock at the last moment — the basket may be hours old.
        foreach ($totals['items'] as $item) {
            if ((int) $item['qty'] > (int) $item['stock']) {
                $errors['stock'] = $item['name'] . ' only has ' . (int) $item['stock'] . ' left. Please update your basket.';
                break;
            }
        }

        if (!$errors) {
            $orderNumber = 'MKT' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
            $pdo = db();
            $pdo->beginTransaction();

            try {
                q('INSERT INTO orders (order_number, user_id, ship_name, ship_phone, ship_line1, ship_line2,
                                       ship_city, ship_state, ship_pincode, slot, subtotal, discount, coupon_code,
                                       delivery_fee, handling_fee, total, payment_method, payment_status, status)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                  [
                      $orderNumber, $userId, $shipTo['full_name'], $shipTo['phone'], $shipTo['line1'],
                      trim(($shipTo['line2'] ?? '') . ($shipTo['landmark'] ? ' (near ' . $shipTo['landmark'] . ')' : '')) ?: null,
                      $shipTo['city'], $shipTo['state'], $shipTo['pincode'], $slot,
                      $totals['subtotal'], $totals['discount'], $totals['coupon_code'],
                      $totals['delivery_fee'], $totals['handling_fee'], $totals['total'],
                      $method, 'pending', 'placed',
                  ]);
                $orderId = (int) $pdo->lastInsertId();

                foreach ($totals['items'] as $item) {
                    q('INSERT INTO order_items (order_id, product_id, name, unit, emoji, tint, price, mrp, qty, line_total)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                      [$orderId, $item['id'], $item['name'], $item['unit'], $item['emoji'], $item['tint'],
                       $item['price'], $item['mrp'], $item['qty'], (float) $item['price'] * (int) $item['qty']]);

                    // Stock is held from here; a cancelled order puts it back.
                    q('UPDATE products SET stock = GREATEST(stock - ?, 0), sold_count = sold_count + ? WHERE id = ?',
                      [(int) $item['qty'], (int) $item['qty'], $item['id']]);
                }

                if ($totals['coupon_code']) {
                    q('UPDATE coupons SET used_count = used_count + 1 WHERE code = ?', [$totals['coupon_code']]);
                }

                cart_clear();
                unset($_SESSION['coupon']);
                $pdo->commit();
            } catch (Throwable $ex) {
                $pdo->rollBack();
                $errors['fatal'] = 'We could not place the order. Please try again.';
                if (DEBUG) {
                    $errors['fatal'] .= ' (' . $ex->getMessage() . ')';
                }
            }

            if (!$errors) {
                if ($method === 'cod') {
                    q('INSERT INTO payments (order_id, txn_id, method, detail, amount, status, message)
                       VALUES (?, ?, ?, ?, ?, ?, ?)',
                      [$orderId, 'COD' . strtoupper(bin2hex(random_bytes(6))), 'cod', 'Cash on delivery',
                       $totals['total'], 'created', 'Amount to be collected at the door']);
                    redirect('order-success.php?order=' . urlencode($orderNumber));
                }
                redirect('payment.php?order=' . urlencode($orderNumber));
            }
        }
    }
}

$defaultAddressId = (int) ($_POST['address_id'] ?? ($addresses[0]['id'] ?? 0));

$pageTitle = 'Checkout | ' . STORE_NAME;
require __DIR__ . '/includes/header.php';
?>

<div class="wrap page">
  <div class="steps">
    <a class="step is-done" href="cart.php"><span class="dot">✓</span> Basket</a>
    <span class="step-line"></span>
    <span class="step is-on"><span class="dot">2</span> Address &amp; payment</span>
    <span class="step-line"></span>
    <span class="step"><span class="dot">3</span> Confirmation</span>
  </div>

  <?php if (isset($errors['fatal'])): ?><div class="alert alert-error"><?= e($errors['fatal']) ?></div><?php endif; ?>
  <?php if (isset($errors['stock'])): ?><div class="alert alert-warn"><?= e($errors['stock']) ?></div><?php endif; ?>

  <div class="two-col">
    <div>
      <!-- ------------------------------------------------------ address -->
      <section class="panel panel-pad" style="margin-bottom:16px">
        <h2 style="font-size:16px;margin-bottom:4px">1. Delivery address</h2>
        <p class="muted tiny" style="margin-bottom:14px">Where should we drop the order?</p>

        <?php if (isset($errors['address_id'])): ?><div class="alert alert-error"><?= e($errors['address_id']) ?></div><?php endif; ?>

        <form method="post" id="place-order" data-once>
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="place">

          <?php if (!$addresses): ?>
            <div class="alert alert-info">No saved addresses yet — add one below.</div>
          <?php endif; ?>

          <?php foreach ($addresses as $i => $a): ?>
            <label class="pick <?= (int) $a['id'] === $defaultAddressId ? 'is-on' : '' ?>" data-address-pick>
              <input type="radio" name="address_id" value="<?= (int) $a['id'] ?>"
                     <?= (int) $a['id'] === $defaultAddressId ? 'checked' : '' ?> data-address-option required>
              <span class="pick-body">
                <strong><?= e($a['full_name']) ?> <span class="chip"><?= e($a['label']) ?></span></strong>
                <p>
                  <?= e($a['line1']) ?><?= $a['line2'] ? ', ' . e($a['line2']) : '' ?>
                  <?= $a['landmark'] ? ' (near ' . e($a['landmark']) . ')' : '' ?><br>
                  <?= e($a['city']) ?>, <?= e($a['state']) ?> — <?= e($a['pincode']) ?><br>
                  📞 <?= e($a['phone']) ?>
                </p>
              </span>
            </label>
          <?php endforeach; ?>

          <!-- --------------------------------------------------- slot -->
          <h2 style="font-size:16px;margin:22px 0 4px">2. Delivery slot</h2>
          <p class="muted tiny" style="margin-bottom:14px">Pick a 3-hour window that suits you.</p>
          <?php foreach ($slots as $i => $slot): ?>
            <label class="pick <?= $i === 0 ? 'is-on' : '' ?>">
              <input type="radio" name="slot" value="<?= e($slot) ?>" <?= $i === 0 ? 'checked' : '' ?>>
              <span class="pick-emoji"><?= $i < 2 ? '🌇' : '🌅' ?></span>
              <span class="pick-body">
                <strong><?= e($slot) ?></strong>
                <p><?= $i === 0 ? 'Fastest option · no extra charge' : 'Standard slot · no extra charge' ?></p>
              </span>
            </label>
          <?php endforeach; ?>

          <!-- ------------------------------------------------ payment -->
          <h2 style="font-size:16px;margin:22px 0 4px">3. Payment method</h2>
          <p class="muted tiny" style="margin-bottom:14px">
            This store runs a <strong>demo gateway</strong> — no real money moves and nothing sensitive is stored.
          </p>
          <?php if (isset($errors['payment_method'])): ?><div class="alert alert-error"><?= e($errors['payment_method']) ?></div><?php endif; ?>

          <?php
          $methods = [
              'upi'        => ['💳', 'UPI', 'Pay with any UPI app — GPay, PhonePe, Paytm'],
              'card'       => ['🏧', 'Credit / Debit card', 'Visa, Mastercard, RuPay and Amex'],
              'netbanking' => ['🏦', 'Net banking', 'All major Indian banks'],
              'wallet'     => ['👛', 'Market Wallet', 'Instant checkout · 5% cashback (demo)'],
              'cod'        => ['💵', 'Cash on delivery', 'Pay the delivery partner at your door'],
          ];
          foreach ($methods as $key => [$emoji, $label, $note]): ?>
            <label class="pick <?= $key === 'upi' ? 'is-on' : '' ?>">
              <input type="radio" name="payment_method" value="<?= e($key) ?>" <?= $key === 'upi' ? 'checked' : '' ?> data-pay-option>
              <span class="pick-emoji"><?= $emoji ?></span>
              <span class="pick-body"><strong><?= e($label) ?></strong><p><?= e($note) ?></p></span>
            </label>
          <?php endforeach; ?>

          <button class="btn btn-accent btn-lg btn-block" type="submit" data-busy="Placing your order…" style="margin-top:16px">
            Place order · <?= money($totals['total']) ?>
          </button>
        </form>
      </section>

      <!-- ------------------------------------------------- new address -->
      <section class="panel panel-pad">
        <h2 style="font-size:16px;margin-bottom:4px">Add a new address</h2>
        <p class="muted tiny" style="margin-bottom:14px">It gets saved to your account for next time.</p>

        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="address">

          <div class="field-row">
            <div class="field">
              <label for="full_name">Full name</label>
              <input class="input <?= isset($errors['full_name']) ? 'is-error' : '' ?>" id="full_name" name="full_name"
                     value="<?= e($_POST['full_name'] ?? $user['name']) ?>" required>
              <?php if (isset($errors['full_name'])): ?><p class="error-text"><?= e($errors['full_name']) ?></p><?php endif; ?>
            </div>
            <div class="field">
              <label for="phone">Mobile number</label>
              <input class="input <?= isset($errors['phone']) ? 'is-error' : '' ?>" id="phone" name="phone" inputmode="numeric"
                     value="<?= e($_POST['phone'] ?? $user['phone'] ?? '') ?>" required>
              <?php if (isset($errors['phone'])): ?><p class="error-text"><?= e($errors['phone']) ?></p><?php endif; ?>
            </div>
          </div>

          <div class="field">
            <label for="line1">Flat, house no., building</label>
            <input class="input <?= isset($errors['line1']) ? 'is-error' : '' ?>" id="line1" name="line1"
                   value="<?= e($_POST['line1'] ?? '') ?>" required>
            <?php if (isset($errors['line1'])): ?><p class="error-text"><?= e($errors['line1']) ?></p><?php endif; ?>
          </div>

          <div class="field">
            <label for="line2">Area, street, sector <span class="muted">(optional)</span></label>
            <input class="input" id="line2" name="line2" value="<?= e($_POST['line2'] ?? '') ?>">
          </div>

          <div class="field">
            <label for="landmark">Landmark <span class="muted">(optional)</span></label>
            <input class="input" id="landmark" name="landmark" value="<?= e($_POST['landmark'] ?? '') ?>" placeholder="e.g. opposite the bus depot">
          </div>

          <div class="field-row">
            <div class="field">
              <label for="city">City</label>
              <input class="input <?= isset($errors['city']) ? 'is-error' : '' ?>" id="city" name="city"
                     value="<?= e($_POST['city'] ?? '') ?>" required>
              <?php if (isset($errors['city'])): ?><p class="error-text"><?= e($errors['city']) ?></p><?php endif; ?>
            </div>
            <div class="field">
              <label for="state">State</label>
              <input class="input <?= isset($errors['state']) ? 'is-error' : '' ?>" id="state" name="state"
                     value="<?= e($_POST['state'] ?? '') ?>" required>
              <?php if (isset($errors['state'])): ?><p class="error-text"><?= e($errors['state']) ?></p><?php endif; ?>
            </div>
          </div>

          <div class="field-row">
            <div class="field">
              <label for="pincode">Pincode</label>
              <input class="input <?= isset($errors['pincode']) ? 'is-error' : '' ?>" id="pincode" name="pincode"
                     inputmode="numeric" maxlength="6" value="<?= e($_POST['pincode'] ?? '') ?>" required>
              <?php if (isset($errors['pincode'])): ?><p class="error-text"><?= e($errors['pincode']) ?></p><?php endif; ?>
            </div>
            <div class="field">
              <label for="label">Address type</label>
              <select class="select" id="label" name="label">
                <option>Home</option><option>Work</option><option>Other</option>
              </select>
            </div>
          </div>

          <button class="btn btn-ghost" type="submit">Save address</button>
        </form>
      </section>
    </div>

    <!-- ------------------------------------------------- order summary -->
    <aside class="panel summary">
      <h3>Order summary</h3>

      <div style="padding:14px 18px;display:grid;gap:12px;max-height:280px;overflow-y:auto">
        <?php foreach ($totals['items'] as $item): ?>
          <div style="display:flex;gap:11px;align-items:center">
            <span style="width:42px;height:42px;border-radius:9px;display:grid;place-items:center;font-size:21px;background:<?= e($item['tint']) ?>">
              <?= e($item['emoji']) ?>
            </span>
            <span style="flex:1;min-width:0">
              <strong style="display:block;font-size:12.5px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($item['name']) ?></strong>
              <small class="muted"><?= e($item['unit']) ?> × <?= (int) $item['qty'] ?></small>
            </span>
            <b style="font-size:13px"><?= money((float) $item['price'] * (int) $item['qty']) ?></b>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="sum-rows" style="border-top:1px solid var(--line)">
        <div class="sum-row"><span>Price (<?= $totals['units'] ?> items)</span><b><?= money($totals['mrp_total']) ?></b></div>
        <?php if ($totals['product_saving'] > 0): ?>
          <div class="sum-row is-off"><span>Product discount</span><b>− <?= money($totals['product_saving']) ?></b></div>
        <?php endif; ?>
        <?php if ($totals['discount'] > 0): ?>
          <div class="sum-row is-off"><span>Coupon (<?= e($totals['coupon_code']) ?>)</span><b>− <?= money($totals['discount']) ?></b></div>
        <?php endif; ?>
        <div class="sum-row <?= $totals['free_delivery'] ? 'is-free' : '' ?>">
          <span>Delivery</span><b><?= $totals['free_delivery'] ? 'FREE' : money($totals['delivery_fee']) ?></b>
        </div>
        <?php if ($totals['handling_fee'] > 0): ?>
          <div class="sum-row"><span>Handling</span><b><?= money($totals['handling_fee']) ?></b></div>
        <?php endif; ?>
      </div>

      <div class="sum-total"><span>Total payable</span><span><?= money($totals['total']) ?></span></div>
      <?php if ($totals['total_saving'] > 0): ?>
        <div class="sum-saving">🎉 You save <?= money($totals['total_saving']) ?></div>
      <?php endif; ?>
      <p class="tiny muted" style="padding:14px 18px 18px;margin:0">
        By placing this order you agree to our <a href="help.php#terms">terms of use</a>.
      </p>
    </aside>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
