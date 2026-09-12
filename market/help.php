<?php
/** Help centre: FAQs, delivery, returns, payments, policies, contact form. */

require_once __DIR__ . '/includes/bootstrap.php';

$sent = false;
if (is_post()) {
    require_csrf();
    // A real deployment would email this or push it into a ticket queue.
    $sent = true;
    flash('Thanks — our support team will reply within 24 hours.');
}

$faqs = [
    ['How long does delivery take?', 'Orders placed before 6 PM are delivered the same evening in serviced pincodes. Everything else arrives the next morning between 7 AM and 10 AM. You pick the exact slot at checkout.'],
    ['What is the minimum order value?', 'There is no minimum. Orders above ' . money(FREE_DELIVERY_ABOVE) . ' ship free; smaller baskets carry a ' . money(DELIVERY_FEE) . ' delivery fee and a ' . money(HANDLING_FEE) . ' handling charge.'],
    ['Can I change or cancel an order?', 'Yes — until it is packed. Open the order from My orders and hit Cancel. Once it ships you can still refuse it at the door for a full refund.'],
    ['How do refunds work?', 'Prepaid refunds go back to the original payment method in 3 to 5 working days. Cash-on-delivery refunds are sent to your bank account after a quick verification call.'],
    ['Are the fruits and vegetables really fresh?', 'They are picked the evening before and stored in a temperature-controlled facility. If anything looks off, tell us within 24 hours and we replace it or refund it.'],
    ['Do you charge for packaging?', 'No. Orders go out in reusable crates, and the delivery partner takes the crate back with them.'],
];

$pageTitle = 'Help centre | ' . STORE_NAME;
require __DIR__ . '/includes/header.php';
?>

