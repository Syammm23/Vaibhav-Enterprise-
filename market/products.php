<?php
/** Catalogue listing: search results, category browsing, filters, sorting. */

require_once __DIR__ . '/includes/bootstrap.php';

$term        = trim((string) ($_GET['q'] ?? ''));
$categorySlug = trim((string) ($_GET['category'] ?? ''));
$category    = $categorySlug !== '' ? category_by_slug($categorySlug) : null;

$result = find_products([
    'q'         => $term,
    'category'  => $categorySlug ?: null,
    'brand'     => (array) ($_GET['brand'] ?? []),
    'min_price' => $_GET['min_price'] ?? null,
    'max_price' => $_GET['max_price'] ?? null,
    'rating'    => $_GET['rating'] ?? null,
    'discount'  => $_GET['discount'] ?? null,
    'veg'       => !empty($_GET['veg']),
    'organic'   => !empty($_GET['organic']),
    'in_stock'  => !empty($_GET['in_stock']),
    'sort'      => $_GET['sort'] ?? 'popularity',
    'page'      => (int) ($_GET['page'] ?? 1),
]);

$items = $result['items'];
$tree  = category_tree();
$brands = all_brands();

// The heading changes with what the shopper actually asked for.
if ($term !== '') {
    $heading = 'Results for “' . $term . '”';
} elseif ($category) {
    $heading = $category['name'];
} else {
    $heading = 'All products';
}

$sorts = [
    'popularity' => 'Popularity',
    'price_asc'  => 'Price: low to high',
    'price_desc' => 'Price: high to low',
    'discount'   => 'Discount',
    'rating'     => 'Customer rating',
    'newest'     => 'Newest first',
];
$activeSort = array_key_exists($_GET['sort'] ?? '', $sorts) ? $_GET['sort'] : 'popularity';

// Chips for every filter currently in effect.
$applied = [];
foreach ((array) ($_GET['brand'] ?? []) as $slug) {
    foreach ($brands as $b) {
        if ($b['slug'] === $slug) { $applied[] = ['label' => $b['name'], 'url' => url_toggle('brand', $slug)]; }
    }
}
if (!empty($_GET['rating']))    $applied[] = ['label' => $_GET['rating'] . '★ & above', 'url' => url_with(['rating' => null])];
if (!empty($_GET['discount']))  $applied[] = ['label' => (int) $_GET['discount'] . '% off or more', 'url' => url_with(['discount' => null])];
if (!empty($_GET['organic']))   $applied[] = ['label' => 'Organic only', 'url' => url_with(['organic' => null])];
if (!empty($_GET['veg']))       $applied[] = ['label' => 'Vegetarian only', 'url' => url_with(['veg' => null])];
if (!empty($_GET['in_stock']))  $applied[] = ['label' => 'In stock only', 'url' => url_with(['in_stock' => null])];
if (($_GET['min_price'] ?? '') !== '' || ($_GET['max_price'] ?? '') !== '') {
    $applied[] = [
        'label' => money((float) ($_GET['min_price'] ?: 0)) . ' – ' . (($_GET['max_price'] ?? '') !== '' ? money((float) $_GET['max_price']) : 'any'),
        'url'   => url_with(['min_price' => null, 'max_price' => null]),
    ];
}

