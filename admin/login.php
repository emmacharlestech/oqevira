<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/layout.php';

admin_headers();
if (current_admin()) {
    redirect('./');
}

/** Only allow redirects back to our own admin pages. */
function safe_next(string $next): string
{
    if ($next === 'index' || $next === '') return './';
    return preg_match('/^(\.\/|[a-z-]+)(\?[\w=&%.-]*)?$/', $next) ? $next : './';
}

$error = '';
$email = '';
$next  = safe_next((string) ($_GET['next'] ?? $_POST['next'] ?? './'));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();
    $email    = strtolower(clean_line($_POST['email'] ?? '', 200));
    $password = (string) ($_POST['password'] ?? '');

    if (login_locked($email)) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $stmt = db()->prepare('SELECT * FROM admins WHERE email = ?');
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        // Always run password_verify so response time doesn't reveal whether the email exists
        $hash  = $admin['password_hash'] ?? '$2y$10$pV8etvopIVVujnqM09XECOiNFvOL0.Ehui4NZ7wAQpk19Ci2/MpPu';
        $valid = password_verify($password, $hash);
        if ($admin && $valid) {
            clear_failed_logins($email);
            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
            }
            db()->prepare('UPDATE admins SET last_login_at = ? WHERE id = ?')->execute([now(), $admin['id']]);
            login_admin($admin);
            redirect($next);
        }
        record_hit('login', $email);
        $error = 'That email and password combination is not correct.';
    }
}

admin_header('Log in');
?>
    <section class="login">
      <img class="login__logo" src="../assets/brand/oqevira_logo_reversed.svg" alt="oqevira" width="180" height="45">
      <h1 class="login__title">Admin login</h1>
<?php if ($error): ?>
      <p class="flash flash--error" role="alert"><?= e($error) ?></p>
<?php endif; ?>
      <form method="post" class="form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label class="field">
          <span>Email</span>
          <input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
        </label>
        <label class="field">
          <span>Password</span>
          <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit" class="btn btn--primary btn--block">Log in</button>
      </form>
      <p class="login__help">Forgotten your password? Ask your web developer to reset it.</p>
    </section>
<?php
admin_footer();
