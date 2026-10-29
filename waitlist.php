<?php
/* =========================================================
   Oqevira — waitlist handler
   Receives the waitlist form (multipart or urlencoded POST), validates it,
   appends it to a CSV kept outside the web root, and emails it to the
   support inbox. Always answers with JSON:
     { "ok": true }  or  { "ok": false, "error": "...", "field": "..." }
   ========================================================= */

declare(strict_types=1);

const TO_EMAIL   = 'support@oqevira.com';
const FROM_EMAIL = 'support@oqevira.com';
const ALLOWED_ORIGINS = ['https://oqevira.com', 'https://www.oqevira.com', 'https://oqevira.vercel.app'];
const RATE_LIMIT  = 5;     // sign-ups allowed per IP address...
const RATE_WINDOW = 3600;  // ...per this many seconds

const ROLES          = ['Traveller', 'Tour guide', 'Travel business'];
const BUSINESS_TYPES = ['Hotel', 'Holiday home or short let', 'Car hire', 'Restaurant', 'Events and tickets', 'Tours and activities', 'Other'];
const INTERESTS      = ['Flights', 'Hotels', 'Holiday homes', 'Car hire', 'Restaurants', 'Local guides', 'Event tickets'];
const FREQUENCIES    = ['Once a year or less', '2-3 times a year', '4 or more times a year', 'Mostly for work'];
const SOURCES        = ['Instagram', 'TikTok', 'YouTube', 'A friend or family member', 'Search engine', 'Other'];

// Sign-ups live next to public_html, never inside it.
$dataDir = dirname(__DIR__) . '/oqevira_waitlist';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$isLocal = (bool) preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin);
if (in_array($origin, ALLOWED_ORIGINS, true) || $isLocal) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $error, ?string $field = null, int $status = 422): void
{
    respond($status, array_filter(['ok' => false, 'error' => $error, 'field' => $field], fn ($v) => $v !== null));
}

/** One trimmed text value from the POST body, control characters removed. */
function field(string $key, int $max, bool $multiline = false): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = $multiline
        ? preg_replace('/[^\P{C}\n\t]/u', '', str_replace("\r\n", "\n", $value))
        : preg_replace('/\p{C}+/u', ' ', $value);
    return mb_substr(trim((string) $value), 0, $max);
}

/** Stops spreadsheet apps treating a cell as a formula. */
function csvCell(string $value): string
{
    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST');
    http_response_code(204);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail('Please use the waitlist form on oqevira.com.', null, 405);
}
if ($origin !== '' && !in_array($origin, ALLOWED_ORIGINS, true) && !$isLocal) {
    fail('Sign-ups are only accepted from oqevira.com.', null, 403);
}

// Spam traps: a filled hidden field, or a form sent within 2.5 seconds of loading.
// Bots get a normal-looking success so they don't retry.
$started = (int) ($_POST['started'] ?? 0);
if (field('website', 200) !== '' || ($started > 0 && (microtime(true) * 1000 - $started) < 2500)) {
    respond(200, ['ok' => true]);
}

/* ---------- Validate ---------- */
$name     = field('name', 100);
$email    = field('email', 160);
$phone    = field('phone', 25);
$location = field('location', 120);
$role     = field('role', 40);

if (mb_strlen($name) < 2)                              fail('Please enter your full name.', 'name');
if (!filter_var($email, FILTER_VALIDATE_EMAIL))        fail('Please enter a valid email address.', 'email');
$digits = strlen(preg_replace('/\D/', '', $phone));
if (!preg_match('/^[0-9+()\-.\s]+$/', $phone) || $digits < 7 || $digits > 15) {
    fail('Please enter a valid phone number, including the country code.', 'phone');
}
if (mb_strlen($location) < 2)                          fail('Please tell us your city and country.', 'location');
if (!in_array($role, ROLES, true))                     fail('Please choose how you’ll use Oqevira.', 'role');

$guideArea = $guideLanguages = $businessName = $businessType = '';
if ($role === 'Tour guide') {
    $guideArea      = field('guide_area', 120);
    $guideLanguages = field('guide_languages', 120);
    if (mb_strlen($guideArea) < 2) fail('Please tell us where you guide.', 'guide_area');
}
if ($role === 'Travel business') {
    $businessName = field('business_name', 120);
    $businessType = field('business_type', 60);
    if (mb_strlen($businessName) < 2)                       fail('Please enter your business name.', 'business_name');
    if (!in_array($businessType, BUSINESS_TYPES, true))     fail('Please choose your type of business.', 'business_type');
}

