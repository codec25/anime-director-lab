<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

$action = (string)($_GET['action'] ?? 'state');

function ad_provider(string $id): ADProvider {
    return ad_provider_instance($id);
}

function ad_find(array $items, string $id): ?array {
    foreach ($items as $item) if ((string)($item['id'] ?? '') === $id) return $item;
    return null;
}

function ad_character_master_url(?array $character): string {
    if (!$character) return '';
    $master = $character['references']['master_front'] ?? $character['asset'] ?? null;
    return is_array($master) ? (string)($master['url'] ?? '') : '';
}

function ad_build_character_from_post(array $existing = null): array {
    $name = trim((string)($_POST['name'] ?? ($existing['name'] ?? 'Akio'))) ?: 'Akio';
    $version = trim((string)($_POST['version'] ?? ($existing['version'] ?? 'v1'))) ?: 'v1';
    $source = (string)($_POST['source'] ?? ($existing['source'] ?? 'upload'));
    if (!in_array($source, ['upload', 'import_sheet', 'generated', 'draw'], true)) $source = 'upload';
    $base = $existing ? ad_normalize_character($existing) : ad_normalize_character([
        'id' => ad_id('char'),
        'name' => $name,
        'version' => $version,
        'status' => 'draft',
        'source' => $source,
        'created_at' => ad_now(),
    ]);
    $fields = [
        'description', 'canonical_style', 'body_notes', 'facial_notes', 'hairstyle_notes',
        'eye_notes', 'outfit_notes', 'movement_notes', 'voice_notes', 'notes',
    ];
    foreach ($fields as $field) {
        if (array_key_exists($field, $_POST)) {
            $base[$field] = ad_substr(trim((string)$_POST[$field]), 0, 2000);
        }
    }
    $base['name'] = ad_substr($name, 0, 80);
    $base['version'] = ad_substr($version, 0, 40);
    $base['source'] = $source;
    $base['updated_at'] = ad_now();
    return ad_normalize_character($base);
}

