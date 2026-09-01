<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

$action = (string)($_GET['action'] ?? 'status');

function addir_find(array $items, string $id): ?array {
    foreach ($items as $item) if ((string)($item['id'] ?? '') === $id) return $item;
    return null;
}

function addir_master_url(?array $character): string {
    if (!$character) return '';
    $master = $character['references']['master_front'] ?? $character['asset'] ?? null;
    return is_array($master) ? trim((string)($master['url'] ?? '')) : '';
}

function addir_prompt(array $shot, ?array $character): string {
    $parts = [];
    if ($character) {
        $parts[] = 'Preserve the same original anime character identity and recognizable face.';
        if (!empty($character['canonical_style'])) $parts[] = 'Visual style: ' . (string)$character['canonical_style'] . '.';
        if (!empty($character['outfit_notes'])) $parts[] = 'Keep wardrobe consistent: ' . (string)$character['outfit_notes'] . '.';
    }
    $parts[] = 'Shot direction: ' . trim((string)($shot['intent'] ?? '')) . '.';
    if (!empty($shot['direction'])) $parts[] = 'Director note: ' . trim((string)$shot['direction']) . '.';
    if (!empty($shot['camera_direction'])) $parts[] = 'Camera: ' . trim((string)$shot['camera_direction']) . '.';
    $boost = (string)($shot['anime_boost_mode'] ?? 'natural');
    if ($boost === 'anime') $parts[] = 'Use polished cinematic anime motion, controlled exaggeration, clear anticipation and impact without changing identity.';
    if ($boost === 'extreme') $parts[] = 'Use high-energy sakuga-inspired motion and dramatic camera energy while preserving character identity and readable anatomy.';
    return ad_substr(implode(' ', array_filter($parts)), 0, 1000);
}

