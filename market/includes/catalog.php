<?php
/** Catalog queries: navigation, search, filtered listings. */

/** Top-level categories with their children, cached per request. */
function category_tree(): array
{
    static $tree = null;
    if ($tree !== null) {
        return $tree;
    }

    $rows = qa('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name');
    $tree = [];
    $index = [];
    foreach ($rows as $row) {
        $row['children'] = [];
        $index[(int) $row['id']] = $row;
    }
    foreach ($index as $id => $row) {
        if ($row['parent_id'] === null) {
            continue;
        }
        $index[(int) $row['parent_id']]['children'][] = $row;
    }
    foreach ($index as $row) {
        if ($row['parent_id'] === null) {
            $tree[] = $row;
        }
    }
    return $tree;
}

function category_by_slug(string $slug): ?array
{
    return q1('SELECT * FROM categories WHERE slug = ? AND is_active = 1', [$slug]);
}

/** A category id plus every descendant id — so a parent listing shows its children's products. */
function category_branch_ids(int $categoryId): array
{
    $ids      = [$categoryId];
    $children = q('SELECT id FROM categories WHERE parent_id = ?', [$categoryId])->fetchAll(PDO::FETCH_COLUMN);
    foreach ($children as $childId) {
        $ids = array_merge($ids, category_branch_ids((int) $childId));
    }
    return array_map('intval', $ids);
}

function all_brands(): array
{
    return qa('SELECT b.*, COUNT(p.id) AS product_count
                 FROM brands b
                 JOIN products p ON p.brand_id = b.id AND p.is_active = 1
             GROUP BY b.id
             ORDER BY b.name');
}

/**
 * The one query behind the listing page, search results and every widget.
 *
 * Options: category, q, brand[], min_price, max_price, rating, discount,
 *          veg, organic, in_stock, sort, page, per_page, featured, ids, exclude
 * Returns ['items' => [...], 'total' => int, 'pages' => int, 'page' => int].
 */
function find_products(array $o = []): array
{
    $where  = ['p.is_active = 1'];
    $params = [];

    if (!empty($o['category'])) {
        $cat = is_array($o['category']) ? $o['category'] : category_by_slug((string) $o['category']);
        if ($cat) {
            $ids   = category_branch_ids((int) $cat['id']);
            $holes = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "p.category_id IN ($holes)";
            $params  = array_merge($params, $ids);
        } else {
            $where[] = '1 = 0';                       // unknown slug: show nothing, not everything
        }
    }

    if (!empty($o['q'])) {
        $term = trim((string) $o['q']);
        $where[] = '(p.name LIKE ? OR p.short_desc LIKE ? OR p.description LIKE ? OR b.name LIKE ? OR c.name LIKE ?)';
        $like    = '%' . $term . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }

    if (!empty($o['brand'])) {
        $slugs = array_values(array_filter((array) $o['brand']));
        if ($slugs) {
            $holes   = implode(',', array_fill(0, count($slugs), '?'));
            $where[] = "b.slug IN ($holes)";
            $params  = array_merge($params, $slugs);
        }
    }

    if (isset($o['min_price']) && $o['min_price'] !== '' && $o['min_price'] !== null) {
        $where[]  = 'p.price >= ?';
        $params[] = (float) $o['min_price'];
    }
    if (isset($o['max_price']) && $o['max_price'] !== '' && $o['max_price'] !== null) {
        $where[]  = 'p.price <= ?';
        $params[] = (float) $o['max_price'];
    }

    if (!empty($o['rating'])) {
        $where[]  = '(p.rating_count > 0 AND (p.rating_sum / p.rating_count) >= ?)';
        $params[] = (float) $o['rating'];
    }

    if (!empty($o['discount'])) {
        $where[]  = '(p.mrp > 0 AND ((p.mrp - p.price) / p.mrp) * 100 >= ?)';
        $params[] = (float) $o['discount'];
    }

    if (!empty($o['veg']))      $where[] = 'p.is_veg = 1';
    if (!empty($o['organic']))  $where[] = 'p.is_organic = 1';
    if (!empty($o['in_stock'])) $where[] = 'p.stock > 0';
    if (!empty($o['featured'])) $where[] = 'p.is_featured = 1';

    if (!empty($o['ids'])) {
        $ids   = array_map('intval', (array) $o['ids']);
        $holes = implode(',', array_fill(0, count($ids), '?'));
        $where[] = "p.id IN ($holes)";
        $params  = array_merge($params, $ids);
    }
    if (!empty($o['exclude'])) {
        $where[]  = 'p.id <> ?';
        $params[] = (int) $o['exclude'];
    }

    $sql = 'FROM products p
            LEFT JOIN brands b     ON b.id = p.brand_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE ' . implode(' AND ', $where);

    $total = (int) qv('SELECT COUNT(*) ' . $sql, $params);

    $order = match ($o['sort'] ?? 'popularity') {
        'price_asc'  => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'newest'     => 'p.created_at DESC, p.id DESC',
        'discount'   => '((p.mrp - p.price) / GREATEST(p.mrp, 1)) DESC',
        'rating'     => '(p.rating_sum / GREATEST(p.rating_count, 1)) DESC, p.rating_count DESC',
        'name'       => 'p.name ASC',
        default      => 'p.stock > 0 DESC, p.sold_count DESC, p.rating_count DESC',
    };

    $perPage = max(1, (int) ($o['per_page'] ?? PRODUCTS_PER_PAGE));
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = min(max(1, (int) ($o['page'] ?? 1)), $pages);
    $offset  = ($page - 1) * $perPage;

    $items = qa(
        "SELECT p.*, b.name AS brand_name, b.slug AS brand_slug, c.name AS category_name, c.slug AS category_slug
         $sql ORDER BY $order LIMIT $perPage OFFSET $offset",
        $params
    );

    return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page, 'per_page' => $perPage];
}

