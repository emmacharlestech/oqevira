<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/images.php';
require __DIR__ . '/../backend/layout.php';

$admin = require_admin();
$pdo = db();

$errors = [];
$new = ['title' => '', 'subtitle' => '', 'alt' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM gallery WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch() ?: null;

    if ($action === 'add') {
        $new = [
            'title'    => clean_line($_POST['title'] ?? '', 60),
            'subtitle' => clean_line($_POST['subtitle'] ?? '', 60),
            'alt'      => clean_line($_POST['alt'] ?? '', 200),
        ];
        if (mb_strlen($new['title']) < 2) $errors['title'] = 'Please add a place name, e.g. “Zanzibar”.';
        if (!has_upload('image')) $errors['image'] = 'Please choose a photo.';
        if (!$errors) {
            try {
                $path = save_image_upload('image');
                $pos = (int) $pdo->query('SELECT COALESCE(MIN(position), 1) FROM gallery')->fetchColumn() - 1;
                $pdo->prepare('INSERT INTO gallery (title, subtitle, image, alt, position, created_at) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$new['title'], $new['subtitle'], $path, $new['alt'] ?: $new['title'], $pos, now()]);
                flash('Photo added to the start of the destination strip.');
                redirect('gallery');
            } catch (RuntimeException $ex) {
                $errors['image'] = $ex->getMessage();
            }
        }
    } elseif ($row && $action === 'edit') {
        $title = clean_line($_POST['title'] ?? '', 60);
        if (mb_strlen($title) < 2) {
            flash('Please keep a place name on every photo.', 'error');
            redirect('gallery#g-' . $id);
        }
        $image = $row['image'];
        if (has_upload('image')) {
            try {
                $image = save_image_upload('image');
                delete_upload($row['image']);
            } catch (RuntimeException $ex) {
                flash($ex->getMessage(), 'error');
                redirect('gallery#g-' . $id);
            }
        }
        $pdo->prepare('UPDATE gallery SET title = ?, subtitle = ?, alt = ?, image = ? WHERE id = ?')
            ->execute([$title, clean_line($_POST['subtitle'] ?? '', 60), clean_line($_POST['alt'] ?? '', 200) ?: $title, $image, $id]);
        flash('Photo details saved.');
        redirect('gallery#g-' . $id);
    } elseif ($row && in_array($action, ['up', 'down'], true)) {
        $ids = $pdo->query('SELECT id FROM gallery ORDER BY position, id')->fetchAll(PDO::FETCH_COLUMN);
        $i = array_search($id, array_map('intval', $ids), true);
        $j = $action === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            $upd = $pdo->prepare('UPDATE gallery SET position = ? WHERE id = ?');
            foreach ($ids as $p => $gid) $upd->execute([$p + 1, $gid]);
        }
        redirect('gallery#g-' . $id);
    } elseif ($row && $action === 'toggle') {
        $pdo->prepare('UPDATE gallery SET is_visible = 1 - is_visible WHERE id = ?')->execute([$id]);
        flash($row['is_visible'] ? '“' . $row['title'] . '” is hidden from the website.' : '“' . $row['title'] . '” is showing on the website.');
        redirect('gallery#g-' . $id);
    } elseif ($row && $action === 'delete') {
        $pdo->prepare('DELETE FROM gallery WHERE id = ?')->execute([$id]);
        delete_upload($row['image']);
        flash('Photo deleted.');
        redirect('gallery');
    } else {
        redirect('gallery');
    }
}

$items = $pdo->query('SELECT * FROM gallery ORDER BY position, id')->fetchAll();
$visible = count(array_filter($items, fn ($g) => $g['is_visible']));

admin_header('Gallery', $admin, 'gallery');
?>
    <div class="page__head">
      <div>
        <h1 class="page__title">Gallery</h1>
        <p class="page__sub">The photos in “Where will your story begin?” on the website. <strong><?= $visible ?></strong> showing.</p>
      </div>
    </div>

    <details class="card upload"<?= $errors ? ' open' : '' ?>>
      <summary class="upload__summary">+ Upload a new photo</summary>
<?php if ($errors): ?>
      <p class="flash flash--error" role="alert">Please fix the highlighted fields.</p>
