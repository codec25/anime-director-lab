<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

$action = (string)($_GET['action'] ?? 'state');

function ad_provider(string $id): ADProvider {
    return match($id) {
        'runway_act_two' => new RunwayProvider(),
        'vidu_motion_2_5' => new ViduProvider(),
        default => throw new InvalidArgumentException('Unknown provider.'),
    };
}

function ad_find(array $items, string $id): ?array {
    foreach ($items as $item) if ((string)($item['id'] ?? '') === $id) return $item;
    return null;
}

try {
    if ($action === 'state') {
        $state = ad_state_read();
        ad_json(['ok' => true, 'state' => $state, 'config' => [
            'mock_mode' => ad_mock_mode(),
            'base_url' => ad_base_url(),
            'providers' => [
                ['id'=>'runway_act_two','label'=>'Runway Act-Two','available'=>(new RunwayProvider())->available(),'cost_per_second_usd'=>0.05,'best_for'=>'Acting / gesture / dialogue'],
                ['id'=>'vidu_motion_2_5','label'=>'Vidu Motion Sync 2.5','available'=>(new ViduProvider())->available(),'cost_per_second_usd'=>0.17,'best_for'=>'Full-body movement'],
                ['id'=>'wan2_2_animate','label'=>'Wan2.2-Animate-14B','available'=>false,'cost_per_second_usd'=>null,'best_for'=>'Open-source benchmark — external GPU runner later'],
            ],
        ]]);
    }

    if ($action === 'save-character' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_FILES['image'])) ad_json(['ok'=>false,'error'=>'Character image is required.'],422);
        $asset = ad_store_upload($_FILES['image'], 'characters');
        $name = trim((string)($_POST['name'] ?? 'Akio')) ?: 'Akio';
        $version = trim((string)($_POST['version'] ?? 'v1')) ?: 'v1';
        $notes = trim((string)($_POST['notes'] ?? ''));
        $character = [
            'id' => ad_id('char'), 'name' => ad_substr($name,0,80), 'version' => ad_substr($version,0,40),
            'notes' => ad_substr($notes,0,1200), 'asset' => $asset, 'created_at' => ad_now(),
        ];
        ad_state_mutate(function(array $state) use ($character): array { $state['character'] = $character; return $state; });
        ad_event('character_locked', ['character_id'=>$character['id']]);
        ad_json(['ok'=>true,'character'=>$character]);
    }

    if ($action === 'upload-performance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_FILES['video'])) ad_json(['ok'=>false,'error'=>'Performance video is required.'],422);
        $asset = ad_store_upload($_FILES['video'], 'performances');
        $duration = max(0.1, min(30, (float)($_POST['duration_seconds'] ?? 5)));
        $performance = [
            'id' => ad_id('perf'),
            'code' => ad_substr(trim((string)($_POST['code'] ?? 'A1')),0,20),
            'label' => ad_substr(trim((string)($_POST['label'] ?? 'Performance')),0,100),
            'track' => in_array((string)($_POST['track'] ?? 'acting'), ['acting','full_body'], true) ? (string)$_POST['track'] : 'acting',
            'duration_seconds' => $duration,
            'notes' => ad_substr(trim((string)($_POST['notes'] ?? '')),0,1000),
            'asset' => $asset,
            'created_at' => ad_now(),
        ];
        ad_state_mutate(function(array $state) use ($performance): array { $state['performances'][]=$performance; return $state; });
        ad_event('performance_uploaded', ['performance_id'=>$performance['id']]);
        ad_json(['ok'=>true,'performance'=>$performance]);
    }

    if ($action === 'create-shot' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = ad_json_input();
        $state = ad_state_read();
        if (!$state['character']) ad_json(['ok'=>false,'error'=>'Lock a character first.'],409);
        $performanceId = (string)($d['performance_id'] ?? '');
        $performance = ad_find($state['performances'], $performanceId);
        if (!$performance) ad_json(['ok'=>false,'error'=>'Choose a valid performance.'],422);
        $boost = in_array((string)($d['boost'] ?? 'natural'), ['natural','anime','extreme'], true) ? (string)$d['boost'] : 'natural';
        $shot = [
            'id'=>ad_id('shot'), 'number'=>count($state['shots'])+1,
            'character_id'=>$state['character']['id'], 'performance_id'=>$performanceId,
            'intent'=>ad_substr(trim((string)($d['intent'] ?? $performance['label'])),0,600),
            'ratio'=>in_array((string)($d['ratio'] ?? '1280:720'), ['1280:720','720:1280','960:960','1104:832','832:1104'], true) ? (string)$d['ratio'] : '1280:720',
            'anime_boost'=>$boost,
            'boost_recipe'=>[ 'motion_exaggeration'=>$boost==='natural'?10:($boost==='anime'?55:90), 'impact'=>$boost==='natural'?5:($boost==='anime'?65:100), 'camera_reaction'=>$boost==='natural'?5:($boost==='anime'?45:85), 'vfx'=>$boost==='natural'?0:($boost==='anime'?45:90) ],
            'selected_take_id'=>null, 'created_at'=>ad_now(),
        ];
        ad_state_mutate(function(array $state) use ($shot): array { $state['shots'][]=$shot; return $state; });
        ad_json(['ok'=>true,'shot'=>$shot]);
    }

    if ($action === 'generate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = ad_json_input(); $state = ad_state_read();
        $shot = ad_find($state['shots'], (string)($d['shot_id'] ?? ''));
        if (!$shot) ad_json(['ok'=>false,'error'=>'Shot not found.'],404);
        $performance = ad_find($state['performances'], (string)$shot['performance_id']);
        $character = $state['character'];
        if (!$performance || !$character) ad_json(['ok'=>false,'error'=>'Character/performance is missing.'],409);
        $providerId = (string)($d['provider'] ?? ''); $provider = ad_provider($providerId);
        if (!$provider->available()) ad_json(['ok'=>false,'error'=>'Provider key is not configured.'],409);
        $attempt = 1;
        foreach ($state['jobs'] as $j) if (($j['shot_id']??'')===$shot['id'] && ($j['provider']??'')===$providerId) $attempt=max($attempt,(int)($j['attempt']??0)+1);
        if ($attempt > 3) ad_json(['ok'=>false,'error'=>'Benchmark limit reached: maximum 3 attempts per provider per shot.'],409);
        $jobId=ad_id('job');
        $job=[
            'id'=>$jobId,'shot_id'=>$shot['id'],'provider'=>$providerId,'attempt'=>$attempt,'status'=>'submitting',
            'external_id'=>'','duration_seconds'=>(float)$performance['duration_seconds'],'estimated_cost_usd'=>$provider->estimatedUsd((float)$performance['duration_seconds']),
            'submitted_at'=>ad_now(),'completed_at'=>null,'error'=>null,'raw'=>null,
        ];
        ad_state_mutate(function(array $state) use($job): array{$state['jobs'][]=$job;return $state;});
        try {
            $submitted=$provider->submit([
                'job_id'=>$jobId,'character_url'=>$character['asset']['url'],'performance_url'=>$performance['asset']['url'],
                'duration_seconds'=>$performance['duration_seconds'],'ratio'=>$shot['ratio'],'anime_boost'=>$shot['anime_boost'],
            ]);
            ad_state_mutate(function(array $state) use($jobId,$submitted): array { foreach($state['jobs'] as &$j){if($j['id']===$jobId){$j['external_id']=$submitted['external_id'];$j['status']='queued';$j['raw']=$submitted['raw']??null;break;}} unset($j); return $state;});
            ad_event('generation_submitted',['job_id'=>$jobId,'provider'=>$providerId,'attempt'=>$attempt]);
            ad_json(['ok'=>true,'job_id'=>$jobId,'attempt'=>$attempt]);
        } catch(Throwable $e) {
            ad_state_mutate(function(array $state) use($jobId,$e): array { foreach($state['jobs'] as &$j){if($j['id']===$jobId){$j['status']='failed';$j['error']=ad_substr($e->getMessage(),0,500);$j['completed_at']=ad_now();break;}} unset($j); return $state;});
            ad_json(['ok'=>false,'error'=>$e->getMessage(),'job_id'=>$jobId],502);
        }
    }

    if ($action === 'poll' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d=ad_json_input();$jobId=(string)($d['job_id']??'');$state=ad_state_read();$job=ad_find($state['jobs'],$jobId);
        if(!$job) ad_json(['ok'=>false,'error'=>'Job not found.'],404);
        if(in_array($job['status'],['succeeded','failed'],true)) ad_json(['ok'=>true,'job'=>$job]);
        $provider=ad_provider((string)$job['provider']);
        $result=$provider->poll((string)$job['external_id']);
        $take=null;
        ad_state_mutate(function(array $state) use($jobId,$result,&$take): array {
            foreach($state['jobs'] as &$j){ if($j['id']!==$jobId) continue; $j['status']=$result['status'];$j['raw']=$result['raw']??$j['raw']; if(isset($result['error']))$j['error']=ad_substr((string)$result['error'],0,500); if(in_array($result['status'],['succeeded','failed'],true))$j['completed_at']=ad_now(); break;} unset($j);
            if($result['status']==='succeeded'){
                foreach($state['takes'] as $existing){if(($existing['job_id']??'')===$jobId){$take=$existing;return $state;}}
                $jobRow=ad_find($state['jobs'],$jobId);$remote=(string)($result['output_url']??'');$local=null;
                if($remote!=='' && !ad_mock_mode()) $local=ad_download_result($remote,$jobId);
                $take=['id'=>ad_id('take'),'job_id'=>$jobId,'shot_id'=>$jobRow['shot_id'],'provider'=>$jobRow['provider'],'attempt'=>$jobRow['attempt'],'remote_url'=>$remote,'local'=>$local,'mock'=>ad_mock_mode(),'selected'=>false,'created_at'=>ad_now()];
                $state['takes'][]=$take;
            }
            return $state;
        });
        ad_json(['ok'=>true,'status'=>$result['status'],'take'=>$take]);
    }

    if ($action === 'score' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d=ad_json_input();$takeId=(string)($d['take_id']??'');$state=ad_state_read();$take=ad_find($state['takes'],$takeId);if(!$take)ad_json(['ok'=>false,'error'=>'Take not found.'],404);
        $keys=['identity','face','hair','costume','proportions','motion_fidelity','timing_fidelity','hands','feet','expression','style','stability'];
        $ratings=[]; foreach($keys as $key){$v=(float)($d['ratings'][$key]??0);if($v<1||$v>5)ad_json(['ok'=>false,'error'=>'All benchmark scores must be 1–5.'],422);$ratings[$key]=$v;}
        $cps=round(($ratings['identity']+$ratings['face']+$ratings['hair']+$ratings['costume']+$ratings['proportions'])/5,2);
        $pps=round(($ratings['motion_fidelity']+$ratings['timing_fidelity']+$ratings['hands']+$ratings['feet']+$ratings['expression'])/5,2);
        $dus=round(($cps+$pps+$ratings['style']+$ratings['stability'])/4,2);
        $score=['id'=>ad_id('score'),'take_id'=>$takeId,'ratings'=>$ratings,'usable'=>!empty($d['usable']),'cps'=>$cps,'pps'=>$pps,'dus'=>$dus,'notes'=>ad_substr(trim((string)($d['notes']??'')),0,1000),'created_at'=>ad_now()];
        ad_state_mutate(function(array $state) use($score,$takeId): array{$state['scores']=array_values(array_filter($state['scores'],fn($s)=>($s['take_id']??'')!==$takeId));$state['scores'][]=$score;return $state;});
        ad_json(['ok'=>true,'score'=>$score]);
    }

    if ($action === 'select-take' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d=ad_json_input();$takeId=(string)($d['take_id']??'');$state=ad_state_read();$take=ad_find($state['takes'],$takeId);if(!$take)ad_json(['ok'=>false,'error'=>'Take not found.'],404);
        ad_state_mutate(function(array $state) use($takeId,$take): array{foreach($state['takes'] as &$t){if(($t['shot_id']??'')===$take['shot_id'])$t['selected']=$t['id']===$takeId;}unset($t);foreach($state['shots'] as &$s){if($s['id']===$take['shot_id'])$s['selected_take_id']=$takeId;}unset($s);return $state;});
        ad_event('take_selected',['take_id'=>$takeId,'shot_id'=>$take['shot_id']]);ad_json(['ok'=>true]);
    }

    if ($action === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $token=(string)($_POST['confirm']??'');if($token!=='RESET')ad_json(['ok'=>false,'error'=>'Reset confirmation missing.'],422);
        @unlink(ad_state_path()); foreach(['characters','performances','results'] as $bucket){foreach(glob(AD_STORAGE_DIR.'/'.$bucket.'/*')?:[] as $f){if(is_file($f)&&basename($f)!=='.gitkeep')@unlink($f);}}
        ad_json(['ok'=>true]);
    }
    ad_json(['ok'=>false,'error'=>'Unknown action.'],404);
} catch(Throwable $e) {
    error_log('[Anime Director Lab] '.$e->getMessage());
    ad_json(['ok'=>false,'error'=>ad_substr($e->getMessage(),0,500)],500);
}
