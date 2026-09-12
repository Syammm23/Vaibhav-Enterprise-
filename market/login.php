<?php
/** Sign in. */

require_once __DIR__ . '/includes/bootstrap.php';

$next = (string) ($_GET['next'] ?? $_POST['next'] ?? 'index.php');
// Only ever bounce back to a path on this site.
if (preg_match('#^(https?:)?//#i', $next)) {
    $next = 'index.php';
}

if (is_logged_in()) {
    redirect($next);
}

$error = null;
$email = '';

if (is_post()) {
    require_csrf();
    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']);

    [$ok, $error] = login_user($email, $password, $remember);
    if ($ok) {
        flash('Welcome back, ' . explode(' ', current_user()['name'])[0] . '!');
        redirect($next);
    }
}

$pageTitle = 'Sign in | ' . STORE_NAME;
require __DIR__ . '/includes/header.php';
?>

<div class="wrap auth-wrap">
  <div class="auth-card">
    <aside class="auth-aside">
      <h2>Login</h2>
      <p>Get access to your orders, favourites, saved addresses and coupons.</p>
      <ul>
        <li>🛍️ One-tap reorder from your history</li>
        <li>❤️ Favourites synced across devices</li>
        <li>🏷️ Member-only coupons</li>
        <li>📦 Live order tracking</li>
      </ul>
    </aside>

    <div class="auth-body">
      <h1>Welcome back</h1>
      <p class="muted">Sign in to continue shopping.</p>

      <?php if ($error): ?><div class="alert alert-error" style="margin-top:14px"><?= e($error) ?></div><?php endif; ?>

      <form method="post" style="margin-top:16px" data-once>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">

        <div class="field">
          <label for="email">Email address</label>
          <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="email">
        </div>

        <div class="field">
          <label for="password">Password</label>
          <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
        </div>

        <label class="check">
          <input type="checkbox" name="remember" value="1">
          <span>Keep me signed in for <?= REMEMBER_DAYS ?> days</span>
        </label>

        <button class="btn btn-lg btn-block" type="submit" data-busy="Signing in…" style="margin-top:12px">Sign in</button>
      </form>

      <p class="auth-alt">New to <?= e(STORE_NAME) ?>? <a href="register.php?next=<?= urlencode($next) ?>">Create an account</a></p>

      <div class="demo-box">
        <b>Demo accounts</b>
        Shopper — <code>demo@market.test</code> / <code>demo@123</code><br>
        Admin — <code>admin@market.test</code> / <code>admin@123</code>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
