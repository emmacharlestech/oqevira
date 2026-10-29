<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/settings.php';
require __DIR__ . '/../backend/layout.php';

$admin = require_admin();
$pdo = db();
$num = function (string $sql, array $args = []) use ($pdo): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return (int) $stmt->fetchColumn();
};

$weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
$stats = [
    ['On the waitlist', $num('SELECT COUNT(*) FROM signups'), 'signups', 'in total'],
    ['New sign-ups', $num("SELECT COUNT(*) FROM signups WHERE status = 'new'"), 'signups?status=new', 'not contacted yet'],
    ['This week', $num('SELECT COUNT(*) FROM signups WHERE created_at >= ?', [$weekAgo]), 'signups', 'last 7 days'],
    ['Updates live', $num('SELECT COUNT(*) FROM updates WHERE is_published = 1'), 'updates', 'on the website'],
];

$roles = $pdo->query('SELECT role, COUNT(*) AS n FROM signups GROUP BY role')->fetchAll(PDO::FETCH_KEY_PAIR);
$total = array_sum($roles) ?: 1;
$interests = array_fill_keys(INTERESTS, 0);
foreach ($pdo->query("SELECT interests FROM signups WHERE interests <> ''")->fetchAll(PDO::FETCH_COLUMN) as $list) {
    foreach (explode(', ', $list) as $i) {
        if (isset($interests[$i])) $interests[$i]++;
    }
}
arsort($interests);
$topInterest = max($interests) ?: 1;

$latest = $pdo->query('SELECT id, name, role, location, status, created_at FROM signups ORDER BY id DESC LIMIT 6')->fetchAll();

$s = get_settings();
$todo = [];
if (!notify_addresses($s)) $todo[] = ['Add the email address that receives new sign-ups', 'settings'];
if ($s['autoreply_enabled'] !== 'on') $todo[] = ['Turn on the welcome email for new sign-ups', 'settings'];
if (!$num('SELECT COUNT(*) FROM updates WHERE is_published = 1')) $todo[] = ['Post your first update to the website', 'updates'];
if ($num("SELECT COUNT(*) FROM gallery WHERE image LIKE 'uploads/%'") === 0) $todo[] = ['Upload your own photos to the destination gallery', 'gallery'];

admin_header('Dashboard', $admin, 'dashboard');
?>
    <div class="page__head">
      <div>
        <h1 class="page__title">Welcome back, <?= e(first_name($admin['name'])) ?></h1>
        <p class="page__sub"><?= e(date('l, j F Y')) ?></p>
      </div>
      <a class="btn btn--primary" href="updates#new">Post an update</a>
    </div>

    <ul class="stats">
<?php foreach ($stats as [$label, $value, $href, $note]): ?>
      <li><a class="stat<?= $value && $label === 'New sign-ups' ? ' is-alert' : '' ?>" href="<?= e($href) ?>">
        <span class="stat__value"><?= $value ?></span>
        <span class="stat__label"><?= e($label) ?></span>
        <span class="stat__note"><?= e($note) ?></span>
      </a></li>
<?php endforeach; ?>
    </ul>

    <div class="dash">
      <section class="card" aria-labelledby="ls-h">
        <h2 id="ls-h" class="card__title">Latest sign-ups</h2>
<?php if (!$latest): ?>
        <p class="hint">Nobody yet. Sign-ups from the waitlist form on the website appear here.</p>
<?php else: ?>
        <ul class="minilist">
<?php foreach ($latest as $r): ?>
          <li><a href="signups#s-<?= (int) $r['id'] ?>">
            <span class="minilist__main"><strong><?= e($r['name']) ?></strong> · <?= e($r['role']) ?> · <?= e($r['location']) ?></span>
            <span class="minilist__side"><span class="badge badge--<?= e($r['status']) ?>"><?= e(SIGNUP_STATUSES[$r['status']] ?? $r['status']) ?></span> <?= e(format_date($r['created_at'], 'j M')) ?></span>
          </a></li>
<?php endforeach; ?>
        </ul>
        <p class="dash__more"><a href="signups">Everyone on the waitlist →</a></p>
<?php endif; ?>
      </section>

<?php if ($todo): ?>
      <section class="card" aria-labelledby="todo-h">
        <h2 id="todo-h" class="card__title">Next steps</h2>
        <ul class="todo">
<?php foreach ($todo as [$text, $href]): ?>
          <li><a href="<?= e($href) ?>"><?= e($text) ?> →</a></li>
<?php endforeach; ?>
        </ul>
      </section>
<?php endif; ?>

      <section class="card" aria-labelledby="who-h">
        <h2 id="who-h" class="card__title">Who's signing up</h2>
        <ul class="bars">
<?php foreach (ROLES as $role): $n = (int) ($roles[$role] ?? 0); ?>
          <li><span class="bars__label"><?= e($role) ?></span><span class="bars__track"><span style="width:<?= round($n / $total * 100) ?>%"></span></span><span class="bars__n"><?= $n ?></span></li>
<?php endforeach; ?>
        </ul>
      </section>

      <section class="card" aria-labelledby="int-h">
        <h2 id="int-h" class="card__title">What they're excited about</h2>
        <ul class="bars">
<?php foreach ($interests as $label => $n): ?>
          <li><span class="bars__label"><?= e($label) ?></span><span class="bars__track"><span style="width:<?= round($n / $topInterest * 100) ?>%"></span></span><span class="bars__n"><?= $n ?></span></li>
<?php endforeach; ?>
        </ul>
      </section>
    </div>
<?php
admin_footer();