/** Shorthand for widgets that just need a handful of cards. */
function products(array $options = []): array
{
    return find_products($options)['items'];
}

function product_by_slug(string $slug): ?array
{
    return q1(
        'SELECT p.*, b.name AS brand_name, b.slug AS brand_slug, c.name AS category_name, c.slug AS category_slug,
                c.parent_id AS category_parent
           FROM products p
      LEFT JOIN brands b     ON b.id = p.brand_id
      LEFT JOIN categories c ON c.id = p.category_id
          WHERE p.slug = ? AND p.is_active = 1',
        [$slug]
    );
}

/** How many of each star rating a product has, for the review histogram. */
function rating_breakdown(int $productId): array
{
    $rows = qa('SELECT rating, COUNT(*) AS n FROM reviews WHERE product_id = ? GROUP BY rating', [$productId]);
    $out  = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    foreach ($rows as $row) {
        $out[(int) $row['rating']] = (int) $row['n'];
    }
    return $out;
}

function product_reviews(int $productId, int $limit = 20): array
{
    return qa(
        'SELECT r.*, u.name AS user_name, u.avatar_color
           FROM reviews r JOIN users u ON u.id = r.user_id
          WHERE r.product_id = ?
       ORDER BY r.created_at DESC
          LIMIT ' . (int) $limit,
        [$productId]
    );
}

/** Recompute the cached rating totals on a product after a review changes. */
function refresh_product_rating(int $productId): void
{
    q('UPDATE products p
          SET rating_sum   = (SELECT COALESCE(SUM(rating), 0) FROM reviews WHERE product_id = p.id),
              rating_count = (SELECT COUNT(*)                 FROM reviews WHERE product_id = p.id)
        WHERE p.id = ?', [$productId]);
}

/** Typeahead rows for the header search box. */
function search_suggestions(string $term, int $limit = 8): array
{
    $like = '%' . trim($term) . '%';
    $products = qa(
        'SELECT p.name, p.slug, p.emoji, p.tint, p.price, p.unit, c.name AS category_name
           FROM products p LEFT JOIN categories c ON c.id = p.category_id
          WHERE p.is_active = 1 AND p.name LIKE ?
       ORDER BY p.sold_count DESC LIMIT ' . (int) $limit,
        [$like]
    );
    $categories = qa(
        'SELECT name, slug, emoji FROM categories WHERE is_active = 1 AND name LIKE ? LIMIT 4',
        [$like]
    );
    return ['products' => $products, 'categories' => $categories];
}
