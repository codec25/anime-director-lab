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
function addir_latest_take_for_shot(array $state, string $shotId): ?array {
    $shot = addir_find($state['shots'], $shotId);
    $selected = trim((string)($shot['selected_take_id'] ?? ''));
    if ($selected !== '') {
        $take = addir_find($state['takes'], $selected);
        if ($take) return $take;
    }
    $matches = array_values(array_filter($state['takes'], static fn(array $t): bool => (string)($t['shot_id'] ?? '') === $shotId));
    return $matches ? end($matches) : null;
}
function addir_prompt(array $shot, ?array $character): string {
    $parts = [];
    if ($character) {
        $parts[] = 'Preserve the same original anime character identity and recognizable face.';
        if (!empty($character['canonical_style'])) $parts[] = 'Visual style: ' . (string)$character['canonical_style'] . '.';
        if (!empty($character['outfit_notes'])) $parts[] = 'Keep wardrobe consistent: ' . (string)$character['outfit_notes'] . '.';
    }
    if (!empty($shot['continuity_from_shot_id'])) $parts[] = 'This shot continues directly from the previous shot. Preserve pose logic, screen direction, wardrobe, lighting logic and scene continuity.';
    if (!empty($shot['revision_notes'])) $parts[] = 'Revision request: ' . trim((string)$shot['revision_notes']) . '.';
    $parts[] = 'Shot direction: ' . trim((string)($shot['intent'] ?? '')) . '.';
    if (!empty($shot['direction'])) $parts[] = 'Director note: ' . trim((string)$shot['direction']) . '.';
    if (!empty($shot['camera_direction'])) $parts[] = 'Camera: ' . trim((string)$shot['camera_direction']) . '.';
    $refs = is_array($shot['references'] ?? null) ? $shot['references'] : [];
    if ($refs) $parts[] = 'Use the attached shot references as creative continuity guidance. Do not replace the locked character identity unless explicitly directed.';
    $boost = (string)($shot['anime_boost_mode'] ?? 'natural');
    if ($boost === 'anime') $parts[] = 'Use polished cinematic anime motion, controlled exaggeration, clear anticipation and impact without changing identity.';
    if ($boost === 'extreme') $parts[] = 'Use high-energy sakuga-inspired motion and dramatic camera energy while preserving character identity and readable anatomy.';
    return ad_substr(implode(' ', array_filter($parts)), 0, 1000);
}
function addir_apply_plan(array $shot, array $d): array {
    foreach (['title','intent','direction','camera_direction','ratio'] as $field) {
        if (array_key_exists($field, $d)) $shot[$field] = ad_substr(trim((string)$d[$field]), 0, $field === 'title' ? 120 : 1000);
    }
    if (isset($d['duration_target'])) $shot['duration_target'] = max(2, min(10, (float)$d['duration_target']));
    $boost = (string)($d['boost'] ?? $d['anime_boost_mode'] ?? ($shot['anime_boost_mode'] ?? 'natural'));
    if (in_array($boost, ['natural','anime','extreme'], true)) {
        $shot['anime_boost_mode'] = $boost;
        $shot['anime_boost'] = ad_default_anime_boost($boost);
    }
    $shot['updated_at'] = ad_now();
    return $shot;
}

