<?php
declare(strict_types=1);

function ad_safe_storage_relative(string $relativePath): string {
    $relativePath = str_replace('\\', '/', $relativePath);
    $relativePath = ltrim($relativePath, '/');
    if ($relativePath === '' || str_contains($relativePath, '..')) {
        throw new InvalidArgumentException('Invalid storage path.');
    }
    if (!str_starts_with($relativePath, 'storage/')) {
        throw new InvalidArgumentException('Media path must stay under storage/.');
    }
    return $relativePath;
}

function ad_store_upload(array $file, string $bucket, ?string $role = null): array {
    $allowedBuckets = ['characters', 'performances', 'sheets'];
    if (!in_array($bucket, $allowedBuckets, true)) throw new InvalidArgumentException('Invalid upload bucket.');
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed.');
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('Invalid upload.');

    $size = (int)($file['size'] ?? 0);
    $max = ($bucket === 'performances') ? 100 * 1024 * 1024 : 50 * 1024 * 1024;
    if ($size < 1 || $size > $max) throw new RuntimeException('File is too large for this test.');

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    if ($bucket === 'performances') {
        $allowed = ['video/mp4' => 'mp4', 'video/quicktime' => 'mov'];
        if (!isset($allowed[$mime])) throw new RuntimeException('Use MP4 or MOV.');
    } else {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) throw new RuntimeException('Use JPG, PNG, or WebP.');
        if (@getimagesize($tmp) === false) throw new RuntimeException('The image could not be validated.');
    }

    if ($role !== null && $bucket === 'characters') {
        if (!in_array($role, ad_character_reference_roles(), true)) {
            throw new InvalidArgumentException('Unknown character reference role.');
        }
    }

    $prefix = match ($bucket) {
        'characters' => 'character',
        'performances' => 'performance',
        'sheets' => 'sheet',
        default => 'asset',
    };
    $name = ad_id($prefix) . ($role ? ('_' . preg_replace('/[^a-z0-9_]/', '', $role)) : '') . '.' . $allowed[$mime];
    $targetBucket = $bucket === 'sheets' ? 'characters' : $bucket;
    $relative = ad_safe_storage_relative('storage/' . $targetBucket . '/' . $name);
    $dest = AD_ROOT . '/' . $relative;
    $dir = dirname($dest);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not prepare upload directory.');
    }
    if (!move_uploaded_file($tmp, $dest)) throw new RuntimeException('Could not save upload.');
    @chmod($dest, 0644);
    return [
        'path' => $relative,
        'url' => ad_public_media_url($relative),
        'mime' => $mime,
        'bytes' => filesize($dest) ?: $size,
        'original_name' => basename((string)($file['name'] ?? $name)),
        'role' => $role,
    ];
}

function ad_store_director_reference(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Reference upload failed.');
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('Invalid reference upload.');
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > 120 * 1024 * 1024) throw new RuntimeException('Reference file is too large.');

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    $allowed = [
        'image/jpeg' => ['jpg','image'], 'image/png' => ['png','image'], 'image/webp' => ['webp','image'],
        'video/mp4' => ['mp4','video'], 'video/quicktime' => ['mov','video'],
        'audio/mpeg' => ['mp3','audio'], 'audio/wav' => ['wav','audio'], 'audio/x-wav' => ['wav','audio'],
        'audio/mp4' => ['m4a','audio'], 'audio/aac' => ['aac','audio'],
    ];
    if (!isset($allowed[$mime])) throw new RuntimeException('Use JPG, PNG, WebP, MP4, MOV, MP3, WAV, M4A, or AAC.');
    [$ext, $kind] = $allowed[$mime];
    if ($kind === 'image' && @getimagesize($tmp) === false) throw new RuntimeException('The reference image could not be validated.');

    $id = ad_id('ref');
    $relative = ad_safe_storage_relative('storage/references/' . $id . '.' . $ext);
    $dest = AD_ROOT . '/' . $relative;
    $dir = dirname($dest);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Could not prepare reference storage.');
    if (!move_uploaded_file($tmp, $dest)) throw new RuntimeException('Could not save reference.');
    @chmod($dest, 0644);
    return [
        'id' => $id,
        'kind' => $kind,
        'path' => $relative,
        'url' => ad_public_media_url($relative),
        'mime' => $mime,
        'bytes' => filesize($dest) ?: $size,
        'original_name' => basename((string)($file['name'] ?? ($id . '.' . $ext))),
        'created_at' => ad_now(),
    ];
}

function ad_shot_visual_reference(array $shot): ?array {
    $refs = is_array($shot['references'] ?? null) ? $shot['references'] : [];
    for ($i = count($refs) - 1; $i >= 0; $i--) {
        $ref = $refs[$i] ?? null;
        if (is_array($ref) && ($ref['kind'] ?? '') === 'image' && !empty($ref['url'])) return $ref;
    }
    return null;
}

function ad_download_result(string $url, string $jobId): ?array {
    if (!preg_match('#^https://#i', $url)) return null;
    $destName = preg_replace('/[^A-Za-z0-9_-]/', '_', $jobId) . '.mp4';
    $relative = ad_safe_storage_relative('storage/results/' . $destName);
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
        CURLOPT_USERAGENT => 'AnimeDirectorLab/0.02',
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