<div class="wrap page">
  <nav class="breadcrumb"><a href="index.php">Home</a> <span class="sep">›</span> <span>Help</span></nav>

  <div class="deal-strip" style="margin-top:0">
    <div>
      <h2>How can we help?</h2>
      <p>Answers to the questions our support team hears most.</p>
    </div>
    <div class="deal-codes">
      <a class="deal-code" href="#delivery">Delivery</a>
      <a class="deal-code" href="#returns">Returns</a>
      <a class="deal-code" href="#payments">Payments</a>
    </div>
  </div>

  <div class="two-col" style="margin-top:20px">
    <div>
      <section class="panel panel-pad" style="margin-bottom:16px">
        <h2 style="font-size:17px;margin-bottom:14px">Frequently asked questions</h2>
        <?php foreach ($faqs as $i => [$question, $answer]): ?>
          <details style="border-bottom:1px solid var(--line-2);padding:12px 0" <?= $i === 0 ? 'open' : '' ?>>
            <summary style="cursor:pointer;font-weight:700;font-size:14px"><?= e($question) ?></summary>
            <p class="muted" style="margin:9px 0 0;max-width:70ch"><?= e($answer) ?></p>
          </details>
        <?php endforeach; ?>
      </section>

      <section class="panel panel-pad" style="margin-bottom:16px" id="delivery">
        <h2 style="font-size:17px;margin-bottom:10px">🚚 Delivery</h2>
        <p class="muted">We deliver to 1,400+ pincodes across Gujarat, Maharashtra, Delhi NCR, Karnataka and Telangana. Slots run from 7 AM to 10 PM, seven days a week.</p>
        <table class="spec-table">
          <tr><th>Free delivery</th><td>On every order above <?= money(FREE_DELIVERY_ABOVE) ?></td></tr>
          <tr><th>Delivery fee</th><td><?= money(DELIVERY_FEE) ?> on smaller baskets</td></tr>
          <tr><th>Handling charge</th><td><?= money(HANDLING_FEE) ?>, waived along with the delivery fee</td></tr>
          <tr><th>Slots</th><td>Four 3-hour windows every day</td></tr>
          <tr><th>Express</th><td>90 minutes, in selected pincodes</td></tr>
        </table>
      </section>

      <section class="panel panel-pad" style="margin-bottom:16px" id="returns">
        <h2 style="font-size:17px;margin-bottom:10px">↩️ Returns &amp; refunds</h2>
        <p class="muted">Fresh produce, dairy and frozen items can be returned at the doorstep if they do not look right. Packaged goods can be returned within 7 days as long as the seal is intact.</p>
        <ul class="offer-list">
          <li><span class="oi">1.</span><span>Open the order from <a href="orders.php">My orders</a>.</span></li>
          <li><span class="oi">2.</span><span>Pick the item and tell us what went wrong.</span></li>
          <li><span class="oi">3.</span><span>We collect it on the next delivery run and refund you once it is back.</span></li>
        </ul>
      </section>

      <section class="panel panel-pad" style="margin-bottom:16px" id="payments">
        <h2 style="font-size:17px;margin-bottom:10px">💳 Payments</h2>
        <div class="alert alert-warn">
          This storefront is a demonstration. Payments run through a simulated gateway — no money moves,
          and card, UPI or bank credentials are never stored. Only a masked reference (for example
          “Card ending 1111”) is saved against the order.
        </div>
        <table class="spec-table">
          <tr><th>UPI</th><td>Any UPI app — enter a VPA such as <code>demo@okmarket</code></td></tr>
          <tr><th>Cards</th><td>Visa, Mastercard, RuPay, Amex</td></tr>
          <tr><th>Net banking</th><td>All major Indian banks</td></tr>
          <tr><th>Market Wallet</th><td>Instant checkout with 5% cashback</td></tr>
          <tr><th>Cash on delivery</th><td>Pay the delivery partner at your door</td></tr>
        </table>
      </section>

      <section class="panel panel-pad" style="margin-bottom:16px" id="privacy">
        <h2 style="font-size:17px;margin-bottom:10px">🔒 Privacy</h2>
        <p class="muted">We store the name, email, phone number and addresses you give us so we can deliver your orders. Passwords are hashed and never stored in readable form. We do not sell your data. Because this is a demo store, do not enter details you would not want to leave in a sample database.</p>
      </section>

      <section class="panel panel-pad" id="terms">
        <h2 style="font-size:17px;margin-bottom:10px">📄 Terms of use</h2>
        <p class="muted">Prices, offers and stock are shown as accurately as we can and may change without notice. Coupons apply to one order each unless stated otherwise. Orders can be refused where an item is unavailable, and any amount paid is then refunded in full. This site exists to demonstrate a PHP and MySQL storefront; nothing sold here is a real transaction.</p>
      </section>
    </div>

    <aside>
      <section class="panel panel-pad" style="margin-bottom:16px">
        <h2 style="font-size:16px;margin-bottom:4px">Contact support</h2>
        <p class="muted tiny" style="margin-bottom:14px">We reply within 24 hours, every day.</p>

        <?php if ($sent): ?><div class="alert alert-success">Message received — we will get back to you.</div><?php endif; ?>

        <form method="post">
          <?= csrf_field() ?>
          <div class="field">
            <label for="cname">Your name</label>
            <input class="input" id="cname" name="name" value="<?= e(current_user()['name'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label for="cemail">Email</label>
            <input class="input" type="email" id="cemail" name="email" value="<?= e(current_user()['email'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label for="corder">Order number <span class="muted">(optional)</span></label>
            <input class="input" id="corder" name="order" placeholder="MKT2609XXXXXX">
          </div>
          <div class="field">
            <label for="cmsg">How can we help?</label>
            <textarea class="input" id="cmsg" name="message" rows="4" required></textarea>
          </div>
          <button class="btn btn-block" type="submit">Send message</button>
        </form>
      </section>

      <section class="panel panel-pad">
        <h2 style="font-size:16px;margin-bottom:12px">Reach us directly</h2>
        <ul class="offer-list">
          <li><span class="oi">📞</span><span><b>1800 000 0000</b><br><small class="muted">7 AM – 11 PM, all days</small></span></li>
          <li><span class="oi">✉️</span><span><b>support@market.test</b></span></li>
          <li><span class="oi">📍</span><span><b><?= e(STORE_NAME) ?> Retail Pvt Ltd</b><br><small class="muted">Warehouse 4, GIDC, Valsad, Gujarat 396001</small></span></li>
        </ul>
      </section>
    </aside>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
