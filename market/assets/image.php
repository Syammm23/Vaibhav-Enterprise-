<?php
/**
 * Product artwork generator.
 *
 * The demo catalogue ships no photographs, so each tile is drawn as an SVG
 * from the product's emoji and tint colour: assets/image.php?e=🍎&b=e6f7ec&s=400
 */

$emoji = $_GET['e'] ?? '🛒';
$bg    = preg_replace('/[^0-9a-fA-F]/', '', (string) ($_GET['b'] ?? 'e8f5ec'));
$size  = max(32, min(1200, (int) ($_GET['s'] ?? 400)));

if (strlen($bg) !== 6) {
    $bg = 'e8f5ec';
}
// Keep it to a single grapheme so a crafted query cannot stuff the file.
if (function_exists('grapheme_substr')) {
    $emoji = grapheme_substr($emoji, 0, 2);
} else {
    $emoji = mb_substr($emoji, 0, 2);
}
$emoji = htmlspecialchars($emoji, ENT_QUOTES | ENT_XML1, 'UTF-8');

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=604800, immutable');

$font = round($size * 0.52);
$mid  = $size / 2;

echo <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="$size" height="$size" viewBox="0 0 $size $size" role="img">
  <defs>
    <radialGradient id="g" cx="50%" cy="38%" r="72%">
      <stop offset="0%" stop-color="#ffffff" stop-opacity=".85"/>
      <stop offset="100%" stop-color="#$bg"/>
    </radialGradient>
  </defs>
  <rect width="$size" height="$size" fill="#$bg"/>
  <rect width="$size" height="$size" fill="url(#g)"/>
  <text x="$mid" y="$mid" font-size="$font" text-anchor="middle" dominant-baseline="central"
        font-family="'Apple Color Emoji','Segoe UI Emoji','Noto Color Emoji',sans-serif">$emoji</text>
</svg>
SVG;
