<?php
// Sound-Upload: Musik für den Beamer (MP3, OGG, M4A, WAV)

const SOUND_NAME_PATTERN = '/^[a-f0-9]{24}\.(mp3|ogg|m4a|wav)$/';

function sound_max_bytes(): int
{
    return max(1, (int)cfg('max_sound_mb', 10)) * 1024 * 1024;
}

function is_valid_sound_name($name): bool
{
    return is_string($name) && preg_match(SOUND_NAME_PATTERN, $name) && is_file(upload_dir() . '/' . $name);
}

/** Systemweite Standard-Musik oder null */
function default_music(): ?string
{
    $f = setting_get('default_music');
    return $f && is_valid_sound_name($f) ? $f : null;
}

/** Musik für ein Spiel: eigene des Quiz, sonst Standard-Musik */
function game_music(array $game): ?string
{
    $snapshot = json_decode($game['quiz_snapshot'], true);
    $own = $snapshot['music'] ?? null;
    if ($own && is_valid_sound_name($own)) {
        return $own;
    }
    return default_music();
}

/** Erkennt das Audioformat an den ersten Bytes der Datei (nicht an Name oder Browser-Angabe) */
function sound_detect_type(string $path): ?string
{
    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return null;
    }
    $head = (string)fread($fh, 16);
    fclose($fh);
    if (strlen($head) < 12) {
        return null;
    }
    if (str_starts_with($head, 'ID3')) {
        return 'mp3';
    }
    $b0 = ord($head[0]);
    $b1 = ord($head[1]);
    if ($b0 === 0xFF && ($b1 & 0xE0) === 0xE0 && ($b1 & 0x06) !== 0) {
        return 'mp3'; // MPEG-Audio-Frame ohne ID3-Tag
    }
    if (str_starts_with($head, 'OggS')) {
        return 'ogg';
    }
    if (str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WAVE') {
        return 'wav';
    }
    if (substr($head, 4, 4) === 'ftyp') {
        $brand = substr($head, 8, 4);
        if (in_array($brand, ['M4A ', 'M4B ', 'mp42', 'isom', 'iso2', 'dash', 'mp41'], true)) {
            return 'm4a';
        }
    }
    return null;
}

function php_upload_limit(): string
{
    $vals = array_filter([ini_get('upload_max_filesize'), ini_get('post_max_size')]);
    $bytes = array_map(function ($v) {
        $n = (float)$v;
        return match (strtoupper(substr(trim($v), -1))) { 'G' => $n * 1073741824, 'M' => $n * 1048576, 'K' => $n * 1024, default => $n };
    }, $vals);
    return $bytes ? round(min($bytes) / 1048576, 1) . ' MB' : '?';
}

/**
 * Speichert eine hochgeladene Sounddatei unverändert unter zufälligem Namen.
 * @return array{file?:string,error?:string}
 */
function store_uploaded_sound(array $file): array
{
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err !== UPLOAD_ERR_OK) {
        if (in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return ['error' => t('Die Datei ist größer als das Upload-Limit des Servers ({limit}).', ['limit' => php_upload_limit()])];
        }
        return ['error' => t('Die Sounddatei konnte nicht hochgeladen werden.')];
    }
    if ($file['size'] > sound_max_bytes()) {
        return ['error' => t('Die Sounddatei darf höchstens {n} MB groß sein.', ['n' => (int)cfg('max_sound_mb', 10)])];
    }
    $type = sound_detect_type($file['tmp_name']);
    if ($type === null) {
        return ['error' => t('Erlaubt sind MP3, OGG, M4A und WAV.')];
    }
    $name = bin2hex(random_bytes(12)) . '.' . $type;
    $target = upload_dir() . '/' . $name;
    if (!@move_uploaded_file($file['tmp_name'], $target)) {
        return ['error' => t('Die Sounddatei konnte nicht gespeichert werden. Ist der Ordner uploads beschreibbar?')];
    }
    @chmod($target, 0644);
    return ['file' => $name];
}
