<?php
/* Safe handling of holiday-home photos.
 *
 * The file name, extension and MIME type sent by the browser are never trusted.
 * The real type is read from the bytes, the image is decoded and re-encoded (which drops
 * hidden payloads and EXIF data), and it is stored under a random name that we choose. */

const HOME_IMAGE_DIR = HHH_ROOT . '/assets/img/holiday-homes';

/**
 * @param array $file one entry of $_FILES
 * @return string the new file name (to store in the database)
 * @throws RuntimeException with a message that is safe to show to the admin
 */
function save_home_image(array $file): string
{
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('That photo is too large (limit 5 MB).');
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new RuntimeException('The photo did not upload. Please try again.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('That photo is too large (limit 5 MB).');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if ($ext === null) {
        throw new RuntimeException('Photos must be JPEG, PNG or WebP files.');
    }

    $info = @getimagesize($file['tmp_name']);
    if (!$info || $info[0] < 600 || $info[1] < 400) {
        throw new RuntimeException('The photo must be at least 600 × 400 pixels.');
    }
    if ($info[0] > 8000 || $info[1] > 8000 || $info[0] * $info[1] > 40_000_000) {
        throw new RuntimeException('The photo dimensions are too large.');
    }

    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    $dest = HOME_IMAGE_DIR . '/' . $name;

    if (function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
        if (!$img) {
            throw new RuntimeException('That file is not a valid image.');
        }
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w > 1800) {                                  // keep pages fast
            $scaled = imagescale($img, 1800, (int) round($h * 1800 / $w));
            if ($scaled) {
                $img = $scaled;
            }
        }
        $ok = match ($ext) {
            'jpg'  => imagejpeg($img, $dest, 86),
            'png'  => imagepng($img, $dest, 6),
            'webp' => function_exists('imagewebp') && imagewebp($img, $dest, 84),
        };
        if (!$ok) {
            throw new RuntimeException('The photo could not be processed.');
        }
    } elseif (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('The photo could not be saved.');
    }
    @chmod($dest, 0644);
    return $name;
}

/** Remove a photo that WE generated. Seed images and anything else are never touched. */
function delete_home_image(string $name): void
{
    if (preg_match('/^[a-f0-9]{24}\.(jpg|png|webp)$/', $name)) {
        $path = HOME_IMAGE_DIR . '/' . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
