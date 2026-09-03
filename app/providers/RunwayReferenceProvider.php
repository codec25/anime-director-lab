<?php
declare(strict_types=1);

final class RunwayReferenceProvider implements ADProvider {
    private string $key;
    public function __construct() { $this->key = ad_env('RUNWAY_API_KEY'); }
    public function id(): string { return 'runway_seedance25_reference'; }
    public function available(): bool { return ad_mock_mode() || $this->key !== ''; }
    public function estimatedUsd(float $seconds): float {
        // 720p output only. Reference video input cost is added by the caller.
        return round(max(4, min(30, $seconds)) * 0.30, 4);
    }

    private function normalizeRatio(string $ratio): string {
        $allowed = ['1470:630','1280:720','1112:834','960:960','834:1112','720:1280'];
        return in_array($ratio, $allowed, true) ? $ratio : '1280:720';
    }

    public function submit(array $input): array {
        $prompt = trim((string)($input['prompt_text'] ?? ''));
        $images = array_values(array_slice((array)($input['reference_images'] ?? []), 0, 30));
        $videos = array_values(array_slice((array)($input['reference_videos'] ?? []), 0, 10));
        $audio = array_values(array_slice((array)($input['reference_audio'] ?? []), 0, 10));
        if ($prompt === '' && !$images && !$videos && !$audio) throw new InvalidArgumentException('Direction or at least one reference is required.');

        $duration = max(4, min(30, (int)round((float)($input['duration_seconds'] ?? 5))));
        $payload = [
            'model' => 'seedance2_5',
            'ratio' => $this->normalizeRatio((string)($input['ratio'] ?? '1280:720')),
            'duration' => $duration,
            'audio' => !empty($input['generate_audio']),
        ];
        if ($prompt !== '') $payload['promptText'] = ad_substr($prompt, 0, 15000);
        if ($images) $payload['references'] = array_map(static fn(array $r): array => ['uri'=>(string)$r['uri']], $images);
        if ($videos) $payload['referenceVideos'] = array_map(static fn(array $r): array => ['type'=>'video','uri'=>(string)$r['uri']], $videos);
        if ($audio) $payload['referenceAudio'] = array_map(static fn(array $r): array => ['type'=>'audio','uri'=>(string)$r['uri']], $audio);

        if (ad_mock_mode()) return ['external_id'=>ad_id('mock_seedance25_reference'),'status'=>'submitted','raw'=>['mock'=>true,'payload'=>$payload]];
        if ($this->key === '') throw new RuntimeException('RUNWAY_API_KEY is not configured.');
        $data = ad_http_json('POST', 'https://api.dev.runwayml.com/v1/text_to_video', [
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'X-Runway-Version: 2024-11-06',
        ], $payload);
        $id = (string)($data['id'] ?? '');
        if ($id === '') throw new RuntimeException('Runway returned no reference-generation task id.');
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
        if ($status === 'FAILED') return ['status'=>'failed','error'=>(string)($data['failure'] ?? 'Reference generation failed.'),'raw'=>$data];
        return ['status'=>in_array($status,['RUNNING','THROTTLED'],true)?'processing':'queued','raw'=>$data];
    }
}
