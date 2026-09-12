<?php
/** Create an account. */

require_once __DIR__ . '/includes/bootstrap.php';

$next = (string) ($_GET['next'] ?? $_POST['next'] ?? 'index.php');
if (preg_match('#^(https?:)?//#i', $next)) {
    $next = 'index.php';
}

if (is_logged_in()) {
    redirect($next);
}

$errors = [];
$values = ['name' => '', 'email' => '', 'phone' => ''];

if (is_post()) {
    require_csrf();
    $values = [
        'name'  => trim((string) ($_POST['name'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
    ];

    // Checked before register_user() so a rejected form never leaves an account behind.
    if (empty($_POST['terms'])) {
        $errors['terms'] = 'Please accept the terms to continue.';
    } else {
        [$ok, $errors, $newUserId] = register_user(
            $values['name'],
            $values['email'],
            $values['phone'],
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['confirm'] ?? '')
        );

        if ($ok) {
            start_session_for(q1('SELECT * FROM users WHERE id = ?', [$newUserId]));
            flash('Your account is ready. Happy shopping!');
            redirect($next);
        }
    }
}

$pageTitle = 'Create an account | ' . STORE_NAME;
require __DIR__ . '/includes/header.php';
?>

<div class="wrap auth-wrap">
  <div class="auth-card">
    <aside class="auth-aside">
      <h2>Looks like you're new here</h2>
      <p>Sign up to start ordering groceries in minutes.</p>
      <ul>
        <li>🎁 <?= money(50) ?> off your first order</li>
        <li>🚚 Free delivery above <?= money(FREE_DELIVERY_ABOVE) ?></li>
        <li>⏱️ 90-minute delivery slots</li>
        <li>🔒 Payments through a secure gateway</li>
      </ul>
    </aside>

    <div class="auth-body">
      <h1>Create your account</h1>
      <p class="muted">It takes about thirty seconds.</p>

      <form method="post" style="margin-top:16px" data-once>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">

        <div class="field">
          <label for="name">Full name</label>
          <input class="input <?= isset($errors['name']) ? 'is-error' : '' ?>" type="text" id="name" name="name"
                 value="<?= e($values['name']) ?>" required autofocus autocomplete="name">
          <?php if (isset($errors['name'])): ?><p class="error-text"><?= e($errors['name']) ?></p><?php endif; ?>
        </div>

        <div class="field">
          <label for="email">Email address</label>
          <input class="input <?= isset($errors['email']) ? 'is-error' : '' ?>" type="email" id="email" name="email"
                 value="<?= e($values['email']) ?>" required autocomplete="email">
          <?php if (isset($errors['email'])): ?><p class="error-text"><?= e($errors['email']) ?></p><?php endif; ?>
        </div>

        <div class="field">
          <label for="phone">Mobile number <span class="muted">(optional)</span></label>
          <input class="input <?= isset($errors['phone']) ? 'is-error' : '' ?>" type="tel" id="phone" name="phone"
                 value="<?= e($values['phone']) ?>" maxlength="15" autocomplete="tel" placeholder="10-digit number">
          <?php if (isset($errors['phone'])): ?><p class="error-text"><?= e($errors['phone']) ?></p><?php endif; ?>
        </div>

        <div class="field-row">
          <div class="field">
            <label for="password">Password</label>
            <input class="input <?= isset($errors['password']) ? 'is-error' : '' ?>" type="password" id="password"
                   name="password" required autocomplete="new-password">
            <?php if (isset($errors['password'])): ?><p class="error-text"><?= e($errors['password']) ?></p><?php endif; ?>
          </div>
          <div class="field">
            <label for="confirm">Confirm password</label>
            <input class="input <?= isset($errors['confirm']) ? 'is-error' : '' ?>" type="password" id="confirm"
                   name="confirm" required autocomplete="new-password">
            <?php if (isset($errors['confirm'])): ?><p class="error-text"><?= e($errors['confirm']) ?></p><?php endif; ?>
          </div>
        </div>

        <label class="check">
          <input type="checkbox" name="terms" value="1" checked>
          <span>I agree to the <a href="help.php#terms">terms of use</a> and <a href="help.php#privacy">privacy policy</a>.</span>
        </label>
        <?php if (isset($errors['terms'])): ?><p class="error-text"><?= e($errors['terms']) ?></p><?php endif; ?>

        <button class="btn btn-lg btn-block" type="submit" data-busy="Creating your account…" style="margin-top:12px">Create account</button>
      </form>

      <p class="auth-alt">Already have an account? <a href="login.php?next=<?= urlencode($next) ?>">Sign in</a></p>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
