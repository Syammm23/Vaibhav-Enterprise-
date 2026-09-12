<?php
/** Account: profile, password, address book, activity snapshot. */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$userId = user_id();
$errors = [];
$open   = 'profile';

if (is_post()) {
    require_csrf();
    $form = $_POST['form'] ?? '';

    // ------------------------------------------------------------ profile
    if ($form === 'profile') {
        $name  = trim((string) ($_POST['name'] ?? ''));
        $phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));

        if (mb_strlen($name) < 2)                        $errors['name']  = 'Name must be at least 2 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors['email'] = 'Enter a valid email address.';
        if ($phone !== '' && strlen($phone) !== 10)      $errors['phone'] = 'Enter a 10-digit mobile number.';

        if (!$errors && qv('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $userId])) {
            $errors['email'] = 'Another account already uses that email.';
        }
        if (!$errors) {
            q('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?', [$name, $email, $phone ?: null, $userId]);
            flash('Profile updated.');
            redirect('account.php');
        }
    }

    // ----------------------------------------------------------- password
    if ($form === 'password') {
        $current = (string) ($_POST['current'] ?? '');
        $fresh   = (string) ($_POST['new'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');
        $open    = 'password';

        $hash = (string) qv('SELECT password_hash FROM users WHERE id = ?', [$userId]);
        if (!password_verify($current, $hash))  $errors['current'] = 'That is not your current password.';
        if (strlen($fresh) < 6)                 $errors['new']     = 'Use at least 6 characters.';
        if ($fresh !== $confirm)                $errors['confirm'] = 'The two passwords do not match.';

        if (!$errors) {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($fresh, PASSWORD_DEFAULT), $userId]);
            flash('Password changed.');
            redirect('account.php');
        }
    }

    // ------------------------------------------------------------ address
    if ($form === 'address-add') {
        $open = 'addresses';
        $a = [
            'label'     => in_array($_POST['label'] ?? '', ['Home', 'Work', 'Other'], true) ? $_POST['label'] : 'Home',
            'full_name' => trim((string) ($_POST['full_name'] ?? '')),
            'phone'     => preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? '')),
            'line1'     => trim((string) ($_POST['line1'] ?? '')),
            'line2'     => trim((string) ($_POST['line2'] ?? '')),
            'city'      => trim((string) ($_POST['city'] ?? '')),
            'state'     => trim((string) ($_POST['state'] ?? '')),
            'pincode'   => preg_replace('/\D+/', '', (string) ($_POST['pincode'] ?? '')),
        ];
        if (mb_strlen($a['full_name']) < 2) $errors['a_name']    = 'Recipient name is required.';
        if (strlen($a['phone']) !== 10)     $errors['a_phone']   = 'Enter a 10-digit mobile number.';
        if (mb_strlen($a['line1']) < 5)     $errors['a_line1']   = 'Address line is too short.';
        if ($a['city'] === '')              $errors['a_city']    = 'City is required.';
        if ($a['state'] === '')             $errors['a_state']   = 'State is required.';
        if (strlen($a['pincode']) !== 6)    $errors['a_pincode'] = 'Enter a 6-digit pincode.';

        if (!$errors) {
            $isFirst = (int) qv('SELECT COUNT(*) FROM addresses WHERE user_id = ?', [$userId]) === 0;
            q('INSERT INTO addresses (user_id, label, full_name, phone, line1, line2, city, state, pincode, is_default)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
              [$userId, $a['label'], $a['full_name'], $a['phone'], $a['line1'], $a['line2'] ?: null,
               $a['city'], $a['state'], $a['pincode'], $isFirst ? 1 : 0]);
            flash('Address added.');
            redirect('account.php#addresses');
        }
    }

    if ($form === 'address-default') {
        $addressId = (int) ($_POST['address_id'] ?? 0);
        q('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [$userId]);
        q('UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?', [$addressId, $userId]);
        flash('Default address updated.');
        redirect('account.php#addresses');
    }

    if ($form === 'address-delete') {
        q('DELETE FROM addresses WHERE id = ? AND user_id = ?', [(int) ($_POST['address_id'] ?? 0), $userId]);
        flash('Address removed.');
        redirect('account.php#addresses');
    }
}

