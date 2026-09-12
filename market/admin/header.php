<?php
/** Admin chrome. Every admin page requires an admin session. */

require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$adminPage = $adminPage ?? '';
$pageTitle = ($pageTitle ?? 'Dashboard') . ' · ' . STORE_NAME . ' admin';
?>
<!doctype html>
<html lang="en-IN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="../assets/image.php?e=%E2%9A%99%EF%B8%8F&amp;b=0f8a3c&amp;s=64" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css?v=1">
<script>window.MARKET = { csrf: <?= json_encode(csrf_token()) ?>, loggedIn: true };</script>
</head>
<body>
<div class="admin-shell">
  <aside class="admin-side">
    <a class="brand" href="index.php">
      <span class="brand-mark">🛒</span>
      <span class="brand-name" style="font-size:19px"><?= e(STORE_NAME) ?></span>
    </a>
    <nav>
      <a class="<?= $adminPage === 'dash' ? 'is-on' : '' ?>" href="index.php">📊 Dashboard</a>
      <a class="<?= $adminPage === 'products' ? 'is-on' : '' ?>" href="products.php">🧺 Products</a>
      <a class="<?= $adminPage === 'orders' ? 'is-on' : '' ?>" href="orders.php">📦 Orders</a>
      <a class="<?= $adminPage === 'customers' ? 'is-on' : '' ?>" href="customers.php">👥 Customers</a>
      <a class="<?= $adminPage === 'coupons' ? 'is-on' : '' ?>" href="coupons.php">🏷️ Coupons</a>
      <div class="sep"></div>
      <a href="../index.php">↗ View storefront</a>
      <form method="post" action="../logout.php"><?= csrf_field() ?>
        <button class="btn btn-ghost btn-block" type="submit" style="margin-top:10px;color:#fff;border-color:rgba(255,255,255,.25)">Log out</button>
      </form>
    </nav>
  </aside>

  <main class="admin-main">
    <?php foreach (take_flashes() as $f): ?>
      <div class="alert alert-<?= $f['type'] === 'error' ? 'error' : 'success' ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