try {
    if ($action === 'status') {
        $providers = ad_providers_for_capability('DESCRIBE_SHOT');
        ad_json(['ok' => true, 'providers' => $providers, 'mock_mode' => ad_mock_mode()]);
    }

    if ($action === 'generate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = ad_json_input();
        $shotId = trim((string)($d['shot_id'] ?? ''));
        $providerId = trim((string)($d['provider'] ?? 'runway_gen45')) ?: 'runway_gen45';
        $state = ad_state_read();
        $shot = addir_find($state['shots'], $shotId);
        if (!$shot) ad_json(['ok' => false, 'error' => 'Shot not found.'], 404);
        if (($shot['generation_mode'] ?? '') !== 'DESCRIBE_IT') ad_json(['ok' => false, 'error' => 'This endpoint only generates DESCRIBE_IT shots.'], 409);
        $character = is_array($state['character'] ?? null) ? $state['character'] : null;
        if (!$character) ad_json(['ok' => false, 'error' => 'Lock a character first.'], 409);
        $meta = ad_provider_registry()[$providerId] ?? null;
        if (!$meta || empty($meta['implemented']) || !in_array('DESCRIBE_SHOT', (array)$meta['capabilities'], true)) {
            ad_json(['ok' => false, 'error' => 'Describe-shot provider is unavailable.'], 409);
        }
        $provider = ad_provider_instance($providerId);
        if (!$provider->available()) ad_json(['ok' => false, 'error' => 'Provider key is not configured.'], 409);
        $accepted = ad_provider_accepted_attempts($state, $shotId, $providerId);
        if ($accepted >= 3) ad_json(['ok' => false, 'error' => 'Maximum 3 paid attempts reached for this provider and shot.'], 409);
        $characterUrl = addir_master_url($character);
        if (!ad_mock_mode() && $characterUrl !== '') ad_require_live_public_https_url($characterUrl);
        $attempt = $accepted + 1;
        $duration = max(2, min(10, (float)($shot['duration_target'] ?? 5)));
        $jobId = ad_id('job');
        $job = ad_normalize_job([
            'id' => $jobId,
            'shot_id' => $shotId,
            'performance_id' => '',
            'character_version_id' => (string)($character['id'] ?? ''),
            'provider' => $providerId,
            'model' => (string)($meta['model'] ?? 'gen4.5'),
            'capability' => 'DESCRIBE_SHOT',
            'attempt' => $attempt,
            'status' => 'submitted',
            'duration_seconds' => $duration,
            'estimated_cost_usd' => $provider->estimatedUsd($duration),
            'submitted_at' => ad_now(),
        ]);
        ad_state_mutate(function(array $s) use ($job, $shotId): array {
            $s['jobs'][] = $job;
            foreach ($s['shots'] as &$shot) if (($shot['id'] ?? '') === $shotId) { $shot['status'] = 'generating'; $shot['updated_at'] = ad_now(); break; }
            unset($shot);
            return $s;
        });
        try {
            $submitted = $provider->submit([
                'character_url' => $characterUrl,
                'prompt_text' => addir_prompt($shot, $character),
                'duration_seconds' => $duration,
                'ratio' => (string)($shot['ratio'] ?? '1280:720'),
            ]);
            $external = trim((string)($submitted['external_id'] ?? ''));
            if ($external === '') throw new RuntimeException('Provider returned no task id.');
            $updated = ad_state_mutate(function(array $s) use ($jobId, $external, $submitted): array {
                foreach ($s['jobs'] as &$j) if (($j['id'] ?? '') === $jobId) {
                    $j['provider_job_id'] = $external; $j['external_id'] = $external; $j['status'] = 'submitted'; $j['raw'] = $submitted['raw'] ?? null; break;
                }
                unset($j); return $s;
            });
            ad_event('describe_generation_submitted', ['job_id' => $jobId, 'shot_id' => $shotId, 'provider' => $providerId]);
            ad_json(['ok' => true, 'job' => addir_find($updated['jobs'], $jobId), 'estimated_cost_usd' => $job['estimated_cost_usd']]);
        } catch (Throwable $e) {
            $safe = ad_safe_provider_error($e);
            ad_state_mutate(function(array $s) use ($jobId, $shotId, $safe): array {
                foreach ($s['jobs'] as &$j) if (($j['id'] ?? '') === $jobId) { $j['status'] = 'failed'; $j['failed_at'] = ad_now(); $j['safe_error'] = $safe['safe_error']; break; }
                unset($j);
                foreach ($s['shots'] as &$shot) if (($shot['id'] ?? '') === $shotId) { $shot['status'] = 'draft'; $shot['updated_at'] = ad_now(); break; }
                unset($shot); return $s;
            });
            ad_json(['ok' => false, 'error' => $safe['safe_error']], 502);
        }
    }

    if ($action === 'poll' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = ad_json_input();
        $jobId = trim((string)($d['job_id'] ?? ''));
        $state = ad_state_read();
        $job = addir_find($state['jobs'], $jobId);
        if (!$job || ($job['capability'] ?? '') !== 'DESCRIBE_SHOT') ad_json(['ok' => false, 'error' => 'Describe generation job not found.'], 404);
        if (in_array((string)$job['status'], ['completed','failed','cancelled'], true)) ad_json(['ok' => true, 'job' => $job, 'done' => true]);
        $external = trim((string)($job['provider_job_id'] ?? $job['external_id'] ?? ''));
        if ($external === '') ad_json(['ok' => false, 'error' => 'Provider task id is missing.'], 409);
        $provider = ad_provider_instance((string)$job['provider']);
        try {
            $result = $provider->poll($external);
            $status = (string)($result['status'] ?? 'processing');
            if ($status === 'succeeded') {
                $remote = trim((string)($result['output_url'] ?? ''));
                $local = $remote !== '' ? ad_download_result($remote, $jobId) : null;
                $take = ad_normalize_take([
                    'id' => ad_id('take'), 'shot_id' => (string)$job['shot_id'], 'performance_id' => '',
                    'generation_job_id' => $jobId, 'provider' => (string)$job['provider'], 'model' => (string)$job['model'],
                    'mode' => 'DESCRIBE_IT', 'remote_url' => $remote, 'local' => $local, 'attempt' => (int)$job['attempt'],
                    'mock' => ad_mock_mode(), 'created_at' => ad_now(),
                ]);
                $next = ad_state_mutate(function(array $s) use ($jobId, $take): array {
                    foreach ($s['jobs'] as &$j) if (($j['id'] ?? '') === $jobId) { $j['status'] = 'completed'; $j['completed_at'] = ad_now(); break; }
                    unset($j); $s['takes'][] = $take;
                    foreach ($s['shots'] as &$shot) if (($shot['id'] ?? '') === ($take['shot_id'] ?? '')) { $shot['status'] = 'review'; $shot['updated_at'] = ad_now(); break; }
                    unset($shot); return $s;
                });
                ad_event('describe_generation_completed', ['job_id' => $jobId, 'take_id' => $take['id']]);
                ad_json(['ok' => true, 'job' => addir_find($next['jobs'], $jobId), 'take' => $take, 'done' => true]);
            }
            if ($status === 'failed') {
                $message = ad_substr((string)($result['error'] ?? 'Generation failed.'), 0, 400);
                $next = ad_state_mutate(function(array $s) use ($jobId, $message): array {
                    foreach ($s['jobs'] as &$j) if (($j['id'] ?? '') === $jobId) { $j['status'] = 'failed'; $j['failed_at'] = ad_now(); $j['safe_error'] = $message; break; }
                    unset($j); return $s;
                });
                ad_json(['ok' => true, 'job' => addir_find($next['jobs'], $jobId), 'done' => true]);
            }
            $next = ad_state_mutate(function(array $s) use ($jobId, $status): array {
                foreach ($s['jobs'] as &$j) if (($j['id'] ?? '') === $jobId) { $j['status'] = $status === 'queued' ? 'queued' : 'processing'; if (empty($j['started_at'])) $j['started_at'] = ad_now(); break; }
                unset($j); return $s;
            });
            ad_json(['ok' => true, 'job' => addir_find($next['jobs'], $jobId), 'done' => false]);
        } catch (Throwable $e) {
            $safe = ad_safe_provider_error($e); ad_json(['ok' => false, 'error' => $safe['safe_error']], 502);
        }
    }

    ad_json(['ok' => false, 'error' => 'Unknown Director action.'], 404);
} catch (Throwable $e) {
    $safe = ad_safe_provider_error($e);
    ad_json(['ok' => false, 'error' => $safe['safe_error']], 500);
}
