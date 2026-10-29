<?php
/**
 * Public content for the home page: published updates and the destination gallery.
 * GET → { ok, updates: [{ id, title, body, image, date }], gallery: [{ title, subtitle, image, alt }] }
 * Image paths are relative to the site root (e.g. uploads/…, assets/img/…).
 */
declare(strict_types=1);

require __DIR__ . '/../backend/bootstrap.php';

allow_cors();
header('Cache-Control: public, max-age=60');

try {
    $updates = db()->query('SELECT id, title, body, image, published_at FROM updates
        WHERE is_published = 1 ORDER BY published_at DESC, id DESC LIMIT 6')->fetchAll();
    $gallery = db()->query('SELECT title, subtitle, image, alt FROM gallery WHERE is_visible = 1 ORDER BY position, id')->fetchAll();
} catch (Throwable $ex) {
    error_log('Oqevira content API: ' . $ex->getMessage());
    json_response(500, ['ok' => false]);
}

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
echo json_encode([
    'ok'      => true,
    'updates' => array_map(fn ($u) => [
        'id'    => (int) $u['id'],
        'title' => $u['title'],
        'body'  => $u['body'],
        'image' => $u['image'],
        'date'  => date('j F Y', strtotime($u['published_at'])),
    ], $updates),
    'gallery' => $gallery,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
