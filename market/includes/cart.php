<?php
/**
 * Cart, wishlist and pricing.
 *
 * Rows live in `cart_items` either against a user id (logged in) or against the
 * session id (guest). Logging in merges the guest rows into the account.
 */

/** The owner columns for the current visitor: ['user_id' => 3] or ['guest_key' => '...']. */
function cart_owner(): array
{
    $uid = user_id();
    return $uid ? ['user_id' => $uid] : ['guest_key' => session_id()];
}

function cart_where(): array
{
    $owner = cart_owner();
    $col   = array_key_first($owner);
    return ["$col = ?", [$owner[$col]]];
}

/** Cart lines joined to live product data. */
function cart_items(): array
{
    [$where, $params] = cart_where();
    return qa(
        "SELECT ci.id AS cart_id, ci.qty, p.*
           FROM cart_items ci
           JOIN products p ON p.id = ci.product_id
          WHERE ci.$where AND p.is_active = 1
       ORDER BY ci.added_at DESC",
        $params
    );
}

function cart_count(): int
{
    [$where, $params] = cart_where();
    return (int) qv("SELECT COALESCE(SUM(qty), 0) FROM cart_items WHERE $where", $params);
}

/** Add (or top up) a line. Returns [ok, message]. */
function cart_add(int $productId, int $qty = 1): array
{
    $product = q1('SELECT id, name, stock FROM products WHERE id = ? AND is_active = 1', [$productId]);
    if (!$product) {
        return [false, 'That product is no longer available.'];
    }
    if ((int) $product['stock'] < 1) {
        return [false, 'Out of stock right now — we will restock soon.'];
    }

    $owner   = cart_owner();
    $col     = array_key_first($owner);
    $existing = (int) qv(
        "SELECT COALESCE(qty, 0) FROM cart_items WHERE $col = ? AND product_id = ?",
        [$owner[$col], $productId]
    );

    $wanted = $existing + max(1, $qty);
    $capped = min($wanted, MAX_QTY_PER_ITEM, (int) $product['stock']);

    if ($existing > 0) {
        q("UPDATE cart_items SET qty = ? WHERE $col = ? AND product_id = ?", [$capped, $owner[$col], $productId]);
    } else {
        q("INSERT INTO cart_items ($col, product_id, qty) VALUES (?, ?, ?)", [$owner[$col], $productId, $capped]);
    }

    if ($capped < $wanted) {
        return [true, 'Only ' . $capped . ' of this item can be added.'];
    }
    return [true, 'Added to basket'];
}

/** Set an exact quantity; 0 removes the line. */
function cart_set_qty(int $productId, int $qty): array
{
    $owner = cart_owner();
    $col   = array_key_first($owner);

    if ($qty <= 0) {
        q("DELETE FROM cart_items WHERE $col = ? AND product_id = ?", [$owner[$col], $productId]);
        return [true, 'Removed from basket'];
    }

    $stock  = (int) qv('SELECT stock FROM products WHERE id = ?', [$productId]);
    $capped = min($qty, MAX_QTY_PER_ITEM, max($stock, 0));
    if ($capped < 1) {
        q("DELETE FROM cart_items WHERE $col = ? AND product_id = ?", [$owner[$col], $productId]);
        return [false, 'That item just went out of stock.'];
    }

    q("UPDATE cart_items SET qty = ? WHERE $col = ? AND product_id = ?", [$capped, $owner[$col], $productId]);
    return [true, $capped < $qty ? 'Only ' . $capped . ' available' : 'Basket updated'];
}

function cart_remove(int $productId): void
{
    $owner = cart_owner();
    $col   = array_key_first($owner);
    q("DELETE FROM cart_items WHERE $col = ? AND product_id = ?", [$owner[$col], $productId]);
}

function cart_clear(): void
{
    [$where, $params] = cart_where();
    q("DELETE FROM cart_items WHERE $where", $params);
}

/** Fold a guest's rows into the account they just signed into. */
function merge_guest_cart(int $userId, string $guestKey): void
{
    $guestRows = qa('SELECT product_id, qty FROM cart_items WHERE guest_key = ?', [$guestKey]);
    foreach ($guestRows as $row) {
        $existing = (int) qv(
            'SELECT COALESCE(qty, 0) FROM cart_items WHERE user_id = ? AND product_id = ?',
            [$userId, $row['product_id']]
        );
        $qty = min($existing + (int) $row['qty'], MAX_QTY_PER_ITEM);
        if ($existing > 0) {
            q('UPDATE cart_items SET qty = ? WHERE user_id = ? AND product_id = ?', [$qty, $userId, $row['product_id']]);
        } else {
            q('INSERT INTO cart_items (user_id, product_id, qty) VALUES (?, ?, ?)', [$userId, $row['product_id'], $qty]);
        }
    }
    q('DELETE FROM cart_items WHERE guest_key = ?', [$guestKey]);
}

// ------------------------------------------------------------------ pricing

/**
 * Every money figure the checkout needs, in one place.
 * Pass a coupon row (or null) to include the discount.
 */
