<?php
/** Sign out. POST only, so a stray link cannot log anyone out. */

require_once __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    require_csrf();
    logout_user();
    session_start();
    flash('You have been signed out.');
}

redirect('index.php');
