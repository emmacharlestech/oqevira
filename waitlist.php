<?php
/* =========================================================
   Oqevira — waitlist handler
   Receives the waitlist form (multipart or urlencoded POST), validates it,
   saves it to the database, emails the support inbox and sends the person
   a branded auto-reply. Always answers with JSON:
     { "ok": true, "already": false }  or  { "ok": false, "error": "...", "field": "..." }
   ========================================================= */

declare(strict_types=1);

require __DIR__ . '/backend/mailer.php';

const RATE_LIMIT  = 5;     // sign-ups allowed per IP address...
const RATE_WINDOW = 3600;  // ...per this many seconds

function fail(string $error, ?string $field = null, int $status = 422): void
{
    json_response($status, array_filter(['ok' => false, 'error' => $error, 'field' => $field], fn ($v) => $v !== null));
}

$origin = allow_cors();
$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST');
    http_response_code(204);
    exit;
}
if ($method !== 'POST') {
    fail('Please use the waitlist form on oqevira.com.', null, 405);
}
if ($origin !== '' && !in_array($origin, ALLOWED_ORIGINS, true) && !is_local_origin($origin)) {
    fail('Sign-ups are only accepted from oqevira.com.', null, 403);
}

// Spam traps: a filled hidden field, or a form sent within 2.5 seconds of loading.
// Bots get a normal-looking success so they don't retry.
$started = (int) ($_POST['started'] ?? 0);
if (clean_line($_POST['website'] ?? '') !== '' || ($started > 0 && (microtime(true) * 1000 - $started) < 2500)) {
    json_response(200, ['ok' => true]);
}

/* ---------- Validate ---------- */
$r = [
    'name'     => clean_line($_POST['name'] ?? '', 100),
    'email'    => strtolower(clean_line($_POST['email'] ?? '', 160)),
    'phone'    => clean_line($_POST['phone'] ?? '', 25),
    'location' => clean_line($_POST['location'] ?? '', 120),
    'role'     => clean_line($_POST['role'] ?? '', 40),
    'guide_area' => '', 'guide_languages' => '', 'business_name' => '', 'business_type' => '',
];

if (mb_strlen($r['name']) < 2)                         fail('Please enter your full name.', 'name');
if (!filter_var($r['email'], FILTER_VALIDATE_EMAIL))   fail('Please enter a valid email address.', 'email');
$digits = strlen(preg_replace('/\D/', '', $r['phone']));
if (!preg_match('/^[0-9+()\-.\s]+$/', $r['phone']) || $digits < 7 || $digits > 15) {
    fail('Please enter a valid phone number, including the country code.', 'phone');
}
if (mb_strlen($r['location']) < 2)                     fail('Please tell us your city and country.', 'location');
if (!in_array($r['role'], ROLES, true))                fail('Please choose how you’ll use Oqevira.', 'role');

if ($r['role'] === 'Tour guide') {
    $r['guide_area']      = clean_line($_POST['guide_area'] ?? '', 120);
    $r['guide_languages'] = clean_line($_POST['guide_languages'] ?? '', 120);
    if (mb_strlen($r['guide_area']) < 2) fail('Please tell us where you guide.', 'guide_area');
}
if ($r['role'] === 'Travel business') {
    $r['business_name'] = clean_line($_POST['business_name'] ?? '', 120);
    $r['business_type'] = clean_line($_POST['business_type'] ?? '', 60);
    if (mb_strlen($r['business_name']) < 2)                  fail('Please enter your business name.', 'business_name');
    if (!in_array($r['business_type'], BUSINESS_TYPES, true)) fail('Please choose your type of business.', 'business_type');
}

$r['interests'] = implode(', ', array_intersect(INTERESTS, (array) ($_POST['interests'] ?? [])));
$r['frequency'] = in_array($f = clean_line($_POST['frequency'] ?? '', 60), FREQUENCIES, true) ? $f : '';
$r['heard']     = in_array($h = clean_line($_POST['heard'] ?? '', 60), SOURCES, true) ? $h : '';
$r['message']   = clean_text($_POST['message'] ?? '', 1000);
$r['source']    = $origin;
$r['created_at'] = now();

if (($_POST['consent'] ?? '') !== 'yes') {
    fail('Please tick the box so we can contact you about your place on the waitlist.', 'consent');
}

try {
    /* ---------- Rate limit ---------- */
    if (recent_hits('signup', RATE_WINDOW)['ip'] >= RATE_LIMIT) {
        fail('You’ve already sent a few sign-ups. Please try again later, or email support@oqevira.com.', null, 429);
    }
    record_hit('signup');

    /* ---------- Already on the list? ---------- */
    $stmt = db()->prepare('SELECT id FROM signups WHERE email = ?');
    $stmt->execute([$r['email']]);
    if ($stmt->fetchColumn()) {
        json_response(200, ['ok' => true, 'already' => true]);
    }

    /* ---------- Save ---------- */
    $cols = array_keys($r);
    db()->prepare('INSERT INTO signups (' . implode(', ', $cols) . ') VALUES (' . rtrim(str_repeat('?, ', count($cols)), ', ') . ')')
        ->execute(array_values($r));
    $id = (int) db()->lastInsertId();
} catch (Throwable $ex) {
    error_log('Oqevira waitlist: ' . $ex->getMessage());
    fail('We couldn’t save your details just now. Please try again, or email support@oqevira.com.', null, 500);
}

/* ---------- Emails (a failure here never loses the sign-up) ---------- */
$notified = send_signup_notification($r);
$replied = get_settings()['autoreply_enabled'] === 'on' && send_autoreply($r);
db()->prepare('UPDATE signups SET notified = ?, autoreply_sent = ? WHERE id = ?')->execute([(int) $notified, (int) $replied, $id]);

json_response(200, ['ok' => true, 'already' => false]);
