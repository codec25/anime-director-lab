<?php
declare(strict_types=1);

/** Canonical Character Bible reference roles. */
function ad_character_reference_roles(): array {
    return [
        'master_front', 'three_quarter', 'portrait', 'side', 'back',
        'expression_neutral', 'expression_happy', 'expression_angry', 'expression_concerned',
    ];
}

function ad_character_required_roles(): array {
    return ['master_front', 'three_quarter', 'portrait'];
}

function ad_provider_binding_keys(): array {
    return ['runway', 'vidu', 'kling', 'google', 'wan', 'future'];
}

function ad_benchmark_tests(): array {
    return [
        'A1' => ['label' => 'Neutral acting', 'track' => 'acting'],
        'A2' => ['label' => 'Emotional dialogue', 'track' => 'acting'],
        'A3' => ['label' => 'Gesture', 'track' => 'acting'],
        'A4' => ['label' => 'Upper-body action', 'track' => 'acting'],
        'B1' => ['label' => 'Walk & turn', 'track' => 'full_body'],
        'B2' => ['label' => 'Groove / footwork', 'track' => 'full_body'],
        'B3' => ['label' => 'Martial combination', 'track' => 'full_body'],
        'B4' => ['label' => 'Jump / turn', 'track' => 'full_body'],
    ];
}

function ad_score_keys(): array {
    return [
        'identity', 'face', 'hair', 'costume', 'proportions',
        'motion_fidelity', 'timing_fidelity', 'hands', 'feet', 'expression',
        'style', 'stability',
    ];
}

function ad_default_anime_boost(string $mode = 'natural'): array {
    $mode = in_array($mode, ['natural', 'anime', 'extreme'], true) ? $mode : 'natural';
    $presets = [
        'natural' => ['anticipation' => 10, 'exaggeration' => 10, 'impact' => 5, 'follow_through' => 10, 'speed' => 10, 'camera_reaction' => 5, 'vfx_intensity' => 0],
        'anime' => ['anticipation' => 55, 'exaggeration' => 55, 'impact' => 65, 'follow_through' => 50, 'speed' => 45, 'camera_reaction' => 45, 'vfx_intensity' => 45],
        'extreme' => ['anticipation' => 90, 'exaggeration' => 90, 'impact' => 100, 'follow_through' => 85, 'speed' => 90, 'camera_reaction' => 85, 'vfx_intensity' => 90],
    ];
    return array_merge(['mode' => $mode], $presets[$mode]);
}

function ad_future_editable_effects(): array {
    return [
        'speed_lines', 'impact_flash', 'camera_shake', 'glow', 'particles',
        'energy_trail', 'shockwave', 'debris', 'motion_blur', 'sfx',
    ];
}

function ad_empty_references(): array {
    $refs = [];
    foreach (ad_character_reference_roles() as $role) $refs[$role] = null;
    return $refs;
}

function ad_empty_provider_bindings(): array {
    $bindings = [];
    foreach (ad_provider_binding_keys() as $key) $bindings[$key] = null;
    return $bindings;
}

function ad_normalize_character(?array $character): ?array {
    if ($character === null) return null;
    $refs = ad_empty_references();
    $incoming = is_array($character['references'] ?? null) ? $character['references'] : [];
    foreach ($refs as $role => $_) {
        if (isset($incoming[$role]) && is_array($incoming[$role])) $refs[$role] = $incoming[$role];
    }
    // Legacy single-asset characters become master_front.
    if ($refs['master_front'] === null && is_array($character['asset'] ?? null)) {
        $refs['master_front'] = $character['asset'];
    }
    $bindings = ad_empty_provider_bindings();
    $incomingBindings = is_array($character['provider_bindings'] ?? null) ? $character['provider_bindings'] : [];
    foreach ($bindings as $key => $_) {
        if (array_key_exists($key, $incomingBindings)) $bindings[$key] = $incomingBindings[$key];
    }
    $status = (string)($character['status'] ?? 'locked');
    if (!in_array($status, ['draft', 'locked'], true)) $status = 'locked';
    $source = (string)($character['source'] ?? 'upload');
    if (!in_array($source, ['upload', 'import_sheet', 'generated', 'draw'], true)) $source = 'upload';
    $master = $refs['master_front'];
    return [
        'id' => (string)($character['id'] ?? ad_id('char')),
        'name' => (string)($character['name'] ?? 'Akio'),
        'version' => (string)($character['version'] ?? 'v1'),
        'status' => $status,
        'description' => (string)($character['description'] ?? $character['notes'] ?? ''),
        'canonical_style' => (string)($character['canonical_style'] ?? ''),
        'body_notes' => (string)($character['body_notes'] ?? ''),
        'facial_notes' => (string)($character['facial_notes'] ?? ''),
        'hairstyle_notes' => (string)($character['hairstyle_notes'] ?? ''),
        'eye_notes' => (string)($character['eye_notes'] ?? ''),
        'outfit_notes' => (string)($character['outfit_notes'] ?? ($character['notes'] ?? '')),
        'movement_notes' => (string)($character['movement_notes'] ?? ''),
        'voice_notes' => (string)($character['voice_notes'] ?? ''),
        'notes' => (string)($character['notes'] ?? ''),
        'source' => $source,
        'references' => $refs,
        'provider_bindings' => $bindings,
        'asset' => is_array($master) ? $master : ($character['asset'] ?? null),
        'created_at' => (string)($character['created_at'] ?? ad_now()),
        'updated_at' => (string)($character['updated_at'] ?? $character['created_at'] ?? ad_now()),
    ];
}

