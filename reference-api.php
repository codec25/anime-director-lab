<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

$action = (string)($_GET['action'] ?? 'status');

function adref_find(array $items, string $id): ?array { foreach ($items as $item) if ((string)($item['id'] ?? '') === $id) return $item; return null; }
function adref_master(?array $character): ?array {
    if (!$character) return null;
    $ref = $character['references']['master_front'] ?? $character['asset'] ?? null;
    return is_array($ref) ? $ref : null;
}
function adref_collect(array $state, array $shot): array {
    $images=[]; $videos=[]; $audio=[]; $seen=[];
    $push = static function(?array $r) use (&$images,&$videos,&$audio,&$seen): void {
        if (!$r || empty($r['url'])) return;
        $url=trim((string)$r['url']); if($url===''||isset($seen[$url]))return; $seen[$url]=true;
        $kind=(string)($r['kind'] ?? (str_starts_with((string)($r['mime']??''),'image/')?'image':''));
        if($kind==='image' && count($images)<30)$images[]=['uri'=>$url];
        elseif($kind==='video' && count($videos)<10)$videos[]=['uri'=>$url];
        elseif($kind==='audio' && count($audio)<10)$audio[]=['uri'=>$url];
    };
    $push(adref_master(is_array($state['character']??null)?$state['character']:null));
    foreach((array)($state['world']['references']??[]) as $r) if(is_array($r))$push($r);
    foreach((array)($shot['references']??[]) as $r) if(is_array($r))$push($r);
    return ['images'=>$images,'videos'=>$videos,'audio'=>$audio];
}
function adref_prompt(array $state,array $shot,array $refs): string {
    $c=is_array($state['character']??null)?$state['character']:null; $w=is_array($state['world']??null)?$state['world']:[]; $parts=[];
    if($c){$parts[]='Keep the locked original anime character identity consistent.';if(!empty($c['canonical_style']))$parts[]='Character style: '.$c['canonical_style'].'.';if(!empty($c['outfit_notes']))$parts[]='Wardrobe: '.$c['outfit_notes'].'.';}
    $worldBits=[];
    foreach(['location'=>'Location','environment'=>'Environment','lighting'=>'Lighting','palette'=>'Palette','weather'=>'Weather','time_of_day'=>'Time','style_rules'=>'World style','continuity_rules'=>'Continuity'] as $k=>$label){if(trim((string)($w[$k]??''))!=='')$worldBits[]=$label.': '.trim((string)$w[$k]);}
    if($worldBits)$parts[]='Persistent world memory — '.implode('; ',$worldBits).'.';
    $parts[]='Shot direction: '.trim((string)($shot['intent']??$shot['direction']??'')).'.';
    if(!empty($shot['camera_direction']))$parts[]='Camera: '.trim((string)$shot['camera_direction']).'.';
    if(!empty($shot['revision_notes']))$parts[]='Revision: '.trim((string)$shot['revision_notes']).'.';
    $imageCount=count($refs['images']);$videoCount=count($refs['videos']);$audioCount=count($refs['audio']);
    if($imageCount)$parts[]='Use the image references to preserve character identity, environment design, props and visual style according to the direction above.';
    if($videoCount)$parts[]='Use video references for motion, timing, staging or scene behavior without replacing the locked identity.';
    if($audioCount)$parts[]='Use audio references as sound, rhythm, vocal or atmosphere guidance where relevant.';
    $boost=(string)($shot['anime_boost_mode']??'natural');
    if($boost==='anime')$parts[]='Polished cinematic anime motion with readable anticipation, impact and follow-through.';
    elseif($boost==='extreme')$parts[]='High-energy sakuga-inspired animation while preserving anatomy and identity.';
    return ad_substr(implode(' ',array_filter($parts)),0,15000);
}

