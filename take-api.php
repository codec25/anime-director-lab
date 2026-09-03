<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

$action=(string)($_GET['action']??'state');
function adtake_find(array $items,string $id):?array{foreach($items as$item)if(is_array($item)&&(string)($item['id']??'')===$id)return$item;return null;}
function adtake_url(array $take):string{return trim((string)($take['local']['url']??$take['result_media']['local']['url']??$take['remote_url']??$take['result_media']['remote_url']??''));}
function adtake_allowed_changes(array $shot):array{
  $explicit=array_values(array_filter(array_map('strval',(array)($shot['allowed_continuity_changes']??[]))));
  if($explicit)return array_values(array_unique($explicit));
  $delta=strtolower(trim((string)($shot['continuity_delta']??'')));$allowed=[];
  if(str_contains($delta,'location may resolve')||str_contains($delta,'location changes')||str_contains($delta,'destination changes'))$allowed[]='location';
  if(str_contains($delta,'lighting changes')||str_contains($delta,'lighting may change'))$allowed[]='lighting';
  if(str_contains($delta,'palette changes')||str_contains($delta,'grade changes'))$allowed[]='palette';
  if(str_contains($delta,'wardrobe changes intentionally')||str_contains($delta,'costume changes intentionally'))$allowed[]='wardrobe';
  if(str_contains($delta,'screen direction reverses')||str_contains($delta,'direction of travel reverses'))$allowed[]='screen_direction';
  return array_values(array_unique($allowed));
}
function adtake_gate(array $shot):array{
  if(empty($shot['sequence_plan_id']))return['required'=>false,'ready'=>true,'required_checks'=>[],'allowed_changes'=>[],'blockers'=>[],'unchecked'=>[]];
  $beat=max(1,(int)($shot['sequence_beat']??1));$allowed=adtake_allowed_changes($shot);
  $required=['identity','wardrobe','location','lighting','palette'];if($beat>1)$required[]='screen_direction';
  $required=array_values(array_diff($required,$allowed));
  $review=is_array($shot['continuity_review']??null)?$shot['continuity_review']:[];$checks=is_array($review['checks']??null)?$review['checks']:[];
  $unchecked=[];$blockers=[];foreach($required as$k){$v=(string)($checks[$k]??'unchecked');if($v==='fail')$blockers[]=$k;elseif(!in_array($v,['pass','warn'],true))$unchecked[]=$k;}
  return['required'=>true,'ready'=>!$blockers&&!$unchecked,'required_checks'=>$required,'allowed_changes'=>$allowed,'blockers'=>$blockers,'unchecked'=>$unchecked,'reviewed_at'=>$review['reviewed_at']??null];
}
function adtake_state():array{$s=ad_state_read();$rows=[];foreach((array)($s['shots']??[])as$shot){if(!is_array($shot))continue;$shotId=(string)($shot['id']??'');$takes=[];foreach((array)($s['takes']??[])as$t)if(is_array($t)&&(string)($t['shot_id']??'')===$shotId&&adtake_url($t)!=='')$takes[]=['id'=>(string)($t['id']??''),'attempt'=>(int)($t['attempt']??1),'provider'=>(string)($t['provider']??''),'mode'=>(string)($t['mode']??''),'url'=>adtake_url($t),'selected'=>((string)($shot['selected_take_id']??'')===(string)($t['id']??''))||!empty($t['selected'])];$rows[]=['shot_id'=>$shotId,'selected_take_id'=>$shot['selected_take_id']??null,'continuity_gate'=>adtake_gate($shot),'takes'=>$takes];}return['ok'=>true,'shots'=>$rows];}

try{
 if($action==='state')ad_json(adtake_state());
 if($action==='select'&&$_SERVER['REQUEST_METHOD']==='POST'){$d=ad_json_input();$shotId=trim((string)($d['shot_id']??''));$takeId=trim((string)($d['take_id']??''));if($shotId===''||$takeId==='')ad_json(['ok'=>false,'error'=>'shot_id and take_id are required.'],422);$before=ad_state_read();$shot=adtake_find((array)($before['shots']??[]),$shotId);$take=adtake_find((array)($before['takes']??[]),$takeId);if(!$shot)ad_json(['ok'=>false,'error'=>'Shot not found.'],404);if(!$take||(string)($take['shot_id']??'')!==$shotId)ad_json(['ok'=>false,'error'=>'That take does not belong to this shot.'],422);if(adtake_url($take)==='')ad_json(['ok'=>false,'error'=>'That take has no usable media.'],409);$gate=adtake_gate($shot);if(!empty($gate['required'])&&!empty($gate['blockers']))ad_json(['ok'=>false,'code'=>'CONTINUITY_FIX_REQUIRED','error'=>'This take has a continuity item marked Needs fix. Correct it or update the review before keeping the take.','shot_id'=>$shotId,'continuity_gate'=>$gate],409);if(!empty($gate['required'])&&!empty($gate['unchecked']))ad_json(['ok'=>false,'code'=>'CONTINUITY_REVIEW_REQUIRED','error'=>'Quick continuity check required before keeping this sequence take. Planned story changes are excluded from the gate.','shot_id'=>$shotId,'continuity_gate'=>$gate],409);$next=ad_state_mutate(function(array$s)use($shotId,$takeId):array{foreach($s['shots']as&$x)if(is_array($x)&&(string)($x['id']??'')===$shotId){$x['selected_take_id']=$takeId;$x['updated_at']=ad_now();break;}unset($x);foreach($s['takes']as&$x)if(is_array($x)&&(string)($x['shot_id']??'')===$shotId)$x['selected']=((string)($x['id']??'')===$takeId);unset($x);return$s;});ad_event('take_selected',['shot_id'=>$shotId,'take_id'=>$takeId]);ad_json(['ok'=>true,'shot'=>adtake_find((array)$next['shots'],$shotId),'take'=>adtake_find((array)$next['takes'],$takeId),'continuity_gate'=>adtake_gate((array)adtake_find((array)$next['shots'],$shotId))]);}
 ad_json(['ok'=>false,'error'=>'Unknown take action.'],404);
}catch(Throwable$e){ad_json(['ok'=>false,'error'=>ad_substr($e->getMessage(),0,400)],500);}
