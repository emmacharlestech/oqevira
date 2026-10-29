<?php
/**
 * Admin sessions, login checks, CSRF protection.
 * Included by every page in admin/.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const ADMIN_IDLE_SECONDS  = 2 * 60 * 60;   // log out after 2 hours of inactivity
const LOGIN_WINDOW        = 15 * 60;
const LOGIN_MAX_PER_IP    = 10;
const LOGIN_MAX_PER_EMAIL = 5;

/** Security headers for every admin response. */
function admin_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' https://fonts.googleapis.com; style-src-attr 'unsafe-inline'; font-src https://fonts.gstatic.com; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
}

function start_admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('oq_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

/** The logged-in admin, or null. Enforces the idle timeout. */
function current_admin(): ?array
{
    static $admin = false;
    if ($admin !== false) {
        return $admin;
    }
    start_admin_session();
    $admin = null;
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    if (time() - ($_SESSION['last_seen'] ?? 0) > ADMIN_IDLE_SECONDS) {
        logout_admin();
        return null;
    }
    $_SESSION['last_seen'] = time();
    $stmt = db()->prepare('SELECT id, email, name, last_login_at FROM admins WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch() ?: null;
    return $admin;
}

/** Redirect to the login page unless an admin is logged in. */
function require_admin(): array
{
    admin_headers();
    $admin = current_admin();
    if (!$admin) {
        $next = preg_replace('/\.php$/', '', basename($_SERVER['SCRIPT_NAME'])) . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
        redirect('login?next=' . urlencode($next));
    }
    return $admin;
}

function login_admin(array $admin): void
{
    start_admin_session();
    session_regenerate_id(true);           // new session id on login: blocks session fixation
    $_SESSION['admin_id']  = (int) $admin['id'];
    $_SESSION['last_seen'] = time();
    $_SESSION['csrf']      = bin2hex(random_bytes(32));
}

function logout_admin(): void
{
    start_admin_session();
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], '', $p['secure'], $p['httponly']);
    session_destroy();
}

/* ------------------------------------------------------------------ CSRF */
function csrf_token(): string
{
    start_admin_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Reject any POST without a valid CSRF token. */
function require_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Your session expired or the request was invalid. Please go back, refresh the page and try again.');
    }
}

/* ------------------------------------------------------- Flash + redirect */
function flash(?string $message = null, string $type = 'success'): ?array
{
    start_admin_session();
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $to): void
{
    header('Location: ' . $to, true, 303);
    exit;
}

/* ------------------------------------------------------- Login throttling */
function login_locked(string $email): bool
{
    $h = recent_hits('login', LOGIN_WINDOW, $email);
    return $h['ip'] >= LOGIN_MAX_PER_IP || $h['email'] >= LOGIN_MAX_PER_EMAIL;
}

function clear_failed_logins(string $email): void
{
    db()->prepare("DELETE FROM hits WHERE action = 'login' AND (ip_hash = ? OR email = ?)")->execute([visitor_hash(), $email]);
}
