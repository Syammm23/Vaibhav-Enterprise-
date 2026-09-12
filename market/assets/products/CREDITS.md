# Product photography credits

## Fresh produce (36 images)

The fruit and vegetable photographs shipped in this folder come from the
**Grocery Store Dataset** by Marcus Klasson, released under the **MIT License**:

- Repository: https://github.com/marcusklasson/GroceryStoreDataset
- Paper: Klasson, Zhang & Kjellström, *A Hierarchical Grocery Store Image Dataset
  with Visual and Semantic Labels*, WACV 2019 — https://arxiv.org/abs/1901.00711

They are the dataset's "iconic" product shots. Each one was cropped to its
subject, centred on a square white canvas, resized to 400×400 and saved as WebP.
The MIT licence permits this use, including commercially; keep this file with the
images if you redistribute them.

## Everything else

Products without a photograph are drawn at request time by `assets/image.php`,
which composes a packshot from the product's package type, brand colour, name and
net weight. Nothing is downloaded and no third-party artwork is involved.

## Adding real photographs for the packaged goods

`tools/import-images.php` fetches photographs from **Open Food Facts**, a free and
open database of packaged groceries that needs no API key:

```bash
php tools/import-images.php            # fill in every product that has no image
php tools/import-images.php --dry-run  # show what it would download
```

Open Food Facts photographs are contributed by the public and licensed
**CC-BY-SA 3.0**. If you publish a store using them, credit
"Open Food Facts contributors" and keep the same licence on the images.

Anything you upload through the admin product form is yours; only you know its
licence, so keep a note of it.