try {
    if ($action === 'status') {
        ad_json(['ok' => true, 'providers' => ad_providers_for_capability('DESCRIBE_SHOT'), 'mock_mode' => ad_mock_mode()]);
    }

    if ($action === 'attach-reference' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $shotId = trim((string)($_POST['shot_id'] ?? ''));
        if ($shotId === '' || empty($_FILES['reference'])) ad_json(['ok' => false, 'error' => 'Shot and reference file are required.'], 422);
        $state = ad_state_read();
        if (!addir_find($state['shots'], $shotId)) ad_json(['ok' => false, 'error' => 'Shot not found.'], 404);
        $asset = ad_store_director_reference($_FILES['reference']);
        $next = ad_state_mutate(function(array $s) use ($shotId, $asset): array {
            foreach ($s['shots'] as &$shot) if (($shot['id'] ?? '') === $shotId) {
                $refs = is_array($shot['references'] ?? null) ? $shot['references'] : [];
                $refs[] = $asset;
                $shot['references'] = array_slice($refs, -8);
                $shot['updated_at'] = ad_now();
                break;
            }
            unset($shot); return $s;
        });
        ad_event('shot_reference_attached', ['shot_id' => $shotId, 'reference_id' => $asset['id'], 'kind' => $asset['kind']]);
        ad_json(['ok' => true, 'reference' => $asset, 'shot' => addir_find($next['shots'], $shotId)]);
    }

    if ($action === 'revise-shot' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = ad_json_input();
        $shotId = trim((string)($d['shot_id'] ?? ''));
        $state = ad_state_read();
        $source = addir_find($state['shots'], $shotId);
        if (!$source) ad_json(['ok' => false, 'error' => 'Shot not found.'], 404);
        $before = [
            'at' => ad_now(), 'title' => $source['title'] ?? '', 'intent' => $source['intent'] ?? '',
            'direction' => $source['direction'] ?? '', 'camera_direction' => $source['camera_direction'] ?? '',
            'ratio' => $source['ratio'] ?? '', 'anime_boost_mode' => $source['anime_boost_mode'] ?? 'natural',
        ];
        $next = ad_state_mutate(function(array $s) use ($shotId, $d, $before): array {
            foreach ($s['shots'] as &$shot) if (($shot['id'] ?? '') === $shotId) {
                $history = is_array($shot['revision_history'] ?? null) ? $shot['revision_history'] : [];
                $history[] = $before;
                $shot['revision_history'] = array_slice($history, -20);
                $shot = addir_apply_plan($shot, $d);
                $shot['revision_notes'] = ad_substr(trim((string)($d['revision_notes'] ?? $d['direction'] ?? '')), 0, 1000);
                $shot['selected_take_id'] = null;
                $shot['status'] = 'draft';
                break;
            }
            unset($shot); return $s;
        });
        ad_event('shot_revised', ['shot_id' => $shotId]);
        ad_json(['ok' => true, 'shot' => addir_find($next['shots'], $shotId)]);
    }

    if ($action === 'continue-shot' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = ad_json_input();
        $sourceId = trim((string)($d['shot_id'] ?? ''));
        $state = ad_state_read();
        $source = addir_find($state['shots'], $sourceId);
        if (!$source) ad_json(['ok' => false, 'error' => 'Source shot not found.'], 404);
        $sourceTake = addir_latest_take_for_shot($state, $sourceId);
        $number = count($state['shots']) + 1;
        $shot = $source;
        $shot['id'] = ad_id('shot');
        $shot['number'] = $number; $shot['shot_number'] = $number;
        $shot['title'] = ad_substr(trim((string)($d['title'] ?? ('Continue ' . ($source['title'] ?? 'shot')))), 0, 120);
        $shot['intent'] = ad_substr(trim((string)($d['intent'] ?? $d['direction'] ?? 'Continue the action naturally.')), 0, 1000);
        $shot['direction'] = ad_substr(trim((string)($d['direction'] ?? $shot['intent'])), 0, 1000);
        $shot = addir_apply_plan($shot, $d);
        $shot['continuity_from_shot_id'] = $sourceId;
        $shot['source_take_id'] = (string)($sourceTake['id'] ?? '');
        $shot['revision_history'] = [];
        $shot['selected_take_id'] = null;
        $shot['status'] = 'draft';
        $shot['created_at'] = ad_now(); $shot['updated_at'] = ad_now();
        $sceneId = (string)($source['scene_id'] ?? ($state['scenes'][0]['id'] ?? ''));
        $shot['scene_id'] = $sceneId;
        $next = ad_state_mutate(function(array $s) use ($shot, $sceneId): array {
            $s['shots'][] = $shot;
            foreach ($s['scenes'] as &$scene) if (($scene['id'] ?? '') === $sceneId) { $scene['shot_ids'][] = $shot['id']; $scene['updated_at'] = ad_now(); break; }
            unset($scene); return $s;
        });
        ad_event('shot_continued', ['source_shot_id' => $sourceId, 'shot_id' => $shot['id'], 'source_take_id' => $shot['source_take_id']]);
        ad_json(['ok' => true, 'shot' => addir_find($next['shots'], $shot['id'])]);
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
        if (!$meta || empty($meta['implemented']) || !in_array('DESCRIBE_SHOT', (array)$meta['capabilities'], true)) ad_json(['ok' => false, 'error' => 'Describe-shot provider is unavailable.'], 409);
        $provider = ad_provider_instance($providerId);
        if (!$provider->available()) ad_json(['ok' => false, 'error' => 'Provider key is not configured.'], 409);
        $accepted = ad_provider_accepted_attempts($state, $shotId, $providerId);
        if ($accepted >= 3) ad_json(['ok' => false, 'error' => 'Maximum 3 paid attempts reached for this provider and shot.'], 409);
        $visualRef = ad_shot_visual_reference($shot);
        $promptImage = trim((string)($visualRef['url'] ?? addir_master_url($character)));
        if (!ad_mock_mode() && $promptImage !== '') ad_require_live_public_https_url($promptImage);
        $attempt = $accepted + 1;
        $duration = max(2, min(10, (float)($shot['duration_target'] ?? 5)));
        $jobId = ad_id('job');
        $job = ad_normalize_job([
            'id'=>$jobId,'shot_id'=>$shotId,'performance_id'=>'','character_version_id'=>(string)($character['id']??''),
            'provider'=>$providerId,'model'=>(string)($meta['model']??'gen4.5'),'capability'=>'DESCRIBE_SHOT','attempt'=>$attempt,
            'status'=>'submitted','duration_seconds'=>$duration,'estimated_cost_usd'=>$provider->estimatedUsd($duration),'submitted_at'=>ad_now(),
            'metadata'=>['visual_reference_id'=>$visualRef['id']??null,'continuity_from_shot_id'=>$shot['continuity_from_shot_id']??null,'source_take_id'=>$shot['source_take_id']??null],
        ]);
        ad_state_mutate(function(array $s) use ($job,$shotId): array { $s['jobs'][]=$job; foreach($s['shots'] as &$shot) if(($shot['id']??'')===$shotId){$shot['status']='generating';$shot['updated_at']=ad_now();break;} unset($shot); return $s; });
        try {
            $submitted = $provider->submit(['character_url'=>$promptImage,'prompt_image'=>$promptImage,'prompt_text'=>addir_prompt($shot,$character),'duration_seconds'=>$duration,'ratio'=>(string)($shot['ratio']??'1280:720')]);
            $external = trim((string)($submitted['external_id'] ?? ''));
            if ($external === '') throw new RuntimeException('Provider returned no task id.');
            $updated = ad_state_mutate(function(array $s) use ($jobId,$external,$submitted): array { foreach($s['jobs'] as &$j) if(($j['id']??'')===$jobId){$j['provider_job_id']=$external;$j['external_id']=$external;$j['status']='submitted';$j['raw']=$submitted['raw']??null;break;} unset($j); return $s; });
            ad_event('describe_generation_submitted',['job_id'=>$jobId,'shot_id'=>$shotId,'provider'=>$providerId]);
            ad_json(['ok'=>true,'job'=>addir_find($updated['jobs'],$jobId),'estimated_cost_usd'=>$job['estimated_cost_usd']]);
        } catch (Throwable $e) {
            $safe=ad_safe_provider_error($e);
            ad_state_mutate(function(array $s) use ($jobId,$shotId,$safe): array { foreach($s['jobs'] as &$j) if(($j['id']??'')===$jobId){$j['status']='failed';$j['failed_at']=ad_now();$j['safe_error']=$safe['safe_error'];break;} unset($j); foreach($s['shots'] as &$shot) if(($shot['id']??'')===$shotId){$shot['status']='draft';$shot['updated_at']=ad_now();break;} unset($shot); return $s; });
            ad_json(['ok'=>false,'error'=>$safe['safe_error']],502);
        }
    }

    if ($action === 'poll' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d=ad_json_input();$jobId=trim((string)($d['job_id']??''));$state=ad_state_read();$job=addir_find($state['jobs'],$jobId);
        if(!$job||($job['capability']??'')!=='DESCRIBE_SHOT') ad_json(['ok'=>false,'error'=>'Describe generation job not found.'],404);
        if(in_array((string)$job['status'],['completed','failed','cancelled'],true)) ad_json(['ok'=>true,'job'=>$job,'done'=>true]);
        $external=trim((string)($job['provider_job_id']??$job['external_id']??''));if($external==='') ad_json(['ok'=>false,'error'=>'Provider task id is missing.'],409);
        $provider=ad_provider_instance((string)$job['provider']);
        try {
            $result=$provider->poll($external);$status=(string)($result['status']??'processing');
            if($status==='succeeded'){
                $remote=trim((string)($result['output_url']??''));$local=$remote!==''?ad_download_result($remote,$jobId):null;
                $take=ad_normalize_take(['id'=>ad_id('take'),'shot_id'=>(string)$job['shot_id'],'performance_id'=>'','generation_job_id'=>$jobId,'provider'=>(string)$job['provider'],'model'=>(string)$job['model'],'mode'=>'DESCRIBE_IT','remote_url'=>$remote,'local'=>$local,'attempt'=>(int)$job['attempt'],'mock'=>ad_mock_mode(),'created_at'=>ad_now()]);
                $next=ad_state_mutate(function(array $s) use ($jobId,$take): array { foreach($s['jobs'] as &$j) if(($j['id']??'')===$jobId){$j['status']='completed';$j['completed_at']=ad_now();break;} unset($j);$s['takes'][]=$take;foreach($s['shots'] as &$shot) if(($shot['id']??'')===($take['shot_id']??'')){$shot['status']='review';$shot['updated_at']=ad_now();break;}unset($shot);return $s;});
                ad_event('describe_generation_completed',['job_id'=>$jobId,'take_id'=>$take['id']]);ad_json(['ok'=>true,'job'=>addir_find($next['jobs'],$jobId),'take'=>$take,'done'=>true]);
            }
            if($status==='failed'){$message=ad_substr((string)($result['error']??'Generation failed.'),0,400);$next=ad_state_mutate(function(array $s) use($jobId,$message):array{foreach($s['jobs'] as &$j)if(($j['id']??'')===$jobId){$j['status']='failed';$j['failed_at']=ad_now();$j['safe_error']=$message;break;}unset($j);return $s;});ad_json(['ok'=>true,'job'=>addir_find($next['jobs'],$jobId),'done'=>true]);}
            $next=ad_state_mutate(function(array $s) use($jobId,$status):array{foreach($s['jobs'] as &$j)if(($j['id']??'')===$jobId){$j['status']=$status==='queued'?'queued':'processing';if(empty($j['started_at']))$j['started_at']=ad_now();break;}unset($j);return $s;});ad_json(['ok'=>true,'job'=>addir_find($next['jobs'],$jobId),'done'=>false]);
        } catch(Throwable $e){$safe=ad_safe_provider_error($e);ad_json(['ok'=>false,'error'=>$safe['safe_error']],502);}
    }

    ad_json(['ok'=>false,'error'=>'Unknown Director action.'],404);
} catch(Throwable $e){$safe=ad_safe_provider_error($e);ad_json(['ok'=>false,'error'=>$safe['safe_error']],500);}
