<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

$action=(string)($_GET['action']??'sync');
function adsync_latest_asset(array $sound,string $type):?array{$direct=$sound[$type.'_asset']??null;if(is_array($direct)&&((string)($direct['url']??'')!==''||(string)($direct['path']??'')!==''))return$direct;$jobs=array_values(array_filter((array)($sound['jobs']??[]),static fn($j)=>is_array($j)&&(string)($j['type']??'')===$type&&(string)($j['status']??'')==='completed'&&is_array($j['asset']??null)));for($i=count($jobs)-1;$i>=0;$i--){$a=$jobs[$i]['asset'];if((string)($a['url']??'')!==''||(string)($a['path']??'')!=='')return$a;}return null;}
try{
 if($action==='sync'&&in_array($_SERVER['REQUEST_METHOD'],['POST','GET'],true)){$changed=0;$state=ad_state_mutate(function(array$s)use(&$changed):array{foreach($s['scenes']as&$scene){if(!is_array($scene))continue;$sound=is_array($scene['sound_design']??null)?$scene['sound_design']:[];foreach(['ambience','music']as$type){$asset=adsync_latest_asset($sound,$type);if($asset&&(!is_array($sound[$type.'_asset']??null)||(string)($sound[$type.'_asset']['id']??'')!==(string)($asset['id']??''))){$sound[$type.'_asset']=$asset;$changed++;}}$scene['sound_design']=$sound;}unset($scene);return$s;});ad_json(['ok'=>true,'changed'=>$changed,'updated_at'=>$state['updated_at']??null]);}
 ad_json(['ok'=>false,'error'=>'Unknown sound repair action.'],404);
}catch(Throwable$e){ad_json(['ok'=>false,'error'=>ad_substr($e->getMessage(),0,400)],500);}
