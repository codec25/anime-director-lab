<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

$action=(string)($_GET['action']??'state');
function addlg_find(array $items,string $id):?array{foreach($items as $x)if((string)($x['id']??'')===$id)return$x;return null;}
function addlg_voice(array $state):array{
  $v=is_array($state['voice_memory']??null)?$state['voice_memory']:[];
  return [
    'id'=>(string)($v['id']??'voice_main'),'name'=>(string)($v['name']??'Main character voice'),
    'description'=>(string)($v['description']??''),'language'=>(string)($v['language']??'en'),
    'delivery_rules'=>(string)($v['delivery_rules']??''),'reference'=>is_array($v['reference']??null)?$v['reference']:null,
    'updated_at'=>(string)($v['updated_at']??ad_now())
  ];
}
function addlg_download_audio(string $url,string $id):?array{
  if(!preg_match('#^https://#i',$url))return null;
  $relative='storage/references/'.preg_replace('/[^A-Za-z0-9_-]/','_',$id).'.mp3';$dest=AD_ROOT.'/'.$relative;
  $ch=curl_init($url);$fh=fopen($dest,'wb');if(!$ch||!$fh)return null;$written=0;
  curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>4,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>180,CURLOPT_WRITEFUNCTION=>static function($ch,string$data)use($fh,&$written):int{$written+=strlen($data);if($written>40*1024*1024)return 0;return fwrite($fh,$data)?:0;}]);
  $ok=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);curl_close($ch);fclose($fh);
  if(!$ok||$code<200||$code>=300||$written<128){@unlink($dest);return null;}
  return ['id'=>ad_id('ref'),'kind'=>'audio','role'=>'voice','path'=>$relative,'url'=>ad_public_media_url($relative),'mime'=>$type?:'audio/mpeg','bytes'=>$written,'original_name'=>'dialogue.mp3','created_at'=>ad_now()];
}
try{
  if($action==='state'){$s=ad_state_read();ad_json(['ok'=>true,'voice'=>addlg_voice($s),'configured'=>ad_mock_mode()||ad_env('RUNWAY_API_KEY')!=='']);}
  if($action==='save-voice'&&$_SERVER['REQUEST_METHOD']==='POST'){
    $d=ad_json_input();$next=ad_state_mutate(function(array$s)use($d):array{$v=addlg_voice($s);foreach(['name','description','language','delivery_rules']as$k)if(array_key_exists($k,$d))$v[$k]=ad_substr(trim((string)$d[$k]),0,$k==='description'||$k==='delivery_rules'?1200:100);$v['updated_at']=ad_now();$s['voice_memory']=$v;return$s;});ad_json(['ok'=>true,'voice'=>addlg_voice($next)]);
  }
  if($action==='upload-voice-reference'&&$_SERVER['REQUEST_METHOD']==='POST'){
    if(empty($_FILES['reference']))ad_json(['ok'=>false,'error'=>'Voice reference audio is required.'],422);$asset=ad_store_director_reference($_FILES['reference']);if(($asset['kind']??'')!=='audio')ad_json(['ok'=>false,'error'=>'Use an audio file for Voice Memory.'],422);$asset['role']='voice';
    $next=ad_state_mutate(function(array$s)use($asset):array{$v=addlg_voice($s);$v['reference']=$asset;$v['updated_at']=ad_now();$s['voice_memory']=$v;return$s;});ad_json(['ok'=>true,'voice'=>addlg_voice($next)]);
  }
  if($action==='save-line'&&$_SERVER['REQUEST_METHOD']==='POST'){
    $d=ad_json_input();$shotId=trim((string)($d['shot_id']??''));$text=ad_substr(trim((string)($d['text']??'')),0,1800);if($text==='')ad_json(['ok'=>false,'error'=>'Dialogue text is required.'],422);
    $line=['id'=>ad_id('dialogue'),'text'=>$text,'delivery'=>ad_substr(trim((string)($d['delivery']??'')),0,500),'language'=>ad_substr(trim((string)($d['language']??'en')),0,20),'audio_reference'=>null,'created_at'=>ad_now(),'updated_at'=>ad_now()];
    $next=ad_state_mutate(function(array$s)use($shotId,$line):array{foreach($s['shots']as&$x)if(($x['id']??'')===$shotId){$lines=is_array($x['dialogue']??null)?$x['dialogue']:[];$lines[]=$line;$x['dialogue']=array_slice($lines,-12);$x['updated_at']=ad_now();break;}unset($x);return$s;});ad_json(['ok'=>true,'shot'=>addlg_find($next['shots'],$shotId),'line'=>$line]);
  }
  if($action==='generate-speech'&&$_SERVER['REQUEST_METHOD']==='POST'){
    $d=ad_json_input();$shotId=trim((string)($d['shot_id']??''));$lineId=trim((string)($d['line_id']??''));$s=ad_state_read();$shot=addlg_find($s['shots'],$shotId);if(!$shot)ad_json(['ok'=>false,'error'=>'Shot not found.'],404);$line=addlg_find((array)($shot['dialogue']??[]),$lineId);if(!$line)ad_json(['ok'=>false,'error'=>'Dialogue line not found.'],404);$voice=addlg_voice($s);$prompt=trim(((string)($line['delivery']??'')!==''?'Delivery: '.$line['delivery'].'. ':'').((string)($voice['delivery_rules']??'')!==''?'Voice rules: '.$voice['delivery_rules'].'. ':'').(string)$line['text']);
    if(ad_mock_mode()){$asset=['id'=>ad_id('ref'),'kind'=>'audio','role'=>'voice','path'=>'','url'=>'','mime'=>'audio/mpeg','bytes'=>0,'original_name'=>'mock-dialogue.mp3','created_at'=>ad_now()];$taskId=ad_id('mock_speech');}
    else{
      $key=ad_env('RUNWAY_API_KEY');if($key==='')ad_json(['ok'=>false,'error'=>'RUNWAY_API_KEY is not configured.'],409);$payload=['model'=>'seed_audio','promptText'=>$prompt,'outputFormat'=>'mp3'];$vr=$voice['reference'];if(is_array($vr)&&!empty($vr['url'])){ad_require_live_public_https_url((string)$vr['url']);$payload['voice']=['type'=>'reference-audio','audioUri'=>(string)$vr['url']];}
      $resp=ad_http_json('POST','https://api.dev.runwayml.com/v1/text_to_speech',['Authorization: Bearer '.$key,'Content-Type: application/json','X-Runway-Version: 2024-11-06'],$payload);$taskId=(string)($resp['id']??'');if($taskId==='')throw new RuntimeException('Runway returned no speech task id.');
      ad_state_mutate(function(array$x)use($shotId,$lineId,$taskId):array{foreach($x['shots']as&$sh)if(($sh['id']??'')===$shotId){foreach($sh['dialogue']as&$ln)if(($ln['id']??'')===$lineId){$ln['speech_task_id']=$taskId;$ln['speech_status']='submitted';break;}unset($ln);break;}unset($sh);return$x;});ad_json(['ok'=>true,'task_id'=>$taskId,'done'=>false]);
    }
    $next=ad_state_mutate(function(array$x)use($shotId,$lineId,$asset):array{foreach($x['shots']as&$sh)if(($sh['id']??'')===$shotId){foreach($sh['dialogue']as&$ln)if(($ln['id']??'')===$lineId){$ln['audio_reference']=$asset;$ln['speech_status']='completed';break;}unset($ln);$refs=is_array($sh['references']??null)?$sh['references']:[];$refs[]=$asset;$sh['references']=array_slice($refs,-12);break;}unset($sh);return$x;});ad_json(['ok'=>true,'done'=>true,'shot'=>addlg_find($next['shots'],$shotId)]);
  }
  if($action==='poll-speech'&&$_SERVER['REQUEST_METHOD']==='POST'){
    $d=ad_json_input();$shotId=trim((string)($d['shot_id']??''));$lineId=trim((string)($d['line_id']??''));$s=ad_state_read();$shot=addlg_find($s['shots'],$shotId);$line=$shot?addlg_find((array)($shot['dialogue']??[]),$lineId):null;if(!$line)ad_json(['ok'=>false,'error'=>'Dialogue line not found.'],404);$taskId=(string)($line['speech_task_id']??'');if($taskId==='')ad_json(['ok'=>false,'error'=>'Speech task id missing.'],409);$key=ad_env('RUNWAY_API_KEY');$resp=ad_http_json('GET','https://api.dev.runwayml.com/v1/tasks/'.rawurlencode($taskId),['Authorization: Bearer '.$key,'X-Runway-Version: 2024-11-06']);$status=strtoupper((string)($resp['status']??'PENDING'));if($status==='FAILED')ad_json(['ok'=>false,'error'=>(string)($resp['failure']??'Speech generation failed.')],502);if($status!=='SUCCEEDED')ad_json(['ok'=>true,'done'=>false,'status'=>strtolower($status)]);$out=$resp['output']??[];$url=is_array($out)?(string)($out[0]??''):'';$asset=$url!==''?addlg_download_audio($url,$taskId):null;if(!$asset)ad_json(['ok'=>false,'error'=>'Speech completed but audio could not be saved.'],502);
    $next=ad_state_mutate(function(array$x)use($shotId,$lineId,$asset):array{foreach($x['shots']as&$sh)if(($sh['id']??'')===$shotId){foreach($sh['dialogue']as&$ln)if(($ln['id']??'')===$lineId){$ln['audio_reference']=$asset;$ln['speech_status']='completed';$ln['updated_at']=ad_now();break;}unset($ln);$refs=is_array($sh['references']??null)?$sh['references']:[];$refs[]=$asset;$sh['references']=array_slice($refs,-12);$sh['updated_at']=ad_now();break;}unset($sh);return$x;});ad_json(['ok'=>true,'done'=>true,'shot'=>addlg_find($next['shots'],$shotId),'reference'=>$asset]);
  }
  ad_json(['ok'=>false,'error'=>'Unknown dialogue action.'],404);
}catch(Throwable$e){ad_json(['ok'=>false,'error'=>ad_substr($e->getMessage(),0,400)],500);}
