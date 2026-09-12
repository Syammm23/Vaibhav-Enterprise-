<?php
/**
 * MARKET — application configuration.
 *
 * Copy this file's database values to match your own server (XAMPP/WAMP users
 * usually only need to change DB_PASS).
 */

// ----------------------------------------------------------------- database
define('DB_HOST', getenv('MARKET_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('MARKET_DB_NAME') ?: 'market_db');
define('DB_USER', getenv('MARKET_DB_USER') ?: 'root');
define('DB_PASS', getenv('MARKET_DB_PASS') !== false ? getenv('MARKET_DB_PASS') : '');
define('DB_PORT', getenv('MARKET_DB_PORT') ?: '3306');

// -------------------------------------------------------------------- store
define('STORE_NAME',    'Market');
define('STORE_TAGLINE', 'Groceries, delivered fresh');
define('CURRENCY',      '₹');

define('FREE_DELIVERY_ABOVE', 499.00);  // order value that waives the delivery fee
define('DELIVERY_FEE',         39.00);
define('HANDLING_FEE',          9.00);  // small-basket handling charge, waived with delivery
define('MAX_QTY_PER_ITEM',       10);
define('PRODUCTS_PER_PAGE',      24);

// ------------------------------------------------------------------ runtime
// Turn this off on a public server — it prints database errors to the page.
define('DEBUG', getenv('MARKET_DEBUG') === '1');

date_default_timezone_set('Asia/Kolkata');

if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}
