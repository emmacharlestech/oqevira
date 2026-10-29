<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/layout.php';

$admin = require_admin();
$errors = [];
$name = $admin['name'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();
    $name    = clean_line($_POST['name'] ?? '', 80);
    $current = (string) ($_POST['current'] ?? '');
    $new     = (string) ($_POST['new'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    $stmt = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
    $stmt->execute([$admin['id']]);
    if (mb_strlen($name) < 2) $errors['name'] = 'Please enter your name.';
    if (!password_verify($current, (string) $stmt->fetchColumn())) $errors['current'] = 'Your current password is not correct.';
    if ($new !== '' && mb_strlen($new) < 10) $errors['new'] = 'Please use at least 10 characters.';
    if ($new !== $confirm) $errors['confirm'] = 'The two new passwords do not match.';

    if (!$errors) {
        if ($new !== '') {
            db()->prepare('UPDATE admins SET name = ?, password_hash = ? WHERE id = ?')->execute([$name, password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
            session_regenerate_id(true);
            flash('Your password was changed.');
        } else {
            db()->prepare('UPDATE admins SET name = ? WHERE id = ?')->execute([$name, $admin['id']]);
            flash('Your details were saved.');
        }
        redirect('account');
    }
}

admin_header('Account', $admin, 'account');
$err = fn (string $k) => isset($errors[$k]) ? '<span class="field__error">' . e($errors[$k]) . '</span>' : '';
?>
    <div class="page__head">
      <div>
        <h1 class="page__title">Account</h1>
        <p class="page__sub">You log in as <strong><?= e($admin['email']) ?></strong>. Last login: <?= e(format_date($admin['last_login_at'])) ?>.</p>
      </div>
    </div>

    <section class="card card--narrow">
      <h2 class="card__title">Your details and password</h2>
      <form method="post" class="form" novalidate>
        <?= csrf_field() ?>
        <label class="field<?= isset($errors['name']) ? ' is-invalid' : '' ?>">
          <span>Your name</span>
          <input type="text" name="name" value="<?= e($name) ?>" autocomplete="name" required>
          <?= $err('name') ?>
        </label>
        <label class="field<?= isset($errors['new']) ? ' is-invalid' : '' ?>">
          <span>New password <small>(leave empty to keep the current one)</small></span>
          <input type="password" name="new" autocomplete="new-password" minlength="10">
          <?= $err('new') ?>
        </label>
        <label class="field<?= isset($errors['confirm']) ? ' is-invalid' : '' ?>">
          <span>Repeat the new password</span>
          <input type="password" name="confirm" autocomplete="new-password">
          <?= $err('confirm') ?>
        </label>
        <label class="field<?= isset($errors['current']) ? ' is-invalid' : '' ?>">
          <span>Current password <small>(to confirm it's you)</small></span>
          <input type="password" name="current" autocomplete="current-password" required>
          <?= $err('current') ?>
        </label>
        <button type="submit" class="btn btn--primary">Save</button>
      </form>
    </section>
<?php
admin_footer();
