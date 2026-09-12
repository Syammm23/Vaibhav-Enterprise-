</main>

<footer class="site-footer">
  <div class="wrap footer-top">
    <div class="footer-brand">
      <a class="brand" href="index.php">
        <span class="brand-mark" aria-hidden="true">🛒</span>
        <span class="brand-name"><?= e(STORE_NAME) ?></span>
      </a>
      <p><?= e(STORE_TAGLINE) ?>. Over 90 everyday essentials from the brands you already trust, picked and packed the same day you order.</p>
      <div class="footer-badges">
        <span>🔒 Secure checkout</span>
        <span>↩️ Easy returns</span>
        <span>🧊 Cold chain</span>
      </div>
    </div>

    <div class="footer-col">
      <h4>Shop</h4>
      <?php foreach (array_slice(category_tree(), 0, 6) as $cat): ?>
        <a href="products.php?category=<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a>
      <?php endforeach; ?>
    </div>

    <div class="footer-col">
      <h4>Your account</h4>
      <a href="account.php">Profile</a>
      <a href="orders.php">Orders</a>
      <a href="wishlist.php">Favourites</a>
      <a href="cart.php">Basket</a>
      <a href="offers.php">Coupons</a>
    </div>

    <div class="footer-col">
      <h4>Help</h4>
      <a href="help.php">Customer support</a>
      <a href="help.php#returns">Returns &amp; refunds</a>
      <a href="help.php#delivery">Delivery areas</a>
      <a href="help.php#payments">Payment options</a>
    </div>

    <div class="footer-col">
      <h4>We accept</h4>
      <div class="paylogos">
        <span>VISA</span><span>Mastercard</span><span>RuPay</span><span>UPI</span><span>Net banking</span><span>COD</span>
      </div>
      <p class="footer-note">Payments on this store run through a <strong>demo gateway</strong>. No real money moves and no card data is ever stored.</p>
    </div>
  </div>

  <div class="wrap footer-bottom">
    <span>© <?= date('Y') ?> <?= e(STORE_NAME) ?>. A demonstration storefront built with PHP &amp; MySQL.</span>
    <span class="footer-links">
      <a href="help.php#privacy">Privacy</a>
      <a href="help.php#terms">Terms</a>
      <a href="help.php">Contact</a>
    </span>
  </div>
</footer>

<script src="assets/js/app.js?v=1"></script>
</body>
</html>
