<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

$action = (string)($_GET['action'] ?? 'state');

function adworld_normalize(array $world): array {
    $refs = array_values(array_filter((array)($world['references'] ?? []), 'is_array'));
    return array_merge($world, [
        'id' => (string)($world['id'] ?? 'world_main'),
        'status' => 'active',
        'title' => ad_substr(trim((string)($world['title'] ?? 'Main World')), 0, 100),
        'location' => ad_substr(trim((string)($world['location'] ?? '')), 0, 400),
        'environment' => ad_substr(trim((string)($world['environment'] ?? '')), 0, 1500),
        'lighting' => ad_substr(trim((string)($world['lighting'] ?? '')), 0, 800),
        'palette' => ad_substr(trim((string)($world['palette'] ?? '')), 0, 500),
        'weather' => ad_substr(trim((string)($world['weather'] ?? '')), 0, 500),
        'time_of_day' => ad_substr(trim((string)($world['time_of_day'] ?? '')), 0, 200),
        'style_rules' => ad_substr(trim((string)($world['style_rules'] ?? '')), 0, 1500),
        'continuity_rules' => ad_substr(trim((string)($world['continuity_rules'] ?? '')), 0, 1500),
        'references' => array_slice($refs, -30),
        'version' => max(1, (int)($world['version'] ?? 1)),
        'updated_at' => (string)($world['updated_at'] ?? ad_now()),
    ]);
}

try {
    if ($action === 'state') {
        $state = ad_state_read();
        ad_json(['ok'=>true,'world'=>adworld_normalize((array)($state['world'] ?? []))]);
    }

    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = ad_json_input();
        $next = ad_state_mutate(function(array $state) use ($d): array {
            $world = adworld_normalize((array)($state['world'] ?? []));
            foreach (['title','location','environment','lighting','palette','weather','time_of_day','style_rules','continuity_rules'] as $field) {
                if (array_key_exists($field, $d)) $world[$field] = (string)$d[$field];
            }
            $world['version'] = (int)($world['version'] ?? 1) + 1;
            $world['updated_at'] = ad_now();
            $state['world'] = adworld_normalize($world);
            return $state;
        });
        ad_event('world_memory_updated', ['version'=>(int)($next['world']['version'] ?? 1)]);
        ad_json(['ok'=>true,'world'=>$next['world']]);
    }

    if ($action === 'attach-reference' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_FILES['reference'])) ad_json(['ok'=>false,'error'=>'Reference file is required.'],422);
        $asset = ad_store_director_reference($_FILES['reference']);
        $next = ad_state_mutate(function(array $state) use ($asset): array {
            $world = adworld_normalize((array)($state['world'] ?? []));
            $refs = (array)($world['references'] ?? []); $refs[] = $asset;
            $world['references'] = array_slice($refs, -30);
            $world['version'] = (int)($world['version'] ?? 1) + 1;
            $world['updated_at'] = ad_now();
            $state['world'] = adworld_normalize($world);
            return $state;
        });
        ad_event('world_reference_attached', ['reference_id'=>$asset['id'],'kind'=>$asset['kind']]);
        ad_json(['ok'=>true,'reference'=>$asset,'world'=>$next['world']]);
    }

    if ($action === 'remove-reference' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = ad_json_input(); $id = trim((string)($d['reference_id'] ?? ''));
        if ($id === '') ad_json(['ok'=>false,'error'=>'Reference id is required.'],422);
        $next = ad_state_mutate(function(array $state) use ($id): array {
            $world = adworld_normalize((array)($state['world'] ?? []));
            $world['references'] = array_values(array_filter((array)$world['references'], static fn(array $r): bool => (string)($r['id'] ?? '') !== $id));
            $world['version'] = (int)($world['version'] ?? 1) + 1;
            $world['updated_at'] = ad_now(); $state['world'] = $world; return $state;
        });
        ad_json(['ok'=>true,'world'=>$next['world']]);
    }

    ad_json(['ok'=>false,'error'=>'Unknown world action.'],404);
} catch (Throwable $e) {
    ad_json(['ok'=>false,'error'=>ad_substr($e->getMessage(),0,400)],500);
}
