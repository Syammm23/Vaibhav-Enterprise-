-- ============================================================================
--  MARKET — Online Grocery Store
--  Database schema for MySQL 5.7+ / MariaDB 10.3+
--
--  Import:  mysql -u root -p < sql/schema.sql
--  or open phpMyAdmin -> Import -> choose this file.
-- ============================================================================

DROP DATABASE IF EXISTS market_db;
CREATE DATABASE market_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE market_db;

-- Emoji and ₹ are 4-byte UTF-8; without this the import mangles them.
SET NAMES utf8mb4;

-- ---------------------------------------------------------------- users ----
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120)  NOT NULL,
  email         VARCHAR(190)  NOT NULL UNIQUE,
  phone         VARCHAR(20)   DEFAULT NULL,
  password_hash VARCHAR(255)  NOT NULL,
  role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  avatar_color  VARCHAR(9)    NOT NULL DEFAULT '#0f8a3c',
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  last_login_at DATETIME      DEFAULT NULL,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------ addresses ----
CREATE TABLE addresses (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  label       ENUM('Home','Work','Other') NOT NULL DEFAULT 'Home',
  full_name   VARCHAR(120) NOT NULL,
  phone       VARCHAR(20)  NOT NULL,
  line1       VARCHAR(190) NOT NULL,
  line2       VARCHAR(190) DEFAULT NULL,
  landmark    VARCHAR(190) DEFAULT NULL,
  city        VARCHAR(90)  NOT NULL,
  state       VARCHAR(90)  NOT NULL,
  pincode     VARCHAR(10)  NOT NULL,
  is_default  TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_addr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_addr_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------- categories ----
CREATE TABLE categories (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id  INT UNSIGNED DEFAULT NULL,
  name       VARCHAR(120) NOT NULL,
  slug       VARCHAR(140) NOT NULL UNIQUE,
  emoji      VARCHAR(16)  NOT NULL DEFAULT '',
  tint       VARCHAR(9)   NOT NULL DEFAULT '#e8f5ec',
  sort_order INT          NOT NULL DEFAULT 0,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE,
  INDEX idx_cat_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------- brands ----
CREATE TABLE brands (
  id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------- products ----
CREATE TABLE products (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id   INT UNSIGNED NOT NULL,
  brand_id      INT UNSIGNED DEFAULT NULL,
  name          VARCHAR(190) NOT NULL,
  slug          VARCHAR(210) NOT NULL UNIQUE,
  sku           VARCHAR(40)  NOT NULL UNIQUE,
  unit          VARCHAR(40)  NOT NULL DEFAULT '1 pc',
  short_desc    VARCHAR(255) NOT NULL DEFAULT '',
  description   TEXT,
  highlights    TEXT COMMENT 'one bullet per line',
  price         DECIMAL(10,2) NOT NULL,
  mrp           DECIMAL(10,2) NOT NULL,
  stock         INT NOT NULL DEFAULT 0,
  emoji         VARCHAR(16)  NOT NULL DEFAULT '',
  tint          VARCHAR(9)   NOT NULL DEFAULT '#e8f5ec',
  image         VARCHAR(190) DEFAULT NULL COMMENT 'file in assets/products/; NULL falls back to generated artwork',
  pack_type     VARCHAR(16)  NOT NULL DEFAULT 'pouch' COMMENT 'silhouette the fallback artwork draws',
  is_veg        TINYINT(1)   NOT NULL DEFAULT 1,
  is_organic    TINYINT(1)   NOT NULL DEFAULT 0,
  is_featured   TINYINT(1)   NOT NULL DEFAULT 0,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  rating_sum    INT UNSIGNED NOT NULL DEFAULT 0,
  rating_count  INT UNSIGNED NOT NULL DEFAULT 0,
  sold_count    INT UNSIGNED NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prod_cat   FOREIGN KEY (category_id) REFERENCES categories(id),
  CONSTRAINT fk_prod_brand FOREIGN KEY (brand_id)    REFERENCES brands(id) ON DELETE SET NULL,
  INDEX idx_prod_cat (category_id),
  INDEX idx_prod_brand (brand_id),
  INDEX idx_prod_price (price),
  FULLTEXT KEY ft_prod (name, short_desc, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------- carts -----
CREATE TABLE cart_items (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED DEFAULT NULL,
  guest_key  VARCHAR(64)  DEFAULT NULL COMMENT 'session id for logged-out shoppers',
  product_id INT UNSIGNED NOT NULL,
  qty        INT UNSIGNED NOT NULL DEFAULT 1,
  added_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cart_user FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_cart_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  UNIQUE KEY uq_cart_user  (user_id, product_id),
  UNIQUE KEY uq_cart_guest (guest_key, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------ wishlist -----
CREATE TABLE wishlist_items (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  added_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wish_user FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_wish_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  UNIQUE KEY uq_wish (user_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------- coupons -----
CREATE TABLE coupons (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code          VARCHAR(40) NOT NULL UNIQUE,
  description   VARCHAR(190) NOT NULL DEFAULT '',
  type          ENUM('percent','flat') NOT NULL DEFAULT 'percent',
  value         DECIMAL(10,2) NOT NULL,
  min_order     DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_discount  DECIMAL(10,2) DEFAULT NULL,
  usage_limit   INT UNSIGNED DEFAULT NULL,
  used_count    INT UNSIGNED NOT NULL DEFAULT 0,
  expires_at    DATE DEFAULT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------- orders -----
CREATE TABLE orders (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number   VARCHAR(24) NOT NULL UNIQUE,
  user_id        INT UNSIGNED NOT NULL,
  -- address snapshot, so a later edit of the address book never rewrites history
  ship_name      VARCHAR(120) NOT NULL,
  ship_phone     VARCHAR(20)  NOT NULL,
  ship_line1     VARCHAR(190) NOT NULL,
  ship_line2     VARCHAR(190) DEFAULT NULL,
  ship_city      VARCHAR(90)  NOT NULL,
  ship_state     VARCHAR(90)  NOT NULL,
  ship_pincode   VARCHAR(10)  NOT NULL,
  slot           VARCHAR(60)  NOT NULL DEFAULT 'Standard delivery',
  subtotal       DECIMAL(10,2) NOT NULL,
  discount       DECIMAL(10,2) NOT NULL DEFAULT 0,
  coupon_code    VARCHAR(40)  DEFAULT NULL,
  delivery_fee   DECIMAL(10,2) NOT NULL DEFAULT 0,
  handling_fee   DECIMAL(10,2) NOT NULL DEFAULT 0,
  total          DECIMAL(10,2) NOT NULL,
  payment_method ENUM('card','upi','netbanking','wallet','cod') NOT NULL DEFAULT 'cod',
  payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  status         ENUM('placed','packed','shipped','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'placed',
  placed_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_order_user (user_id),
  INDEX idx_order_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id     INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED DEFAULT NULL,
  name         VARCHAR(190) NOT NULL,
  unit         VARCHAR(40)  NOT NULL DEFAULT '',
  emoji        VARCHAR(16)  NOT NULL DEFAULT '',
  tint         VARCHAR(9)   NOT NULL DEFAULT '#e8f5ec',
  image        VARCHAR(190) DEFAULT NULL COMMENT 'artwork as it was when the order was placed',
  price        DECIMAL(10,2) NOT NULL,
  mrp          DECIMAL(10,2) NOT NULL,
  qty          INT UNSIGNED NOT NULL,
  line_total   DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id)   REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_prod  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  INDEX idx_oi_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------ payments -----
CREATE TABLE payments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED NOT NULL,
  gateway     VARCHAR(40) NOT NULL DEFAULT 'MarketPay (demo)',
  txn_id      VARCHAR(48) NOT NULL UNIQUE,
  method      VARCHAR(20) NOT NULL,
  detail      VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'masked card / vpa / bank — never raw data',
  amount      DECIMAL(10,2) NOT NULL,
  status      ENUM('created','success','failed') NOT NULL DEFAULT 'created',
  message     VARCHAR(190) NOT NULL DEFAULT '',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pay_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX idx_pay_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------- reviews -----
CREATE TABLE reviews (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  rating     TINYINT UNSIGNED NOT NULL,
  title      VARCHAR(140) NOT NULL DEFAULT '',
  body       TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rev_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_user FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  UNIQUE KEY uq_review (product_id, user_id),
  INDEX idx_rev_prod (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------- banners -----
CREATE TABLE banners (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(140) NOT NULL,
  subtitle   VARCHAR(190) NOT NULL DEFAULT '',
  cta_text   VARCHAR(60)  NOT NULL DEFAULT 'Shop now',
  cta_link   VARCHAR(190) NOT NULL DEFAULT 'products.php',
  emoji         VARCHAR(16)  NOT NULL DEFAULT '',
  category_slug VARCHAR(140) DEFAULT NULL COMMENT 'aisle the hero collage pulls from',
  bg_from    VARCHAR(9)   NOT NULL DEFAULT '#0f8a3c',
  bg_to      VARCHAR(9)   NOT NULL DEFAULT '#0a5c28',
  sort_order INT NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================== SEED DATA ==============================

INSERT INTO users (id, name, email, phone, password_hash, role, avatar_color) VALUES
  (1, 'Market Admin', 'admin@market.test', '9876500001', '$2y$12$HvT3pTtShu0mRRLHpj2r6eo52AtDsSx76xZJ6ULiX7rlDpGuDLrMe', 'admin', '#0f8a3c'),
  (2, 'Demo Shopper', 'demo@market.test', '9876500002', '$2y$12$lVz95iMfq/XHttp8lGulHuiNtjwFlAyPvtbXTDQ1X3aergmhmEIIa', 'customer', '#e26a2c'),
  (3, 'Priya Sharma', 'priya@market.test', '9876500003', '$2y$12$lVz95iMfq/XHttp8lGulHuiNtjwFlAyPvtbXTDQ1X3aergmhmEIIa', 'customer', '#2d6cdf'),
  (4, 'Rahul Verma', 'rahul@market.test', '9876500004', '$2y$12$lVz95iMfq/XHttp8lGulHuiNtjwFlAyPvtbXTDQ1X3aergmhmEIIa', 'customer', '#8b46c9');

INSERT INTO addresses (user_id, label, full_name, phone, line1, line2, city, state, pincode, is_default) VALUES
  (2, 'Home', 'Demo Shopper', '9876500002', 'B-402, Green Valley Residency', 'Station Road', 'Valsad', 'Gujarat', '396001', 1),
  (2, 'Work', 'Demo Shopper', '9876500002', '2nd Floor, Sunrise Business Park', 'GIDC Char Rasta', 'Vapi', 'Gujarat', '396195', 0);

INSERT INTO categories (id, parent_id, name, slug, emoji, tint, sort_order) VALUES
  (1, NULL, 'Fruits & Vegetables', 'fruits-vegetables', '🥦', '#e6f7ec', 10),
  (2, 1, 'Fresh Fruits', 'fresh-fruits', '🥦', '#e6f7ec', 1),
  (3, 1, 'Fresh Vegetables', 'fresh-vegetables', '🥦', '#e6f7ec', 2),
  (4, 1, 'Herbs & Seasonings', 'herbs-seasonings', '🥦', '#e6f7ec', 3),
  (5, 1, 'Exotic & Organic', 'exotic-organic', '🥦', '#e6f7ec', 4),
  (6, NULL, 'Dairy, Bread & Eggs', 'dairy-bread-eggs', '🥛', '#eef3ff', 20),
  (7, 6, 'Milk & Curd', 'milk-curd', '🥛', '#eef3ff', 1),
  (8, 6, 'Paneer & Cheese', 'paneer-cheese', '🥛', '#eef3ff', 2),
  (9, 6, 'Bread & Buns', 'bread-buns', '🥛', '#eef3ff', 3),
  (10, 6, 'Eggs', 'eggs', '🥛', '#eef3ff', 4),
  (11, NULL, 'Atta, Rice & Dal', 'atta-rice-dal', '🌾', '#fdf4e3', 30),
  (12, 11, 'Atta & Flours', 'atta-flours', '🌾', '#fdf4e3', 1),
  (13, 11, 'Rice & Poha', 'rice-poha', '🌾', '#fdf4e3', 2),
  (14, 11, 'Dals & Pulses', 'dals-pulses', '🌾', '#fdf4e3', 3),
  (15, 11, 'Sugar & Jaggery', 'sugar-jaggery', '🌾', '#fdf4e3', 4),
  (16, NULL, 'Masala & Oils', 'masala-oils', '🫒', '#fdf0e8', 40),
  (17, 16, 'Spices & Masala', 'spices-masala', '🫒', '#fdf0e8', 1),
  (18, 16, 'Edible Oils', 'edible-oils', '🫒', '#fdf0e8', 2),
  (19, 16, 'Ghee & Vanaspati', 'ghee-vanaspati', '🫒', '#fdf0e8', 3),
  (20, 16, 'Salt & Sugar', 'salt-sugar', '🫒', '#fdf0e8', 4),
  (21, NULL, 'Snacks & Munchies', 'snacks-munchies', '🍿', '#fff2e8', 50),
  (22, 21, 'Chips & Crisps', 'chips-crisps', '🍿', '#fff2e8', 1),
  (23, 21, 'Namkeen', 'namkeen', '🍿', '#fff2e8', 2),
  (24, 21, 'Popcorn & Nachos', 'popcorn-nachos', '🍿', '#fff2e8', 3),
  (25, 21, 'Dry Fruits', 'dry-fruits', '🍿', '#fff2e8', 4),
  (26, NULL, 'Cold Drinks & Juices', 'cold-drinks-juices', '🥤', '#e8f4fd', 60),
  (27, 26, 'Soft Drinks', 'soft-drinks', '🥤', '#e8f4fd', 1),
  (28, 26, 'Fruit Juices', 'fruit-juices', '🥤', '#e8f4fd', 2),
  (29, 26, 'Energy Drinks', 'energy-drinks', '🥤', '#e8f4fd', 3),
  (30, 26, 'Water & Soda', 'water-soda', '🥤', '#e8f4fd', 4),
  (31, NULL, 'Tea, Coffee & More', 'tea-coffee-more', '☕', '#f4ecdf', 70),
  (32, 31, 'Tea', 'tea', '☕', '#f4ecdf', 1),
  (33, 31, 'Coffee', 'coffee', '☕', '#f4ecdf', 2),
  (34, 31, 'Health Drinks', 'health-drinks', '☕', '#f4ecdf', 3),
  (35, 31, 'Milk Powder', 'milk-powder', '☕', '#f4ecdf', 4),
  (36, NULL, 'Bakery & Biscuits', 'bakery-biscuits', '🍪', '#fdf1e0', 80),
  (37, 36, 'Biscuits & Cookies', 'biscuits-cookies', '🍪', '#fdf1e0', 1),
  (38, 36, 'Cakes & Rusk', 'cakes-rusk', '🍪', '#fdf1e0', 2),
  (39, 36, 'Chocolates', 'chocolates', '🍪', '#fdf1e0', 3),
  (40, 36, 'Sweets', 'sweets', '🍪', '#fdf1e0', 4),
  (41, NULL, 'Instant & Frozen', 'instant-frozen', '🍜', '#eef7f0', 90),
  (42, 41, 'Noodles & Pasta', 'noodles-pasta', '🍜', '#eef7f0', 1),
  (43, 41, 'Ready to Eat', 'ready-to-eat', '🍜', '#eef7f0', 2),
  (44, 41, 'Frozen Veg', 'frozen-veg', '🍜', '#eef7f0', 3),
  (45, 41, 'Sauces & Spreads', 'sauces-spreads', '🍜', '#eef7f0', 4),
  (46, NULL, 'Personal Care', 'personal-care', '🧴', '#f3ecfd', 100),
  (47, 46, 'Hair Care', 'hair-care', '🧴', '#f3ecfd', 1),
  (48, 46, 'Skin Care', 'skin-care', '🧴', '#f3ecfd', 2),
  (49, 46, 'Oral Care', 'oral-care', '🧴', '#f3ecfd', 3),
  (50, 46, 'Bath & Body', 'bath-body', '🧴', '#f3ecfd', 4),
  (51, NULL, 'Cleaning Essentials', 'cleaning-essentials', '🧽', '#e9f6f8', 110),
  (52, 51, 'Detergents', 'detergents', '🧽', '#e9f6f8', 1),
  (53, 51, 'Dishwash', 'dishwash', '🧽', '#e9f6f8', 2),
  (54, 51, 'Floor & Toilet', 'floor-toilet', '🧽', '#e9f6f8', 3),
  (55, 51, 'Fresheners', 'fresheners', '🧽', '#e9f6f8', 4),
  (56, NULL, 'Baby Care', 'baby-care', '🍼', '#fdecf3', 120),
  (57, 56, 'Diapers', 'diapers', '🍼', '#fdecf3', 1),
  (58, 56, 'Baby Food', 'baby-food', '🍼', '#fdecf3', 2),
  (59, 56, 'Baby Bath', 'baby-bath', '🍼', '#fdecf3', 3),
  (60, 56, 'Wipes', 'wipes', '🍼', '#fdecf3', 4);

INSERT INTO brands (id, name, slug) VALUES
  (1, 'Amul', 'amul'),
  (2, 'Aashirvaad', 'aashirvaad'),
  (3, 'Fortune', 'fortune'),
  (4, 'Tata', 'tata'),
  (5, 'Nestlé', 'nestl'),
  (6, 'Britannia', 'britannia'),
  (7, 'Parle', 'parle'),
  (8, 'Haldiram''s', 'haldiram-s'),
  (9, 'Mother Dairy', 'mother-dairy'),
  (10, 'Saffola', 'saffola'),
  (11, 'Dabur', 'dabur'),
  (12, 'Patanjali', 'patanjali'),
  (13, 'Kellogg''s', 'kellogg-s'),
  (14, 'Maggi', 'maggi'),
  (15, 'Coca-Cola', 'coca-cola'),
  (16, 'Tropicana', 'tropicana'),
  (17, 'Lay''s', 'lay-s'),
  (18, 'Kurkure', 'kurkure'),
  (19, 'Cadbury', 'cadbury'),
  (20, 'Himalaya', 'himalaya'),
  (21, 'Pampers', 'pampers'),
  (22, 'Surf Excel', 'surf-excel'),
  (23, 'Vim', 'vim'),
  (24, 'Harpic', 'harpic'),
  (25, 'Lizol', 'lizol'),
  (26, 'Dove', 'dove'),
  (27, 'Colgate', 'colgate'),
  (28, 'Everest', 'everest'),
  (29, 'MDH', 'mdh'),
  (30, 'Bru', 'bru'),
  (31, 'Red Label', 'red-label'),
  (32, 'Real', 'real'),
  (33, 'Farm Fresh', 'farm-fresh'),
  (34, 'Organic India', 'organic-india'),
  (35, 'Bikano', 'bikano'),
  (36, 'Sunfeast', 'sunfeast'),
  (37, 'Del Monte', 'del-monte'),
  (38, 'Nivea', 'nivea'),
  (39, 'Head & Shoulders', 'head-shoulders'),
  (40, 'Cerelac', 'cerelac');

INSERT INTO products (id, category_id, brand_id, name, slug, sku, unit, short_desc, description, highlights, price, mrp, stock, emoji, tint, image, pack_type, is_veg, is_organic, is_featured, is_active, rating_sum, rating_count, sold_count) VALUES
  (1, 2, 33, 'Shimla Apple (Premium)', 'shimla-apple-premium', 'MKT-0001', '1 kg', '1 kg · No artificial colours or preservatives', 'Shimla Apple (Premium) from Farm Fresh comes in a 1 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 kg pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 179, 249, 120, '🍎', '#e6f7ec', 'shimla-apple-premium.webp', 'loose', 1, 0, 0, 1, 1646, 484, 512),
  (2, 2, 33, 'Robusta Banana', 'robusta-banana', 'MKT-0002', '6 pcs', '6 pcs · Sealed for freshness, delivered within hours', 'Robusta Banana from Farm Fresh comes in a 6 pcs pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '6 pcs pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 49, 65, 0, '🍌', '#fdf4e3', 'robusta-banana.webp', 'loose', 1, 0, 0, 1, 1634, 363, 691),
  (3, 2, 33, 'Alphonso Mango (Ratnagiri)', 'alphonso-mango-ratnagiri', 'MKT-0003', '1 kg', '1 kg · Stored in a temperature controlled facility', 'Alphonso Mango (Ratnagiri) from Farm Fresh comes in a 1 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 kg pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 449, 599, 47, '🥭', '#fdf0e8', 'alphonso-mango-ratnagiri.webp', 'loose', 1, 0, 0, 1, 2423, 591, 1519),
  (4, 2, 33, 'Nagpur Orange', 'nagpur-orange', 'MKT-0004', '1 kg', '1 kg · Handpicked and quality-checked at the source', 'Nagpur Orange from Farm Fresh comes in a 1 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 kg pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 129, 169, 63, '🍊', '#fdecf3', 'nagpur-orange.webp', 'loose', 1, 0, 1, 1, 1922, 427, 155),
  (5, 2, 33, 'Green Seedless Grapes', 'green-seedless-grapes', 'MKT-0005', '500 g', '500 g · Sealed for freshness, delivered within hours', 'Green Seedless Grapes from Farm Fresh comes in a 500 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '500 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 89, 120, 154, '🍇', '#e8f4fd', NULL, 'loose', 1, 0, 0, 1, 695, 158, 1386),
  (6, 2, 33, 'Pomegranate Bhagwa', 'pomegranate-bhagwa', 'MKT-0006', '2 pcs', '2 pcs · Stored in a temperature controlled facility', 'Pomegranate Bhagwa from Farm Fresh comes in a 2 pcs pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '2 pcs pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 159, 210, 32, '🍎', '#fff2e8', 'pomegranate-bhagwa.webp', 'loose', 1, 0, 0, 1, 137, 36, 364),
  (7, 2, 33, 'Watermelon Kiran', 'watermelon-kiran', 'MKT-0007', '1 pc (2-3 kg)', '1 pc (2-3 kg) · Stored in a temperature controlled facility', 'Watermelon Kiran from Farm Fresh comes in a 1 pc (2-3 kg) pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 pc (2-3 kg) pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 79, 110, 27, '🍉', '#fdf0e8', 'watermelon-kiran.webp', 'sack', 1, 0, 0, 1, 1447, 391, 2219),
  (8, 5, 33, 'Strawberry Mahabaleshwar', 'strawberry-mahabaleshwar', 'MKT-0008', '200 g', '200 g · Best before 6 months from packaging', 'Strawberry Mahabaleshwar from Farm Fresh comes in a 200 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '200 g pack
Brand: Farm Fresh
Certified organic produce
100% vegetarian
Free delivery on orders above ₹499', 149, 199, 0, '🍓', '#fdf4e3', NULL, 'loose', 1, 1, 0, 1, 956, 195, 2623),
  (9, 5, 33, 'Kiwi Green (Imported)', 'kiwi-green-imported', 'MKT-0009', '3 pcs', '3 pcs · Best before 6 months from packaging', 'Kiwi Green (Imported) from Farm Fresh comes in a 3 pcs pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '3 pcs pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 119, 165, 16, '🥝', '#fdf4e3', 'kiwi-green-imported.webp', 'loose', 1, 0, 0, 1, 1904, 560, 3888),
  (10, 5, 33, 'Avocado Hass', 'avocado-hass', 'MKT-0010', '2 pcs', '2 pcs · Best before 6 months from packaging', 'Avocado Hass from Farm Fresh comes in a 2 pcs pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '2 pcs pack
Brand: Farm Fresh
Certified organic produce
100% vegetarian
Free delivery on orders above ₹499', 229, 299, 66, '🥑', '#fdf4e3', 'avocado-hass.webp', 'loose', 1, 1, 0, 1, 1548, 430, 3458),
  (11, 3, 33, 'Tomato Hybrid', 'tomato-hybrid', 'MKT-0011', '1 kg', '1 kg · No artificial colours or preservatives', 'Tomato Hybrid from Farm Fresh comes in a 1 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 kg pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 34, 49, 165, '🍅', '#f4ecdf', 'tomato-hybrid.webp', 'loose', 1, 0, 0, 1, 3823, 889, 1217),
  (12, 3, 33, 'Onion Nashik', 'onion-nashik', 'MKT-0012', '1 kg', '1 kg · No artificial colours or preservatives', 'Onion Nashik from Farm Fresh comes in a 1 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 kg pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 39, 55, 0, '🧅', '#fff2e8', 'onion-nashik.webp', 'pouch', 1, 0, 0, 1, 779, 159, 2287),
  (13, 3, 33, 'Potato Jyoti', 'potato-jyoti', 'MKT-0013', '1 kg', '1 kg · Stored in a temperature controlled facility', 'Potato Jyoti from Farm Fresh comes in a 1 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 kg pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 32, 45, 97, '🥔', '#fff2e8', 'potato-jyoti.webp', 'loose', 1, 0, 0, 1, 2500, 641, 3669),
  (14, 3, 33, 'Green Capsicum', 'green-capsicum', 'MKT-0014', '500 g', '500 g · Best before 6 months from packaging', 'Green Capsicum from Farm Fresh comes in a 500 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '500 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 45, 60, 136, '🫑', '#e6f7ec', 'green-capsicum.webp', 'loose', 1, 0, 0, 1, 1472, 320, 3348),
  (15, 3, 33, 'Cauliflower', 'cauliflower', 'MKT-0015', '1 pc', '1 pc · Stored in a temperature controlled facility', 'Cauliflower from Farm Fresh comes in a 1 pc pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 pc pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 39, 52, 17, '🥦', '#f4ecdf', NULL, 'loose', 1, 0, 0, 1, 3483, 741, 3469),
  (16, 3, 33, 'Carrot Ooty', 'carrot-ooty', 'MKT-0016', '500 g', '500 g · Sealed for freshness, delivered within hours', 'Carrot Ooty from Farm Fresh comes in a 500 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '500 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 42, 58, 169, '🥕', '#fdf0e8', 'carrot-ooty.webp', 'loose', 1, 0, 0, 1, 2496, 713, 4041),
  (17, 3, 33, 'Cucumber Green', 'cucumber-green', 'MKT-0017', '500 g', '500 g · Stored in a temperature controlled facility', 'Cucumber Green from Farm Fresh comes in a 500 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '500 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 29, 40, 42, '🥒', '#e8f4fd', 'cucumber-green.webp', 'loose', 1, 0, 0, 1, 166, 46, 3674),
  (18, 4, 34, 'Organic Spinach (Palak)', 'organic-spinach-palak', 'MKT-0018', '250 g', '250 g · No artificial colours or preservatives', 'Organic Spinach (Palak) from Organic India comes in a 250 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '250 g pack
Brand: Organic India
Certified organic produce
100% vegetarian
Free delivery on orders above ₹499', 35, 49, 65, '🥬', '#fff2e8', NULL, 'loose', 1, 1, 0, 1, 872, 249, 3577),
  (19, 4, 33, 'Coriander Leaves', 'coriander-leaves', 'MKT-0019', '100 g', '100 g · Sealed for freshness, delivered within hours', 'Coriander Leaves from Farm Fresh comes in a 100 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '100 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 15, 25, 134, '🌿', '#e6f7ec', NULL, 'loose', 1, 0, 0, 1, 4118, 858, 1486),
  (20, 4, 34, 'Ginger Organic', 'ginger-organic', 'MKT-0020', '250 g', '250 g · No artificial colours or preservatives', 'Ginger Organic from Organic India comes in a 250 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '250 g pack
Brand: Organic India
Certified organic produce
100% vegetarian
Free delivery on orders above ₹499', 59, 79, 95, '🫚', '#fdecf3', 'ginger-organic.webp', 'loose', 1, 1, 0, 1, 2876, 639, 667),
  (21, 2, 33, 'Lemon (Nimbu)', 'lemon-nimbu', 'MKT-0021', '500 g', '500 g · Stored in a temperature controlled facility', 'Lemon (Nimbu) from Farm Fresh comes in a 500 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '500 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 39, 55, 152, '🍋', '#e8f4fd', 'lemon-nimbu.webp', 'loose', 1, 0, 0, 1, 718, 156, 2884),
  (22, 2, 33, 'Lime Kagzi Nimbu', 'lime-kagzi-nimbu', 'MKT-0022', '250 g', '250 g · No artificial colours or preservatives', 'Lime Kagzi Nimbu from Farm Fresh comes in a 250 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '250 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 29, 42, 120, '🍋', '#e6f7ec', 'lime-kagzi-nimbu.webp', 'loose', 1, 0, 0, 1, 1277, 304, 4232),
  (23, 2, 33, 'Papaya Semi-Ripe', 'papaya-semi-ripe', 'MKT-0023', '1 pc (800 g-1 kg)', '1 pc (800 g-1 kg) · Sealed for freshness, delivered within hours', 'Papaya Semi-Ripe from Farm Fresh comes in a 1 pc (800 g-1 kg) pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 pc (800 g-1 kg) pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 59, 85, 44, '🍈', '#fdf0e8', 'papaya-semi-ripe.webp', 'sack', 1, 0, 0, 1, 2611, 768, 3496),
  (24, 2, 33, 'Pineapple Queen', 'pineapple-queen', 'MKT-0024', '1 pc', '1 pc · Sealed for freshness, delivered within hours', 'Pineapple Queen from Farm Fresh comes in a 1 pc pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 pc pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 69, 95, 184, '🍍', '#e6f7ec', 'pineapple-queen.webp', 'loose', 1, 0, 0, 1, 865, 188, 1716),
  (25, 2, 33, 'Pear Babugosha', 'pear-babugosha', 'MKT-0025', '500 g', '500 g · Handpicked and quality-checked at the source', 'Pear Babugosha from Farm Fresh comes in a 500 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '500 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 89, 119, 148, '🍐', '#eef3ff', 'pear-babugosha.webp', 'loose', 1, 0, 1, 1, 1394, 332, 360),
  (26, 5, 33, 'Peach Imported', 'peach-imported', 'MKT-0026', '4 pcs', '4 pcs · No artificial colours or preservatives', 'Peach Imported from Farm Fresh comes in a 4 pcs pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '4 pcs pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 199, 265, 120, '🍑', '#fff2e8', 'peach-imported.webp', 'loose', 1, 0, 0, 1, 2033, 484, 512),
  (27, 5, 33, 'Plum Red', 'plum-red', 'MKT-0027', '500 g', '500 g · Sealed for freshness, delivered within hours', 'Plum Red from Farm Fresh comes in a 500 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '500 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 139, 185, 19, '🍑', '#e9f6f8', 'plum-red.webp', 'loose', 1, 0, 0, 1, 751, 203, 2331),
  (28, 2, 33, 'Musk Melon (Kharbooja)', 'musk-melon-kharbooja', 'MKT-0028', '1 pc (1-1.5 kg)', '1 pc (1-1.5 kg) · Handpicked and quality-checked at the source', 'Musk Melon (Kharbooja) from Farm Fresh comes in a 1 pc (1-1.5 kg) pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 pc (1-1.5 kg) pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 65, 89, 38, '🍈', '#fdf0e8', 'musk-melon-kharbooja.webp', 'sack', 1, 0, 1, 1, 888, 222, 3550),
  (29, 2, 33, 'Kinnow Mandarin', 'kinnow-mandarin', 'MKT-0029', '1 kg', '1 kg · Best before 6 months from packaging', 'Kinnow Mandarin from Farm Fresh comes in a 1 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 kg pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 99, 139, 0, '🍊', '#f4ecdf', 'kinnow-mandarin.webp', 'loose', 1, 0, 0, 1, 572, 130, 4058),
  (30, 3, 33, 'Button Mushrooms', 'button-mushrooms', 'MKT-0030', '200 g', '200 g · Handpicked and quality-checked at the source', 'Button Mushrooms from Farm Fresh comes in a 200 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '200 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 59, 79, 118, '🍄', '#eef3ff', 'button-mushrooms.webp', 'loose', 1, 0, 1, 1, 2314, 482, 4110),
  (31, 4, 33, 'Garlic (Lehsun)', 'garlic-lehsun', 'MKT-0031', '250 g', '250 g · Stored in a temperature controlled facility', 'Garlic (Lehsun) from Farm Fresh comes in a 250 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '250 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 49, 69, 0, '🧄', '#fdf0e8', 'garlic-lehsun.webp', 'loose', 1, 0, 0, 1, 263, 71, 3099),
  (32, 3, 33, 'Cabbage (Patta Gobhi)', 'cabbage-patta-gobhi', 'MKT-0032', '1 pc', '1 pc · No artificial colours or preservatives', 'Cabbage (Patta Gobhi) from Farm Fresh comes in a 1 pc pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 pc pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 32, 45, 95, '🥬', '#fdecf3', 'cabbage-patta-gobhi.webp', 'sack', 1, 0, 0, 1, 2620, 639, 1567),
  (33, 3, 33, 'Brinjal (Baingan) Long', 'brinjal-baingan-long', 'MKT-0033', '500 g', '500 g · Best before 6 months from packaging', 'Brinjal (Baingan) Long from Farm Fresh comes in a 500 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '500 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 35, 48, 156, '🍆', '#f3ecfd', 'brinjal-baingan-long.webp', 'loose', 1, 0, 0, 1, 3696, 880, 2408),
  (34, 5, 33, 'Zucchini Green', 'zucchini-green', 'MKT-0034', '500 g', '500 g · Stored in a temperature controlled facility', 'Zucchini Green from Farm Fresh comes in a 500 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '500 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 79, 105, 97, '🥒', '#fdf0e8', 'zucchini-green.webp', 'loose', 1, 0, 0, 1, 2167, 461, 2589),
  (35, 3, 33, 'Sweet Potato (Shakarkandi)', 'sweet-potato-shakarkandi', 'MKT-0035', '1 kg', '1 kg · Sealed for freshness, delivered within hours', 'Sweet Potato (Shakarkandi) from Farm Fresh comes in a 1 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 kg pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 49, 65, 174, '🍠', '#fff2e8', 'sweet-potato-shakarkandi.webp', 'loose', 1, 0, 0, 1, 1718, 358, 3686),
  (36, 3, 33, 'Beetroot (Chukandar)', 'beetroot-chukandar', 'MKT-0036', '500 g', '500 g · Handpicked and quality-checked at the source', 'Beetroot (Chukandar) from Farm Fresh comes in a 500 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '500 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 32, 44, 93, '🫒', '#fdf0e8', 'beetroot-chukandar.webp', 'loose', 1, 0, 1, 1, 1782, 457, 2885),
  (37, 5, 33, 'Red Capsicum', 'red-capsicum', 'MKT-0037', '250 g', '250 g · Handpicked and quality-checked at the source', 'Red Capsicum from Farm Fresh comes in a 250 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '250 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 89, 119, 88, '🫑', '#fdecf3', 'red-capsicum.webp', 'loose', 1, 0, 1, 1, 2654, 632, 960),
  (38, 5, 33, 'Yellow Capsicum', 'yellow-capsicum', 'MKT-0038', '250 g', '250 g · Sealed for freshness, delivered within hours', 'Yellow Capsicum from Farm Fresh comes in a 250 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '250 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 89, 119, 169, '🫑', '#e9f6f8', 'yellow-capsicum.webp', 'loose', 1, 0, 0, 1, 2292, 533, 4161),
  (39, 4, 33, 'Spring Onion Leek', 'spring-onion-leek', 'MKT-0039', '250 g', '250 g · Stored in a temperature controlled facility', 'Spring Onion Leek from Farm Fresh comes in a 250 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '250 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 45, 62, 122, '🧅', '#fdf4e3', 'spring-onion-leek.webp', 'pouch', 1, 0, 0, 1, 1944, 486, 3214),
  (40, 5, 33, 'Asparagus Spears', 'asparagus-spears', 'MKT-0040', '200 g', '200 g · No artificial colours or preservatives', 'Asparagus Spears from Farm Fresh comes in a 200 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '200 g pack
Brand: Farm Fresh
Certified organic produce
100% vegetarian
Free delivery on orders above ₹499', 249, 319, 80, '🌿', '#f3ecfd', 'asparagus-spears.webp', 'loose', 1, 1, 0, 1, 3377, 804, 832),
  (41, 3, 33, 'Beef Tomato Salad', 'beef-tomato-salad', 'MKT-0041', '500 g', '500 g · Stored in a temperature controlled facility', 'Beef Tomato Salad from Farm Fresh comes in a 500 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '500 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 59, 79, 77, '🍅', '#fdf4e3', 'beef-tomato-salad.webp', 'loose', 1, 0, 0, 1, 1122, 261, 3889),
  (42, 7, 1, 'Amul Taaza Toned Milk', 'amul-taaza-toned-milk', 'MKT-0042', '1 L', '1 L · Sealed for freshness, delivered within hours', 'Amul Taaza Toned Milk from Amul comes in a 1 L pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 L pack
Brand: Amul
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 72, 78, 89, '🥛', '#e8f4fd', NULL, 'carton', 1, 0, 0, 1, 2975, 633, 2461),
  (43, 7, 1, 'Amul Gold Full Cream Milk', 'amul-gold-full-cream-milk', 'MKT-0043', '1 L', '1 L · Handpicked and quality-checked at the source', 'Amul Gold Full Cream Milk from Amul comes in a 1 L pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 L pack
Brand: Amul
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 84, 90, 173, '🥛', '#e6f7ec', NULL, 'carton', 1, 0, 1, 1, 3083, 717, 1345),
  (44, 7, 9, 'Mother Dairy Classic Dahi', 'mother-dairy-classic-dahi', 'MKT-0044', '400 g', '400 g · Sealed for freshness, delivered within hours', 'Mother Dairy Classic Dahi from Mother Dairy comes in a 400 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '400 g pack
Brand: Mother Dairy
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 54, 65, 84, '🍶', '#eef3ff', NULL, 'tub', 1, 0, 0, 1, 1882, 448, 776),
  (45, 7, 1, 'Amul Masti Buttermilk', 'amul-masti-buttermilk', 'MKT-0045', '500 ml', '500 ml · Sealed for freshness, delivered within hours', 'Amul Masti Buttermilk from Amul comes in a 500 ml pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '500 ml pack
Brand: Amul
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 20, 25, 0, '🥛', '#f3ecfd', NULL, 'tub', 1, 0, 0, 1, 3974, 883, 1811),
  (46, 8, 1, 'Amul Fresh Malai Paneer', 'amul-fresh-malai-paneer', 'MKT-0046', '200 g', '200 g · Sealed for freshness, delivered within hours', 'Amul Fresh Malai Paneer from Amul comes in a 200 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '200 g pack
Brand: Amul
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 95, 110, 49, '🧀', '#fdf1e0', NULL, 'tub', 1, 0, 0, 1, 3324, 773, 3201),
  (47, 8, 1, 'Amul Cheese Slices', 'amul-cheese-slices', 'MKT-0047', '200 g (10 slices)', '200 g (10 slices) · Best before 6 months from packaging', 'Amul Cheese Slices from Amul comes in a 200 g (10 slices) pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '200 g (10 slices) pack
Brand: Amul
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 145, 160, 111, '🧀', '#fdf0e8', NULL, 'tub', 1, 0, 0, 1, 3210, 655, 2183),
  (48, 8, 1, 'Amul Butter Pasteurised', 'amul-butter-pasteurised', 'MKT-0048', '500 g', '500 g · Stored in a temperature controlled facility', 'Amul Butter Pasteurised from Amul comes in a 500 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '500 g pack
Brand: Amul
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 285, 305, 72, '🧈', '#fdf1e0', NULL, 'tub', 1, 0, 0, 1, 2341, 616, 644),
  (49, 9, 6, 'Britannia Brown Bread', 'britannia-brown-bread', 'MKT-0049', '400 g', '400 g · Stored in a temperature controlled facility', 'Britannia Brown Bread from Britannia comes in a 400 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '400 g pack
Brand: Britannia
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 45, 55, 62, '🍞', '#fdf4e3', NULL, 'pouch', 1, 0, 0, 1, 290, 66, 3994),
  (50, 9, 6, 'Britannia Pav Buns', 'britannia-pav-buns', 'MKT-0050', '6 pcs', '6 pcs · Stored in a temperature controlled facility', 'Britannia Pav Buns from Britannia comes in a 6 pcs pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '6 pcs pack
Brand: Britannia
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 35, 42, 142, '🥐', '#fdecf3', NULL, 'pouch', 1, 0, 0, 1, 584, 146, 774),
  (51, 10, 33, 'Farm Fresh Brown Eggs', 'farm-fresh-brown-eggs', 'MKT-0051', '12 pcs', '12 pcs · Handpicked and quality-checked at the source', 'Farm Fresh Brown Eggs from Farm Fresh comes in a 12 pcs pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '12 pcs pack
Brand: Farm Fresh
Quality checked before dispatch
Contains egg
Free delivery on orders above ₹499', 119, 149, 0, '🥚', '#e9f6f8', NULL, 'tray', 0, 0, 1, 1, 2986, 807, 2035),
  (52, 12, 2, 'Aashirvaad Shudh Chakki Atta', 'aashirvaad-shudh-chakki-atta', 'MKT-0052', '5 kg', '5 kg · No artificial colours or preservatives', 'Aashirvaad Shudh Chakki Atta from Aashirvaad comes in a 5 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '5 kg pack
Brand: Aashirvaad
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 265, 320, 160, '🌾', '#fdf4e3', NULL, 'sack', 1, 0, 0, 1, 754, 164, 2292),
  (53, 12, 2, 'Aashirvaad Multigrain Atta', 'aashirvaad-multigrain-atta', 'MKT-0053', '5 kg', '5 kg · Best before 6 months from packaging', 'Aashirvaad Multigrain Atta from Aashirvaad comes in a 5 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '5 kg pack
Brand: Aashirvaad
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 345, 399, 36, '🌾', '#f4ecdf', NULL, 'sack', 1, 0, 0, 1, 2204, 580, 3908),
  (54, 12, 4, 'Besan (Gram Flour)', 'besan-gram-flour', 'MKT-0054', '1 kg', '1 kg · Sealed for freshness, delivered within hours', 'Besan (Gram Flour) from Tata comes in a 1 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 kg pack
Brand: Tata
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 105, 135, 149, '🌾', '#f4ecdf', NULL, 'sack', 1, 0, 0, 1, 597, 153, 1981),
  (55, 13, 4, 'India Gate Basmati Rice', 'india-gate-basmati-rice', 'MKT-0055', '5 kg', '5 kg · Best before 6 months from packaging', 'India Gate Basmati Rice from Tata comes in a 5 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '5 kg pack
Brand: Tata
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 649, 799, 171, '🍚', '#e9f6f8', NULL, 'sack', 1, 0, 0, 1, 3218, 715, 2843),
  (56, 13, 4, 'Sona Masoori Rice', 'sona-masoori-rice', 'MKT-0056', '10 kg', '10 kg · Best before 6 months from packaging', 'Sona Masoori Rice from Tata comes in a 10 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '10 kg pack
Brand: Tata
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 729, 899, 71, '🍚', '#e8f4fd', NULL, 'sack', 1, 0, 0, 1, 3896, 795, 1423),
  (57, 14, 4, 'Toor Dal (Arhar) Premium', 'toor-dal-arhar-premium', 'MKT-0057', '1 kg', '1 kg · Handpicked and quality-checked at the source', 'Toor Dal (Arhar) Premium from Tata comes in a 1 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 kg pack
Brand: Tata
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 165, 199, 178, '🫘', '#f3ecfd', NULL, 'sack', 1, 0, 1, 1, 3177, 722, 450),
  (58, 14, 4, 'Moong Dal Yellow', 'moong-dal-yellow', 'MKT-0058', '1 kg', '1 kg · Best before 6 months from packaging', 'Moong Dal Yellow from Tata comes in a 1 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 kg pack
Brand: Tata
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 139, 175, 26, '🫘', '#eef3ff', NULL, 'sack', 1, 0, 0, 1, 840, 210, 238),
  (59, 14, 4, 'Rajma Chitra', 'rajma-chitra', 'MKT-0059', '1 kg', '1 kg · Best before 6 months from packaging', 'Rajma Chitra from Tata comes in a 1 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 kg pack
Brand: Tata
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 179, 215, 126, '🫘', '#fdf4e3', NULL, 'sack', 1, 0, 0, 1, 2680, 670, 2198),
  (60, 14, 4, 'Chana Dal', 'chana-dal', 'MKT-0060', '1 kg', '1 kg · Stored in a temperature controlled facility', 'Chana Dal from Tata comes in a 1 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 kg pack
Brand: Tata
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 99, 129, 47, '🫘', '#fdecf3', NULL, 'sack', 1, 0, 0, 1, 2853, 771, 2899),
  (61, 18, 3, 'Fortune Sunlite Refined Sunflower Oil', 'fortune-sunlite-refined-sunflower-oil', 'MKT-0061', '1 L jar', '1 L jar · No artificial colours or preservatives', 'Fortune Sunlite Refined Sunflower Oil from Fortune comes in a 1 L jar pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 L jar pack
Brand: Fortune
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 159, 199, 85, '🫗', '#f4ecdf', NULL, 'bottle', 1, 0, 0, 1, 942, 269, 297),
  (62, 18, 10, 'Saffola Gold Blended Oil', 'saffola-gold-blended-oil', 'MKT-0062', '1 L', '1 L · Best before 6 months from packaging', 'Saffola Gold Blended Oil from Saffola comes in a 1 L pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 L pack
Brand: Saffola
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 185, 229, 56, '🫗', '#fdf1e0', NULL, 'bottle', 1, 0, 0, 1, 2040, 600, 328),
  (63, 18, 3, 'Fortune Kachi Ghani Mustard Oil', 'fortune-kachi-ghani-mustard-oil', 'MKT-0063', '1 L', '1 L · Sealed for freshness, delivered within hours', 'Fortune Kachi Ghani Mustard Oil from Fortune comes in a 1 L pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 L pack
Brand: Fortune
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 165, 210, 144, '🫗', '#fdf0e8', NULL, 'bottle', 1, 0, 0, 1, 2890, 688, 2216),
  (64, 19, 1, 'Amul Pure Cow Ghee', 'amul-pure-cow-ghee', 'MKT-0064', '1 L', '1 L · Best before 6 months from packaging', 'Amul Pure Cow Ghee from Amul comes in a 1 L pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 L pack
Brand: Amul
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 649, 725, 46, '🫙', '#e9f6f8', NULL, 'jar', 1, 0, 0, 1, 200, 50, 2478),
  (65, 17, 28, 'Everest Garam Masala', 'everest-garam-masala', 'MKT-0065', '100 g', '100 g · Handpicked and quality-checked at the source', 'Everest Garam Masala from Everest comes in a 100 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '100 g pack
Brand: Everest
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 85, 99, 153, '🌶️', '#fff2e8', NULL, 'pouch', 1, 0, 1, 1, 550, 157, 185),
  (66, 17, 29, 'MDH Deggi Mirch', 'mdh-deggi-mirch', 'MKT-0066', '100 g', '100 g · Stored in a temperature controlled facility', 'MDH Deggi Mirch from MDH comes in a 100 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '100 g pack
Brand: MDH
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 92, 110, 117, '🌶️', '#fff2e8', NULL, 'tray', 1, 0, 0, 1, 1876, 481, 4109),
  (67, 17, 34, 'Turmeric Powder Organic', 'turmeric-powder-organic', 'MKT-0067', '200 g', '200 g · Handpicked and quality-checked at the source', 'Turmeric Powder Organic from Organic India comes in a 200 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '200 g pack
Brand: Organic India
Certified organic produce
100% vegetarian
Free delivery on orders above ₹499', 79, 99, 158, '🟡', '#f3ecfd', NULL, 'pouch', 1, 1, 1, 1, 583, 162, 2290),
  (68, 20, 4, 'Tata Salt Iodised', 'tata-salt-iodised', 'MKT-0068', '1 kg', '1 kg · No artificial colours or preservatives', 'Tata Salt Iodised from Tata comes in a 1 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 kg pack
Brand: Tata
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 28, 32, 50, '🧂', '#fdf4e3', NULL, 'pouch', 1, 0, 0, 1, 3096, 774, 2302),
  (69, 22, 17, 'Lay''s Classic Salted', 'lay-s-classic-salted', 'MKT-0069', '52 g', '52 g · Sealed for freshness, delivered within hours', 'Lay''s Classic Salted from Lay''s comes in a 52 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '52 g pack
Brand: Lay''s
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 20, 20, 39, '🥔', '#fff2e8', NULL, 'pouch', 1, 0, 0, 1, 1975, 403, 1031),
  (70, 22, 17, 'Lay''s American Style Cream & Onion', 'lay-s-american-style-cream-onion', 'MKT-0070', '52 g', '52 g · Handpicked and quality-checked at the source', 'Lay''s American Style Cream & Onion from Lay''s comes in a 52 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '52 g pack
Brand: Lay''s
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 20, 20, 143, '🥔', '#e6f7ec', NULL, 'carton', 1, 0, 1, 1, 2079, 507, 2935),
  (71, 22, 18, 'Kurkure Masala Munch', 'kurkure-masala-munch', 'MKT-0071', '90 g', '90 g · Sealed for freshness, delivered within hours', 'Kurkure Masala Munch from Kurkure comes in a 90 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '90 g pack
Brand: Kurkure
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 40, 45, 0, '🌽', '#e6f7ec', NULL, 'pouch', 1, 0, 0, 1, 1042, 248, 1776),
  (72, 23, 8, 'Haldiram''s Aloo Bhujia', 'haldiram-s-aloo-bhujia', 'MKT-0072', '400 g', '400 g · Best before 6 months from packaging', 'Haldiram''s Aloo Bhujia from Haldiram''s comes in a 400 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '400 g pack
Brand: Haldiram''s
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 105, 125, 106, '🥨', '#e6f7ec', NULL, 'pouch', 1, 0, 0, 1, 3984, 830, 1158),
  (73, 23, 8, 'Haldiram''s Moong Dal', 'haldiram-s-moong-dal', 'MKT-0073', '200 g', '200 g · Sealed for freshness, delivered within hours', 'Haldiram''s Moong Dal from Haldiram''s comes in a 200 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '200 g pack
Brand: Haldiram''s
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 65, 75, 19, '🥨', '#fdecf3', NULL, 'sack', 1, 0, 0, 1, 1417, 383, 1011),
  (74, 23, 35, 'Bikano Navratan Mixture', 'bikano-navratan-mixture', 'MKT-0074', '350 g', '350 g · Sealed for freshness, delivered within hours', 'Bikano Navratan Mixture from Bikano comes in a 350 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '350 g pack
Brand: Bikano
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 95, 115, 114, '🥨', '#eef3ff', NULL, 'pouch', 1, 0, 0, 1, 3158, 658, 2486),
  (75, 24, 36, 'Act II Butter Popcorn', 'act-ii-butter-popcorn', 'MKT-0075', '90 g', '90 g · No artificial colours or preservatives', 'Act II Butter Popcorn from Sunfeast comes in a 90 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '90 g pack
Brand: Sunfeast
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 35, 40, 80, '🍿', '#e9f6f8', NULL, 'tub', 1, 0, 0, 1, 1865, 444, 1072),
  (76, 25, 33, 'California Almonds', 'california-almonds', 'MKT-0076', '500 g', '500 g · Stored in a temperature controlled facility', 'California Almonds from Farm Fresh comes in a 500 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '500 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 449, 649, 157, '🌰', '#e6f7ec', NULL, 'pouch', 1, 0, 0, 1, 1194, 341, 2169),
  (77, 25, 33, 'Cashew W240 Premium', 'cashew-w240-premium', 'MKT-0077', '500 g', '500 g · Stored in a temperature controlled facility', 'Cashew W240 Premium from Farm Fresh comes in a 500 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '500 g pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 589, 799, 87, '🌰', '#e9f6f8', NULL, 'pouch', 1, 0, 0, 1, 2587, 631, 959),
  (78, 27, 15, 'Coca-Cola Original', 'coca-cola-original', 'MKT-0078', '750 ml', '750 ml · Stored in a temperature controlled facility', 'Coca-Cola Original from Coca-Cola comes in a 750 ml pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '750 ml pack
Brand: Coca-Cola
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 40, 45, 157, '🥤', '#fdf4e3', NULL, 'bottle', 1, 0, 0, 1, 4141, 881, 2709),
  (79, 27, 15, 'Sprite Lime', 'sprite-lime', 'MKT-0079', '750 ml', '750 ml · No artificial colours or preservatives', 'Sprite Lime from Coca-Cola comes in a 750 ml pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '750 ml pack
Brand: Coca-Cola
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 40, 45, 120, '🥤', '#fdf0e8', NULL, 'bottle', 1, 0, 0, 1, 1646, 484, 1712),
  (80, 28, 32, 'Real Mixed Fruit Juice', 'real-mixed-fruit-juice', 'MKT-0080', '1 L', '1 L · Sealed for freshness, delivered within hours', 'Real Mixed Fruit Juice from Real comes in a 1 L pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 L pack
Brand: Real
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 115, 135, 149, '🧃', '#fdf4e3', NULL, 'bottle', 1, 0, 0, 1, 1565, 333, 1861),
  (81, 28, 16, 'Tropicana Orange Delight', 'tropicana-orange-delight', 'MKT-0081', '1 L', '1 L · No artificial colours or preservatives', 'Tropicana Orange Delight from Tropicana comes in a 1 L pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 L pack
Brand: Tropicana
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 119, 140, 70, '🧃', '#eef3ff', NULL, 'pouch', 1, 0, 0, 1, 2702, 614, 1842),
  (82, 30, 4, 'Bisleri Mineral Water', 'bisleri-mineral-water', 'MKT-0082', '2 L', '2 L · No artificial colours or preservatives', 'Bisleri Mineral Water from Tata comes in a 2 L pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '2 L pack
Brand: Tata
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 30, 35, 175, '💧', '#e8f4fd', NULL, 'bottle', 1, 0, 0, 1, 3326, 899, 1827),
  (83, 32, 4, 'Tata Tea Premium', 'tata-tea-premium', 'MKT-0083', '1 kg', '1 kg · Sealed for freshness, delivered within hours', 'Tata Tea Premium from Tata comes in a 1 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 kg pack
Brand: Tata
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 525, 610, 144, '🍵', '#eef3ff', NULL, 'pouch', 1, 0, 0, 1, 1930, 508, 1436),
  (84, 32, 31, 'Red Label Natural Care Tea', 'red-label-natural-care-tea', 'MKT-0084', '500 g', '500 g · Handpicked and quality-checked at the source', 'Red Label Natural Care Tea from Red Label comes in a 500 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '500 g pack
Brand: Red Label
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 285, 330, 0, '🍵', '#fdf1e0', NULL, 'pouch', 1, 0, 1, 1, 2383, 662, 2490),
  (85, 33, 30, 'Bru Instant Coffee', 'bru-instant-coffee', 'MKT-0085', '200 g', '200 g · Best before 6 months from packaging', 'Bru Instant Coffee from Bru comes in a 200 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '200 g pack
Brand: Bru
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 530, 610, 101, '☕', '#e9f6f8', NULL, 'jar', 1, 0, 0, 1, 3548, 825, 2953),
  (86, 35, 5, 'Nestlé Everyday Dairy Whitener', 'nestl-everyday-dairy-whitener', 'MKT-0086', '400 g', '400 g · Handpicked and quality-checked at the source', 'Nestlé Everyday Dairy Whitener from Nestlé comes in a 400 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '400 g pack
Brand: Nestlé
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 235, 265, 53, '🥛', '#fdf4e3', NULL, 'box', 1, 0, 1, 1, 1793, 417, 1345),
  (87, 34, 19, 'Bournvita Health Drink', 'bournvita-health-drink', 'MKT-0087', '750 g', '750 g · Sealed for freshness, delivered within hours', 'Bournvita Health Drink from Cadbury comes in a 750 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '750 g pack
Brand: Cadbury
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 385, 440, 34, '🍫', '#fdf1e0', NULL, 'box', 1, 0, 0, 1, 785, 218, 4146),
  (88, 37, 7, 'Parle-G Gold Biscuits', 'parle-g-gold-biscuits', 'MKT-0088', '1 kg', '1 kg · Sealed for freshness, delivered within hours', 'Parle-G Gold Biscuits from Parle comes in a 1 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 kg pack
Brand: Parle
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 145, 165, 0, '🍪', '#fdf1e0', NULL, 'box', 1, 0, 0, 1, 1170, 308, 3036),
  (89, 37, 6, 'Britannia Good Day Cashew', 'britannia-good-day-cashew', 'MKT-0089', '600 g', '600 g · Handpicked and quality-checked at the source', 'Britannia Good Day Cashew from Britannia comes in a 600 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '600 g pack
Brand: Britannia
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 130, 150, 0, '🍪', '#eef3ff', NULL, 'pouch', 1, 0, 1, 1, 3175, 882, 3610),
  (90, 37, 36, 'Sunfeast Dark Fantasy Choco Fills', 'sunfeast-dark-fantasy-choco-fills', 'MKT-0090', '300 g', '300 g · Handpicked and quality-checked at the source', 'Sunfeast Dark Fantasy Choco Fills from Sunfeast comes in a 300 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '300 g pack
Brand: Sunfeast
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 155, 180, 23, '🍪', '#e8f4fd', NULL, 'pouch', 1, 0, 1, 1, 3063, 747, 1975),
  (91, 38, 6, 'Britannia Fruit Cake', 'britannia-fruit-cake', 'MKT-0091', '450 g', '450 g · Sealed for freshness, delivered within hours', 'Britannia Fruit Cake from Britannia comes in a 450 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '450 g pack
Brand: Britannia
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 135, 155, 144, '🍰', '#f3ecfd', NULL, 'box', 1, 0, 0, 1, 1246, 328, 3356),
  (92, 39, 19, 'Cadbury Dairy Milk Silk', 'cadbury-dairy-milk-silk', 'MKT-0092', '150 g', '150 g · Stored in a temperature controlled facility', 'Cadbury Dairy Milk Silk from Cadbury comes in a 150 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '150 g pack
Brand: Cadbury
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 175, 195, 172, '🍫', '#e9f6f8', NULL, 'bar', 1, 0, 0, 1, 2037, 536, 1764),
  (93, 42, 14, 'Maggi 2-Minute Masala Noodles', 'maggi-2-minute-masala-noodles', 'MKT-0093', '12 pack', '12 pack · Sealed for freshness, delivered within hours', 'Maggi 2-Minute Masala Noodles from Maggi comes in a 12 pack pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '12 pack pack
Brand: Maggi
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 168, 180, 0, '🍜', '#e8f4fd', NULL, 'box', 1, 0, 0, 1, 137, 38, 3666),
  (94, 42, 37, 'Del Monte Penne Pasta', 'del-monte-penne-pasta', 'MKT-0094', '500 g', '500 g · No artificial colours or preservatives', 'Del Monte Penne Pasta from Del Monte comes in a 500 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '500 g pack
Brand: Del Monte
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 119, 145, 0, '🍝', '#f4ecdf', NULL, 'box', 1, 0, 0, 1, 4178, 889, 1517),
  (95, 45, 14, 'Maggi Hot & Sweet Tomato Sauce', 'maggi-hot-sweet-tomato-sauce', 'MKT-0095', '1 kg', '1 kg · No artificial colours or preservatives', 'Maggi Hot & Sweet Tomato Sauce from Maggi comes in a 1 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 kg pack
Brand: Maggi
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 145, 165, 65, '🍅', '#f3ecfd', NULL, 'bottle', 1, 0, 0, 1, 242, 69, 697),
  (96, 45, 5, 'Kissan Mixed Fruit Jam', 'kissan-mixed-fruit-jam', 'MKT-0096', '700 g', '700 g · Best before 6 months from packaging', 'Kissan Mixed Fruit Jam from Nestlé comes in a 700 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '700 g pack
Brand: Nestlé
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 215, 245, 26, '🍓', '#eef3ff', NULL, 'jar', 1, 0, 0, 1, 2280, 570, 2398),
  (97, 44, 33, 'Frozen Green Peas', 'frozen-green-peas', 'MKT-0097', '1 kg', '1 kg · Handpicked and quality-checked at the source', 'Frozen Green Peas from Farm Fresh comes in a 1 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 kg pack
Brand: Farm Fresh
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 145, 175, 0, '🫛', '#e8f4fd', NULL, 'pouch', 1, 0, 1, 1, 3145, 767, 495),
  (98, 43, 8, 'Ready to Eat Dal Makhani', 'ready-to-eat-dal-makhani', 'MKT-0098', '300 g', '300 g · Stored in a temperature controlled facility', 'Ready to Eat Dal Makhani from Haldiram''s comes in a 300 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '300 g pack
Brand: Haldiram''s
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 99, 120, 32, '🍛', '#f3ecfd', NULL, 'sack', 1, 0, 0, 1, 994, 216, 3844),
  (99, 47, 26, 'Dove Intense Repair Shampoo', 'dove-intense-repair-shampoo', 'MKT-0099', '650 ml', '650 ml · Stored in a temperature controlled facility', 'Dove Intense Repair Shampoo from Dove comes in a 650 ml pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '650 ml pack
Brand: Dove
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 649, 775, 12, '🧴', '#f4ecdf', NULL, 'bottle', 1, 0, 0, 1, 1890, 556, 584),
  (100, 47, 39, 'Head & Shoulders Anti-Dandruff', 'head-shoulders-anti-dandruff', 'MKT-0100', '650 ml', '650 ml · Stored in a temperature controlled facility', 'Head & Shoulders Anti-Dandruff from Head & Shoulders comes in a 650 ml pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '650 ml pack
Brand: Head & Shoulders
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 699, 820, 0, '🧴', '#e6f7ec', NULL, 'pouch', 1, 0, 0, 1, 1552, 361, 89),
  (101, 48, 38, 'Nivea Soft Light Moisturiser', 'nivea-soft-light-moisturiser', 'MKT-0101', '300 ml', '300 ml · Best before 6 months from packaging', 'Nivea Soft Light Moisturiser from Nivea comes in a 300 ml pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '300 ml pack
Brand: Nivea
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 399, 475, 31, '🧴', '#e6f7ec', NULL, 'bottle', 1, 0, 0, 1, 2128, 575, 1203),
  (102, 48, 20, 'Himalaya Neem Face Wash', 'himalaya-neem-face-wash', 'MKT-0102', '300 ml', '300 ml · Best before 6 months from packaging', 'Himalaya Neem Face Wash from Himalaya comes in a 300 ml pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '300 ml pack
Brand: Himalaya
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 315, 360, 166, '🧴', '#e8f4fd', NULL, 'bottle', 1, 0, 0, 1, 4272, 890, 2718),
  (103, 49, 27, 'Colgate Strong Teeth Toothpaste', 'colgate-strong-teeth-toothpaste', 'MKT-0103', '500 g', '500 g · Handpicked and quality-checked at the source', 'Colgate Strong Teeth Toothpaste from Colgate comes in a 500 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '500 g pack
Brand: Colgate
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 265, 299, 123, '🪥', '#e9f6f8', NULL, 'tube', 1, 0, 1, 1, 1382, 307, 3035),
  (104, 50, 26, 'Dove Cream Beauty Bathing Bar', 'dove-cream-beauty-bathing-bar', 'MKT-0104', '4 x 100 g', '4 x 100 g · Best before 6 months from packaging', 'Dove Cream Beauty Bathing Bar from Dove comes in a 4 x 100 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '4 x 100 g pack
Brand: Dove
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 280, 320, 76, '🧼', '#fdf1e0', NULL, 'bar', 1, 0, 0, 1, 1196, 260, 1788),
  (105, 52, 22, 'Surf Excel Matic Front Load', 'surf-excel-matic-front-load', 'MKT-0105', '4 kg', '4 kg · Best before 6 months from packaging', 'Surf Excel Matic Front Load from Surf Excel comes in a 4 kg pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '4 kg pack
Brand: Surf Excel
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 899, 1085, 91, '🧺', '#e8f4fd', NULL, 'pouch', 1, 0, 0, 1, 3994, 815, 1143),
  (106, 53, 23, 'Vim Dishwash Liquid Gel', 'vim-dishwash-liquid-gel', 'MKT-0106', '1.8 L', '1.8 L · Sealed for freshness, delivered within hours', 'Vim Dishwash Liquid Gel from Vim comes in a 1.8 L pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1.8 L pack
Brand: Vim
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 359, 420, 99, '🧽', '#f4ecdf', NULL, 'bottle', 1, 0, 0, 1, 464, 103, 1331),
  (107, 54, 24, 'Harpic Power Plus Toilet Cleaner', 'harpic-power-plus-toilet-cleaner', 'MKT-0107', '1 L', '1 L · Best before 6 months from packaging', 'Harpic Power Plus Toilet Cleaner from Harpic comes in a 1 L pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '1 L pack
Brand: Harpic
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 199, 235, 21, '🚽', '#fdf4e3', NULL, 'bottle', 1, 0, 0, 1, 108, 25, 353),
  (108, 54, 25, 'Lizol Disinfectant Floor Cleaner', 'lizol-disinfectant-floor-cleaner', 'MKT-0108', '2 L', '2 L · Sealed for freshness, delivered within hours', 'Lizol Disinfectant Floor Cleaner from Lizol comes in a 2 L pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '2 L pack
Brand: Lizol
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 429, 499, 44, '🧹', '#fdf0e8', NULL, 'bottle', 1, 0, 0, 1, 3533, 768, 2596),
  (109, 57, 21, 'Pampers All Round Protection Pants (M)', 'pampers-all-round-protection-pants-m', 'MKT-0109', '56 pcs', '56 pcs · Stored in a temperature controlled facility', 'Pampers All Round Protection Pants (M) from Pampers comes in a 56 pcs pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '56 pcs pack
Brand: Pampers
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 849, 999, 77, '🍼', '#fdf1e0', NULL, 'pouch', 1, 0, 0, 1, 914, 261, 3889),
  (110, 58, 40, 'Cerelac Wheat Apple Cherry', 'cerelac-wheat-apple-cherry', 'MKT-0110', '300 g', '300 g · Stored in a temperature controlled facility', 'Cerelac Wheat Apple Cherry from Cerelac comes in a 300 g pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '300 g pack
Brand: Cerelac
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 335, 375, 12, '🍼', '#fdf0e8', NULL, 'box', 1, 0, 0, 1, 902, 196, 1724),
  (111, 60, 20, 'Himalaya Baby Gentle Wipes', 'himalaya-baby-gentle-wipes', 'MKT-0111', '72 pcs', '72 pcs · Stored in a temperature controlled facility', 'Himalaya Baby Gentle Wipes from Himalaya comes in a 72 pcs pack. Every batch that reaches a Market warehouse is inspected for weight, packaging and shelf life before it is listed, and orders are picked the same day they are placed.

Store in a cool, dry place away from direct sunlight. Once opened, transfer to an airtight container to keep the contents fresh for longer.', '72 pcs pack
Brand: Himalaya
Quality checked before dispatch
100% vegetarian
Free delivery on orders above ₹499', 199, 240, 137, '🧻', '#f3ecfd', NULL, 'box', 1, 0, 0, 1, 1754, 501, 3529);

INSERT INTO reviews (product_id, user_id, rating, title, body) VALUES
  (1, 3, 4, 'Good value for the price', 'Slightly cheaper than my local store and the freshness was fine. Will reorder.'),
  (2, 4, 4, 'Happy with the order', 'Second time buying this. Consistent quality so far.'),
  (3, 2, 5, 'Exactly as described', 'Packaging was sealed and the quality matched the photos. Delivery landed inside the slot I picked.'),
  (4, 3, 4, 'Good value for the price', 'Slightly cheaper than my local store and the freshness was fine. Will reorder.'),
  (5, 4, 4, 'Happy with the order', 'Second time buying this. Consistent quality so far.'),
  (6, 2, 5, 'Exactly as described', 'Packaging was sealed and the quality matched the photos. Delivery landed inside the slot I picked.'),
  (7, 3, 4, 'Good value for the price', 'Slightly cheaper than my local store and the freshness was fine. Will reorder.'),
  (8, 4, 4, 'Happy with the order', 'Second time buying this. Consistent quality so far.'),
  (9, 2, 5, 'Exactly as described', 'Packaging was sealed and the quality matched the photos. Delivery landed inside the slot I picked.'),
  (10, 3, 4, 'Good value for the price', 'Slightly cheaper than my local store and the freshness was fine. Will reorder.'),
  (11, 4, 4, 'Happy with the order', 'Second time buying this. Consistent quality so far.'),
  (12, 2, 5, 'Exactly as described', 'Packaging was sealed and the quality matched the photos. Delivery landed inside the slot I picked.');

INSERT INTO coupons (code, description, type, value, min_order, max_discount, usage_limit, expires_at, is_active) VALUES
  ('MARKET50', 'Flat ₹50 off on your first order above ₹499', 'flat', 50, 499, NULL, 5000, '2030-12-31', 1),
  ('FRESH10',  '10% off on orders above ₹799 (max ₹150)', 'percent', 10, 799, 150, NULL, '2030-12-31', 1),
  ('SAVE100',  'Flat ₹100 off on orders above ₹1499', 'flat', 100, 1499, NULL, NULL, '2030-12-31', 1),
  ('BIGBASKET15', '15% off on orders above ₹1999 (max ₹400)', 'percent', 15, 1999, 400, NULL, '2030-12-31', 1);

INSERT INTO banners (title, subtitle, cta_text, cta_link, emoji, category_slug, bg_from, bg_to, sort_order) VALUES
  ('Fresh from the farm, before 7 AM', 'Fruits and vegetables picked yesterday evening, at your door this morning.', 'Shop fresh', 'products.php?category=fruits-vegetables', '🥬', 'fruits-vegetables', '#0f8a3c', '#0a5c28', 1),
  ('Monthly ration, one basket', 'Atta, rice, dal and oil at wholesale-style prices. Up to 35% off.', 'Stock up', 'products.php?category=atta-rice-dal', '🌾', 'atta-rice-dal', '#b4530a', '#7a3706', 2),
  ('Snack o''clock', 'Chips, namkeen and chocolates from the brands you already trust.', 'Grab snacks', 'products.php?category=snacks-munchies', '🍿', 'snacks-munchies', '#2d6cdf', '#17408f', 3);
