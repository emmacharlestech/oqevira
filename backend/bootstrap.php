<?php
/**
 * Shared setup for every backend script: config, SQLite database, helpers.
 */
declare(strict_types=1);

const BACKEND_DIR = __DIR__;
define('SITE_DIR', dirname(__DIR__));
define('UPLOAD_DIR', SITE_DIR . '/uploads');

$configFile = BACKEND_DIR . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'The site backend is not installed yet.']);
    exit;
}

/** @var array $CONFIG */
$CONFIG = require $configFile;

ini_set('display_errors', $CONFIG['debug'] ? '1' : '0');
error_reporting(E_ALL);
date_default_timezone_set('Africa/Lagos');

/** Sites allowed to post to the waitlist and read public content. */
const ALLOWED_ORIGINS = ['https://oqevira.com', 'https://www.oqevira.com', 'https://oqevira.vercel.app'];

const ROLES          = ['Traveller', 'Tour guide', 'Travel business'];
const BUSINESS_TYPES = ['Hotel', 'Holiday home or short let', 'Car hire', 'Restaurant', 'Events and tickets', 'Tours and activities', 'Other'];
const INTERESTS      = ['Flights', 'Hotels', 'Holiday homes', 'Car hire', 'Restaurants', 'Local guides', 'Event tickets'];
const FREQUENCIES    = ['Once a year or less', '2-3 times a year', '4 or more times a year', 'Mostly for work'];
const SOURCES        = ['Instagram', 'TikTok', 'YouTube', 'A friend or family member', 'Search engine', 'Other'];
const SIGNUP_STATUSES = ['new' => 'New', 'contacted' => 'Contacted'];

const SCHEMA_VERSION = 1;

