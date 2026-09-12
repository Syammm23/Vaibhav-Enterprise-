<?php
/** Shared helpers: escaping, money, CSRF, flash messages, URL building. */

/** Escape for HTML output. Used as `e()` everywhere in the templates. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** ₹1,234 / ₹1,234.50 — Indian grouping, decimals only when they matter. */
function money($amount, bool $symbol = true): string
{
    $amount = (float) $amount;
    $whole  = floor(abs($amount));
    $paise  = round((abs($amount) - $whole) * 100);

    $s = (string) $whole;
    if (strlen($s) > 3) {
        $last3 = substr($s, -3);
        $rest  = substr($s, 0, -3);
        $rest  = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $s     = $rest . ',' . $last3;
    }
    if ($paise > 0) {
        $s .= '.' . str_pad((string) $paise, 2, '0', STR_PAD_LEFT);
    }

    return ($amount < 0 ? '-' : '') . ($symbol ? CURRENCY : '') . $s;
}

/** Percent saved off MRP, floored. Returns 0 when there is no real discount. */
function discount_pct($price, $mrp): int
{
    $price = (float) $price;
    $mrp   = (float) $mrp;
    if ($mrp <= 0 || $price >= $mrp) {
        return 0;
    }
    return (int) floor((($mrp - $price) / $mrp) * 100);
}

function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string) $s, '-') ?: 'item';
}

// --------------------------------------------------------------------- CSRF

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ok(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/** Reject the request unless it carries a valid token. */
function require_csrf(): void
{
    $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!csrf_ok($token)) {
        if (is_ajax()) {
            json_out(['ok' => false, 'message' => 'Your session expired. Please refresh the page.'], 419);
        }
        http_response_code(419);
        exit('Session expired — go back, refresh the page and try again.');
    }
}

// ------------------------------------------------------------------ request

function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function json_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

/** Where the user came from, when it is safely inside this site. */
function safe_back(string $fallback = 'index.php'): string
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref === '') {
        return $fallback;
    }
    $host = parse_url($ref, PHP_URL_HOST);
    if ($host !== null && $host !== ($_SERVER['HTTP_HOST'] ?? '')) {
        return $fallback;
    }
    return $ref;
}

// ------------------------------------------------------------------- flash

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function take_flashes(): array
{
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $all;
}

// --------------------------------------------------------------------- urls

/** Current query string with some keys replaced; null removes a key. */
function url_with(array $changes, ?array $base = null): string
{
    $params = $base ?? $_GET;
    foreach ($changes as $k => $v) {
        if ($v === null || $v === '' || $v === []) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    unset($params['page']);          // any filter change resets pagination
    if (isset($changes['page'])) {
        $params['page'] = $changes['page'];
    }
    $qs = http_build_query($params);
    return basename($_SERVER['SCRIPT_NAME']) . ($qs ? '?' . $qs : '');
}

/** Toggle one value inside a multi-select filter (brand[]=amul&brand[]=tata). */
function url_toggle(string $key, string $value): string
{
    $current = (array) ($_GET[$key] ?? []);
    $current = array_map('strval', $current);
    $index   = array_search($value, $current, true);
    if ($index === false) {
        $current[] = $value;
    } else {
        unset($current[$index]);
    }
    return url_with([$key => array_values($current)]);
}

function filter_active(string $key, string $value): bool
{
    return in_array($value, array_map('strval', (array) ($_GET[$key] ?? [])), true);
}

// ------------------------------------------------------------------ display

/** Star row markup: full/half/empty. */
function stars(float $rating, string $size = ''): string
{
    $out = '<span class="stars ' . e($size) . '" aria-label="' . number_format($rating, 1) . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) {
        $class = $rating >= $i ? 'is-full' : ($rating >= $i - 0.5 ? 'is-half' : '');
        $out  .= '<span class="star ' . $class . '">★</span>';
    }
    return $out . '</span>';
}

function rating_of(array $product): float
{
    $count = (int) ($product['rating_count'] ?? 0);
    if ($count === 0) {
        return 0.0;
    }
    return round(((int) $product['rating_sum']) / $count, 1);
}

/** "2.3k" for big review counts. */
function compact_number(int $n): string
{
    if ($n >= 1000000) return rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.') . 'M';
    if ($n >= 1000)    return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'k';
    return (string) $n;
}

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('j M Y', strtotime($datetime));
}

/** Human label for an order status enum. */
function status_label(string $status): string
{
    return [
        'placed'           => 'Order placed',
        'packed'           => 'Packed',
        'shipped'          => 'Shipped',
        'out_for_delivery' => 'Out for delivery',
        'delivered'        => 'Delivered',
        'cancelled'        => 'Cancelled',
    ][$status] ?? ucfirst($status);
}

function payment_label(string $method): string
{
    return [
        'card'       => 'Credit / Debit card',
        'upi'        => 'UPI',
        'netbanking' => 'Net banking',
        'wallet'     => 'Market Wallet',
        'cod'        => 'Cash on delivery',
    ][$method] ?? ucfirst($method);
}

/** Delivery promise shown on product and cart pages. */
function delivery_estimate(): string
{
    $hour = (int) date('G');
    if ($hour < 18) {
        return 'Today by ' . date('g A', strtotime('+3 hours'));
    }
    return 'Tomorrow, 7 AM – 10 AM';
}

/** Product image is generated on the fly — no binary assets to ship. */
function product_image(array $p, int $size = 400): string
{
    return 'assets/image.php?e=' . rawurlencode($p['emoji'] ?? '🛒')
         . '&b=' . rawurlencode(ltrim($p['tint'] ?? '#e8f5ec', '#'))
         . '&s=' . $size;
}
