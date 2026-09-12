<?php
/** Admin: registered shoppers. */

$pageTitle = 'Customers';
$adminPage = 'customers';
require __DIR__ . '/header.php';

if (is_post()) {
    require_csrf();
    $targetId = (int) ($_POST['id'] ?? 0);

    if ($targetId === user_id()) {
        flash('You cannot change your own account from here.', 'error');
        redirect('customers.php');
    }

    if (($_POST['form'] ?? '') === 'toggle') {
        q('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$targetId]);
        flash('Account access updated.');
    }
    redirect('customers.php');
}

$term = trim((string) ($_GET['q'] ?? ''));

$where  = ['1 = 1'];
$params = [];
if ($term !== '') {
    $where[]  = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    array_push($params, '%' . $term . '%', '%' . $term . '%', '%' . $term . '%');
}

$customers = qa('SELECT u.*,
                        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status <> "cancelled") AS order_count,
                        (SELECT COALESCE(SUM(o.total), 0) FROM orders o WHERE o.user_id = u.id AND o.status <> "cancelled") AS spent
                   FROM users u
                  WHERE ' . implode(' AND ', $where) . '
               ORDER BY u.created_at DESC LIMIT 200', $params);
?>

<div class="admin-head">
  <div>
    <h1>Customers</h1>
    <p class="muted tiny"><?= count($customers) ?> account<?= count($customers) === 1 ? '' : 's' ?></p>
  </div>
  <form method="get" style="display:flex;gap:8px">
    <input class="input" type="search" name="q" value="<?= e($term) ?>" placeholder="Name, email or phone">
    <button class="btn btn-ghost" type="submit">Search</button>
  </form>
</div>

<div class="table-wrap">
  <table class="data">
    <thead><tr><th></th><th>Name</th><th>Contact</th><th>Role</th><th>Orders</th><th>Spent</th><th>Joined</th><th>Access</th><th></th></tr></thead>
    <tbody>
      <?php if (!$customers): ?><tr><td colspan="9" class="muted">No accounts match that search.</td></tr><?php endif; ?>
      <?php foreach ($customers as $c): ?>
        <tr>
          <td><span class="avatar" style="background:<?= e($c['avatar_color']) ?>;border-color:transparent"><?= e(initials($c['name'])) ?></span></td>
          <td><strong><?= e($c['name']) ?></strong></td>
          <td class="wrap-cell"><?= e($c['email']) ?><?= $c['phone'] ? '<br><small class="muted">' . e($c['phone']) . '</small>' : '' ?></td>
          <td><span class="chip <?= $c['role'] === 'admin' ? 'tag-green' : '' ?>"><?= e(ucfirst($c['role'])) ?></span></td>
          <td><?= (int) $c['order_count'] ?></td>
          <td><?= money($c['spent']) ?></td>
          <td class="muted"><?= e(date('j M Y', strtotime($c['created_at']))) ?></td>
          <td><span class="chip <?= (int) $c['is_active'] === 1 ? 'tag-green' : 'tag-red' ?>"><?= (int) $c['is_active'] === 1 ? 'Active' : 'Disabled' ?></span></td>
          <td>
            <?php if ((int) $c['id'] !== user_id()): ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="toggle">
                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $c['is_active'] === 1 ? 'Disable' : 'Enable' ?></button>
              </form>
            <?php else: ?>
              <span class="muted tiny">You</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/footer.php'; ?>