function ad_normalize_job(array $job): array {
    $status = (string)($job['status'] ?? 'queued');
    $statusMap = [
        'submitting' => 'submitted',
        'succeeded' => 'completed',
        'success' => 'completed',
        'running' => 'processing',
        'pending' => 'queued',
    ];
    if (isset($statusMap[$status])) $status = $statusMap[$status];
    if (!in_array($status, ['queued', 'submitted', 'processing', 'completed', 'failed', 'cancelled'], true)) {
        $status = 'queued';
    }
    $external = (string)($job['provider_job_id'] ?? $job['external_id'] ?? '');
    return array_merge($job, [
        'id' => (string)($job['id'] ?? ad_id('job')),
        'shot_id' => (string)($job['shot_id'] ?? ''),
        'performance_id' => (string)($job['performance_id'] ?? ''),
        'character_version_id' => (string)($job['character_version_id'] ?? ''),
        'provider' => (string)($job['provider'] ?? ''),
        'model' => (string)($job['model'] ?? ''),
        'capability' => (string)($job['capability'] ?? 'ACT_IT'),
        'status' => $status,
        'attempt' => (int)($job['attempt'] ?? 1),
        'provider_job_id' => $external,
        'external_id' => $external,
        'submitted_at' => $job['submitted_at'] ?? null,
        'started_at' => $job['started_at'] ?? null,
        'completed_at' => $job['completed_at'] ?? null,
        'failed_at' => $job['failed_at'] ?? (($status === 'failed') ? ($job['completed_at'] ?? null) : null),
        'estimated_cost_usd' => (float)($job['estimated_cost_usd'] ?? $job['estimated_cost'] ?? 0),
        'actual_cost' => $job['actual_cost'] ?? null,
        'error_code' => $job['error_code'] ?? null,
        'safe_error' => $job['safe_error'] ?? $job['error'] ?? null,
        'error' => $job['safe_error'] ?? $job['error'] ?? null,
        'metadata' => is_array($job['metadata'] ?? null) ? $job['metadata'] : (is_array($job['raw'] ?? null) ? $job['raw'] : null),
        'raw' => $job['raw'] ?? $job['metadata'] ?? null,
        'duration_seconds' => (float)($job['duration_seconds'] ?? 0),
    ]);
}

function ad_normalize_take(array $take): array {
    $local = is_array($take['local'] ?? null) ? $take['local'] : null;
    $remote = (string)($take['remote_url'] ?? '');
    $result = is_array($take['result_media'] ?? null) ? $take['result_media'] : null;
    if ($result === null) {
        $result = [
            'remote_url' => $remote,
            'local' => $local,
            'mock' => !empty($take['mock']),
        ];
    }
    return array_merge($take, [
        'id' => (string)($take['id'] ?? ad_id('take')),
        'shot_id' => (string)($take['shot_id'] ?? ''),
        'performance_id' => (string)($take['performance_id'] ?? ''),
        'generation_job_id' => (string)($take['generation_job_id'] ?? $take['job_id'] ?? ''),
        'job_id' => (string)($take['job_id'] ?? $take['generation_job_id'] ?? ''),
        'provider' => (string)($take['provider'] ?? ''),
        'model' => (string)($take['model'] ?? ''),
        'mode' => (string)($take['mode'] ?? 'ACT_IT'),
        'result_media' => $result,
        'remote_url' => $remote !== '' ? $remote : (string)($result['remote_url'] ?? ''),
        'local' => $local ?? ($result['local'] ?? null),
        'selected' => !empty($take['selected']),
        'usable' => !array_key_exists('usable', $take) || $take['usable'] === null
            ? null
            : !empty($take['usable']),
        'score_summary' => is_array($take['score_summary'] ?? null) ? $take['score_summary'] : null,
        'generation_cost' => $take['generation_cost'] ?? null,
        'attempt' => (int)($take['attempt'] ?? 1),
        'mock' => !empty($take['mock']),
        'created_at' => (string)($take['created_at'] ?? ad_now()),
    ]);
}