/** Lazily-opened SQLite connection; creates and seeds the database on first use. */
function db(): PDO
{
    static $pdo = null;
    global $CONFIG;
    if ($pdo !== null) {
        return $pdo;
    }
    $dir = $CONFIG['data_dir'];
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    if (!is_file($dir . '/.htaccess')) {
        file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    $pdo = new PDO('sqlite:' . $dir . '/oqevira.sqlite', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA foreign_keys = ON');
    if ((int) $pdo->query('PRAGMA user_version')->fetchColumn() < SCHEMA_VERSION) {
        migrate($pdo);
    }
    return $pdo;
}

function migrate(PDO $pdo): void
{
    global $CONFIG;
    $pdo->beginTransaction();
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY,
            email TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            password_hash TEXT NOT NULL,
            last_login_at TEXT
        );
        CREATE TABLE IF NOT EXISTS signups (
            id INTEGER PRIMARY KEY,
            created_at TEXT NOT NULL,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE COLLATE NOCASE,
            phone TEXT NOT NULL,
            location TEXT NOT NULL,
            role TEXT NOT NULL,
            guide_area TEXT NOT NULL DEFAULT '',
            guide_languages TEXT NOT NULL DEFAULT '',
            business_name TEXT NOT NULL DEFAULT '',
            business_type TEXT NOT NULL DEFAULT '',
            interests TEXT NOT NULL DEFAULT '',
            frequency TEXT NOT NULL DEFAULT '',
            heard TEXT NOT NULL DEFAULT '',
            message TEXT NOT NULL DEFAULT '',
            source TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'new',
            notified INTEGER NOT NULL DEFAULT 0,
            autoreply_sent INTEGER NOT NULL DEFAULT 0
        );
        CREATE INDEX IF NOT EXISTS signups_created ON signups (created_at);
        CREATE TABLE IF NOT EXISTS updates (
            id INTEGER PRIMARY KEY,
            title TEXT NOT NULL,
            body TEXT NOT NULL,
            image TEXT NOT NULL DEFAULT '',
            is_published INTEGER NOT NULL DEFAULT 0,
            published_at TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS gallery (
            id INTEGER PRIMARY KEY,
            title TEXT NOT NULL,
            subtitle TEXT NOT NULL DEFAULT '',
            image TEXT NOT NULL,
            alt TEXT NOT NULL DEFAULT '',
            position INTEGER NOT NULL DEFAULT 0,
            is_visible INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS hits (
            ip_hash TEXT NOT NULL,
            action TEXT NOT NULL,
            email TEXT NOT NULL DEFAULT '',
            at INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS hits_lookup ON hits (action, at);
    SQL);

    $a = $CONFIG['admin'] ?? [];
    if (!empty($a['password_hash']) && !(int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn()) {
        $pdo->prepare('INSERT INTO admins (email, name, password_hash) VALUES (?, ?, ?)')
            ->execute([strtolower($a['email']), $a['name'], $a['password_hash']]);
    }

    // The destination strip on the home page starts with the photos the site launched with.
    if (!(int) $pdo->query('SELECT COUNT(*) FROM gallery')->fetchColumn()) {
        $seed = [
            ['Venice', 'Italy', 'assets/img/venice.jpg', 'Gondola passing the Rialto Bridge in Venice'],
            ['Giza', 'Egypt', 'assets/img/giza.jpg', 'The pyramids of Giza rising from the desert'],
            ['Cappadocia', 'Türkiye', 'assets/img/cappadocia.jpg', "Hot-air balloons drifting over Cappadocia's valleys"],
            ['Paris', 'France', 'assets/img/paris.jpg', 'The Eiffel Tower and the Seine at dusk'],
            ['The savannah', 'Safari country', 'assets/img/safari.jpg', 'Safari vehicle on the savannah at sunset'],
            ['Bali', 'Indonesia', 'assets/img/bali-temple.jpg', 'Lakeside temple with a tiered roof in Bali'],
            ['Old-town lanes', 'Wander slowly', 'assets/img/old-town.jpg', 'Traveller with a backpack walking a cobbled old-town lane'],
        ];
        $ins = $pdo->prepare('INSERT INTO gallery (title, subtitle, image, alt, position, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($seed as $i => [$t, $s, $img, $alt]) {
            $ins->execute([$t, $s, $img, $alt, $i + 1, now()]);
        }
    }

    $pdo->exec('PRAGMA user_version = ' . SCHEMA_VERSION);
    $pdo->commit();
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function json_response(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Echo the CORS header for our own sites (and localhost while developing). */
function allow_cors(): string
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, ALLOWED_ORIGINS, true) || is_local_origin($origin)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    return $origin;
}

function is_local_origin(string $origin): bool
{
    return (bool) preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin);
}

/** Escape for HTML output. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Trim, strip control characters (incl. CR/LF: blocks header injection), cap length. */
function clean_line(mixed $value, int $max = 200): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    $value = preg_replace('/\p{C}+/u', ' ', $value) ?? '';
    return mb_substr(trim($value), 0, $max);
}

/** Like clean_line but keeps line breaks (for message bodies). */
function clean_text(mixed $value, int $max = 5000): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = preg_replace('/[^\P{C}\n\t]+/u', ' ', $value) ?? '';
    return mb_substr(trim($value), 0, $max);
}

/** Salted hash of the visitor's IP: lets us rate-limit without storing raw IPs. */
function visitor_hash(): string
{
    global $CONFIG;
    return hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $CONFIG['app_secret']);
}

/** Count this visitor's recent hits for an action. */
function recent_hits(string $action, int $seconds, string $email = ''): array
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(ip_hash = ?), 0) AS by_ip, COALESCE(SUM(email = ? AND email <> \'\'), 0) AS by_email
        FROM hits WHERE action = ? AND at > ?');
    $stmt->execute([visitor_hash(), $email, $action, time() - $seconds]);
    $r = $stmt->fetch();
    return ['ip' => (int) $r['by_ip'], 'email' => (int) $r['by_email']];
}

function record_hit(string $action, string $email = ''): void
{
    db()->prepare('INSERT INTO hits (ip_hash, action, email, at) VALUES (?, ?, ?, ?)')
        ->execute([visitor_hash(), $action, $email, time()]);
    db()->prepare('DELETE FROM hits WHERE at < ?')->execute([time() - 86400]);
}

/** Local numbers (0803…) → international digits (234803…) for WhatsApp links. */
function whatsapp_digits(string $phone): string
{
    $d = preg_replace('/\D/', '', $phone) ?? '';
    if (strlen($d) === 11 && $d[0] === '0') {
        $d = '234' . substr($d, 1);
    }
    return $d;
}

function format_date(?string $value, string $format = 'j M Y, g:ia'): string
{
    return $value ? date($format, strtotime($value)) : '—';
}

function first_name(string $name): string
{
    return explode(' ', trim($name))[0] ?: $name;
}