$pageTitle = $heading . ' | ' . STORE_NAME;
$activeNav = $category ? ($category['parent_id'] ? '' : $category['slug']) : '';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap page">

  <nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="index.php">Home</a> <span class="sep">›</span>
    <a href="products.php">Products</a>
    <?php if ($category): ?>
      <span class="sep">›</span> <span><?= e($category['name']) ?></span>
    <?php elseif ($term !== ''): ?>
      <span class="sep">›</span> <span>Search</span>
    <?php endif; ?>
  </nav>

  <div class="listing">

    <!-- ------------------------------------------------------ filters -->
    <form class="filters" method="get" action="products.php" id="filter-form">
      <?php if ($term !== ''): ?><input type="hidden" name="q" value="<?= e($term) ?>"><?php endif; ?>
      <?php if ($activeSort !== 'popularity'): ?><input type="hidden" name="sort" value="<?= e($activeSort) ?>"><?php endif; ?>

      <div class="filters-head">
        <strong>Filters</strong>
        <a href="products.php<?= $term !== '' ? '?q=' . urlencode($term) : ($categorySlug ? '?category=' . urlencode($categorySlug) : '') ?>">Clear all</a>
      </div>

      <div class="fgroup">
        <h4>Category</h4>
        <div class="fscroll cat-list">
          <a class="<?= $categorySlug === '' ? 'is-on' : '' ?>" href="<?= e(url_with(['category' => null])) ?>">All categories</a>
          <?php foreach ($tree as $cat): ?>
            <a class="<?= $categorySlug === $cat['slug'] ? 'is-on' : '' ?>" href="<?= e(url_with(['category' => $cat['slug']])) ?>">
              <?= e($cat['emoji']) ?> <?= e($cat['name']) ?>
            </a>
            <?php if ($category && ($category['id'] == $cat['id'] || $category['parent_id'] == $cat['id'])): ?>
              <?php foreach ($cat['children'] as $child): ?>
                <a class="sub <?= $categorySlug === $child['slug'] ? 'is-on' : '' ?>" href="<?= e(url_with(['category' => $child['slug']])) ?>">
                  <?= e($child['name']) ?>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>

      <details class="fgroup" open>
        <summary>Price</summary>
        <div class="price-inputs">
          <input class="input" type="number" name="min_price" min="0" placeholder="Min" value="<?= e($_GET['min_price'] ?? '') ?>">
          <span class="muted">to</span>
          <input class="input" type="number" name="max_price" min="0" placeholder="Max" value="<?= e($_GET['max_price'] ?? '') ?>">
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:9px">
          <?php foreach ([[0, 100], [100, 300], [300, 600], [600, 99999]] as [$lo, $hi]): ?>
            <a class="chip" href="<?= e(url_with(['min_price' => $lo ?: null, 'max_price' => $hi >= 99999 ? null : $hi])) ?>">
              <?= $hi >= 99999 ? money($lo) . '+' : money($lo) . '–' . money($hi) ?>
            </a>
          <?php endforeach; ?>
        </div>
        <button class="btn btn-sm btn-ghost btn-block" type="submit" style="margin-top:10px">Apply price</button>
      </details>

      <details class="fgroup" open>
        <summary>Brand</summary>
        <div class="fscroll">
          <?php foreach ($brands as $b): ?>
            <label class="check">
              <input type="checkbox" name="brand[]" value="<?= e($b['slug']) ?>"
                     <?= filter_active('brand', $b['slug']) ? 'checked' : '' ?> data-autosubmit>
              <span><?= e($b['name']) ?> <span class="count">(<?= (int) $b['product_count'] ?>)</span></span>
            </label>
          <?php endforeach; ?>
        </div>
      </details>

      <details class="fgroup" open>
        <summary>Customer rating</summary>
        <?php foreach ([4.5, 4.0, 3.5, 3.0] as $r): ?>
          <label class="check">
            <input type="radio" name="rating" value="<?= $r ?>" <?= (string) ($_GET['rating'] ?? '') === (string) $r ? 'checked' : '' ?> data-autosubmit>
            <span><?= $r ?>★ &amp; above</span>
          </label>
        <?php endforeach; ?>
      </details>

      <details class="fgroup" open>
        <summary>Discount</summary>
        <?php foreach ([10, 20, 30, 40] as $d): ?>
          <label class="check">
            <input type="radio" name="discount" value="<?= $d ?>" <?= (string) ($_GET['discount'] ?? '') === (string) $d ? 'checked' : '' ?> data-autosubmit>
            <span><?= $d ?>% off or more</span>
          </label>
        <?php endforeach; ?>
      </details>

      <details class="fgroup" open>
        <summary>Other</summary>
        <label class="check">
          <input type="checkbox" name="in_stock" value="1" <?= !empty($_GET['in_stock']) ? 'checked' : '' ?> data-autosubmit>
          <span>In stock only</span>
        </label>
        <label class="check">
          <input type="checkbox" name="organic" value="1" <?= !empty($_GET['organic']) ? 'checked' : '' ?> data-autosubmit>
          <span>🌱 Organic</span>
        </label>
        <label class="check">
          <input type="checkbox" name="veg" value="1" <?= !empty($_GET['veg']) ? 'checked' : '' ?> data-autosubmit>
          <span>🟢 Vegetarian</span>
        </label>
      </details>
    </form>

    <!-- ------------------------------------------------------ results -->
    <div>
      <div class="toolbar">
        <div>
          <h1><?= e($heading) ?></h1>
          <span class="count">
            <?= number_format($result['total']) ?> product<?= $result['total'] === 1 ? '' : 's' ?>
            <?php if ($result['pages'] > 1): ?> · page <?= $result['page'] ?> of <?= $result['pages'] ?><?php endif; ?>
          </span>
        </div>
        <div class="sorts">
          <button class="btn btn-sm btn-ghost filter-toggle" type="button" data-filter-toggle>⚙ Filters</button>
          <span class="hide-sm">Sort by</span>
          <?php foreach ($sorts as $key => $label): ?>
            <a class="<?= $activeSort === $key ? 'is-on' : '' ?>" href="<?= e(url_with(['sort' => $key === 'popularity' ? null : $key])) ?>"><?= e($label) ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ($applied): ?>
        <div class="applied">
          <?php foreach ($applied as $chip): ?>
            <span class="chip"><?= e($chip['label']) ?> <a href="<?= e($chip['url']) ?>" aria-label="Remove filter">✕</a></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!$items): ?>
        <div class="panel empty">
          <span class="empty-emoji">🧺</span>
          <h2>Nothing matched those filters</h2>
          <p>Try widening the price range, clearing a brand, or searching for something else.</p>
          <p style="margin-top:16px"><a class="btn" href="products.php">Browse all products</a></p>
        </div>
      <?php else: ?>
        <div class="grid">
          <?php foreach ($items as $p) { include __DIR__ . '/includes/product-card.php'; } ?>
        </div>

        <?php if ($result['pages'] > 1): ?>
          <nav class="pagination" aria-label="Pagination">
            <?php if ($result['page'] > 1): ?>
              <a href="<?= e(url_with(['page' => $result['page'] - 1])) ?>">‹ Prev</a>
            <?php endif; ?>

            <?php
            $from = max(1, $result['page'] - 2);
            $to   = min($result['pages'], $result['page'] + 2);
            if ($from > 1): ?>
              <a href="<?= e(url_with(['page' => 1])) ?>">1</a>
              <?php if ($from > 2): ?><span class="gap">…</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $from; $i <= $to; $i++): ?>
              <?php if ($i === $result['page']): ?>
                <span class="is-on"><?= $i ?></span>
              <?php else: ?>
                <a href="<?= e(url_with(['page' => $i])) ?>"><?= $i ?></a>
              <?php endif; ?>
            <?php endfor; ?>

            <?php if ($to < $result['pages']): ?>
              <?php if ($to < $result['pages'] - 1): ?><span class="gap">…</span><?php endif; ?>
              <a href="<?= e(url_with(['page' => $result['pages']])) ?>"><?= $result['pages'] ?></a>
            <?php endif; ?>

            <?php if ($result['page'] < $result['pages']): ?>
              <a href="<?= e(url_with(['page' => $result['page'] + 1])) ?>">Next ›</a>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