function ad_normalize_performance(array $performance): array {
    return array_merge($performance, [
        'id' => (string)($performance['id'] ?? ad_id('perf')),
        'shot_id' => $performance['shot_id'] ?? null,
        'code' => (string)($performance['code'] ?? $performance['benchmark_test_id'] ?? 'A1'),
        'benchmark_test_id' => (string)($performance['benchmark_test_id'] ?? $performance['code'] ?? 'A1'),
        'label' => (string)($performance['label'] ?? 'Performance'),
        'track' => in_array(($performance['track'] ?? 'acting'), ['acting', 'full_body'], true) ? (string)$performance['track'] : 'acting',
        'duration_seconds' => (float)($performance['duration_seconds'] ?? $performance['duration'] ?? 5),
        'duration' => (float)($performance['duration_seconds'] ?? $performance['duration'] ?? 5),
        'source' => (string)($performance['source'] ?? 'upload'),
        'notes' => (string)($performance['notes'] ?? ''),
        'media_path' => (string)($performance['media_path'] ?? ($performance['asset']['path'] ?? '')),
        'asset' => is_array($performance['asset'] ?? null) ? $performance['asset'] : null,
        'created_at' => (string)($performance['created_at'] ?? ad_now()),
    ]);
}

function ad_normalize_shot(array $shot, ?array $character = null): array {
    $boostMode = (string)($shot['anime_boost_mode'] ?? $shot['anime_boost'] ?? 'natural');
    if (is_array($shot['anime_boost'] ?? null)) {
        $boostMode = (string)($shot['anime_boost']['mode'] ?? $boostMode);
    }
    if (!in_array($boostMode, ['natural', 'anime', 'extreme'], true)) $boostMode = 'natural';
    $boost = ad_default_anime_boost($boostMode);
    if (is_array($shot['anime_boost'] ?? null)) {
        foreach ($boost as $k => $v) {
            if (isset($shot['anime_boost'][$k])) $boost[$k] = $shot['anime_boost'][$k];
        }
    } elseif (is_array($shot['boost_recipe'] ?? null)) {
        $boost['exaggeration'] = (int)($shot['boost_recipe']['motion_exaggeration'] ?? $boost['exaggeration']);
        $boost['impact'] = (int)($shot['boost_recipe']['impact'] ?? $boost['impact']);
        $boost['camera_reaction'] = (int)($shot['boost_recipe']['camera_reaction'] ?? $boost['camera_reaction']);
        $boost['vfx_intensity'] = (int)($shot['boost_recipe']['vfx'] ?? $boost['vfx_intensity']);
    }
    $mode = strtoupper((string)($shot['generation_mode'] ?? 'ACT_IT'));
    if (!in_array($mode, ['ACT_IT', 'DESCRIBE_IT'], true)) $mode = 'ACT_IT';
    $status = (string)($shot['status'] ?? 'draft');
    if (!in_array($status, ['draft', 'ready', 'generating', 'review', 'approved'], true)) $status = 'draft';
    $charIds = $shot['character_version_ids'] ?? null;
    if (!is_array($charIds)) {
        $charIds = [];
        if (!empty($shot['character_id'])) $charIds[] = (string)$shot['character_id'];
        elseif ($character) $charIds[] = (string)$character['id'];
    }
    $ratio = (string)($shot['ratio'] ?? $shot['framing'] ?? '1280:720');
    return array_merge($shot, [
        'id' => (string)($shot['id'] ?? ad_id('shot')),
        'scene_id' => (string)($shot['scene_id'] ?? ''),
        'number' => (int)($shot['number'] ?? $shot['shot_number'] ?? 1),
        'shot_number' => (int)($shot['shot_number'] ?? $shot['number'] ?? 1),
        'title' => (string)($shot['title'] ?? ('Shot ' . str_pad((string)($shot['number'] ?? 1), 2, '0', STR_PAD_LEFT))),
        'intent' => (string)($shot['intent'] ?? ''),
        'direction' => (string)($shot['direction'] ?? ''),
        'character_id' => (string)($shot['character_id'] ?? ($charIds[0] ?? '')),
        'character_version_ids' => array_values(array_map('strval', $charIds)),
        'world_version_id' => $shot['world_version_id'] ?? null,
        'performance_id' => (string)($shot['performance_id'] ?? ''),
        'framing' => $ratio,
        'ratio' => $ratio,
        'camera_direction' => (string)($shot['camera_direction'] ?? ''),
        'duration_target' => (float)($shot['duration_target'] ?? 5),
        'generation_mode' => $mode,
        'anime_boost_mode' => $boostMode,
        'anime_boost' => $boost,
        'boost_recipe' => [
            'motion_exaggeration' => (int)$boost['exaggeration'],
            'impact' => (int)$boost['impact'],
            'camera_reaction' => (int)$boost['camera_reaction'],
            'vfx' => (int)$boost['vfx_intensity'],
        ],
        'status' => $status,
        'selected_take_id' => $shot['selected_take_id'] ?? null,
        'created_at' => (string)($shot['created_at'] ?? ad_now()),
        'updated_at' => (string)($shot['updated_at'] ?? $shot['created_at'] ?? ad_now()),
    ]);
}

