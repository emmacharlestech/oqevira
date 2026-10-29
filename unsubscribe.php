<?php
/**
 * Unsubscribe from Oqevira update emails (the link at the bottom of every update email).
 * GET shows a confirm button (so link scanners can't unsubscribe people by accident);
 * POST unsubscribes. Mail apps' one-click unsubscribe POSTs here directly.
 */
declare(strict_types=1);

require __DIR__ . '/backend/bootstrap.php';

header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$token = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$valid = $id > 0 && hash_equals(unsubscribe_token($id), $token);

$stmt = db()->prepare('SELECT id, name, email, unsubscribed FROM signups WHERE id = ?');
$stmt->execute([$id]);
$person = $valid ? $stmt->fetch() : false;

$state = !$person ? 'invalid' : ($person['unsubscribed'] ? 'done' : 'confirm');
if ($person && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    db()->prepare('UPDATE signups SET unsubscribed = 1 WHERE id = ?')->execute([$id]);
    $state = 'done';
}

$titles = [
    'confirm' => 'Stop update emails?',
    'done'    => 'You’re unsubscribed',
    'invalid' => 'This link doesn’t work',
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e($titles[$state]) ?> · Oqevira</title>
  <link rel="icon" href="assets/brand/oqevira_favicon_32.png" type="image/png">
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&display=swap" rel="stylesheet">
  <style>
    :root { --navy: #14213D; --coral: #FF5A4E; --muted: #646B7A; }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: #0D1730 radial-gradient(circle at 50% 120%, rgba(255, 90, 78, .35), transparent 55%); font-family: Manrope, system-ui, sans-serif; color: var(--navy); }
    .box { width: 100%; max-width: 440px; padding: 36px 28px; border-radius: 24px; background: #fff; text-align: center; box-shadow: 0 40px 80px -30px rgba(0, 0, 0, .6); }
    .box img { width: 150px; height: auto; margin: 0 auto 22px; display: block; }
    h1 { margin: 0 0 10px; font-size: 1.5rem; letter-spacing: -.02em; }
    p { margin: 0 0 18px; color: var(--muted); line-height: 1.6; }
    button, .link { display: inline-flex; align-items: center; justify-content: center; min-height: 50px; padding: 0 26px; border: 0; border-radius: 999px; background: var(--coral); color: var(--navy); font: 700 1rem Manrope, system-ui, sans-serif; text-decoration: none; cursor: pointer; }
    .link--ghost { background: none; color: var(--navy); text-decoration: underline; text-underline-offset: 4px; }
  </style>
</head>
<body>
  <main class="box">
    <img src="assets/brand/oqevira_logo_colour.svg" alt="oqevira" width="150" height="38">
    <h1><?= e($titles[$state]) ?></h1>
<?php if ($state === 'confirm'): ?>
    <p><?= e(first_name($person['name'])) ?>, you’ll stop getting Oqevira update emails at <strong><?= e($person['email']) ?></strong>. You’ll keep your place on the waitlist.</p>
    <form method="post">
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <input type="hidden" name="t" value="<?= e($token) ?>">
      <button type="submit">Unsubscribe</button>
    </form>
    <p style="margin:16px 0 0"><a class="link link--ghost" href="./">Keep me subscribed</a></p>
<?php elseif ($state === 'done'): ?>
    <p>We won’t send update emails to <strong><?= e($person['email']) ?></strong> any more, and you keep your place on the waitlist. Changed your mind? Email <a href="mailto:support@oqevira.com">support@oqevira.com</a>.</p>
    <a class="link" href="./">Back to oqevira.com</a>
<?php else: ?>
    <p>The unsubscribe link is incomplete or has expired. To stop update emails, reply to any Oqevira email or write to <a href="mailto:support@oqevira.com">support@oqevira.com</a>.</p>
    <a class="link" href="./">Back to oqevira.com</a>
<?php endif; ?>
  </main>
</body>
</html>
