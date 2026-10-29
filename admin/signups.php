<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/mailer.php';
require __DIR__ . '/../backend/layout.php';

$admin = require_admin();
$pdo = db();

/* ---------- Actions ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $back = 'signups' . (($q = (string) ($_POST['back'] ?? '')) !== '' && preg_match('/^\?[\w=&%.+-]*$/', $q) ? $q : '');
    $stmt = $pdo->prepare('SELECT * FROM signups WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('That sign-up no longer exists.', 'error');
        redirect($back);
    }
    switch ($_POST['action'] ?? '') {
        case 'status':
            $status = (string) ($_POST['status'] ?? '');
            if (isset(SIGNUP_STATUSES[$status])) {
                $pdo->prepare('UPDATE signups SET status = ? WHERE id = ?')->execute([$status, $id]);
                flash($row['name'] . ' marked as ' . strtolower(SIGNUP_STATUSES[$status]) . '.');
            }
            break;
        case 'resend':
            $ok = send_autoreply($row);
            if ($ok) $pdo->prepare('UPDATE signups SET autoreply_sent = 1 WHERE id = ?')->execute([$id]);
            flash($ok ? 'Welcome email sent again to ' . $row['email'] . '.' : 'The server couldn’t send the email. Please try again later.', $ok ? 'success' : 'error');
            break;
        case 'resubscribe':
            $pdo->prepare('UPDATE signups SET unsubscribed = 0 WHERE id = ?')->execute([$id]);
            flash($row['name'] . ' will receive update emails again.');
            break;
        case 'delete':
            $pdo->prepare('DELETE FROM signups WHERE id = ?')->execute([$id]);
            flash($row['name'] . ' was removed from the waitlist.');
            break;
    }
    redirect($back . '#s-' . $id);
}

/* ---------- Filters ---------- */
$status = isset(SIGNUP_STATUSES[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$role   = in_array($_GET['role'] ?? '', ROLES, true) ? $_GET['role'] : '';
$q      = clean_line($_GET['q'] ?? '', 100);
$page   = max(1, (int) ($_GET['page'] ?? 1));
$per    = 30;

$where = [];
$args  = [];
if ($status !== '') { $where[] = 'status = ?'; $args[] = $status; }
if ($role !== '')   { $where[] = 'role = ?';   $args[] = $role; }
if ($q !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR location LIKE ? OR business_name LIKE ? OR guide_area LIKE ?)';
    array_push($args, ...array_fill(0, 6, '%' . $q . '%'));
}
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$count = $pdo->prepare('SELECT COUNT(*) FROM signups' . $sqlWhere);
$count->execute($args);
$found = (int) $count->fetchColumn();
$pages = max(1, (int) ceil($found / $per));
$page  = min($page, $pages);

$stmt = $pdo->prepare('SELECT * FROM signups' . $sqlWhere . ' ORDER BY id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per));
$stmt->execute($args);
$rows = $stmt->fetchAll();

$tabCounts = $pdo->query('SELECT status, COUNT(*) FROM signups GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$all = array_sum($tabCounts);

$qs = fn (array $over) => '?' . http_build_query(array_filter(array_merge(['status' => $status, 'role' => $role, 'q' => $q], $over), fn ($v) => $v !== '' && $v !== 1 && $v !== null));
$back = $qs(['page' => $page > 1 ? $page : null]);

admin_header('Waitlist', $admin, 'signups');
?>
    <div class="page__head">
      <div>
        <h1 class="page__title">Waitlist</h1>
        <p class="page__sub"><strong><?= $all ?></strong> <?= $all === 1 ? 'person has' : 'people have' ?> joined so far.</p>
      </div>
      <form class="search" method="get" role="search">
        <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <label class="visually-hidden" for="q">Search the waitlist</label>
        <input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Name, email, phone or city">
        <select name="role" aria-label="Filter by role">
          <option value="">Everyone</option>
<?php foreach (ROLES as $r): ?>
          <option<?= $role === $r ? ' selected' : '' ?>><?= e($r) ?></option>
<?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Search</button>
      </form>
    </div>

    <nav class="tabs" aria-label="Filter by status">
      <a class="tabs__tab" href="<?= e($qs(['status' => ''])) ?>"<?= $status === '' ? ' aria-current="page"' : '' ?>>All <span class="tabs__count"><?= $all ?></span></a>
<?php foreach (SIGNUP_STATUSES as $key => $label): ?>
      <a class="tabs__tab" href="<?= e($qs(['status' => $key])) ?>"<?= $status === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?> <span class="tabs__count"><?= (int) ($tabCounts[$key] ?? 0) ?></span></a>
<?php endforeach; ?>
    </nav>

    <p class="export-link"><a class="btn btn--sm" href="export<?= e($qs([])) ?>">Download as spreadsheet (CSV)</a></p>

<?php if (!$rows): ?>
    <div class="empty">
      <p class="empty__title"><?= $q !== '' || $role !== '' || $status !== '' ? 'No matches' : 'No sign-ups yet' ?></p>
      <p class="hint"><?= $q !== '' || $role !== '' || $status !== '' ? 'Try a different search or filter.' : 'When people join the waitlist on oqevira.com, they appear here.' ?></p>
    </div>
<?php else: ?>
    <ul class="inbox">
<?php foreach ($rows as $r):
      $wa = whatsapp_digits($r['phone']); ?>
      <li class="inbox__item<?= $r['status'] === 'new' ? ' is-new' : '' ?>" id="s-<?= (int) $r['id'] ?>">
        <details class="signup">
          <summary class="inbox__link">
            <span class="inbox__main">
              <span class="inbox__name"><?= e($r['name']) ?></span>
              <span class="inbox__meta"><?= e($r['role']) ?> · <?= e($r['location']) ?> · <?= e($r['email']) ?></span>
            </span>
            <span class="inbox__side">
              <span class="badge badge--<?= e($r['status']) ?>"><?= e(SIGNUP_STATUSES[$r['status']] ?? $r['status']) ?></span>
<?php if ($r['unsubscribed']): ?>
              <span class="badge badge--muted">Unsubscribed</span>
<?php endif; ?>
              <span class="inbox__date"><?= e(format_date($r['created_at'], 'j M Y, g:ia')) ?></span>
            </span>
          </summary>
          <div class="signup__body">
            <dl class="facts">
              <div><dt>Email</dt><dd><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></dd></div>
              <div><dt>Phone</dt><dd><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $r['phone'])) ?>"><?= e($r['phone']) ?></a></dd></div>
              <div><dt>Based in</dt><dd><?= e($r['location']) ?></dd></div>
              <div><dt>Joined as</dt><dd><?= e($r['role']) ?></dd></div>
<?php if ($r['role'] === 'Tour guide'): ?>
              <div><dt>Guides in</dt><dd><?= e($r['guide_area']) ?></dd></div>
              <div><dt>Languages</dt><dd><?= e($r['guide_languages'] ?: '—') ?></dd></div>
<?php elseif ($r['role'] === 'Travel business'): ?>
              <div><dt>Business</dt><dd><?= e($r['business_name']) ?></dd></div>
              <div><dt>Type</dt><dd><?= e($r['business_type']) ?></dd></div>
<?php endif; ?>
              <div><dt>Excited about</dt><dd><?= e($r['interests'] ?: '—') ?></dd></div>
              <div><dt>Travels</dt><dd><?= e($r['frequency'] ?: '—') ?></dd></div>
              <div><dt>Heard via</dt><dd><?= e($r['heard'] ?: '—') ?></dd></div>
              <div><dt>Emails</dt><dd><?= $r['autoreply_sent'] ? 'Welcome email sent' : 'Welcome email not sent' ?> · <?= $r['notified'] ? 'support inbox notified' : 'support inbox not notified' ?></dd></div>
              <div><dt>Update emails</dt><dd><?= $r['unsubscribed'] ? 'Unsubscribed. They won’t receive update emails.' : 'Subscribed' ?></dd></div>
            </dl>
<?php if ($r['message'] !== ''): ?>
            <h3 class="card__sub">Their message</h3>
            <p class="message"><?= nl2br(e($r['message'])) ?></p>
<?php endif; ?>
            <div class="signup__actions">
              <a class="btn btn--sm btn--primary" href="mailto:<?= e($r['email']) ?>?subject=<?= e(rawurlencode('Your place on the Oqevira waitlist')) ?>&amp;body=<?= e(rawurlencode('Hi ' . first_name($r['name']) . ",\n\n")) ?>">Email</a>
<?php if ($wa !== ''): ?>
              <a class="btn btn--sm" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode('Hi ' . first_name($r['name']) . ', this is the Oqevira team. ')) ?>" target="_blank" rel="noopener">WhatsApp</a>