function ad_default_production(): array {
    return [
        'id' => ad_id('prod'),
        'title' => 'Anime Director Lab',
        'created_at' => ad_now(),
        'updated_at' => ad_now(),
    ];
}

function ad_default_scene(string $productionId, int $number = 1): array {
    return [
        'id' => ad_id('scene'),
        'production_id' => $productionId,
        'number' => $number,
        'title' => 'Scene ' . str_pad((string)$number, 2, '0', STR_PAD_LEFT),
        'shot_ids' => [],
        'created_at' => ad_now(),
        'updated_at' => ad_now(),
    ];
}

function ad_default_world_bible(): array {
    return [
        'id' => null,
        'status' => 'stub',
        'title' => 'World Bible',
        'notes' => 'Stub only — not implemented in 0.01 foundation sprint.',
        'version' => null,
    ];
}

function ad_normalize_state(array $state): array {
    $base = ad_default_state();
    $merged = array_replace($base, $state);
    $character = ad_normalize_character(is_array($merged['character'] ?? null) ? $merged['character'] : null);
    $production = is_array($merged['production'] ?? null) ? $merged['production'] : ad_default_production();
    if (empty($production['id'])) $production = ad_default_production();
    $scenes = is_array($merged['scenes'] ?? null) ? $merged['scenes'] : [];
    if ($scenes === []) {
        $scenes[] = ad_default_scene((string)$production['id'], 1);
    }
    $shots = [];
    foreach ((array)($merged['shots'] ?? []) as $shot) {
        if (is_array($shot)) $shots[] = ad_normalize_shot($shot, $character);
    }
    // Ensure every shot appears in a scene order list.
    $knownShotIds = [];
    foreach ($scenes as &$scene) {
        if (!is_array($scene)) continue;
        $scene['shot_ids'] = array_values(array_filter(array_map('strval', (array)($scene['shot_ids'] ?? []))));
        foreach ($scene['shot_ids'] as $sid) $knownShotIds[$sid] = true;
    }
    unset($scene);
    $defaultSceneId = (string)($scenes[0]['id'] ?? 'scene_lab_01');
    foreach ($shots as &$shot) {
        if ($shot['scene_id'] === '') $shot['scene_id'] = $defaultSceneId;
        if (!isset($knownShotIds[$shot['id']])) {
            $scenes[0]['shot_ids'][] = $shot['id'];
            $knownShotIds[$shot['id']] = true;
        }
    }
    unset($shot);
    $performances = [];
    foreach ((array)($merged['performances'] ?? []) as $p) {
        if (is_array($p)) $performances[] = ad_normalize_performance($p);
    }
    $jobs = [];
    foreach ((array)($merged['jobs'] ?? []) as $j) {
        if (is_array($j)) $jobs[] = ad_normalize_job($j);
    }
    $takes = [];
    foreach ((array)($merged['takes'] ?? []) as $t) {
        if (is_array($t)) $takes[] = ad_normalize_take($t);
    }
    $merged['version'] = max(2, (int)($merged['version'] ?? 2));
    $merged['production'] = $production;
    $merged['world'] = is_array($merged['world'] ?? null) ? array_replace(ad_default_world_bible(), $merged['world']) : ad_default_world_bible();
    $merged['scenes'] = array_values($scenes);
    $merged['character'] = $character;
    $merged['performances'] = $performances;
    $merged['shots'] = $shots;
    $merged['jobs'] = $jobs;
    $merged['takes'] = $takes;
    $merged['scores'] = array_values(array_filter((array)($merged['scores'] ?? []), 'is_array'));
    $merged['events'] = array_values(array_filter((array)($merged['events'] ?? []), 'is_array'));
    return $merged;
}

