<?php
/** Admin: coupon codes. */

$pageTitle = 'Coupons';
$adminPage = 'coupons';
require __DIR__ . '/header.php';

$errors = [];

if (is_post()) {
    require_csrf();
    $form = $_POST['form'] ?? '';

    if ($form === 'create') {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['code'] ?? '')));
        $data = [
            'description'  => trim((string) ($_POST['description'] ?? '')),
            'type'         => ($_POST['type'] ?? 'percent') === 'flat' ? 'flat' : 'percent',
            'value'        => (float) ($_POST['value'] ?? 0),
            'min_order'    => (float) ($_POST['min_order'] ?? 0),
            'max_discount' => ($_POST['max_discount'] ?? '') !== '' ? (float) $_POST['max_discount'] : null,
            'usage_limit'  => ($_POST['usage_limit'] ?? '') !== '' ? (int) $_POST['usage_limit'] : null,
            'expires_at'   => ($_POST['expires_at'] ?? '') !== '' ? $_POST['expires_at'] : null,
        ];

        if (strlen($code) < 3)     $errors['code']  = 'Codes need at least 3 letters or digits.';
        if ($data['value'] <= 0)   $errors['value'] = 'The discount must be greater than zero.';
        if ($data['type'] === 'percent' && $data['value'] > 90) {
            $errors['value'] = 'A percentage discount above 90% is almost certainly a typo.';
        }
        if (!$errors && qv('SELECT id FROM coupons WHERE code = ?', [$code])) {
            $errors['code'] = 'That code already exists.';
        }

        if (!$errors) {
            q('INSERT INTO coupons (code, description, type, value, min_order, max_discount, usage_limit, expires_at)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
              [$code, $data['description'], $data['type'], $data['value'], $data['min_order'],
               $data['max_discount'], $data['usage_limit'], $data['expires_at']]);
            flash('Coupon ' . $code . ' created.');
            redirect('coupons.php');
        }
    }

    if ($form === 'toggle') {
        q('UPDATE coupons SET is_active = 1 - is_active WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
        flash('Coupon updated.');
        redirect('coupons.php');
    }

    if ($form === 'delete') {
        q('DELETE FROM coupons WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
        flash('Coupon deleted.');
        redirect('coupons.php');
    }
}

$coupons = qa('SELECT * FROM coupons ORDER BY is_active DESC, min_order');
?>

<div class="admin-head">
  <div>
    <h1>Coupons</h1>
    <p class="muted tiny"><?= count($coupons) ?> code<?= count($coupons) === 1 ? '' : 's' ?></p>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:16px;align-items:start" class="admin-grid">
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Code</th><th>Discount</th><th>Min order</th><th>Used</th><th>Expires</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if (!$coupons): ?><tr><td colspan="7" class="muted">No coupons yet.</td></tr><?php endif; ?>
        <?php foreach ($coupons as $c): ?>
          <tr>
            <td>
              <strong><?= e($c['code']) ?></strong>
              <?php if ($c['description']): ?><br><small class="muted"><?= e($c['description']) ?></small><?php endif; ?>
            </td>
            <td>
              <?= $c['type'] === 'flat' ? money($c['value']) . ' off' : (int) $c['value'] . '% off' ?>
              <?php if ($c['max_discount'] !== null): ?><br><small class="muted">max <?= money($c['max_discount']) ?></small><?php endif; ?>
            </td>
            <td><?= money($c['min_order']) ?></td>
            <td><?= (int) $c['used_count'] ?><?= $c['usage_limit'] !== null ? ' / ' . (int) $c['usage_limit'] : '' ?></td>
            <td class="muted"><?= $c['expires_at'] ? e(date('j M Y', strtotime($c['expires_at']))) : 'Never' ?></td>
            <td><span class="chip <?= (int) $c['is_active'] === 1 ? 'tag-green' : 'tag-red' ?>"><?= (int) $c['is_active'] === 1 ? 'Active' : 'Paused' ?></span></td>
            <td>
              <div style="display:flex;gap:6px">
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="form" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $c['is_active'] === 1 ? 'Pause' : 'Activate' ?></button>
                </form>
                <form method="post" onsubmit="return confirm('Delete <?= e($c['code']) ?>?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="form" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <aside class="panel panel-pad">
    <h2 style="font-size:15px;margin-bottom:12px">New coupon</h2>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="create">

      <div class="field">
        <label for="code">Code</label>
        <input class="input <?= isset($errors['code']) ? 'is-error' : '' ?>" id="code" name="code"
               value="<?= e($_POST['code'] ?? '') ?>" placeholder="SUMMER20" style="text-transform:uppercase" required>
        <?php if (isset($errors['code'])): ?><p class="error-text"><?= e($errors['code']) ?></p><?php endif; ?>
      </div>

      <div class="field">
        <label for="description">Description</label>
        <input class="input" id="description" name="description" value="<?= e($_POST['description'] ?? '') ?>">
      </div>

      <div class="field-row">
        <div class="field">
          <label for="type">Type</label>
          <select class="select" id="type" name="type">
            <option value="percent">Percent</option>
            <option value="flat" <?= ($_POST['type'] ?? '') === 'flat' ? 'selected' : '' ?>>Flat amount</option>
          </select>
        </div>
        <div class="field">
          <label for="value">Value</label>
          <input class="input <?= isset($errors['value']) ? 'is-error' : '' ?>" id="value" name="value" type="number" step="0.01" min="0"
                 value="<?= e($_POST['value'] ?? '') ?>" required>
          <?php if (isset($errors['value'])): ?><p class="error-text"><?= e($errors['value']) ?></p><?php endif; ?>
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="min_order">Min order</label>
          <input class="input" id="min_order" name="min_order" type="number" step="0.01" min="0" value="<?= e($_POST['min_order'] ?? '0') ?>">
        </div>
        <div class="field">
          <label for="max_discount">Max discount</label>
          <input class="input" id="max_discount" name="max_discount" type="number" step="0.01" min="0"
                 value="<?= e($_POST['max_discount'] ?? '') ?>" placeholder="optional">
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="usage_limit">Usage limit</label>
          <input class="input" id="usage_limit" name="usage_limit" type="number" min="1" value="<?= e($_POST['usage_limit'] ?? '') ?>" placeholder="unlimited">
        </div>
        <div class="field">
          <label for="expires_at">Expires on</label>
          <input class="input" id="expires_at" name="expires_at" type="date" value="<?= e($_POST['expires_at'] ?? '') ?>">
        </div>
      </div>

      <button class="btn btn-block" type="submit">Create coupon</button>
    </form>
  </aside>
</div>

<?php require __DIR__ . '/footer.php'; ?>
