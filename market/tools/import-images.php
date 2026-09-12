<?php
/**
 * Pull real product photographs from Open Food Facts.
 *
 *   php tools/import-images.php                 # fill in products with no image
 *   php tools/import-images.php --all           # re-fetch every product
 *   php tools/import-images.php --only=maggi    # just the ones matching a word
 *   php tools/import-images.php --limit=20      # stop after 20 downloads
 *   php tools/import-images.php --dry-run       # show matches, write nothing
 *
 * Open Food Facts is a free, open database of packaged groceries — no API key
 * and no signup. Photos are contributed by the public and licensed CC-BY-SA 3.0,
 * so if you publish the store, credit "Open Food Facts contributors".
 *
 * Fresh produce is not in Open Food Facts; those photographs ship with the
 * repository already (see assets/products/CREDITS.md).
 */

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line: php tools/import-images.php\n");
}

require_once __DIR__ . '/../includes/bootstrap.php';

// Overridable so the importer can be pointed at a mirror or a test double.
define('OFF_BASE', rtrim(getenv('OFF_BASE') ?: 'https://world.openfoodfacts.org', '/'));
define('OFF_SEARCH', OFF_BASE . '/cgi/search.pl');
const USER_AGENT   = 'MarketDemoStore/1.0 (PHP importer; https://github.com/)';
const TARGET_PX    = 500;
const REQUEST_GAP  = 1;     // OFF asks clients to stay under ~1 request/second
const IMAGE_DIR    = __DIR__ . '/../assets/products';

// ------------------------------------------------------------------ options
$options = getopt('', ['all', 'only::', 'limit::', 'dry-run', 'help']);
if (isset($options['help'])) {
    exit(file_get_contents(__FILE__, false, null, 0, 1200));
}
$refetchAll = isset($options['all']);
$dryRun     = isset($options['dry-run']);
$only       = $options['only'] ?? null;
$limit      = isset($options['limit']) ? max(1, (int) $options['limit']) : PHP_INT_MAX;

if (!is_dir(IMAGE_DIR) && !mkdir(IMAGE_DIR, 0775, true) && !is_dir(IMAGE_DIR)) {
    exit("Cannot create " . IMAGE_DIR . "\n");
}
if (!extension_loaded('gd')) {
    exit("The gd extension is required to resize downloaded photos.\n");
}

// ------------------------------------------------------------------ helpers
function http_get(string $url, int $timeout = 25): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_USERAGENT      => USER_AGENT,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code >= 200 && $code < 300) ? $body : null;
    }

    $context = stream_context_create(['http' => [
        'timeout' => $timeout,
        'header'  => 'User-Agent: ' . USER_AGENT . "\r\n",
    ]]);
    $body = @file_get_contents($url, false, $context);
    return $body === false ? null : $body;
}

/** Ask Open Food Facts for the best photo for a product name. */
function find_photo(string $name, string $brand): ?array
{
    // Brand first, because "Gold" alone matches half the database.
    $queries = array_unique(array_filter([
        trim($brand . ' ' . $name),
        $name,
    ]));

    foreach ($queries as $query) {
        $url = OFF_SEARCH . '?' . http_build_query([
            'search_terms' => $query,
            'search_simple' => 1,
            'action'        => 'process',
            'json'          => 1,
            'page_size'     => 8,
            'fields'        => 'product_name,brands,image_front_url,image_url',
        ]);

        $body = http_get($url);
        sleep(REQUEST_GAP);
        if ($body === null) {
            continue;
        }

        $data = json_decode($body, true);
        foreach ($data['products'] ?? [] as $candidate) {
            $image = $candidate['image_front_url'] ?? ($candidate['image_url'] ?? null);
            if (!$image) {
                continue;
            }
            // Prefer a hit that actually mentions the brand we asked for.
            $brandsFound = strtolower((string) ($candidate['brands'] ?? ''));
            $score = ($brand !== '' && str_contains($brandsFound, strtolower($brand))) ? 2 : 1;
            return [
                'url'   => $image,
                'title' => $candidate['product_name'] ?? $query,
                'score' => $score,
            ];
        }
    }
    return null;
}

/** Square, centre, resize and save as WebP (or JPEG where WebP is unavailable). */
function save_image(string $binary, string $slug): ?string
{
    $source = @imagecreatefromstring($binary);
    if ($source === false) {
        return null;
    }

    $w = imagesx($source);
    $h = imagesy($source);
    $side = max($w, $h);

    $canvas = imagecreatetruecolor($side, $side);
    $white  = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, 0, 0, $side, $side, $white);
    imagecopy($canvas, $source, (int) (($side - $w) / 2), (int) (($side - $h) / 2), 0, 0, $w, $h);

    $out = imagecreatetruecolor(TARGET_PX, TARGET_PX);
    imagefilledrectangle($out, 0, 0, TARGET_PX, TARGET_PX, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $canvas, 0, 0, 0, 0, TARGET_PX, TARGET_PX, $side, $side);

    $webp = function_exists('imagewebp');
    $file = $slug . ($webp ? '.webp' : '.jpg');
    $path = IMAGE_DIR . '/' . $file;
    $ok   = $webp ? imagewebp($out, $path, 86) : imagejpeg($out, $path, 88);

    imagedestroy($source);
    imagedestroy($canvas);
    imagedestroy($out);

    return $ok ? $file : null;
}

// --------------------------------------------------------------------- run
$where  = $refetchAll ? '1 = 1' : 'p.image IS NULL';
$params = [];
if ($only !== null && $only !== '') {
    $where   .= ' AND (p.name LIKE ? OR b.name LIKE ?)';
    $params[] = '%' . $only . '%';
    $params[] = '%' . $only . '%';
}

$products = qa(
    "SELECT p.id, p.name, p.slug, b.name AS brand
       FROM products p LEFT JOIN brands b ON b.id = p.brand_id
      WHERE $where
   ORDER BY p.id",
    $params
);

printf("Open Food Facts image import — %d product(s) to look up%s\n\n",
    count($products), $dryRun ? ' (dry run)' : '');

$found = $saved = $missed = 0;

foreach ($products as $product) {
    if ($saved >= $limit) {
        echo "\nReached --limit={$limit}.\n";
        break;
    }

    printf('%-46s ', mb_strimwidth($product['name'], 0, 44, '…'));

    $match = find_photo($product['name'], (string) ($product['brand'] ?? ''));
    if ($match === null) {
        echo "no match\n";
        $missed++;
        continue;
    }
    $found++;

    if ($dryRun) {
        printf("would use %s\n", mb_strimwidth($match['title'], 0, 40, '…'));
        continue;
    }

    $binary = http_get($match['url'], 40);
    if ($binary === null || strlen($binary) < 1024) {
        echo "download failed\n";
        $missed++;
        continue;
    }

    $file = save_image($binary, $product['slug']);
    if ($file === null) {
        echo "unreadable image\n";
        $missed++;
        continue;
    }

    q('UPDATE products SET image = ? WHERE id = ?', [$file, $product['id']]);
    printf("saved %s\n", $file);
    $saved++;
}

printf("\nMatched %d, saved %d, missed %d.\n", $found, $saved, $missed);
if (!$dryRun && $saved > 0) {
    echo "Photos are in assets/products/ and the products table now points at them.\n";
    echo "Credit Open Food Facts contributors (CC-BY-SA 3.0) if you publish this store.\n";
}
