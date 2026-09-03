<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

$action = (string)($_GET['action'] ?? 'state');

function adscene_find(array $items, string $id): ?array {
    foreach ($items as $item) if (is_array($item) && (string)($item['id'] ?? '') === $id) return $item;
    return null;
}
function adscene_fields(): array {
    return ['location','environment','lighting','palette','weather','time_of_day','style_rules','continuity_rules','composition_rules','camera_rules','character_motion','environment_motion'];
}
function adscene_memory(array $scene): array {
    $m = is_array($scene['memory'] ?? null) ? $scene['memory'] : [];
    $out = ['preset_id'=>(string)($m['preset_id'] ?? ''),'references'=>array_values(array_filter((array)($m['references'] ?? []),'is_array'))];
    foreach (adscene_fields() as $f) $out[$f] = ad_substr(trim((string)($m[$f] ?? '')), 0, in_array($f,['environment','style_rules','continuity_rules','composition_rules','camera_rules','character_motion','environment_motion'],true) ? 1500 : 500);
    return $out;
}
function adscene_normalize(array $scene): array {
    $scene['memory'] = adscene_memory($scene);
    $scene['title'] = ad_substr(trim((string)($scene['title'] ?? 'Scene')),0,120) ?: 'Scene';
    $scene['shot_ids'] = array_values(array_filter(array_map('strval',(array)($scene['shot_ids'] ?? []))));
    return $scene;
}
function adscene_preset(array $p): array {
    $out=['id'=>(string)($p['id']??ad_id('loc')),'name'=>ad_substr(trim((string)($p['name']??'Location preset')),0,100),'references'=>array_values(array_filter((array)($p['references']??[]),'is_array')),'created_at'=>(string)($p['created_at']??ad_now()),'updated_at'=>(string)($p['updated_at']??ad_now())];
    foreach(adscene_fields() as $f)$out[$f]=ad_substr(trim((string)($p[$f]??'')),0,in_array($f,['environment','style_rules','continuity_rules','composition_rules','camera_rules','character_motion','environment_motion'],true)?1500:500);
    return $out;
}
function adscene_active_id(array $state): string {
    $id=trim((string)($state['production']['active_scene_id']??''));
    if($id!==''&&adscene_find((array)$state['scenes'],$id))return $id;
    return (string)($state['scenes'][0]['id']??'');
}