function cart_totals(?array $coupon = null, ?array $items = null): array
{
    $items    = $items ?? cart_items();
    $subtotal = 0.0;
    $mrpTotal = 0.0;
    $units    = 0;

    foreach ($items as $item) {
        $subtotal += (float) $item['price'] * (int) $item['qty'];
        $mrpTotal += (float) $item['mrp']   * (int) $item['qty'];
        $units    += (int) $item['qty'];
    }

    $discount = $coupon ? coupon_discount($coupon, $subtotal) : 0.0;
    $payable  = max(0.0, $subtotal - $discount);

    $freeDelivery = $payable >= FREE_DELIVERY_ABOVE || $units === 0;
    $delivery     = $freeDelivery ? 0.0 : DELIVERY_FEE;
    $handling     = $freeDelivery ? 0.0 : HANDLING_FEE;

    return [
        'items'          => $items,
        'units'          => $units,
        'lines'          => count($items),
        'mrp_total'      => $mrpTotal,
        'subtotal'       => $subtotal,
        'product_saving' => max(0.0, $mrpTotal - $subtotal),
        'coupon_code'    => $coupon['code'] ?? null,
        'discount'       => $discount,
        'delivery_fee'   => $delivery,
        'handling_fee'   => $handling,
        'free_delivery'  => $freeDelivery,
        'away_from_free' => max(0.0, FREE_DELIVERY_ABOVE - $payable),
        'total'          => $payable + $delivery + $handling,
        'total_saving'   => max(0.0, $mrpTotal - $subtotal) + $discount + ($freeDelivery && $units > 0 ? DELIVERY_FEE : 0),
    ];
}

function coupon_discount(array $coupon, float $subtotal): float
{
    if ($subtotal < (float) $coupon['min_order']) {
        return 0.0;
    }
    if ($coupon['type'] === 'flat') {
        return min((float) $coupon['value'], $subtotal);
    }
    $off = $subtotal * ((float) $coupon['value'] / 100);
    if ($coupon['max_discount'] !== null) {
        $off = min($off, (float) $coupon['max_discount']);
    }
    return round($off, 2);
}

/** Look a coupon up and check it can be used for this basket. Returns [coupon|null, error|null]. */
function find_valid_coupon(string $code, float $subtotal): array
{
    $code   = strtoupper(trim($code));
    $coupon = q1('SELECT * FROM coupons WHERE code = ? AND is_active = 1', [$code]);

    if (!$coupon) {
        return [null, 'That coupon code is not valid.'];
    }
    if ($coupon['expires_at'] !== null && $coupon['expires_at'] < date('Y-m-d')) {
        return [null, 'This coupon has expired.'];
    }
    if ($coupon['usage_limit'] !== null && (int) $coupon['used_count'] >= (int) $coupon['usage_limit']) {
        return [null, 'This coupon has been fully redeemed.'];
    }
    if ($subtotal < (float) $coupon['min_order']) {
        return [null, 'Add items worth ' . money((float) $coupon['min_order'] - $subtotal) . ' more to use ' . $code . '.'];
    }
    return [$coupon, null];
}

/** The coupon currently held in the session, re-validated against the basket. */
function active_coupon(float $subtotal): ?array
{
    $code = $_SESSION['coupon'] ?? null;
    if (!$code) {
        return null;
    }
    [$coupon, $error] = find_valid_coupon($code, $subtotal);
    if ($error !== null) {
        unset($_SESSION['coupon']);          // basket shrank below the threshold
        return null;
    }
    return $coupon;
}

// ----------------------------------------------------------------- wishlist

function wishlist_ids(): array
{
    $uid = user_id();
    if (!$uid) {
        return [];
    }
    static $ids = null;
    if ($ids === null) {
        $ids = array_map('intval', q('SELECT product_id FROM wishlist_items WHERE user_id = ?', [$uid])
            ->fetchAll(PDO::FETCH_COLUMN));
    }
    return $ids;
}

function in_wishlist(int $productId): bool
{
    return in_array($productId, wishlist_ids(), true);
}

function wishlist_count(): int
{
    $uid = user_id();
    return $uid ? (int) qv('SELECT COUNT(*) FROM wishlist_items WHERE user_id = ?', [$uid]) : 0;
}

/** Returns [inList, message]. */
function wishlist_toggle(int $productId): array
{
    $uid = user_id();
    if (!$uid) {
        return [false, 'Please log in to save favourites.'];
    }
    if (in_wishlist($productId)) {
        q('DELETE FROM wishlist_items WHERE user_id = ? AND product_id = ?', [$uid, $productId]);
        return [false, 'Removed from favourites'];
    }
    q('INSERT IGNORE INTO wishlist_items (user_id, product_id) VALUES (?, ?)', [$uid, $productId]);
    return [true, 'Saved to favourites'];
}

// --------------------------------------------------------- recently viewed

function remember_view(int $productId): void
{
    $seen = $_SESSION['recent'] ?? [];
    $seen = array_values(array_diff($seen, [$productId]));
    array_unshift($seen, $productId);
    $_SESSION['recent'] = array_slice($seen, 0, 12);
}

function recently_viewed(int $excludeId = 0, int $limit = 6): array
{
    $ids = array_values(array_diff($_SESSION['recent'] ?? [], [$excludeId]));
    if (!$ids) {
        return [];
    }
    $ids   = array_slice($ids, 0, $limit);
    $holes = implode(',', array_fill(0, count($ids), '?'));
    $rows  = qa("SELECT * FROM products WHERE id IN ($holes) AND is_active = 1", $ids);

    // keep the most-recent-first order the session recorded
    $byId = [];
    foreach ($rows as $row) {
        $byId[(int) $row['id']] = $row;
    }
    $ordered = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $ordered[] = $byId[$id];
        }
    }
    return $ordered;
}