<?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="form form--grid upload__form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="field<?= isset($errors['image']) ? ' is-invalid' : '' ?>">
          <label for="g-image">Photo</label>
          <input id="g-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" required data-preview="g-preview">
          <span class="field__help">JPG, PNG or WebP, up to 15 MB. Portrait (tall) photos fit the strip best.</span>
<?php if (isset($errors['image'])): ?><span class="field__error"><?= e($errors['image']) ?></span><?php endif; ?>
          <img id="g-preview" class="upload__preview" alt="" hidden>
        </div>
        <div class="stack">
          <label class="field<?= isset($errors['title']) ? ' is-invalid' : '' ?>">
            <span>Place</span>
            <input type="text" name="title" maxlength="60" value="<?= e($new['title']) ?>" placeholder="e.g. Zanzibar" required>
<?php if (isset($errors['title'])): ?><span class="field__error"><?= e($errors['title']) ?></span><?php endif; ?>
          </label>
          <label class="field">
            <span>Country or short line <small>(optional)</small></span>
            <input type="text" name="subtitle" maxlength="60" value="<?= e($new['subtitle']) ?>" placeholder="e.g. Tanzania">
          </label>
          <label class="field">
            <span>Describe the photo <small>(for screen readers, optional)</small></span>
            <input type="text" name="alt" maxlength="200" value="<?= e($new['alt']) ?>" placeholder="e.g. Dhow sailing past Stone Town at sunset">
          </label>
        </div>
        <div class="field--full form__actions">
          <button type="submit" class="btn btn--primary">Upload photo</button>
        </div>
      </form>
    </details>

<?php if (!$items): ?>
    <div class="empty">
      <p class="empty__title">No photos yet</p>
      <p class="hint">Upload a photo to start the destination strip.</p>
    </div>
<?php else: ?>
    <ul class="glist">
<?php foreach ($items as $n => $g): ?>
      <li class="gcard<?= $g['is_visible'] ? '' : ' is-hidden' ?>" id="g-<?= (int) $g['id'] ?>">
        <div class="gcard__thumb">
          <img src="<?= e(admin_img($g['image'])) ?>" alt="<?= e($g['alt']) ?>" loading="lazy">
          <span class="gcard__pos"><?= $n + 1 ?></span>
        </div>
        <div class="gcard__body">
          <p class="gcard__title"><?= e($g['title']) ?></p>
          <p class="gcard__meta"><?= e($g['subtitle'] ?: '—') ?><?php if (!$g['is_visible']): ?> <span class="badge badge--new">Hidden</span><?php endif; ?></p>
          <div class="gcard__actions">
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
              <button class="btn btn--icon" name="action" value="up" aria-label="Move <?= e($g['title']) ?> earlier"<?= $n === 0 ? ' disabled' : '' ?>>←</button>
              <button class="btn btn--icon" name="action" value="down" aria-label="Move <?= e($g['title']) ?> later"<?= $n === count($items) - 1 ? ' disabled' : '' ?>>→</button>
              <button class="btn btn--sm" name="action" value="toggle"><?= $g['is_visible'] ? 'Hide' : 'Show' ?></button>
            </form>
          </div>
          <details class="gcard__edit">
            <summary>Edit details</summary>
            <form method="post" enctype="multipart/form-data" class="form">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
              <label class="field"><span>Place</span><input type="text" name="title" maxlength="60" value="<?= e($g['title']) ?>" required></label>
              <label class="field"><span>Country or short line</span><input type="text" name="subtitle" maxlength="60" value="<?= e($g['subtitle']) ?>"></label>
              <label class="field"><span>Describe the photo</span><input type="text" name="alt" maxlength="200" value="<?= e($g['alt']) ?>"></label>
              <label class="field"><span>Replace photo <small>(optional)</small></span><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
              <button class="btn btn--primary btn--sm" name="action" value="edit">Save</button>
            </form>
            <form method="post" data-confirm="Delete the <?= e($g['title']) ?> photo? This can't be undone.">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
              <button class="btn btn--sm btn--danger" name="action" value="delete">Delete photo</button>
            </form>
          </details>
        </div>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php
admin_footer();
