<?php
/** JSON cart endpoint: add / set / remove. */

require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) {
    json_out(['ok' => false, 'message' => 'POST only.'], 405);
}
require_csrf();

$action    = $_POST['action'] ?? 'add';
$productId = (int) ($_POST['product_id'] ?? 0);
$qty       = (int) ($_POST['qty'] ?? 1);

if ($productId < 1) {
    json_out(['ok' => false, 'message' => 'Which product?'], 422);
}

switch ($action) {
    case 'add':
        [$ok, $message] = cart_add($productId, max(1, $qty));
        break;
    case 'set':
        [$ok, $message] = cart_set_qty($productId, max(0, $qty));
        break;
    case 'remove':
        cart_remove($productId);
        [$ok, $message] = [true, 'Removed from basket'];
        break;
    default:
        json_out(['ok' => false, 'message' => 'Unknown action.'], 422);
}

$totals = cart_totals();
$totals = cart_totals(active_coupon($totals['subtotal']), $totals['items']);

json_out([
    'ok'         => $ok,
    'message'    => $message,
    'cart_count' => $totals['units'],
    'subtotal'   => money($totals['subtotal']),
    'total'      => money($totals['total']),
]);