try {
    if($action==='state'){
        $s=ad_state_read();$scenes=array_map('adscene_normalize',(array)$s['scenes']);$presets=array_map('adscene_preset',array_values(array_filter((array)($s['location_presets']??[]),'is_array')));
        ad_json(['ok'=>true,'active_scene_id'=>adscene_active_id($s),'scenes'=>$scenes,'presets'=>$presets]);
    }

    if($action==='set-active'&&$_SERVER['REQUEST_METHOD']==='POST'){
        $d=ad_json_input();$id=trim((string)($d['scene_id']??''));$s=ad_state_read();if(!$id||!adscene_find((array)$s['scenes'],$id))ad_json(['ok'=>false,'error'=>'Scene not found.'],404);
        $next=ad_state_mutate(function(array $s)use($id):array{$s['production']['active_scene_id']=$id;$s['production']['updated_at']=ad_now();return$s;});
        ad_json(['ok'=>true,'active_scene_id'=>$id]);
    }

    if($action==='create-scene'&&$_SERVER['REQUEST_METHOD']==='POST'){
        $d=ad_json_input();$next=ad_state_mutate(function(array $s)use($d):array{$n=count((array)$s['scenes'])+1;$scene=ad_default_scene((string)$s['production']['id'],$n);$scene['title']=ad_substr(trim((string)($d['title']??('Scene '.str_pad((string)$n,2,'0',STR_PAD_LEFT)))),0,120);$scene['memory']=adscene_memory([]);$s['scenes'][]=$scene;$s['production']['active_scene_id']=$scene['id'];return$s;});
        $id=adscene_active_id($next);ad_event('scene_created',['scene_id'=>$id]);ad_json(['ok'=>true,'scene'=>adscene_find($next['scenes'],$id),'active_scene_id'=>$id]);
    }

    if($action==='save-scene'&&$_SERVER['REQUEST_METHOD']==='POST'){
        $d=ad_json_input();$id=trim((string)($d['scene_id']??''));
        $next=ad_state_mutate(function(array $s)use($d,$id):array{foreach($s['scenes']as&$scene)if((string)($scene['id']??'')===$id){$m=adscene_memory($scene);foreach(adscene_fields()as$f)if(array_key_exists($f,$d))$m[$f]=(string)$d[$f];if(array_key_exists('title',$d))$scene['title']=ad_substr(trim((string)$d['title']),0,120);$scene['memory']=adscene_memory(['memory'=>$m]);$scene['updated_at']=ad_now();break;}unset($scene);return$s;});
        $scene=adscene_find($next['scenes'],$id);if(!$scene)ad_json(['ok'=>false,'error'=>'Scene not found.'],404);ad_event('scene_memory_updated',['scene_id'=>$id]);ad_json(['ok'=>true,'scene'=>adscene_normalize($scene)]);
    }

    if($action==='save-preset'&&$_SERVER['REQUEST_METHOD']==='POST'){
        $d=ad_json_input();$sceneId=trim((string)($d['scene_id']??''));$state=ad_state_read();$scene=adscene_find((array)$state['scenes'],$sceneId);if(!$scene)ad_json(['ok'=>false,'error'=>'Scene not found.'],404);$m=adscene_memory($scene);$preset=adscene_preset(array_merge($m,['id'=>ad_id('loc'),'name'=>trim((string)($d['name']??$scene['title']??'Location preset')),'created_at'=>ad_now(),'updated_at'=>ad_now()]));
        $next=ad_state_mutate(function(array $s)use($preset):array{$s['location_presets'][]=$preset;$s['location_presets']=array_slice(array_values(array_filter((array)$s['location_presets'],'is_array')),-50);return$s;});ad_event('location_preset_saved',['preset_id'=>$preset['id']]);ad_json(['ok'=>true,'preset'=>$preset,'presets'=>$next['location_presets']]);
    }

    if($action==='apply-preset'&&$_SERVER['REQUEST_METHOD']==='POST'){
        $d=ad_json_input();$sceneId=trim((string)($d['scene_id']??''));$presetId=trim((string)($d['preset_id']??''));$state=ad_state_read();$preset=adscene_find((array)($state['location_presets']??[]),$presetId);if(!$preset)ad_json(['ok'=>false,'error'=>'Location preset not found.'],404);$preset=adscene_preset($preset);
        $next=ad_state_mutate(function(array $s)use($sceneId,$preset):array{foreach($s['scenes']as&$scene)if((string)($scene['id']??'')===$sceneId){$current=adscene_memory($scene);$memory=$current;foreach(adscene_fields()as$f)$memory[$f]=$preset[$f]??'';$memory['references']=$preset['references']??[];$memory['preset_id']=$preset['id'];$scene['memory']=adscene_memory(['memory'=>$memory]);$scene['updated_at']=ad_now();break;}unset($scene);return$s;});$scene=adscene_find($next['scenes'],$sceneId);if(!$scene)ad_json(['ok'=>false,'error'=>'Scene not found.'],404);ad_event('location_preset_applied',['preset_id'=>$presetId,'scene_id'=>$sceneId]);ad_json(['ok'=>true,'scene'=>adscene_normalize($scene)]);
    }

    if($action==='attach-reference'&&$_SERVER['REQUEST_METHOD']==='POST'){
        $sceneId=trim((string)($_POST['scene_id']??''));if(empty($_FILES['reference']))ad_json(['ok'=>false,'error'=>'Scene reference is required.'],422);$role=strtolower(trim((string)($_POST['role']??'environment')));if(!in_array($role,['environment','prop','style','motion','sound','reference'],true))$role='environment';$asset=ad_store_director_reference($_FILES['reference']);$asset['role']=$role;
        $next=ad_state_mutate(function(array $s)use($sceneId,$asset):array{foreach($s['scenes']as&$scene)if((string)($scene['id']??'')===$sceneId){$m=adscene_memory($scene);$m['references'][]=$asset;$m['references']=array_slice($m['references'],-30);$scene['memory']=$m;$scene['updated_at']=ad_now();break;}unset($scene);return$s;});$scene=adscene_find($next['scenes'],$sceneId);if(!$scene)ad_json(['ok'=>false,'error'=>'Scene not found.'],404);ad_event('scene_reference_attached',['scene_id'=>$sceneId,'reference_id'=>$asset['id'],'role'=>$role]);ad_json(['ok'=>true,'reference'=>$asset,'scene'=>adscene_normalize($scene)]);
    }

    if($action==='remove-reference'&&$_SERVER['REQUEST_METHOD']==='POST'){
        $d=ad_json_input();$sceneId=trim((string)($d['scene_id']??''));$refId=trim((string)($d['reference_id']??''));$next=ad_state_mutate(function(array $s)use($sceneId,$refId):array{foreach($s['scenes']as&$scene)if((string)($scene['id']??'')===$sceneId){$m=adscene_memory($scene);$m['references']=array_values(array_filter($m['references'],static fn(array$r):bool=>(string)($r['id']??'')!==$refId));$scene['memory']=$m;$scene['updated_at']=ad_now();break;}unset($scene);return$s;});ad_json(['ok'=>true,'scene'=>adscene_normalize((array)adscene_find($next['scenes'],$sceneId))]);
    }

    if($action==='bind-shot'&&$_SERVER['REQUEST_METHOD']==='POST'){
        $d=ad_json_input();$shotId=trim((string)($d['shot_id']??''));$sceneId=trim((string)($d['scene_id']??''));$state=ad_state_read();$scene=adscene_find((array)$state['scenes'],$sceneId);if(!$scene)ad_json(['ok'=>false,'error'=>'Scene not found.'],404);$m=adscene_memory($scene);
        $next=ad_state_mutate(function(array $s)use($shotId,$sceneId,$m):array{foreach($s['shots']as&$shot)if((string)($shot['id']??'')===$shotId){$shot['scene_id']=$sceneId;$existing=array_values(array_filter((array)($shot['references']??[]),'is_array'));$seen=[];foreach($existing as$r)if(!empty($r['url']))$seen[(string)$r['url']]=true;foreach($m['references']as$r){$u=(string)($r['url']??'');if($u!==''&&!isset($seen[$u])){$existing[]=$r;$seen[$u]=true;}}$shot['references']=$existing;$shot['scene_memory_snapshot']=array_diff_key($m,['references'=>true]);$shot['updated_at']=ad_now();break;}unset($shot);foreach($s['scenes']as&$sc){$sc['shot_ids']=array_values(array_filter((array)($sc['shot_ids']??[]),static fn($id):bool=>(string)$id!==$shotId));if((string)($sc['id']??'')===$sceneId&&!in_array($shotId,$sc['shot_ids'],true))$sc['shot_ids'][]=$shotId;}unset($sc);return$s;});ad_json(['ok'=>true,'shot'=>adscene_find($next['shots'],$shotId)]);
    }

    ad_json(['ok'=>false,'error'=>'Unknown scene-memory action.'],404);
}catch(Throwable$e){ad_json(['ok'=>false,'error'=>ad_substr($e->getMessage(),0,400)],500);}
