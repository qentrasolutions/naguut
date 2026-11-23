<?php
// Bild-Upload: prüfen, verkleinern, ohne Metadaten neu speichern

const IMAGE_MAX_BYTES = 2 * 1024 * 1024;
const IMAGE_MAX_SIDE = 1280;
const IMAGE_NAME_PATTERN = '/^[a-f0-9]{24}\.(webp|jpg|png)$/';

function upload_dir(): string
{
    return PUBLIC_DIR . '/uploads';
}

function is_valid_image_name($name): bool
{
    return is_string($name) && preg_match(IMAGE_NAME_PATTERN, $name) && is_file(upload_dir() . '/' . $name);
}

/**
 * Speichert eine hochgeladene Datei als Bild.
 * @return array{file?:string,error?:string}
 */
function store_uploaded_image(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $tooBig = in_array($file['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        return ['error' => $tooBig ? t('Das Bild ist größer als 2 MB.') : t('Das Bild konnte nicht hochgeladen werden.')];
    }
    if ($file['size'] > IMAGE_MAX_BYTES) {
        return ['error' => t('Das Bild ist größer als 2 MB.')];
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return ['error' => t('Erlaubt sind JPG, PNG und WebP.')];
    }
    if (!function_exists('imagecreatefromstring')) {
        return ['error' => t('Auf dem Server fehlt die PHP-Erweiterung GD.')];
    }
    if ($info[0] * $info[1] > 40_000_000) {
        return ['error' => t('Das Bild hat zu viele Pixel.')];
    }
    $src = @imagecreatefromstring((string)file_get_contents($file['tmp_name']));
    if (!$src) {
        return ['error' => t('Das Bild konnte nicht gelesen werden.')];
    }

    // EXIF-Drehung bei Handyfotos berücksichtigen
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $rot = ['3' => 180, '6' => -90, '8' => 90][(string)($exif['Orientation'] ?? '')] ?? 0;
        if ($rot) {
            $src = imagerotate($src, $rot, 0);
        }
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, IMAGE_MAX_SIDE / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $base = bin2hex(random_bytes(12));
    if (function_exists('imagewebp')) {
        $name = $base . '.webp';
        $ok = imagewebp($dst, upload_dir() . '/' . $name, 82);
    } else {
        $name = $base . '.jpg';
        $ok = imagejpeg($dst, upload_dir() . '/' . $name, 85);
    }
    imagedestroy($src);
    imagedestroy($dst);
    if (!$ok) {
        return ['error' => t('Das Bild konnte nicht gespeichert werden. Ist der Ordner uploads beschreibbar?')];
    }
    @chmod(upload_dir() . '/' . $name, 0644);
    return ['file' => $name];
}
