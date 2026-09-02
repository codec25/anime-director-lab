<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

$action = (string)($_GET['action'] ?? 'status');

function adcont_find(array $items, string $id): ?array {
    foreach ($items as $item) if ((string)($item['id'] ?? '') === $id) return $item;
    return null;
}

function adcont_take_url(array $take): string {
    return trim((string)($take['local']['url'] ?? $take['result_media']['local']['url'] ?? $take['remote_url'] ?? $take['result_media']['remote_url'] ?? ''));
}

function adcont_source_take(array $state, array $shot): ?array {
    $takeId = trim((string)($shot['source_take_id'] ?? ''));
    if ($takeId !== '') {
        $take = adcont_find($state['takes'], $takeId);
        if ($take && adcont_take_url($take) !== '') return $take;
    }
    $sourceShotId = trim((string)($shot['continuity_from_shot_id'] ?? ''));
    if ($sourceShotId === '') return null;
    $sourceShot = adcont_find($state['shots'], $sourceShotId);
    $selected = trim((string)($sourceShot['selected_take_id'] ?? ''));
    if ($selected !== '') {
        $take = adcont_find($state['takes'], $selected);
        if ($take && adcont_take_url($take) !== '') return $take;
    }
    $matches = array_values(array_filter($state['takes'], static fn(array $t): bool => (string)($t['shot_id'] ?? '') === $sourceShotId));
    foreach(array_reverse($matches) as $take) if(!empty($take['selected']) && adcont_take_url($take)!=='') return $take;
    for ($i=count($matches)-1;$i>=0;$i--) if (adcont_take_url($matches[$i]) !== '') return $matches[$i];
    return null;
}
function adcont_fail_job(string $jobId,string $error):array{return ad_state_mutate(function(array$s)use($jobId,$error):array{$shotId='';foreach($s['jobs']as&$j)if(($j['id']??'')===$jobId){$j['status']='failed';$j['failed_at']=ad_now();$j['safe_error']=$error;$j['error']=$error;$shotId=(string)($j['shot_id']??'');break;}unset($j);if($shotId!=='')foreach($s['shots']as&$shot)if(($shot['id']??'')===$shotId){$shot['status']=!empty($shot['selected_take_id'])?'review':'draft';$shot['updated_at']=ad_now();break;}unset($shot);return$s;});}

function adcont_prompt(array $shot): string {
    $parts = [
        'Continue directly from the supplied source video with seamless temporal and visual continuity.',
        'Preserve the same character identity, wardrobe, environment, lighting logic, screen direction and motion momentum unless the director explicitly changes them.',
        'Next action: ' . trim((string)($shot['intent'] ?? $shot['direction'] ?? 'Continue naturally.')),
    ];
    if (!empty($shot['direction'])) $parts[] = 'Director note: ' . trim((string)$shot['direction']);
    if (!empty($shot['camera_direction'])) $parts[] = 'Camera direction: ' . trim((string)$shot['camera_direction']);
    return ad_substr(implode(' ', $parts), 0, 1000);
}

