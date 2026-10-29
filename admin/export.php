<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';

require_admin();

$status = isset(SIGNUP_STATUSES[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$role   = in_array($_GET['role'] ?? '', ROLES, true) ? $_GET['role'] : '';
$q      = clean_line($_GET['q'] ?? '', 100);

$where = [];
$args  = [];
if ($status !== '') { $where[] = 'status = ?'; $args[] = $status; }
if ($role !== '')   { $where[] = 'role = ?';   $args[] = $role; }
if ($q !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR location LIKE ? OR business_name LIKE ? OR guide_area LIKE ?)';
    array_push($args, ...array_fill(0, 6, '%' . $q . '%'));
}
$stmt = db()->prepare('SELECT * FROM signups' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id');
$stmt->execute($args);

/** Stops spreadsheet apps treating a cell as a formula. */
$cell = fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v;

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="oqevira-waitlist-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF"); // lets Excel read the file as UTF-8
fputcsv($out, ['Joined', 'Name', 'Email', 'Phone', 'Based in', 'Role', 'Guiding area', 'Guide languages', 'Business name',
               'Business type', 'Interests', 'Travel frequency', 'Heard via', 'Message', 'Status', 'Welcome email sent']);
while ($r = $stmt->fetch()) {
    fputcsv($out, array_map($cell, [$r['created_at'], $r['name'], $r['email'], $r['phone'], $r['location'], $r['role'],
        $r['guide_area'], $r['guide_languages'], $r['business_name'], $r['business_type'], $r['interests'], $r['frequency'],
        $r['heard'], $r['message'], SIGNUP_STATUSES[$r['status']] ?? $r['status'], $r['autoreply_sent'] ? 'Yes' : 'No']));
}
fclose($out);
