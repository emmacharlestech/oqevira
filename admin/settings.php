<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/mailer.php';
require __DIR__ . '/../backend/layout.php';

$admin = require_admin();
$s = get_settings();
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();
    $s = [
        'notify_email'      => clean_line($_POST['notify_email'] ?? '', 300),
        'autoreply_enabled' => isset($_POST['autoreply_enabled']) ? 'on' : 'off',
        'autoreply_subject' => clean_line($_POST['autoreply_subject'] ?? '', 150),
        'autoreply_body'    => clean_text($_POST['autoreply_body'] ?? '', 6000),
    ];
    $listed = preg_split('/[\s,;]+/', $s['notify_email'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (!$listed || count(notify_addresses($s)) !== count($listed)) {
        $errors['notify_email'] = 'Please enter one or more valid email addresses, separated by commas.';
    }
    if (mb_strlen($s['autoreply_subject']) < 3) $errors['autoreply_subject'] = 'Please add a subject line.';
    if (mb_strlen($s['autoreply_body']) < 20) $errors['autoreply_body'] = 'Please write the welcome email.';

    if (!$errors) {
        save_settings($s);
        if (($_POST['action'] ?? '') === 'test') {
            $ok = send_autoreply(['name' => $admin['name'], 'email' => $admin['email']], $admin['email']);
            flash($ok ? 'Settings saved, and a test welcome email was sent to ' . $admin['email'] . '.' : 'Settings saved, but the server couldn’t send the test email.', $ok ? 'success' : 'error');
        } else {
            flash('Settings saved.');
        }
        redirect('settings');
    }
}

admin_header('Settings', $admin, 'settings');
$field = fn (string $k) => isset($errors[$k]) ? ' is-invalid' : '';
?>
    <div class="page__head">
      <div>
        <h1 class="page__title">Settings</h1>
        <p class="page__sub">Where sign-up alerts go, and the welcome email new sign-ups receive.</p>
      </div>
    </div>

<?php if ($errors): ?>
    <p class="flash flash--error" role="alert">Please fix the highlighted fields.</p>
<?php endif; ?>
    <form method="post" class="settings" novalidate>
      <?= csrf_field() ?>
      <section class="card">
        <h2 class="card__title">New sign-up alerts</h2>
        <label class="field<?= $field('notify_email') ?>">
          <span>Send each new sign-up to</span>
          <input type="text" name="notify_email" value="<?= e($s['notify_email']) ?>" inputmode="email" required>
          <span class="field__help">Separate several addresses with commas.</span>
<?php if (isset($errors['notify_email'])): ?><span class="field__error"><?= e($errors['notify_email']) ?></span><?php endif; ?>
        </label>
      </section>

      <section class="card">
        <h2 class="card__title">Welcome email (auto-reply)</h2>
        <label class="check check--box"><input type="checkbox" name="autoreply_enabled" value="on"<?= $s['autoreply_enabled'] === 'on' ? ' checked' : '' ?>> Send a welcome email to everyone who joins the waitlist</label>
        <div class="form">
          <label class="field<?= $field('autoreply_subject') ?>">
            <span>Subject</span>
            <input type="text" name="autoreply_subject" maxlength="150" value="<?= e($s['autoreply_subject']) ?>" required>
<?php if (isset($errors['autoreply_subject'])): ?><span class="field__error"><?= e($errors['autoreply_subject']) ?></span><?php endif; ?>
          </label>
          <label class="field<?= $field('autoreply_body') ?>">
            <span>Message</span>
            <textarea name="autoreply_body" rows="16" maxlength="6000" required><?= e($s['autoreply_body']) ?></textarea>
            <span class="field__help"><code>{first_name}</code> becomes the person's first name and <code>{name}</code> their full name. Leave an empty line between paragraphs. Links starting with https:// become clickable. The email is sent from support@oqevira.com with the Oqevira logo at the top, and replies come back to that inbox.</span>
<?php if (isset($errors['autoreply_body'])): ?><span class="field__error"><?= e($errors['autoreply_body']) ?></span><?php endif; ?>
          </label>
        </div>
      </section>

      <div class="settings__actions">
        <button type="submit" class="btn btn--primary" name="action" value="save">Save settings</button>
        <button type="submit" class="btn" name="action" value="test">Save and send me a test</button>
      </div>
    </form>
<?php
admin_footer();
