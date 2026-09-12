<?php
/** Registration, login, session and access control. */

const REMEMBER_DAYS = 30;

function current_user(): ?array
{
    static $cached = null;
    static $loaded = false;

    if ($loaded) {
        return $cached;
    }
    $loaded = true;

    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        $cached = null;
        return null;
    }

    $cached = q1('SELECT * FROM users WHERE id = ? AND is_active = 1', [$id]);
    if ($cached === null) {
        unset($_SESSION['user_id']);          // account removed or disabled mid-session
    }
    return $cached;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

function user_id(): ?int
{
    $u = current_user();
    return $u ? (int) $u['id'] : null;
}

/** Send guests to the login page, remembering where they were headed. */
function require_login(?string $intended = null): void
{
    if (is_logged_in()) {
        return;
    }
    $intended = $intended ?: ($_SERVER['REQUEST_URI'] ?? 'index.php');
    redirect(BASE_URL . 'login.php?next=' . urlencode($intended));
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('<h1>403 — Admins only</h1><p>This area is restricted to store administrators.</p>');
    }
}

/**
 * Create an account. Returns [ok, errors[], userId].
 */
function register_user(string $name, string $email, string $phone, string $password, string $confirm): array
{
    $errors = [];
    $name   = trim($name);
    $email  = strtolower(trim($email));
    $phone  = preg_replace('/\D+/', '', $phone);

    if (mb_strlen($name) < 2)                            $errors['name']  = 'Tell us your name (at least 2 characters).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))      $errors['email'] = 'That does not look like a valid email address.';
    if ($phone !== '' && strlen($phone) !== 10)          $errors['phone'] = 'Enter a 10-digit mobile number, or leave it blank.';
    if (strlen($password) < 6)                           $errors['password'] = 'Use at least 6 characters for your password.';
    if ($password !== $confirm)                          $errors['confirm']  = 'The two passwords do not match.';

    if (!$errors && qv('SELECT id FROM users WHERE email = ?', [$email])) {
        $errors['email'] = 'An account with this email already exists. Try logging in instead.';
    }
    if ($errors) {
        return [false, $errors, null];
    }

    $palette = ['#0f8a3c', '#e26a2c', '#2d6cdf', '#8b46c9', '#c9184a', '#0d9488'];
    q(
        'INSERT INTO users (name, email, phone, password_hash, avatar_color) VALUES (?, ?, ?, ?, ?)',
        [$name, $email, $phone ?: null, password_hash($password, PASSWORD_DEFAULT), $palette[array_rand($palette)]]
    );

    return [true, [], (int) db()->lastInsertId()];
}

/**
 * Verify credentials and start the session. Returns [ok, error].
 */
function login_user(string $email, string $password, bool $remember = false): array
{
    $email = strtolower(trim($email));
    $user  = q1('SELECT * FROM users WHERE email = ?', [$email]);

    // Same message either way, so the form cannot be used to discover emails.
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return [false, 'Email or password is incorrect.'];
    }
    if (!$user['is_active']) {
        return [false, 'This account has been disabled. Please contact support.'];
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }

    start_session_for($user, $remember);
    return [true, null];
}

/** Regenerate the session id, adopt the user, and carry the guest cart over. */
function start_session_for(array $user, bool $remember = false): void
{
    $guestKey = session_id();
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);

    merge_guest_cart((int) $user['id'], $guestKey);

    if ($remember) {
        // Demo-grade "remember me": extend the session cookie lifetime.
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires'  => time() + REMEMBER_DAYS * 86400,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Initials for the avatar chip. */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = mb_substr($parts[0] ?? '?', 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}