try {
    if ($action === 'state') {
        $state = ad_state_read();
        ad_json([
            'ok' => true,
            'state' => $state,
            'benchmark_summary' => ad_benchmark_summary($state),
            'config' => [
                'mock_mode' => ad_mock_mode(),
                'base_url' => ad_base_url(),
                'capabilities' => ad_capabilities(),
                'reference_roles' => ad_character_reference_roles(),
                'required_reference_roles' => ad_character_required_roles(),
                'benchmark_tests' => ad_benchmark_tests(),
                'anime_boost_effects_future' => ad_future_editable_effects(),
                'providers' => ad_gateway_catalog(),
            ],
        ]);
    }

    if ($action === 'save-character' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $state = ad_state_read();
        $character = ad_build_character_from_post(is_array($state['character'] ?? null) ? $state['character'] : null);
        $role = (string)($_POST['role'] ?? 'master_front');
        if (!in_array($role, ad_character_reference_roles(), true)) $role = 'master_front';
        if (!empty($_FILES['image'])) {
            $asset = ad_store_upload($_FILES['image'], 'characters', $role);
            $character['references'][$role] = $asset;
            if ($role === 'master_front') $character['asset'] = $asset;
        } elseif (!empty($_FILES['sheet'])) {
            $asset = ad_store_upload($_FILES['sheet'], 'sheets', 'master_front');
            $character['source'] = 'import_sheet';
            $character['references']['master_front'] = $asset;
            $character['asset'] = $asset;
        } elseif (empty($character['references']['master_front']) && empty($character['asset'])) {
            ad_json(['ok' => false, 'error' => 'Character image is required.'], 422);
        }
        $lock = filter_var($_POST['lock'] ?? '1', FILTER_VALIDATE_BOOLEAN);
        if ($lock) $character['status'] = 'locked';
        $character = ad_normalize_character($character);
        ad_state_mutate(function(array $state) use ($character): array {
            $state['character'] = $character;
            return $state;
        });
        ad_event('character_locked', ['character_id' => $character['id'], 'status' => $character['status']]);
        ad_json(['ok' => true, 'character' => $character]);
    }

    if ($action === 'upload-character-reference' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $state = ad_state_read();
        if (!$state['character']) ad_json(['ok' => false, 'error' => 'Create a character first.'], 409);
        $role = (string)($_POST['role'] ?? '');
        if (!in_array($role, ad_character_reference_roles(), true)) ad_json(['ok' => false, 'error' => 'Unknown reference role.'], 422);
        if (empty($_FILES['image'])) ad_json(['ok' => false, 'error' => 'Reference image is required.'], 422);
        $asset = ad_store_upload($_FILES['image'], 'characters', $role);
        $character = ad_state_mutate(function(array $state) use ($role, $asset): array {
            $character = ad_normalize_character($state['character']);
            $character['references'][$role] = $asset;
            if ($role === 'master_front') $character['asset'] = $asset;
            $character['updated_at'] = ad_now();
            $state['character'] = ad_normalize_character($character);
            return $state;
        })['character'];
        ad_event('character_reference_uploaded', ['role' => $role, 'character_id' => $character['id']]);
        ad_json(['ok' => true, 'character' => $character]);
    }

    if ($action === 'upload-performance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_FILES['video'])) ad_json(['ok' => false, 'error' => 'Performance video is required.'], 422);
        $duration = (float)($_POST['duration_seconds'] ?? 5);
        if ($duration < 3 || $duration > 30) {
            ad_json(['ok' => false, 'error' => 'Performance duration must be between 3 and 30 seconds.'], 422);
        }
        $asset = ad_store_upload($_FILES['video'], 'performances');
        $code = ad_substr(trim((string)($_POST['code'] ?? 'A1')), 0, 20);
        $tests = ad_benchmark_tests();
        $defaultTrack = $tests[$code]['track'] ?? 'acting';
        $performance = ad_normalize_performance([
            'id' => ad_id('perf'),
            'shot_id' => ($_POST['shot_id'] ?? '') !== '' ? (string)$_POST['shot_id'] : null,
            'code' => $code,
            'benchmark_test_id' => $code,
            'label' => ad_substr(trim((string)($_POST['label'] ?? ($tests[$code]['label'] ?? 'Performance'))), 0, 100),
            'track' => in_array((string)($_POST['track'] ?? $defaultTrack), ['acting', 'full_body'], true) ? (string)$_POST['track'] : $defaultTrack,
            'duration_seconds' => $duration,
            'duration' => $duration,
            'notes' => ad_substr(trim((string)($_POST['notes'] ?? '')), 0, 1000),
            'source' => 'upload',
            'media_path' => $asset['path'],
            'asset' => $asset,
            'created_at' => ad_now(),
        ]);
        ad_state_mutate(function(array $state) use ($performance): array {
            $state['performances'][] = $performance;
            return $state;
        });
        ad_event('performance_uploaded', ['performance_id' => $performance['id']]);
        ad_json(['ok' => true, 'performance' => $performance]);
    }

    if ($action === 'create-shot' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = ad_json_input();
        $state = ad_state_read();
        if (!$state['character']) ad_json(['ok' => false, 'error' => 'Lock a character first.'], 409);
        $mode = strtoupper((string)($d['generation_mode'] ?? 'ACT_IT'));
        if (!in_array($mode, ['ACT_IT', 'DESCRIBE_IT'], true)) $mode = 'ACT_IT';
        $performanceId = trim((string)($d['performance_id'] ?? ''));
        $performance = $performanceId !== '' ? ad_find($state['performances'], $performanceId) : null;
        if ($mode === 'ACT_IT') {
            if (!$performance) ad_json(['ok' => false, 'error' => 'Choose a valid performance.'], 422);
        } else {
            // DESCRIBE_IT: architecture-only drafts may omit performance.
            $performanceId = $performance ? (string)$performance['id'] : '';
        }
        $boost = in_array((string)($d['boost'] ?? $d['anime_boost_mode'] ?? 'natural'), ['natural', 'anime', 'extreme'], true)
            ? (string)($d['boost'] ?? $d['anime_boost_mode'] ?? 'natural') : 'natural';
        $sceneId = (string)($d['scene_id'] ?? ($state['scenes'][0]['id'] ?? 'scene_lab_01'));
        $scene = ad_find($state['scenes'], $sceneId) ?? $state['scenes'][0];
        $number = count($state['shots']) + 1;
        $intentDefault = $performance['label'] ?? 'Described shot';
        $shot = ad_normalize_shot([
            'id' => ad_id('shot'),
            'scene_id' => (string)$scene['id'],
            'number' => $number,
            'shot_number' => $number,
            'title' => ad_substr(trim((string)($d['title'] ?? ('Shot ' . str_pad((string)$number, 2, '0', STR_PAD_LEFT)))), 0, 120),
            'character_id' => $state['character']['id'],
            'character_version_ids' => [$state['character']['id']],
            'performance_id' => $performanceId,
            'intent' => ad_substr(trim((string)($d['intent'] ?? $intentDefault)), 0, 600),
            'direction' => ad_substr(trim((string)($d['direction'] ?? '')), 0, 1000),
            'camera_direction' => ad_substr(trim((string)($d['camera_direction'] ?? '')), 0, 400),
            'duration_target' => (float)($d['duration_target'] ?? ($performance['duration_seconds'] ?? 5)),
            'ratio' => (string)($d['ratio'] ?? $d['framing'] ?? '1280:720'),
            'generation_mode' => $mode,
            'anime_boost_mode' => $boost,
            'anime_boost' => ad_default_anime_boost($boost),
            'status' => $mode === 'DESCRIBE_IT' ? 'draft' : 'ready',
            'selected_take_id' => null,
            'created_at' => ad_now(),
            'updated_at' => ad_now(),
        ], $state['character']);
        ad_state_mutate(function(array $state) use ($shot, $scene): array {
            $state['shots'][] = $shot;
            foreach ($state['scenes'] as &$s) {
                if (($s['id'] ?? '') === ($scene['id'] ?? '')) {
                    $s['shot_ids'][] = $shot['id'];
                    $s['updated_at'] = ad_now();
                    break;
                }
            }
            unset($s);
            return $state;
        });
        ad_json(['ok' => true, 'shot' => $shot]);
    }

    if ($action === 'generate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = ad_json_input();
        $state = ad_state_read();
        $shot = ad_find($state['shots'], (string)($d['shot_id'] ?? ''));
        if (!$shot) ad_json(['ok' => false, 'error' => 'Shot not found.'], 404);
        if (($shot['generation_mode'] ?? 'ACT_IT') === 'DESCRIBE_IT') {
            ad_json(['ok' => false, 'error' => 'DESCRIBE_IT is architecture-only in this build. Use ACT_IT.'], 409);
        }
        $performance = ad_find($state['performances'], (string)$shot['performance_id']);
        $character = $state['character'];
        if (!$performance || !$character) ad_json(['ok' => false, 'error' => 'Character/performance is missing.'], 409);
        $capability = strtoupper((string)($d['capability'] ?? 'ACT_IT'));
        if ($capability !== 'ACT_IT') ad_json(['ok' => false, 'error' => 'Only ACT_IT generation is live in this lab build.'], 409);
        $providerId = (string)($d['provider'] ?? '');
        $meta = ad_provider_registry()[$providerId] ?? null;
        if (!$meta || empty($meta['implemented'])) ad_json(['ok' => false, 'error' => 'Provider is not implemented.'], 409);
        $provider = ad_provider($providerId);
        if (!$provider->available()) ad_json(['ok' => false, 'error' => 'Provider key is not configured.'], 409);

        $characterUrl = ad_character_master_url($character);
        if ($characterUrl === '') ad_json(['ok' => false, 'error' => 'Character master_front reference is required.'], 409);
        $performanceUrl = (string)($performance['asset']['url'] ?? '');
        try {
            ad_assert_live_media_urls($characterUrl, $performanceUrl);
        } catch (Throwable $e) {
            // Preflight failures must not create/count a generation attempt.
            ad_json(['ok' => false, 'error' => $e->getMessage()], 409);
        }

        $accepted = ad_provider_accepted_attempts($state, (string)$shot['id'], $providerId);
        if ($accepted >= 3) ad_json(['ok' => false, 'error' => 'Benchmark limit reached: maximum 3 attempts per provider per shot.'], 409);
        $attempt = $accepted + 1;
        $jobId = ad_id('job');
        $job = ad_normalize_job([
            'id' => $jobId,
            'shot_id' => $shot['id'],
            'performance_id' => $performance['id'],
            'character_version_id' => $character['id'],
            'provider' => $providerId,
            'model' => (string)($meta['model'] ?? ''),
            'capability' => 'ACT_IT',
            'attempt' => $attempt,
            'status' => 'submitted',
            'external_id' => '',
            'provider_job_id' => '',
            'duration_seconds' => (float)$performance['duration_seconds'],
            'estimated_cost_usd' => $provider->estimatedUsd((float)$performance['duration_seconds']),
            'submitted_at' => ad_now(),
            'started_at' => null,
            'completed_at' => null,
            'failed_at' => null,
            'error' => null,
            'safe_error' => null,
            'raw' => null,
            'metadata' => null,
        ]);
        ad_state_mutate(function(array $state) use ($job): array {
            $state['jobs'][] = $job;
            foreach ($state['shots'] as &$s) {
                if ($s['id'] === $job['shot_id']) { $s['status'] = 'generating'; $s['updated_at'] = ad_now(); break; }
            }
            unset($s);
            return $state;
        });
        try {
            $submitted = $provider->submit([
                'job_id' => $jobId,
                'character_url' => $characterUrl,
                'performance_url' => $performanceUrl,
                'duration_seconds' => $performance['duration_seconds'],
                'ratio' => $shot['ratio'],
                'anime_boost' => $shot['anime_boost_mode'],
                'anime_boost_direction' => $shot['anime_boost'],
            ]);
            $external = trim((string)($submitted['external_id'] ?? ''));
            if ($external === '') throw new RuntimeException('Provider returned no task id.');
            ad_state_mutate(function(array $state) use ($jobId, $submitted, $external, $attempt): array {
                foreach ($state['jobs'] as &$j) {
                    if ($j['id'] !== $jobId) continue;
                    $j['external_id'] = $external;
                    $j['provider_job_id'] = $external;
                    $j['attempt'] = $attempt;
                    $j['status'] = 'queued';
                    $j['raw'] = $submitted['raw'] ?? null;
                    $j['metadata'] = $submitted['raw'] ?? null;
                    break;
                }
                unset($j);
                return $state;
            });
            ad_event('generation_submitted', ['job_id' => $jobId, 'provider' => $providerId, 'attempt' => $attempt, 'capability' => 'ACT_IT']);
            ad_json(['ok' => true, 'job_id' => $jobId, 'attempt' => $attempt]);
        } catch (Throwable $e) {
            $safe = ad_safe_provider_error($e);
            ad_state_mutate(function(array $state) use ($jobId, $safe): array {
                $shotId = '';
                foreach ($state['jobs'] as &$j) {
                    if ($j['id'] !== $jobId) continue;
                    // No provider task id => does not consume a benchmark attempt.
                    $shotId = (string)$j['shot_id'];
                    $j['attempt'] = 0;
                    $j['external_id'] = '';
                    $j['provider_job_id'] = '';
                    $j['status'] = 'failed';
                    $j['error'] = $safe['safe_error'];
                    $j['safe_error'] = $safe['safe_error'];
                    $j['error_code'] = $safe['error_code'];
                    $j['completed_at'] = ad_now();
                    $j['failed_at'] = ad_now();
                    break;
                }
                unset($j);
                foreach ($state['shots'] as &$s) {
                    if (($s['id'] ?? '') !== $shotId) continue;
                    $s['status'] = 'ready';
                    $s['updated_at'] = ad_now();
                    break;
                }
                unset($s);
                return $state;
            });
            ad_json(['ok' => false, 'error' => $safe['safe_error'], 'job_id' => $jobId], 502);
        }
    }

    if ($action === 'poll' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = ad_json_input();
        $jobId = (string)($d['job_id'] ?? '');
        $state = ad_state_read();
        $job = ad_find($state['jobs'], $jobId);
        if (!$job) ad_json(['ok' => false, 'error' => 'Job not found.'], 404);
        if (in_array($job['status'], ['completed', 'failed', 'cancelled', 'succeeded'], true)) {
            ad_json(['ok' => true, 'job' => ad_normalize_job($job), 'status' => ad_normalize_job($job)['status']]);
        }
        $provider = ad_provider((string)$job['provider']);
        $result = $provider->poll((string)($job['provider_job_id'] ?: $job['external_id']));
        $take = null;
        $status = (string)($result['status'] ?? 'processing');
        if ($status === 'succeeded') $status = 'completed';
        ad_state_mutate(function(array $state) use ($jobId, $result, $status, &$take): array {
            foreach ($state['jobs'] as &$j) {
                if ($j['id'] !== $jobId) continue;
                $j['status'] = $status;
                $submitPayload = null;
                if (is_array($j['metadata'] ?? null) && isset($j['metadata']['payload'])) $submitPayload = $j['metadata']['payload'];
                elseif (is_array($j['raw'] ?? null) && isset($j['raw']['payload'])) $submitPayload = $j['raw']['payload'];
                $pollRaw = is_array($result['raw'] ?? null) ? $result['raw'] : null;
                $j['raw'] = $pollRaw ?? $j['raw'];
                $meta = is_array($j['metadata'] ?? null) ? $j['metadata'] : [];
                if ($pollRaw !== null) $meta['poll'] = $pollRaw;
                if ($submitPayload !== null) $meta['payload'] = $submitPayload;
                $j['metadata'] = $meta;
                if (isset($result['error'])) {
                    $j['error'] = ad_substr((string)$result['error'], 0, 500);
                    $j['safe_error'] = $j['error'];
                    $j['error_code'] = 'PROVIDER_FAILED';
                }
                if ($status === 'processing' && empty($j['started_at'])) $j['started_at'] = ad_now();
                if (in_array($status, ['completed', 'failed'], true)) {
                    $j['completed_at'] = ad_now();
                    if ($status === 'failed') $j['failed_at'] = ad_now();
                }
                break;
            }
            unset($j);
            if ($status === 'failed') {
                $jobRow = ad_find($state['jobs'], $jobId);
                $shotId = (string)($jobRow['shot_id'] ?? '');
                $hasTakes = ad_shot_has_takes($state, $shotId);
                foreach ($state['shots'] as &$s) {
                    if (($s['id'] ?? '') !== $shotId) continue;
                    $s['status'] = $hasTakes ? 'review' : 'ready';
                    $s['updated_at'] = ad_now();
                    break;
                }
                unset($s);
            }
            if ($status === 'completed') {
                foreach ($state['takes'] as $existing) {
                    if (($existing['job_id'] ?? $existing['generation_job_id'] ?? '') === $jobId) {
                        $take = ad_normalize_take($existing);
                        return $state;
                    }
                }
                $jobRow = ad_find($state['jobs'], $jobId);
                $remote = (string)($result['output_url'] ?? '');
                $local = null;
                if ($remote !== '' && !ad_mock_mode()) $local = ad_download_result($remote, $jobId);
                $registry = ad_provider_registry();
                $take = ad_normalize_take([
                    'id' => ad_id('take'),
                    'job_id' => $jobId,
                    'generation_job_id' => $jobId,
                    'shot_id' => $jobRow['shot_id'],
                    'performance_id' => $jobRow['performance_id'] ?? '',
                    'provider' => $jobRow['provider'],
                    'model' => $jobRow['model'] ?? ($registry[$jobRow['provider']]['model'] ?? ''),
                    'mode' => $jobRow['capability'] ?? 'ACT_IT',
                    'attempt' => $jobRow['attempt'],
                    'remote_url' => $remote,
                    'local' => $local,
                    'mock' => ad_mock_mode(),
                    'selected' => false,
                    'usable' => null,
                    'generation_cost' => $jobRow['estimated_cost_usd'] ?? null,
                    'created_at' => ad_now(),
                ]);
                $state['takes'][] = $take;
                foreach ($state['shots'] as &$s) {
                    if ($s['id'] === $jobRow['shot_id']) { $s['status'] = 'review'; $s['updated_at'] = ad_now(); break; }
                }
                unset($s);
            }
            return $state;
        });
        ad_json(['ok' => true, 'status' => $status, 'take' => $take]);
    }

    if ($action === 'score' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = ad_json_input();
        $takeId = (string)($d['take_id'] ?? '');
        $state = ad_state_read();
        $take = ad_find($state['takes'], $takeId);
        if (!$take) ad_json(['ok' => false, 'error' => 'Take not found.'], 404);
        $keys = ad_score_keys();
        $ratings = [];
        foreach ($keys as $key) {
            $v = (float)($d['ratings'][$key] ?? 0);
            if ($v < 1 || $v > 5) ad_json(['ok' => false, 'error' => 'All benchmark scores must be 1–5.'], 422);
            $ratings[$key] = $v;
        }
        $cps = round(($ratings['identity'] + $ratings['face'] + $ratings['hair'] + $ratings['costume'] + $ratings['proportions']) / 5, 2);
        $pps = round(($ratings['motion_fidelity'] + $ratings['timing_fidelity'] + $ratings['hands'] + $ratings['feet'] + $ratings['expression']) / 5, 2);
        $dus = round(($cps + $pps + $ratings['style'] + $ratings['stability']) / 4, 2);
        $usable = !empty($d['usable']);
        $score = [
            'id' => ad_id('score'),
            'take_id' => $takeId,
            'ratings' => $ratings,
            'usable' => $usable,
            'cps' => $cps,
            'pps' => $pps,
            'dus' => $dus,
            'notes' => ad_substr(trim((string)($d['notes'] ?? '')), 0, 1000),
            'created_at' => ad_now(),
        ];
        ad_state_mutate(function(array $state) use ($score, $takeId, $usable, $cps, $pps, $dus): array {
            $state['scores'] = array_values(array_filter($state['scores'], fn($s) => ($s['take_id'] ?? '') !== $takeId));
            $state['scores'][] = $score;
            foreach ($state['takes'] as &$t) {
                if (($t['id'] ?? '') !== $takeId) continue;
                $t['usable'] = $usable;
                $t['score_summary'] = ['cps' => $cps, 'pps' => $pps, 'dus' => $dus, 'usable' => $usable];
                break;
            }
            unset($t);
            return $state;
        });
        ad_json(['ok' => true, 'score' => $score]);
    }

    if ($action === 'select-take' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = ad_json_input();
        $takeId = (string)($d['take_id'] ?? '');
        $state = ad_state_read();
        $take = ad_find($state['takes'], $takeId);
        if (!$take) ad_json(['ok' => false, 'error' => 'Take not found.'], 404);
        ad_state_mutate(function(array $state) use ($takeId, $take): array {
            foreach ($state['takes'] as &$t) {
                if (($t['shot_id'] ?? '') === $take['shot_id']) $t['selected'] = $t['id'] === $takeId;
            }
            unset($t);
            foreach ($state['shots'] as &$s) {
                if ($s['id'] === $take['shot_id']) {
                    $s['selected_take_id'] = $takeId;
                    $s['status'] = 'approved';
                    $s['updated_at'] = ad_now();
                }
            }
            unset($s);
            return $state;
        });
        ad_event('take_selected', ['take_id' => $takeId, 'shot_id' => $take['shot_id']]);
        ad_json(['ok' => true]);
    }

    if ($action === 'clear-selected-take' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = ad_json_input();
        $shotId = (string)($d['shot_id'] ?? '');
        $state = ad_state_read();
        if (!ad_find($state['shots'], $shotId)) ad_json(['ok' => false, 'error' => 'Shot not found.'], 404);
        ad_state_mutate(function(array $state) use ($shotId): array {
            foreach ($state['takes'] as &$t) {
                if (($t['shot_id'] ?? '') === $shotId) $t['selected'] = false;
            }
            unset($t);
            foreach ($state['shots'] as &$s) {
                if ($s['id'] === $shotId) {
                    $s['selected_take_id'] = null;
                    $s['status'] = 'review';
                    $s['updated_at'] = ad_now();
                }
            }
            unset($s);
            return $state;
        });
        ad_json(['ok' => true]);
    }

    if ($action === 'reorder-shots' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = ad_json_input();
        $sceneId = (string)($d['scene_id'] ?? '');
        $shotIds = array_values(array_map('strval', (array)($d['shot_ids'] ?? [])));
        $state = ad_state_read();
        $scene = ad_find($state['scenes'], $sceneId);
        if (!$scene) ad_json(['ok' => false, 'error' => 'Scene not found.'], 404);
        $existing = array_map('strval', (array)$scene['shot_ids']);
        sort($existing);
        $check = $shotIds;
        sort($check);
        if ($existing !== $check) ad_json(['ok' => false, 'error' => 'shot_ids must contain the same shots.'], 422);
        ad_state_mutate(function(array $state) use ($sceneId, $shotIds): array {
            foreach ($state['scenes'] as &$s) {
                if (($s['id'] ?? '') === $sceneId) {
                    $s['shot_ids'] = $shotIds;
                    $s['updated_at'] = ad_now();
                    break;
                }
            }
            unset($s);
            return $state;
        });
        ad_json(['ok' => true]);
    }

    if ($action === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = (string)($_POST['confirm'] ?? '');
        if ($token !== 'RESET') ad_json(['ok' => false, 'error' => 'Reset confirmation missing.'], 422);
        @unlink(ad_state_path());
        foreach (['characters', 'performances', 'results'] as $bucket) {
            foreach (glob(AD_STORAGE_DIR . '/' . $bucket . '/*') ?: [] as $f) {
                if (is_file($f) && basename($f) !== '.gitkeep') @unlink($f);
            }
        }
        ad_json(['ok' => true]);
    }

    ad_json(['ok' => false, 'error' => 'Unknown action.'], 404);
} catch (Throwable $e) {
    error_log('[Anime Director Lab] ' . $e->getMessage());
    ad_json(['ok' => false, 'error' => ad_substr($e->getMessage(), 0, 500)], 500);
}
