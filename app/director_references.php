<?php
declare(strict_types=1);

function ad_store_director_reference(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Reference upload failed.');
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('Invalid reference upload.');
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > 100 * 1024 * 1024) throw new RuntimeException('Reference must be under 100 MB.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    $allowed = [
        'image/jpeg' => ['jpg','image'], 'image/png' => ['png','image'], 'image/webp' => ['webp','image'],
        'video/mp4' => ['mp4','video'], 'video/quicktime' => ['mov','video'],
        'audio/mpeg' => ['mp3','audio'], 'audio/wav' => ['wav','audio'], 'audio/x-wav' => ['wav','audio'],
        'audio/mp4' => ['m4a','audio'], 'audio/x-m4a' => ['m4a','audio'],
    ];
    if (!isset($allowed[$mime])) throw new RuntimeException('Use JPG, PNG, WebP, MP4, MOV, MP3, WAV, or M4A.');
    [$ext,$kind] = $allowed[$mime];
    if ($kind === 'image' && @getimagesize($tmp) === false) throw new RuntimeException('Reference image could not be validated.');
    $dir = AD_STORAGE_DIR . '/references';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Could not prepare reference storage.');
    $name = ad_id('reference') . '.' . $ext;
    $relative = 'storage/references/' . $name;
    $dest = AD_ROOT . '/' . $relative;
    if (!move_uploaded_file($tmp, $dest)) throw new RuntimeException('Could not save reference.');
    @chmod($dest, 0644);
    return [
        'id' => ad_id('ref'),
        'kind' => $kind,
        'path' => $relative,
        'url' => ad_public_media_url($relative),
        'mime' => $mime,
        'bytes' => filesize($dest) ?: $size,
        'original_name' => basename((string)($file['name'] ?? $name)),
        'created_at' => ad_now(),
    ];
}

function ad_shot_visual_reference(array $shot): ?array {
    $refs = is_array($shot['references'] ?? null) ? $shot['references'] : [];
    foreach (array_reverse($refs) as $ref) {
        if (is_array($ref) && ($ref['kind'] ?? '') === 'image' && !empty($ref['url'])) return $ref;
    }
    return null;
}
