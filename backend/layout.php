<?php
/**
 * Shared header/footer markup for admin pages.
 */
declare(strict_types=1);

const ADMIN_ASSET_VERSION = '2';

function admin_header(string $title, ?array $admin = null, string $active = ''): void
{
    $flash = $admin ? flash() : null;
    $nav = [
        'dashboard' => ['./', 'Dashboard'],
        'signups'   => ['signups', 'Waitlist'],
        'updates'   => ['updates', 'Updates'],
        'gallery'   => ['gallery', 'Gallery'],
        'settings'  => ['settings', 'Settings'],
        'account'   => ['account', 'Account'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($title) ?> · Oqevira Admin</title>
  <link rel="icon" href="../assets/brand/oqevira_favicon_32.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin.css?v=<?= ADMIN_ASSET_VERSION ?>">
</head>
<body class="<?= $admin ? 'is-auth' : 'is-guest' ?>">
<?php if ($admin): ?>
  <header class="topbar">
    <a href="./" class="topbar__brand">
      <img src="../assets/brand/oqevira_logo_reversed.svg" alt="oqevira" width="120" height="30">
      <em>Admin</em>
    </a>
    <button type="button" class="topbar__toggle" aria-expanded="false" aria-controls="admin-nav">
      <span class="topbar__toggle-bars" aria-hidden="true"></span>
      <span class="topbar__toggle-label">Menu</span>
    </button>
    <nav class="topbar__nav" id="admin-nav" aria-label="Admin">
<?php foreach ($nav as $key => [$href, $label]): ?>
      <a href="<?= e($href) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
<?php endforeach; ?>
      <a href="../" target="_blank" rel="noopener">View site ↗</a>
      <form method="post" action="logout" class="topbar__logout">
        <?= csrf_field() ?>
        <button type="submit">Log out</button>
      </form>
    </nav>
  </header>
<?php endif; ?>
  <main class="page">
<?php if ($flash): ?>
    <p class="flash flash--<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p>
<?php endif;
}

function admin_footer(): void
{
    ?>
  </main>
  <script src="admin.js?v=<?= ADMIN_ASSET_VERSION ?>" defer></script>
</body>
</html>
<?php
}

/** Site-relative image path → URL usable from inside /admin. */
function admin_img(string $path): string
{
    return '../' . ltrim($path, '/');
}
