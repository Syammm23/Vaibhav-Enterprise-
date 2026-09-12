<?php
/** Typeahead results for the header search box. */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$term = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($term) < 2) {
    echo json_encode(['products' => [], 'categories' => []]);
    exit;
}

$found = search_suggestions($term);

$products = array_map(static function (array $p): array {
    return [
        'name'          => $p['name'],
        'slug'          => $p['slug'],
        'emoji'         => $p['emoji'],
        'tint'          => $p['tint'],
        'image'         => product_image($p, 96),
        'unit'          => $p['unit'],
        'price'         => rtrim(rtrim(number_format((float) $p['price'], 2, '.', ''), '0'), '.'),
        'category_name' => $p['category_name'] ?? '',
    ];
}, $found['products']);

echo json_encode(['products' => $products, 'categories' => $found['categories']], JSON_UNESCAPED_UNICODE);