try {
    if($action==='status') ad_json(['ok'=>true,'providers'=>ad_providers_for_capability('MULTI_REFERENCE'),'mock_mode'=>ad_mock_mode()]);

    if($action==='generate' && $_SERVER['REQUEST_METHOD']==='POST'){
        $d=ad_json_input();$shotId=trim((string)($d['shot_id']??''));$state=ad_state_read();$shot=adref_find((array)$state['shots'],$shotId);
        if(!$shot)ad_json(['ok'=>false,'error'=>'Shot not found.'],404);
        $providerId='runway_seedance25_reference';$provider=ad_provider_instance($providerId);if(!$provider->available())ad_json(['ok'=>false,'error'=>'Reference provider is not configured.'],409);
        $accepted=ad_provider_accepted_attempts($state,$shotId,$providerId);if($accepted>=3)ad_json(['ok'=>false,'error'=>'Maximum 3 paid reference attempts reached for this shot.'],409);
        $refs=adref_collect($state,$shot);if(!$refs['images']&&!$refs['videos']&&!$refs['audio'])ad_json(['ok'=>false,'error'=>'No character, world, or shot references are available.'],409);
        foreach(array_merge($refs['images'],$refs['videos'],$refs['audio']) as $r)if(!ad_mock_mode())ad_require_live_public_https_url((string)$r['uri']);
        $duration=max(4,min(30,(float)($shot['duration_target']??5)));$jobId=ad_id('job');$meta=ad_provider_registry()[$providerId];
        $job=ad_normalize_job(['id'=>$jobId,'shot_id'=>$shotId,'performance_id'=>'','character_version_id'=>(string)($state['character']['id']??''),'provider'=>$providerId,'model'=>'seedance2_5','capability'=>'MULTI_REFERENCE','attempt'=>$accepted+1,'status'=>'submitted','duration_seconds'=>$duration,'estimated_cost_usd'=>$provider->estimatedUsd($duration),'submitted_at'=>ad_now(),'metadata'=>['world_version'=>$state['world']['version']??null,'image_refs'=>count($refs['images']),'video_refs'=>count($refs['videos']),'audio_refs'=>count($refs['audio'])]]);
        ad_state_mutate(function(array $s)use($job,$shotId):array{$s['jobs'][]=$job;foreach($s['shots']as&$x)if(($x['id']??'')===$shotId){$x['status']='generating';$x['world_version_id']='world-v'.(string)($s['world']['version']??1);$x['updated_at']=ad_now();break;}unset($x);return$s;});
        try{
            $submitted=$provider->submit(['prompt_text'=>adref_prompt($state,$shot,$refs),'reference_images'=>$refs['images'],'reference_videos'=>$refs['videos'],'reference_audio'=>$refs['audio'],'duration_seconds'=>$duration,'ratio'=>(string)($shot['ratio']??'1280:720'),'generate_audio'=>!empty($d['generate_audio'])]);
            $external=trim((string)($submitted['external_id']??''));if($external==='')throw new RuntimeException('Provider returned no task id.');
            $updated=ad_state_mutate(function(array $s)use($jobId,$external,$submitted):array{foreach($s['jobs']as&$j)if(($j['id']??'')===$jobId){$j['provider_job_id']=$external;$j['external_id']=$external;$j['raw']=$submitted['raw']??null;break;}unset($j);return$s;});
            ad_event('multi_reference_generation_submitted',['job_id'=>$jobId,'shot_id'=>$shotId]);ad_json(['ok'=>true,'job'=>adref_find($updated['jobs'],$jobId),'estimated_cost_usd'=>$job['estimated_cost_usd']]);
        }catch(Throwable$e){$safe=ad_safe_provider_error($e);ad_state_mutate(function(array$s)use($jobId,$shotId,$safe):array{foreach($s['jobs']as&$j)if(($j['id']??'')===$jobId){$j['status']='failed';$j['failed_at']=ad_now();$j['safe_error']=$safe['safe_error'];break;}unset($j);foreach($s['shots']as&$x)if(($x['id']??'')===$shotId){$x['status']='draft';break;}unset($x);return$s;});ad_json(['ok'=>false,'error'=>$safe['safe_error']],502);}
    }

    if($action==='poll' && $_SERVER['REQUEST_METHOD']==='POST'){
        $d=ad_json_input();$jobId=trim((string)($d['job_id']??''));$state=ad_state_read();$job=adref_find((array)$state['jobs'],$jobId);
        if(!$job||($job['capability']??'')!=='MULTI_REFERENCE')ad_json(['ok'=>false,'error'=>'Reference generation job not found.'],404);
        if(in_array((string)$job['status'],['completed','failed','cancelled'],true))ad_json(['ok'=>true,'job'=>$job,'done'=>true]);
        $provider=ad_provider_instance((string)$job['provider']);$external=trim((string)($job['provider_job_id']??''));
        try{$result=$provider->poll($external);$status=(string)($result['status']??'processing');
            if($status==='succeeded'){$remote=trim((string)($result['output_url']??''));$local=$remote!==''?ad_download_result($remote,$jobId):null;$take=ad_normalize_take(['id'=>ad_id('take'),'shot_id'=>(string)$job['shot_id'],'generation_job_id'=>$jobId,'job_id'=>$jobId,'provider'=>(string)$job['provider'],'model'=>(string)$job['model'],'mode'=>'MULTI_REFERENCE','remote_url'=>$remote,'local'=>$local,'attempt'=>(int)$job['attempt'],'mock'=>!empty($result['mock']),'created_at'=>ad_now()]);$next=ad_state_mutate(function(array$s)use($jobId,$take):array{$s['takes'][]=$take;foreach($s['jobs']as&$j)if(($j['id']??'')===$jobId){$j['status']='completed';$j['completed_at']=ad_now();break;}unset($j);foreach($s['shots']as&$x)if(($x['id']??'')===($take['shot_id']??'')){$x['status']='review';$x['updated_at']=ad_now();break;}unset($x);return$s;});ad_json(['ok'=>true,'job'=>adref_find($next['jobs'],$jobId),'take'=>$take,'done'=>true]);}
            $next=ad_state_mutate(function(array$s)use($jobId,$status):array{foreach($s['jobs']as&$j)if(($j['id']??'')===$jobId){$j['status']=$status;break;}unset($j);return$s;});ad_json(['ok'=>true,'job'=>adref_find($next['jobs'],$jobId),'done'=>false]);
        }catch(Throwable$e){$safe=ad_safe_provider_error($e);ad_json(['ok'=>false,'error'=>$safe['safe_error']],502);}
    }
    ad_json(['ok'=>false,'error'=>'Unknown reference action.'],404);
}catch(Throwable$e){ad_json(['ok'=>false,'error'=>ad_substr($e->getMessage(),0,400)],500);}
