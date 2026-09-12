<?php
/**
 * Fallback product artwork.
 *
 * Products that have a real photograph are served straight from
 * assets/products/. Everything else is drawn here: a packshot built from the
 * product's package type, brand colour and emoji, so a catalogue without
 * photography still reads as a shop rather than a wall of placeholders.
 *
 *   assets/image.php?p=42          → packshot for product 42
 *   assets/image.php?e=🍎&b=e6f7ec → bare tile (categories, favicons)
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$size = max(48, min(1200, (int) ($_GET['s'] ?? 500)));

$product = null;
if (isset($_GET['p'])) {
    $product = q1(
        'SELECT p.name, p.unit, p.emoji, p.tint, p.pack_type, b.name AS brand_name
           FROM products p LEFT JOIN brands b ON b.id = p.brand_id
          WHERE p.id = ?',
        [(int) $_GET['p']]
    );
}

$emoji = $product['emoji'] ?? ($_GET['e'] ?? '🛒');
$tint  = $product['tint']  ?? ('#' . preg_replace('/[^0-9a-fA-F]/', '', (string) ($_GET['b'] ?? 'e8f5ec')));
$tint  = preg_match('/^#[0-9a-fA-F]{6}$/', $tint) ? $tint : '#e8f5ec';
$pack  = $product['pack_type'] ?? 'plain';
$brand = $product['brand_name'] ?? '';
$name  = $product['name'] ?? '';
$unit  = $product['unit'] ?? '';

// Keep the emoji to a single glyph — the value can arrive from the query string.
$emoji = function_exists('grapheme_substr') ? grapheme_substr($emoji, 0, 2) : mb_substr($emoji, 0, 2);

/** Brand-stable colour: the same brand always gets the same pack. */
function pack_colours(string $seed): array
{
    $palette = [
        ['#1c6bb0', '#0d4780'], ['#c62828', '#8e1b1b'], ['#e08b00', '#a35f00'],
        ['#2e7d32', '#1a5220'], ['#6a3fb5', '#432578'], ['#0f8a8a', '#08595c'],
        ['#d2455f', '#94263c'], ['#b0631a', '#7a410b'], ['#3f51b5', '#25317a'],
        ['#00796b', '#004d40'], ['#8d6e63', '#5d4037'], ['#455a64', '#263238'],
    ];
    return $palette[crc32($seed) % count($palette)];
}

[$c1, $c2] = pack_colours($brand !== '' ? $brand : $name);

