<?php
/** JSON favourites toggle. */

require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) {
    json_out(['ok' => false, 'message' => 'POST only.'], 405);
}
require_csrf();

if (!is_logged_in()) {
    json_out(['ok' => false, 'message' => 'Please log in to save favourites.', 'login' => true], 401);
}

$productId = (int) ($_POST['product_id'] ?? 0);
if ($productId < 1 || !qv('SELECT id FROM products WHERE id = ? AND is_active = 1', [$productId])) {
    json_out(['ok' => false, 'message' => 'That product is unavailable.'], 422);
}

[$inList, $message] = wishlist_toggle($productId);

json_out([
    'ok'         => true,
    'in_list'    => $inList,
    'message'    => $message,
    'wish_count' => wishlist_count(),
]);
