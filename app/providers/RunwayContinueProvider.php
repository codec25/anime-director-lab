<?php
declare(strict_types=1);

final class RunwayContinueProvider implements ADProvider {
    private string $key;
    public function __construct() { $this->key = ad_env('RUNWAY_API_KEY'); }
    public function id(): string { return 'runway_seedance25_extend'; }
    public function available(): bool { return ad_mock_mode() || $this->key !== ''; }
    public function estimatedUsd(float $seconds): float {
        // 720p output pricing only; input video cost is accounted separately by the caller.
        return round(max(4, min(30, $seconds)) * 0.30, 4);
    }

    public function submit(array $input): array {
        $video = trim((string)($input['prompt_video'] ?? $input['source_video'] ?? ''));
        $prompt = trim((string)($input['prompt_text'] ?? ''));
        if ($video === '') throw new InvalidArgumentException('Continuation source video is required.');
        if ($prompt === '') throw new InvalidArgumentException('Continuation prompt is required.');
        $duration = max(4, min(30, (int)round((float)($input['duration_seconds'] ?? 5))));
        $payload = [
            'model' => 'seedance2_5',
            'promptVideo' => $video,
            'promptText' => ad_substr($prompt, 0, 1000),
            'mode' => 'extend',
            'duration' => $duration,
            'resolution' => '720p',
        ];
        if (ad_mock_mode()) return ['external_id'=>ad_id('mock_seedance25_extend'),'status'=>'submitted','raw'=>['mock'=>true,'payload'=>$payload]];
        if ($this->key === '') throw new RuntimeException('RUNWAY_API_KEY is not configured.');
        $data = ad_http_json('POST', 'https://api.dev.runwayml.com/v1/video_to_video', [
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'X-Runway-Version: 2024-11-06',
        ], $payload);
        $id = (string)($data['id'] ?? '');
        if ($id === '') throw new RuntimeException('Runway returned no continuation task id.');
        return ['external_id'=>$id,'status'=>'submitted','raw'=>$data];
    }

    public function poll(string $externalId): array {
        if (ad_mock_mode()) return ['status'=>'succeeded','output_url'=>'','mock'=>true,'raw'=>['id'=>$externalId,'mock'=>true]];
        $data = ad_http_json('GET', 'https://api.dev.runwayml.com/v1/tasks/' . rawurlencode($externalId), [
            'Authorization: Bearer ' . $this->key,
            'X-Runway-Version: 2024-11-06',
        ]);
        $status = strtoupper((string)($data['status'] ?? 'PENDING'));
        if ($status === 'SUCCEEDED') {
            $output = $data['output'] ?? [];
            $url = is_array($output) ? (string)($output[0] ?? '') : '';
            return ['status'=>'succeeded','output_url'=>$url,'raw'=>$data];
        }
        if ($status === 'FAILED') return ['status'=>'failed','error'=>(string)($data['failure'] ?? 'Continuation generation failed.'),'raw'=>$data];
        return ['status'=>in_array($status,['RUNNING','THROTTLED'],true)?'processing':'queued','raw'=>$data];
    }
}
