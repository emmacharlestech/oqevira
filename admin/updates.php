<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/images.php';
require __DIR__ . '/../backend/mailer.php';
require __DIR__ . '/../backend/layout.php';

// Emails sent per click. Shared hosting limits mail per hour, so big lists go out in rounds.
const EMAIL_BATCH = 80;

$admin = require_admin();
$pdo = db();

$find = function (int $id) use ($pdo): ?array {
    $stmt = $pdo->prepare('SELECT * FROM updates WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
};

/** Read and check the "Email it to" choice. Returns [audience, email, error]. */
$readAudience = function (bool $allowNone): array {
    $audience = (string) ($_POST['audience'] ?? 'none');
    $one = strtolower(clean_line($_POST['one_email'] ?? '', 160));
    if ($audience === 'none' && $allowNone) return ['none', '', ''];
    if (!isset(AUDIENCES[$audience]) && $audience !== 'one') return ['none', '', 'Please choose who should receive the email.'];
    if ($audience === 'one') {
        $stmt = db()->prepare('SELECT unsubscribed FROM signups WHERE email = ?');
        $stmt->execute([$one]);
        $u = $stmt->fetchColumn();
        if ($u === false) return ['one', $one, 'That email address isn’t on the waitlist.'];
        if ((int) $u === 1) return ['one', $one, 'That person has unsubscribed from update emails.'];
    }
    return [$audience, $one, ''];
};

/** Send an update to the chosen people who haven't had it yet. Returns [flash message, went ok]. */
$sendUpdate = function (array $update, string $audience, string $one) use ($pdo): array {
    @set_time_limit(300);
    $batch = update_recipients((int) $update['id'], $audience, $one, EMAIL_BATCH);
    if (!$batch) {
        return [$audience === 'one' ? 'That person has already received this update by email.' : 'Everyone in that group has already received this update by email.', true];
    }
    $log = $pdo->prepare('INSERT OR IGNORE INTO update_sends (update_id, signup_id, sent_at) VALUES (?, ?, ?)');
    $sent = $failed = 0;
    foreach ($batch as $person) {
        if (send_update_email($update, $person)) {
            $log->execute([$update['id'], $person['id'], now()]);
            $sent++;
        } else {
            $failed++;
        }
    }
    $left = count(update_recipients((int) $update['id'], $audience, $one));
    $msg = 'Emailed to ' . $sent . ($sent === 1 ? ' person' : ' people') . ($audience === 'one' ? ' (' . $batch[0]['email'] . ')' : ' (' . AUDIENCES[$audience] . ')') . '.';
    if ($failed) $msg .= " $failed couldn’t be sent; try again later.";
    if ($left && !$failed) $msg .= " $left more still to go: press “Send email” again in a few minutes to continue.";
    return [$msg, $sent > 0 || !$failed];
};

// People who can receive update emails, by group (unsubscribed people excluded).
$audienceCounts = ['all' => 0] + array_fill_keys(ROLES, 0);
foreach ($pdo->query('SELECT role, COUNT(*) FROM signups WHERE unsubscribed = 0 GROUP BY role')->fetchAll(PDO::FETCH_KEY_PAIR) as $r => $n) {
    $audienceCounts[$r] = (int) $n;
    $audienceCounts['all'] += (int) $n;
}
$waitlistEmails = $pdo->query('SELECT email, name FROM signups WHERE unsubscribed = 0 ORDER BY name')->fetchAll();

/** The "Email it to" radio group (Everyone / Travellers / … / One person). */
function audience_picker(string $prefix, array $counts, bool $withNone, string $selected = 'none', string $one = '', string $error = ''): void
{
    ?>
          <fieldset class="audience<?= $error ? ' is-invalid' : '' ?>">
            <legend><?= $withNone ? 'Also send it by email?' : 'Send it to' ?></legend>
<?php if ($withNone): ?>
            <label class="audience__opt"><input type="radio" name="audience" value="none"<?= $selected === 'none' ? ' checked' : '' ?>> <span>No, website only</span></label>
<?php endif; ?>
<?php foreach (AUDIENCES as $key => $label): ?>
            <label class="audience__opt"><input type="radio" name="audience" value="<?= e($key) ?>"<?= $selected === $key ? ' checked' : '' ?><?= $counts[$key] ? '' : ' disabled' ?>> <span><?= e($label) ?> <small><?= $counts[$key] ?></small></span></label>
<?php endforeach; ?>
            <label class="audience__opt"><input type="radio" name="audience" value="one"<?= $selected === 'one' ? ' checked' : '' ?><?= $counts['all'] ? '' : ' disabled' ?>> <span>One person</span></label>
            <label class="audience__one" data-audience-one>
              <span class="visually-hidden">Their email address</span>
              <input type="email" name="one_email" value="<?= e($one) ?>" list="<?= e($prefix) ?>-people" placeholder="Start typing a name or email">
            </label>
            <p class="field__help">Each person gets their own copy addressed to them by first name, with an unsubscribe link. People who already received this update are skipped.</p>
<?php if ($error): ?><p class="field__error"><?= e($error) ?></p><?php endif; ?>
          </fieldset>
<?php
}

$errors = [];
$form = ['id' => 0, 'title' => '', 'body' => '', 'image' => '', 'is_published' => 1];
$pick = ['none', ''];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $row = $id ? $find($id) : null;

    if ($action === 'save') {
        $form = [
            'id'           => $row ? $id : 0,
            'title'        => clean_line($_POST['title'] ?? '', 140),
            'body'         => clean_text($_POST['body'] ?? '', 8000),
            'image'        => $row['image'] ?? '',
            'is_published' => isset($_POST['is_published']) ? 1 : 0,
        ];
        if (mb_strlen($form['title']) < 3) $errors['title'] = 'Please give the update a title.';
        if (mb_strlen($form['body']) < 10) $errors['body'] = 'Please write a little more for the update.';
        [$aud, $one, $audErr] = $readAudience(true);
        $pick = [$aud, $one];
        if ($audErr) $errors['audience'] = $audErr;

        $newImage = '';
        if (!$errors && has_upload('image')) {
            try {
                $newImage = save_image_upload('image');
            } catch (RuntimeException $ex) {
                $errors['image'] = $ex->getMessage();
            }
        }

        if (!$errors) {
            if ($newImage !== '' || isset($_POST['remove_image'])) {
                delete_upload($form['image']);
                $form['image'] = $newImage;
            }
            // First publish sets the date shown on the website; later edits keep it.
            $publishedAt = $form['is_published'] ? (($row['published_at'] ?? '') ?: now()) : ($row['published_at'] ?? null);
            if ($row) {
                $pdo->prepare('UPDATE updates SET title = ?, body = ?, image = ?, is_published = ?, published_at = ?, updated_at = ? WHERE id = ?')
                    ->execute([$form['title'], $form['body'], $form['image'], $form['is_published'], $publishedAt, now(), $id]);
                $msg = 'Update saved.' . ($form['is_published'] ? ' It is live on the website.' : ' It is saved as a draft.');
            } else {
                $pdo->prepare('INSERT INTO updates (title, body, image, is_published, published_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$form['title'], $form['body'], $form['image'], $form['is_published'], $publishedAt, now(), now()]);
                $id = (int) $pdo->lastInsertId();
                $msg = $form['is_published'] ? 'Update posted. It is now live on the website.' : 'Draft saved. Publish it when you are ready.';
            }
            $sendOk = true;
            if ($aud !== 'none') {
                [$sendMsg, $sendOk] = $sendUpdate($find($id), $aud, $one);
                $msg .= ' ' . $sendMsg;
            }
            flash($msg, $sendOk ? 'success' : 'error');
            redirect('updates#u-' . $id);
        }
        if ($newImage !== '') delete_upload($newImage);
    } elseif ($row && $action === 'toggle') {
        $pub = $row['is_published'] ? 0 : 1;
        $pdo->prepare('UPDATE updates SET is_published = ?, published_at = COALESCE(published_at, ?), updated_at = ? WHERE id = ?')
            ->execute([$pub, now(), now(), $id]);
        flash($pub ? '“' . $row['title'] . '” is now live on the website.' : '“' . $row['title'] . '” is hidden from the website.');
        redirect('updates');
    } elseif ($row && $action === 'email') {
        [$aud, $one, $audErr] = $readAudience(false);
        [$sendMsg, $sendOk] = $audErr ? [$audErr, false] : $sendUpdate($row, $aud, $one);
        flash($sendMsg, $sendOk ? 'success' : 'error');
        redirect('updates#u-' . $id);
    } elseif ($row && $action === 'delete') {
        $pdo->prepare('DELETE FROM updates WHERE id = ?')->execute([$id]);
        delete_upload($row['image']);
        flash('Update deleted.');
        redirect('updates');
    } else {
        redirect('updates');
    }
} elseif (($edit = (int) ($_GET['edit'] ?? 0)) && ($row = $find($edit))) {
    $form = $row;
}

$list = $pdo->query('SELECT * FROM updates ORDER BY COALESCE(published_at, created_at) DESC, id DESC')->fetchAll();
$sentCounts = $pdo->query('SELECT update_id, COUNT(*) FROM update_sends GROUP BY update_id')->fetchAll(PDO::FETCH_KEY_PAIR);
$editing = $form['id'] > 0;

admin_header('Updates', $admin, 'updates');
?>
    <div class="page__head">
      <div>
        <h1 class="page__title">Updates</h1>
        <p class="page__sub">News you post here appears in the “Latest from Oqevira” section of the website.</p>
      </div>
    </div>

    <section class="card upload-card" id="new" aria-labelledby="form-h">
      <h2 id="form-h" class="card__title"><?= $editing ? 'Edit update' : 'Post a new update' ?></h2>
<?php if ($errors): ?>
      <p class="flash flash--error" role="alert">Please fix the highlighted fields.</p>
<?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="form form--grid" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <label class="field field--full<?= isset($errors['title']) ? ' is-invalid' : '' ?>">
          <span>Title</span>
          <input type="text" name="title" maxlength="140" value="<?= e($form['title']) ?>" placeholder="e.g. Early access opens next month" required>
<?php if (isset($errors['title'])): ?><span class="field__error"><?= e($errors['title']) ?></span><?php endif; ?>
        </label>
        <label class="field field--full<?= isset($errors['body']) ? ' is-invalid' : '' ?>">
          <span>Update</span>
          <textarea name="body" rows="7" maxlength="8000" placeholder="Write your news. Leave an empty line between paragraphs." required><?= e($form['body']) ?></textarea>
<?php if (isset($errors['body'])): ?><span class="field__error"><?= e($errors['body']) ?></span><?php endif; ?>
          <span class="field__help">Links starting with https:// become clickable on the website.</span>
        </label>
        <div class="field<?= isset($errors['image']) ? ' is-invalid' : '' ?>">
          <label for="up-image"><?= $form['image'] !== '' ? 'Replace photo' : 'Photo' ?> <small>(optional)</small></label>
          <input id="up-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" data-preview="up-preview">
          <span class="field__help">JPG, PNG or WebP, up to 15 MB. Landscape photos look best.</span>
<?php if (isset($errors['image'])): ?><span class="field__error"><?= e($errors['image']) ?></span><?php endif; ?>
          <img id="up-preview" class="upload__preview" alt="" <?= $form['image'] !== '' ? 'src="' . e(admin_img($form['image'])) . '"' : 'hidden' ?>>
<?php if ($form['image'] !== ''): ?>
          <label class="check check--box"><input type="checkbox" name="remove_image" value="1"> Remove the photo</label>
<?php endif; ?>
        </div>
        <div class="field">
          <span>Visibility</span>
          <label class="check check--box"><input type="checkbox" name="is_published" value="1"<?= $form['is_published'] ? ' checked' : '' ?>> Show on the website</label>
          <span class="field__help">Untick to save it as a draft that only you can see. Drafts can still be emailed.</span>
        </div>
        <div class="field--full">
<?php audience_picker('waitlist', $audienceCounts, true, $pick[0], $pick[1], $errors['audience'] ?? ''); ?>
        </div>
        <div class="field--full form__actions">
          <button type="submit" class="btn btn--primary"><?= $editing ? 'Save changes' : 'Post update' ?></button>
<?php if ($editing): ?>
          <a class="btn" href="updates">Cancel</a>
<?php endif; ?>
        </div>
      </form>
    </section>

    <datalist id="waitlist-people">
<?php foreach ($waitlistEmails as $p): ?>
      <option value="<?= e($p['email']) ?>"><?= e($p['name']) ?></option>
<?php endforeach; ?>
    </datalist>

    <h2 class="section-title">All updates</h2>
<?php if (!$list): ?>
    <div class="empty">
      <p class="empty__title">Nothing posted yet</p>
      <p class="hint">Your first update will appear on the website as soon as you post it.</p>
    </div>
<?php else: ?>
    <ul class="ulist">
<?php foreach ($list as $u): ?>
      <li class="card ucard<?= $u['is_published'] ? '' : ' is-hidden' ?>" id="u-<?= (int) $u['id'] ?>">
<?php if ($u['image'] !== ''): ?>
        <img class="ucard__img" src="<?= e(admin_img(thumb_path($u['image']))) ?>" loading="lazy" alt="">
<?php endif; ?>
        <div class="ucard__body">
          <p class="ucard__meta">
            <span class="badge badge--<?= $u['is_published'] ? 'contacted' : 'new' ?>"><?= $u['is_published'] ? 'Live' : 'Draft' ?></span>
            <?= e(format_date($u['published_at'] ?: $u['created_at'], 'j M Y')) ?>
<?php if ($sent = (int) ($sentCounts[$u['id']] ?? 0)): ?>
            · <span class="ucard__sent">✉ Emailed to <?= $sent ?> <?= $sent === 1 ? 'person' : 'people' ?></span>
<?php endif; ?>
          </p>
          <h3 class="ucard__title"><?= e($u['title']) ?></h3>
          <p class="ucard__text"><?= e(mb_strimwidth($u['body'], 0, 220, '…')) ?></p>
          <div class="gcard__actions">
            <a class="btn btn--sm" href="updates?edit=<?= (int) $u['id'] ?>#new">Edit</a>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <button class="btn btn--sm" name="action" value="toggle"><?= $u['is_published'] ? 'Hide from website' : 'Publish' ?></button>
            </form>
            <form method="post" data-confirm="Delete “<?= e($u['title']) ?>”? This can't be undone.">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <button class="btn btn--sm btn--danger" name="action" value="delete">Delete</button>
            </form>
          </div>
          <details class="ucard__email">
            <summary>✉ Email this update</summary>
            <form method="post" class="form" data-confirm="Send “<?= e($u['title']) ?>” by email now?">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
<?php audience_picker('waitlist', $audienceCounts, false, 'all'); ?>
              <div><button class="btn btn--primary btn--sm" name="action" value="email">Send email</button></div>
            </form>
          </details>
        </div>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php
admin_footer();