<?php endif; ?>
              <a class="btn btn--sm" href="tel:<?= e(preg_replace('/[^\d+]/', '', $r['phone'])) ?>">Call</a>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="back" value="<?= e($back) ?>">
                <input type="hidden" name="action" value="status">
<?php if ($r['status'] === 'new'): ?>
                <button class="btn btn--sm" name="status" value="contacted">Mark as contacted</button>
<?php else: ?>
                <button class="btn btn--sm" name="status" value="new">Mark as new</button>
<?php endif; ?>
              </form>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="back" value="<?= e($back) ?>">
                <button class="btn btn--sm" name="action" value="resend">Resend welcome email</button>
              </form>
<?php if ($r['unsubscribed']): ?>
              <form method="post" data-confirm="Only do this if <?= e(first_name($r['name'])) ?> asked to get update emails again. Continue?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="back" value="<?= e($back) ?>">
                <button class="btn btn--sm" name="action" value="resubscribe">Resubscribe to updates</button>
              </form>
<?php endif; ?>
              <form method="post" data-confirm="Remove <?= e($r['name']) ?> from the waitlist? This can't be undone.">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="back" value="<?= e($back) ?>">
                <button class="btn btn--sm btn--danger" name="action" value="delete">Delete</button>
              </form>
            </div>
          </div>
        </details>
      </li>
<?php endforeach; ?>
    </ul>
<?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Pages">
<?php if ($page > 1): ?><a class="btn btn--sm" href="<?= e($qs(['page' => $page - 1])) ?>">← Newer</a><?php endif; ?>
      <span>Page <?= $page ?> of <?= $pages ?></span>
<?php if ($page < $pages): ?><a class="btn btn--sm" href="<?= e($qs(['page' => $page + 1])) ?>">Older →</a><?php endif; ?>
    </nav>
<?php endif; ?>
<?php endif; ?>
<?php
admin_footer();
