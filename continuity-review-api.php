<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

$action=(string)($_GET['action']??'state');

function adcr_find(array $items,string $id):?array{foreach($items as $x)if(is_array($x)&&(string)($x['id']??'')===$id)return$x;return null;}
function adcr_review(array $shot):array{
  $r=is_array($shot['continuity_review']??null)?$shot['continuity_review']:[];
  $checks=[];foreach(['identity','wardrobe','location','lighting','palette','screen_direction']as$k){$v=(string)($r['checks'][$k]??'unchecked');$checks[$k]=in_array($v,['unchecked','pass','warn','fail'],true)?$v:'unchecked';}
  return ['checks'=>$checks,'notes'=>ad_substr(trim((string)($r['notes']??'')),0,1800),'reviewed_at'=>$r['reviewed_at']??null];
}
function adcr_take_url(array $state,array $shot):string{
  $take=null;$selected=(string)($shot['selected_take_id']??'');if($selected!=='')$take=adcr_find((array)$state['takes'],$selected);
  if(!$take){$matches=array_values(array_filter((array)$state['takes'],static fn($t):bool=>is_array($t)&&(string)($t['shot_id']??'')===(string)($shot['id']??'')));foreach(array_reverse($matches)as$t)if(!empty($t['selected'])){$take=$t;break;}if(!$take)$take=$matches?$matches[count($matches)-1]:null;}
  if(!$take)return'';$local=$take['local']['url']??$take['result_media']['local']['url']??'';return trim((string)($local?:($take['remote_url']??$take['result_media']['remote_url']??'')));
}
function adcr_scene_memory(array $scene):array{$m=is_array($scene['memory']??null)?$scene['memory']:[];$out=[];foreach(['location','environment','lighting','palette','weather','time_of_day','style_rules','continuity_rules','composition_rules','camera_rules','character_motion','environment_motion']as$k)$out[$k]=trim((string)($m[$k]??''));return$out;}
function adcr_metadata_warnings(array $state,array $scene,array $shot,string $dominantRatio):array{
  $w=[];$media=adcr_take_url($state,$shot);if($media==='')$w[]=['code'=>'NO_TAKE','label'=>'No generated take yet'];
  $ratio=trim((string)($shot['ratio']??''));if($dominantRatio!==''&&$ratio!==''&&$ratio!==$dominantRatio)$w[]=['code'=>'RATIO_DRIFT','label'=>'Aspect ratio differs from most shots in this scene'];
  $current=adcr_scene_memory($scene);$snap=is_array($shot['scene_memory_snapshot']??null)?$shot['scene_memory_snapshot']:[];$stale=[];foreach($current as$k=>$v){$old=trim((string)($snap[$k]??''));if($v!==''&&$old!==''&&$v!==$old)$stale[]=$k;}if($stale)$w[]=['code'=>'SCENE_MEMORY_STALE','label'=>'Scene Memory changed after this shot: '.implode(', ',array_slice($stale,0,5))];
  $lines=array_values(array_filter((array)($shot['dialogue']??[]),'is_array'));if($lines){$missing=0;foreach($lines as$l)if(!is_array($l['audio_reference']??null)||empty($l['audio_reference']['url']))$missing++;if($missing)$w[]=['code'=>'DIALOGUE_AUDIO_MISSING','label'=>$missing.' dialogue '.($missing===1?'line is':'lines are').' not voiced yet'];}
  $orch=is_array($shot['act_two_orchestration']??null)?$shot['act_two_orchestration']:[];$passes=array_values(array_filter((array)($orch['passes']??[]),'is_array'));if($passes){$pending=0;$failed=0;foreach($passes as$p){$status=(string)($p['status']??'draft');if($status==='failed')$failed++;elseif($status!=='completed')$pending++;}if($failed)$w[]=['code'=>'PRECISION_FAILED','label'=>$failed.' precision '.($failed===1?'pass failed':'passes failed').' and can be retried'];if($pending)$w[]=['code'=>'PRECISION_PENDING','label'=>$pending.' precision '.($pending===1?'pass is':'passes are').' incomplete'];}
  return$w;
}
function adcr_payload(array $state):array{
  $scenes=[];foreach((array)$state['scenes']as$scene){if(!is_array($scene))continue;$sceneShots=[];foreach((array)($scene['shot_ids']??[])as$id){$shot=adcr_find((array)$state['shots'],(string)$id);if($shot)$sceneShots[]=$shot;}if(!$sceneShots){foreach((array)$state['shots']as$shot)if(is_array($shot)&&(string)($shot['scene_id']??'')===(string)($scene['id']??''))$sceneShots[]=$shot;}
    $ratioCounts=[];foreach($sceneShots as$s){$r=(string)($s['ratio']??'');if($r!=='')$ratioCounts[$r]=($ratioCounts[$r]??0)+1;}arsort($ratioCounts);$dominant=(string)(array_key_first($ratioCounts)??'');$rows=[];
    foreach($sceneShots as$shot){$lines=array_values(array_filter((array)($shot['dialogue']??[]),'is_array'));$speakers=array_values(array_unique(array_filter(array_map(static fn($l)=>(string)($l['speaker_name']??''),$lines))));$rows[]=['id'=>(string)$shot['id'],'number'=>(int)($shot['number']??$shot['shot_number']??0),'title'=>(string)($shot['title']??'Shot'),'intent'=>(string)($shot['intent']??$shot['direction']??''),'ratio'=>(string)($shot['ratio']??''),'status'=>(string)($shot['status']??'draft'),'camera_direction'=>(string)($shot['camera_direction']??''),'media_url'=>adcr_take_url($state,$shot),'review'=>adcr_review($shot),'warnings'=>adcr_metadata_warnings($state,$scene,$shot,$dominant),'dialogue_count'=>count($lines),'speakers'=>$speakers,'world_version_id'=>$shot['world_version_id']??null,'selected_take_id'=>$shot['selected_take_id']??null];}
    $scenes[]=['id'=>(string)($scene['id']??''),'number'=>(int)($scene['number']??0),'title'=>(string)($scene['title']??'Scene'),'memory'=>adcr_scene_memory($scene),'dominant_ratio'=>$dominant,'shots'=>$rows];
  }return['scenes'=>$scenes,'active_scene_id'=>(string)($state['production']['active_scene_id']??($scenes[0]['id']??''))];
}
try{
  if($action==='state')ad_json(['ok'=>true]+adcr_payload(ad_state_read()));
  if($action==='save-review'&&$_SERVER['REQUEST_METHOD']==='POST'){$d=ad_json_input();$shotId=trim((string)($d['shot_id']??''));$checks=is_array($d['checks']??null)?$d['checks']:[];$valid=[];foreach(['identity','wardrobe','location','lighting','palette','screen_direction']as$k){$v=(string)($checks[$k]??'unchecked');$valid[$k]=in_array($v,['unchecked','pass','warn','fail'],true)?$v:'unchecked';}$review=['checks'=>$valid,'notes'=>ad_substr(trim((string)($d['notes']??'')),0,1800),'reviewed_at'=>ad_now()];$found=false;$next=ad_state_mutate(function(array$s)use($shotId,$review,&$found):array{foreach($s['shots']as&$shot)if((string)($shot['id']??'')===$shotId){$shot['continuity_review']=$review;$shot['updated_at']=ad_now();$found=true;break;}unset($shot);return$s;});if(!$found)ad_json(['ok'=>false,'error'=>'Shot not found.'],404);ad_event('continuity_review_saved',['shot_id'=>$shotId]);ad_json(['ok'=>true,'shot_id'=>$shotId,'review'=>$review]+adcr_payload($next));}
  ad_json(['ok'=>false,'error'=>'Unknown continuity review action.'],404);
}catch(Throwable$e){ad_json(['ok'=>false,'error'=>ad_substr($e->getMessage(),0,400)],500);}
