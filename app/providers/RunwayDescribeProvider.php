<?php
declare(strict_types=1);

final class RunwayDescribeProvider implements ADProvider {
    private string $key;
    public function __construct() { $this->key = ad_env('RUNWAY_API_KEY'); }
    public function id(): string { return 'runway_gen45'; }
    public function available(): bool { return ad_mock_mode() || $this->key !== ''; }
    public function estimatedUsd(float $seconds): float { return round(max(2, min(10, $seconds)) * 0.12, 4); }

    /** @return list<string> */
    private function allowedRatios(): array {
        return ['1280:720', '720:1280', '1104:832', '832:1104', '960:960', '1584:672'];
    }

    private function normalizeRatio(string $ratio, bool $hasImage): string {
        if (!$hasImage) return in_array($ratio, ['1280:720', '720:1280'], true) ? $ratio : '1280:720';
        return in_array($ratio, $this->allowedRatios(), true) ? $ratio : '1280:720';
    }

    public function submit(array $input): array {
        $prompt = trim((string)($input['prompt_text'] ?? ''));
        if ($prompt === '') throw new InvalidArgumentException('Describe-shot prompt is required.');
        $image = trim((string)($input['character_url'] ?? $input['prompt_image'] ?? ''));
        $duration = max(2, min(10, (int)round((float)($input['duration_seconds'] ?? 5))));
        $payload = [
            'model' => 'gen4.5',
            'promptText' => ad_substr($prompt, 0, 1000),
            'ratio' => $this->normalizeRatio((string)($input['ratio'] ?? '1280:720'), $image !== ''),
            'duration' => $duration,
        ];
        if ($image !== '') $payload['promptImage'] = $image;
        if (ad_mock_mode()) return ['external_id' => ad_id('mock_runway_gen45'), 'status' => 'submitted', 'raw' => ['mock' => true, 'payload' => $payload]];
        if ($this->key === '') throw new RuntimeException('RUNWAY_API_KEY is not configured.');
        $data = ad_http_json('POST', 'https://api.dev.runwayml.com/v1/image_to_video', [
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'X-Runway-Version: 2024-11-06',
        ], $payload);
        $id = (string)($data['id'] ?? '');
        if ($id === '') throw new RuntimeException('Runway returned no task id.');
        return ['external_id' => $id, 'status' => 'submitted', 'raw' => $data];
    }

    public function poll(string $externalId): array {
        if (ad_mock_mode()) return ['status' => 'succeeded', 'output_url' => '', 'mock' => true, 'raw' => ['id' => $externalId, 'mock' => true]];
        $data = ad_http_json('GET', 'https://api.dev.runwayml.com/v1/tasks/' . rawurlencode($externalId), [
            'Authorization: Bearer ' . $this->key,
            'X-Runway-Version: 2024-11-06',
        ]);
        $status = strtoupper((string)($data['status'] ?? 'PENDING'));
        if ($status === 'SUCCEEDED') {
            $output = $data['output'] ?? [];
            $url = is_array($output) ? (string)($output[0] ?? '') : '';
            return ['status' => 'succeeded', 'output_url' => $url, 'raw' => $data];
        }
        if ($status === 'FAILED') return ['status' => 'failed', 'error' => (string)($data['failure'] ?? 'Runway generation failed.'), 'raw' => $data];
        return ['status' => in_array($status, ['RUNNING','THROTTLED'], true) ? 'processing' : 'queued', 'raw' => $data];
    }
}