$interests = array_values(array_intersect(INTERESTS, (array) ($_POST['interests'] ?? [])));
$frequency = in_array($f = field('frequency', 60), FREQUENCIES, true) ? $f : '';
$heard     = in_array($h = field('heard', 60), SOURCES, true) ? $h : '';
$message   = field('message', 1000, true);

if (($_POST['consent'] ?? '') !== 'yes') {
    fail('Please tick the box so we can contact you about your place on the waitlist.', 'consent');
}

/* ---------- Rate limit ---------- */
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0750, true);
    @file_put_contents($dataDir . '/.htaccess', "Require all denied\n");
}
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = $dataDir . '/rate-' . hash('sha256', $ip) . '.json';
$now = time();
$hits = is_file($rateFile) ? (array) json_decode((string) file_get_contents($rateFile), true) : [];
$hits = array_values(array_filter($hits, fn ($t) => is_int($t) && $t > $now - RATE_WINDOW));
if (count($hits) >= RATE_LIMIT) {
    fail('You’ve already sent a few sign-ups. Please try again later, or email support@oqevira.com.', null, 429);
}
$hits[] = $now;
@file_put_contents($rateFile, json_encode($hits), LOCK_EX);

/* ---------- Save ---------- */
$submitted = gmdate('Y-m-d H:i:s') . ' UTC';
$row = [$submitted, $name, $email, $phone, $location, $role, $guideArea, $guideLanguages, $businessName, $businessType,
        implode(', ', $interests), $frequency, $heard, $message, $origin];

$csv = $dataDir . '/waitlist.csv';
$saved = false;
if ($fh = @fopen($csv, 'ab')) {
    if (flock($fh, LOCK_EX)) {
        if (filesize($csv) === 0) {
            fwrite($fh, "\xEF\xBB\xBF"); // lets Excel read the file as UTF-8
            fputcsv($fh, ['Submitted', 'Name', 'Email', 'Phone', 'Based in', 'Role', 'Guiding area', 'Guide languages',
                          'Business name', 'Business type', 'Interests', 'Travel frequency', 'Heard via', 'Message', 'Page']);
        }
        $saved = fputcsv($fh, array_map('csvCell', $row)) !== false;
        flock($fh, LOCK_UN);
    }
    fclose($fh);
}

/* ---------- Email the support inbox ---------- */
$lines = [
    'New Oqevira waitlist sign-up',
    '',
    'Name:            ' . $name,
    'Email:           ' . $email,
    'Phone:           ' . $phone,
    'Based in:        ' . $location,
    'Using Oqevira as: ' . $role,
];
if ($role === 'Tour guide') {
    $lines[] = 'Guides in:       ' . $guideArea;
    $lines[] = 'Languages:       ' . ($guideLanguages ?: '—');
}
if ($role === 'Travel business') {
    $lines[] = 'Business:        ' . $businessName;
    $lines[] = 'Business type:   ' . $businessType;
}
$lines[] = 'Excited about:   ' . ($interests ? implode(', ', $interests) : '—');
$lines[] = 'Travels:         ' . ($frequency ?: '—');
$lines[] = 'Heard via:       ' . ($heard ?: '—');
$lines[] = '';
$lines[] = 'Message:';
$lines[] = $message !== '' ? $message : '—';
$lines[] = '';
$lines[] = '---';
$lines[] = 'Submitted: ' . $submitted;
$lines[] = 'From page: ' . ($origin ?: 'unknown');
$lines[] = 'Reply to this email to answer ' . $name . ' directly.';

$subject = '=?UTF-8?B?' . base64_encode("New waitlist sign-up: $name ($role)") . '?=';
$headers = implode("\r\n", [
    'From: =?UTF-8?B?' . base64_encode('Oqevira Waitlist') . '?= <' . FROM_EMAIL . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-Mailer: Oqevira waitlist',
]);
$sent = @mail(TO_EMAIL, $subject, implode("\n", $lines), $headers, '-f' . FROM_EMAIL);
if (!$sent) {
    error_log('Oqevira waitlist: mail() failed for ' . $email);
}

if (!$saved && !$sent) {
    fail('We couldn’t save your details just now. Please try again, or email support@oqevira.com.', null, 500);
}
respond(200, ['ok' => true]);
