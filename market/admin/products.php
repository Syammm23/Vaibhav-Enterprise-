<?php
/** Admin: product list, create, edit, stock and visibility. */

$pageTitle = 'Products';
$adminPage = 'products';
require __DIR__ . '/header.php';

$action = $_GET['action'] ?? 'list';
$editId = (int) ($_GET['id'] ?? 0);
$errors = [];

// ---------------------------------------------------------------- writes
if (is_post()) {
    require_csrf();
    $form = $_POST['form'] ?? '';

    if ($form === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $uploadError = null;
        $data = [
            'name'        => trim((string) ($_POST['name'] ?? '')),
            'category_id' => (int) ($_POST['category_id'] ?? 0),
            'brand_id'    => ((int) ($_POST['brand_id'] ?? 0)) ?: null,
            'unit'        => trim((string) ($_POST['unit'] ?? '1 pc')),
            'price'       => (float) ($_POST['price'] ?? 0),
            'mrp'         => (float) ($_POST['mrp'] ?? 0),
            'stock'       => max(0, (int) ($_POST['stock'] ?? 0)),
            'emoji'       => trim((string) ($_POST['emoji'] ?? '🛒')),
            'tint'        => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($_POST['tint'] ?? '')) ? $_POST['tint'] : '#e8f5ec',
            'pack_type'   => in_array($_POST['pack_type'] ?? '', PACK_TYPES, true) ? $_POST['pack_type'] : 'pouch',
            'short_desc'  => trim((string) ($_POST['short_desc'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'highlights'  => trim((string) ($_POST['highlights'] ?? '')),
            'is_veg'      => !empty($_POST['is_veg']) ? 1 : 0,
            'is_organic'  => !empty($_POST['is_organic']) ? 1 : 0,
            'is_featured' => !empty($_POST['is_featured']) ? 1 : 0,
            'is_active'   => !empty($_POST['is_active']) ? 1 : 0,
        ];

        // An uploaded photograph replaces whatever artwork the product had.
        $upload = store_product_image($_FILES['photo'] ?? null, slugify($data['name']), $uploadError);
        if ($uploadError !== null) {
            $errors['photo'] = $uploadError;
        }

        if (mb_strlen($data['name']) < 3)                    $errors['name']  = 'Give the product a name.';
        if ($data['price'] <= 0)                             $errors['price'] = 'Price must be greater than zero.';
        if ($data['mrp'] < $data['price'])                   $errors['mrp']   = 'MRP cannot be lower than the selling price.';
        if (!qv('SELECT id FROM categories WHERE id = ?', [$data['category_id']])) {
            $errors['category_id'] = 'Pick a category.';
        }

        if ($upload !== null) {
            $data['image'] = $upload;
        } elseif (!empty($_POST['remove_photo'])) {
            $data['image'] = null;
        }

        if (!$errors) {
            if ($id > 0) {
                $columns = implode(', ', array_map(static fn($col) => "$col = ?", array_keys($data)));
                q("UPDATE products SET $columns WHERE id = ?", [...array_values($data), $id]);
                flash('Product updated.');
            } else {
                // Keep the slug and SKU unique even if two products share a name.
                $slug = slugify($data['name']);
                if (qv('SELECT id FROM products WHERE slug = ?', [$slug])) {
                    $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
                }
                $sku = 'MKT-' . strtoupper(bin2hex(random_bytes(3)));
                q('INSERT INTO products (name, slug, sku, category_id, brand_id, unit, price, mrp, stock, emoji, tint,
                                         image, pack_type, short_desc, description, highlights,
                                         is_veg, is_organic, is_featured, is_active)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                  [$data['name'], $slug, $sku, $data['category_id'], $data['brand_id'], $data['unit'],
                   $data['price'], $data['mrp'], $data['stock'], $data['emoji'], $data['tint'],
                   $data['image'] ?? null, $data['pack_type'],
                   $data['short_desc'], $data['description'], $data['highlights'],
                   $data['is_veg'], $data['is_organic'], $data['is_featured'], $data['is_active']]);
                flash('Product created.');
            }
            redirect('products.php');
        }
        $action = 'edit';
        $editId = $id;
    }

    if ($form === 'toggle') {
        q('UPDATE products SET is_active = 1 - is_active WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
        flash('Visibility updated.');
        redirect('products.php?' . http_build_query(['q' => $_POST['back_q'] ?? '', 'page' => $_POST['back_page'] ?? 1]));
    }

    if ($form === 'restock') {
        q('UPDATE products SET stock = ? WHERE id = ?', [max(0, (int) ($_POST['stock'] ?? 0)), (int) ($_POST['id'] ?? 0)]);
        flash('Stock updated.');
        redirect('products.php?' . http_build_query(['q' => $_POST['back_q'] ?? '', 'page' => $_POST['back_page'] ?? 1]));
    }
}

$categories = qa('SELECT c.id, c.name, p.name AS parent
                    FROM categories c LEFT JOIN categories p ON p.id = c.parent_id
                ORDER BY COALESCE(p.sort_order, c.sort_order), c.sort_order');
$brands = qa('SELECT id, name FROM brands ORDER BY name');

// ------------------------------------------------------------ edit form
if ($action === 'new' || $action === 'edit') {
    $product = $editId > 0 ? q1('SELECT * FROM products WHERE id = ?', [$editId]) : null;
    $v = static fn(string $key, $fallback = '') => e((string) ($_POST[$key] ?? $product[$key] ?? $fallback));
    $checked = static fn(string $key, int $fallback = 0) => (is_post() ? !empty($_POST[$key]) : (int) ($product[$key] ?? $fallback) === 1) ? 'checked' : '';
    ?>
    <div class="admin-head">
      <h1><?= $product ? 'Edit product' : 'New product' ?></h1>
      <a class="btn btn-ghost btn-sm" href="products.php">← Back to list</a>
    </div>

    <form method="post" class="panel panel-pad" style="max-width:820px" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="save">
      <input type="hidden" name="id" value="<?= (int) ($product['id'] ?? 0) ?>">

      <div class="field">
        <label for="name">Product name</label>
        <input class="input <?= isset($errors['name']) ? 'is-error' : '' ?>" id="name" name="name" value="<?= $v('name') ?>" required>
        <?php if (isset($errors['name'])): ?><p class="error-text"><?= e($errors['name']) ?></p><?php endif; ?>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="category_id">Category</label>
          <select class="select <?= isset($errors['category_id']) ? 'is-error' : '' ?>" id="category_id" name="category_id" required>
            <option value="">Choose…</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= (int) ($_POST['category_id'] ?? $product['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                <?= e($c['parent'] ? $c['parent'] . ' › ' . $c['name'] : $c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['category_id'])): ?><p class="error-text"><?= e($errors['category_id']) ?></p><?php endif; ?>
        </div>
        <div class="field">
          <label for="brand_id">Brand</label>
          <select class="select" id="brand_id" name="brand_id">
            <option value="">No brand</option>
            <?php foreach ($brands as $b): ?>
              <option value="<?= (int) $b['id'] ?>" <?= (int) ($_POST['brand_id'] ?? $product['brand_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>>
                <?= e($b['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="price">Selling price (<?= CURRENCY ?>)</label>
          <input class="input <?= isset($errors['price']) ? 'is-error' : '' ?>" id="price" name="price" type="number" step="0.01" min="0" value="<?= $v('price') ?>" required>
          <?php if (isset($errors['price'])): ?><p class="error-text"><?= e($errors['price']) ?></p><?php endif; ?>
        </div>
        <div class="field">
          <label for="mrp">MRP (<?= CURRENCY ?>)</label>
          <input class="input <?= isset($errors['mrp']) ? 'is-error' : '' ?>" id="mrp" name="mrp" type="number" step="0.01" min="0" value="<?= $v('mrp') ?>" required>
          <?php if (isset($errors['mrp'])): ?><p class="error-text"><?= e($errors['mrp']) ?></p><?php endif; ?>
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="unit">Pack size</label>
          <input class="input" id="unit" name="unit" value="<?= $v('unit', '1 pc') ?>" placeholder="500 g, 1 L, 6 pcs…">
        </div>
        <div class="field">
          <label for="stock">Stock on hand</label>
          <input class="input" id="stock" name="stock" type="number" min="0" value="<?= $v('stock', '0') ?>">
        </div>
      </div>

      <!-- ------------------------------------------------------- artwork -->
      <fieldset style="border:1px solid var(--line);border-radius:var(--r);padding:16px;margin:0 0 16px">
        <legend style="font-size:12px;font-weight:800;color:var(--ink-3);padding:0 6px">PRODUCT PHOTO</legend>

        <div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap">
          <span class="mini-thumb" style="--tile:<?= $v('tint', '#e8f5ec') ?>;width:110px;height:110px;border-radius:var(--r)">
            <?php if ($product): ?>
              <?= product_img($product, 110) ?>
            <?php else: ?>
              <span class="muted tiny" style="padding:8px;text-align:center">No image yet</span>
            <?php endif; ?>
          </span>

          <div style="flex:1;min-width:260px">
            <div class="field">
              <label for="photo">Upload a photograph</label>
              <input class="input <?= isset($errors['photo']) ? 'is-error' : '' ?>" type="file" id="photo" name="photo"
                     accept="image/jpeg,image/png,image/webp">
              <p class="hint">JPEG, PNG or WebP up to <?= (int) (MAX_UPLOAD_BYTES / 1048576) ?> MB.
                 It is squared, resized to 500&times;500 and converted to WebP automatically.</p>
              <?php if (isset($errors['photo'])): ?><p class="error-text"><?= e($errors['photo']) ?></p><?php endif; ?>
            </div>
            <?php if ($product && !empty($product['image'])): ?>
              <label class="check"><input type="checkbox" name="remove_photo" value="1">
                <span>Remove the photo and go back to generated artwork</span></label>
            <?php endif; ?>
          </div>
        </div>

        <p class="hint" style="margin:14px 0 10px">
          Without a photo the store draws a packshot from the settings below.
          <code>php tools/import-images.php</code> can fetch real photos from Open Food Facts.
        </p>

        <div class="field-row">
          <div class="field">
            <label for="pack_type">Package shape</label>
            <select class="select" id="pack_type" name="pack_type">
              <?php foreach (PACK_TYPES as $type): ?>
                <option value="<?= e($type) ?>" <?= ($_POST['pack_type'] ?? $product['pack_type'] ?? 'pouch') === $type ? 'selected' : '' ?>>
                  <?= e(ucfirst($type)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="emoji">Tile emoji</label>
            <input class="input" id="emoji" name="emoji" maxlength="8" value="<?= $v('emoji', '🛒') ?>">
          </div>
          <div class="field">
            <label for="tint">Backdrop colour</label>
            <input class="input" id="tint" name="tint" type="color" value="<?= $v('tint', '#e8f5ec') ?>" style="height:44px;padding:5px">
          </div>
        </div>
      </fieldset>

      <div class="field">
        <label for="short_desc">Short description</label>
        <input class="input" id="short_desc" name="short_desc" maxlength="255" value="<?= $v('short_desc') ?>">
      </div>

      <div class="field">
        <label for="description">Full description</label>
        <textarea class="input" id="description" name="description" rows="5"><?= $v('description') ?></textarea>
      </div>

      <div class="field">
        <label for="highlights">Highlights <span class="muted">(one per line)</span></label>
        <textarea class="input" id="highlights" name="highlights" rows="4"><?= $v('highlights') ?></textarea>
      </div>

      <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:16px">
        <label class="check"><input type="checkbox" name="is_active" value="1" <?= $checked('is_active', 1) ?>><span>Visible in store</span></label>
        <label class="check"><input type="checkbox" name="is_veg" value="1" <?= $checked('is_veg', 1) ?>><span>Vegetarian</span></label>
        <label class="check"><input type="checkbox" name="is_organic" value="1" <?= $checked('is_organic') ?>><span>Organic</span></label>
        <label class="check"><input type="checkbox" name="is_featured" value="1" <?= $checked('is_featured') ?>><span>Featured</span></label>
      </div>

      <button class="btn" type="submit"><?= $product ? 'Save changes' : 'Create product' ?></button>
      <a class="btn btn-ghost" href="products.php">Cancel</a>
    </form>

    <?php require __DIR__ . '/footer.php'; exit;
}

// ---------------------------------------------------------------- list
$term    = trim((string) ($_GET['q'] ?? ''));
$sort    = $_GET['sort'] ?? 'newest';
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$where  = ['1 = 1'];
$params = [];
if ($term !== '') {
    $where[]  = '(p.name LIKE ? OR p.sku LIKE ?)';
    $params[] = '%' . $term . '%';
    $params[] = '%' . $term . '%';
}

$orderBy = match ($sort) {
    'stock'    => 'p.stock ASC',
    'price'    => 'p.price DESC',
    'name'     => 'p.name ASC',
    'sold'     => 'p.sold_count DESC',
    default    => 'p.id DESC',
};

$sql   = 'FROM products p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN brands b ON b.id = p.brand_id
          WHERE ' . implode(' AND ', $where);
$total = (int) qv('SELECT COUNT(*) ' . $sql, $params);
$pages = max(1, (int) ceil($total / $perPage));
$page  = min($page, $pages);

$rows = qa("SELECT p.*, c.name AS category_name, b.name AS brand_name $sql ORDER BY $orderBy LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);
?>

<div class="admin-head">
  <div>
    <h1>Products</h1>
    <p class="muted tiny"><?= number_format($total) ?> product<?= $total === 1 ? '' : 's' ?></p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <form method="get" style="display:flex;gap:8px">
      <input class="input" type="search" name="q" value="<?= e($term) ?>" placeholder="Search name or SKU">
      <select class="select" name="sort" onchange="this.form.submit()" style="width:auto">
        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
        <option value="stock"  <?= $sort === 'stock' ? 'selected' : '' ?>>Lowest stock</option>
        <option value="price"  <?= $sort === 'price' ? 'selected' : '' ?>>Highest price</option>
        <option value="sold"   <?= $sort === 'sold' ? 'selected' : '' ?>>Best selling</option>
        <option value="name"   <?= $sort === 'name' ? 'selected' : '' ?>>Name A–Z</option>
      </select>
      <button class="btn btn-ghost" type="submit">Filter</button>
    </form>
    <a class="btn" href="products.php?action=new">+ Add product</a>
  </div>
</div>

<div class="table-wrap">
  <table class="data">
    <thead>
      <tr><th></th><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Sold</th><th>Rating</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="9" class="muted">No products match that search.</td></tr><?php endif; ?>
      <?php foreach ($rows as $p): ?>
        <tr>
          <td><span class="mini-thumb" style="--tile:<?= e($p['tint']) ?>"><?= product_img($p, 34) ?></span></td>
          <td class="wrap-cell">
            <strong><?= e($p['name']) ?></strong><br>
            <small class="muted"><?= e($p['sku']) ?> · <?= e($p['unit']) ?><?= $p['brand_name'] ? ' · ' . e($p['brand_name']) : '' ?></small>
          </td>
          <td><?= e($p['category_name']) ?></td>
          <td>
            <?= money($p['price']) ?>
            <?php if (discount_pct($p['price'], $p['mrp']) > 0): ?>
              <br><small class="muted"><s><?= money($p['mrp']) ?></s> <?= discount_pct($p['price'], $p['mrp']) ?>% off</small>
            <?php endif; ?>
          </td>
          <td>
            <form method="post" style="display:flex;gap:5px;align-items:center">
              <?= csrf_field() ?>
              <input type="hidden" name="form" value="restock">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="back_q" value="<?= e($term) ?>">
              <input type="hidden" name="back_page" value="<?= $page ?>">
              <input class="input" type="number" name="stock" min="0" value="<?= (int) $p['stock'] ?>" style="width:76px;padding:6px 8px">
              <button class="link-btn save" type="submit">Set</button>
            </form>
          </td>
          <td><?= (int) $p['sold_count'] ?></td>
          <td><?= (int) $p['rating_count'] > 0 ? number_format(rating_of($p), 1) . ' ★' : '—' ?></td>
          <td>
            <span class="chip <?= (int) $p['is_active'] === 1 ? 'tag-green' : 'tag-red' ?>">
              <?= (int) $p['is_active'] === 1 ? 'Live' : 'Hidden' ?>
            </span>
          </td>
          <td>
            <div style="display:flex;gap:6px">
              <a class="btn btn-ghost btn-sm" href="products.php?action=edit&amp;id=<?= (int) $p['id'] ?>">Edit</a>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="toggle">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="back_q" value="<?= e($term) ?>">
                <input type="hidden" name="back_page" value="<?= $page ?>">
                <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $p['is_active'] === 1 ? 'Hide' : 'Show' ?></button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
  <nav class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <?php if ($i === $page): ?>
        <span class="is-on"><?= $i ?></span>
      <?php else: ?>
        <a href="products.php?<?= e(http_build_query(['q' => $term, 'sort' => $sort, 'page' => $i])) ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </nav>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
