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

/**
 * URL of a product's artwork.
 *
 * Products that have a real photograph resolve to assets/products/. The rest
 * fall back to assets/image.php, which draws a packshot from the product's
 * package type, brand colour and emoji.
 */
function product_image(array $p, int $size = 500): string
{
    if (!empty($p['image'])) {
        return BASE_URL . 'assets/products/' . rawurlencode((string) $p['image']);
    }
    if (!empty($p['id'])) {
        return BASE_URL . 'assets/image.php?p=' . (int) $p['id'] . '&s=' . $size;
    }
    return BASE_URL . 'assets/image.php?e=' . rawurlencode($p['emoji'] ?? '🛒')
         . '&b=' . rawurlencode(ltrim($p['tint'] ?? '#e8f5ec', '#')) . '&s=' . $size;
}

/**
 * A complete <img> for a product, sized in CSS pixels.
 * Everything above the fold should pass $eager so the hero rail does not pop in.
 */
function product_img(array $p, int $box, string $class = '', bool $eager = false): string
{
    $alt = $p['name'] ?? 'Product';
    return sprintf(
        '<img src="%s" alt="%s" width="%d" height="%d" class="%s" loading="%s" decoding="async">',
        e(product_image($p, $box * 2)), e($alt), $box, $box, e($class), $eager ? 'eager' : 'lazy'
    );
}

/** Whether this row is backed by a real photograph rather than generated art. */
function has_photo(array $p): bool
{
    return !empty($p['image']);
}

// ------------------------------------------------------------------ uploads

/** Package silhouettes assets/image.php knows how to draw. */
const PACK_TYPES = ['pouch', 'bottle', 'carton', 'box', 'jar', 'tub', 'sack', 'tray', 'bar', 'tube', 'loose'];

const MAX_UPLOAD_BYTES = 4 * 1024 * 1024;
const PRODUCT_IMAGE_PX = 500;

/**
 * Validate and store an uploaded product photograph.
 *
 * The file is never trusted: the type comes from the image data rather than the
 * filename, the name is built from the product slug, and the bytes are decoded
 * and re-encoded through GD, so anything hidden inside the original is dropped.
 *
 * Returns the stored filename, or null (with $error set) when nothing was saved.
 */
function store_product_image(?array $file, string $slug, ?string &$error = null): ?string
{
    $error = null;

    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;                                     // nothing uploaded is fine
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'That file is larger than the server allows.'
            : 'The upload did not complete. Please try again.';
        return null;
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        $error = 'Keep the image under ' . (int) (MAX_UPLOAD_BYTES / 1048576) . ' MB.';
        return null;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        $error = 'That upload could not be verified.';
        return null;
    }

    $info = @getimagesize($file['tmp_name']);
    $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
    if ($info === false || !in_array($info[2], $allowed, true)) {
        $error = 'Upload a JPEG, PNG or WebP image.';
        return null;
    }
    if (!extension_loaded('gd')) {
        $error = 'The server is missing the gd extension, so images cannot be processed.';
        return null;
    }

    $source = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
    if ($source === false) {
        $error = 'That image could not be read.';
        return null;
    }

    // Square it on white, then resize — tiles all frame the same way.
    $w = imagesx($source);
    $h = imagesy($source);
    $side = max($w, $h);

    $square = imagecreatetruecolor($side, $side);
    imagefilledrectangle($square, 0, 0, $side, $side, imagecolorallocate($square, 255, 255, 255));
    imagecopy($square, $source, (int) (($side - $w) / 2), (int) (($side - $h) / 2), 0, 0, $w, $h);

    $out = imagecreatetruecolor(PRODUCT_IMAGE_PX, PRODUCT_IMAGE_PX);
    imagefilledrectangle($out, 0, 0, PRODUCT_IMAGE_PX, PRODUCT_IMAGE_PX, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $square, 0, 0, 0, 0, PRODUCT_IMAGE_PX, PRODUCT_IMAGE_PX, $side, $side);

    $dir = __DIR__ . '/../assets/products';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        imagedestroy($source); imagedestroy($square); imagedestroy($out);
        $error = 'The assets/products folder is not writable.';
        return null;
    }

    $webp = function_exists('imagewebp');
    $name = slugify($slug) . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . ($webp ? '.webp' : '.jpg');
    $ok   = $webp ? imagewebp($out, $dir . '/' . $name, 86) : imagejpeg($out, $dir . '/' . $name, 88);

    imagedestroy($source);
    imagedestroy($square);
    imagedestroy($out);

    if (!$ok) {
        $error = 'The image could not be saved.';
        return null;
    }
    return $name;
}
