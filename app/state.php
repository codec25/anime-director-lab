<?php
declare(strict_types=1);

function ad_state_path(): string { return AD_DATA_DIR . '/lab.json'; }

function ad_default_state(): array {
    $production = [
        'id' => 'prod_lab_default',
        'title' => 'Anime Director Lab',
        'created_at' => '2026-01-01T00:00:00+00:00',
        'updated_at' => '2026-01-01T00:00:00+00:00',
    ];
    $scene = [
        'id' => 'scene_lab_01',
        'production_id' => $production['id'],
        'number' => 1,
        'title' => 'Scene 01',
        'shot_ids' => [],
        'created_at' => '2026-01-01T00:00:00+00:00',
        'updated_at' => '2026-01-01T00:00:00+00:00',
    ];
    return [
        'version' => 2,
        'production' => $production,
        'world' => ad_default_world_bible(),
        'scenes' => [$scene],
        'character' => null,
        'performances' => [],
        'shots' => [],
        'jobs' => [],
        'takes' => [],
        'scores' => [],
        'events' => [],
        'updated_at' => ad_now(),
    ];
}

function ad_state_read(): array {
    $path = ad_state_path();
    if (!is_file($path)) return ad_normalize_state(ad_default_state());
    $handle = fopen($path, 'rb');
    if (!$handle) return ad_normalize_state(ad_default_state());
    try {
        if (!flock($handle, LOCK_SH)) throw new RuntimeException('Unable to lock lab state for reading.');
        rewind($handle);
        $raw = stream_get_contents($handle);
        $data = (is_string($raw) && trim($raw) !== '') ? json_decode($raw, true) : null;
        return ad_normalize_state(is_array($data) ? $data : ad_default_state());
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function ad_state_mutate(callable $mutator): array {
    $path = ad_state_path();
    $handle = fopen($path, 'c+');
    if (!$handle) throw new RuntimeException('Unable to open lab state.');
    try {
        if (!flock($handle, LOCK_EX)) throw new RuntimeException('Unable to lock lab state.');
        rewind($handle);
        $raw = stream_get_contents($handle);
        $data = (is_string($raw) && trim($raw) !== '') ? json_decode($raw, true) : null;
        $state = ad_normalize_state(is_array($data) ? $data : ad_default_state());
        $next = $mutator($state);
        if (!is_array($next)) $next = $state;
        $next = ad_normalize_state($next);
        $next['updated_at'] = ad_now();
        $encoded = json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) throw new RuntimeException('Unable to encode lab state.');
        // Locked in-place write keeps exclusive flock on the same inode.
        rewind($handle);
        ftruncate($handle, 0);
        if (fwrite($handle, $encoded) === false) throw new RuntimeException('Unable to write lab state.');
        fflush($handle);
        flock($handle, LOCK_UN);
        return $next;
    } finally { fclose($handle); }
}

function ad_event(string $type, array $meta = []): void {
    ad_state_mutate(function(array $state) use ($type, $meta): array {
        $state['events'][] = ['id' => ad_id('evt'), 'type' => $type, 'meta' => $meta, 'at' => ad_now()];
        $state['events'] = array_slice($state['events'], -300);
        return $state;
    });
}