$user      = current_user();
$addresses = qa('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC', [$userId]);
$stats     = q1('SELECT COUNT(*) AS orders, COALESCE(SUM(total), 0) AS spent
                   FROM orders WHERE user_id = ? AND status <> "cancelled"', [$userId]);
$recent    = qa('SELECT * FROM orders WHERE user_id = ? ORDER BY placed_at DESC LIMIT 3', [$userId]);

$pageTitle = 'Your account | ' . STORE_NAME;
require __DIR__ . '/includes/header.php';
?>

<div class="wrap page">
  <nav class="breadcrumb"><a href="index.php">Home</a> <span class="sep">›</span> <span>Account</span></nav>

  <div class="acct">
    <aside class="acct-nav">
      <a class="is-on" href="#profile">👤 Profile</a>
      <a href="#addresses">📍 Addresses</a>
      <a href="#security">🔐 Password</a>
      <a href="orders.php">📦 Orders</a>
      <a href="wishlist.php">❤️ Favourites</a>
      <a href="offers.php">🎁 Coupons</a>
      <?php if (is_admin()): ?><a href="admin/index.php">⚙️ Admin dashboard</a><?php endif; ?>
    </aside>

    <div>
      <!-- ---------------------------------------------------- snapshot -->
      <div class="panel panel-pad" style="margin-bottom:16px;display:flex;gap:18px;align-items:center;flex-wrap:wrap">
        <span class="avatar lg" style="background:<?= e($user['avatar_color']) ?>;width:60px;height:60px;font-size:20px">
          <?= e(initials($user['name'])) ?>
        </span>
        <div style="flex:1;min-width:180px">
          <h1 style="font-size:20px"><?= e($user['name']) ?></h1>
          <p class="muted tiny" style="margin:2px 0 0">
            <?= e($user['email']) ?> · member since <?= e(date('M Y', strtotime($user['created_at']))) ?>
            <?php if ($user['role'] === 'admin'): ?><span class="chip tag-green">Admin</span><?php endif; ?>
          </p>
        </div>
        <div style="display:flex;gap:22px;text-align:center">
          <div><strong style="font-size:20px"><?= (int) $stats['orders'] ?></strong><br><small class="muted">Orders</small></div>
          <div><strong style="font-size:20px"><?= money($stats['spent']) ?></strong><br><small class="muted">Spent</small></div>
          <div><strong style="font-size:20px"><?= wishlist_count() ?></strong><br><small class="muted">Favourites</small></div>
        </div>
      </div>

      <!-- ----------------------------------------------------- profile -->
      <section class="panel panel-pad" style="margin-bottom:16px" id="profile">
        <h2 style="font-size:16px;margin-bottom:12px">Profile details</h2>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="form" value="profile">
          <div class="field-row">
            <div class="field">
              <label for="name">Full name</label>
              <input class="input <?= isset($errors['name']) ? 'is-error' : '' ?>" id="name" name="name" value="<?= e($user['name']) ?>" required>
              <?php if (isset($errors['name'])): ?><p class="error-text"><?= e($errors['name']) ?></p><?php endif; ?>
            </div>
            <div class="field">
              <label for="phone">Mobile number</label>
              <input class="input <?= isset($errors['phone']) ? 'is-error' : '' ?>" id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>">
              <?php if (isset($errors['phone'])): ?><p class="error-text"><?= e($errors['phone']) ?></p><?php endif; ?>
            </div>
          </div>
          <div class="field">
            <label for="email">Email address</label>
            <input class="input <?= isset($errors['email']) ? 'is-error' : '' ?>" id="email" type="email" name="email" value="<?= e($user['email']) ?>" required>
            <?php if (isset($errors['email'])): ?><p class="error-text"><?= e($errors['email']) ?></p><?php endif; ?>
          </div>
          <button class="btn" type="submit">Save changes</button>
        </form>
      </section>

      <!-- --------------------------------------------------- addresses -->
      <section class="panel panel-pad" style="margin-bottom:16px" id="addresses">
        <h2 style="font-size:16px;margin-bottom:12px">Saved addresses</h2>

        <?php if (!$addresses): ?>
          <p class="muted">No addresses saved yet — add one below.</p>
        <?php else: ?>
          <div class="addr-grid" style="margin-bottom:18px">
            <?php foreach ($addresses as $a): ?>
              <div class="addr <?= (int) $a['is_default'] === 1 ? 'is-default' : '' ?>">
                <span class="chip" style="position:absolute;top:12px;right:12px"><?= e($a['label']) ?></span>
                <strong><?= e($a['full_name']) ?></strong>
                <?= e($a['line1']) ?><?= $a['line2'] ? ', ' . e($a['line2']) : '' ?><br>
                <?= e($a['city']) ?>, <?= e($a['state']) ?> — <?= e($a['pincode']) ?><br>
                📞 <?= e($a['phone']) ?>
                <div class="addr-actions">
                  <?php if ((int) $a['is_default'] !== 1): ?>
                    <form method="post"><?= csrf_field() ?>
                      <input type="hidden" name="form" value="address-default">
                      <input type="hidden" name="address_id" value="<?= (int) $a['id'] ?>">
                      <button class="link-btn save" type="submit">Set as default</button>
                    </form>
                  <?php else: ?>
                    <span class="tiny" style="color:var(--green-700);font-weight:700">✓ Default</span>
                  <?php endif; ?>
                  <form method="post" onsubmit="return confirm('Remove this address?')"><?= csrf_field() ?>
                    <input type="hidden" name="form" value="address-delete">
                    <input type="hidden" name="address_id" value="<?= (int) $a['id'] ?>">
                    <button class="link-btn" type="submit">Delete</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <details <?= $open === 'addresses' ? 'open' : '' ?>>
          <summary style="cursor:pointer;font-weight:700;font-size:13.5px;color:var(--green-700)">+ Add a new address</summary>
          <form method="post" style="margin-top:14px">
            <?= csrf_field() ?><input type="hidden" name="form" value="address-add">
            <div class="field-row">
              <div class="field">
                <label for="a_name">Full name</label>
                <input class="input <?= isset($errors['a_name']) ? 'is-error' : '' ?>" id="a_name" name="full_name" value="<?= e($_POST['full_name'] ?? $user['name']) ?>" required>
                <?php if (isset($errors['a_name'])): ?><p class="error-text"><?= e($errors['a_name']) ?></p><?php endif; ?>
              </div>
              <div class="field">
                <label for="a_phone">Mobile number</label>
                <input class="input <?= isset($errors['a_phone']) ? 'is-error' : '' ?>" id="a_phone" name="phone" value="<?= e($_POST['phone'] ?? $user['phone'] ?? '') ?>" required>
                <?php if (isset($errors['a_phone'])): ?><p class="error-text"><?= e($errors['a_phone']) ?></p><?php endif; ?>
              </div>
            </div>
            <div class="field">
              <label for="a_line1">Flat, house no., building</label>
              <input class="input <?= isset($errors['a_line1']) ? 'is-error' : '' ?>" id="a_line1" name="line1" value="<?= e($_POST['line1'] ?? '') ?>" required>
              <?php if (isset($errors['a_line1'])): ?><p class="error-text"><?= e($errors['a_line1']) ?></p><?php endif; ?>
            </div>
            <div class="field">
              <label for="a_line2">Area, street, sector <span class="muted">(optional)</span></label>
              <input class="input" id="a_line2" name="line2" value="<?= e($_POST['line2'] ?? '') ?>">
            </div>
            <div class="field-row">
              <div class="field">
                <label for="a_city">City</label>
                <input class="input <?= isset($errors['a_city']) ? 'is-error' : '' ?>" id="a_city" name="city" value="<?= e($_POST['city'] ?? '') ?>" required>
                <?php if (isset($errors['a_city'])): ?><p class="error-text"><?= e($errors['a_city']) ?></p><?php endif; ?>
              </div>
              <div class="field">
                <label for="a_state">State</label>
                <input class="input <?= isset($errors['a_state']) ? 'is-error' : '' ?>" id="a_state" name="state" value="<?= e($_POST['state'] ?? '') ?>" required>
                <?php if (isset($errors['a_state'])): ?><p class="error-text"><?= e($errors['a_state']) ?></p><?php endif; ?>
              </div>
            </div>
            <div class="field-row">
              <div class="field">
                <label for="a_pincode">Pincode</label>
                <input class="input <?= isset($errors['a_pincode']) ? 'is-error' : '' ?>" id="a_pincode" name="pincode" maxlength="6" value="<?= e($_POST['pincode'] ?? '') ?>" required>
                <?php if (isset($errors['a_pincode'])): ?><p class="error-text"><?= e($errors['a_pincode']) ?></p><?php endif; ?>
              </div>
              <div class="field">
                <label for="a_label">Address type</label>
                <select class="select" id="a_label" name="label"><option>Home</option><option>Work</option><option>Other</option></select>
              </div>
            </div>
            <button class="btn" type="submit">Save address</button>
          </form>
        </details>
      </section>

      <!-- ---------------------------------------------------- password -->
      <section class="panel panel-pad" style="margin-bottom:16px" id="security">
        <h2 style="font-size:16px;margin-bottom:12px">Change password</h2>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="form" value="password">
          <div class="field">
            <label for="current">Current password</label>
            <input class="input <?= isset($errors['current']) ? 'is-error' : '' ?>" type="password" id="current" name="current" required autocomplete="current-password">
            <?php if (isset($errors['current'])): ?><p class="error-text"><?= e($errors['current']) ?></p><?php endif; ?>
          </div>
          <div class="field-row">
            <div class="field">
              <label for="new">New password</label>
              <input class="input <?= isset($errors['new']) ? 'is-error' : '' ?>" type="password" id="new" name="new" required autocomplete="new-password">
              <?php if (isset($errors['new'])): ?><p class="error-text"><?= e($errors['new']) ?></p><?php endif; ?>
            </div>
            <div class="field">
              <label for="confirm2">Confirm new password</label>
              <input class="input <?= isset($errors['confirm']) ? 'is-error' : '' ?>" type="password" id="confirm2" name="confirm" required autocomplete="new-password">
              <?php if (isset($errors['confirm'])): ?><p class="error-text"><?= e($errors['confirm']) ?></p><?php endif; ?>
            </div>
          </div>
          <button class="btn" type="submit">Update password</button>
        </form>
      </section>

      <!-- ----------------------------------------------- recent orders -->
      <?php if ($recent): ?>
        <section class="panel panel-pad">
          <div class="row-between" style="margin-bottom:12px">
            <h2 style="font-size:16px">Recent orders</h2>
            <a class="more" href="orders.php" style="color:var(--green-700);font-weight:700;font-size:12.5px">View all →</a>
          </div>
          <?php foreach ($recent as $o): ?>
            <div class="row-between" style="padding:10px 0;border-top:1px solid var(--line-2)">
              <div>
                <strong style="font-size:13.5px"><?= e($o['order_number']) ?></strong>
                <p class="muted tiny" style="margin:0"><?= e(date('j M Y', strtotime($o['placed_at']))) ?> · <?= money($o['total']) ?></p>
              </div>
              <div style="display:flex;gap:9px;align-items:center">
                <span class="status-tag st-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span>
                <a class="btn btn-ghost btn-sm" href="order.php?number=<?= e($o['order_number']) ?>">Details</a>
              </div>
            </div>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
