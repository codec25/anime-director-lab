<?php
declare(strict_types=1);

function ad_store_upload(array $file, string $bucket): array {
    $allowedBuckets = ['characters', 'performances'];
    if (!in_array($bucket, $allowedBuckets, true)) throw new InvalidArgumentException('Invalid upload bucket.');
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed.');
    $tmp = (string)($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) throw new RuntimeException('Invalid upload.');

    $size = (int)($file['size'] ?? 0);
    $max = $bucket === 'characters' ? 50 * 1024 * 1024 : 100 * 1024 * 1024;
    if ($size < 1 || $size > $max) throw new RuntimeException('File is too large for this test.');

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    $allowed = $bucket === 'characters'
        ? ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']
        : ['video/mp4' => 'mp4', 'video/quicktime' => 'mov'];
    if (!isset($allowed[$mime])) throw new RuntimeException($bucket === 'characters' ? 'Use JPG, PNG, or WebP.' : 'Use MP4 or MOV.');

    if ($bucket === 'characters' && @getimagesize($tmp) === false) throw new RuntimeException('The image could not be validated.');

    $name = ad_id($bucket === 'characters' ? 'character' : 'performance') . '.' . $allowed[$mime];
    $relative = 'storage/' . $bucket . '/' . $name;
    $dest = AD_ROOT . '/' . $relative;
    if (!move_uploaded_file($tmp, $dest)) throw new RuntimeException('Could not save upload.');
    @chmod($dest, 0644);
    return [
        'path' => $relative,
        'url' => ad_public_media_url($relative),
        'mime' => $mime,
        'bytes' => filesize($dest) ?: $size,
        'original_name' => basename((string)($file['name'] ?? $name)),
    ];
}

function ad_download_result(string $url, string $jobId): ?array {
    if (!preg_match('#^https://#i', $url)) return null;
    $destName = preg_replace('/[^A-Za-z0-9_-]/', '_', $jobId) . '.mp4';
    $relative = 'storage/results/' . $destName;
    $dest = AD_ROOT . '/' . $relative;

    $ch = curl_init($url);
    $fh = fopen($dest, 'wb');
    if (!$ch || !$fh) return null;
    $written = 0;
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_USERAGENT => 'AnimeDirectorLab/0.01',
        CURLOPT_WRITEFUNCTION => static function($ch, string $data) use ($fh, &$written): int {
            $written += strlen($data);
            if ($written > 300 * 1024 * 1024) return 0;
            return fwrite($fh, $data) ?: 0;
        },
    ]);
    $ok = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch); fclose($fh);
    if (!$ok || $code < 200 || $code >= 300 || $written < 1024 || (!str_contains(strtolower($type), 'video') && !str_contains(strtolower($type), 'octet-stream'))) {
        @unlink($dest); return null;
    }
    return ['path' => $relative, 'url' => ad_public_media_url($relative), 'bytes' => $written, 'mime' => $type ?: 'video/mp4'];
}