function ad_benchmark_summary(array $state): array {
    $rows = [];
    $byKey = [];
    $ensure = static function(array &$byKey, string $provider, string $test) : string {
        $key = $provider . '|' . $test;
        if (!isset($byKey[$key])) {
            $byKey[$key] = [
                'provider' => $provider,
                'test' => $test,
                'attempts' => 0,
                'usable_takes' => 0,
                'scored' => 0,
                'cps_sum' => 0.0,
                'pps_sum' => 0.0,
                'dus_sum' => 0.0,
                'estimated_spend' => 0.0,
            ];
        }
        return $key;
    };

    // Scores / usable takes from scored results.
    foreach ($state['takes'] as $take) {
        $job = null;
        foreach ($state['jobs'] as $j) {
            if (($j['id'] ?? '') === ($take['generation_job_id'] ?? $take['job_id'] ?? '')) { $job = $j; break; }
        }
        $shot = null;
        foreach ($state['shots'] as $s) {
            if (($s['id'] ?? '') === ($take['shot_id'] ?? '')) { $shot = $s; break; }
        }
        $perf = null;
        $perfId = trim((string)($take['performance_id'] ?? ''));
        if ($perfId === '') $perfId = trim((string)($shot['performance_id'] ?? ''));
        foreach ($state['performances'] as $p) {
            if (($p['id'] ?? '') === $perfId) { $perf = $p; break; }
        }
        $score = null;
        foreach ($state['scores'] as $sc) {
            if (($sc['take_id'] ?? '') === ($take['id'] ?? '')) { $score = $sc; break; }
        }
        $provider = (string)($take['provider'] ?? $job['provider'] ?? 'unknown');
        $test = (string)($perf['benchmark_test_id'] ?? $perf['code'] ?? '—');
        $key = $ensure($byKey, $provider, $test);
        $byKey[$key]['attempts'] = max($byKey[$key]['attempts'], (int)($take['attempt'] ?? $job['attempt'] ?? 0));
        if ($score) {
            $byKey[$key]['scored']++;
            $byKey[$key]['cps_sum'] += (float)$score['cps'];
            $byKey[$key]['pps_sum'] += (float)$score['pps'];
            $byKey[$key]['dus_sum'] += (float)$score['dus'];
            if (!empty($score['usable'])) $byKey[$key]['usable_takes']++;
        }
    }

    // Economics: each provider-accepted job counted once (including failed jobs with a task id).
    foreach ($state['jobs'] as $job) {
        $external = trim((string)($job['provider_job_id'] ?? $job['external_id'] ?? ''));
        if ($external === '') continue; // rejected before task id => $0
        $shot = null;
        foreach ($state['shots'] as $s) {
            if (($s['id'] ?? '') === ($job['shot_id'] ?? '')) { $shot = $s; break; }
        }
        $perf = null;
        $perfId = trim((string)($job['performance_id'] ?? ''));
        if ($perfId === '') $perfId = trim((string)($shot['performance_id'] ?? ''));
        foreach ($state['performances'] as $p) {
            if (($p['id'] ?? '') === $perfId) { $perf = $p; break; }
        }
        $provider = (string)($job['provider'] ?? 'unknown');
        $test = (string)($perf['benchmark_test_id'] ?? $perf['code'] ?? '—');
        $key = $ensure($byKey, $provider, $test);
        $byKey[$key]['attempts'] = max($byKey[$key]['attempts'], (int)($job['attempt'] ?? 0));
        $byKey[$key]['estimated_spend'] += (float)($job['estimated_cost_usd'] ?? 0);
    }

    foreach ($byKey as $row) {
        $n = max(1, (int)$row['scored']);
        $spend = (float)$row['estimated_spend'];
        $rows[] = [
            'provider' => $row['provider'],
            'test' => $row['test'],
            'attempts' => (int)$row['attempts'],
            'usable_takes' => (int)$row['usable_takes'],
            'average_cps' => $row['scored'] ? round($row['cps_sum'] / $n, 2) : null,
            'average_pps' => $row['scored'] ? round($row['pps_sum'] / $n, 2) : null,
            'average_dus' => $row['scored'] ? round($row['dus_sum'] / $n, 2) : null,
            'estimated_spend' => round($spend, 4),
            'cost_per_accepted_take' => $row['usable_takes'] > 0
                ? round($spend / $row['usable_takes'], 4)
                : null,
        ];
    }
    usort($rows, static fn($a, $b) => [$a['test'], $a['provider']] <=> [$b['test'], $b['provider']]);
    return $rows;
}
