<?php
/**
 * Public content for the home page: published updates and the destination gallery.
 * GET → { ok, updates: [{ id, title, body, image, thumb, date }], gallery: [{ title, subtitle, image, alt }] }
 * Image paths are relative to the site root (e.g. uploads/…, assets/img/…). Cards and the
 * gallery strip get the light thumbnail versions; `image` on an update is the full size for the reader.
 */
declare(strict_types=1);

require __DIR__ . '/../backend/images.php';

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
        'thumb' => $u['image'] !== '' ? thumb_path($u['image']) : '',
        'date'  => date('j F Y', strtotime($u['published_at'])),
    ], $updates),
    'gallery' => array_map(fn ($g) => ['image' => thumb_path($g['image'])] + $g, $gallery),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
