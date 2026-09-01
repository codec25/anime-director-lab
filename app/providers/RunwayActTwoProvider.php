<?php
declare(strict_types=1);

final class RunwayActTwoProvider implements ADProvider {
    private string $key;
    public function __construct() { $this->key = ad_env('RUNWAY_API_KEY'); }
    public function id(): string { return 'runway_act_two'; }
    public function available(): bool { return ad_mock_mode() || $this->key !== ''; }
    public function estimatedUsd(float $seconds): float {
        // Act-Two is 5 Runway credits/sec. Runway API credits are $0.01 each.
        return round(max(3, min(30, $seconds)) * 0.05, 4);
    }
    private function ratio(string $ratio): string {
        $allowed=['1280:720','720:1280','960:960','1104:832','832:1104','1584:672'];
        return in_array($ratio,$allowed,true)?$ratio:'1280:720';
    }
    public function submit(array $input): array {
        $characterUri=trim((string)($input['character_uri']??''));
        $characterType=(string)($input['character_type']??'image');
        $referenceUri=trim((string)($input['reference_uri']??''));
        if(!in_array($characterType,['image','video'],true))$characterType='image';
        if($characterUri===''||$referenceUri==='')throw new InvalidArgumentException('Act-Two requires character media and a driving performance video.');
        $payload=[
            'model'=>'act_two',
            'character'=>['type'=>$characterType,'uri'=>$characterUri],
            'reference'=>['type'=>'video','uri'=>$referenceUri],
            'bodyControl'=>!empty($input['body_control']),
            'expressionIntensity'=>max(1,min(5,(int)($input['expression_intensity']??3))),
            'ratio'=>$this->ratio((string)($input['ratio']??'1280:720')),
        ];
        if(isset($input['seed']))$payload['seed']=(int)$input['seed'];
        if(ad_mock_mode())return ['external_id'=>ad_id('mock_act_two'),'status'=>'submitted','raw'=>['mock'=>true,'payload'=>$payload]];
        if($this->key==='')throw new RuntimeException('RUNWAY_API_KEY is not configured.');
        $data=ad_http_json('POST','https://api.dev.runwayml.com/v1/character_performance',[
            'Authorization: Bearer '.$this->key,
            'Content-Type: application/json',
            'X-Runway-Version: 2024-11-06',
        ],$payload);
        $id=(string)($data['id']??'');
        if($id==='')throw new RuntimeException('Runway returned no Act-Two task id.');
        return ['external_id'=>$id,'status'=>'submitted','raw'=>$data];
    }
    public function poll(string $externalId): array {
        if(ad_mock_mode())return ['status'=>'succeeded','output_url'=>'','mock'=>true,'raw'=>['id'=>$externalId,'mock'=>true]];
        $data=ad_http_json('GET','https://api.dev.runwayml.com/v1/tasks/'.rawurlencode($externalId),[
            'Authorization: Bearer '.$this->key,
            'X-Runway-Version: 2024-11-06',
        ]);
        $status=strtoupper((string)($data['status']??'PENDING'));
        if($status==='SUCCEEDED'){$output=$data['output']??[];$url=is_array($output)?(string)($output[0]??''):'';return ['status'=>'succeeded','output_url'=>$url,'raw'=>$data];}
        if($status==='FAILED')return ['status'=>'failed','error'=>(string)($data['failure']??'Act-Two generation failed.'),'raw'=>$data];
        return ['status'=>in_array($status,['RUNNING','THROTTLED'],true)?'processing':'queued','raw'=>$data];
    }
}