try {
    if ($action === 'status') {
        ad_json(['ok'=>true,'providers'=>ad_providers_for_capability('CONTINUE_SHOT'),'mock_mode'=>ad_mock_mode()]);
    }

    if ($action === 'generate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = ad_json_input();
        $shotId = trim((string)($d['shot_id'] ?? ''));
        $state = ad_state_read();
        $shot = adcont_find($state['shots'], $shotId);
        if (!$shot) ad_json(['ok'=>false,'error'=>'Continuation shot not found.'],404);
        if (empty($shot['continuity_from_shot_id'])) ad_json(['ok'=>false,'error'=>'This shot is not linked to a previous shot.'],409);
        $take = adcont_source_take($state, $shot);
        if (!$take) ad_json(['ok'=>false,'error'=>'Generate or select a take on the previous shot first. Native continuation needs the actual source video.'],409);
        $video = adcont_take_url($take);
        if (!ad_mock_mode()) ad_require_live_public_https_url($video);
        $providerId = 'runway_seedance25_extend';
        $provider = ad_provider_instance($providerId);
        if (!$provider->available()) ad_json(['ok'=>false,'error'=>'Native continuation provider is not configured.'],409);
        if (ad_provider_accepted_attempts($state,$shotId,$providerId) >= 3) ad_json(['ok'=>false,'error'=>'Maximum 3 continuation attempts reached for this shot.'],409);
        $duration = max(4,min(30,(float)($shot['duration_target'] ?? 5)));
        $jobId = ad_id('job');
        $job = ad_normalize_job([
            'id'=>$jobId,'shot_id'=>$shotId,'performance_id'=>'','character_version_id'=>(string)($shot['character_id'] ?? ''),
            'provider'=>$providerId,'model'=>'seedance2_5','capability'=>'CONTINUE_SHOT','attempt'=>ad_provider_accepted_attempts($state,$shotId,$providerId)+1,
            'status'=>'submitted','duration_seconds'=>$duration,'estimated_cost_usd'=>$provider->estimatedUsd($duration),'submitted_at'=>ad_now(),
            'metadata'=>['source_take_id'=>$take['id'] ?? null,'source_video_url'=>$video,'source_shot_id'=>$shot['continuity_from_shot_id'] ?? null],
        ]);
        ad_state_mutate(function(array $s) use ($job,$shotId): array {
            $s['jobs'][]=$job;
            foreach($s['shots'] as &$shot) if(($shot['id']??'')===$shotId){$shot['status']='generating';$shot['updated_at']=ad_now();break;}
            unset($shot);return $s;
        });
        try {
            $submitted=$provider->submit(['prompt_video'=>$video,'prompt_text'=>adcont_prompt($shot),'duration_seconds'=>$duration]);
            $external=trim((string)($submitted['external_id']??''));
            if($external==='') throw new RuntimeException('Provider returned no continuation task id.');
            $updated=ad_state_mutate(function(array $s) use($jobId,$external,$submitted):array{foreach($s['jobs'] as &$j)if(($j['id']??'')===$jobId){$j['provider_job_id']=$external;$j['external_id']=$external;$j['raw']=$submitted['raw']??null;break;}unset($j);return $s;});
            ad_event('native_continuation_submitted',['job_id'=>$jobId,'shot_id'=>$shotId,'source_take_id'=>$take['id']??null]);
            ad_json(['ok'=>true,'job'=>adcont_find($updated['jobs'],$jobId),'estimated_cost_usd'=>$job['estimated_cost_usd'],'source_take_id'=>$take['id']??null]);
        } catch(Throwable $e) {
            $safe=ad_safe_provider_error($e);adcont_fail_job($jobId,$safe['safe_error']);
            ad_json(['ok'=>false,'error'=>$safe['safe_error']],502);
        }
    }

    if ($action === 'poll' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d=ad_json_input();$jobId=trim((string)($d['job_id']??''));$state=ad_state_read();$job=adcont_find($state['jobs'],$jobId);
        if(!$job||($job['capability']??'')!=='CONTINUE_SHOT') ad_json(['ok'=>false,'error'=>'Continuation job not found.'],404);
        if(in_array((string)$job['status'],['completed','failed','cancelled'],true)) ad_json(['ok'=>true,'job'=>$job,'done'=>true]);
        $external=trim((string)($job['provider_job_id']??$job['external_id']??''));
        if($external==='') ad_json(['ok'=>false,'error'=>'Provider task id is missing.'],409);
        $provider=ad_provider_instance((string)$job['provider']);
        try {
            $result=$provider->poll($external);$status=(string)($result['status']??'processing');
            if($status==='succeeded'){
                $remote=trim((string)($result['output_url']??''));$local=$remote!==''?ad_download_result($remote,$jobId):null;
                $take=ad_normalize_take(['id'=>ad_id('take'),'shot_id'=>(string)$job['shot_id'],'performance_id'=>'','generation_job_id'=>$jobId,'job_id'=>$jobId,'provider'=>(string)$job['provider'],'model'=>(string)$job['model'],'mode'=>'CONTINUE_SHOT','remote_url'=>$remote,'local'=>$local,'attempt'=>(int)$job['attempt'],'mock'=>!empty($result['mock']),'created_at'=>ad_now()]);
                $next=ad_state_mutate(function(array $s)use($jobId,$take,$result):array{foreach($s['jobs']as &$j)if(($j['id']??'')===$jobId){$j['status']='completed';$j['completed_at']=ad_now();$j['raw']=$result['raw']??null;break;}unset($j);$autoSelect=false;foreach($s['shots']as&$shot)if(($shot['id']??'')===($take['shot_id']??'')){$autoSelect=empty($shot['selected_take_id']);if($autoSelect)$shot['selected_take_id']=$take['id'];$shot['status']='review';$shot['updated_at']=ad_now();break;}unset($shot);$take['selected']=$autoSelect;$s['takes'][]=$take;return $s;});
                $stored=adcont_find($next['takes'],$take['id'])??$take;ad_event('native_continuation_completed',['job_id'=>$jobId,'take_id'=>$take['id'],'shot_id'=>$take['shot_id']]);
                ad_json(['ok'=>true,'job'=>adcont_find($next['jobs'],$jobId),'take'=>$stored,'done'=>true]);
            }
            if($status==='failed'){
                $error=ad_substr((string)($result['error']??'Continuation failed.'),0,400);
                $next=adcont_fail_job($jobId,$error);
                ad_json(['ok'=>true,'job'=>adcont_find($next['jobs'],$jobId),'done'=>true,'failed'=>true]);
            }
            $next=ad_state_mutate(function(array $s)use($jobId,$status,$result):array{foreach($s['jobs']as &$j)if(($j['id']??'')===$jobId){$j['status']=$status;$j['raw']=$result['raw']??null;break;}unset($j);return $s;});
            ad_json(['ok'=>true,'job'=>adcont_find($next['jobs'],$jobId),'done'=>false]);
        }catch(Throwable $e){$safe=ad_safe_provider_error($e);adcont_fail_job($jobId,$safe['safe_error']);ad_json(['ok'=>false,'error'=>$safe['safe_error']],502);}
    }

    ad_json(['ok'=>false,'error'=>'Unknown continuation action.'],404);
} catch(Throwable $e) {
    $safe=ad_safe_provider_error($e);ad_json(['ok'=>false,'error'=>$safe['safe_error']],500);
}
