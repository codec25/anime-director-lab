<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

$asset = static fn(string $id,string $kind,string $url,string $path=''): array => [
    'id'=>$id,'kind'=>$kind,'role'=>$kind==='audio'?'sound':'reference','url'=>$url,'path'=>$path,
    'mime'=>$kind==='audio'?'audio/mpeg':($kind==='video'?'video/mp4':'image/png'),'created_at'=>ad_now(),
];

$characterAsset=$asset('char_ref','image','https://example.test/akio.png');
$ambience=$asset('amb_flow','audio','https://example.test/rain.mp3','storage/references/flow-rain.mp3');
$music=$asset('music_flow','audio','https://example.test/score.mp3','storage/references/flow-score.mp3');

$fixture=ad_default_state();
$fixture['production']['active_scene_id']='scene_flow';
$fixture['world']=array_merge($fixture['world'],['location'=>'Rainy neon alley','lighting'=>'Blue-magenta neon','weather'=>'Light rain','version'=>3]);
$fixture['character']=ad_normalize_character([
    'id'=>'char_flow','name'=>'Akio','version'=>'v1','status'=>'locked','canonical_style'=>'cinematic anime',
    'outfit_notes'=>'black jacket, white shirt','references'=>['master_front'=>$characterAsset],'asset'=>$characterAsset,
]);
$fixture['scenes']=[[
    'id'=>'scene_flow','production_id'=>$fixture['production']['id'],'number'=>1,'title'=>'Alley Reveal','shot_ids'=>['shot_flow_1'],
    'sound_design'=>[
        'ambience_prompt'=>'Steady rain in a narrow neon alley','music_prompt'=>'Sparse suspense pulse','music_behavior'=>'hold under dialogue',
        'jobs'=>[
            ['id'=>'snd_amb','scope'=>'scene','target_id'=>'scene_flow','type'=>'ambience','status'=>'completed','asset'=>$ambience],
            ['id'=>'snd_music','scope'=>'scene','target_id'=>'scene_flow','type'=>'music','status'=>'completed','asset'=>$music],
        ],
    ],
    'created_at'=>ad_now(),'updated_at'=>ad_now(),
]];
$fixture['shots']=[ad_normalize_shot([
    'id'=>'shot_flow_1','scene_id'=>'scene_flow','number'=>1,'shot_number'=>1,'title'=>'Approach the ball','character_id'=>'char_flow',
    'character_version_ids'=>['char_flow'],'intent'=>'Akio walks slowly toward the ball.','direction'=>'Low camera following his feet.',
    'camera_direction'=>'Low-angle tracking','duration_target'=>5,'ratio'=>'1280:720','generation_mode'=>'DESCRIBE_IT','status'=>'review',
    'selected_take_id'=>'take_flow_1','world_version_id'=>'world-v3',
    'act_two_orchestration'=>[
        'status'=>'generating','base_take_id'=>'take_flow_1','passes'=>[
            ['id'=>'pass_ok','speaker_id'=>'char_flow','speaker_name'=>'Akio','status'=>'completed','take_id'=>'take_act_ok'],
            ['id'=>'pass_retry','speaker_id'=>'char_flow','speaker_name'=>'Akio','status'=>'failed','take_id'=>null],
        ],'updated_at'=>ad_now(),
    ],
    'created_at'=>ad_now(),'updated_at'=>ad_now(),
],$fixture['character'])];
$fixture['takes']=[
    ad_normalize_take(['id'=>'take_flow_1','shot_id'=>'shot_flow_1','provider'=>'mock','model'=>'mock','mode'=>'DESCRIBE_IT','remote_url'=>'https://example.test/take-1.mp4','attempt'=>1,'selected'=>true]),
    ad_normalize_take(['id'=>'take_flow_2','shot_id'=>'shot_flow_1','provider'=>'mock','model'=>'mock','mode'=>'DESCRIBE_IT','remote_url'=>'https://example.test/take-2.mp4','attempt'=>2,'selected'=>false]),
];
$fixture['timeline']=null;

ad_state_mutate(static fn(array $state): array => $fixture);
echo "seeded\n";
