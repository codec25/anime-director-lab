<?php
declare(strict_types=1);

function ad_state_path(): string { return AD_DATA_DIR . '/lab.json'; }

function ad_default_state(): array {
    return [
        'version' => 1,
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
    if (!is_file($path)) return ad_default_state();
    $raw = file_get_contents($path);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($data) ? array_replace(ad_default_state(), $data) : ad_default_state();
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
        $state = is_array($data) ? array_replace(ad_default_state(), $data) : ad_default_state();
        $next = $mutator($state);
        if (!is_array($next)) $next = $state;
        $next['updated_at'] = ad_now();
        $encoded = json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) throw new RuntimeException('Unable to encode lab state.');
        rewind($handle); ftruncate($handle, 0); fwrite($handle, $encoded); fflush($handle);
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
