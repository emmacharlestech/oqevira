<?php
/**
 * Image uploads for updates and the gallery. Every upload is checked, then
 * re-saved (resized, metadata stripped) into /uploads under a random name.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const IMAGE_MAX_BYTES = 15 * 1024 * 1024;
const IMAGE_MAX_EDGE  = 2000;
const IMAGE_TYPES     = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

/** True when the form included a file in this field. */
function has_upload(string $field): bool
{
    return isset($_FILES[$field]) && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

/**
 * Validate and store the uploaded image in $field.
 * Returns its site-relative path (uploads/…) or throws with a message for the admin.
 */
function save_image_upload(string $field): string
{
    $f = $_FILES[$field] ?? null;
    if (!$f || is_array($f['error'])) {
        throw new RuntimeException('Please choose one image.');
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > IMAGE_MAX_BYTES) {
        throw new RuntimeException('That image is too large. Please use one under 15 MB.');
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        throw new RuntimeException('The upload didn’t finish. Please try again.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $info = @getimagesize($f['tmp_name']);
    if (!isset(IMAGE_TYPES[$mime]) || !$info) {
        throw new RuntimeException('Please upload a JPG, PNG or WebP photo.');
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    $base = date('Ymd') . '-' . bin2hex(random_bytes(6));

    // Without GD (some local setups) keep the checked original as it is.
    if (!function_exists('imagecreatetruecolor')) {
        $name = $base . '.' . IMAGE_TYPES[$mime];
        if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $name)) {
            throw new RuntimeException('Couldn’t save the image on the server.');
        }
        return 'uploads/' . $name;
    }

    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($f['tmp_name']),
        'image/png'  => @imagecreatefrompng($f['tmp_name']),
        'image/webp' => @imagecreatefromwebp($f['tmp_name']),
    };
    if (!$src) {
        throw new RuntimeException('That image file seems to be damaged. Please try another.');
    }

    // Phone photos are often stored sideways with an EXIF rotation flag.
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $o = (int) (@exif_read_data($f['tmp_name'])['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($angle) {
            $src = imagerotate($src, $angle, 0);
        }
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, IMAGE_MAX_EDGE / max($w, $h));
    $nw = (int) round($w * $scale);
    $nh = (int) round($h * $scale);
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $webp = function_exists('imagewebp');
    $name = $base . ($webp ? '.webp' : '.jpg');
    $ok = $webp ? imagewebp($dst, UPLOAD_DIR . '/' . $name, 82) : imagejpeg($dst, UPLOAD_DIR . '/' . $name, 84);
    if (!$ok) {
        throw new RuntimeException('Couldn’t save the image on the server.');
    }
    return 'uploads/' . $name;
}

/** Remove an uploaded file (never touches the built-in assets/ photos). */
function delete_upload(string $path): void
{
    if (preg_match('#^uploads/[\w.-]+$#', $path) && is_file(SITE_DIR . '/' . $path)) {
        @unlink(SITE_DIR . '/' . $path);
    }
}