function sx(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** Wrap a product name onto the label, ellipsising whatever will not fit. */
function label_lines(string $name, int $maxLines = 2, int $perLine = 17): array
{
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $lines = [];
    $current = '';
    $dropped = false;

    foreach ($words as $word) {
        $candidate = $current === '' ? $word : $current . ' ' . $word;
        if (mb_strlen($candidate) <= $perLine) {
            $current = $candidate;
            continue;
        }
        if (count($lines) + 1 < $maxLines) {
            if ($current !== '') { $lines[] = $current; }
            $current = $word;
        } else {
            $dropped = true;
            break;
        }
    }
    if ($current !== '') { $lines[] = $current; }
    if ($dropped) {
        $last = count($lines) - 1;
        $lines[$last] = mb_substr($lines[$last], 0, $perLine - 1) . '…';
    }
    return array_slice($lines, 0, $maxLines) ?: [mb_substr($name, 0, $perLine)];
}

/**
 * SVG has no text wrapping or shrink-to-fit, so squeeze anything wider than the
 * label rather than let it bleed over the edge of the pack.
 */
function fit(string $text, float $fontSize, float $maxWidth, float $tracking = 0): string
{
    $estimate = mb_strlen($text) * ($fontSize * 0.58 + $tracking);
    return $estimate > $maxWidth
        ? sprintf(' textLength="%.1f" lengthAdjust="spacingAndGlyphs"', $maxWidth)
        : '';
}

/**
 * The package silhouette, drawn in a 100x100 space.
 * Each entry: [body path, cap/top markup, label rect y, label rect height].
 */
function silhouette(string $pack): array
{
    return match ($pack) {
        'bottle' => [
            'M38 40 q0-6 4-9 l0-7 h16 l0 7 q4 3 4 9 v40 q0 6-6 6 H44 q-6 0-6-6 Z',
            '<rect x="43" y="16" width="14" height="9" rx="2" fill="rgba(0,0,0,.28)"/>',
            52, 20,
        ],
        'jar' => [
            'M32 36 h36 v46 q0 6-6 6 H38 q-6 0-6-6 Z',
            '<rect x="30" y="26" width="40" height="11" rx="4" fill="rgba(0,0,0,.28)"/>',
            50, 20,
        ],
        'tube' => [
            'M40 34 h20 v48 q0 6-6 6 H46 q-6 0-6-6 Z',
            '<rect x="44" y="22" width="12" height="13" rx="3" fill="rgba(0,0,0,.28)"/>',
            48, 22,
        ],
        'carton' => [
            'M30 40 h40 v42 q0 6-6 6 H36 q-6 0-6-6 Z',
            '<path d="M30 40 L50 24 L70 40 Z" fill="rgba(255,255,255,.22)"/>'
            . '<rect x="46" y="22" width="8" height="6" rx="1.5" fill="rgba(0,0,0,.22)"/>',
            52, 22,
        ],
        'tub' => [
            'M30 40 L70 40 L66 82 q-.5 6-6.5 6 H40.5 q-6 0-6.5-6 Z',
            '<rect x="27" y="32" width="46" height="9" rx="4" fill="rgba(255,255,255,.3)"/>',
            50, 20,
        ],
        'sack' => [
            'M28 34 q22-6 44 0 v48 q0 6-6 6 H34 q-6 0-6-6 Z',
            '<path d="M28 34 q22-9 44 0 q-22 7-44 0 Z" fill="rgba(0,0,0,.2)"/>',
            48, 24,
        ],
        'box' => [
            'M27 36 h46 v46 q0 6-6 6 H33 q-6 0-6-6 Z',
            '<path d="M27 36 h46 v7 h-46 Z" fill="rgba(255,255,255,.22)"/>',
            50, 22,
        ],
        'tray' => [
            'M24 52 h52 v24 q0 6-6 6 H30 q-6 0-6-6 Z',
            '<g fill="rgba(255,255,255,.34)"><ellipse cx="36" cy="50" rx="9" ry="7"/>'
            . '<ellipse cx="50" cy="50" rx="9" ry="7"/><ellipse cx="64" cy="50" rx="9" ry="7"/></g>',
            60, 16,
        ],
        'bar' => [
            'M22 44 h56 q4 0 4 4 v22 q0 4-4 4 H22 q-4 0-4-4 V48 q0-4 4-4 Z',
            '<path d="M18 56 h64" stroke="rgba(255,255,255,.28)" stroke-width="1.5"/>',
            52, 16,
        ],
        'loose' => ['', '', 0, 0],
        default  => [            // pouch — the everyday snack/staple bag
            'M28 36 h44 v46 q0 6-6 6 H34 q-6 0-6-6 Z',
            '<path d="M28 36 l4-6 h36 l4 6 Z" fill="rgba(0,0,0,.22)"/>'
            . '<path d="M40 27 h20 v4 H40 Z" fill="rgba(0,0,0,.16)"/>',
            50, 22,
        ],
    };
}

[$body, $top, $labelY, $labelH] = silhouette($pack);
$lines = label_lines($name, $labelH >= 20 ? 2 : 1);

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$emojiOut = sx($emoji);
$brandOut = sx(mb_strtoupper(mb_substr($brand, 0, 18)));
$unitOut  = sx(mb_substr($unit, 0, 16));

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<svg xmlns="http://www.w3.org/2000/svg" width="<?= $size ?>" height="<?= $size ?>" viewBox="0 0 100 100" role="img">
  <title><?= sx($name !== '' ? $name : 'Product') ?></title>
  <defs>
    <radialGradient id="bg" cx="50%" cy="34%" r="76%">
      <stop offset="0%" stop-color="#ffffff"/>
      <stop offset="100%" stop-color="<?= sx($tint) ?>"/>
    </radialGradient>
    <linearGradient id="pk" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="<?= sx($c1) ?>"/>
      <stop offset="100%" stop-color="<?= sx($c2) ?>"/>
    </linearGradient>
    <linearGradient id="gloss" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0%" stop-color="#ffffff" stop-opacity=".26"/>
      <stop offset="42%" stop-color="#ffffff" stop-opacity="0"/>
    </linearGradient>
    <filter id="soft" x="-30%" y="-30%" width="160%" height="160%">
      <feGaussianBlur stdDeviation="2.4"/>
    </filter>
  </defs>

  <rect width="100" height="100" fill="url(#bg)"/>

<?php if ($pack === 'loose' || $body === ''): ?>
  <!-- fresh produce: no packaging, just the item on a soft disc -->
  <ellipse cx="50" cy="88" rx="24" ry="4.5" fill="rgba(20,40,25,.20)" filter="url(#soft)"/>
  <circle cx="50" cy="45" r="27" fill="#ffffff" opacity=".55"/>
  <text x="50" y="46" font-size="40" text-anchor="middle" dominant-baseline="central"
        font-family="'Apple Color Emoji','Segoe UI Emoji','Noto Color Emoji',sans-serif"><?= $emojiOut ?></text>
  <?php if ($unitOut !== ''): ?>
  <rect x="34" y="79" width="32" height="10" rx="5" fill="#ffffff" opacity=".82"/>
  <text x="50" y="84.6" font-size="5" text-anchor="middle" fill="#33513c" font-weight="800"
        dominant-baseline="middle"<?= fit($unitOut, 5, 28) ?>
        font-family="'Plus Jakarta Sans',system-ui,sans-serif"><?= $unitOut ?></text>
  <?php endif; ?>
<?php else: ?>
  <ellipse cx="50" cy="90" rx="23" ry="4" fill="rgba(20,30,45,.22)" filter="url(#soft)"/>
  <?= $top ?>
  <path d="<?= $body ?>" fill="url(#pk)"/>
  <path d="<?= $body ?>" fill="url(#gloss)"/>

  <!-- label panel -->
  <rect x="30" y="<?= $labelY ?>" width="40" height="<?= $labelH ?>" rx="2.5" fill="#ffffff" opacity=".95"/>
  <rect x="30" y="<?= $labelY ?>" width="40" height="1.6" fill="<?= sx($c1) ?>" opacity=".8"/>
  <?php if ($brandOut !== ''): ?>
  <text x="50" y="<?= $labelY + 6 ?>" font-size="3.2" text-anchor="middle" fill="<?= sx($c2) ?>"
        font-weight="800" letter-spacing=".3"<?= fit($brandOut, 3.2, 34, .3) ?>
        font-family="'Plus Jakarta Sans',system-ui,sans-serif"><?= $brandOut ?></text>
  <?php endif; ?>
  <?php
  $textTop = $labelY + ($brandOut !== '' ? 11 : 8);
  foreach ($lines as $i => $line): ?>
  <text x="50" y="<?= $textTop + $i * 4.3 ?>" font-size="3.6" text-anchor="middle" fill="#1d2530"
        font-weight="600"<?= fit($line, 3.6, 35) ?>
        font-family="'Plus Jakarta Sans',system-ui,sans-serif"><?= sx($line) ?></text>
  <?php endforeach; ?>

  <?php if ($unitOut !== ''): ?>
  <!-- net weight, stamped on the pack below the label -->
  <text x="50" y="<?= min(85, $labelY + $labelH + 6) ?>" font-size="3.8" text-anchor="middle" fill="#ffffff"
        font-weight="800" opacity=".95"<?= fit($unitOut, 3.8, 34) ?>
        font-family="'Plus Jakarta Sans',system-ui,sans-serif"><?= $unitOut ?></text>
  <?php endif; ?>
<?php endif; ?>
</svg>
